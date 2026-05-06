@extends('layouts.app', ['role' => 'hr'])
@section('title', 'Post Job')
@section('content')
<div class="page-header"><div><h1>Post a New Job</h1><p>Create a job and link an assessment</p></div></div>
<div class="card"><div class="card-body">
@include('hr.jobs.partials.form', ['mode' => 'create'])
</div></div>
@push('scripts')
<script>
(async () => {
    try {
        const data = await THR.api('/hr/assessments');
        const items = data.data || data;
        const sel = document.querySelector('[name=assessment_id]');
        sel.innerHTML = '<option value="">Select assessment</option>' + items.map(a => `<option value="${a.id}">${THR.escapeHtml(a.title)}</option>`).join('');
    } catch (e) { /* ignore */ }
})();
document.getElementById('jobForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = THR.formData(e.target);
    if (data.skills) data.skills = data.skills.split(',').map(s => s.trim()).filter(Boolean);
    if (data.candidates_required) data.candidates_required = parseInt(data.candidates_required, 10);
    if (data.assessment_id) data.assessment_id = parseInt(data.assessment_id, 10);
    try { const r = await THR.api('/hr/jobs', { method: 'POST', body: data }); THR.toast('Job posted','success'); setTimeout(()=>location.href='/hr/jobs/'+(r.job?.id||r.id||''),500); }
    catch (err) { THR.toast(err.message, 'danger'); }
});
</script>
@endpush
@endsection
