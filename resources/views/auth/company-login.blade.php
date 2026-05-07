@extends('layouts.guest')
@section('title', 'Company Login')
@section('subtitle', 'Company Owner Portal')
@section('content')
<h4 class="mb-3">Sign in to your company</h4>
<form id="loginForm" novalidate>
    <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required minlength="6"></div>
    <button class="btn btn-primary w-100" type="submit">Sign in</button>
    <p class="text-center mt-3 mb-0 small">No account? <a href="/company/register">Register your company</a></p>
    <p class="text-center mb-0 small"><a href="/" class="text-muted">← Back to home</a></p>
</form>
@push('scripts')
<script>
document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = window.THR.formData(e.target);
    try {
        console.log('Attempting company login with:', data.email);
        const res = await window.THR.api('/company/login', { method: 'POST', body: data });
        console.log('Company login response:', res);
        
        if (res.email_verification_required) {
            console.log('Email verification required');
            sessionStorage.setItem('pendingEmail', data.email);
            window.THR.toast('Please verify your email first.', 'warning');
            setTimeout(() => location.href = '/company/verify-email', 600);
            return;
        }
        if (res.requires_two_factor) {
            console.log('2FA required');
            sessionStorage.setItem('pendingEmail', res.email);
            window.THR.toast('2FA code sent.', 'info');
            setTimeout(() => location.href = '/company/2fa', 400);
            return;
        }
        if (res.token && res.user) {
            console.log('Login successful');
            window.THR.Auth.set(res.token, 'company', res.user);
            window.THR.toast('Login successful!', 'success');
            location.href = '/company/dashboard';
        } else {
            throw new Error('Invalid response from server');
        }
    } catch (err) {
        console.error('Company login error:', err);
        const errorMessage = err.message || 'Login failed';
        window.THR.toast(errorMessage, 'danger');
    }
});
</script>
@endpush
@endsection
