<?php
// app/Http/Controllers/Api/AnalysisController.php

namespace App\Http\Controllers\Api;

use App\Enums\AnalysisStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnalyzeFileRequest;
use App\Http\Resources\AnalysisJobResource;
use App\Http\Resources\AnalysisResultResource;
use App\Models\AnalysisJob;
use App\Models\AnalysisResult;
use App\Services\AIDetectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

class AnalysisController extends Controller
{
    public function __construct(
        protected AIDetectionService $detector
    ) {}

    /** Screen 1: URL input */
    public function index()
    {
        return view('ai_code_analysis.index');
    }

    /** Screen 2: GitHub Insights (metadata) */
    public function insights(Request $request)
    {
        $repo = $request->query('repo');
        if (!$repo) {
            return redirect()->route('analyzer.index');
        }
        return view('ai_code_analysis.insight');
    }

    /** Screen 3: Analyze + inline AJAX results */
    public function analyze(Request $request)
    {
        $repo = $request->query('repo');
        if (!$repo) {
            return redirect()->route('analyzer.index');
        }
        return view('ai_code_analysis.analyze');
    }

    public function status(AnalysisJob $job): JsonResponse
    {
        $job->refresh();

        return response()->json([
            'success' => true,
            'data'    => new AnalysisJobResource($job),
        ]);
    }

    public function results(Request $request, AnalysisJob $job): JsonResponse
    {
        // Sirf completed job ke results do
        if (!$job->isCompleted()) {
            return response()->json([
                'success' => false,
                'message' => 'Analysis abhi complete nahi hua. Status: ' . $job->status->value,
                'data'    => new AnalysisJobResource($job),
            ], 422);
        }

        $summary = Cache::remember(
            "job_summary_{$job->id}",
            3600,
            fn() => $this->buildSummary($job)
        );

        $query = $job->results();

        $classification = $request->query('classification');
        if ($classification && in_array($classification, ['ai', 'human', 'uncertain'])) {
            $query->where('classification', $classification);
        }

        $sort = $request->query('sort', 'confidence_desc');
        match($sort) {
            'confidence_asc'  => $query->orderBy('confidence_score'),
            'confidence_desc' => $query->orderByDesc('confidence_score'),
            default           => $query->orderByDesc('confidence_score'),
        };

        $results = $query->paginate(50);

        return response()->json([
            'success' => true,
            'data'    => [
                'summary' => $summary,
                'results' => AnalysisResultResource::collection($results),
                'meta'    => [
                    'current_page' => $results->currentPage(),
                    'last_page'    => $results->lastPage(),
                    'total'        => $results->total(),
                    'per_page'     => $results->perPage(),
                ],
            ],
        ]);
    }

    public function fileResult(Request $request, AnalysisJob $job): JsonResponse
    {
        $filePath = $request->query('file_path');

        if (!$filePath) {
            return response()->json([
                'success' => false,
                'message' => 'file_path query parameter zaroori hai.',
            ], 422);
        }

        $result = AnalysisResult::where('analysis_job_id', $job->id)
            ->where('file_path', $filePath)
            ->first();

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Is file ka result nahi mila.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => new AnalysisResultResource($result),
        ]);
    }

    public function analyzeFile(AnalyzeFileRequest $request, AnalysisJob $job): JsonResponse
    {
        $filePath = $request->validated('file_path');

        // Pehle database check karo
        $existing = AnalysisResult::where('analysis_job_id', $job->id)
            ->where('file_path', $filePath)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'source'  => 'database',
                'data'    => new AnalysisResultResource($existing),
            ]);
        }

        $clonePath = storage_path("app/clones/job_{$job->id}");
        $fullPath  = $clonePath . '/' . ltrim($filePath, '/');

        if (!file_exists($fullPath)) {
            return response()->json([
                'success' => false,
                'message' => 'File available nahi — bulk analysis pehle chalao.',
            ], 404);
        }

        $content = file_get_contents($fullPath);

        if (strlen($content) > 10240) {
            return response()->json([
                'success' => false,
                'message' => 'File bohot badi hai sync analysis ke liye (max 10KB). Bulk analysis use karo.',
            ], 422);
        }

        try {
            $result = $this->detector->analyzeFile($filePath, $content, 'deep');

            $saved = AnalysisResult::create([
                'analysis_job_id'  => $job->id,
                'file_path'        => $filePath,
                'file_extension'   => pathinfo($filePath, PATHINFO_EXTENSION),
                'classification'   => $result['classification'],
                'confidence_score' => $result['confidence_score'],
                'explanation'      => $result['explanation'],
                'signals'          => $result['signals'] ?? [],
                'suggestion'       => $result['suggestion'] ?? '',
                'source'           => $result['source'],
                'raw_llm_response' => $result['raw_llm_response'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'source'  => 'fresh_analysis',
                'data'    => new AnalysisResultResource($saved),
            ]);

        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Analysis fail ho gaya: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function fileTree(AnalysisJob $job): JsonResponse
    {
        $cacheKey = "file_tree_{$job->id}";

        $tree = Cache::remember($cacheKey, 3600, function () use ($job) {
            return AnalysisResult::where('analysis_job_id', $job->id)
                ->select('file_path', 'file_extension', 'classification', 'confidence_score')
                ->orderBy('file_path')
                ->get()
                ->map(fn($r) => [
                    'path'        => $r->file_path,
                    'extension'   => $r->file_extension,
                    'class'       => $r->classification->value,
                    'confidence'  => $r->confidence_score,
                ])
                ->toArray();
        });

        return response()->json([
            'success' => true,
            'data'    => $tree,
        ]);
    }

    protected function buildSummary(AnalysisJob $job): array
    {
        $total   = $job->total_files;
        $ai      = $job->results()->where('classification', 'ai')->count();
        $human   = $job->results()->where('classification', 'human')->count();
        $uncertain = $job->results()->where('classification', 'uncertain')->count();

        return [
            'total_files'    => $total,
            'ai_files'       => $ai,
            'human_files'    => $human,
            'uncertain'      => $uncertain,
            'ai_percentage'  => $total > 0 ? round(($ai / $total) * 100) : 0,
            'avg_confidence' => (int) $job->results()->avg('confidence_score'),
            'llm_calls'      => $job->results()->where('source', 'llm')->count(),
            'heuristic_calls'=> $job->results()->where('source', 'heuristic')->count(),
        ];
    }
}
