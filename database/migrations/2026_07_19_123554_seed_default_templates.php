<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        DB::table('templates')->insert([
            [
                'name' => 'Standard Resolution',
                'category' => 'resolution',
                'content' => <<<TEXT
                RESOLUTION NO. {{resolution_no}}
                Series of {{year}}

                {{title}}

                Date: {{date}}
                Authored by: {{author}}

                {{body}}

                APPROVED this {{date}} at {{location}}.
                TEXT,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Standard Request Letter',
                'category' => 'request_letter',
                'content' => <<<TEXT
                {{date}}

                {{recipient}}

                Subject: {{subject}}

                Dear {{recipient}},

                {{body}}

                Respectfully yours,

                {{sender}}
                TEXT,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Standard Memorandum',
                'category' => 'memorandum',
                'content' => <<<TEXT
                MEMORANDUM NO. {{memo_no}}

                To: {{to}}
                From: {{from}}
                Date: {{date}}
                Subject: {{subject}}

                {{body}}
                TEXT,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('templates')->whereIn('name', [
            'Standard Resolution',
            'Standard Request Letter',
            'Standard Memorandum',
        ])->delete();
    }
};
