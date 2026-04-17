{{-- resources/views/ai_code_analysis/analyze.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Analyze Repository</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.16/themes/default/style.min.css">
    <style>
        body{background:#f5f5f9;font-family:'Public Sans',sans-serif;}
        .card{border-radius:12px;box-shadow:0 2px 10px rgba(47,43,61,.08);border:1px solid #e7e3f1;}
        .btn-success-custom{background:#28c76f;border:none;color:#fff;padding:13px;border-radius:8px;font-weight:600;font-size:.95rem;}
        .btn-success-custom:hover{background:#24b263;color:#fff;}
        .model-banner{background:linear-gradient(135deg,#f0f4ff,#f8f5ff);border:1px solid #d0d5f5;}
        .step-pill{font-size:.75rem;padding:5px 14px;border-radius:20px;border:1px solid #dee2e6;background:#fff;color:#6c757d;transition:all .35s ease;}
        .step-pill.s-active{background:#696cff;color:#fff;border-color:#696cff;}
        .step-pill.s-done{background:#28c76f;color:#fff;border-color:#28c76f;}
        .dot{width:9px;height:9px;border-radius:50%;background:#28c76f;display:inline-block;animation:dotPulse 1.2s ease-in-out infinite;}
        .dot:nth-child(2){animation-delay:.3s;}.dot:nth-child(3){animation-delay:.6s;}
        @keyframes dotPulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.35;transform:scale(.7)}}
        .file-item{border:1px solid #e7e3f1;border-radius:8px;padding:13px 15px;margin-bottom:8px;background:#fff;}
        .progress{border-radius:6px;}
        .commit-row{padding:11px 0;border-bottom:1px solid #f0eef5;}
        .commit-row:last-child{border-bottom:none;}
        .avatar-circle{width:34px;height:34px;border-radius:50%;background:#696cff;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.82rem;flex-shrink:0;}
        .nav-tabs .nav-link{color:#6e6b7b;border:none;padding:11px 18px;font-weight:500;}
        .nav-tabs .nav-link.active{color:#696cff;border-bottom:2px solid #696cff;background:transparent;}
        .nav-tabs{border-bottom:1px solid #e7e3f1;}
        .file-panel-placeholder{min-height:300px;display:flex;flex-direction:column;align-items:center;justify-content:center;background:#fafafa;border:1px dashed #d0cedf;border-radius:10px;}
    </style>
</head>
<body>
<div class="container-fluid px-4 py-4" style="max-width:1200px;">

    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1" style="color:#2f2b3d;">Repository Code Analysis</h4>
            <p class="text-muted mb-0 small">Analyze code files for AI-generated content detection</p>
        </div>
        <a id="backBtn" href="#" class="btn btn-outline-secondary btn-sm">
            <i class="ti ti-arrow-left me-1"></i> Back
        </a>
    </div>

    {{-- ===== FETCH SECTION ===== --}}
    <div id="fetchSection">

        <div class="card model-banner p-3 mb-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <span class="rounded-circle d-flex align-items-center justify-content-center" style="width:40px;height:40px;background:rgba(105,108,255,.15);">
                        <i class="ti ti-robot fs-5" style="color:#696cff;"></i>
                    </span>
                    <div>
                        <div class="fw-semibold" style="color:#2f2b3d;">Claude AI Detector (Anthropic)</div>
                        <small class="text-muted">LLM-powered detection using Claude, Gemini &amp; DeepSeek models</small>
                    </div>
                </div>
                <a href="https://docs.anthropic.com" target="_blank" class="btn btn-primary btn-sm">
                    <i class="ti ti-book me-1"></i> View Documentation
                </a>
            </div>
        </div>

        <div class="card p-3 mb-3">
            <div class="d-flex align-items-center gap-3">
                <span class="rounded-circle d-flex align-items-center justify-content-center" style="width:36px;height:36px;background:rgba(40,199,111,.12);">
                    <i class="ti ti-brand-github text-success"></i>
                </span>
                <div class="flex-grow-1">
                    <div class="text-muted small">Repository</div>
                    <div class="fw-semibold small" id="repoDisplay" style="word-break:break-all;">—</div>
                </div>
            </div>
        </div>

        <div id="fetchError" class="alert alert-danger d-none mb-3">
            <i class="ti ti-alert-circle me-2"></i><span id="fetchErrorMsg"></span>
        </div>

        <div class="card p-4 mb-3" id="fetchBtnCard">
            <button id="fetchBtn" class="btn btn-success-custom w-100" onclick="startFetch()">
                <i class="ti ti-folder-search me-2"></i> Fetch Repository Files
            </button>
        </div>

        <div id="stepSection" class="card p-4 d-none">
            <div class="text-center mb-3">
                <div class="spinner-border text-success mb-3" style="width:2.8rem;height:2.8rem;" role="status"></div>
                <h6 class="fw-semibold mb-1" id="stepText">🔗 Connecting to GitHub...</h6>
                <p class="text-muted small mb-3">This might take a moment while we process your repository...</p>
            </div>
            <div class="d-flex justify-content-center gap-2 flex-wrap mb-3">
                <span class="step-pill" id="sp1"><i class="ti ti-brand-github me-1"></i>Connecting</span>
                <span class="step-pill" id="sp2"><i class="ti ti-download me-1"></i>Cloning</span>
                <span class="step-pill" id="sp3"><i class="ti ti-robot me-1"></i>Analyzing</span>
                <span class="step-pill" id="sp4"><i class="ti ti-check me-1"></i>Complete</span>
            </div>
            <div id="progressWrap" class="d-none mb-3">
                <div class="d-flex justify-content-between mb-1">
                    <span class="small fw-semibold">Analysis Progress</span>
                    <span class="small text-muted" id="pctText">0%</span>
                </div>
                <div class="progress mb-2" style="height:9px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" id="progBar" style="width:0%;border-radius:6px;"></div>
                </div>
                <div class="row g-2 text-center">
                    <div class="col-4"><div class="fw-bold" id="liveAnalyzed">0</div><small class="text-muted">Analyzed</small></div>
                    <div class="col-4"><div class="fw-bold text-success" id="liveHuman">0</div><small class="text-muted">Human</small></div>
                    <div class="col-4"><div class="fw-bold text-danger" id="liveAI">0</div><small class="text-muted">AI</small></div>
                </div>
            </div>
            <div class="d-flex justify-content-center gap-2">
                <span class="dot"></span><span class="dot"></span><span class="dot"></span>
            </div>
        </div>

    </div>

    {{-- ===== RESULTS SECTION ===== --}}
    <div id="resultsSection" class="d-none">

        <div class="row g-3 mb-4" id="summaryCards"></div>

        <div class="card mb-4">
            <div class="card-header p-0 bg-white" style="border-radius:12px 12px 0 0;">
                <ul class="nav nav-tabs">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#bulkPane">
                            <i class="ti ti-search me-1"></i> Analysis Results
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#indivPane">
                            <i class="ti ti-files me-1"></i> Individual Files
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body tab-content pt-3">

                <div class="tab-pane fade show active" id="bulkPane">
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <h6 class="mb-0 fw-semibold">File Analysis Details <span class="text-primary small fw-normal" id="processedLabel"></span></h6>
                        <div class="d-flex gap-2 flex-wrap">
                            <button class="btn btn-sm btn-primary"          onclick="filterFiles(this,'all')">All</button>
                            <button class="btn btn-sm btn-outline-danger"   onclick="filterFiles(this,'ai')">AI Only</button>
                            <button class="btn btn-sm btn-outline-success"  onclick="filterFiles(this,'human')">Human Only</button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="filterFiles(this,'uncertain')">Uncertain</button>
                        </div>
                    </div>
                    <div id="fileList"></div>
                    <div id="loadMoreWrap" class="text-center mt-3 d-none">
                        <button class="btn btn-outline-primary btn-sm" onclick="loadMoreFiles()">Load More</button>
                    </div>
                </div>

                <div class="tab-pane fade" id="indivPane">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="card h-100" style="border:1px solid #e7e3f1;">
                                <div class="card-header py-2 bg-white">
                                    <span class="fw-semibold small"><i class="ti ti-folder-open text-warning me-2"></i>File Explorer</span>
                                </div>
                                <div class="card-body p-2" style="max-height:460px;overflow-y:auto;">
                                    <div id="jsFileTree"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div id="filePanelPlaceholder" class="file-panel-placeholder">
                                <span class="rounded-circle d-flex align-items-center justify-content-center mb-3" style="width:50px;height:50px;background:rgba(105,108,255,.1);">
                                    <i class="ti ti-file-search fs-3" style="color:#696cff;"></i>
                                </span>
                                <div class="fw-semibold text-muted">Select a file from the tree</div>
                                <small class="text-muted">Click any file to view its AI analysis</small>
                            </div>
                            <div id="filePanelLoading" class="card d-none" style="min-height:260px;">
                                <div class="card-body d-flex align-items-center justify-content-center">
                                    <div class="spinner-border text-primary me-3" role="status"></div>
                                    <span class="text-muted">Loading...</span>
                                </div>
                            </div>
                            <div id="filePanelResult" class="card d-none" style="border:1px solid #e7e3f1;">
                                <div class="card-header d-flex align-items-center justify-content-between bg-white" style="border-radius:12px 12px 0 0;">
                                    <span class="fw-semibold small" id="fpFileName">—</span>
                                    <a id="fpGithubLink" href="#" target="_blank" class="btn btn-sm btn-outline-secondary">
                                        <i class="ti ti-brand-github me-1"></i> GitHub
                                    </a>
                                </div>
                                <div class="card-body">
                                    <div class="text-center mb-3">
                                        <span id="fpClassBadge" class="badge px-4 py-2" style="font-size:.9rem;">—</span>
                                    </div>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="small fw-semibold">Confidence Score</span>
                                            <span class="fw-bold" id="fpConfText">0%</span>
                                        </div>
                                        <div class="progress" style="height:11px;">
                                            <div id="fpConfBar" class="progress-bar" style="width:0%;border-radius:6px;"></div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="fw-semibold small mb-2"><i class="ti ti-notes me-1 text-primary"></i>Explanation</div>
                                        <p id="fpExplanation" class="text-muted small p-3 rounded mb-0" style="background:#f8f7fa;">—</p>
                                    </div>
                                    <div>
                                        <div class="fw-semibold small mb-2"><i class="ti ti-list-check me-1 text-primary"></i>Detection Signals</div>
                                        <div id="fpSignals" class="d-flex flex-wrap gap-2"></div>
                                    </div>
                                    <div id="fpSuggestion" class="alert alert-warning py-2 small mt-2 d-none">
                                        <i class="ti ti-bulb me-2"></i><span id="fpSuggestionText"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div id="repoInfoSection"></div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.16/jstree.min.js"></script>
<script>
const REPO_URL = new URLSearchParams(window.location.search).get('repo') || '';
const CSRF     = document.querySelector('meta[name="csrf-token"]').content;
let JOB_ID = null, pollTimer = null, allFiles = [], currentFilter = 'all', currentPage = 1;
const PER_PAGE = 20;

document.addEventListener('DOMContentLoaded', () => {
    if (!REPO_URL) { window.location.href = '/analyzer'; return; }
    document.getElementById('repoDisplay').textContent = REPO_URL;
    document.getElementById('backBtn').href = '/analyzer/insights?repo=' + encodeURIComponent(REPO_URL);
});

const stepCfg = {
    pending:    { text:'🔗 Connecting to GitHub...', active:0 },
    cloning:    { text:'📥 Cloning Repository...',   active:1 },
    processing: { text:'🤖 AI Analyzing Files...',   active:2 },
    completed:  { text:'✅ Analysis Complete!',       active:3 },
};

function setSteps(status) {
    const cfg = stepCfg[status] || stepCfg.pending;
    document.getElementById('stepText').textContent = cfg.text;
    ['sp1','sp2','sp3','sp4'].forEach((id, i) => {
        const el = document.getElementById(id);
        if (i < cfg.active)        el.className = 'step-pill s-done';
        else if (i === cfg.active) el.className = 'step-pill s-active';
        else                       el.className = 'step-pill';
    });
}

function startFetch() {
    document.getElementById('fetchBtn').disabled = true;
    document.getElementById('fetchError').classList.add('d-none');
    document.getElementById('stepSection').classList.remove('d-none');
    document.getElementById('fetchBtnCard').classList.add('d-none');
    setSteps('pending');

    fetch('/api/v1/repositories/analyze', {
        method: 'POST',
        headers: { 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ github_url: REPO_URL })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) { showFetchError(data.message); return; }
        JOB_ID = data.data.job_id;
        console.log('Job ID:', JOB_ID);
        pollTimer = setInterval(poll, 3000);
        poll();
    })
    .catch(() => showFetchError('Network error. Please try again.'));
}

function showFetchError(msg) {
    document.getElementById('fetchErrorMsg').textContent = msg;
    document.getElementById('fetchError').classList.remove('d-none');
    document.getElementById('fetchBtn').disabled = false;
    document.getElementById('fetchBtnCard').classList.remove('d-none');
    document.getElementById('stepSection').classList.add('d-none');
}

function poll() {
    fetch(`/api/v1/analysis/${JOB_ID}/status`, { headers:{ 'Accept':'application/json' } })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        const d = data.data;
        console.log('Status:', d.status);
        setSteps(d.status);
        if (d.status === 'processing' || d.status === 'completed') {
            document.getElementById('progressWrap').classList.remove('d-none');
            const pct = d.progress || 0;
            document.getElementById('progBar').style.width = pct + '%';
            document.getElementById('pctText').textContent = pct + '%';
            document.getElementById('liveAnalyzed').textContent = d.processed_files || 0;
        }
        if (d.status === 'completed') {
            clearInterval(pollTimer);
            loadResults();
        }
        if (d.status === 'failed') {
            clearInterval(pollTimer);
            showFetchError('Analysis failed: ' + (d.error_message || 'Unknown error'));
        }
    });
}

function loadResults() {
    console.log('loadResults called, JOB_ID:', JOB_ID);

    if (!JOB_ID) {
        console.error('JOB_ID is null!');
        return;
    }

    fetch(`/api/v1/analysis/${JOB_ID}/results`, { headers:{ 'Accept':'application/json' } })
    .then(r => r.json())
    .then(data => {
        console.log('Results API response:', data);
        console.log('Files:', data?.data?.results);
        if (!data.success) return;
        // Hide fetch section completely
        document.getElementById('fetchSection').classList.add('d-none');
        // Show results
        document.getElementById('resultsSection').classList.remove('d-none');

        const s = data.data.summary;
        renderSummaryCards(s);

        allFiles = data.data.results?.data || data.data.results || [];
        console.log('allFiles length:', allFiles.length);
        
        document.getElementById('processedLabel').textContent = `(${s.total_files} files)`;
        document.getElementById('liveHuman').textContent = s.human_files || 0;
        document.getElementById('liveAI').textContent    = s.ai_files    || 0;
        renderFileList();
        loadFileTree();
        loadRepoInfo();
    });
}

function renderSummaryCards(s) {
    const hp = s.total_files > 0 ? Math.round(s.human_files / s.total_files * 100) : 0;
    document.getElementById('summaryCards').innerHTML = `
        <div class="col-6 col-md-4"><div class="card border-0 p-3 text-center" style="background:#f8f7fa;"><div class="fw-bold fs-3">${s.total_files}</div><small class="text-muted">Files Analyzed</small></div></div>
        <div class="col-6 col-md-4"><div class="card border-0 p-3 text-center" style="background:#f8f7fa;"><div class="fw-bold fs-3 text-success">${s.human_files}</div><small class="text-muted">Human-Written</small></div></div>
        <div class="col-6 col-md-4"><div class="card border-0 p-3 text-center" style="background:#f8f7fa;"><div class="fw-bold fs-3 text-danger">${s.ai_files}</div><small class="text-muted">AI-Generated</small></div></div>
        <div class="col-6 col-md-4"><div class="card border-0 p-3 text-center" style="background:#f8f7fa;"><div class="fw-bold fs-3 text-secondary">${s.uncertain}</div><small class="text-muted">Uncertain</small></div></div>
        <div class="col-6 col-md-4"><div class="card border-0 p-3 text-center" style="background:#f8f7fa;"><div class="fw-bold fs-3 text-success">${hp}%</div><small class="text-muted">Human Content %</small></div></div>
        <div class="col-6 col-md-4"><div class="card border-0 p-3 text-center" style="background:#f8f7fa;"><div class="fw-bold fs-3 text-danger">${s.ai_percentage}%</div><small class="text-muted">AI Content %</small></div></div>`;
}

function filterFiles(btn, filter) {
    currentFilter = filter; currentPage = 1;
    document.querySelectorAll('#bulkPane .btn-sm').forEach(b => {
        b.className = b.className.replace(/btn-(primary|danger|success|secondary)\b/g, '').replace('btn-outline-', 'btn-outline-') ;
        if (!b.className.includes('btn-outline')) b.className += ' btn-outline-secondary';
    });
    const activeMap = { all:'btn-primary', ai:'btn-danger', human:'btn-success', uncertain:'btn-secondary' };
    btn.className = btn.className.replace('btn-outline-secondary','').replace('btn-outline-danger','').replace('btn-outline-success','').trim() + ' ' + activeMap[filter];
    renderFileList();
}

function renderFileList() {
    const filtered = currentFilter === 'all' ? allFiles : allFiles.filter(f => f.classification === currentFilter);
    const paged = filtered.slice(0, currentPage * PER_PAGE);
    let html = '';

    if (!paged.length) {
        html = '<div class="text-center text-muted py-4">No files found.</div>';
    } else {
        paged.forEach(f => {
            const cls = f.classification;
            const iconColor = cls==='ai'?'#ea5455':cls==='human'?'#28c76f':'#6c757d';
            const iconBg = cls==='ai'?'rgba(234,84,85,.1)':cls==='human'?'rgba(40,199,111,.1)':'rgba(108,117,125,.09)';
            const clsHtml = cls==='ai'?'<span class="text-danger fw-bold small">AI-Generated</span>':cls==='human'?'<span class="text-success fw-bold small">Human-Written</span>':'<span class="text-secondary fw-bold small">Uncertain</span>';
            html += `<div class="file-item">
                <div class="d-flex align-items-start justify-content-between gap-3">
                    <div class="d-flex align-items-start gap-3 flex-grow-1 min-width-0">
                        <span class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 mt-1" style="width:34px;height:34px;background:${iconBg};">
                            <i class="ti ti-file-code" style="color:${iconColor};"></i>
                        </span>
                        <div class="flex-grow-1 min-width-0">
                            <div class="fw-semibold small text-truncate">${escH(f.file_path.split('/').pop())}</div>
                            <div class="text-muted" style="font-size:.75rem;">${escH(f.file_path)}</div>
                            <div class="d-flex gap-1 flex-wrap mt-1">
                                <span class="badge" style="background:#f0eef5;color:#6e6b7b;font-size:.68rem;">${(f.file_extension||'').toUpperCase()}</span>
                                <span class="badge" style="background:rgba(105,108,255,.1);color:#696cff;font-size:.68rem;">${f.confidence_score}% conf</span>
                            </div>
                        </div>
                    </div>
                    <div class="text-end flex-shrink-0" style="min-width:110px;">${clsHtml}<div class="text-muted" style="font-size:.72rem;">${f.confidence_score}% confidence</div></div>
                </div>
                ${f.explanation?`<div class="mt-2 pt-2 border-top"><small class="text-muted"><i class="ti ti-info-circle me-1"></i>${escH(f.explanation)}</small></div>`:''}
            </div>`;
        });
    }
    document.getElementById('fileList').innerHTML = html;
    document.getElementById('loadMoreWrap').classList.toggle('d-none', filtered.length <= currentPage * PER_PAGE);
}

function loadMoreFiles() { currentPage++; renderFileList(); }

function loadFileTree() {
    fetch(`/api/v1/analysis/${JOB_ID}/file-tree`, { headers:{ 'Accept':'application/json' } })
    .then(r => r.json())
    .then(data => { if (data.success && data.data.length) buildJsTree(data.data); });
}

function buildJsTree(files) {
    const nodes = {}, root = [];
    files.forEach(file => {
        const parts = file.path.split('/');
        let current = root, builtPath = '';
        parts.forEach((part, i) => {
            builtPath = builtPath ? builtPath+'/'+part : part;
            const isLast = i === parts.length - 1;
            if (!nodes[builtPath]) {
                nodes[builtPath] = { id:builtPath, text:part, children:isLast?false:[], icon:isLast?(file.class==='ai'?'ti ti-file-code':file.class==='human'?'ti ti-file-check':'ti ti-file'):'ti ti-folder', data:isLast?{path:file.path,cls:file.class,conf:file.confidence}:null };
                current.push(nodes[builtPath]);
            }
            if (!isLast && nodes[builtPath].children) current = nodes[builtPath].children;
        });
    });
    $('#jsFileTree').jstree({ core:{ data:root, themes:{ icons:true, dots:false } }, plugins:['wholerow'] })
    .on('select_node.jstree', function(e, d) { if (d.node.data && d.node.data.path) loadFileResult(d.node.data.path); });
}

function loadFileResult(filePath) {
    document.getElementById('filePanelPlaceholder').classList.add('d-none');
    document.getElementById('filePanelLoading').classList.remove('d-none');
    document.getElementById('filePanelResult').classList.add('d-none');
    fetch(`/api/v1/analysis/${JOB_ID}/file?file_path=${encodeURIComponent(filePath)}`, { headers:{ 'Accept':'application/json' } })
    .then(r => r.json())
    .then(data => {
        document.getElementById('filePanelLoading').classList.add('d-none');
        if (data.success) renderFilePanel(filePath, data.data);
        else renderFilePanelNotAnalyzed(filePath);
    });
}

function renderFilePanel(path, r) {
    document.getElementById('filePanelResult').classList.remove('d-none');
    document.getElementById('fpFileName').textContent = path.split('/').pop();
    document.getElementById('fpGithubLink').href = REPO_URL + '/blob/main/' + path;
    const cfg = { ai:{t:'AI-Generated',bg:'#ea5455'}, human:{t:'Human-Written',bg:'#28c76f'}, uncertain:{t:'Uncertain',bg:'#6c757d'} };
    const c = cfg[r.classification] || cfg.uncertain;
    const badge = document.getElementById('fpClassBadge');
    badge.textContent = c.t; badge.style.background = c.bg; badge.style.color = '#fff';
    const pct = r.confidence_score || 0;
    const barColor = r.classification==='ai'?'#ea5455':r.classification==='human'?'#28c76f':'#6c757d';
    document.getElementById('fpConfText').textContent = pct + '%';
    document.getElementById('fpConfBar').style.width = pct + '%';
    document.getElementById('fpConfBar').style.background = barColor;
    document.getElementById('fpExplanation').textContent = r.explanation || '—';
    const sigEl = document.getElementById('fpSignals');
    sigEl.innerHTML = '';
    const labels = { uniform_naming:'Uniform Naming', excessive_comments:'Excessive Comments', has_debug_artifacts:'Debug Artifacts', has_todos_or_wip:'TODOs/WIP', boilerplate_heavy:'Boilerplate Heavy', inconsistent_style:'Inconsistent Style' };
    if (r.signals) Object.entries(r.signals).forEach(([k,v]) => {
        const col = v?'rgba(234,84,85,.1);color:#ea5455':'rgba(40,199,111,.1);color:#28c76f';
        sigEl.innerHTML += `<span class="badge" style="background:${col};font-size:.7rem;"><i class="ti ti-${v?'check':'x'} me-1"></i>${labels[k]||k}</span>`;
    });
    if (r.suggestion) { document.getElementById('fpSuggestionText').textContent=r.suggestion; document.getElementById('fpSuggestion').classList.remove('d-none'); }
    else document.getElementById('fpSuggestion').classList.add('d-none');
}

function renderFilePanelNotAnalyzed(path) {
    document.getElementById('filePanelResult').classList.remove('d-none');
    document.getElementById('fpFileName').textContent = path.split('/').pop();
    const badge = document.getElementById('fpClassBadge');
    badge.textContent='Not Analyzed'; badge.style.background='#ff9f43'; badge.style.color='#fff';
    document.getElementById('fpConfText').textContent = '—';
    document.getElementById('fpConfBar').style.width = '0%';
    document.getElementById('fpExplanation').textContent = 'This file has not been analyzed yet.';
    document.getElementById('fpSignals').innerHTML = '';
    document.getElementById('fpSuggestion').classList.add('d-none');
}

function loadRepoInfo() {
    fetch('/api/v1/repositories/metadata', {
        method:'POST', headers:{ 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN':CSRF },
        body: JSON.stringify({ github_url: REPO_URL })
    })
    .then(r => r.json())
    .then(data => {
        console.log('FULL API RESPONSE:', data);
        console.log('Files array:', data.data.results);

        if (data.success) renderRepoInfo(data.data); });
}

function renderRepoInfo(meta) {
    const langs = (meta.stats||{}).languages || {};
    const contribs = meta.contributors || [];
    const commits  = meta.recent_commits || [];
    const identity = meta.identity || {};
    let html = '';

    if (Object.keys(langs).length > 0) {
        const total = Object.values(langs).reduce((a,b)=>a+b,0);
        let lh = '';
        Object.entries(langs).forEach(([l,b]) => {
            const p = total > 0 ? ((b/total)*100).toFixed(1) : 0;
            lh += `<div class="mb-3"><div class="d-flex justify-content-between mb-1"><span class="fw-semibold small">${l}</span><span class="text-muted small">${p}%</span></div><div class="progress" style="height:7px;border-radius:4px;"><div class="progress-bar bg-primary" style="width:${p}%;"></div></div></div>`;
        });
        html += `<div class="card p-4 mb-4"><h6 class="fw-bold mb-3" style="color:#2f2b3d;"><i class="ti ti-chart-bar me-2 text-primary"></i>Programming Languages</h6>${lh}</div>`;
    }

    if (contribs.length > 0) {
        let ch = '';
        contribs.forEach(c => {
            ch += `<div class="col-md-6"><div class="d-flex align-items-center gap-3 p-3 border rounded bg-white">
                <div class="position-relative"><img src="${c.avatar}" class="rounded-circle" width="42" height="42"><span class="position-absolute" style="bottom:0;right:0;width:11px;height:11px;background:#28c76f;border-radius:50%;border:2px solid #fff;"></span></div>
                <div class="flex-grow-1 min-width-0"><div class="fw-semibold small text-truncate">${escH(c.name)}</div><small class="text-muted">${c.commits} commits</small></div>
                <a href="${c.profile}" target="_blank" class="btn btn-sm btn-outline-secondary flex-shrink-0">Profile</a>
            </div></div>`;
        });
        html += `<div class="card mb-4"><div class="card-header d-flex align-items-center justify-content-between bg-white" style="border-radius:12px 12px 0 0;"><h6 class="fw-bold mb-0" style="color:#2f2b3d;"><i class="ti ti-users me-2 text-primary"></i>Contributors (${contribs.length})</h6><a href="${identity.url||REPO_URL}/graphs/contributors" target="_blank" class="btn btn-sm btn-dark"><i class="ti ti-external-link me-1"></i>View on GitHub</a></div><div class="card-body"><div class="row g-3">${ch}</div></div></div>`;
    }

    if (commits.length > 0) {
        let cm = '';
        commits.forEach(c => {
            const init = (c.author||'?').charAt(0).toUpperCase();
            cm += `<div class="commit-row d-flex align-items-start gap-3">
                <div class="avatar-circle flex-shrink-0">${init}</div>
                <div class="flex-grow-1 min-width-0"><div class="fw-semibold small text-truncate">${escH(c.message||'')}</div>
                <small class="text-muted"><strong>${escH(c.author||'')}</strong> &bull; ${c.date||''}${c.url?` &bull; <a href="${c.url}" target="_blank" class="text-primary">View</a>`:''}</small></div>
            </div>`;
        });
        html += `<div class="card mb-5"><div class="card-header d-flex align-items-center justify-content-between bg-white" style="border-radius:12px 12px 0 0;"><h6 class="fw-bold mb-0" style="color:#2f2b3d;"><i class="ti ti-history me-2 text-primary"></i>Recent Commits</h6><a href="${identity.url||REPO_URL}/commits" target="_blank" class="btn btn-sm btn-dark"><i class="ti ti-external-link me-1"></i>View All on GitHub</a></div><div class="card-body p-0 px-3">${cm}</div></div>`;
    }

    document.getElementById('repoInfoSection').innerHTML = html;
}

function escH(s) { return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
</script>
</body>
</html>
