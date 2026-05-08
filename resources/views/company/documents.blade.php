@extends('layouts.app', ['role' => 'company'])
@section('title', 'Verification Documents')
@section('content')
<div class="page-header"><div><h1>Verification Documents</h1><p>Upload company verification proofs for admin review</p></div></div>
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Upload Documents</div>
            <div class="card-body">
                <form id="docForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label fw-bold">SECP Registration Certificate</label>
                        <input type="file" name="secp" class="form-control" accept=".pdf,.png,.jpg,.jpeg,.webp">
                        <small class="text-muted">Upload SECP document if not submitted.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">NTN / Tax Certificate</label>
                        <input type="file" name="ntn" class="form-control" accept=".pdf,.png,.jpg,.jpeg,.webp">
                        <small class="text-muted">Upload your NTN certificate.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Address Proof / Utility Bill</label>
                        <input type="file" name="address" class="form-control" accept=".pdf,.png,.jpg,.jpeg,.webp">
                        <small class="text-muted">Electricity bill or rental agreement.</small>
                    </div>

                    <div class="mt-4">
                        <button class="btn btn-primary w-100" type="submit" id="uploadBtn">Upload Selected Documents</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Submitted documents</div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="docList">
                        <tr><td colspan="4" class="empty-state">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Helper function to map type codes to readable names
const docTypeMap = {
    'secp': 'SECP Certificate',
    'ntn': 'NTN / Tax Certificate',
    'address': 'Address Proof'
};

async function loadDocs() {
    try {
        const r = await THR.api('/company/profile');
        const docs = (r.company && r.company.verification_documents) || r.verification_documents || [];
        const tb = document.getElementById('docList');
        
        tb.innerHTML = docs.length ? docs.map(d => {
            // Hum check karenge ke URL file_path mein hai ya secure_url mein
            const fileUrl = d.file_path || d.secure_url; 
            
            return `
            <tr>
                <td><strong>${docTypeMap[d.type] || d.type.toUpperCase()}</strong></td>
                <td>${THR.statusPill(d.status)}</td>
                <td>${THR.fmtDate(d.created_at)}</td>
                <td>
                    ${fileUrl ? `
                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                onclick="window.open('${fileUrl}', '_blank')">
                            <i class="bi bi-eye"></i> View
                        </button>` : 'N/A'}
                </td>
            </tr>`;
        }).join('') : '<tr><td colspan="4" class="empty-state">No documents yet</td></tr>';
} catch (e) { 
        THR.toast('Error loading documents', 'danger'); 
    }
}

loadDocs();

document.getElementById('docForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('uploadBtn');
    const fd = new FormData(e.target);

    // Check if at least one file is selected
    let hasFile = false;
    for (let value of fd.values()) {
        if (value.name) { hasFile = true; break; }
    }

    if (!hasFile) {
        THR.toast('Please select at least one document to upload', 'warning');
        return;
    }

    try {
        btn.disabled = true;
        btn.innerText = 'Uploading...';
        
        await THR.api('/company/documents', { 
            method: 'POST', 
            body: fd 
        });

        THR.toast('Documents uploaded successfully', 'success');
        e.target.reset(); // Form clear kar dein
        loadDocs(); // List refresh karein
    } catch (err) { 
        THR.toast(err.message || 'Upload failed', 'danger'); 
    } finally {
        btn.disabled = false;
        btn.innerText = 'Upload Selected Documents';
    }
});
</script>
@endpush
@endsection
