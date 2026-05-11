@extends('layouts.app', ['role' => 'admin'])
@section('title', 'Job Management')
@section('content')
<div class="page-header"><div><h1>Job Management</h1><p>Approve or reject job postings</p></div></div>
<div class="card"><div class="card-body p-0">
<table class="table mb-0">
<thead><tr><th>Job</th><th>Company</th><th>Location</th><th>Work Mode</th><th>Status</th><th>Created</th><th></th></tr></thead>
<tbody id="jobs"><tr><td colspan="7" class="empty-state">Loading…</td></tr></tbody>
</table>
</div></div>
@push('scripts')
<script>
(async () => {
    try {
        const data = await THR.api('/admin/jobs');
        const jobs = data.data || data;
        const tb = document.getElementById('jobs');
        if (!jobs.length) return tb.innerHTML = '<tr><td colspan="7" class="empty-state">No jobs found</td></tr>';
        tb.innerHTML = jobs.map(job => `
            <tr>
                <td>
                    <div class="fw-semibold">${THR.escapeHtml(job.title)}</div>
                    ${job.assessment ? '<span class="badge bg-success">Has Assessment</span>' : '<span class="badge bg-warning">No Assessment</span>'}
                </td>
                <td>${THR.escapeHtml(job.company?.name || '—')}</td>
                <td>${THR.escapeHtml(job.location || '—')}</td>
                <td>${THR.escapeHtml(job.work_mode || '—')}</td>
                <td>${THR.statusPill(job.status)}</td>
                <td>${THR.fmtDate(job.created_at)}</td>
                <td>
                    <div class="btn-group">
                        <a class="btn btn-sm btn-outline-primary" href="/admin/jobs/${job.id}">View</a>
                        ${job.status === 'pending_approval' ? `
                            <button class="btn btn-sm btn-success" onclick="approveJob(${job.id})">Approve</button>
                            <button class="btn btn-sm btn-danger" onclick="rejectJob(${job.id})">Reject</button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `).join('');
    } catch (e) { THR.toast(e.message, 'danger'); }
})();

async function approveJob(jobId) {
    if (!confirm('Approve this job? It will be visible to candidates.')) return;
    try {
        await THR.api(`/admin/jobs/${jobId}/approve`, { method: 'POST' });
        THR.toast('Job approved successfully', 'success');
        location.reload();
    } catch (e) { THR.toast(e.message, 'danger'); }
}

async function rejectJob(jobId) {
    const reason = prompt('Reject this job? Please enter a rejection reason.');
    if (!reason) return;
    try {
        await THR.api(`/admin/jobs/${jobId}/reject`, { method: 'POST', body: { reason } });
        THR.toast('Job rejected successfully', 'success');
        location.reload();
    } catch (e) { THR.toast(e.message, 'danger'); }
}
</script>
@endpush
@endsection
