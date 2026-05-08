@extends('layouts.app', ['role' => 'company'])
@section('title', 'HR Users')

@section('content')
<div class="page-header">
    <div>
        <h1>HR Users</h1>
        <p>Create and manage HR accounts under your company</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newHrModal">
        <i class="bi bi-plus-circle"></i> New HR
    </button>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>HR Type</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="rows">
                <tr><td colspan="5" class="empty-state">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>
@endsection

{{-- Modals ko section ke bahar rakha hai taake backdrop ka masla na ho --}}
<div class="modal fade" id="newHrModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="newHrForm">
                <input type="hidden" name="role" value="hr">
                <div class="modal-header">
                    <h5 class="modal-title">New HR User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" required></div>
                    <div class="mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div>
                    <div class="mb-3"><label class="form-label">Password</label><input class="form-control" type="password" name="password" required minlength="6"></div>
                    <div class="mb-3">
                        <label class="form-label">HR Type</label>
                        <select class="form-select" name="hr_type">
                            <option value="hr_manager">HR Manager</option>
                            <option value="recruiter">Recruiter</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer"><button class="btn btn-primary">Create</button></div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editHrModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editHrForm">
                <input type="hidden" name="id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit HR</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" required></div>
                    <div class="mb-3">
                        <label class="form-label">HR Type</label>
                        <select class="form-select" name="hr_type">
                            <option value="hr_manager">HR Manager</option>
                            <option value="recruiter">Recruiter</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer"><button class="btn btn-primary">Save</button></div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    let editModal;
    let newModal;

    async function load() {
        try {
            const data = await THR.api('/company/hr');
            const items = data.data || data;
            const tb = document.getElementById('rows');
            if (!items.length) return tb.innerHTML = '<tr><td colspan="5" class="empty-state">No HR users yet</td></tr>';
            tb.innerHTML = items.map(h => `<tr>
                <td>${THR.escapeHtml(h.name)}</td>
                <td>${THR.escapeHtml(h.email)}</td>
                <td>${THR.escapeHtml(h.hr_type || '--')}</td>
                <td>${THR.statusPill(h.status)}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary edit-btn" data-row='${THR.escapeHtml(JSON.stringify(h))}'>Edit</button>
                    ${h.status === 'active' ? `<button class="btn btn-sm btn-outline-danger deact-btn" data-id="${h.id}">Deactivate</button>` : ''}
                </td></tr>`).join('');
            attachEventListeners();
        } catch (e) { 
            console.error('Load error:', e);
            THR.toast(e.message, 'danger'); 
        }
    }

    function attachEventListeners() {
        document.querySelectorAll('.edit-btn').forEach(b => b.addEventListener('click', () => {
            const h = JSON.parse(b.dataset.row);
            const f = document.getElementById('editHrForm');
            f.id.value = h.id; 
            f.name.value = h.name; 
            f.hr_type.value = h.hr_type || 'recruiter';
            if (editModal) editModal.show();
        }));
        
        document.querySelectorAll('.deact-btn').forEach(b => b.addEventListener('click', async () => {
            if (!confirm('Deactivate this HR user?')) return;
            try { 
                await THR.api(`/company/hr/${b.dataset.id}/deactivate`, { method: 'POST' }); 
                THR.toast('Deactivated','warning'); 
                load(); 
            } catch(e){
                THR.toast(e.message,'danger');
            }
        }));
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Init modals without double backdrop issues
        editModal = new bootstrap.Modal('#editHrModal');
        newModal = new bootstrap.Modal('#newHrModal');
        load();
    });

    document.getElementById('newHrForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        try { 
            await THR.api('/company/hr', { method: 'POST', body: THR.formData(e.target) }); 
            THR.toast('Created','success'); 
            newModal.hide();
            e.target.reset();
            load(); 
        } catch (err) {
            THR.toast(err.message, 'danger'); 
        }
    });

    document.getElementById('editHrForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = THR.formData(e.target); 
        const id = data.id; 
        delete data.id;
        try { 
            await THR.api(`/company/hr/${id}`, { method: 'PUT', body: data }); 
            THR.toast('Updated','success'); 
            editModal.hide();
            load(); 
        } catch (err) {
            THR.toast(err.message, 'danger'); 
        }
    });
</script>
@endpush
