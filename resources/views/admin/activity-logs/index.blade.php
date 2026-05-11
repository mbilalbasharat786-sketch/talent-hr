@extends('layouts.app', ['role' => 'admin'])
@section('title', 'Activity Logs')
@section('content')
<div class="page-header">
    <div>
        <h1>Activity Logs</h1>
        <p>Audit trail across all modules</p>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form id="filterForm" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-bold">Search User</label>
                <input type="text" id="filterUser" class="form-control form-control-sm" placeholder="Email or System...">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">Module</label>
                <select id="filterModule" class="form-select form-select-sm">
                    <option value="">All Modules</option>
                    <option value="admin_auth">Admin Auth</option>
                    <option value="candidate_auth">Candidate Auth</option>
                    <option value="company_auth">Company Auth</option>
                    <option value="hr_auth">HR Auth</option>
                    <option value="hr_jobs">HR Jobs</option>
                    <option value="hr_assessments">Assessments</option>
                    <option value="company_verification">Company Verification</option>
                    <option value="supervisor_verification">Supervisor Verification</option>
                    <option value="internship_verification">Internship Verification</option>
                    <option value="fraud_detection">Fraud Detection</option>
                    <option value="user_management">User Management</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">Date</label>
                <input type="date" id="filterDate" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-primary w-100">
                    <i class="bi bi-filter"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0 table-responsive">
        <table class="table mb-0" style="table-layout: fixed; width: 100%; min-width: 1100px;">
            <thead>
                <tr>
                    <th style="width: 18%;">When</th>
                    <th style="width: 22%;">User</th>
                    <th style="width: 10%;">Action</th>
                    <th style="width: 12%;">Module</th>
                    <th style="width: 25%;">Description</th>
                    <th style="width: 13%;">IP Address</th>
                </tr>
            </thead>
            <tbody id="rows">
                <tr><td colspan="6" class="empty-state">Loading…</td></tr>
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
// Main function to fetch and display logs
async function fetchLogs(filters = {}) {
    const tb = document.getElementById('rows');
    tb.innerHTML = '<tr><td colspan="6" class="empty-state">Loading…</td></tr>';

    try {
        // Construct Query String for filters
        const params = new URLSearchParams(filters).toString();
        const data = await THR.api(`/admin/activity-logs?${params}`);
        const items = data.data || data;
        
        if (!items.length) {
            tb.innerHTML = '<tr><td colspan="6" class="empty-state">No activity found</td></tr>';
            return;
        }

        tb.innerHTML = items.map(a => `
            <tr>
                <td class="text-nowrap" style="font-size: 0.9rem;">
                    ${THR.fmtDate(a.created_at)}
                </td>
                <td class="text-truncate" title="${THR.escapeHtml(a.user?.email||'system')}">
                    <span class="text-secondary small">${THR.escapeHtml(a.user?.email||'system')}</span>
                </td>
                <td>
                    <span class="badge bg-light text-dark border-secondary-subtle" style="font-size: 0.75rem;">
                        ${THR.escapeHtml(a.action)}
                    </span>
                </td>
                <td class="text-muted small">${THR.escapeHtml(a.module)}</td>
                <td class="text-truncate" style="max-width: 100%;" title="${THR.escapeHtml(a.description||'')}">
                    ${THR.escapeHtml(a.description||'')}
                </td>
                <td class="text-nowrap font-monospace text-primary" style="font-size: 0.85rem;">
                    ${THR.escapeHtml(a.ip_address||'—')}
                </td>
            </tr>`).join('');
    } catch (e) { 
        THR.toast(e.message, 'danger'); 
    }
}

// Initial Load
fetchLogs();

// Filter Form Submission
document.getElementById('filterForm').addEventListener('submit', (e) => {
    e.preventDefault();
    const filters = {
        user: document.getElementById('filterUser').value,
        module: document.getElementById('filterModule').value,
        date: document.getElementById('filterDate').value
    };
    fetchLogs(filters);
});
</script>
@endpush
@endsection
