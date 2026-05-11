@extends('layouts.app', ['role' => 'admin'])
@section('title', 'HR Detail')
@section('content')
<div class="page-header"><div><h1 id="hrName">HR User</h1><p id="hrMeta" class="text-muted small"></p></div><a href="/admin/hr-monitoring" class="btn btn-light"><i class="bi bi-arrow-left"></i> Back</a></div>

<div class="row g-3">
    <div class="col-md-3"><div class="card stat-card"><div class="stat-label">Jobs Created</div><div class="stat-value" id="jobsCreated">0</div></div></div>
    <div class="col-md-3"><div class="card stat-card"><div class="stat-label">Applications</div><div class="stat-value" id="totalApps">0</div></div></div>
    <div class="col-md-3"><div class="card stat-card"><div class="stat-label">Rejection Rate</div><div class="stat-value" id="rejectRate">0%</div></div></div>
    <div class="col-md-3"><div class="card stat-card"><div class="stat-label">Shortlist Rate</div><div class="stat-value" id="shortRate">0%</div></div></div>
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-6"><div class="card"><div class="card-header">Rejection reasons</div><div class="card-body p-0"><table class="table mb-0"><thead><tr><th>Reason</th><th>Total</th></tr></thead><tbody id="reasons"></tbody></table></div></div></div>
    <div class="col-lg-6"><div class="card"><div class="card-header">Hiring patterns</div><div class="card-body p-0"><table class="table mb-0"><thead><tr><th>Status</th><th>Total</th></tr></thead><tbody id="patterns"></tbody></table></div></div></div>
</div>
@push('scripts')
<script>
const id = {{ $id }};
(async () => {
    try {
        const r = await THR.api('/admin/hr-monitoring/' + id);
        const hr = r.hr || {};
        const a = r.analytics || {};
        document.getElementById('hrName').textContent = hr.name || 'HR User';
        document.getElementById('hrMeta').textContent = `${hr.email || ''} · ${hr.company?.name || hr.hr_jobs?.[0]?.company?.name || 'No company'}`;
        document.getElementById('jobsCreated').textContent = a.jobs_created ?? 0;
        document.getElementById('totalApps').textContent = a.total_applications ?? 0;
        document.getElementById('rejectRate').textContent = `${a.rejection_rate ?? 0}%`;
        document.getElementById('shortRate').textContent = `${a.shortlist_rate ?? 0}%`;

        const reasons = a.rejection_reasons || [];
        document.getElementById('reasons').innerHTML = reasons.length
            ? reasons.map(item => `<tr><td>${THR.escapeHtml(item.rejection_reason)}</td><td>${item.total}</td></tr>`).join('')
            : '<tr><td colspan="2" class="empty-state">No rejection reasons</td></tr>';

        const patterns = a.hiring_patterns || [];
        document.getElementById('patterns').innerHTML = patterns.length
            ? patterns.map(item => `<tr><td>${THR.statusPill(item.status)}</td><td>${item.total}</td></tr>`).join('')
            : '<tr><td colspan="2" class="empty-state">No hiring activity</td></tr>';
    } catch (e) { THR.toast(e.message, 'danger'); }
})();
</script>
@endpush
@endsection
