<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportCsvRequest;
use App\Services\ImportService;
use App\Models\ImportLog;
use Illuminate\Http\JsonResponse;

class ProductImportController extends Controller
{
    /**
     * Start CSV import
     */
    public function import(
        ImportCsvRequest $request,
        ImportService $service
    ): JsonResponse {
        $result = $service->importCsv($request->file('file'));

        return response()->json($result, 202);
    }

    /**
     * Get import job status
     */
    public function status(int $id): JsonResponse
    {
        $job = ImportLog::findOrFail($id);

        return response()->json([
            'job_id'     => $job->id,
            'status'     => $job->status,
            'total'      => $job->total,
            'success'    => $job->success,
            'failed'     => $job->failed,
            'updated_at' => $job->updated_at->format('Y-m-d H:i:s'),
        ]);
    }
}
