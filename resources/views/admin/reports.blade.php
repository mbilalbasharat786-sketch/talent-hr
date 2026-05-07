@extends('layouts.app', ['role' => 'admin'])
@section('title', 'Reports')
@section('content')
<div class="page-header">
    <div>
        <h1>Reports</h1>
        <p>Platform health, verification quality, hiring activity, and risk signals</p>
    </div>
    <button class="btn btn-primary" id="refreshReports"><i class="bi bi-arrow-clockwise me-1"></i> Refresh</button>
</div>

<div class="row g-3" id="summaryCards">
    <div class="col-sm-6 col-xl-3"><div class="card stat-card"><div class="stat-label">Companies</div><div class="stat-value">--</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card stat-card"><div class="stat-label">Users</div><div class="stat-value">--</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card stat-card"><div class="stat-label">Applications</div><div class="stat-value">--</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card stat-card"><div class="stat-label">Fraud Alerts</div><div class="stat-value">--</div></div></div>
</div>

<div class="row g-3 mt-1">
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-building-check me-2 text-success"></i>Company Verification</span>
                <span class="badge text-bg-light" id="companyApprovalRate">--</span>
            </div>
            <div class="card-body" id="companyReport">Loading...</div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-people me-2 text-primary"></i>User Composition</div>
            <div class="card-body">
                <div class="row g-3 align-items-center">
                    <div class="col-md-5">
                        <div class="report-donut" id="userDonut" style="--p:0"><span>0%</span></div>
                    </div>
                    <div class="col-md-7" id="userReport">Loading...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-mortarboard me-2 text-warning"></i>Internships</div>
            <div class="card-body" id="internshipReport">Loading...</div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-funnel me-2 text-info"></i>Hiring Activity</div>
            <div class="card-body" id="hrReport">Loading...</div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-shield-exclamation me-2 text-danger"></i>Fraud Monitor</div>
            <div class="card-body" id="fraudReport">Loading...</div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><i class="bi bi-award me-2 text-warning"></i>Trust Level Distribution</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Trust level</th><th>Total companies</th><th>Share</th></tr></thead>
                <tbody id="trustRows"><tr><td colspan="3" class="empty-state">Loading...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

@push('head')
<style>
    .report-donut {
        width: min(210px, 100%);
        aspect-ratio: 1;
        margin: auto;
        border-radius: 50%;
        display: grid;
        place-items: center;
        background: conic-gradient(var(--thr-primary) calc(var(--p) * 1%), rgba(15,23,42,.08) 0);
        position: relative;
        box-shadow: inset 0 0 0 1px rgba(15,23,42,.06);
    }
    .report-donut::after {
        content: "";
        position: absolute;
        inset: 17%;
        border-radius: 50%;
        background: rgba(255,255,255,.92);
        box-shadow: inset 0 0 0 1px rgba(15,23,42,.06);
    }
    .report-donut span {
        position: relative;
        z-index: 1;
        color: var(--thr-ink);
        font-size: 1.55rem;
        font-weight: 900;
        letter-spacing: -.04em;
    }
    .report-chip {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        padding: .42rem .68rem;
        border-radius: 999px;
        background: rgba(15,118,110,.08);
        color: #0f766e;
        font-weight: 900;
        font-size: .78rem;
    }
</style>
@endpush

