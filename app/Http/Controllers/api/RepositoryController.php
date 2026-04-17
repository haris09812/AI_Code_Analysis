<?php
// app/Http/Controllers/Api/RepositoryController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnalyzeRepositoryRequest;
use App\Http\Resources\AnalysisJobResource;
use App\Http\Resources\RepositoryResource;
use App\Models\Repository;
use App\Services\AnalysisOrchestrator;
use Illuminate\Http\JsonResponse;
use Throwable;

class RepositoryController extends Controller
{
    public function __construct(
        protected AnalysisOrchestrator $orchestrator
    ) {}

    public function analyze(AnalyzeRepositoryRequest $request): JsonResponse
    {
        try {
            $job = $this->orchestrator->initiate(
                $request->validated('github_url')
            );

            return response()->json([
                'success' => true,
                'message' => 'Analysis queue mein add ho gaya.',
                'data'    => [
                    'job_id'   => $job->id,
                    'status'   => $job->status->value,
                    'poll_url' => route('api.analysis.status', $job->id),
                ],
            ], 202);

        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(string $owner, string $repo): JsonResponse
    {
        $repository = Repository::where('owner', $owner)
            ->where('repo_name', $repo)
            ->with('latestJob')
            ->first();

        if (!$repository) {
            return response()->json([
                'success' => false,
                'message' => 'Repository nahi mili. Pehle analyze karo.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'repository' => new RepositoryResource($repository),
                'latest_job' => $repository->latestJob
                    ? new AnalysisJobResource($repository->latestJob)
                    : null,
            ],
        ]);
    }
    public function metadata(\App\Http\Requests\AnalyzeRepositoryRequest $request): \Illuminate\Http\JsonResponse
    {
        try {
            $meta = $this->orchestrator->getMetadataOnly(
                $request->validated('github_url')
            );

            return response()->json([
                'success' => true,
                'data'    => $meta,
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
