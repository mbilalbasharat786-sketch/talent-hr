@extends('layouts.app', ['role' => 'company'])
@section('title', 'Verification Documents')
@section('content')
<div class="page-header"><div><h1>Verification Documents</h1><p>Upload company verification proofs for admin review</p></div></div>
<div class="row g-3">
    <div class="col-lg-5"><div class="card"><div class="card-header">Upload document</div><div class="card-body">
        <form id="docForm" enctype="multipart/form-data">
            <div class="mb-3"><label class="form-label">Type</label>
                <select class="form-select" name="type" required>
                    <option value="">-- Select --</option>
                    <option value="secp">SECP Registration Certificate</option>
                    <option value="ntn">NTN / Tax Certificate</option>
                    <option value="address">Address Proof / Utility Bill</option>
                </select></div>
            <div class="mb-3"><label class="form-label">File (PDF/PNG/JPG/WEBP)</label><input type="file" name="file" class="form-control" required accept=".pdf,.png,.jpg,.jpeg,.webp"></div>
            <button class="btn btn-primary">Upload</button>
        </form>
    </div></div></div>
    <div class="col-lg-7"><div class="card"><div class="card-header">Submitted documents</div><div class="card-body p-0">
        <table class="table mb-0"><thead><tr><th>Type</th><th>Status</th><th>Submitted</th><th></th></tr></thead><tbody id="docList"><tr><td colspan="4" class="empty-state">Loading...</td></tr></tbody></table>
    </div></div></div>
</div>
@push('scripts')
<script>
async function loadDocs() {
    try {
        const r = await THR.api('/company/profile');
        const docs = (r.company && r.company.verification_documents) || r.verification_documents || [];
        const tb = document.getElementById('docList');
        tb.innerHTML = docs.length ? docs.map(d => `<tr><td>${THR.escapeHtml(d.type)}</td><td>${THR.statusPill(d.status)}</td><td>${THR.fmtDate(d.created_at)}</td><td>${d.secure_url ? `<button type="button" class="btn btn-sm btn-outline-primary" onclick="THR.openFile('${THR.escapeHtml(d.secure_url)}')"><i class="bi bi-eye"></i> View</button>` : ''}</td></tr>`).join('') : '<tr><td colspan="4" class="empty-state">No documents yet</td></tr>';
    } catch (e) { THR.toast(e.message, 'danger'); }
}
loadDocs();
document.getElementById('docForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    try { await THR.api('/company/documents', { method: 'POST', body: fd }); THR.toast('Uploaded','success'); e.target.reset(); loadDocs(); }
    catch (err) { THR.toast(err.message, 'danger'); }
});
</script>
@endpush
@endsection
