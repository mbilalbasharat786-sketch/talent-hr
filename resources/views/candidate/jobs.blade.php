@extends('layouts.app', ['role' => 'candidate'])
@section('title', 'Browse Jobs')
@section('content')
<div class="page-header">
    <div>
        <h1>Browse Jobs</h1>
        <p>Apply to live roles with mandatory assessments attached</p>
    </div>
    <button class="btn btn-primary" id="refreshJobs"><i class="bi bi-arrow-clockwise me-1"></i> Refresh</button>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input class="form-control" id="jobSearch" placeholder="Search by title, company, location, or skill">
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label">Live roles</div>
                    <div class="stat-value fs-3" id="jobCount">--</div>
                </div>
                <span class="stat-icon bg-success-subtle text-success"><i class="bi bi-briefcase"></i></span>
            </div>
        </div>
    </div>
</div>

<div class="row g-3" id="jobGrid">
    <div class="col-12"><div class="card"><div class="empty-state">Loading jobs...</div></div></div>
</div>

@push('head')
<style>
    .job-card {
        height: 100%;
    }
    .job-card .card-body {
        display: flex;
        flex-direction: column;
        gap: .9rem;
    }
    .job-title {
        color: var(--thr-ink);
        font-size: 1.12rem;
        font-weight: 900;
        letter-spacing: -.03em;
        margin: 0;
    }
    .job-meta {
        color: var(--thr-muted);
        display: flex;
        flex-wrap: wrap;
        gap: .55rem .9rem;
        font-size: .86rem;
        font-weight: 700;
    }
    .skill-chip {
        display: inline-flex;
        align-items: center;
        padding: .28rem .55rem;
        border-radius: 999px;
        background: rgba(15,118,110,.08);
        color: #0f766e;
        font-size: .76rem;
        font-weight: 900;
        margin: 0 .28rem .28rem 0;
    }
    .job-description {
        color: #475569;
        font-size: .9rem;
        line-height: 1.55;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
</style>
@endpush

@push('scripts')
<script>
let allJobs = [];

function shortText(value, limit = 170) {
    const text = String(value || '').trim();
    return text.length > limit ? text.slice(0, limit).trim() + '...' : text;
}

function jobMatches(job, term) {
    if (!term) return true;
    const haystack = [
        job.title,
        job.company?.name,
        job.company?.industry,
        job.location,
        job.work_mode,
        ...(job.skills || []),
    ].join(' ').toLowerCase();

    return haystack.includes(term.toLowerCase());
}

function renderJobs() {
    const grid = document.getElementById('jobGrid');
    const term = document.getElementById('jobSearch').value.trim();
    const jobs = allJobs.filter(job => jobMatches(job, term));
    document.getElementById('jobCount').textContent = allJobs.length;

    if (!jobs.length) {
        grid.innerHTML = '<div class="col-12"><div class="card"><div class="empty-state"><i class="bi bi-search d-block mb-2"></i>No live jobs match your search.</div></div></div>';
        return;
    }

    grid.innerHTML = jobs.map(job => {
        const skills = (job.skills || []).slice(0, 6).map(skill => `<span class="skill-chip">${THR.escapeHtml(skill)}</span>`).join('');
        const applied = !!job.already_applied;
        return `<div class="col-md-6 col-xl-4">
            <div class="card job-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <h2 class="job-title">${THR.escapeHtml(job.title)}</h2>
                            <div class="text-muted fw-semibold small mt-1">${THR.escapeHtml(job.company?.name || 'Verified company')}</div>
                        </div>
                        ${THR.statusPill(job.status || 'live')}
                    </div>
                    <div class="job-meta">
                        <span><i class="bi bi-geo-alt me-1"></i>${THR.escapeHtml(job.location || 'Not specified')}</span>
                        <span><i class="bi bi-laptop me-1"></i>${THR.escapeHtml((job.work_mode || '').replace('_', ' ') || 'Work mode')}</span>
                        <span><i class="bi bi-clock me-1"></i>${job.assessment?.time_limit || 30} min assessment</span>
                    </div>
                    <p class="job-description mb-0">${THR.escapeHtml(shortText(job.description))}</p>
                    <div>${skills || '<span class="text-muted small">No skills listed</span>'}</div>
                    <div class="d-flex justify-content-between align-items-center mt-auto pt-2">
                        <span class="status-pill bg-warning-subtle text-warning">${THR.escapeHtml(job.company?.trust_level || 'basic')}</span>
                        <button class="btn ${applied ? 'btn-light' : 'btn-primary'} apply-btn" data-id="${job.id}" ${applied ? 'disabled' : ''}>
                            <i class="bi ${applied ? 'bi-check2-circle' : 'bi-send'} me-1"></i>${applied ? 'Applied' : 'Apply'}
                        </button>
                    </div>
                </div>
            </div>
        </div>`;
    }).join('');

    document.querySelectorAll('.apply-btn').forEach(btn => {
        btn.addEventListener('click', () => applyJob(parseInt(btn.dataset.id, 10), btn));
    });
}

async function loadJobs() {
    const grid = document.getElementById('jobGrid');
    grid.innerHTML = '<div class="col-12"><div class="card"><div class="empty-state">Loading jobs...</div></div></div>';
    try {
        const data = await THR.api('/candidate/jobs');
        allJobs = data.data || data || [];
        renderJobs();
    } catch (err) {
        THR.toast(err.message, 'danger');
        grid.innerHTML = '<div class="col-12"><div class="card"><div class="empty-state">Could not load jobs.</div></div></div>';
    }
}

async function applyJob(jobId, button) {
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Applying';
    try {
        const r = await THR.api('/candidate/apply', { method: 'POST', body: { job_id: jobId } });
        THR.toast('Application submitted', 'success');
        const job = allJobs.find(item => item.id === jobId);
        if (job) job.already_applied = true;
        renderJobs();
        setTimeout(() => location.href = '/candidate/applications/' + (r.application?.id || ''), 700);
    } catch (err) {
        THR.toast(err.message, 'danger');
        button.disabled = false;
        button.innerHTML = '<i class="bi bi-send me-1"></i>Apply';
    }
}

document.getElementById('refreshJobs').addEventListener('click', loadJobs);
document.getElementById('jobSearch').addEventListener('input', renderJobs);
loadJobs();
</script>
@endpush
@endsection
