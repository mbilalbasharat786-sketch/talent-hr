@extends('layouts.app', ['role' => 'hr'])
@section('title', 'Edit Job')
@section('content')
<div class="page-header"><div><h1>Edit Job</h1></div><a href="/hr/jobs/{{ $id }}" class="btn btn-light">Back</a></div>
<div class="card"><div class="card-body">
@include('hr.jobs.partials.form', ['mode' => 'edit'])
</div></div>
@push('scripts')
<script>
const id = {{ $id }};
(async () => {
    try {
        const [job, ass] = await Promise.all([THR.api('/hr/jobs/' + id), THR.api('/hr/assessments')]);
        const sel = document.querySelector('[name=assessment_id]');
        const items = ass.data || ass;
        sel.innerHTML = '<option value="">Select assessment</option>' + items.map(a => `<option value="${a.id}">${THR.escapeHtml(a.title)}</option>`).join('');
        const j = job.job || job;
        const f = document.getElementById('jobForm');
        THR.fillForm(f, j);
        if (Array.isArray(j.skills)) f.skills.value = j.skills.join(', ');
    } catch (e) { THR.toast(e.message, 'danger'); }
})();
document.getElementById('jobForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = THR.formData(e.target);
    if (data.skills) data.skills = data.skills.split(',').map(s => s.trim()).filter(Boolean);
    if (data.candidates_required) data.candidates_required = parseInt(data.candidates_required, 10);
    if (data.assessment_id) data.assessment_id = parseInt(data.assessment_id, 10);
    try { await THR.api(`/hr/jobs/${id}`, { method: 'PUT', body: data }); THR.toast('Saved','success'); }
    catch (err) { THR.toast(err.message, 'danger'); }
});
</script>
@endpush
@endsection
