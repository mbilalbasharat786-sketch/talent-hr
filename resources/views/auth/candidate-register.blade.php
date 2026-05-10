@extends('layouts.guest')
@section('title', 'Candidate Registration')
@section('subtitle', 'Create your candidate account')
@section('content')
<h4 class="mb-2">Candidate register</h4>
<p class="text-muted small mb-4">Create an account, verify your email, then complete your profile from the candidate dashboard.</p>

<form id="registerForm" novalidate>
    <div class="mb-3">
        <label class="form-label">Full name</label>
        <input type="text" class="form-control" name="name" required maxlength="255" autocomplete="name">
    </div>
    <div class="mb-3">
        <label class="form-label">Email address</label>
        <input type="email" class="form-control" name="email" required autocomplete="email">
    </div>
    <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" class="form-control" name="password" required minlength="8" autocomplete="new-password">
        <div class="form-text">Minimum 8 characters.</div>
    </div>
    <div class="mb-3">
        <label class="form-label">Confirm password</label>
        <input type="password" class="form-control" name="password_confirmation" required minlength="8" autocomplete="new-password">
    </div>
    <button type="submit" class="btn btn-primary w-100" id="registerBtn">
        <span class="spinner-border spinner-border-sm d-none" id="spinner"></span>
        Create account
    </button>
    <p class="text-center mt-3 mb-0 small">Already have an account? <a href="/candidate/login">Sign in</a></p>
</form>

@push('scripts')
<script>
document.getElementById('registerForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const data = THR.formData(form);

    if (!data.name || !data.email || !data.password || !data.password_confirmation) {
        return THR.toast('Please fill all required fields.', 'warning');
    }

    if (data.password.length < 8) {
        return THR.toast('Password must be at least 8 characters.', 'warning');
    }

    if (data.password !== data.password_confirmation) {
        return THR.toast('Passwords do not match.', 'warning');
    }

    delete data.password_confirmation;

    const spinner = document.getElementById('spinner');
    const registerBtn = document.getElementById('registerBtn');
    spinner.classList.remove('d-none');
    registerBtn.disabled = true;

    try {
        const response = await THR.api('/candidate/register', { method: 'POST', body: data });
        sessionStorage.setItem('pendingCandidateEmail', data.email);
        if (response.verification_code) THR.toast('Dev code: ' + response.verification_code, 'info');
        THR.toast(response.message || 'Account created. Verify your email.', 'success');
        setTimeout(() => window.location.href = '/candidate/verify-email', 700);
    } catch (error) {
        THR.toast(error.message || 'Registration failed. Please try again.', 'danger');
    } finally {
        spinner.classList.add('d-none');
        registerBtn.disabled = false;
    }
});
</script>
@endpush
@endsection
