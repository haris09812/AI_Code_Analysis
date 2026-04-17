{{-- resources/views/ai_code_analysis/partials/individual-files.blade.php --}}

<div class="row g-3">

    {{-- File Tree --}}
    <div class="col-md-4">
        <div class="card h-100" style="border:1px solid #e7e3f1;">
            <div class="card-header py-2 bg-white" style="border-radius:12px 12px 0 0;">
                <span class="fw-semibold small"><i class="ti ti-folder-open text-warning me-2"></i>File Explorer</span>
            </div>
            <div class="card-body p-2" style="max-height:480px;overflow-y:auto;">
                <div id="jsFileTree"></div>
            </div>
        </div>
    </div>

    {{-- Analysis Panel --}}
    <div class="col-md-8">

        {{-- Placeholder --}}
        <div id="panelPlaceholder" class="file-panel-placeholder">
            <span class="rounded-circle d-flex align-items-center justify-content-center mb-3"
                style="width:56px;height:56px;background:rgba(105,108,255,.1);">
                <i class="ti ti-file-search fs-3" style="color:#696cff;"></i>
            </span>
            <div class="fw-semibold text-muted">Select a file from the tree</div>
            <small class="text-muted">Click any file to view its analysis</small>
        </div>

        {{-- Loading --}}
        <div id="panelLoading" class="card d-none" style="min-height:300px;">
            <div class="card-body d-flex flex-column align-items-center justify-content-center">
                <div class="spinner-border text-primary mb-3" role="status"></div>
                <p class="text-muted mb-0">Analyzing file...</p>
            </div>
        </div>

        {{-- Result --}}
        <div id="panelResult" class="card d-none" style="border:1px solid #e7e3f1;">
            <div class="card-header d-flex align-items-center justify-content-between bg-white" style="border-radius:12px 12px 0 0;">
                <span class="fw-semibold small" id="rFileName">—</span>
                <a id="rGithubLink" href="#" target="_blank" class="btn btn-sm btn-outline-secondary">
                    <i class="ti ti-brand-github me-1"></i> GitHub
                </a>
            </div>
            <div class="card-body">

                {{-- Classification --}}
                <div class="text-center mb-4">
                    <span id="rClassBadge" class="badge px-4 py-2" style="font-size:.9rem;">—</span>
                </div>

                {{-- Confidence --}}
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small fw-semibold">Confidence Score</span>
                        <span class="fw-bold" id="rConfText">0%</span>
                    </div>
                    <div class="progress" style="height:12px;border-radius:6px;">
                        <div id="rConfBar" class="progress-bar bg-primary" style="width:0%;border-radius:6px;"></div>
                    </div>
                </div>

                {{-- Explanation --}}
                <div class="mb-3">
                    <div class="fw-semibold small mb-2"><i class="ti ti-notes me-1 text-primary"></i>Analysis Explanation</div>
                    <p id="rExplanation" class="text-muted small p-3 mb-0 rounded" style="background:#f8f7fa;">—</p>
                </div>

                {{-- Signals --}}
                <div class="mb-3">
                    <div class="fw-semibold small mb-2"><i class="ti ti-list-check me-1 text-primary"></i>Detection Signals</div>
                    <div id="rSignals" class="d-flex flex-wrap gap-2"></div>
                </div>

                {{-- Suggestion --}}
                <div id="rSuggestionBlock" class="alert alert-warning py-2 d-none small">
                    <i class="ti ti-bulb me-2"></i><span id="rSuggestion"></span>
                </div>

            </div>
        </div>

    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.16/jstree.min.js"></script>
