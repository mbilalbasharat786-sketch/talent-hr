@extends('layouts.guest')
@section('title', 'Candidate Login')
@section('subtitle', 'Candidate Hub')
@section('content')
<h4 class="mb-3">Candidate sign in</h4>
<form id="loginForm" novalidate>
    <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
    <button class="btn btn-primary w-100" type="submit">Sign in</button>
    <p class="text-center mt-3 mb-0 small"><a href="/" class="text-muted">← Back to home</a></p>
</form>
@push('scripts')
<script>
document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = window.THR.formData(e.target);
    try {
        console.log('Attempting login with:', data.email);
        const res = await window.THR.api('/candidate/login', { method: 'POST', body: data });
        console.log('Login response:', res);
        
        if (res.token && res.user) {
            window.THR.Auth.set(res.token, 'candidate', res.user);
            window.THR.toast('Login successful!', 'success');
            location.href = '/candidate/dashboard';
        } else {
            throw new Error('Invalid response from server');
        }
    } catch (err) { 
        console.error('Login error:', err);
        const errorMessage = err.message || 'Login failed';
        window.THR.toast(errorMessage, 'danger'); 
    }
});
</script>
@endpush
@endsection
