@extends('layouts.app', ['role' => 'company'])
@section('title', 'Supervisors')
@section('content')
<div class="page-header"><div><h1>Supervisors</h1><p>Add internship verification supervisors</p></div></div>
<div class="card"><div class="card-body">
<form id="supForm" enctype="multipart/form-data">
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" required></div>
        <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div>
        <div class="col-md-6"><label class="form-label">CNIC</label><input class="form-control" name="cnic" required></div>
        <div class="col-md-6"><label class="form-label">Selfie (PNG/JPG/WEBP)</label><input class="form-control" type="file" name="selfie" accept=".png,.jpg,.jpeg,.webp" required></div>
    </div>
    <button class="btn btn-primary mt-3">Submit for verification</button>
</form>
</div></div>
@push('scripts')
<script>
document.getElementById('supForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    try { await THR.api('/company/supervisor', { method: 'POST', body: fd }); THR.toast('Submitted','success'); e.target.reset(); }
    catch (err) { THR.toast(err.message, 'danger'); }
});
</script>
@endpush
@endsection