@push('scripts')
<script>
function pct(value, total) {
    if (!total) return 0;
    return Math.round((value / total) * 100);
}
function row(label, value, total, icon = 'bi-dot') {
    const percent = pct(value, total);
    return `<div class="metric-row">
        <div class="flex-grow-1">
            <div class="d-flex justify-content-between gap-3 mb-2">
                <span class="metric-label"><i class="bi ${icon} me-1"></i>${THR.escapeHtml(label)}</span>
                <span class="metric-value">${value ?? 0}</span>
            </div>
            <div class="mini-progress"><span style="width:${percent}%"></span></div>
        </div>
    </div>`;
}
function stat(label, value, icon, tone) {
    return `<div class="col-sm-6 col-xl-3"><div class="card stat-card">
        <div class="d-flex justify-content-between align-items-start">
            <div><div class="stat-label">${label}</div><div class="stat-value">${value ?? 0}</div></div>
            <span class="stat-icon bg-${tone}-subtle text-${tone}"><i class="bi ${icon}"></i></span>
        </div>
    </div></div>`;
}
async function loadReports() {
    try {
        const data = await window.THR.api('/admin/reports');
        const c = data.companies || {};
        const u = data.users || {};
        const i = data.internships || {};
        const h = data.hr_activity || {};
        const f = data.fraud || {};

        document.getElementById('summaryCards').innerHTML = [
            stat('Companies', c.total, 'bi-building', 'primary'),
            stat('Users', u.total, 'bi-people', 'success'),
            stat('Applications', h.total_applications, 'bi-kanban', 'warning'),
            stat('Fraud Alerts', (f.open || 0) + (f.flagged || 0) + (f.fraud || 0), 'bi-shield-exclamation', 'danger'),
        ].join('');

        const companyTotal = c.total || 0;
        const approvalRate = pct(c.approved || 0, companyTotal);
        document.getElementById('companyApprovalRate').textContent = `${approvalRate}% approved`;
        document.getElementById('companyReport').innerHTML = [
            row('Approved', c.approved || 0, companyTotal, 'bi-patch-check'),
            row('Pending', c.pending || 0, companyTotal, 'bi-hourglass-split'),
            row('Rejected', c.rejected || 0, companyTotal, 'bi-x-octagon'),
            `<div class="mt-3"><span class="report-chip"><i class="bi bi-activity"></i>${companyTotal} total records</span></div>`
        ].join('');

        const activeUsers = Math.max(0, (u.total || 0) - (u.inactive_users || 0));
        const activePercent = pct(activeUsers, u.total || 0);
        document.getElementById('userDonut').style.setProperty('--p', activePercent);
        document.querySelector('#userDonut span').textContent = `${activePercent}%`;
        document.getElementById('userReport').innerHTML = [
            row('Candidates', u.candidates || 0, u.total || 0, 'bi-person-check'),
            row('HR users', u.hr_users || 0, u.total || 0, 'bi-person-badge'),
            row('Company owners', u.company_users || 0, u.total || 0, 'bi-building'),
            row('Inactive users', u.inactive_users || 0, u.total || 0, 'bi-person-x'),
        ].join('');

        document.getElementById('internshipReport').innerHTML = [
            row('Verified', i.verified || 0, i.total || 0, 'bi-patch-check'),
            row('Pending', i.pending || 0, i.total || 0, 'bi-clock'),
            row('Partial', i.partial || 0, i.total || 0, 'bi-slash-circle'),
            row('Rejected', i.rejected || 0, i.total || 0, 'bi-x-circle'),
        ].join('') || '<p class="text-muted mb-0">No internship data yet</p>';

        document.getElementById('hrReport').innerHTML = [
            row('Total jobs', h.total_jobs || 0, Math.max(h.total_jobs || 0, 1), 'bi-briefcase'),
            row('Applications', h.total_applications || 0, Math.max(h.total_applications || 0, 1), 'bi-file-text'),
            row('Shortlisted', h.shortlisted_applications || 0, h.total_applications || 0, 'bi-star'),
            row('Rejected', h.rejected_applications || 0, h.total_applications || 0, 'bi-x-circle'),
            row('Hired', h.hired_applications || 0, h.total_applications || 0, 'bi-award'),
        ].join('');

        document.getElementById('fraudReport').innerHTML = [
            row('Open', f.open || 0, f.total || 0, 'bi-exclamation-diamond'),
            row('Flagged', f.flagged || 0, f.total || 0, 'bi-flag'),
            row('Resolved', f.resolved || 0, f.total || 0, 'bi-check2-circle'),
            row('Confirmed fraud', f.fraud || 0, f.total || 0, 'bi-shield-fill-x'),
        ].join('') || '<p class="text-muted mb-0">No fraud data yet</p>';

        const trust = c.by_trust_level || [];
        document.getElementById('trustRows').innerHTML = trust.length ? trust.map(t => {
            const total = t.total || 0;
            const share = pct(total, companyTotal);
            return `<tr>
                <td><span class="status-pill bg-warning-subtle text-warning">${THR.escapeHtml(t.trust_level || 'basic')}</span></td>
                <td class="fw-bold">${total}</td>
                <td><div class="mini-progress"><span style="width:${share}%"></span></div><span class="text-muted small">${share}%</span></td>
            </tr>`;
        }).join('') : '<tr><td colspan="3" class="empty-state">No trust data yet</td></tr>';
    } catch (e) {
        THR.toast(e.message, 'danger');
    }
}
document.getElementById('refreshReports').addEventListener('click', loadReports);
loadReports();
</script>
@endpush
@endsection
