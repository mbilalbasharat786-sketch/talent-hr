@extends('layouts.app', ['role' => 'admin'])
@section('title', 'Supervisor Detail')
@section('content')
<div class="page-header"><div><h1>Supervisor</h1></div><a href="/admin/supervisors" class="btn btn-light"><i class="bi bi-arrow-left"></i> Back</a></div>
<div class="row g-3">
    <div class="col-lg-8"><div class="card"><div class="card-body" id="body">Loading…</div></div></div>
    <div class="col-lg-4"><div class="card"><div class="card-header">Actions</div><div class="card-body d-grid gap-2">
        <button class="btn btn-success" id="approveBtn">Approve</button>
        <button class="btn btn-danger" id="rejectBtn">Reject</button>
        <p class="text-muted small mb-0 d-none" id="noActionMsg">Decision already recorded.</p>
    </div></div></div>
</div>
@push('scripts')
<script>
const id = {{ $id }};
async function load() {
    try {
        const r = await THR.api('/admin/supervisors/' + id);
        const s = r.supervisor || r;
        document.getElementById('body').innerHTML = `
            <dl class="row mb-0">
                <dt class="col-sm-3">Name</dt><dd class="col-sm-9">${THR.escapeHtml(s.name)}</dd>
                <dt class="col-sm-3">Email</dt><dd class="col-sm-9">${THR.escapeHtml(s.email)}</dd>
                <dt class="col-sm-3">CNIC</dt><dd class="col-sm-9">${THR.escapeHtml(s.cnic||'—')}</dd>
                <dt class="col-sm-3">Company</dt><dd class="col-sm-9">${THR.escapeHtml(s.company?.name||'—')}</dd>
                <dt class="col-sm-3">Status</dt><dd class="col-sm-9">${THR.statusPill(s.status)}</dd>
                <dt class="col-sm-3">Selfie</dt><dd class="col-sm-9">${s.selfie_secure_url ? `<button type="button" class="btn btn-sm btn-outline-primary" onclick="THR.openFile('${THR.escapeHtml(s.selfie_secure_url)}')"><i class="bi bi-eye"></i> View selfie</button>` : '—'}</dd>
                <dt class="col-sm-3">Rejection</dt><dd class="col-sm-9">${THR.escapeHtml(s.rejection_reason||'—')}</dd>
            </dl>`;
        const pending = s.status === 'pending';
        document.getElementById('approveBtn').classList.toggle('d-none', !pending);
        document.getElementById('rejectBtn').classList.toggle('d-none', !pending);
        document.getElementById('noActionMsg').classList.toggle('d-none', pending);
    } catch (e) { THR.toast(e.message, 'danger'); }
}
load();
document.getElementById('approveBtn').addEventListener('click', async () => {
    try { await THR.api(`/admin/supervisor/${id}/approve`, { method: 'POST' }); THR.toast('Approved', 'success'); load(); }
    catch (e) { THR.toast(e.message, 'danger'); }
});
document.getElementById('rejectBtn').addEventListener('click', async () => {
    const reason = prompt('Rejection reason:'); if (!reason) return;
    try { await THR.api(`/admin/supervisor/${id}/reject`, { method: 'POST', body: { reason } }); THR.toast('Rejected', 'warning'); load(); }
    catch (e) { THR.toast(e.message, 'danger'); }
});
</script>
@endpush
@endsection
