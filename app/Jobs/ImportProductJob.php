<?php

namespace App\Jobs;

use App\Models\ImportLog;
use App\Constants\ImportStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [30, 60, 120];

    public function __construct(
        public string $path,
        public int $jobId
    ) {}

    public function handle(): void
    {
        $importLog = ImportLog::findOrFail($this->jobId);
        $importLog->update(['status' => ImportStatus::IN_PROGRESS]);

        $file = storage_path('app/' . $this->path);

        if (!file_exists($file)) {
            throw new \RuntimeException('CSV file not found');
        }

        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);

        if (!$header) {
            fclose($handle);
            throw new \RuntimeException('Invalid CSV header');
        }

        // Validasi header minimal
        $header = array_map('strtolower', $header);
        $expected = ['name', 'sku', 'price', 'stock'];
        
        foreach ($expected as $column) {
            if (!in_array($column, $header)) {
                fclose($handle);
                throw new \RuntimeException("Missing required column: {$column}");
            }
        }

        $jobs = [];
        $chunk = [];
        $total = 0;
        $chunkSize = config('import.chunk_size', 500);

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($header)) {
                ImportLog::where('id', $this->jobId)->increment('failed');
                continue;
            }

            $chunk[] = array_combine($header, $row);
            $total++;

            if (count($chunk) === $chunkSize) {
                $jobs[] = new ImportProductChunkJob($chunk, $this->jobId);
                $chunk = [];
                
                // Prevent memory issues untuk file besar
                if (count($jobs) % 20 === 0) {
                    gc_collect_cycles();
                }
            }
        }

        if (!empty($chunk)) {
            $jobs[] = new ImportProductChunkJob($chunk, $this->jobId);
        }

        fclose($handle);

        if ($importLog->total === 0) {
            $importLog->update(['total' => $total]);
        }

        // Jika tidak ada data
        if (empty($jobs)) {
            $importLog->update(['status' => ImportStatus::COMPLETED]);
            return;
        }

        Bus::batch($jobs)
            ->then(function () use ($importLog) {
                $importLog->update(['status' => ImportStatus::COMPLETED]);
            })
            ->catch(function (Throwable $e) use ($importLog) {
                Log::channel('import')->error('Import batch failed', [
                    'job_id' => $importLog->id,
                    'error' => $e->getMessage()
                ]);
                $importLog->update(['status' => ImportStatus::FAILED]);
            })
            ->dispatch();
    }

    public function failed(Throwable $e): void
    {
        Log::channel('import')->error('ImportProductJob failed', [
            'job_id' => $this->jobId,
            'error' => $e->getMessage(),
            'file' => $this->path
        ]);
        
        ImportLog::where('id', $this->jobId)
            ->update(['status' => ImportStatus::FAILED]);
    }
}