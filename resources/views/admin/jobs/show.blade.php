@extends('layouts.app', ['role' => 'admin'])
@section('title', 'Job Details')
@section('content')
<div class="page-header"><div><h1>Job Details</h1></div><a href="/admin/jobs" class="btn btn-light"><i class="bi bi-arrow-left"></i> Back</a></div>
<div class="row g-3">
    <div class="col-lg-8"><div class="card"><div class="card-body" id="jobDetails">Loading…</div></div></div>
    <div class="col-lg-4"><div class="card"><div class="card-header">Actions</div><div class="card-body d-grid gap-2">
        <button class="btn btn-success" id="approveBtn">Approve Job</button>
        <button class="btn btn-danger" id="rejectBtn">Reject Job</button>
    </div></div></div>
</div>
@push('scripts')
<script>
const id = {{ $id }};
async function load() {
    try {
        const data = await THR.api('/admin/jobs/' + id);
        const job = data.job || data;
        document.getElementById('jobDetails').innerHTML = `
            <dl class="row mb-0">
                <dt class="col-sm-3">Title</dt><dd class="col-sm-9">${THR.escapeHtml(job.title)}</dd>
                <dt class="col-sm-3">Company</dt><dd class="col-sm-9">${THR.escapeHtml(job.company?.name || '—')}</dd>
                <dt class="col-sm-3">Location</dt><dd class="col-sm-9">${THR.escapeHtml(job.location || '—')}</dd>
                <dt class="col-sm-3">Work Mode</dt><dd class="col-sm-9">${THR.escapeHtml(job.work_mode || '—')}</dd>
                <dt class="col-sm-3">Description</dt><dd class="col-sm-9">${job.description ? THR.escapeHtml(job.description) : '—'}</dd>
                <dt class="col-sm-3">Requirements</dt><dd class="col-sm-9">${job.requirements ? THR.escapeHtml(job.requirements) : '—'}</dd>
                <dt class="col-sm-3">Status</dt><dd class="col-sm-9">${THR.statusPill(job.status)}</dd>
                <dt class="col-sm-3">Created</dt><dd class="col-sm-9">${THR.fmtDate(job.created_at)}</dd>
                <dt class="col-sm-3">Assessment</dt><dd class="col-sm-9">${job.assessment ? 'Yes' : 'No'}</dd>
            </dl>`;
        
        // Update action buttons based on status
        if (job.status === 'pending') {
            document.getElementById('approveBtn').style.display = 'block';
            document.getElementById('rejectBtn').style.display = 'block';
        } else {
            document.getElementById('approveBtn').style.display = 'none';
            document.getElementById('rejectBtn').style.display = 'none';
        }
    } catch (e) { THR.toast(e.message, 'danger'); }
}
load();

document.getElementById('approveBtn').addEventListener('click', async () => {
    if (!confirm('Approve this job? It will be visible to candidates.')) return;
    try {
        await THR.api(`/admin/jobs/${id}/approve`, { method: 'POST' });
        THR.toast('Job approved successfully', 'success');
        load();
    } catch (e) { THR.toast(e.message, 'danger'); }
});

document.getElementById('rejectBtn').addEventListener('click', async () => {
    if (!confirm('Reject this job? It will not be visible to candidates.')) return;
    try {
        await THR.api(`/admin/jobs/${id}/reject`, { method: 'POST' });
        THR.toast('Job rejected successfully', 'success');
        load();
    } catch (e) { THR.toast(e.message, 'danger'); }
});
</script>
@endpush
@endsection
