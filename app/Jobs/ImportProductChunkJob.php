<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\ImportLog;
use App\Constants\ImportStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportProductChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public function __construct(
        public array $rows,
        public int $jobId
    ) {}

    /**
     * Execute the job
     */
    public function handle(): void
    {
        Log::channel('import')->debug('Processing import chunk', [
            'job_id' => $this->jobId,
            'chunk_size' => count($this->rows)
        ]);

        $successCount = 0;
        $failedCount = 0;

        foreach ($this->rows as $index => $row) {
            try {
                $this->validateRow($row);
                $this->processRow($row);
                $successCount++;

            } catch (\Throwable $e) {
                $failedCount++;

                Log::channel('import')->warning('Row import failed', [
                    'job_id' => $this->jobId,
                    'row_index' => $index,
                    'sku' => $row['sku'] ?? null,
                    'error' => $e->getMessage()
                ]);

                continue;
            }
        }

        $this->updateImportLog($successCount, $failedCount);

        Log::channel('import')->info('Import chunk finished', [
            'job_id' => $this->jobId,
            'success' => $successCount,
            'failed' => $failedCount,
            'total' => count($this->rows)
        ]);
    }


    /**
     * Process single row
     */
    protected function processRow(array $row): void
    {
        // Format and validate data
        $productData = [
            'sku' => trim($row['sku']),
            'name' => trim($row['name']),
            'price' => $this->formatPrice($row['price']),
            'stock' => $this->formatStock($row['stock'] ?? 0),
        ];

        // Check for duplicate SKU in this chunk (optional)
        // Could track SKUs processed in this chunk
        
        Product::updateOrCreate(
            ['sku' => $productData['sku']],
            [
                'name' => $productData['name'],
                'price' => $productData['price'],
                'stock' => $productData['stock'],
            ]
        );
    }

    /**
     * Validate CSV row
     */
    protected function validateRow(array $row): void
    {
        // Check required fields
        $required = ['sku', 'name', 'price'];
        foreach ($required as $field) {
            if (!isset($row[$field]) || trim($row[$field]) === '') {
                throw new \InvalidArgumentException(
                    "Missing required field: {$field}"
                );
            }
        }

        // Validate price is numeric
        if (!is_numeric($row['price'])) {
            throw new \InvalidArgumentException(
                "Price must be numeric: {$row['price']}"
            );
        }

        // Validate price positive
        if ((float) $row['price'] < 0) {
            throw new \InvalidArgumentException(
                "Price must be positive: {$row['price']}"
            );
        }

        // Validate stock if provided
        if (isset($row['stock']) && !is_numeric($row['stock'])) {
            throw new \InvalidArgumentException(
                "Stock must be numeric: {$row['stock']}"
            );
        }
    }

    /**
     * Format price value
     */
    protected function formatPrice($price): float
    {
        // Remove any non-numeric characters except decimal point
        $price = preg_replace('/[^0-9.-]/', '', (string) $price);
        return (float) number_format((float) $price, 2, '.', '');
    }

    /**
     * Format stock value
     */
    protected function formatStock($stock): int
    {
        return (int) $stock;
    }

    /**
     * Update import log with results
     */
    protected function updateImportLog(int $success, int $failed): void
    {
        // Use atomic updates to prevent race conditions
        DB::transaction(function () use ($success, $failed) {
            $importLog = ImportLog::lockForUpdate()->find($this->jobId);
            
            if ($importLog) {
                $importLog->increment('success', $success);
                $importLog->increment('failed', $failed);
            }
        });
    }

    /**
     * Handle job failure
     */
    public function failed(Throwable $e): void
    {
        Log::channel('import')->error('ImportProductChunkJob failed', [
            'job_id' => $this->jobId,
            'chunk_size' => count($this->rows),
            'error' => $e->getMessage()
        ]);

        // Important: DO NOT update the main import status here
        // Only mark the rows in this chunk as failed
        $this->updateImportLog(0, count($this->rows));
        
        // The main ImportProductJob will handle overall status
    }
}