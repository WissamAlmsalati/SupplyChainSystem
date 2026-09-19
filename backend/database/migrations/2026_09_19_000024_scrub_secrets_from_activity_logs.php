<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The activity log used to copy every changed column, password hash included.
// New rows no longer do; this removes the hashes already written.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('activity_logs')
            ->where('metadata', 'like', '%password%')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $metadata = json_decode($row->metadata, true);
                    if (! is_array($metadata) || ! isset($metadata['changes']['password'])) {
                        continue;
                    }
                    $metadata['changes']['password'] = '••••••';
                    unset($metadata['changes']['remember_token']);
                    DB::table('activity_logs')->where('id', $row->id)->update([
                        'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Nothing to restore: the point is that the hashes are gone.
    }
};
