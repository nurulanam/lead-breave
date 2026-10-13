<?php

namespace App\Jobs;

use App\Models\LeadImport;
use App\Services\Leads\LeadImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Imports one chunk of a CSV, then queues the next, so a large file never
 * runs into the worker's time limit.
 */
class ImportLeadsCsv implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $importId, public int $offset = 0) {}

    public function handle(LeadImporter $importer): void
    {
        $import = LeadImport::query()->find($this->importId);

        if ($import === null || $import->isFinished() || $import->processed_rows !== $this->offset) {
            return;
        }

        if ($import->started_at === null) {
            $import->forceFill(['status' => LeadImport::STATUS_RUNNING, 'started_at' => now()])->save();
        }

        $read = $importer->importChunk($import, Storage::disk('local')->path($import->file_path), $this->offset);

        if ($read === LeadImporter::CHUNK) {
            self::dispatch($import->id, $this->offset + $read);

            return;
        }

        $import->forceFill(['status' => LeadImport::STATUS_COMPLETED, 'completed_at' => now()])->save();
    }

    public function failed(?Throwable $exception): void
    {
        LeadImport::query()->whereKey($this->importId)->update([
            'status' => LeadImport::STATUS_FAILED,
            'completed_at' => now(),
            'errors' => json_encode([['row' => $this->offset, 'reason' => 'The import stopped: '.($exception?->getMessage() ?? 'unknown error')]]),
        ]);
    }
}
