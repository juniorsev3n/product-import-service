<?php

namespace App\Services;

use App\Models\ImportLog;
use App\Jobs\ImportProductJob;
use App\Constants\ImportStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ImportService
{
    /**
     * Handle CSV import request
     */
    public function importCsv(UploadedFile $file): array
    {
        if (!$file->isValid()) {
            throw new RuntimeException('Invalid uploaded file: ' . $file->getErrorMessage());
        }

        // Store file
        $path = $file->store('imports');

        // Quick validation
        $total = $this->countCsvRows($path);

        if ($total === 0) {
            // Cleanup empty file
            \Illuminate\Support\Facades\Storage::delete($path);
            throw new RuntimeException('CSV contains no data (excluding header)');
        }

        // Create import job record
        $job = ImportLog::create([
            'filename'  => $file->getClientOriginalName(),
            'status'    => ImportStatus::PENDING,
            'total'     => $total,
            'success'   => 0,
            'failed'    => 0,
        ]);

        // Dispatch background job
        ImportProductJob::dispatch($path, $job->id)
            ->onQueue(config('import.queue', 'imports'));

        Log::channel('import')->info('Import job started', [
            'job_id' => $job->id,
            'filename' => $file->getClientOriginalName(),
            'total' => $total
        ]);

        return [
            'job_id' => $job->id,
            'status' => $job->status
        ];
    }

    /**
     * Count CSV rows safely (skip header)
     */
    protected function countCsvRows(string $path): int
    {
        $file = storage_path('app/' . $path);

        if (!file_exists($file)) {
            throw new RuntimeException('CSV file not found');
        }

        $handle = @fopen($file, 'r');
        if ($handle === false) {
            throw new RuntimeException('Cannot open CSV file');
        }

        try {
            // Skip header
            fgetcsv($handle);

            $count = 0;
            while (($row = fgetcsv($handle)) !== false) {
                // Skip completely empty rows
                if (!empty(array_filter($row))) {
                    $count++;
                }
            }

            return $count;
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }
}