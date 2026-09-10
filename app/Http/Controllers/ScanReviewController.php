<?php

namespace App\Http\Controllers;

use App\Jobs\ExtractFileOcrText;
use App\Jobs\ProcessScanBatch;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\File;
use App\Models\Folder;
use App\Models\ScanBatch;
use App\Services\CloudFileUploader;
use App\Services\PdfToolkit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ScanReviewController extends Controller
{
    public const DOCUMENT_TYPES = [
        'Memo', 'Letter', 'Resolution', 'Notice', 'Invoice', 'Receipt', 'Contract', 'Report', 'Other',
    ];

    public const STATUSES = ['filed', 'for_review', 'archived', 'superseded'];

    /** The review queue. */
    public function index()
    {
        $batches = ScanBatch::where('is_archived', false)->latest()->limit(100)->get();
        $layout  = Folder::isSuperAdmin(Auth::user()) ? 'SuperAdmin.homeSuperAdmin' : 'Admin.home';

        return view('Admin.documents.scans', compact('batches', 'layout'));
    }

    /** JSON snapshot for the queue's polling refresh. */
    public function status()
    {
        $batches = ScanBatch::where('is_archived', false)->latest()->limit(100)->get()
            ->map(fn (ScanBatch $b) => [
                'id'                   => $b->id,
                'status'               => $b->status,
                'page_count'           => $b->page_count,
                'segment_count'        => $b->segment_count,
                'filed_document_count' => $b->filed_document_count,
                'original_filename'    => $b->original_filename,
                'updated_at'           => optional($b->updated_at)->diffForHumans(),
            ]);

        return response()->json(['batches' => $batches]);
    }

    /** Full-page review + split screen. */
    public function review(ScanBatch $batch)
    {
        if (! $batch->isReadyForReview() && ! $batch->isFailed()) {
            return redirect()->route('documents.scans.index')
                ->with('error', 'That batch is still being processed.');
        }

        $pageCount = $batch->page_count ?: 1;
        $pages     = range(1, $pageCount);
        $segments  = $batch->segments ?: [[
            'start_page' => 1,
            'end_page'   => $pageCount,
            'guessed'    => [],
        ]];

        $folders = Folder::where('is_archived', false)->get()
            ->filter(fn (Folder $f) => $f->isAccessibleBy(Auth::user()))
            ->values();

        $documentTypes = self::DOCUMENT_TYPES;
        $statuses      = self::STATUSES;
        $layout        = Folder::isSuperAdmin(Auth::user()) ? 'SuperAdmin.homeSuperAdmin' : 'Admin.home';

        return view('Admin.documents.review', compact(
            'batch', 'pages', 'segments', 'folders', 'documentTypes', 'statuses', 'layout'
        ));
    }

    /** Serve a cached, rasterized thumbnail of one batch page. */
    public function page(ScanBatch $batch, int $page, PdfToolkit $pdf)
    {
        abort_unless($page >= 1 && $page <= ($batch->page_count ?: 1), 404);

        // Once filed, a batch inherits its target folder's restriction
        // rules like every other file/document - without this check, a
        // document filed into a restricted folder stayed viewable to any
        // officer via this endpoint even though the folder itself was locked.
        abort_unless($batch->isAccessibleBy(Auth::user()), 403, 'You do not have access to this document.');

        $cacheRel = "scan_batches/{$batch->id}/thumbs/p{$page}.png";

        if (! Storage::disk('local')->exists($cacheRel)) {
            // Reads from wherever the original actually lives (older rows
            // may still be on the public disk from before this pipeline
            // moved to the private one) but the thumbnail cache always
            // lands on 'local' - see the stored_disk comment in ScanInbox.
            $rawPath = Storage::disk($batch->stored_disk)->path($batch->stored_path);
            $isImage = in_array(strtolower((string) $batch->extension), ['jpg', 'jpeg', 'png'], true);

            if ($isImage) {
                $this->cacheImageThumb($rawPath, $cacheRel);
            } else {
                $png = $pdf->rasterizePage($rawPath, $page, (int) config('scan.thumbnail_dpi'));
                Storage::disk('local')->put($cacheRel, file_get_contents($png));
                @unlink($png);
            }
        }

        return response()->file(
            Storage::disk('local')->path($cacheRel),
            ['Content-Type' => 'image/png']
        );
    }

    /**
     * Runs the inbox ingest right now instead of waiting for the next
     * schedule:run tick (up to a minute away). Lets an operator who just
     * dropped a file in the watched folder see it show up immediately.
     */
    public function checkNow(Request $request)
    {
        Artisan::call('documents:scan-inbox');

        preg_match('/Ingested (\d+) of/', Artisan::output(), $m);
        $ingested = (int) ($m[1] ?? 0);

        $message = $ingested > 0
            ? $ingested . ' new scan(s) found and queued for processing.'
            : 'No new scans found in the inbox.';

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'ingested' => $ingested, 'message' => $message]);
        }

        return back()->with('success', $message);
    }

    /** Re-queue a failed batch. */
    public function reprocess(ScanBatch $batch, Request $request)
    {
        if (! $batch->isFailed()) {
            return back()->with('error', 'Only a failed batch can be re-processed.');
        }

        $batch->update(['status' => 'extracting', 'error' => null]);
        ProcessScanBatch::dispatch($batch->id);

        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => "Re-processing scan batch #{$batch->id}",
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', "Re-processing scan batch #{$batch->id}.");
    }

    /** Discard a batch and its stored artefacts. */
    public function destroyBatch(ScanBatch $batch, Request $request)
    {
        if ($batch->isFiled() && ! Folder::isSuperAdmin(Auth::user())) {
            return back()->with('error', 'A filed batch can only be discarded by a SuperAdmin.');
        }

        // Clean up both disks - a no-op on whichever one this batch never
        // touched. Covers rows ingested before the pipeline moved off the
        // public disk (see ScanInbox), which still have artefacts there.
        Storage::disk('local')->deleteDirectory('scan_batches/' . $batch->id);
        Storage::disk('public')->deleteDirectory('scan_batches/' . $batch->id);
        $batch->delete();

        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => "Discarded scan batch #{$batch->id} ({$batch->original_filename})",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('documents.scans.index')->with('success', 'Scan batch discarded.');
    }

    /**
     * Split the batch into the reviewed segments and file each as its own
     * Document + File. Slow IO (PDF split, cloud upload) runs outside the DB
     * transaction so a slow Supabase call never holds a row lock - the same
     * trade-off ExtractFileOcrText::replaceWithSearchablePdf() makes.
     */
    public function file(Request $request, ScanBatch $batch, PdfToolkit $pdf, CloudFileUploader $uploader)
    {
        if ($batch->status !== 'ready_for_review') {
            return $this->fileError($request, 'This batch has already been filed or is not ready for review.');
        }

        $maxPage = $batch->page_count ?: 9999;

        $data = $request->validate([
            'target_folder_id'         => 'required|exists:folders,id',
            'segments'                 => 'required|array|min:1',
            'segments.*.start_page'    => "required|integer|min:1|max:{$maxPage}",
            'segments.*.end_page'      => "required|integer|min:1|max:{$maxPage}",
            'segments.*.title'         => 'required|string|max:255',
            'segments.*.document_type' => 'nullable|string|max:100',
            'segments.*.reference_no'  => 'nullable|string|max:100',
            'segments.*.document_date' => 'nullable|date',
            'segments.*.date_received' => 'required|date',
            'segments.*.sender'        => 'nullable|string|max:255',
            'segments.*.recipient'     => 'nullable|string|max:255',
            'segments.*.status'        => 'nullable|string|max:50',
            'segments.*.notes'         => 'nullable|string',
        ]);

        foreach ($data['segments'] as $i => $seg) {
            if ((int) $seg['end_page'] < (int) $seg['start_page']) {
                throw ValidationException::withMessages([
                    "segments.{$i}.end_page" => 'The end page cannot be before the start page.',
                ]);
            }
        }

        $folder = Folder::findOrFail($data['target_folder_id']);

        if ($folder->is_archived) {
            return $this->fileError($request, 'That folder is stored under a past school year and is read-only.');
        }

        if (! $folder->isAccessibleBy(Auth::user())) {
            abort(403, 'You do not have access to that folder.');
        }

        // Claim the batch so a second submit can't double-file it.
        $batch->update(['status' => 'filing']);

        [$rawPdf, $rawPdfIsTemp] = $this->resolveRawPdf($batch, $pdf);

        $existingNames = File::where('folder_id', $folder->id)->pluck('filename')->flip()->all();

        $prepared = [];

        try {
            foreach ($data['segments'] as $seg) {
                $start = (int) $seg['start_page'];
                $end   = (int) $seg['end_page'];

                $segPdf = $pdf->extractRange($rawPdf, $start, $end);

                try {
                    $base = Str::slug($seg['title']) ?: 'document';
                    $name = $base;
                    $c    = 0;
                    while (isset($existingNames["{$name}.pdf"])) {
                        $c++;
                        $name = "{$base}-{$c}";
                    }
                    $existingNames["{$name}.pdf"] = true;

                    $cloudPath = time() . '_' . Str::slug($base) . '_' . $c . '_' . uniqid() . '.pdf';
                    $localPath = 'uploads/' . $folder->id . '/' . Str::random(40) . '.pdf';

                    Storage::disk('public')->put($localPath, file_get_contents($segPdf));

                    $uploaded = $uploader->upload($cloudPath, Storage::disk('public')->path($localPath));

                    $prepared[] = [
                        'seg'          => $seg,
                        'filename'     => "{$name}.pdf",
                        'filepath'     => $cloudPath,
                        'local_path'   => $localPath,
                        'size'         => filesize($segPdf) ?: null,
                        'storage_type' => $uploaded ? 'both' : 'local',
                        'page_count'   => $end - $start + 1,
                        'start_page'   => $start,
                        'end_page'     => $end,
                    ];
                } finally {
                    @unlink($segPdf);
                }
            }
        } catch (\Throwable $e) {
            // Roll the batch back so the operator can retry.
            $batch->update(['status' => 'ready_for_review']);
            if ($rawPdfIsTemp) {
                @unlink($rawPdf);
            }
            throw $e;
        }

        if ($rawPdfIsTemp) {
            @unlink($rawPdf);
        }

        $fileIds = [];

        DB::transaction(function () use ($prepared, $folder, $batch, &$fileIds) {
            foreach ($prepared as $row) {
                $seg = $row['seg'];

                $file = File::create([
                    'filename'     => $row['filename'],
                    'filepath'     => $row['filepath'],
                    'local_path'   => $row['local_path'],
                    'folder_id'    => $folder->id,
                    'size'         => $row['size'],
                    'storage_type' => $row['storage_type'],
                    'ocr_status'   => 'pending',
                ]);

                Document::create([
                    'file_id'       => $file->id,
                    'folder_id'     => $folder->id,
                    'scan_batch_id' => $batch->id,
                    'created_by'    => Auth::id(),
                    'title'         => $seg['title'],
                    'document_type' => $seg['document_type'] ?? null,
                    'reference_no'  => $seg['reference_no'] ?? null,
                    'document_date' => $seg['document_date'] ?? null,
                    'date_received' => $seg['date_received'],
                    'sender'        => $seg['sender'] ?? null,
                    'recipient'     => $seg['recipient'] ?? null,
                    'status'        => $seg['status'] ?: 'filed',
                    'notes'         => $seg['notes'] ?? null,
                    'page_count'    => $row['page_count'],
                    'start_page'    => $row['start_page'],
                    'end_page'      => $row['end_page'],
                ]);

                $fileIds[] = $file->id;
            }

            $batch->update([
                'status'               => 'filed',
                'filed_at'             => now(),
                'filed_by'             => Auth::id(),
                'filed_document_count' => count($prepared),
                'segments'             => $prepared === [] ? $batch->segments : $this->finalSegments($prepared),
                'target_folder_id'     => $folder->id,
            ]);
        });

        foreach ($fileIds as $id) {
            ExtractFileOcrText::dispatch($id, false);
        }

        $folder->touchAccessed();

        $titles = collect($prepared)->pluck('seg.title')->implode(', ');
        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => "Filed scan batch #{$batch->id} into \"{$folder->name}\" as " . count($prepared)
                . ' document(s): ' . $titles,
            'ip_address' => $request->ip(),
        ]);

        $message = count($prepared) . ' document(s) filed into "' . $folder->name . '".';

        if ($request->expectsJson()) {
            return response()->json([
                'ok'       => true,
                'redirect' => route('documents.scans.index'),
                'message'  => $message,
            ]);
        }

        return redirect()->route('documents.scans.index')->with('success', $message);
    }

    /** @return array{0: string, 1: bool} [pdf path, whether caller must delete it] */
    private function resolveRawPdf(ScanBatch $batch, PdfToolkit $pdf): array
    {
        $rawPath = Storage::disk($batch->stored_disk)->path($batch->stored_path);
        $isImage = in_array(strtolower((string) $batch->extension), ['jpg', 'jpeg', 'png'], true);

        return $isImage
            ? [$pdf->imageToPdf($rawPath), true]
            : [$rawPath, false];
    }

    private function finalSegments(array $prepared): array
    {
        return array_map(fn ($row) => [
            'start_page' => $row['start_page'],
            'end_page'   => $row['end_page'],
            'title'      => $row['seg']['title'],
        ], $prepared);
    }

    private function cacheImageThumb(string $rawImagePath, string $cacheRel): void
    {
        $src = @imagecreatefromstring(file_get_contents($rawImagePath));

        if (! $src) {
            Storage::disk('local')->put($cacheRel, file_get_contents($rawImagePath));
            return;
        }

        $width  = imagesx($src);
        $target = min($width, 1000);
        $scaled = imagescale($src, $target);
        $out    = $scaled !== false ? $scaled : $src;

        ob_start();
        imagepng($out);
        Storage::disk('local')->put($cacheRel, ob_get_clean());

        imagedestroy($src);
        if ($scaled !== false) {
            imagedestroy($scaled);
        }
    }

    private function fileError(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'message' => $message], 422);
        }

        return back()->with('error', $message);
    }
}
