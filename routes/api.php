<?php

use App\Http\Controllers\Api\Admin\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\ActivityLogController;
use App\Http\Controllers\Api\Admin\CompanyVerificationController;
use App\Http\Controllers\Api\Admin\SupervisorVerificationController;
use App\Http\Controllers\Api\Admin\InternshipVerificationController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Admin\HrMonitoringController;
use App\Http\Controllers\Api\Admin\JobApprovalController;
use App\Http\Controllers\Api\Admin\FraudLogController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\SecureFileController;
use App\Http\Controllers\Api\Candidate\ApplicationController as CandidateApplicationController;
use App\Http\Controllers\Api\Candidate\AssessmentController as CandidateAssessmentController;
use App\Http\Controllers\Api\Candidate\AuthController as CandidateAuthController;
use App\Http\Controllers\Api\Candidate\DashboardController as CandidateDashboardController;
use App\Http\Controllers\Api\Candidate\InternshipController as CandidateInternshipController;
use App\Http\Controllers\Api\Candidate\NotificationController as CandidateNotificationController;
use App\Http\Controllers\Api\Candidate\ProfileController as CandidateProfileController;
use App\Http\Controllers\Api\Candidate\TaskController as CandidateTaskController;
use App\Http\Controllers\Api\Company\AuthController as CompanyAuthController;
use App\Http\Controllers\Api\Company\DashboardController as CompanyDashboardController;
use App\Http\Controllers\Api\Company\ProfileController as CompanyProfileController;
use App\Http\Controllers\Api\Company\DocumentController as CompanyDocumentController;
use App\Http\Controllers\Api\Company\SupervisorController as CompanySupervisorController;
use App\Http\Controllers\Api\Company\HrUserController as CompanyHrUserController;
use App\Http\Controllers\Api\Company\HiringOverviewController as CompanyHiringOverviewController;
use App\Http\Controllers\Api\Company\NotificationController as CompanyNotificationController;
use App\Http\Controllers\Api\Company\AccountSettingsController as CompanyAccountSettingsController;
use App\Http\Controllers\Api\Hr\AuthController as HrAuthController;
use App\Http\Controllers\Api\Hr\ApplicationController as HrApplicationController;
use App\Http\Controllers\Api\Hr\AssessmentController as HrAssessmentController;
use App\Http\Controllers\Api\Hr\DashboardController as HrDashboardController;
use App\Http\Controllers\Api\Hr\JobController as HrJobController;




Route::prefix('admin')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'super_admin'])->group(function () {
        Route::get('/me', function () {
            return response()->json([
                'message' => 'Super admin route working.',
                'user' => request()->user(),
            ]);
            
        });
     

        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/companies', [CompanyVerificationController::class, 'index']);
        Route::get('/companies/{company}', [CompanyVerificationController::class, 'show']);
        Route::post('/companies/{company}/approve', [CompanyVerificationController::class, 'approve']);
        Route::post('/companies/{company}/reject', [CompanyVerificationController::class, 'reject']);
        Route::get('/supervisors', [SupervisorVerificationController::class, 'index']);
        Route::get('/supervisors/{supervisor}', [SupervisorVerificationController::class, 'show']);
        Route::post('/supervisor/{supervisor}/approve', [SupervisorVerificationController::class, 'approve']);
        Route::post('/supervisor/{supervisor}/reject', [SupervisorVerificationController::class, 'reject']);
        Route::get('/internships', [InternshipVerificationController::class, 'index']);
        Route::get('/internships/{internship}', [InternshipVerificationController::class, 'show']);
        Route::post('/internships/{internship}/verify', [InternshipVerificationController::class, 'verify']);
        Route::post('/internships/{internship}/partial', [InternshipVerificationController::class, 'partial']);
        Route::post('/internships/{internship}/reject', [InternshipVerificationController::class, 'reject']);
        Route::get('/users', [UserManagementController::class, 'index']);
        Route::get('/users/{user}', [UserManagementController::class, 'show']);
        Route::post('/users/{user}/deactivate', [UserManagementController::class, 'deactivate']);
        Route::post('/users/{user}/activate', [UserManagementController::class, 'activate']);
        Route::get('/hr-monitoring', [HrMonitoringController::class, 'index']);
        Route::get('/hr-monitoring/{hr}', [HrMonitoringController::class, 'show']);
        Route::get('/fraud-logs', [FraudLogController::class, 'index']);
        Route::get('/fraud-logs/{fraudLog}', [FraudLogController::class, 'show']);
        Route::post('/fraud/{fraudLog}/flag', [FraudLogController::class, 'flag']);
        Route::post('/fraud/{fraudLog}/resolve', [FraudLogController::class, 'resolve']);
        Route::post('/fraud/{fraudLog}/mark-as-fraud', [FraudLogController::class, 'markAsFraud']);
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/reports', [DashboardController::class, 'reports']);
        Route::get('/files/company-document/{document}', [SecureFileController::class, 'companyDocument']);
        Route::get('/files/supervisor-selfie/{supervisor}', [SecureFileController::class, 'supervisorSelfie']);
        Route::get('/files/internship-certificate/{internship}', [SecureFileController::class, 'internshipCertificate']);







        Route::get('/jobs', [JobApprovalController::class, 'index']);
        Route::get('/jobs/{job}', [JobApprovalController::class, 'show']);
        Route::post('/jobs/{job}/approve', [JobApprovalController::class, 'approve']);
        Route::post('/jobs/{job}/reject', [JobApprovalController::class, 'reject']);

        Route::get('/activity-logs', [ActivityLogController::class, 'index']);

    });
});
Route::prefix('company')->group(function () {
    Route::post('/register', [CompanyAuthController::class, 'register']);
    Route::post('/login', [CompanyAuthController::class, 'login']);
    Route::post('/verify-email', [CompanyAuthController::class, 'verifyEmail']);
    Route::post('/resend-verification-code', [CompanyAuthController::class, 'resendVerificationCode']);
    Route::post('/verify-2fa', [CompanyAuthController::class, 'verifyTwoFactor']);
 

        Route::middleware(['auth:sanctum', 'company_owner'])->group(function () {
        Route::get('/me', [CompanyAuthController::class, 'me']);
        Route::post('/logout', [CompanyAuthController::class, 'logout']);
        Route::get('/dashboard', [CompanyDashboardController::class, 'index']);
        Route::get('/profile', [CompanyProfileController::class, 'show']);
        Route::put('/profile', [CompanyProfileController::class, 'update']);
        Route::post('/documents', [CompanyDocumentController::class, 'store']);
        Route::get('/documents/{document}/file', [CompanyDocumentController::class, 'file']);
        Route::post('/supervisor', [CompanySupervisorController::class, 'store']);
        Route::get('/hr', [CompanyHrUserController::class, 'index']);
        Route::post('/hr', [CompanyHrUserController::class, 'store']);
        Route::put('/hr/{hr}', [CompanyHrUserController::class, 'update']);
        Route::post('/hr/{hr}/deactivate', [CompanyHrUserController::class, 'deactivate']);
        Route::get('/jobs-overview', [CompanyHiringOverviewController::class, 'index']);
        Route::get('/notifications', [CompanyNotificationController::class, 'index']);
        Route::post('/notifications/read-all', [CompanyNotificationController::class, 'markAllAsRead']);
        Route::post('/notifications/{notification}/read', [CompanyNotificationController::class, 'markAsRead']);
        Route::get('/account-settings', [CompanyAccountSettingsController::class, 'show']);
        Route::post('/account-settings/change-password', [CompanyAccountSettingsController::class, 'changePassword']);
        Route::post('/account-settings/two-factor', [CompanyAccountSettingsController::class, 'updateTwoFactor']);



    });
});

