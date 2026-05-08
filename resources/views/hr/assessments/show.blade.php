@extends('layouts.app', ['role' => 'hr'])
@section('title', 'Assessment Detail')

@section('content')
<div class="page-header">
    <div><h1 id="title">Assessment</h1><p id="meta" class="text-muted small"></p></div>
    <a href="/hr/assessments" class="btn btn-light">Back</a>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span>Questions</span>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#qModal">
                    <i class="bi bi-plus"></i> Add question
                </button>
            </div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead><tr><th>#</th><th>Type</th><th>Question</th><th>Marks</th></tr></thead>
                    <tbody id="qList"></tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Settings</div>
            <div class="card-body">
                <form id="settingsForm">
                    <div class="mb-3"><label class="form-label">Title</label><input class="form-control" name="title" required></div>
                    <div class="mb-3"><label class="form-label">Time limit (min)</label><input type="number" min="1" class="form-control" name="time_limit" required></div>
                    <div class="mb-3"><label class="form-label">Cooldown (days)</label><input type="number" min="1" class="form-control" name="cooldown_days"></div>
                    <div class="form-check"><input type="checkbox" class="form-check-input" name="one_attempt_only" id="ona2"><label class="form-check-label" for="ona2">One attempt only</label></div>
                    <div class="form-check"><input type="checkbox" class="form-check-input" name="auto_submit" id="as2"><label class="form-check-label" for="as2">Auto-submit</label></div>
                    <div class="form-check"><input type="checkbox" class="form-check-input" name="randomize_questions" id="rq2"><label class="form-check-label" for="rq2">Randomize</label></div>
                    <div class="mb-3 mt-3"><label class="form-label">Status</label><select class="form-select" name="status"><option value="draft">Draft</option><option value="active">Active</option></select></div>
                    <button class="btn btn-primary">Save</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

{{-- MODAL SECTION SE BAHAR HAI --}}
<div class="modal fade" id="qModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="qForm">
                <div class="modal-header">
                    <h5 class="modal-title">Add Question</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="type" id="qType" required>
                            <option value="mcq">Multiple choice</option>
                            <option value="coding">Coding</option>
                            <option value="case">Case study</option>
                            <option value="file">File upload task</option>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Question</label><textarea class="form-control" name="question_text" rows="3" required></textarea></div>
                    <div class="mb-3" id="optionsBlock"><label class="form-label">Options (one per line)</label><textarea class="form-control" name="options" rows="4" placeholder="Option A&#10;Option B"></textarea></div>
                    <div class="mb-3"><label class="form-label">Expected answer</label><input class="form-control" name="expected_answer"></div>
                    <div class="mb-3"><label class="form-label">Marks</label><input type="number" min="1" class="form-control" name="marks" value="1" required></div>
                </div>
                <div class="modal-footer"><button class="btn btn-primary">Add</button></div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
const id = {{ $id }};
let qModal;

async function load() {
    try {
        const r = await THR.api('/hr/assessments/' + id);
        const a = r.assessment || r;
        document.getElementById('title').textContent = a.title;
        document.getElementById('meta').innerHTML = `${a.time_limit} min · ${THR.statusPill(a.status)}`;
        const f = document.getElementById('settingsForm');
        THR.fillForm(f, a);
        f.one_attempt_only.checked = !!a.one_attempt_only;
        f.auto_submit.checked = !!a.auto_submit;
        f.randomize_questions.checked = !!a.randomize_questions;
        const qs = a.questions || r.questions || [];
        document.getElementById('qList').innerHTML = qs.length ? qs.map((q,i) => `<tr><td>${i+1}</td><td>${THR.escapeHtml(q.type)}</td><td>${THR.escapeHtml(q.question_text)}</td><td>${q.marks}</td></tr>`).join('') : '<tr><td colspan="4" class="empty-state">No questions yet</td></tr>';
    } catch (e) { THR.toast(e.message, 'danger'); }
}

document.addEventListener('DOMContentLoaded', () => {
    // Initialize modal properly
    qModal = new bootstrap.Modal(document.getElementById('qModal'));
    
    document.getElementById('qType').addEventListener('change', e => {
        document.getElementById('optionsBlock').style.display = e.target.value === 'mcq' ? '' : 'none';
    });
    load();
});

document.getElementById('settingsForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = THR.formData(e.target);
    data.one_attempt_only = !!e.target.one_attempt_only.checked;
    data.auto_submit = !!e.target.auto_submit.checked;
    data.randomize_questions = !!e.target.randomize_questions.checked;
    data.time_limit = parseInt(data.time_limit, 10);
    data.cooldown_days = parseInt(data.cooldown_days || 7, 10);
    try { 
        await THR.api(`/hr/assessments/${id}`, { method: 'PUT', body: data }); 
        THR.toast('Saved','success'); 
        load(); 
    } catch (err) { THR.toast(err.message, 'danger'); }
});

document.getElementById('qForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = THR.formData(e.target);
    if (data.type === 'mcq' && data.options) data.options = data.options.split(/\r?\n/).map(s=>s.trim()).filter(Boolean);
    else delete data.options;
    data.marks = parseInt(data.marks, 10);
    try { 
        await THR.api(`/hr/assessments/${id}/questions`, { method: 'POST', body: data }); 
        THR.toast('Question added','success'); 
        qModal.hide(); 
        e.target.reset(); 
        load(); 
    } catch (err) { THR.toast(err.message, 'danger'); }
});
</script>
@endpush
