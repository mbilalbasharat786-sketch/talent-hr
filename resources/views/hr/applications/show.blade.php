@extends('layouts.app', ['role' => 'hr'])
@section('title', 'Application Detail')

@section('content')
<style>
    /* Z-index overlap fix */
    .modal-backdrop { z-index: 1040 !important; }
    .modal { z-index: 1050 !important; }
    /* Scoped button logic for cleaner UI */
    .action-btn { display: none; }
</style>

<div class="page-header">
    <div><h1>Application</h1><p id="meta" class="text-muted small"></p></div>
    <a href="/hr/applications" class="btn btn-light">Back</a>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card"><div class="card-header fw-bold">Candidate</div><div class="card-body" id="candBody">Loading…</div></div>
        <div class="card mt-3"><div class="card-header fw-bold">Assessment</div><div class="card-body" id="assBody">—</div></div>
        <div class="card mt-3"><div class="card-header fw-bold">Anti-cheat & verification</div><div class="card-body" id="antiBody">—</div></div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header fw-bold">Pipeline actions</div>
            <div class="card-body d-grid gap-2" id="actionPanel">
                <button class="btn btn-success action-btn" id="shortlistBtn"><i class="bi bi-star"></i> Shortlist</button>
                <button class="btn btn-info text-white action-btn" id="taskBtn"><i class="bi bi-clipboard-check"></i> Assign 2nd round task</button>
                <button class="btn btn-primary action-btn" id="reviewTaskBtn"><i class="bi bi-clipboard-data"></i> Review task</button>
                <button class="btn btn-warning action-btn" id="interviewBtn"><i class="bi bi-calendar-event"></i> Schedule interview</button>
                <button class="btn btn-danger action-btn" id="rejectBtn"><i class="bi bi-x-circle"></i> Reject</button>
                <p id="noActionMsg" class="text-muted small text-center d-none">No actions available for current state.</p>
            </div>
        </div>
    </div>
</div>

<!-- Task Assignment Modal -->
<div class="modal fade" id="taskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="taskForm" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Assign 2nd Round Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Title</label><input class="form-control" name="title" required></div>
                    <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="4" required></textarea></div>
                    <div class="mb-3"><label class="form-label">Deadline</label><input type="datetime-local" name="deadline" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Instructions file</label><input type="file" name="instructions_file" class="form-control"></div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary">Assign Task</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Interview Modal -->
<div class="modal fade" id="interviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="interviewForm">
                <div class="modal-header">
                    <h5 class="modal-title">Schedule Interview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Date</label><input type="date" name="date" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Time</label><input type="time" name="time" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Mode</label><select class="form-select" name="mode" required><option value="onsite">Onsite</option><option value="online">Online</option><option value="hybrid">Hybrid</option></select></div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary">Schedule</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Review Task Modal -->
<div class="modal fade" id="reviewTaskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Review Candidate Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="reviewTaskBody">
                <p class="text-muted text-center">Loading submission details...</p>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-success me-2" onclick="submitReview('passed')">Mark as Passed</button>
                    <button type="button" class="btn btn-danger" onclick="submitReview('failed')">Mark as Failed</button>
                </div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
const id = {{ $id }};
let taskModal, interviewModal, reviewTaskModal;
let appData = null;

function forceCleanup() {
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
    document.body.classList.remove('modal-open');
    document.body.style = '';
}

function parseJson(value, fallback = null) {
    if (!value) return fallback;
    if (typeof value === 'object') return value;
    try { return JSON.parse(value); } catch (e) { return fallback; }
}

function flagLabel(flag) {
    const labels = {
        normal: ['Normal', 'success'],
        suspicious: ['Cheating Suspected', 'warning'],
        cheating_detected: ['Cheating Detected', 'danger']
    };
    const item = labels[flag] || ['Not available', 'secondary'];
    return `<span class="badge bg-${item[1]}">${item[0]}</span>`;
}

async function load() {
    try {
        const r = await THR.api('/hr/applications/' + id);
        appData = r.application || r;
        const a = appData;
        
        document.getElementById('meta').innerHTML = `Status: ${THR.statusPill(a.status)} · Job: ${THR.escapeHtml(a.job?.title||'—')}`;
        document.getElementById('candBody').innerHTML = `
            <dl class="row mb-0">
                <dt class="col-sm-3">Name</dt><dd class="col-sm-9">${THR.escapeHtml(a.candidate?.name||'—')}</dd>
                <dt class="col-sm-3">Email</dt><dd class="col-sm-9">${THR.escapeHtml(a.candidate?.email||'—')}</dd>
                <dt class="col-sm-3">Skill match</dt><dd class="col-sm-9">${a.skill_match_percentage??'—'}%</dd>
                <dt class="col-sm-3">2nd round task</dt><dd class="col-sm-9">${a.task? `${THR.escapeHtml(a.task.title)} (${THR.statusPill(a.task.status)})` : '—'}</dd>
                <dt class="col-sm-3">Interview</dt><dd class="col-sm-9">${a.interview? `${a.interview.date} ${a.interview.time} (${a.interview.mode})` : '—'}</dd>
            </dl>`;

        const score = r.assessment_score_breakdown;
        document.getElementById('assBody').innerHTML = score ? `
            <dl class="row mb-0">
                <dt class="col-sm-4">Score</dt><dd class="col-sm-8">${score.score}%</dd>
                <dt class="col-sm-4">Result</dt><dd class="col-sm-8">${THR.statusPill(score.status)}</dd>
                <dt class="col-sm-4">Assessment ID</dt><dd class="col-sm-8">${score.assessment_id}</dd>
            </dl>
        ` : '<span class="text-muted">No assessment submission yet.</span>';

        const anti = parseJson(r.anti_cheat_logs, {});
        const logs = Array.isArray(anti.latest_logs) ? anti.latest_logs : [];
        document.getElementById('antiBody').innerHTML = `
            <dl class="row mb-2">
                <dt class="col-sm-4">Submission flag</dt><dd class="col-sm-8">${flagLabel(anti.flag)}</dd>
                <dt class="col-sm-4">Warnings</dt><dd class="col-sm-8">${anti.warnings ?? 0}</dd>
                <dt class="col-sm-4">Violations</dt><dd class="col-sm-8">${anti.violations ?? 0}</dd>
                <dt class="col-sm-4">Plagiarism</dt><dd class="col-sm-8">${THR.escapeHtml(r.plagiarism_report || 'Normal')}</dd>
                <dt class="col-sm-4">Experience</dt><dd class="col-sm-8">${THR.escapeHtml(r.experience_verification_status || 'Not verified')}</dd>
            </dl>
            ${logs.length ? `
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Event</th><th>Time</th></tr></thead>
                        <tbody>${logs.map(log => `<tr><td>${THR.escapeHtml(log.event_type)}</td><td>${THR.fmtDate(log.event_time)}</td></tr>`).join('')}</tbody>
                    </table>
                </div>
            ` : '<span class="text-muted small">No anti-cheat events logged.</span>'}
        `;

        updateActionButtons(a.status);

    } catch (e) { THR.toast(e.message, 'danger'); }
}

