@extends('layouts.app', ['role' => 'admin'])
@section('title', 'Company Detail')
@section('content')
<div class="page-header"><div><h1 id="companyName">Company</h1><p id="companyMeta" class="text-muted small"></p></div>
<div><a href="/admin/companies" class="btn btn-light"><i class="bi bi-arrow-left"></i> Back</a></div></div>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card"><div class="card-header">Profile</div><div class="card-body" id="profileBody">Loading...</div></div>
        <div class="card mt-3"><div class="card-header">Verification Documents</div><div class="card-body p-0"><table class="table mb-0"><thead><tr><th>Type</th><th>Status</th><th></th></tr></thead><tbody id="docs"></tbody></table></div></div>
        <div class="card mt-3"><div class="card-header">Supervisor Info</div><div class="card-body p-0"><table class="table mb-0"><thead><tr><th>Name</th><th>Email</th><th>CNIC</th><th>Status</th><th>Selfie</th></tr></thead><tbody id="supervisors"></tbody></table></div></div>
    </div>
    <div class="col-lg-4">
        <div class="card"><div class="card-header">Actions</div><div class="card-body d-grid gap-2">
            <label class="form-label small mb-1" for="trustLevel">Trust level on approval</label>
            <select class="form-select mb-2" id="trustLevel">
                <option value="basic">Basic</option>
                <option value="standard" selected>Standard</option>
                <option value="gold">Gold</option>
                <option value="platinum">Platinum</option>
            </select>
            <button class="btn btn-success" id="approveBtn"><i class="bi bi-check-circle"></i> Approve</button>
            <button class="btn btn-danger" id="rejectBtn"><i class="bi bi-x-circle"></i> Reject</button>
            <p class="text-muted small mb-0 d-none" id="noActionMsg">Decision already recorded.</p>
        </div></div>
    </div>
</div>
@push('scripts')
<script>
const id = {{ $id }};

function formatOfficeLocations(locations) {
    if (!locations) return '-';
    if (Array.isArray(locations)) return locations.map(loc => `${loc.city || ''}${loc.address ? ', ' + loc.address : ''}`).join('; ');
    return THR.escapeHtml(locations);
}

function formatWorkingHours(hours) {
    if (!hours) return '-';
    if (Array.isArray(hours)) return hours.map(wh => `${wh.day}: ${wh.start} - ${wh.end}`).join('; ');
    return THR.escapeHtml(hours);
}

async function load() {
    try {
        const c = await THR.api('/admin/companies/' + id);
        document.getElementById('companyName').textContent = c.name;
        document.getElementById('companyMeta').innerHTML = `${THR.escapeHtml(c.email)} · ${THR.statusPill(c.status)} · Trust: ${THR.escapeHtml(c.trust_level || 'basic')}`;
        document.getElementById('profileBody').innerHTML = `
            <dl class="row mb-0">
                <dt class="col-sm-3">Phone</dt><dd class="col-sm-9">${THR.escapeHtml(c.phone || '-')}</dd>
                <dt class="col-sm-3">Industry</dt><dd class="col-sm-9">${THR.escapeHtml(c.industry || '-')}</dd>
                <dt class="col-sm-3">Website</dt><dd class="col-sm-9">${c.website ? `<a href="${THR.escapeHtml(c.website)}" target="_blank">${THR.escapeHtml(c.website)}</a>` : '-'}</dd>
                <dt class="col-sm-3">About</dt><dd class="col-sm-9">${c.about ? THR.escapeHtml(c.about) : '-'}</dd>
                <dt class="col-sm-3">Company Size</dt><dd class="col-sm-9">${THR.escapeHtml(c.company_size || '-')}</dd>
                <dt class="col-sm-3">Office Locations</dt><dd class="col-sm-9">${formatOfficeLocations(c.office_locations)}</dd>
                <dt class="col-sm-3">Working Hours</dt><dd class="col-sm-9">${formatWorkingHours(c.working_hours)}</dd>
                <dt class="col-sm-3">Rejection</dt><dd class="col-sm-9">${THR.escapeHtml(c.rejection_reason || '-')}</dd>
            </dl>`;

        const docs = c.verification_documents || [];
        const labels = { secp: 'SECP Certificate', ntn: 'NTN Certificate', address: 'Address Proof' };
        document.getElementById('docs').innerHTML = docs.length
            ? docs.map(d => `<tr><td>${THR.escapeHtml(labels[d.type] || d.type)}</td><td>${THR.statusPill(d.status)}</td><td>${d.secure_url ? `<button type="button" class="btn btn-sm btn-outline-primary" onclick="THR.openFile('${THR.escapeHtml(d.secure_url)}')"><i class="bi bi-eye"></i> Preview file</button>` : '-'}</td></tr>`).join('')
            : '<tr><td colspan="3" class="empty-state">No documents</td></tr>';

        const supervisors = c.supervisors || [];
        document.getElementById('supervisors').innerHTML = supervisors.length
            ? supervisors.map(s => `<tr><td>${THR.escapeHtml(s.name)}</td><td>${THR.escapeHtml(s.email)}</td><td>${THR.escapeHtml(s.cnic || '-')}</td><td>${THR.statusPill(s.status)}</td><td>${s.selfie_secure_url ? `<button type="button" class="btn btn-sm btn-outline-primary" onclick="THR.openFile('${THR.escapeHtml(s.selfie_secure_url)}')">View</button>` : '-'}</td></tr>`).join('')
            : '<tr><td colspan="5" class="empty-state">No supervisors</td></tr>';

        const pending = c.status === 'pending';
        document.getElementById('approveBtn').classList.toggle('d-none', !pending);
        document.getElementById('rejectBtn').classList.toggle('d-none', !pending);
        document.getElementById('trustLevel').classList.toggle('d-none', !pending);
        document.querySelector('label[for="trustLevel"]').classList.toggle('d-none', !pending);
        document.getElementById('noActionMsg').classList.toggle('d-none', pending);
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
