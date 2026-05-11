@extends('layouts.app', ['role' => 'admin'])
@section('title', 'Internship Detail')
@section('content')
<div class="page-header"><div><h1>Internship</h1></div><a href="/admin/internships" class="btn btn-light"><i class="bi bi-arrow-left"></i> Back</a></div>
<div class="row g-3">
    <div class="col-lg-8"><div class="card"><div class="card-body" id="body">Loading…</div></div></div>
    <div class="col-lg-4"><div class="card"><div class="card-header">Actions</div><div class="card-body d-grid gap-2">
        <button class="btn btn-success" id="verifyBtn">Verify (full)</button>
        <button class="btn btn-warning" id="partialBtn">Mark Partial</button>
        <button class="btn btn-danger" id="rejectBtn">Reject</button>
        <p class="text-muted small mb-0 d-none" id="noActionMsg">Decision already recorded.</p>
    </div></div></div>
</div>
@push('scripts')
<script>
const id = {{ $id }};
async function load() {
    try {
        const r = await THR.api('/admin/internships/' + id);
        const i = r.internship || r;
        document.getElementById('body').innerHTML = `
            <dl class="row mb-0">
                <dt class="col-sm-3">Candidate</dt><dd class="col-sm-9">${THR.escapeHtml(i.candidate?.name||'—')} (${THR.escapeHtml(i.candidate?.email||'—')})</dd>
                <dt class="col-sm-3">Company</dt><dd class="col-sm-9">${THR.escapeHtml(i.company_name)}</dd>
                <dt class="col-sm-3">Duration</dt><dd class="col-sm-9">${THR.escapeHtml(i.duration||'—')}</dd>
                <dt class="col-sm-3">Supervisor email</dt><dd class="col-sm-9">${THR.escapeHtml(i.supervisor_email||'—')}</dd>
                <dt class="col-sm-3">Status</dt><dd class="col-sm-9">${THR.statusPill(i.status)}</dd>
                <dt class="col-sm-3">Certificate</dt><dd class="col-sm-9">${i.certificate_secure_url ? `<button type="button" class="btn btn-sm btn-outline-primary" onclick="THR.openFile('${THR.escapeHtml(i.certificate_secure_url)}')"><i class="bi bi-eye"></i> View certificate</button>` : '—'}</dd>
                <dt class="col-sm-3">Verification email</dt><dd class="col-sm-9"><pre class="mb-0 small">${THR.escapeHtml(i.verification_email_response||'—')}</pre></dd>
                <dt class="col-sm-3">Rejection</dt><dd class="col-sm-9">${THR.escapeHtml(i.rejection_reason||'—')}</dd>
            </dl>`;
        const pending = i.status === 'pending';
        document.getElementById('verifyBtn').classList.toggle('d-none', !pending);
        document.getElementById('partialBtn').classList.toggle('d-none', !pending);
        document.getElementById('rejectBtn').classList.toggle('d-none', !pending);
        document.getElementById('noActionMsg').classList.toggle('d-none', pending);
    } catch (e) { THR.toast(e.message, 'danger'); }
}
load();
function action(path, ok) {
    return async () => {
        let body = null;
        if (path.endsWith('reject')) {
            const reason = prompt('Reason:'); if (!reason) return; body = { reason };
        }
        try { await THR.api(path, { method: 'POST', body }); THR.toast(ok, 'success'); load(); }
        catch (e) { THR.toast(e.message, 'danger'); }
    };
}
document.getElementById('verifyBtn').addEventListener('click', action(`/admin/internships/${id}/verify`, 'Verified'));
document.getElementById('partialBtn').addEventListener('click', action(`/admin/internships/${id}/partial`, 'Marked partial'));
document.getElementById('rejectBtn').addEventListener('click', action(`/admin/internships/${id}/reject`, 'Rejected'));
</script>
@endpush
@endsection
