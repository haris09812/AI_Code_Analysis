<?php
// app/Jobs/AnalyzeFileBatchJob.php

namespace App\Jobs;

use App\Enums\AnalysisStatus;
use App\Models\AnalysisJob;
use App\Models\AnalysisResult;
use App\Services\AIDetectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnalyzeFileBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 180;
    public int $backoff = 30;

    public function __construct(
        public readonly AnalysisJob $analysisJob,
        public readonly array       $files,
        public readonly int         $batchNumber,
        public readonly int         $totalBatches
    ) {}

    public function handle(AIDetectionService $detector)
    {
        Log::info('AnalyzeFileBatchJob: Starting', [
            'job_id'         => $this->analysisJob->id,
            'batch'          => "{$this->batchNumber}/{$this->totalBatches}",
            'files_in_batch' => count($this->files),
        ]);

        $filesToAnalyze = [];
        $results        = [];
        $processedCount = 0;

        foreach ($this->files as $file) {
            try {
                $content = file_get_contents($file['full_path']);

                if ($content === false) {
                    Log::warning('AnalyzeFileBatchJob: Could not read file', [
                        'path' => $file['rel_path'],
                    ]);
                    $processedCount++;
                    continue;
                }

                $hScore = $detector->getHeuristicScore($content);

                if ($hScore <= 15 || $hScore >= 85) {
                    $class   = $hScore <= 15 ? 'human' : 'ai';
                    $results[] = $this->mapResult($file, [
                        'classification'   => $class,
                        'confidence_score' => $hScore,
                        'explanation'      => 'Confirmed via heuristic analysis.',
                        'signals'          => [],
                        'source'           => 'heuristic',
                    ]);
                    $processedCount++;
                } else {
                    $filesToAnalyze[] = [
                        'path'     => $file['rel_path'],
                        'content'  => $content,
                        'file_ref' => $file,
                    ];
                }

            } catch (Throwable $e) {
                Log::error('AnalyzeFileBatchJob: Heuristic pass failed', [
                    'job_id' => $this->analysisJob->id,
                    'file'   => $file['rel_path'],
                    'error'  => $e->getMessage(),
                ]);
                $processedCount++;
            }
        }

        if (!empty($filesToAnalyze)) {
            try {
                $aiResults = $detector->analyzeBatch($filesToAnalyze);

                if(!empty($aiResults)) {
                    foreach ($aiResults as $aiRes) {
                        $results[] = [
                            'analysis_job_id'  => $this->analysisJob->id,
                            'file_path'        => $aiRes['file_path'],
                            'file_extension'   => pathinfo($aiRes['file_path'], PATHINFO_EXTENSION),
                            'classification'   => $aiRes['classification'],
                            'confidence_score' => $aiRes['confidence_score'],
                            'explanation'      => $aiRes['explanation'] ?? '',
                            'signals'          => json_encode($aiRes['signals'] ?? []),
                            'source'           => 'llm',
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ];
                        $processedCount++;  // ← FIX 3: LLM result aane ke baad count karo
                    }
                }
                else
                    {
                    Log::warning('AnalyzeFileBatchJob: LLM empty, using heuristic for all uncertain files', [
                        'count' => count($filesToAnalyze),
                    ]);

                    $returnedPaths = array_column($aiResults, 'file_path');

                     foreach ($filesToAnalyze as $f) {
                        if (!in_array($f['path'], $returnedPaths)) {
                            $hScore    = $detector->getHeuristicScore($f['content']);
                            $class     = match(true) {
                                $hScore >= 65 => 'ai',
                                $hScore <= 35 => 'human',
                                default       => 'uncertain',
                            };
                            $results[] = $this->mapResult($f['file_ref'], [
                                'classification'   => $class,
                                'confidence_score' => $hScore,
                                'explanation'      => 'LLM response mein file missing. Heuristic fallback used.',
                                'signals'          => [],
                                'source'           => 'heuristic',
                            ]);
                            $processedCount++;
                        }
                    }
                }
            } catch (Throwable $e) {
                Log::error('AnalyzeFileBatchJob: LLM batch call failed', [
                    'job_id' => $this->analysisJob->id,
                    'error'  => $e->getMessage(),
                ]);

                // Poora LLM batch fail — sab ko fallback do
                foreach ($filesToAnalyze as $f) {
                    $hScore    = $detector->getHeuristicScore($f['content']);
                    $results[] = $this->mapResult($f['file_ref'], [
                        'classification'   => 'uncertain',
                        'confidence_score' => $hScore,
                        'explanation'      => 'LLM batch failed. Heuristic fallback used.',
                        'signals'          => [],
                        'source'           => 'fallback',
                    ]);
                    $processedCount++;
                }
            }
        }

        if (!empty($results)) {
            AnalysisResult::insert($results);  

            Log::info('AnalyzeFileBatchJob: Results inserted', [
                'job_id' => $this->analysisJob->id,
                'batch'  => $this->batchNumber,
                'count'  => count($results),
            ]);
        }

        $this->incrementProcessed($processedCount);
        $this->checkJobCompletion();
    }

    protected function mapResult(array $file, array $data): array {
        return [
            'analysis_job_id'  => $this->analysisJob->id,
            'file_path'        => $file['rel_path'],
            'file_extension'   => $file['extension'],
            'classification'   => $data['classification'],
            'confidence_score' => $data['confidence_score'],
            'explanation'      => $data['explanation'],
            'signals'          => json_encode($data['signals']),
            'source'           => $data['source'],
            'created_at'       => now(),
            'updated_at'       => now(),
        ];
    }

    protected function incrementProcessed(int $count = 1): void
    {
        DB::table('analysis_jobs')
            ->where('id', $this->analysisJob->id)
            ->increment('processed_files', $count);
    }

    protected function checkJobCompletion(): void
    {
        DB::transaction(function () {

            $job = AnalysisJob::where('id', $this->analysisJob->id)
                ->lockForUpdate()
                ->first();

            if (in_array($job->status, [AnalysisStatus::COMPLETED, AnalysisStatus::FAILED])) {
                return;
            }

            if ($job->processed_files >= $job->total_files) {
                $job->update([
                    'status'       => AnalysisStatus::COMPLETED,
                    'completed_at' => now(),
                ]);

            $cloner = app(\App\Services\GitCloneService::class);
            $clonePath = $cloner->getClonePath($job->id);
            $cloner->cleanup($clonePath);

                Log::info('AnalyzeFileBatchJob: Job COMPLETED', [
                    'job_id'          => $job->id,
                    'total_files'     => $job->total_files,
                    'processed_files' => $job->processed_files,
                    'duration'        => $job->started_at->diffInSeconds(now()) . 's',
                ]);
            }
        });
    }

    public function failed(Throwable $exception): void
    {
        Log::error('AnalyzeFileBatchJob: FAILED', [
            'job_id' => $this->analysisJob->id,
            'batch'  => $this->batchNumber,
            'error'  => $exception->getMessage(),
        ]);

        $job = AnalysisJob::find($this->analysisJob->id);

        if ($job && !$job->isCompleted()) {
            $failedBatches = cache()->increment("failed_batches_{$this->analysisJob->id}");

            $threshold = (int) ceil($this->totalBatches * 0.5);

            if ($failedBatches >= $threshold) {
                $job->update([
                    'status'        => AnalysisStatus::FAILED,
                    'error_message' => 'Too many batch failures: ' . $exception->getMessage(),
                    'completed_at'  => now(),
                ]);
            }
        }
    }

}
