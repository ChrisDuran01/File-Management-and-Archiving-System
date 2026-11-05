<?php

namespace App\Services;

use App\Models\File;
use App\Models\Folder;

/**
 * "Looks like a Resolution · Move": a file whose title names one kind of
 * document (DocumentKind) but sits in a folder named for another kind, and
 * the folder it most likely belongs in. The same title rule as the Reports
 * "wrong folder" panel (ArchiveInsights), shown on the file itself once its
 * text has been read after upload.
 *
 * Only folders whose name says what they hold are judged: a folder named
 * after an event or a school year may hold any kind. Files without a title
 * line (letters) never get a hint, and neither does a file an officer said
 * to keep where it is (folder_hint_dismissed_at).
 */
class FolderHint
{
    public function __construct(private readonly DocumentKind $kinds)
    {
    }

    /**
     * @param  iterable<File>  $files  the files of $folder
     * @param  iterable<Folder>  $targets  where the viewer may move them (Folder::moveTargetsFor)
     * @return array<int, array{kind: string, folder: Folder}> file id => hint
     */
    public function forFiles(Folder $folder, iterable $files, iterable $targets): array
    {
        $ownKinds = $this->kinds->forFolderName($folder->name);
        if (! $ownKinds || $folder->is_archived) {
            return [];
        }

        $hints = [];
        foreach ($files as $file) {
            if ($file->folder_hint_dismissed_at || $file->is_archived) {
                continue;
            }
            // The folder page loads only the start of the text (File::forListing).
            $kind = $this->kinds->fromText(array_key_exists('ocr_head', $file->getAttributes()) ? $file->ocr_head : $file->ocr_text);
            if (! $kind || in_array($kind, $ownKinds, true)) {
                continue;
            }
            if ($target = $this->folderFor($kind, $targets)) {
                $hints[$file->id] = ['kind' => $this->kinds->label($kind), 'folder' => $target];
            }
        }

        return $hints;
    }

    /**
     * The folder named for $kind that already holds the most files (the
     * one the SG actually uses), the older one on a tie. Null when no
     * folder is named for it: then there is nowhere to suggest.
     */
    private function folderFor(string $kind, iterable $targets): ?Folder
    {
        return collect($targets)
            ->filter(fn (Folder $f) => in_array($kind, $this->kinds->forFolderName($f->name), true))
            ->sort(fn (Folder $a, Folder $b) => [$b->file_count ?? 0, $a->id] <=> [$a->file_count ?? 0, $b->id])
            ->first();
    }
}
