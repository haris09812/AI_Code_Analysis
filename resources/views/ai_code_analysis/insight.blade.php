{{-- resources/views/ai_code_analysis/insights.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>GitHub Repository Insights</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet">
    <style>
        body { background:#f5f5f9; font-family:'Public Sans',sans-serif; }
        .card { border-radius:12px; box-shadow:0 2px 10px rgba(47,43,61,.08); border:1px solid #e7e3f1; }
        .stat-card { color:#fff; border-radius:12px; padding:20px 24px; border:none; }
        .stat-card.blue   { background:linear-gradient(135deg,#4e73e5,#696cff); }
        .stat-card.green  { background:linear-gradient(135deg,#20c476,#28c76f); }
        .stat-card.purple { background:linear-gradient(135deg,#9f3fbf,#a855f7); }
        .stat-card.orange { background:linear-gradient(135deg,#e08b2a,#ff9f43); }
        .analyze-card { background:linear-gradient(135deg,#f0f4ff 0%,#f5f0ff 100%); border:1px solid #d8d5f5; border-radius:12px; padding:24px; }
        .btn-analyze { background:#696cff; color:#fff; border:none; padding:10px 22px; border-radius:8px; font-weight:600; }
        .btn-analyze:hover { background:#5f61e6; color:#fff; }
        .btn-single { border:1px solid #ccc; background:#fff; padding:10px 22px; border-radius:8px; font-weight:500; }
        .btn-single:hover { background:#f5f5f5; }
        .lang-bar { height:8px; border-radius:4px; background:#696cff; }
        .commit-row { padding:12px 0; border-bottom:1px solid #f0eef5; }
        .commit-row:last-child { border-bottom:none; }
        .avatar-circle { width:36px; height:36px; border-radius:50%; background:#696cff; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.85rem; flex-shrink:0; }
        .contributor-wrap { border:1px solid #e7e3f1; border-radius:10px; padding:14px; background:#fff; }
        /* Loading overlay */
        #pageLoader { position:fixed; inset:0; background:rgba(245,245,249,.92); z-index:9999; display:flex; flex-direction:column; align-items:center; justify-content:center; }
    </style>
</head>
<body>

{{-- Full page loader while fetching metadata --}}
<div id="pageLoader">
    <div class="spinner-border text-primary mb-3" style="width:3rem;height:3rem;"></div>
    <div class="fw-semibold" style="color:#2f2b3d;">Fetching Repository Data...</div>
    <div class="text-muted small mt-1">Connecting to GitHub API</div>
</div>

<div id="pageContent" class="d-none">
<div class="container-fluid px-4 py-4" style="max-width:1200px;">

    {{-- Header --}}
    <div class="d-flex align-items-start justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1" style="color:#2f2b3d;">GitHub Repository Insights</h4>
            <div class="text-muted small" id="projectSubtitle">Loading...</div>
            <div class="small mt-1">
                Detailed analytics for
                <a id="repoLink" href="#" target="_blank" class="fw-semibold text-primary" style="word-break:break-all;"></a>
            </div>
        </div>
        <a href="{{ route('analyzer.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="ti ti-arrow-left me-1"></i> Back
        </a>
    </div>

    {{-- AI Analysis Card --}}
    <div class="analyze-card mb-4">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-start gap-3 flex-grow-1">
                <span class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                    style="width:48px;height:48px;background:rgba(105,108,255,.15);">
                    <i class="ti ti-bulb fs-4" style="color:#696cff;"></i>
                </span>
                <div>
                    <div class="fw-bold mb-1" style="color:#2f2b3d;font-size:1.05rem;">AI Code Generation Analysis</div>
                    <div class="text-muted small mb-2">Advanced AI-powered detection system</div>
                    <p class="text-muted small mb-3">
                        Analyze your repository code files using our LLM-powered AI model to detect AI-generated content.
                        Our system examines individual code files and provides detailed confidence scores for each analysis,
                        helping you understand the nature of your codebase.
                    </p>
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-analyze" onclick="goToAnalyze()">
                            <i class="ti ti-file-description me-2"></i>Analyze Repository Code
                        </button>
                        <button class="btn btn-single" onclick="goToAnalyze()">
                            <i class="ti ti-search me-2"></i>Single Code Analysis
                        </button>
                    </div>
                </div>
            </div>
            <span class="rounded-circle d-flex align-items-center justify-content-center"
                style="width:56px;height:56px;background:rgba(105,108,255,.1);">
                <i class="ti ti-device-desktop fs-3" style="color:#a855f7;"></i>
            </span>
        </div>
    </div>

    {{-- 4 Stat Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card blue">
                <div class="fw-bold" style="font-size:1.8rem;" id="sc_commits">—</div>
                <div style="opacity:.85;font-size:.88rem;">Total Commits</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card green">
                <div class="fw-bold" style="font-size:1.8rem;" id="sc_contributors">—</div>
                <div style="opacity:.85;font-size:.88rem;">Contributors</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card purple">
                <div class="fw-bold" style="font-size:1.8rem;" id="sc_prs">—</div>
                <div style="opacity:.85;font-size:.88rem;">Pull Requests</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card orange">
                <div class="fw-bold" style="font-size:1.8rem;" id="sc_stars">—</div>
                <div style="opacity:.85;font-size:.88rem;">Stars</div>
            </div>
        </div>
    </div>

    {{-- Repository Information --}}
    <div class="card p-4 mb-4">
        <h6 class="fw-bold mb-3" style="color:#2f2b3d;">Repository Information</h6>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="text-muted small mb-1">Description</div>
                <div class="small fw-semibold" id="ri_desc">—</div>
            </div>
            <div class="col-md-6">
                <div class="text-muted small mb-1">Primary Language</div>
                <div class="small fw-semibold" id="ri_lang">—</div>
            </div>
            <div class="col-md-6">
                <div class="text-muted small mb-1">Created</div>
                <div class="small fw-semibold" id="ri_created">—</div>
            </div>
            <div class="col-md-6">
                <div class="text-muted small mb-1">Repository Age</div>
                <div class="small fw-semibold" id="ri_age">—</div>
            </div>
            <div class="col-md-6">
                <div class="text-muted small mb-1">Default Branch</div>
                <div class="small"><code id="ri_branch">—</code></div>
            </div>
            <div class="col-md-6">
                <div class="text-muted small mb-1">Forks</div>
                <div class="small fw-semibold" id="ri_forks">—</div>
            </div>
        </div>
    </div>

    {{-- Programming Languages --}}
    <div class="card p-4 mb-4" id="langsCard" style="display:none!important;">
        <h6 class="fw-bold mb-3" style="color:#2f2b3d;">Programming Languages</h6>
        <div id="langsContainer"></div>
    </div>

    {{-- Pull Requests --}}
    <div class="card p-4 mb-4">
        <h6 class="fw-bold mb-3" style="color:#2f2b3d;">Pull Requests Overview</h6>
        <div class="row text-center">
            <div class="col-4">
                <div class="fw-bold fs-3 text-success" id="pr_open">0</div>
                <div class="text-muted small">Open</div>
            </div>
            <div class="col-4">
                <div class="fw-bold fs-3 text-secondary">0</div>
                <div class="text-muted small">Closed</div>
            </div>
            <div class="col-4">
                <div class="fw-bold fs-3" style="color:#a855f7;" id="pr_merged">0</div>
                <div class="text-muted small">Merged</div>
            </div>
        </div>
    </div>

    {{-- Contributors --}}
    <div class="card mb-4" id="contribCard" style="display:none!important;">
        <div class="card-header d-flex align-items-center justify-content-between bg-white" style="border-radius:12px 12px 0 0;">
            <h6 class="fw-bold mb-0" style="color:#2f2b3d;">Contributors (<span id="contribCount">0</span>)</h6>
            <a id="contribGithubLink" href="#" target="_blank" class="btn btn-sm btn-dark">
                <i class="ti ti-external-link me-1"></i> View Contributors on GitHub
            </a>
        </div>
        <div class="card-body">
            <div class="row g-3" id="contribContainer"></div>
        </div>
    </div>

    {{-- Recent Commits --}}
    <div class="card mb-5" id="commitsCard" style="display:none!important;">
        <div class="card-header d-flex align-items-center justify-content-between bg-white" style="border-radius:12px 12px 0 0;">
            <h6 class="fw-bold mb-0" style="color:#2f2b3d;">Recent Commits</h6>
            <a id="commitsGithubLink" href="#" target="_blank" class="btn btn-sm btn-dark">
                <i class="ti ti-external-link me-1"></i> View All Commits on GitHub
            </a>
        </div>
        <div class="card-body p-0 px-3" id="commitsContainer"></div>
    </div>

</div>
</div>{{-- end pageContent --}}

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const REPO_URL = new URLSearchParams(window.location.search).get('repo') || '';

function goToAnalyze() {
    window.location.href = '/analyzer/analyze?repo=' + encodeURIComponent(REPO_URL);
}

function loadMetadata() {
    if (!REPO_URL) {
        window.location.href = '/analyzer';
        return;
    }

    fetch('/api/v1/repositories/metadata', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ github_url: REPO_URL })
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('pageLoader').style.display = 'none';

        if (!data.success) {
            alert('Error: ' + data.message);
            window.location.href = '/analyzer';
            return;
        }

        renderPage(data.data);
        document.getElementById('pageContent').classList.remove('d-none');
    })
    .catch(err => {
        document.getElementById('pageLoader').style.display = 'none';
        alert('Network error. Please try again.');
        window.location.href = '/analyzer';
    });
}

function renderPage(meta) {
    const identity = meta.identity   || {};
    const stats    = meta.stats      || {};
    const history  = meta.history    || {};
    const prs      = meta.pull_requests || {};
    const contributors = meta.contributors   || [];
    const commits      = meta.recent_commits || [];

    // Header
    document.getElementById('projectSubtitle').textContent = identity.full_name || '';
    const repoLink = document.getElementById('repoLink');
    repoLink.href = identity.url || REPO_URL;
    repoLink.textContent = identity.full_name || REPO_URL;

    // Stat cards
    document.getElementById('sc_commits').textContent     = (meta.commits_count || 0).toLocaleString();
    document.getElementById('sc_contributors').textContent = contributors.length;
    document.getElementById('sc_prs').textContent          = (prs.open || 0) + (prs.merged || 0);
    document.getElementById('sc_stars').textContent        = (stats.stars || 0).toLocaleString();

    // Repo info
    document.getElementById('ri_desc').textContent    = identity.description || 'No description.';
    document.getElementById('ri_lang').textContent    = stats.primary_lang   || '—';
    document.getElementById('ri_created').textContent = history.created_at   ? new Date(history.created_at).toLocaleDateString() : '—';
    document.getElementById('ri_age').textContent     = history.repo_age     || '—';
    document.getElementById('ri_branch').textContent  = history.default_branch || 'main';
    document.getElementById('ri_forks').textContent   = stats.forks || 0;

    // PR
    document.getElementById('pr_open').textContent   = prs.open   || 0;
    document.getElementById('pr_merged').textContent = prs.merged || 0;

    // Languages
    const langs = stats.languages || {};
    if (Object.keys(langs).length > 0) {
        const total = Object.values(langs).reduce((a, b) => a + b, 0);
        let html = '';
        Object.entries(langs).forEach(([lang, bytes]) => {
            const pct = total > 0 ? ((bytes / total) * 100).toFixed(1) : 0;
            html += `
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-semibold small">${lang}</span>
                        <span class="text-muted small">${pct}%</span>
                    </div>
                    <div class="progress" style="height:8px;border-radius:4px;">
                        <div class="lang-bar" style="width:${pct}%;"></div>
                    </div>
                </div>`;
        });
        document.getElementById('langsContainer').innerHTML = html;
        document.getElementById('langsCard').style.removeProperty('display');
    }

    // Contributors
    if (contributors.length > 0) {
        document.getElementById('contribCount').textContent = contributors.length;
        document.getElementById('contribGithubLink').href   = (identity.url || REPO_URL) + '/graphs/contributors';
        let html = '';
        contributors.forEach(c => {
            html += `
            <div class="col-md-6">
                <div class="contributor-wrap d-flex align-items-center gap-3">
                    <div class="position-relative">
                        <img src="${c.avatar}" class="rounded-circle" width="44" height="44" alt="${c.name}">
                        <span class="position-absolute" style="bottom:0;right:0;width:12px;height:12px;background:#28c76f;border-radius:50%;border:2px solid #fff;"></span>
                    </div>
                    <div class="flex-grow-1 min-width-0">
                        <div class="fw-semibold text-truncate">${c.name}</div>
                        <small class="text-muted">
                            <i class="ti ti-git-commit me-1"></i>${c.commits} commits &bull; GitHub member
                        </small>
                    </div>
                    <a href="${c.profile}" target="_blank" class="btn btn-sm btn-outline-secondary flex-shrink-0">View Profile</a>
                </div>
            </div>`;
        });
        document.getElementById('contribContainer').innerHTML = html;
        document.getElementById('contribCard').style.removeProperty('display');
    }

    // Commits
    if (commits.length > 0) {
        document.getElementById('commitsGithubLink').href = (identity.url || REPO_URL) + '/commits';
        let html = '';
        commits.forEach(c => {
            const initial = (c.author || '?').charAt(0).toUpperCase();
            html += `
            <div class="commit-row d-flex align-items-start gap-3">
                <div class="avatar-circle flex-shrink-0">${initial}</div>
                <div class="flex-grow-1 min-width-0">
                    <div class="fw-semibold small text-truncate">${escHtml(c.message || '')}</div>
                    <small class="text-muted"><strong>${escHtml(c.author || '')}</strong> &bull; ${c.date || ''}
                        ${c.url ? `&bull; <a href="${c.url}" target="_blank" class="text-primary">View</a>` : ''}
                    </small>
                </div>
            </div>`;
        });
        document.getElementById('commitsContainer').innerHTML = html;
        document.getElementById('commitsCard').style.removeProperty('display');
    }
}

function escHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Boot
loadMetadata();
</script>
</body>
</html>
