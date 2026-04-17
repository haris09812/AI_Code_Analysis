{{-- resources/views/ai_code_analysis/partials/bulk-progress.blade.php --}}

<h5 class="fw-semibold mb-1"><i class="ti ti-search me-2 text-primary"></i>Bulk Repository Analysis</h5>
<p class="text-muted mb-4">Analyzing all files. Real-time progress is shown below.</p>

{{-- Step Pills --}}
<div class="d-flex gap-2 flex-wrap mb-4">
    <span class="step-pill badge bg-light text-dark border" id="sp1"><i class="ti ti-brand-github me-1"></i>Connecting to GitHub</span>
    <span class="step-pill badge bg-light text-dark border" id="sp2"><i class="ti ti-download me-1"></i>Cloning Repository</span>
    <span class="step-pill badge bg-light text-dark border" id="sp3"><i class="ti ti-robot me-1"></i>AI Analyzing</span>
    <span class="step-pill badge bg-light text-dark border" id="sp4"><i class="ti ti-check me-1"></i>Complete</span>
</div>

{{-- Progress Bar --}}
<div class="d-flex justify-content-between mb-1">
    <span class="small fw-semibold">Analysis Progress</span>
    <span class="small text-muted" id="pctText">0%</span>
</div>
<div class="progress mb-1" style="height:10px;border-radius:6px;">
    <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
         id="progBar" style="width:0%;border-radius:6px;"></div>
</div>
<div class="text-muted small mb-4">
    <i class="ti ti-info-circle text-warning me-1"></i>
    Note: Analysis may take a little time depending on the number of files in your repository.
</div>

{{-- Live Stats --}}
<div class="row g-3 mb-2">
    <div class="col-6 col-md-4">
        <div class="card border-0" style="background:#f8f7fa;">
            <div class="card-body text-center py-3">
                <div class="fw-bold fs-4" id="s_analyzed">0</div>
                <small class="text-muted">Files Analyzed</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card border-0" style="background:#f8f7fa;">
            <div class="card-body text-center py-3">
                <div class="fw-bold fs-4" id="s_total">{{ $job->total_files ?? 0 }}</div>
                <small class="text-muted">Total Files</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card border-0" style="background:#f8f7fa;">
            <div class="card-body text-center py-3">
                <div class="fw-bold fs-4 text-success" id="s_human">0</div>
                <small class="text-muted">Human-Written</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card border-0" style="background:#f8f7fa;">
            <div class="card-body text-center py-3">
                <div class="fw-bold fs-4 text-danger" id="s_ai">0</div>
                <small class="text-muted">AI-Generated</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card border-0" style="background:#f8f7fa;">
            <div class="card-body text-center py-3">
                <div class="fw-bold fs-4 text-success" id="s_hpct">0%</div>
                <small class="text-muted">Human Content %</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card border-0" style="background:#f8f7fa;">
            <div class="card-body text-center py-3">
                <div class="fw-bold fs-4 text-danger" id="s_apct">0%</div>
                <small class="text-muted">AI Content %</small>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const JOB_ID = {{ $job->id }};
    const stepMap = { pending:0, cloning:1, processing:2, completed:3, failed:3 };

    function setSteps(status) {
        const active = stepMap[status] ?? 0;
        ['sp1','sp2','sp3','sp4'].forEach((id, i) => {
            const el = document.getElementById(id);
            if (!el) return;
            if (i < active) {
                el.className = 'step-pill badge done-step';
            } else if (i === active) {
                el.className = 'step-pill badge active-step';
            } else {
                el.className = 'step-pill badge bg-light text-dark border';
            }
        });
    }

    let interval = setInterval(function() {
        fetch(`/api/v1/analysis/${JOB_ID}/status`, { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(res => {
            if (!res.success) return;
            const d = res.data;
            setSteps(d.status);
            const pct = d.progress || 0;
            document.getElementById('progBar').style.width    = pct + '%';
            document.getElementById('pctText').textContent    = pct + '%';
            document.getElementById('s_analyzed').textContent = d.processed_files || 0;
            document.getElementById('s_total').textContent    = d.total_files || 0;

            if (d.status === 'completed') {
                clearInterval(interval);
                fetch(`/api/v1/analysis/${JOB_ID}/results`, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(rr => {
                    if (rr.success) {
                        const s = rr.data.summary;
                        document.getElementById('s_ai').textContent    = s.ai_files;
                        document.getElementById('s_human').textContent  = s.human_files;
                        document.getElementById('s_apct').textContent  = s.ai_percentage + '%';
                        const hp = s.total_files > 0 ? Math.round(s.human_files / s.total_files * 100) : 0;
                        document.getElementById('s_hpct').textContent  = hp + '%';
                    }
                    setTimeout(() => window.location.reload(), 1500);
                });
            }

            if (d.status === 'failed') {
                clearInterval(interval);
                document.getElementById('progBar').classList.replace('bg-primary','bg-danger');
                document.getElementById('pctText').textContent = 'Failed — ' + (d.error_message || '');
            }
        });
    }, 3000);
})();
</script>
