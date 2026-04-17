<?php
// app/Services/AnalysisOrchestrator.php

namespace App\Services;

use App\Enums\AnalysisStatus;
use App\Jobs\CloneRepositoryJob;
use App\Models\AnalysisJob;
use App\Models\Repository;
use Exception;
use Illuminate\Support\Facades\Log;

class AnalysisOrchestrator
{
    public function __construct(
        protected GitHubService $github
    ) {}

    public function initiate(string $githubUrl): AnalysisJob
    {
        [$owner, $repo] = $this->github->parseRepoUrl($githubUrl);
        $metadata = null;

        // $metadata = $this->github->getRepoMetadata($githubUrl);
        // dd($metadata);

        try {
            $metadata = $this->github->getRepoMetadata($githubUrl);
            Log::info('AnalysisOrchestrator: Metadata fetched', [
                'owner'       => $owner,
                'repo'        => $repo,
                'stars'       => $metadata['stats']['stars'] ?? 'MISSING',
                'description' => $metadata['identity']['description'] ?? 'MISSING',
            ]);
        } catch (\Exception $e) {
            Log::warning('AnalysisOrchestrator: Metadata fetch failed', [
                'url'   => $githubUrl,
                'error' => $e->getMessage(),
            ]);
        }



        $repository = Repository::updateOrCreate(
            [
                'owner'          => $owner,
                'repo_name'      => $repo,
                ],
                [
                    'github_url'     => $githubUrl,
                    'description'    => $metadata['identity']['description'] ?? null,

                    'stars'          => $metadata['stats']['stars'] ?? 0,
                    'forks'          => $metadata['stats']['forks'] ?? 0,
                    'languages'      => $metadata['stats']['languages'] ?? [],

                'default_branch' => $metadata['history']['default_branch'] ?? 'main',
                'repo_age'       => $metadata['history']['repo_age'] ?? null,

                'open_prs'       => $metadata['pull_requests']['open'] ?? 0,
                'merged_prs'     => $metadata['pull_requests']['merged'] ?? 0,

                'contributors'   => $metadata['contributors'] ?? [],
                'recent_commits' => $metadata['recent_commits'] ?? [],

                'is_private'     => false,
                ]
        );

        $runningJob = AnalysisJob::where('repository_id', $repository->id)
            ->whereIn('status', [
                AnalysisStatus::PENDING,
                AnalysisStatus::CLONING,
                AnalysisStatus::PROCESSING,
            ])
            ->latest()
            ->first();

        if ($runningJob) {
            Log::info('AnalysisOrchestrator: Job already running', [
                'repository_id' => $repository->id,
                'job_id'        => $runningJob->id,
                'status'        => $runningJob->status,
            ]);
            return $runningJob;
        }

        $analysisJob = AnalysisJob::create([
            'repository_id'   => $repository->id,
            'status'          => AnalysisStatus::PENDING,
            'total_files'     => 0,
            'processed_files' => 0,
        ]);

        CloneRepositoryJob::dispatch($analysisJob)
            ->onQueue('clone');

        Log::info('AnalysisOrchestrator: Job dispatched', [
            'repository_id' => $repository->id,
            'job_id'        => $analysisJob->id,
        ]);

        return $analysisJob;
    }


    public function getStatus(AnalysisJob $job): array
    {
        $job->refresh();

        return [
            'job_id'          => $job->id,
            'status'          => $job->status->value,
            'progress'        => $job->progress,
            'total_files'     => $job->total_files,
            'processed_files' => $job->processed_files,
            'started_at'      => $job->started_at?->toISOString(),
            'completed_at'    => $job->completed_at?->toISOString(),
            'error_message'   => $job->error_message,
        ];
    }


    public function getResults(AnalysisJob $job, int $page = 1, ?string $filter = null): array
    {
        $query = $job->results();

        if ($filter && in_array($filter, ['ai', 'human', 'uncertain'])) {
            $query->where('classification', $filter);
        }

        $results = $query
            ->orderByDesc('confidence_score')
            ->paginate(50, ['*'], 'page', $page);

        $summary = [
            'total_files'    => $job->total_files,
            'ai_files'       => $job->results()->where('classification', 'ai')->count(),
            'human_files'    => $job->results()->where('classification', 'human')->count(),
            'uncertain'      => $job->results()->where('classification', 'uncertain')->count(),
            'avg_confidence' => (int) $job->results()->avg('confidence_score'),
            'ai_percentage'  => $job->total_files > 0
                ? round(($job->results()->where('classification', 'ai')->count() / $job->total_files) * 100)
                : 0,
        ];

        return compact('summary', 'results');
    }

    public function getMetadataOnly(string $githubUrl): array
    {
        // Sirf metadata — clone nahi, analyze nahi
        $metadata = $this->github->getRepoMetadata($githubUrl);

        [$owner, $repo] = $this->github->parseRepoUrl($githubUrl);

        // Repository save ya update karo
        \App\Models\Repository::updateOrCreate(
            ['owner' => $owner, 'repo_name' => $repo],
            [
                'github_url'     => $githubUrl,
                'description'    => $metadata['identity']['description'] ?? null,
                'stars'          => $metadata['stats']['stars'] ?? 0,
                'forks'          => $metadata['stats']['forks'] ?? 0,
                'languages'      => $metadata['stats']['languages'] ?? [],
                'default_branch' => $metadata['history']['default_branch'] ?? 'main',
                'repo_age'       => $metadata['history']['repo_age'] ?? null,
                'open_prs'       => $metadata['pull_requests']['open'] ?? 0,
                'merged_prs'     => $metadata['pull_requests']['merged'] ?? 0,
                'contributors'   => $metadata['contributors'] ?? [],
                'recent_commits' => $metadata['recent_commits'] ?? [],
                'commits_count'  => 0,
                'is_private'     => false,
            ]
        );

        // commits_count bhi attach karo
        $metadata['commits_count'] = 0;

        return $metadata;
    }
}