<script>
(function(){
    const JOB_ID = {{ $job?->id ?? 'null' }};
    const REPO_URL = "{{ $repository->github_url }}";
    const BRANCH   = "{{ $repository->default_branch ?? 'main' }}";

    function loadTree() {
        if (!JOB_ID) { buildEmpty(); return; }
        fetch(`/api/v1/analysis/${JOB_ID}/file-tree`, { headers:{ 'Accept':'application/json' } })
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.data.length) { buildEmpty(); return; }
            buildTree(data.data);
        }).catch(buildEmpty);
    }

    function buildTree(files) {
        const nodes = {};
        const root  = [];

        files.forEach(file => {
            const parts = file.path.split('/');
            let current = root;
            let builtPath = '';

            parts.forEach((part, i) => {
                builtPath = builtPath ? builtPath + '/' + part : part;
                const isLast = i === parts.length - 1;

                if (!nodes[builtPath]) {
                    const iconColor = isLast
                        ? (file.class === 'ai' ? '#ea5455' : file.class === 'human' ? '#28c76f' : '#6c757d')
                        : '#ff9f43';
                    const icon = isLast
                        ? `<span style="color:${iconColor}" class="ti ti-file-code"></span>`
                        : `<span style="color:#ff9f43" class="ti ti-folder"></span>`;

                    const node = {
                        id:       builtPath,
                        text:     part,
                        children: isLast ? false : [],
                        icon:     isLast
                            ? (file.class==='ai' ? 'ti ti-file-code' : file.class==='human' ? 'ti ti-file-check' : 'ti ti-file')
                            : 'ti ti-folder',
                        data: isLast ? { path: file.path, cls: file.class, conf: file.confidence } : null,
                    };

                    nodes[builtPath] = node;
                    current.push(node);
                }
                if (!isLast && nodes[builtPath].children) {
                    current = nodes[builtPath].children;
                }
            });
        });

        $('#jsFileTree').jstree('destroy');
        $('#jsFileTree').jstree({
            core: { data: root, themes: { icons: true, dots: false, stripes: false } },
            plugins: ['wholerow']
        }).on('select_node.jstree', function(e, d) {
            if (d.node.data && d.node.data.path) loadResult(d.node.data.path);
        });
    }

    function buildEmpty() {
        $('#jsFileTree').jstree({ core: { data: [{ text:'No files available', icon:'ti ti-info-circle' }] } });
    }

    function loadResult(filePath) {
        document.getElementById('panelPlaceholder').classList.add('d-none');
        document.getElementById('panelLoading').classList.remove('d-none');
        document.getElementById('panelResult').classList.add('d-none');

        if (!JOB_ID) { showPlaceholder(); return; }

        fetch(`/api/v1/analysis/${JOB_ID}/file?file_path=${encodeURIComponent(filePath)}`, {
            headers: { 'Accept':'application/json' }
        })
        .then(r => r.json())
        .then(data => {
            document.getElementById('panelLoading').classList.add('d-none');
            if (data.success) renderResult(filePath, data.data);
            else renderNotAnalyzed(filePath);
        })
        .catch(() => { document.getElementById('panelLoading').classList.add('d-none'); showPlaceholder(); });
    }

    function renderResult(filePath, r) {
        document.getElementById('panelResult').classList.remove('d-none');
        document.getElementById('rFileName').textContent = filePath.split('/').pop();
        document.getElementById('rGithubLink').href = `${REPO_URL}/blob/${BRANCH}/${filePath}`;

        // Badge
        const badge = document.getElementById('rClassBadge');
        const cfg = { ai:{ t:'AI-Generated', bg:'#ea5455' }, human:{ t:'Human-Written', bg:'#28c76f' }, uncertain:{ t:'Uncertain', bg:'#6c757d' } };
        const c = cfg[r.classification] || cfg.uncertain;
        badge.textContent = c.t;
        badge.style.background = c.bg;
        badge.style.color = '#fff';

        // Confidence
        const pct = r.confidence_score || 0;
        const barColor = r.classification==='ai' ? '#ea5455' : r.classification==='human' ? '#28c76f' : '#6c757d';
        document.getElementById('rConfText').textContent = pct + '%';
        document.getElementById('rConfBar').style.width = pct + '%';
        document.getElementById('rConfBar').style.background = barColor;

        // Explanation
        document.getElementById('rExplanation').textContent = r.explanation || 'No explanation available.';

        // Signals
        const signalEl = document.getElementById('rSignals');
        signalEl.innerHTML = '';
        const labels = { uniform_naming:'Uniform Naming', excessive_comments:'Excessive Comments', has_debug_artifacts:'Debug Artifacts', has_todos_or_wip:'TODOs / WIP', boilerplate_heavy:'Boilerplate Heavy', inconsistent_style:'Inconsistent Style' };
        if (r.signals) {
            Object.entries(r.signals).forEach(([k, v]) => {
                const color = v ? 'rgba(234,84,85,.12);color:#ea5455' : 'rgba(40,199,111,.12);color:#28c76f';
                const icon  = v ? 'ti ti-check' : 'ti ti-x';
                signalEl.innerHTML += `<span class="badge" style="background:${color};font-size:.72rem;"><i class="${icon} me-1"></i>${labels[k]||k}</span>`;
            });
        }

        // Suggestion
        if (r.suggestion) {
            document.getElementById('rSuggestion').textContent = r.suggestion;
            document.getElementById('rSuggestionBlock').classList.remove('d-none');
        } else {
            document.getElementById('rSuggestionBlock').classList.add('d-none');
        }
    }

    function renderNotAnalyzed(filePath) {
        document.getElementById('panelResult').classList.remove('d-none');
        document.getElementById('rFileName').textContent = filePath.split('/').pop();
        document.getElementById('rClassBadge').textContent = 'Not Analyzed Yet';
        document.getElementById('rClassBadge').style.background = '#ff9f43';
        document.getElementById('rClassBadge').style.color = '#fff';
        document.getElementById('rConfText').textContent = '—';
        document.getElementById('rConfBar').style.width = '0%';
        document.getElementById('rExplanation').textContent = 'This file has not been analyzed yet. Run Bulk Analysis to analyze all files at once.';
        document.getElementById('rSignals').innerHTML = '';
        document.getElementById('rSuggestionBlock').classList.add('d-none');
    }

    function showPlaceholder() {
        document.getElementById('panelPlaceholder').classList.remove('d-none');
    }

    loadTree();
})();
</script>