function updateActionButtons(status) {
    // Sab buttons ko pehle hide karein
    document.querySelectorAll('.action-btn').forEach(btn => btn.style.display = 'none');
    
    // Document Section 9 Logic
    if (status === 'passed') {
        document.getElementById('shortlistBtn').style.display = 'block';
        document.getElementById('rejectBtn').style.display = 'block';
    } 
    else if (status === 'shortlisted') {
        document.getElementById('taskBtn').style.display = 'block';
        document.getElementById('interviewBtn').style.display = 'block';
        document.getElementById('rejectBtn').style.display = 'block';
    }
    else if (status === 'second_task_assigned') {
        document.getElementById('reviewTaskBtn').style.display = 'block';
        document.getElementById('interviewBtn').style.display = 'block';
        document.getElementById('rejectBtn').style.display = 'block';
    }
    else if (status === 'interview_scheduled') {
        // Agar interview schedule hai toh bhi shortlist status maintain rehta hai
        document.getElementById('rejectBtn').style.display = 'block';
    }
}

window.addEventListener('DOMContentLoaded', () => {
    const tEl = document.getElementById('taskModal');
    const iEl = document.getElementById('interviewModal');
    const rEl = document.getElementById('reviewTaskModal');

    document.body.appendChild(tEl);
    document.body.appendChild(iEl);
    document.body.appendChild(rEl);

    taskModal = new bootstrap.Modal(tEl);
    interviewModal = new bootstrap.Modal(iEl);
    reviewTaskModal = new bootstrap.Modal(rEl);
    
    load();
});

// Open Review Modal
document.getElementById('reviewTaskBtn').addEventListener('click', () => {
    if (!appData || !appData.task) {
        document.getElementById('reviewTaskBody').innerHTML = `<div class="alert alert-warning">No task has been assigned yet.</div>`;
    } else if (appData.task.status === 'assigned') {
        document.getElementById('reviewTaskBody').innerHTML = `<div class="alert alert-info">Task is assigned but not yet submitted.</div>`;
    } else {
        const file = appData.task.submission_file;
        const fileHtml = file 
            ? `<div class="d-grid mt-3"><a href="${file}" target="_blank" class="btn btn-success"><i class="bi bi-download"></i> Download Submitted File</a></div>`
            : `<div class="text-danger mt-2">No file found in submission.</div>`;

        document.getElementById('reviewTaskBody').innerHTML = `
            <h6><strong>Task Details</strong></h6>
            <p class="mb-1 text-muted">Title: ${THR.escapeHtml(appData.task.title)}</p>
            <p class="mb-1 text-muted">Status: ${THR.statusPill(appData.task.status)}</p>
            ${fileHtml}
        `;
    }
    reviewTaskModal.show();
});

async function submitReview(status) {
    if(!confirm(`Are you sure you want to mark this task as ${status}?`)) return;
    try {
        await THR.api(`/hr/applications/${id}/review-task`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ status: status })
        });
        
        THR.toast(`Task marked as ${status}`, 'success');
        reviewTaskModal.hide();
        forceCleanup();
        load(); 
    } catch (e) { THR.toast(e.message, 'danger'); }
}

document.getElementById('taskBtn').addEventListener('click', () => taskModal.show());
document.getElementById('taskForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    try { 
        await THR.api(`/hr/applications/${id}/assign-task`, { method: 'POST', body: new FormData(e.target) }); 
        THR.toast('Task assigned','success'); 
        taskModal.hide(); 
        forceCleanup();
        load(); 
    } catch (err) { THR.toast(err.message, 'danger'); }
});

document.getElementById('interviewBtn').addEventListener('click', () => interviewModal.show());
document.getElementById('interviewForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    try { 
        await THR.api(`/hr/applications/${id}/schedule-interview`, { method: 'POST', body: THR.formData(e.target) }); 
        THR.toast('Interview scheduled','success'); 
        interviewModal.hide(); 
        forceCleanup();
        load(); 
    } catch (err) { THR.toast(err.message, 'danger'); }
});

document.getElementById('shortlistBtn').addEventListener('click', async () => {
    if(!confirm('Shortlist this candidate?')) return;
    try { 
        await THR.api(`/hr/applications/${id}/shortlist`, { method: 'POST' }); 
        THR.toast('Candidate Shortlisted', 'success');
        load(); 
    } catch (e) { THR.toast(e.message, 'danger'); }
});
</script>
@endpush
@endsection
