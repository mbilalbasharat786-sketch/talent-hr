<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — Blade view shells for the TalentHR portals
|--------------------------------------------------------------------------
| All authenticated data is fetched client-side via /api endpoints with a
| Sanctum bearer token stored in localStorage (see public/assets/js/app.js).
| These routes only render the view skeletons and redirect-on-unauth logic
| is enforced inside layouts/app.blade.php.
*/

Route::get('/', fn () => view('welcome'));
Route::get('/jobs', fn () => view('public.jobs'));

// Named "login" route exists only to satisfy Laravel's auth redirect
// (when an API call hits 401 without a token, Laravel calls route('login')).
// We respond with JSON for API/XHR clients and redirect to admin login otherwise.
Route::get('/login', function (\Illuminate\Http\Request $request) {
    if ($request->expectsJson() || str_starts_with($request->path(), 'api')) {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }
    return redirect('/admin/login');
})->name('login');

// ---------- AUTH ----------
Route::view('/admin/login', 'auth.admin-login');
Route::view('/company/login', 'auth.company-login');
Route::view('/company/register', 'auth.company-register');
Route::view('/company/verify-email', 'auth.company-verify-email');
Route::view('/company/2fa', 'auth.company-2fa');
Route::view('/hr/login', 'auth.hr-login');
Route::view('/candidate/login', 'auth.candidate-login');
Route::view('/candidate/register', 'auth.candidate-register');

// ---------- ADMIN ----------
Route::prefix('admin')->group(function () {
    Route::view('/dashboard', 'admin.dashboard');
    Route::view('/reports', 'admin.reports');
    Route::view('/companies', 'admin.companies.index');
    Route::get('/companies/{id}', fn ($id) => view('admin.companies.show', ['id' => (int) $id]))->whereNumber('id');
    Route::view('/supervisors', 'admin.supervisors.index');
    Route::get('/supervisors/{id}', fn ($id) => view('admin.supervisors.show', ['id' => (int) $id]))->whereNumber('id');
    Route::view('/internships', 'admin.internships.index');
    Route::get('/internships/{id}', fn ($id) => view('admin.internships.show', ['id' => (int) $id]))->whereNumber('id');
    Route::view('/users', 'admin.users.index');
    Route::get('/users/{id}', fn ($id) => view('admin.users.show', ['id' => (int) $id]))->whereNumber('id');
    Route::view('/hr-monitoring', 'admin.hr-monitoring.index');
    Route::get('/hr-monitoring/{id}', fn ($id) => view('admin.hr-monitoring.show', ['id' => (int) $id]))->whereNumber('id');
    Route::view('/fraud-logs', 'admin.fraud-logs.index');
    Route::get('/fraud-logs/{id}', fn ($id) => view('admin.fraud-logs.show', ['id' => (int) $id]))->whereNumber('id');
    Route::view('/activity-logs', 'admin.activity-logs.index');
});

// ---------- COMPANY ----------
Route::prefix('company')->group(function () {
    Route::view('/dashboard', 'company.dashboard');
    Route::view('/profile', 'company.profile');
    Route::view('/documents', 'company.documents');
    Route::view('/supervisors', 'company.supervisors');
    Route::view('/hr', 'company.hr.index');
    Route::view('/jobs-overview', 'company.jobs-overview');
    Route::view('/notifications', 'company.notifications');
    Route::view('/account-settings', 'company.account-settings');
});

// ---------- HR ----------
Route::prefix('hr')->group(function () {
    Route::view('/dashboard', 'hr.dashboard');
    Route::view('/jobs', 'hr.jobs.index');
    Route::view('/jobs/create', 'hr.jobs.create');
    Route::get('/jobs/{id}', fn ($id) => view('hr.jobs.show', ['id' => (int) $id]))->whereNumber('id');
    Route::get('/jobs/{id}/edit', fn ($id) => view('hr.jobs.edit', ['id' => (int) $id]))->whereNumber('id');
    Route::view('/applications', 'hr.applications.index');
    Route::get('/applications/{id}', fn ($id) => view('hr.applications.show', ['id' => (int) $id]))->whereNumber('id');
    Route::view('/assessments', 'hr.assessments.index');
    Route::view('/assessments/create', 'hr.assessments.create');
    Route::get('/assessments/{id}', fn ($id) => view('hr.assessments.show', ['id' => (int) $id]))->whereNumber('id');
});

// ---------- CANDIDATE ----------
Route::prefix('candidate')->group(function () {
    Route::view('/dashboard', 'candidate.dashboard');
    Route::view('/profile', 'candidate.profile');
    Route::view('/jobs', 'candidate.jobs');
    Route::view('/applications', 'candidate.applications.index');
    Route::get('/applications/{id}', fn ($id) => view('candidate.applications.show', ['id' => (int) $id]))->whereNumber('id');
    Route::view('/assessment', 'candidate.assessment');
    Route::view('/internships', 'candidate.internships');
    Route::view('/notifications', 'candidate.notifications');
});