Route::prefix('candidate')->group(function () {
    Route::post('/login', [CandidateAuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'candidate_user'])->group(function () {
        Route::get('/me', [CandidateAuthController::class, 'me']);
        Route::post('/logout', [CandidateAuthController::class, 'logout']);
        Route::get('/dashboard', [CandidateDashboardController::class, 'index']);
        Route::get('/profile', [CandidateProfileController::class, 'show']);
        Route::put('/profile', [CandidateProfileController::class, 'update']);
        Route::post('/apply', [CandidateApplicationController::class, 'apply']);
        Route::get('/applications', [CandidateApplicationController::class, 'index']);
        Route::get('/applications/{application}', [CandidateApplicationController::class, 'show']);
        Route::post('/assessment/start', [CandidateAssessmentController::class, 'start']);
        Route::post('/assessment/log', [CandidateAssessmentController::class, 'logEvent']);
        Route::post('/assessment/submit', [CandidateAssessmentController::class, 'submit']);
        Route::post('/task/submit', [CandidateTaskController::class, 'submit']);
        Route::get('/internships', [CandidateInternshipController::class, 'index']);
        Route::post('/internships', [CandidateInternshipController::class, 'store']);
        Route::get('/notifications', [CandidateNotificationController::class, 'index']);
        Route::post('/notifications/read-all', [CandidateNotificationController::class, 'markAllAsRead']);
        Route::post('/notifications/{notification}/read', [CandidateNotificationController::class, 'markAsRead']);
    });
});

Route::prefix('hr')->group(function () {
    Route::post('/login', [HrAuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'hr_user'])->group(function () {
        Route::get('/me', [HrAuthController::class, 'me']);
        Route::post('/logout', [HrAuthController::class, 'logout']);
        Route::get('/dashboard', [HrDashboardController::class, 'index']);
        Route::get('/applications', [HrApplicationController::class, 'index']);
        Route::get('/applications/{application}', [HrApplicationController::class, 'show']);
        Route::post('/applications/{application}/shortlist', [HrApplicationController::class, 'shortlist']);
        Route::post('/applications/{application}/reject', [HrApplicationController::class, 'reject']);
        Route::post('/applications/{application}/assign-task', [HrApplicationController::class, 'assignTask']);
        Route::post('/applications/{application}/review-task', [HrApplicationController::class, 'reviewTask']);
        Route::post('/applications/{application}/schedule-interview', [HrApplicationController::class, 'scheduleInterview']);
        Route::get('/assessments', [HrAssessmentController::class, 'index']);
        Route::get('/assessments/{assessment}', [HrAssessmentController::class, 'show']);
        Route::post('/assessments', [HrAssessmentController::class, 'store']);
        Route::put('/assessments/{assessment}', [HrAssessmentController::class, 'update']);
        Route::post('/assessments/{assessment}/questions', [HrAssessmentController::class, 'addQuestion']);
        Route::get('/jobs', [HrJobController::class, 'index']);
        Route::get('/jobs/{job}', [HrJobController::class, 'show']);
        Route::post('/jobs', [HrJobController::class, 'store']);
        Route::put('/jobs/{job}', [HrJobController::class, 'update']);
        Route::post('/jobs/{job}/deactivate', [HrJobController::class, 'deactivate']);
        

    });
});
