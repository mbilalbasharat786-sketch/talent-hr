@extends('layouts.app', ['role' => 'candidate'])
@section('title', 'My Dashboard')
@section('content')
<div class="page-header"><div><h1>My Dashboard</h1><p>Track your applications and assessments</p></div></div>

<div class="row g-3">
    <div class="col-sm-6 col-lg-3"><div class="card stat-card"><div class="stat-label">Applied jobs</div><div class="stat-value" data-k="applied_jobs">-</div></div></div>
    <div class="col-sm-6 col-lg-3"><div class="card stat-card"><div class="stat-label">Pending assessments</div><div class="stat-value" id="pendingAss">-</div></div></div>
    <div class="col-sm-6 col-lg-3"><div class="card stat-card"><div class="stat-label">Verified internships</div><div class="stat-value" data-k="verified_internships">-</div></div></div>
    <div class="col-sm-6 col-lg-3"><div class="card stat-card"><div class="stat-label">Unread notifications</div><div class="stat-value" id="unreadNotif">-</div></div></div>
</div>

<div class="card mt-3">
    <div class="card-header">Assessment status</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-sm-6 col-lg-3"><div class="metric-row"><span class="metric-label">Pending</span><span class="metric-value" id="assPending">0</span></div></div>
            <div class="col-sm-6 col-lg-3"><div class="metric-row"><span class="metric-label">In Progress</span><span class="metric-value" id="assProgress">0</span></div></div>
            <div class="col-sm-6 col-lg-3"><div class="metric-row"><span class="metric-label">Submitted</span><span class="metric-value" id="assSubmitted">0</span></div></div>
            <div class="col-sm-6 col-lg-3"><div class="metric-row"><span class="metric-label">Results</span><span class="metric-value" id="assResults">0</span></div></div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">Notifications</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-sm-6"><div class="metric-row"><span class="metric-label">Total</span><span class="metric-value" id="notifTotal">0</span></div></div>
            <div class="col-sm-6"><div class="metric-row"><span class="metric-label">Unread</span><span class="metric-value" id="notifUnread">0</span></div></div>
        </div>
    </div>
</div>

<div class="card mt-3"><div class="card-header">Recent applications</div><div class="card-body p-0">
<table class="table mb-0"><thead><tr><th>Job</th><th>Status</th><th>Applied</th><th></th></tr></thead><tbody id="recent"><tr><td colspan="4" class="empty-state">Loading...</td></tr></tbody></table>
</div></div>
@push('scripts')
<script>
(async () => {
    try {
        const data = await THR.api('/candidate/dashboard');
        document.querySelectorAll('[data-k]').forEach(el => { el.textContent = data[el.dataset.k] ?? '-'; });
        document.getElementById('pendingAss').textContent = data.assessment_status?.pending ?? 0;
        document.getElementById('unreadNotif').textContent = data.notifications?.unread ?? 0;
        document.getElementById('assPending').textContent = data.assessment_status?.pending ?? 0;
        document.getElementById('assProgress').textContent = data.assessment_status?.in_progress ?? 0;
        document.getElementById('assSubmitted').textContent = data.assessment_status?.submitted ?? 0;
        document.getElementById('assResults').textContent = data.assessment_status?.results ?? 0;
        document.getElementById('notifTotal').textContent = data.notifications?.total ?? 0;
        document.getElementById('notifUnread').textContent = data.notifications?.unread ?? 0;

        const items = data.recent_applications || [];
        const tb = document.getElementById('recent');
        tb.innerHTML = items.length
            ? items.map(a => `<tr><td>${THR.escapeHtml(a.job?.title || '-')}</td><td>${THR.statusPill(a.status)}</td><td>${THR.fmtDate(a.created_at)}</td><td><a class="btn btn-sm btn-outline-primary" href="/candidate/applications/${a.id}">Open</a></td></tr>`).join('')
            : '<tr><td colspan="4" class="empty-state">No applications yet - <a href="/candidate/jobs">apply now</a></td></tr>';
    } catch (e) {
        THR.toast(e.message, 'danger');
    }
})();
</script>
@endpush
@endsection
