<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment · TalentHR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('assets/css/app.css') }}" rel="stylesheet">
</head>
<body class="assessment-shell">
<div class="container-xl">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h3 class="mb-0" id="assTitle">Assessment</h3><div class="text-muted small" id="assMeta">Loading…</div></div>
        <div class="text-end">
            <div class="badge bg-warning text-dark fs-5" id="timer">--:--</div>
            <div class="small text-muted">Warnings: <span id="warnCount">0</span> / Violations: <span id="violCount">0</span></div>
        </div>
    </div>
    <div id="container">
        <div class="card bg-dark text-light"><div class="card-body">
            <h5>Pre-flight</h5>
            <p class="text-muted">By starting, you agree to fullscreen, no tab switching, no copy/paste. Violations may auto-submit.</p>
            <button class="btn btn-warning" id="startBtn"><i class="bi bi-play-fill"></i> Start assessment</button>
            <a href="/candidate/applications" class="btn btn-link text-light">Cancel</a>
        </div></div>
    </div>
</div>
<div id="toastStack"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('assets/js/app.js') }}"></script>
<script>
window.THR.role = 'candidate';
if (!window.THR.requireAuth('candidate')) { /* redirected */ }

const params = new URLSearchParams(location.search);
const applicationId = parseInt(params.get('application_id'), 10);
let session, submission, assessment, questions = [], timerInterval;

function fingerprint() {
    return btoa(navigator.userAgent + '|' + screen.width + 'x' + screen.height + '|' + (navigator.language||'')).slice(0, 64);
}
function browserName() {
    const ua = navigator.userAgent;
    if (/Edg/i.test(ua)) return 'Edge';
    if (/Chrome/i.test(ua)) return 'Chrome';
    if (/Firefox/i.test(ua)) return 'Firefox';
    if (/Safari/i.test(ua)) return 'Safari';
    return 'Unknown';
}

async function logEvent(event_type, metadata = {}) {
    if (!session) return;
    try {
        const r = await THR.api('/candidate/assessment/log', { method: 'POST', body: { session_token: session.session_token, event_type, metadata } });
        if (r.session) {
            document.getElementById('warnCount').textContent = r.session.warning_count;
            document.getElementById('violCount').textContent = r.session.violation_count;
            if (r.session.status !== 'in_progress') {
                THR.toast('Session ended: ' + r.message, 'warning');
                setTimeout(() => location.href = '/candidate/applications/' + applicationId, 1500);
            }
        }
    } catch (e) { /* ignore */ }
}

document.getElementById('startBtn').addEventListener('click', async () => {
    if (!applicationId) return THR.toast('Missing application_id in URL.', 'danger');
    try {
        const res = await THR.api('/candidate/assessment/start', {
            method: 'POST',
            body: { application_id: applicationId, device_fingerprint: fingerprint(), browser: browserName() }
        });
        session = res.session; submission = res.submission;
        assessment = session.assessment || {};
        questions = assessment.questions || [];
        if (!questions.length && session.assessment_id) {
            // fallback: HR may not have surfaced questions; try fetching via candidate dashboard not available, skip
        }
        renderQuestions();
        startTimer();
        attachAntiCheat();
        try { document.documentElement.requestFullscreen?.(); } catch(e){}
    } catch (e) { THR.toast(e.message, 'danger'); }
});

function renderQuestions() {
    document.getElementById('assTitle').textContent = assessment.title || 'Assessment';
    document.getElementById('assMeta').textContent = `${questions.length} questions · ${assessment.time_limit || 30} min`;
    if (!questions.length) {
        document.getElementById('container').innerHTML = `
            <div class="card bg-dark text-light"><div class="card-body">
                <p class="mb-3">Your assessment session is active. Submit when ready (HR will review).</p>
                <button class="btn btn-success" id="submitBtn"><i class="bi bi-send"></i> Submit assessment</button>
            </div></div>`;
        document.getElementById('submitBtn').addEventListener('click', () => submit({}));
        return;
    }
    const html = questions.map((q, i) => `
        <div class="question-card mb-3" data-qid="${q.id}">
            <div class="d-flex justify-content-between"><h6>Q${i+1}. ${THR.escapeHtml(q.question_text)}</h6><span class="badge bg-secondary">${q.marks} mark(s)</span></div>
            ${renderInput(q)}
        </div>`).join('');
    document.getElementById('container').innerHTML = html + `<button class="btn btn-success" id="submitBtn"><i class="bi bi-send"></i> Submit assessment</button>`;
    document.getElementById('submitBtn').addEventListener('click', () => {
        const answers = {};
        document.querySelectorAll('[data-qid]').forEach(card => {
            const qid = card.dataset.qid;
            const inp = card.querySelector('input[type=radio]:checked, input[type=text], textarea');
            answers[qid] = inp ? inp.value : null;
        });
        submit(answers);
    });
}

function renderInput(q) {
    if (q.type === 'mcq' && Array.isArray(q.options)) {
        return q.options.map((opt,i) => `<div class="form-check"><input type="radio" class="form-check-input" name="q_${q.id}" value="${THR.escapeHtml(opt)}" id="q${q.id}_${i}"><label class="form-check-label" for="q${q.id}_${i}">${THR.escapeHtml(opt)}</label></div>`).join('');
    }
    return `<textarea rows="4" class="form-control bg-dark text-light border-secondary" name="q_${q.id}"></textarea>`;
}

function startTimer() {
    const expires = new Date(session.expires_at).getTime();
    timerInterval = setInterval(() => {
        const left = Math.max(0, expires - Date.now());
        const m = Math.floor(left / 60000); const s = Math.floor((left % 60000) / 1000);
        document.getElementById('timer').textContent = String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
        if (left <= 0) { clearInterval(timerInterval); submit({}); }
    }, 1000);
}

function attachAntiCheat() {
    document.addEventListener('visibilitychange', () => { if (document.hidden) logEvent('tab_switch'); });
    window.addEventListener('blur', () => logEvent('window_blur'));
    document.addEventListener('fullscreenchange', () => { if (!document.fullscreenElement) logEvent('fullscreen_exit'); });
    document.addEventListener('contextmenu', (e) => { e.preventDefault(); logEvent('right_click'); });
    document.addEventListener('copy', () => logEvent('copy_paste_attempt', { kind: 'copy' }));
    document.addEventListener('paste', () => logEvent('copy_paste_attempt', { kind: 'paste' }));
}

async function submit(answers) {
    if (!session) return;
    try {
        const r = await THR.api('/candidate/assessment/submit', { method: 'POST', body: { session_token: session.session_token, answers } });
        THR.toast(r.message || 'Submitted', 'success');
        clearInterval(timerInterval);
        setTimeout(() => location.href = '/candidate/applications/' + applicationId, 1200);
    } catch (e) { THR.toast(e.message, 'danger'); }
}
</script>
</body>
</html>
