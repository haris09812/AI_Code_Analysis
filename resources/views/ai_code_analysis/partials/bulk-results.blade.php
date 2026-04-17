{{-- resources/views/ai_code_analysis/partials/bulk-results.blade.php --}}
@php
use Illuminate\Support\Facades\Cache;
$summary = Cache::remember("job_summary_{$job->id}", 3600, function() use ($job) {
    $total = $job->total_files;
    $ai    = $job->results()->where('classification','ai')->count();
    $human = $job->results()->where('classification','human')->count();
    return [
        'total_files'    => $total,
        'ai_files'       => $ai,
        'human_files'    => $human,
        'uncertain'      => $job->results()->where('classification','uncertain')->count(),
        'avg_confidence' => (int)$job->results()->avg('confidence_score'),
        'ai_percentage'  => $total > 0 ? round(($ai / $total) * 100) : 0,
    ];
});
$humanPct = $summary['total_files'] > 0 ? round($summary['human_files'] / $summary['total_files'] * 100) : 0;
@endphp

{{-- Summary Stats --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="card border-0" style="background:#f8f7fa;">
            <div class="card-body text-center py-3">
                <div class="fw-bold fs-4">{{ $summary['total_files'] }}</div>
                <small class="text-muted">Files Analyzed</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card border-0" style="background:#f8f7fa;">
            <div class="card-body text-center py-3">
                <div class="fw-bold fs-4 text-secondary">{{ $summary['uncertain'] }}</div>
                <small class="text-muted">Files Skipped</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card border-0" style="background:#f8f7fa;">
            <div class="card-body text-center py-3">
                <div class="fw-bold fs-4 text-success">{{ $summary['human_files'] }}</div>
                <small class="text-muted">Human-Written</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card border-0" style="background:#f8f7fa;">
            <div class="card-body text-center py-3">
                <div class="fw-bold fs-4 text-danger">{{ $summary['ai_files'] }}</div>
                <small class="text-muted">AI-Generated</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card border-0" style="background:#f8f7fa;">
            <div class="card-body text-center py-3">
                <div class="fw-bold fs-4 text-success">{{ $humanPct }}%</div>
                <small class="text-muted">Human Content %</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card border-0" style="background:#f8f7fa;">
            <div class="card-body text-center py-3">
                <div class="fw-bold fs-4 text-danger">{{ $summary['ai_percentage'] }}%</div>
                <small class="text-muted">AI Content %</small>
            </div>
        </div>
    </div>
</div>

{{-- Filter Bar --}}
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h6 class="mb-0 fw-semibold">
        File Analysis Details
        <span class="text-primary small fw-normal">({{ $summary['total_files'] }} of {{ $job->total_files }} processed)</span>
    </h6>
    <div class="d-flex gap-2 flex-wrap">
        <a href="?filter=all"       class="btn btn-sm {{ request('filter','all')==='all'      ? 'btn-primary'         : 'btn-outline-secondary' }}">All</a>
        <a href="?filter=ai"        class="btn btn-sm {{ request('filter')==='ai'              ? 'btn-danger'          : 'btn-outline-danger'    }}">AI Only</a>
        <a href="?filter=human"     class="btn btn-sm {{ request('filter')==='human'           ? 'btn-success'         : 'btn-outline-success'   }}">Human Only</a>
        <a href="?filter=uncertain" class="btn btn-sm {{ request('filter')==='uncertain'       ? 'btn-secondary'       : 'btn-outline-secondary' }}">Uncertain</a>
    </div>
</div>

{{-- File List --}}
@php
    $filter = request('filter','all');
    $q = $job->results()->orderByDesc('confidence_score');
    if ($filter !== 'all') $q->where('classification', $filter);
    $results = $q->paginate(20);
@endphp

@forelse($results as $result)
<div class="file-item">
    <div class="d-flex align-items-start justify-content-between gap-3">

        {{-- Left: icon + info --}}
        <div class="d-flex align-items-start gap-3 flex-grow-1 min-width-0">
            <span class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 mt-1"
                style="width:36px;height:36px;
                @if($result->classification->value==='ai') background:rgba(234,84,85,.12);
                @elseif($result->classification->value==='human') background:rgba(40,199,111,.12);
                @else background:rgba(108,117,125,.1); @endif">
                <i class="ti ti-file-code"
                   style="@if($result->classification->value==='ai') color:#ea5455;
                   @elseif($result->classification->value==='human') color:#28c76f;
                   @else color:#6c757d; @endif"></i>
            </span>
            <div class="flex-grow-1 min-width-0">
                <div class="fw-semibold text-truncate small">{{ basename($result->file_path) }}</div>
                <div class="text-muted" style="font-size:.78rem;">{{ $result->file_path }}</div>
                <div class="d-flex gap-2 flex-wrap mt-1">
                    <span class="badge" style="background:#f0eef5;color:#6e6b7b;font-size:.7rem;">{{ strtoupper($result->file_extension) }}</span>
                    <span class="badge" style="background:rgba(105,108,255,.1);color:#696cff;font-size:.7rem;">{{ $result->confidence_score }}% confidence</span>
                    @if($result->source==='heuristic')
                        <span class="badge" style="background:rgba(255,159,67,.12);color:#ff9f43;font-size:.7rem;">Heuristic</span>
                    @endif
                    <a href="{{ $repository->github_url }}/blob/{{ $repository->default_branch }}/{{ $result->file_path }}"
                       target="_blank"
                       class="badge text-decoration-none"
                       style="background:#f0eef5;color:#6e6b7b;font-size:.7rem;">
                        → View on GitHub
                    </a>
                </div>
            </div>
        </div>

        {{-- Right: classification --}}
        <div class="text-end flex-shrink-0" style="min-width:120px;">
            @if($result->classification->value==='ai')
                <div class="fw-bold text-danger small">AI-Generated</div>
                <div style="font-size:.75rem;color:#28c76f;">{{ $result->confidence_score }}% confidence</div>
            @elseif($result->classification->value==='human')
                <div class="fw-bold text-success small">Human-Written</div>
                <div class="text-muted" style="font-size:.75rem;">{{ $result->confidence_score }}% confidence</div>
            @else
                <div class="fw-bold text-secondary small">Uncertain</div>
                <div class="text-muted" style="font-size:.75rem;">{{ $result->confidence_score }}% confidence</div>
            @endif
        </div>

    </div>

    {{-- Explanation --}}
    @if($result->explanation)
    <div class="mt-2 pt-2 border-top">
        <small class="text-muted"><i class="ti ti-info-circle me-1"></i>{{ $result->explanation }}</small>
    </div>
    @endif
</div>
@empty
<div class="text-center text-muted py-4">No files found for selected filter.</div>
@endforelse

<div class="mt-3">
    {{ $results->appends(request()->query())->links() }}
</div>
