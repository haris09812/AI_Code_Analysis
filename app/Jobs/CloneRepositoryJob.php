<?php
// app/Jobs/CloneRepositoryJob.php

namespace App\Jobs;

use App\Enums\AnalysisStatus;
use App\Models\AnalysisJob;
use App\Services\GitCloneService;
use App\Services\FileExtractorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class CloneRepositoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 1000;

    public int $backoff = 30;

    public function __construct(
        public readonly AnalysisJob $analysisJob
    ) {}

    public function handle(GitCloneService $cloner, FileExtractorService $extractor) {

        $clonePath = null;

        try {
            $this->analysisJob->update([
                'status'     => AnalysisStatus::CLONING,
                'started_at' => now(),
            ]);

            Log::info('CloneRepositoryJob: Starting clone', [
                'job_id' => $this->analysisJob->id,
                'repo'   => $this->analysisJob->repository->github_url,
            ]);

            $clonePath = $cloner->clone(
                $this->analysisJob->repository->github_url,
                $this->analysisJob->id
            );

            $files = $extractor->extract($clonePath);
            $stats = $extractor->getStats($files);

            Log::info('CloneRepositoryJob: Files extracted', [
                'job_id'      => $this->analysisJob->id,
                'total_files' => $stats['total_files'],
                'size_kb'     => $stats['total_size_kb'],
            ]);

            if (empty($files)) {
                $this->analysisJob->update([
                    'status'       => AnalysisStatus::COMPLETED,
                    'total_files'  => 0,
                    'completed_at' => now(),
                ]);
                return;
            }

            $this->analysisJob->update([
                'status'      => AnalysisStatus::PROCESSING,
                'total_files' => $stats['total_files'],
            ]);

            $batches   = array_chunk($files, config('analyzer.batch_size', 8));
            $batchNum  = 0;

            foreach ($batches as $batch) {
                $batchNum++;

                AnalyzeFileBatchJob::dispatch(
                    $this->analysisJob,
                    $batch,
                    $batchNum,
                    count($batches)
                )
                ->onQueue('analysis')
                ->delay(now()->addSeconds($batchNum * 2));
            }

            Log::info('CloneRepositoryJob: Batches dispatched', [
                'job_id'        => $this->analysisJob->id,
                'total_batches' => count($batches),
            ]);

        } finally {
            // if ($clonePath) {
            //     $cloner->cleanup($clonePath);
            //     Log::info('CloneRepositoryJob: Cleanup done', ['path' => $clonePath]);
            // }
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('CloneRepositoryJob: FAILED', [
            'job_id' => $this->analysisJob->id,
            'error'  => $exception->getMessage(),
        ]);

        $this->analysisJob->update([
            'status'        => AnalysisStatus::FAILED,
            'error_message' => $exception->getMessage(),
            'completed_at'  => now(),
        ]);
    }
}
