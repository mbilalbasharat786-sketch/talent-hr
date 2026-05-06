@extends('layouts.app', ['role' => 'hr'])
@section('title', 'Create Assessment')
@section('content')
<div class="page-header"><div><h1>New Assessment</h1></div><a href="/hr/assessments" class="btn btn-light">Back</a></div>
<div class="card"><div class="card-body">
<form id="assForm">
    <div class="row g-3">
        <div class="col-md-8"><label class="form-label">Title</label><input class="form-control" name="title" required maxlength="255"></div>
        <div class="col-md-4"><label class="form-label">Time limit (minutes)</label><input type="number" min="1" name="time_limit" class="form-control" value="30" required></div>
        <div class="col-md-3"><label class="form-label">Cooldown days</label><input type="number" min="1" name="cooldown_days" class="form-control" value="7"></div>
        <div class="col-md-3 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="one_attempt_only" id="ona" checked><label class="form-check-label" for="ona">One attempt only</label></div></div>
        <div class="col-md-3 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="auto_submit" id="as" checked><label class="form-check-label" for="as">Auto-submit on time-up</label></div></div>
        <div class="col-md-3 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="randomize_questions" id="rq"><label class="form-check-label" for="rq">Randomize questions</label></div></div>
        <div class="col-md-4"><label class="form-label">Status</label><select class="form-select" name="status"><option value="draft">Draft</option><option value="active">Active</option></select></div>
    </div>
    <button class="btn btn-primary mt-3">Create assessment</button>
</form>
</div></div>
@push('scripts')
<script>
document.getElementById('assForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const data = {};
    fd.forEach((v,k) => { data[k] = v; });
    data.one_attempt_only = !!e.target.one_attempt_only.checked;
    data.auto_submit = !!e.target.auto_submit.checked;
    data.randomize_questions = !!e.target.randomize_questions.checked;
    data.time_limit = parseInt(data.time_limit, 10);
    data.cooldown_days = parseInt(data.cooldown_days || 7, 10);
    try { const r = await THR.api('/hr/assessments', { method: 'POST', body: data }); THR.toast('Created','success'); setTimeout(()=>location.href='/hr/assessments/'+(r.assessment?.id||r.id||''),500); }
    catch (err) { THR.toast(err.message, 'danger'); }
});
</script>
@endpush
@endsection
