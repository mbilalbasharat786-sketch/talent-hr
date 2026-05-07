@extends('layouts.app', ['role' => 'admin'])
@section('title', 'Company Detail')
@section('content')
<div class="page-header"><div><h1 id="companyName">Company</h1><p id="companyMeta" class="text-muted small"></p></div>
<div><a href="/admin/companies" class="btn btn-light"><i class="bi bi-arrow-left"></i> Back</a></div></div>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card"><div class="card-header">Profile</div><div class="card-body" id="profileBody">Loading…</div></div>
        <div class="card mt-3"><div class="card-header">Verification Documents</div><div class="card-body p-0"><table class="table mb-0"><thead><tr><th>Type</th><th>Status</th><th></th></tr></thead><tbody id="docs"></tbody></table></div></div>
    </div>
    <div class="col-lg-4">
        <div class="card"><div class="card-header">Actions</div><div class="card-body d-grid gap-2">
            <label class="form-label small mb-1">Trust level on approval</label>
            <select class="form-select mb-2" id="trustLevel">
                <option value="basic">Basic</option>
                <option value="standard" selected>Standard</option>
                <option value="gold">Gold</option>
                <option value="platinum">Platinum</option>
            </select>
            <button class="btn btn-success" id="approveBtn"><i class="bi bi-check-circle"></i> Approve</button>
            <button class="btn btn-danger" id="rejectBtn"><i class="bi bi-x-circle"></i> Reject</button>
        </div></div>
    </div>
</div>
@push('scripts')
<script>
const id = {{ $id }};
async function load() {
    try {
        const res = await THR.api('/admin/companies/' + id);
        const c = res.company || res;
        document.getElementById('companyName').textContent = c.name;
        document.getElementById('companyMeta').innerHTML = `${THR.escapeHtml(c.email)} · ${THR.statusPill(c.status)} · Trust: ${THR.escapeHtml(c.trust_level||'basic')}`;
        // Format office locations
function formatOfficeLocations(locations) {
    if (!locations) return '—';
    if (Array.isArray(locations)) {
        return locations.map(loc => `${loc.city}${loc.address ? ', ' + loc.address : ''}`).join('; ');
    }
    return THR.escapeHtml(locations);
}

// Format working hours
function formatWorkingHours(hours) {
    if (!hours) return '—';
    if (Array.isArray(hours)) {
        return hours.map(wh => `${wh.day}: ${wh.start} - ${wh.end}`).join('; ');
    }
    return THR.escapeHtml(hours);
}

document.getElementById('profileBody').innerHTML = `
            <dl class="row mb-0">
                <dt class="col-sm-3">Phone</dt><dd class="col-sm-9">${THR.escapeHtml(c.phone||'—')}</dd>
                <dt class="col-sm-3">Industry</dt><dd class="col-sm-9">${THR.escapeHtml(c.industry||'—')}</dd>
                <dt class="col-sm-3">Website</dt><dd class="col-sm-9">${c.website ? `<a href="${THR.escapeHtml(c.website)}" target="_blank">${THR.escapeHtml(c.website)}</a>` : '—'}</dd>
                <dt class="col-sm-3">About</dt><dd class="col-sm-9">${c.about ? THR.escapeHtml(c.about) : '—'}</dd>
                <dt class="col-sm-3">Company Size</dt><dd class="col-sm-9">${THR.escapeHtml(c.company_size||'—')}</dd>
                <dt class="col-sm-3">Office Locations</dt><dd class="col-sm-9">${formatOfficeLocations(c.office_locations)}</dd>
                <dt class="col-sm-3">Working Hours</dt><dd class="col-sm-9">${formatWorkingHours(c.working_hours)}</dd>
                <dt class="col-sm-3">Rejection</dt><dd class="col-sm-9">${THR.escapeHtml(c.rejection_reason||'—')}</dd>
            </dl>`;
        const docs = c.verification_documents || res.verification_documents || [];
        document.getElementById('docs').innerHTML = docs.length ? docs.map(d => `<tr><td>${THR.escapeHtml(d.type)}</td><td>${THR.statusPill(d.status)}</td><td>${d.secure_url ? `<button type="button" class="btn btn-sm btn-outline-primary" onclick="THR.openFile('${THR.escapeHtml(d.secure_url)}')"><i class="bi bi-eye"></i> View file</button>` : '—'}</td></tr>`).join('') : '<tr><td colspan="3" class="empty-state">No documents</td></tr>';
    } catch (e) { THR.toast(e.message, 'danger'); }
}
load();
document.getElementById('approveBtn').addEventListener('click', async () => {
    const trust_level = document.getElementById('trustLevel').value;
    if (!confirm(`Approve this company with trust level: ${trust_level}?`)) return;
    try { await THR.api(`/admin/companies/${id}/approve`, { method: 'POST', body: { trust_level } }); THR.toast('Approved', 'success'); load(); }
    catch (e) { THR.toast(e.message, 'danger'); }
});
document.getElementById('rejectBtn').addEventListener('click', async () => {
    const reason = prompt('Rejection reason:');
    if (!reason) return;
    try { await THR.api(`/admin/companies/${id}/reject`, { method: 'POST', body: { reason } }); THR.toast('Rejected', 'warning'); load(); }
    catch (e) { THR.toast(e.message, 'danger'); }
});
</script>
@endpush
@endsection
