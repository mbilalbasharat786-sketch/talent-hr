@extends('layouts.app', ['role' => 'company'])
@section('title', 'Account Settings')
@section('content')
<div class="page-header"><div><h1>Account Settings</h1><p>Password & two-factor authentication</p></div></div>
<div class="row g-3">
    <div class="col-lg-6"><div class="card"><div class="card-header">Change password</div><div class="card-body">
        <form id="pwForm">
            <div class="mb-3"><label class="form-label">Current password</label><input type="password" name="current_password" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">New password</label><input type="password" name="new_password" class="form-control" required minlength="6"></div>
            <div class="mb-3"><label class="form-label">Confirm new</label><input type="password" name="new_password_confirmation" class="form-control" required></div>
            <button class="btn btn-primary">Update password</button>
        </form>
    </div></div></div>
    <div class="col-lg-6"><div class="card"><div class="card-header">Two-factor authentication</div><div class="card-body">
        <p class="text-muted small">Require a 6-digit email code at every login.</p>
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="twoFactorSwitch">
            <label class="form-check-label" for="twoFactorSwitch">Enable 2FA</label>
        </div>
        <button class="btn btn-primary mt-3" id="twoFactorSave">Save</button>
    </div></div></div>
</div>
@push('scripts')
<script>
async function load() {
    try {
        const r = await THR.api('/company/account-settings');
        document.getElementById('twoFactorSwitch').checked = !!(r.account_settings && r.account_settings.two_factor_enabled);
    } catch (e) { THR.toast(e.message, 'danger'); }
}
load();
document.getElementById('pwForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    try { await THR.api('/company/account-settings/change-password', { method: 'POST', body: THR.formData(e.target) }); THR.toast('Password updated','success'); e.target.reset(); }
    catch (err) { THR.toast(err.message, 'danger'); }
});
document.getElementById('twoFactorSave').addEventListener('click', async () => {
    const enabled = document.getElementById('twoFactorSwitch').checked;
    try { await THR.api('/company/account-settings/two-factor', { method: 'POST', body: { two_factor_enabled: enabled } }); THR.toast('2FA preference saved','success'); }
    catch (err) { THR.toast(err.message, 'danger'); }
});
</script>
@endpush
@endsection
