{{-- resources/views/ai_code_analysis/index.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AI Code Analyzer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet">
    <style>
        body { background:#f5f5f9; font-family:'Public Sans',sans-serif; min-height:100vh; display:flex; align-items:center; justify-content:center; }
        .card { border-radius:12px; box-shadow:0 2px 12px rgba(47,43,61,.09); border:1px solid #e7e3f1; }
        .btn-primary-custom { background:#696cff; border:none; color:#fff; padding:12px 28px; border-radius:8px; font-weight:600; font-size:.95rem; }
        .btn-primary-custom:hover { background:#5f61e6; color:#fff; }
        .input-group-text { background:#fff; border-right:none; }
        .form-control { border-left:none; }
        .form-control:focus { box-shadow:none; border-color:#dee2e6; }
    </style>
</head>
<body>
<div style="width:100%;max-width:620px;padding:24px 16px;">

    <div class="text-center mb-4">
        <span class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:56px;height:56px;background:rgba(105,108,255,.12);">
            <i class="ti ti-brand-github fs-2" style="color:#696cff;"></i>
        </span>
        <h4 class="fw-bold mb-1" style="color:#2f2b3d;">AI Code Analyzer</h4>
        <p class="text-muted mb-0">Enter a GitHub repository URL to get started</p>
    </div>

    <div class="card p-4">

        <div id="errorBox" class="alert alert-danger d-none mb-3">
            <i class="ti ti-alert-circle me-2"></i><span id="errorMsg"></span>
        </div>

        <label class="form-label fw-semibold">GitHub Repository URL</label>
        <div class="input-group mb-2">
            <span class="input-group-text"><i class="ti ti-brand-github text-muted"></i></span>
            <input type="url" id="repoUrl" class="form-control form-control-lg"
                placeholder="https://github.com/owner/repository">
        </div>
        <div class="text-muted small mb-4">Example: https://github.com/laravel/laravel</div>

        <button id="nextBtn" class="btn btn-primary-custom w-100" onclick="goNext()">
            <i class="ti ti-arrow-right me-2"></i>Next
        </button>

        {{-- Loading --}}
        <div id="loadingBox" class="text-center mt-3 d-none">
            <div class="spinner-border text-primary spinner-border-sm me-2"></div>
            <span class="text-muted small">Fetching repository info...</span>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function goNext() {
    const url = document.getElementById('repoUrl').value.trim();
    if (!url) { showError('Please enter a GitHub repository URL.'); return; }
    if (!url.match(/^https:\/\/github\.com\/[^\/]+\/[^\/]+/)) {
        showError('Please enter a valid GitHub URL. Example: https://github.com/owner/repo');
        return;
    }
    hideError();
    document.getElementById('nextBtn').disabled = true;
    document.getElementById('loadingBox').classList.remove('d-none');

    // Redirect to insights page with URL as query param
    window.location.href = '/analyzer/insights?repo=' + encodeURIComponent(url);
}
function showError(msg) {
    document.getElementById('errorMsg').textContent = msg;
    document.getElementById('errorBox').classList.remove('d-none');
    document.getElementById('nextBtn').disabled = false;
    document.getElementById('loadingBox').classList.add('d-none');
}
function hideError() { document.getElementById('errorBox').classList.add('d-none'); }
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('repoUrl').addEventListener('keydown', e => {
        if (e.key === 'Enter') goNext();
    });
});
</script>
</body>
</html>
