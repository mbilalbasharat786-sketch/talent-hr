@extends('layouts.app', ['role' => 'candidate'])
@section('title', 'Application')

@section('content')
<style>
    /* Overlap fix to keep modal on top of the backdrop */
    .modal-backdrop { z-index: 1040 !important; }
    .modal { z-index: 1050 !important; }
    .list-group-numbered > li::before { font-weight: bold; color: #0d6efd; }
</style>

<div class="page-header">
    <div><h1 id="jobTitle">Application</h1><p id="meta" class="text-muted small"></p></div>
    <a href="/candidate/applications" class="btn btn-light">Back</a>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card"><div class="card-header fw-bold">Status timeline</div><div class="card-body" id="timeline">Loading…</div></div>
        <div class="card mt-3"><div class="card-header fw-bold">Second-round task</div><div class="card-body" id="taskBox">—</div></div>
    </div>
    <div class="col-lg-5">
        <div class="card"><div class="card-header fw-bold">Quick actions</div><div class="card-body d-grid gap-2" id="actions">—</div></div>
        <div class="card mt-3"><div class="card-header fw-bold">Interview</div><div class="card-body" id="interviewBox">—</div></div>
    </div>
</div>

<div class="modal fade" id="taskUploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="taskUploadForm" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Submit Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small">
                        Please upload your solution in a single ZIP or PDF file as per the instructions.
                    </div>
                    <input type="hidden" name="task_id" id="taskIdInput">
                    <div class="mb-3">
                        <label class="form-label">File (PDF/DOC/ZIP)</label>
                        <input class="form-control" type="file" name="submission_file" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload Solution</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
const id = {{ $id }};
let taskUploadModalInstance;

function forceCleanup() {
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
    document.body.classList.remove('modal-open');
    document.body.style = '';
}

async function load() {
    try {
        const r = await THR.api('/candidate/applications/' + id);
        const a = r.application || r;
        
        document.getElementById('jobTitle').textContent = a.job?.title || 'Application';
        document.getElementById('meta').innerHTML = `${THR.statusPill(a.status)} · Applied ${THR.fmtDate(a.created_at)}`;

        // LOGIC: Assessment Status determine karna
        let assessmentStatusHtml = '<span class="badge bg-secondary">pending</span>';
        if (r.assessment_submission) {
            assessmentStatusHtml = THR.statusPill(r.assessment_submission.status);
        } else if (a.task?.status === 'passed' || a.status === 'interview_scheduled') {
            // Agar task pass ho gaya ya interview schedule ho gaya, to purana assessment "Passed" hi hoga
            assessmentStatusHtml = '<span class="badge bg-success">passed</span>';
        }

        document.getElementById('timeline').innerHTML = `
            <ol class="list-group list-group-numbered">
                <li class="list-group-item d-flex justify-content-between align-items-start">
                    <div class="ms-2 me-auto">Application submitted</div>
                    <span>${THR.fmtDate(a.created_at)}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-start">
                    <div class="ms-2 me-auto">Assessment / Task</div>
                    ${assessmentStatusHtml}
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-start">
                    <div class="ms-2 me-auto">Current Pipeline Status</div>
                    ${THR.statusPill(a.status)}
                </li>
                ${a.rejection_reason ? `<li class="list-group-item list-group-item-danger">Reason: ${THR.escapeHtml(a.rejection_reason)}</li>` : ''}
            </ol>`;

        // Actions Logic
        const acts = [];
        if (['assessment_pending'].includes(a.status) && a.job?.assessment_id) {
            acts.push(`<a class="btn btn-primary" href="/candidate/assessment?application_id=${a.id}"><i class="bi bi-pencil-square"></i> Start assessment</a>`);
        }
        
        // Show upload button only before the first submission. Submitted work is locked.
        if (a.task && a.task.status === 'assigned') {
            acts.push(`<button class="btn btn-info text-white" id="openTaskUpload"><i class="bi bi-upload"></i> Upload task</button>`);
        }
        
        const actionsDiv = document.getElementById('actions');
        actionsDiv.innerHTML = acts.length ? acts.join('') : '<span class="text-muted">No actions available right now.</span>';
        
        // Re-attach event listener
        const openBtn = document.getElementById('openTaskUpload');
        if (openBtn) {
            openBtn.addEventListener('click', () => { 
                document.getElementById('taskIdInput').value = a.task.id; 
                if(taskUploadModalInstance) taskUploadModalInstance.show();
            });
        }

        // Task Box Update
        if (a.task) {
            let taskFileHtml = a.task.instructions_file 
                ? `<div class="mt-2"><a href="${a.task.instructions_file}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-earmark-arrow-down"></i> Download Instructions</a></div>` 
                : '';

            document.getElementById('taskBox').innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <strong>${THR.escapeHtml(a.task.title)}</strong>
                    ${THR.statusPill(a.task.status)}
                </div>
                <p class="small text-secondary mb-2">${THR.escapeHtml(a.task.description || 'No description provided.')}</p>
                <p class="small text-muted mb-0"><i class="bi bi-clock"></i> Deadline: ${THR.fmtDate(a.task.deadline)}</p>
                ${taskFileHtml}
            `;
        } else {
            document.getElementById('taskBox').innerHTML = '<span class="text-muted">No task assigned yet.</span>';
        }

        // Interview Box Update (Clean Format)
        if (a.interview) {
            // Formatting the date/time string to be more readable
            const interviewDate = new Date(a.interview.date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
            document.getElementById('interviewBox').innerHTML = `
                <div class="p-2 border rounded bg-light">
                    <p class="mb-1 text-primary"><strong><i class="bi bi-calendar-event"></i> ${interviewDate}</strong></p>
                    <p class="mb-1"><strong><i class="bi bi-clock"></i> ${a.interview.time}</strong></p>
                    <p class="mb-0 text-muted small">Mode: <span class="badge bg-white text-dark border">${THR.escapeHtml(a.interview.mode)}</span></p>
                </div>
            `;
        } else {
            document.getElementById('interviewBox').innerHTML = '<span class="text-muted">No interview scheduled</span>';
        }

    } catch (e) { 
        console.error(e);
        THR.toast(e.message, 'danger'); 
    }
}

window.addEventListener('DOMContentLoaded', () => {
    const modalEl = document.getElementById('taskUploadModal');
    document.body.appendChild(modalEl);
    
    taskUploadModalInstance = new bootstrap.Modal(modalEl, {
        backdrop: 'static'
    });
    
    load();
});

document.getElementById('taskUploadForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true;
    try { 
        await THR.api('/candidate/task/submit', { method: 'POST', body: new FormData(e.target) }); 
        THR.toast('Task submitted successfully','success'); 
        taskUploadModalInstance.hide(); 
        forceCleanup();
        load(); 
    } catch (err) { 
        THR.toast(err.message, 'danger'); 
    } finally {
        btn.disabled = false;
    }
});
</script>
@endpush
@endsection
