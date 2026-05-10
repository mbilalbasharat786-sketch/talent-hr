@extends('layouts.guest')
@section('title', 'Verify Candidate Email')
@section('content')
<h4 class="mb-3">Verify your email</h4>
<p class="text-muted small">A 6-digit code was sent to your email. Enter it below to activate your candidate login.</p>
<form id="verifyForm" novalidate>
    <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">Code</label><input type="text" name="code" class="form-control" required pattern="\d{6}" maxlength="6"></div>
    <button class="btn btn-primary w-100" type="submit">Verify</button>
    <button type="button" class="btn btn-link w-100 mt-2" id="resendBtn">Resend code</button>
    <p class="text-center mt-2 mb-0 small"><a href="/candidate/login">Back to login</a></p>
</form>
@push('scripts')
<script>
const pending = sessionStorage.getItem('pendingCandidateEmail');
if (pending) document.querySelector('[name=email]').value = pending;

document.getElementById('verifyForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = window.THR.formData(e.target);
    try {
        await window.THR.api('/candidate/verify-email', { method: 'POST', body: data });
        window.THR.toast('Email verified. Please log in.', 'success');
        setTimeout(() => location.href = '/candidate/login', 600);
    } catch (err) {
        window.THR.toast(err.message || 'Verification failed', 'danger');
    }
});

document.getElementById('resendBtn').addEventListener('click', async () => {
    const email = document.querySelector('[name=email]').value;
    if (!email) return window.THR.toast('Enter email first', 'warning');
    try {
        const res = await window.THR.api('/candidate/resend-verification-code', { method: 'POST', body: { email } });
        if (res.verification_code) window.THR.toast('Dev code: ' + res.verification_code, 'info');
        window.THR.toast('Code resent.', 'success');
    } catch (err) {
        window.THR.toast(err.message || 'Failed to resend code', 'danger');
    }
});
</script>
@endpush
@endsection
