# TalentHR Project Analytics

 Is document ka maqsad yeh hai ke agar kisi ko project ka complete overview dena ho to woh yahan se samajh sake ke system kis cheez ke liye bana hai, roles kya hain, modules kaise kaam karte hain, database mein kya store hota hai, aur backend/frontend flow kaisa hai.

## 1. Project Ka Short Overview

TalentHR ek Laravel based hiring aur internship verification platform hai. Isme 4 main user roles hain:

- `super_admin`: companies, supervisors, internships, users, jobs aur fraud alerts ko verify/manage karta hai.
- `company`: company owner hota hai jo company register karta hai, documents upload karta hai, supervisors add karta hai, HR users create karta hai, aur hiring overview dekh sakta hai.
- `hr`: company ka HR user hota hai jo assessments banata hai, jobs post karta hai, candidates ki applications process karta hai, tasks/interviews/hiring manage karta hai.
- `candidate`: job apply karta hai, profile update karta hai, assessments deta hai, internship certificates submit karta hai, task submit karta hai aur notifications dekhta hai.

Project Laravel 12, Sanctum API tokens, Blade views, plain JavaScript, Bootstrap style UI aur Vite/Tailwind tooling use karta hai. Frontend mostly Blade shell pages hain jahan data `/api/...` endpoints se `fetch` ke through load hota hai. Token browser `localStorage` mein store hota hai.

## 2. Tech Stack

- Backend: Laravel Framework `^12.0`
- PHP: `^8.2`
- Auth/API: Laravel Sanctum `^4.0`
- Queue/Jobs: Laravel queue tables aur `ProcessSubmissionJob`
- Frontend build: Vite `^7.0.7`
- JS dependency: Axios installed, lekin main UI mein mostly native `fetch` helper use hota hai
- CSS tooling: Tailwind CSS `^4.0.0`, Bootstrap/Bootstrap Icons style classes Blade mein use ho rahi hain
- Testing: PHPUnit `^11.5.50`, Laravel test command composer script mein configured hai

## 3. Main Folder Structure

- `app/Http/Controllers/Api/Admin`: super admin APIs.
- `app/Http/Controllers/Api/Company`: company owner APIs.
- `app/Http/Controllers/Api/Hr`: HR APIs.
- `app/Http/Controllers/Api/Candidate`: candidate APIs.
- `app/Http/Controllers/Api/Public`: public jobs APIs.
- `app/Http/Middleware`: role based access middleware.
- `app/Models`: database models and relations.
- `app/Services`: helper/services for logging, scoring, anti-fraud, anti-cheat, plagiarism, skill matching.
- `app/Jobs`: async/sync background job for assessment submission processing.
- `database/migrations`: project ka database schema.
- `database/seeders`: test/demo data seeders.
- `resources/views`: Blade pages for admin/company/hr/candidate/public/auth.
- `public/assets/js/app.js`: global frontend helper `THR`, token handling, API calls, logout, toasts, formatting.
- `public/assets/css/app.css`: custom frontend styling.
- `routes/web.php`: browser pages render karta hai.
- `routes/api.php`: API endpoints define karta hai.

## 4. Authentication Aur Authorization Flow

Project API authentication ke liye Sanctum personal access tokens use karta hai.

Frontend login ke baad token `localStorage` mein `thr_token`, role `thr_role`, aur user `thr_user` keys ke under save hota hai. `public/assets/js/app.js` ka `THR.api()` helper har API request mein `Authorization: Bearer <token>` header bhejta hai. Agar API 401 return kare to helper token clear karke user ko us role ke login page par redirect karta hai.

Role based middleware:

- `SuperAdminMiddleware`: user login ho, role `super_admin` ho, status active ho.
- `CompanyOwnerMiddleware`: user login ho, role `company` ho, active ho, aur `company_id` linked ho.
- `HrUserMiddleware`: user login ho, role `hr` ho, active ho, company linked ho, aur company approved ho.
- `CandidateUserMiddleware`: user login ho, role `candidate` ho, active ho.

## 5. Web Routes

`routes/web.php` mostly Blade view shells render karta hai. Real data API se aata hai.

Public pages:

- `/`: welcome landing page.
- `/jobs`: public jobs listing page.
- `/login`: Laravel default auth redirect ke liye JSON 401 ya `/admin/login` redirect.

Auth pages:

- `/admin/login`
- `/company/login`
- `/company/register`
- `/company/verify-email`
- `/company/2fa`
- `/hr/login`
- `/candidate/login`
- `/candidate/register`
- `/candidate/verify-email`

Admin pages:

- `/admin/dashboard`
- `/admin/reports`
- `/admin/companies`
- `/admin/companies/{id}`
- `/admin/supervisors`
- `/admin/supervisors/{id}`
- `/admin/internships`
- `/admin/internships/{id}`
- `/admin/users`
- `/admin/users/{id}`
- `/admin/hr-monitoring`
- `/admin/hr-monitoring/{id}`
- `/admin/fraud-logs`
- `/admin/fraud-logs/{id}`
- `/admin/activity-logs`
- `/admin/jobs`
- `/admin/jobs/{id}`

Company pages:

- `/company/dashboard`
- `/company/profile`
- `/company/documents`
- `/company/supervisors`
- `/company/hr`
- `/company/jobs-overview`
- `/company/notifications`
- `/company/account-settings`

HR pages:

- `/hr/dashboard`
- `/hr/jobs`
- `/hr/jobs/create`
- `/hr/jobs/{id}`
- `/hr/jobs/{id}/edit`
- `/hr/applications`
- `/hr/applications/{id}`
- `/hr/assessments`
- `/hr/assessments/create`
- `/hr/assessments/{id}`

Candidate pages:

- `/candidate/dashboard`
- `/candidate/profile`
- `/candidate/jobs`
- `/candidate/apply`
- `/candidate/applications`
- `/candidate/applications/{id}`
- `/candidate/assessment`
- `/candidate/internships`
- `/candidate/notifications`

## 6. API Routes Summary

### Admin APIs

Base prefix: `/api/admin`

- `POST /login`: super admin login.
- Protected by `auth:sanctum` and `super_admin`:
- `GET /me`: current admin return karta hai.
- `POST /logout`: current token delete karta hai.
- `GET /companies`: companies list with status/trust filter.
- `GET /companies/{company}`: company details with documents/supervisors.
- `POST /companies/{company}/approve`: company approve, trust level set, documents approve, notification create.
- `POST /companies/{company}/reject`: company reject with reason.
- `GET /supervisors`: supervisors list.
- `GET /supervisors/{supervisor}`: supervisor details.
- `POST /supervisor/{supervisor}/approve`: supervisor approve.
- `POST /supervisor/{supervisor}/reject`: supervisor reject.
- `GET /internships`: internship submissions list.
- `GET /internships/{internship}`: internship details.
- `POST /internships/{internship}/verify`: internship verified.
- `POST /internships/{internship}/partial`: internship partial verification.
- `POST /internships/{internship}/reject`: internship reject.
- `GET /users`: users list role/status filters ke sath.
- `GET /users/{user}`: user details.
- `POST /users/{user}/deactivate`: user inactive aur tokens delete.
- `POST /users/{user}/activate`: user active.
- `GET /hr-monitoring`: HR users ka monitoring list with rejection/shortlist rates.
- `GET /hr-monitoring/{hr}`: HR analytics detail.
- `GET /fraud-logs`: fraud logs list.
- `GET /fraud-logs/{fraudLog}`: fraud log details.
- `POST /fraud/{fraudLog}/flag`: fraud alert flagged.
- `POST /fraud/{fraudLog}/resolve`: fraud resolved.
- `POST /fraud/{fraudLog}/mark-as-fraud`: alert fraud mark hota hai.
- `GET /dashboard`: admin dashboard stats.
- `GET /reports`: reports data.
- `GET /files/company-document/{document}`: secure company document.
- `GET /files/supervisor-selfie/{supervisor}`: supervisor selfie file.
- `GET /files/internship-certificate/{internship}`: internship certificate file.
- `GET /jobs`: jobs for approval.
- `GET /jobs/{job}`: job detail.
- `POST /jobs/{job}/approve`: pending job live karta hai.
- `POST /jobs/{job}/reject`: job closed karta hai with reason notification.
- `GET /activity-logs`: immutable activity logs list.

### Company APIs

Base prefix: `/api/company`

- `POST /register`: company aur company owner user create, email OTP send.
- `POST /login`: company login; email verify required ho to OTP resend; 2FA enabled ho to 2FA challenge.
- `POST /verify-email`: company owner email OTP verify.
- `POST /resend-verification-code`: email verification code resend.
- `POST /verify-2fa`: 2FA code verify aur token issue.
- Protected by `auth:sanctum` and `company_owner`:
- `GET /me`: current user with company.
- `POST /logout`: token delete.
- `GET /dashboard`: company stats, HR count, jobs overview, internships overview.
- `GET /profile`: company profile with documents and supervisors.
- `PUT /profile`: logo, cover, about, industry, size, website, locations, hours update.
- `POST /documents`: SECP/NTN/address verification docs upload, fake doc detection run, company status pending.
- `GET /documents/{document}/file`: own document file access.
- `POST /supervisor`: supervisor details and selfie upload/upsert.
- `GET /hr`: company HR users list.
- `POST /hr`: HR user create.
- `PUT /hr/{hr}`: own HR user update.
- `POST /hr/{hr}/deactivate`: HR inactive and tokens delete.
- `GET /jobs-overview`: company ke all HR jobs aur application counts.
- `GET /notifications`: company notifications list/filter.
- `POST /notifications/read-all`: all notifications read.
- `POST /notifications/{notification}/read`: one notification read.
- `GET /account-settings`: account info.
- `POST /account-settings/change-password`: password change, old tokens delete, new token issue.
- `POST /account-settings/two-factor`: 2FA enable/disable.

### Candidate APIs

Base prefix: `/api/candidate`

- `POST /login`: candidate login; email verified na ho to OTP resend.
- `POST /register`: candidate account create with skills/education/experience, OTP send.
- `POST /verify-email`: candidate OTP verify.
- `POST /resend-verification-code`: OTP resend.
- Public jobs group currently `candidate` prefix ke andar define hai, is liye URLs `/api/candidate/public/...` ban sakte hain, lekin frontend `/api/public/jobs` call kar raha hai. Yeh routing mismatch hai.
- Protected by `auth:sanctum` and `candidate_user`:
- `GET /me`: current candidate with applications/internship counts.
- `POST /logout`: token delete.
- `GET /dashboard`: candidate stats.
- `GET /profile`: candidate profile with score breakdown.
- `PUT /profile`: skills, education, experience update.
- `GET /jobs`: live jobs with assessment.
- `POST /apply`: job apply and status `assessment_pending`.
- `GET /applications`: candidate applications list.
- `GET /applications/{application}`: own application detail, assessment submission/session info.
- `POST /assessment/start`: assessment session start/resume with device lock.
- `POST /assessment/log`: anti-cheat event log.
- `POST /assessment/submit`: assessment submit/auto-submit processing.
- `POST /task/submit`: second round task file submit.
- `GET /internships`: own internships list.
- `POST /internships`: internship certificate submit with duplicate/fake detection.
- `GET /notifications`: own notifications.
- `POST /notifications/read-all`: all read.
- `POST /notifications/{notification}/read`: one read.

### HR APIs

Base prefix: `/api/hr`

- `POST /login`: HR login.
- Protected by `auth:sanctum` and `hr_user`:
- `GET /me`: current HR user.
- `POST /logout`: token delete.
- `GET /dashboard`: HR dashboard stats.
- `GET /applications`: HR-owned job applications.
- `GET /applications/{application}`: application detail with score, skill match, logs.
- `POST /applications/{application}/shortlist`: passed candidate ko shortlist karta hai.
- `POST /applications/{application}/reject`: candidate reject.
- `POST /applications/{application}/assign-task`: shortlisted candidate ko second round task.
- `POST /applications/{application}/review-task`: task passed/failed mark.
- `POST /applications/{application}/schedule-interview`: interview schedule.
- `POST /applications/{application}/complete-interview`: interview result update.
- `POST /applications/{application}/hire`: candidate hire.
- `GET /assessments`: HR assessments list.
- `GET /assessments/{assessment}`: assessment with questions/submissions.
- `POST /assessments`: assessment create.
- `PUT /assessments/{assessment}`: assessment update.
- `POST /assessments/{assessment}/questions`: question add.
- `GET /jobs`: own jobs.
- `GET /jobs/{job}`: own job detail.
- `POST /jobs`: job create and send to admin approval.
- `PUT /jobs/{job}`: job update and resubmit.
- `POST /jobs/{job}/deactivate`: job closed.

### Public APIs

Controller exists at `App\Http\Controllers\Api\Public\JobController`.

- Intended endpoints: `/api/public/jobs`, `/api/public/jobs/{id}`, `/api/public/jobs/categories`, `/api/public/jobs/featured-companies`, `/api/public/jobs/statistics`, `/api/public/jobs/search`.
- Current `routes/api.php` mein public group candidate prefix ke andar nested nazar aa raha hai, is wajah se actual route path mismatch ho sakta hai. Frontend public jobs page `/api/public/jobs` call karta hai.

## 7. Database Tables Aur Models

### users

Users table roles aur auth details store karta hai.

Important fields:

- `name`, `email`, `password`
- `role`: `super_admin`, `company`, `hr`, `candidate`
- `status`: `active`, `inactive`
- `company_id`: company owner/HR ko company se link karta hai
- `phone`
- `hr_type`: `hr_manager`, `recruiter`
- candidate profile: `skills`, `education`, `experience`, `candidate_rating`
- email verification: `email_verification_code`, `email_verification_expires_at`, `email_verified_at`
- company 2FA: `two_factor_enabled`, `two_factor_code`, `two_factor_expires_at`

Model relations:

- user has many HR jobs as `hrJobs`
- candidate has many `jobApplications`, `assessmentSubmissions`, `internships`, `assessmentSessions`, `taskSubmissions`
- user belongs to `company`
- user has many `notifications`
- HR user has many `assessments`

### companies

Company data aur verification status.

Fields:

- `name`, `email`, `phone`
- `status`: `pending`, `approved`, `rejected`
- `trust_level`: `basic`, `standard`, `gold`, `platinum`
- `rejection_reason`
- profile fields: `logo`, `cover_image`, `about`, `industry`, `company_size`, `website`, `office_locations`, `working_hours`

Relations:

- has many `supervisors`
- has many `verificationDocuments`
- has many `hrJobs`
- has one `owner` where user role company
- has many `hrUsers` where user role hr
- has many `notifications`

### verification_documents

Company documents store karta hai:

- `company_id`
- `type`: `secp`, `ntn`, `address`
- `file_path`
- `status`: `pending`, `approved`, `rejected`

Model secure URL role ke hisaab se company ya admin file endpoint return karta hai.

### supervisors

Company internship supervisor verification ke liye.

- `company_id`
- `name`, `email`, `cnic`
- `selfie_path`
- `status`: `pending`, `approved`, `rejected`
- `rejection_reason`

Model `selfie_secure_url` admin endpoint return karta hai.

### internships

Candidate ke internship proof submissions.

- `candidate_id`
- `company_name`
- `duration`
- `supervisor_email`
- `certificate_path`
- `certificate_hash`
- `certificate_text`
- `verification_email_response`
- `status`: `pending`, `verified`, `partial`, `rejected`
- `rejection_reason`

Admin verify/partial/reject karta hai. Candidate create karta hai.

### fraud_logs

Fraud/suspicious alerts ka central table.

- `type`: `duplicate_internship_certificate`, `suspicious_assessment_pattern`, `fake_document`
- `reference_id`
- `description`
- `status`: `open`, `flagged`, `resolved`, `fraud`
- `resolved_by`, `resolved_at`

Admin fraud log ko flag, resolve, ya mark as fraud kar sakta hai.

### activity_logs

Audit trail. Model updating/deleting block karta hai.

- `user_id`
- `action`
- `module`
- `description`
- `ip_address`
- `user_agent`

Almost har important action `ActivityLogger::log()` se yahan store hota hai.

### hr_jobs

Jobs jo HR post karta hai.

- `hr_id`, `company_id`
- `title`
- `type`: `full_time`, `part_time`, `contract`, `internship`
- `work_mode`: `onsite`, `remote`, `hybrid`
- `location`
- `skills`
- `experience_level`, `education`
- `description`
- `candidates_required`
- `hiring_urgency`: `low`, `medium`, `high`
- `assessment_id`
- `status`: originally migrations mein `draft`, `active`, `pending_approval`, `live`, `closed` hain. Code mein `filled` bhi use ho raha hai, jo enum migration mein missing ho sakta hai.

Relations:

- belongs to HR user
- belongs to company
- has many applications
- belongs to assessment

### job_applications

Candidate job application pipeline.

Fields:

- `job_id`, `candidate_id`
- `status`: initial migrations mein many statuses hain, later pipeline uses `assessment_pending`, `passed`, `failed`, `shortlisted`, `second_task_assigned`, `interview_scheduled`, `interview_passed`, `hired`, `rejected` etc.
- `rejection_reason`
- `skill_match_percentage`
- `experience_verification_status`
- `plagiarism_report`
- `anti_cheat_logs`
- `portfolio_links`
- `cooldown_until`

Relations:

- belongs to job
- belongs to candidate
- has one task
- has one interview

### assessments

HR ke tests/assessments.

- `hr_id`
- `title`
- `time_limit`
- `one_attempt_only`
- `auto_submit`
- `randomize_questions`
- `cooldown_days`
- `status`: `draft`, `active`, `locked`

Assessment has many questions, submissions, jobs.

### questions

Assessment questions.

- `assessment_id`
- `type`: `mcq`, `coding`, `case`, `file`
- `question_text`
- `options`
- `expected_answer`
- `marks`

### assessment_submissions

Candidate assessment attempt ka result.

- `assessment_id`, `candidate_id`
- `score`
- `status`: `started`, `submitted`, `passed`, `failed`, `auto_submitted`
- `started_at`, `submitted_at`
- `cheating_flag`: `normal`, `suspicious`, `cheating_detected`
- `plagiarism_report`
- `answers_payload`

Unique index `assessment_id + candidate_id` one attempt behavior enforce karta hai.

### assessment_sessions

Live assessment session tracking.

- `candidate_id`, `assessment_id`, `job_application_id`
- `session_token`
- `started_at`, `expires_at`
- `status`: `in_progress`, `submitted`, `auto_submitted`, `expired`
- `device_info`
- `ip_address`
- `warning_count`
- `violation_count`

### assessment_logs

Assessment anti-cheat events. Model updating/deleting block karta hai.

- `session_id`
- `event_type`: tab switch, blur, fullscreen exit, copy paste, right click, multiple device, multiple tab, suspicious pattern
- `event_time`
- `metadata`

### tasks

Second round task.

- `application_id`
- `title`, `description`
- `instructions_file`
- `deadline`
- `submission_file`
- `status`: `assigned`, `submitted`, `passed`, `failed`

### task_submissions

Candidate task submission file.

- `task_id`, `candidate_id`
- `file_path`
- `status`: `submitted`, `reviewed`

### interviews

Interview schedule.

- `application_id`
- `date`
- `time`
- `mode`: `onsite`, `online`, `hybrid`

Code `completeInterview()` mein `status`, `result`, `feedback` update karta hai, lekin migration mein yeh fields visible nahi hain. Yeh schema gap ho sakta hai.

### notifications

System/user/company notifications.

- `user_id`
- `company_id`
- `type`: company verification, HR activity, system alert etc.
- `title`
- `message`
- `read_at`

## 8. Admin Module Detail

Admin ka kaam platform governance hai.

### Admin Auth

`Admin\AuthController`

- `login()`: email/password validate karta hai, role `super_admin` user find karta hai, password check karta hai, active status check karta hai, Sanctum token issue karta hai, activity log create karta hai.
- `logout()`: logout activity log karta hai aur current token delete karta hai.

### Admin Dashboard

`Admin\DashboardController`

- `index()`: total companies, verified companies, pending verifications, candidates, jobs, live jobs, pending approvals, assessments, fraud alerts aur recent activities return karta hai.
- `reports()`: company, users, internships, HR activity aur fraud ka detailed count/grouped report return karta hai.

### Company Verification

`Admin\CompanyVerificationController`

- `index()`: status/trust filter ke sath companies paginate.
- `show()`: company with documents and supervisors.
- `approve()`: trust level validate, company approved, documents approved, owner notification, activity log.
- `reject()`: reason required, company rejected, documents rejected, owner notification, activity log.

### Supervisor Verification

`Admin\SupervisorVerificationController`

- `index()`: status filter ke sath supervisors.
- `show()`: supervisor with company.
- `approve()`: supervisor status approved.
- `reject()`: reason ke sath rejected.

### Internship Verification

`Admin\InternshipVerificationController`

- `index()`: status/candidate filter ke sath internships.
- `show()`: candidate details ke sath internship.
- `verify()`: internship verified.
- `partial()`: internship partial.
- `reject()`: rejection reason ke sath rejected.

### User Management

`Admin\UserManagementController`

- `index()`: role/status filters ke sath users.
- `show()`: user with company.
- `deactivate()`: super admin ko deactivate nahi karta; baqi user inactive aur tokens delete.
- `activate()`: user active.

### HR Monitoring

`Admin\HrMonitoringController`

- `index()`: HR users list with jobs count, company name, rejection rate, shortlist rate.
- `show()`: selected HR ka jobs, applications, rejection reasons aur hiring status distribution.

### Job Approval

`Admin\JobApprovalController`

- `index()`: job approval list.
- `show()`: job detail with HR/company/assessment questions.
- `approve()`: sirf `pending_approval` job ko `live` karta hai, HR ko notification.
- `reject()`: reason validate, job `closed`, HR notification.

### Fraud Logs

`Admin\FraudLogController`

- `index()`: type/status filter.
- `show()`: resolver details ke sath.
- `flag()`: status `flagged`.
- `resolve()`: status `resolved`, resolver and resolved_at set.
- `markAsFraud()`: status `fraud`, resolver and resolved_at set.

### Secure File Access

`Admin\SecureFileController`

- `companyDocument()`: verification document secure file serve.
- `supervisorSelfie()`: supervisor selfie serve.
- `internshipCertificate()`: certificate serve.
- `fileResponse()`: local storage mein file exists check karta hai, warna 404.

## 9. Company Module Detail

### Company Auth

`Company\AuthController`

- `register()`: company aur user transaction mein create, email verification OTP generate, mail send, local environment mein code response mein bhi deta hai.
- `login()`: email/password check, inactive block, unverified email par OTP resend, 2FA enabled ho to 2FA code send, otherwise token issue.
- `verifyEmail()`: 6 digit OTP verify, email verified_at set, notification create.
- `resendVerificationCode()`: OTP regenerate.
- `verifyTwoFactor()`: 2FA code verify aur token issue.
- `me()`: user with company.
- `logout()`: token delete.

### Company Dashboard

`Company\DashboardController`

Company status, trust level, HR users count, jobs overview aur internships overview return karta hai.

### Company Profile

`Company\ProfileController`

- `show()`: company with verification documents and supervisors.
- `update()`: logo/cover upload public disk par, about/industry/company_size/website/office_locations/working_hours update. String JSON values decode karta hai.

### Company Documents

`Company\DocumentController`

- `store()`: single `file + type` ya multiple `secp`, `ntn`, `address` files accept karta hai. Fake document detection run karta hai, documents update/create karta hai, company status pending karta hai, notification and activity log create karta hai.
- `file()`: sirf own company document allow, local storage se secure response.

### Supervisor Submission

`Company\SupervisorController`

- `store()`: one supervisor per company upsert karta hai. Name, email, CNIC, selfie required. Selfie local storage mein save hoti hai. Status pending ho jata hai.

### HR User Management

`Company\HrUserController`

- `index()`: company ke HR users list.
- `store()`: HR user create with role `hr`, type default `recruiter`, status active.
- `update()`: own company HR user name/email/password/hr_type/status update.
- `deactivate()`: HR inactive and tokens delete.

### Hiring Overview

`Company\HiringOverviewController`

Company owner ko read-only jobs overview deta hai. Jobs ke total/applied/shortlisted/rejected/hired counts aur overall pipeline stats return karta hai. Permissions explicitly false hain: company owner job/assessment create nahi kar sakta.

### Company Notifications

`Company\NotificationController`

Company/user notifications list/filter by type/read status, mark one read, mark all read.

### Account Settings

`Company\AccountSettingsController`

Account details show, password change with token refresh, aur two factor enable/disable.

## 10. HR Module Detail

### HR Auth

`Hr\AuthController`

HR email/password validate karta hai, role `hr` check karta hai, inactive block karta hai, company approved requirement middleware enforce karta hai, token issue karta hai. `me()` current user with company return karta hai, `logout()` token delete karta hai.

### HR Dashboard

`Hr\DashboardController`

HR ke jobs, applications, pending/shortlisted/rejected/hired stats, live jobs, assessments etc. calculate karta hai.

### HR Jobs

`Hr\JobController`

- `index()`: current HR and company ke jobs.
- `show()`: ownership check ke baad job detail.
- `store()`: company approved honi chahiye; title/type/work mode/location/skills/description/candidates_required/hiring_urgency/assessment_id validate; job status `pending_approval`; admin approval ke baad live hota hai.
- `update()`: ownership check; company approved; agar applications exist hain to assessment change block; data update kar ke status `pending_approval` set.
- `deactivate()`: job `closed`.
- `authorizeJobOwnership()`: HR sirf apni company aur apni job access kar sakta hai.

### HR Assessments

`Hr\AssessmentController`

- `index()`: HR ke assessments list with questions/submissions count.
- `show()`: assessment detail with questions and candidate submissions.
- `store()`: title, time limit, one attempt, auto submit, randomization, cooldown, status create.
- `addQuestion()`: assessment locked na ho to question add.
- `update()`: assessment update; locked assessment ko edit block karta hai.
- `authorizeAssessmentOwnership()`: HR ownership enforce.
- `ensureAssessmentNotLocked()`: locked assessment modification block.

### HR Applications

`Hr\ApplicationController`

- `index()`: HR-owned jobs ki applications list.
- `show()`: candidate, job, task, interview, score breakdown, skill match, plagiarism report, anti-cheat logs, portfolio links, candidate activity logs.
- `shortlist()`: sirf `passed`, `second_task_assigned`, `interview_scheduled` statuses se `shortlisted`.
- `reject()`: rejection reason ke sath rejected.
- `assignTask()`: shortlisted candidate ko second round task assign; application status `second_task_assigned`.
- `reviewTask()`: task passed/failed; latest task submission reviewed.
- `scheduleInterview()`: shortlisted/second_task_assigned/interview_scheduled status allowed, agar task exist karta hai to passed hona zaroori, future date/time required, application `interview_scheduled`.
- `completeInterview()`: interview scheduled application ka result passed/failed; interview record mein status/result/feedback update karne ki koshish karta hai; application status `interview_passed` ya `rejected`.
- `hire()`: candidate ko hired karta hai, offer letter optional upload, notification, activity log, hiring details response. Code job ko `filled` status par set karta hai jab hired count candidates_required se equal/greater ho.
- `authorizeApplicationOwnership()`: HR sirf apni job ki applications access kar sakta hai.

## 11. Candidate Module Detail

### Candidate Auth

`Candidate\AuthController`

- `register()`: candidate create, skills comma string ya array dono handle, education/experience save, OTP send, notification create.
- `login()`: email/password check, unverified email par OTP resend, inactive block, token issue.
- `verifyEmail()`: OTP verify and email verified.
- `resendVerificationCode()`: OTP resend.
- `me()`: user with jobApplications/internships count.
- `logout()`: token delete.

### Candidate Dashboard

`Candidate\DashboardController`

Applied jobs count, assessment pending/in_progress/submitted/results, verified internships, notifications count, recent applications, task submissions count return karta hai.

### Candidate Profile

`Candidate\ProfileController`

- `show()`: candidate profile with internships and submissions, plus score breakdown.
- `update()`: skills/education/experience update, score recalculate.

### Candidate Applications

`Candidate\ApplicationController`

- `jobs()`: live jobs with assessment list; already_applied flag add karta hai.
- `apply()`: live job and assessment availability check; duplicate apply block; application status `assessment_pending`; notification and activity log.
- `index()`: own applications with job/task/interview.
- `show()`: own application detail plus latest assessment submission/session.

### Candidate Assessment

`Candidate\AssessmentController`

- `start()`: application ownership check, assessment find, existing in-progress session handle. Same device/browser/IP/tab par resume allow. Different device ya tab par violation log aur block. New session create with session token, expiry time, device info.
- `logEvent()`: anti-cheat event validate and log. 5 violations par auto-submit trigger.
- `submit()`: session validate, submission find, timeout/violation based auto flag calculate, `ProcessSubmissionJob::dispatchSync()` run.
- `logViolation()`: assessment log create, session warning/violation increment, 3 warnings par submission suspicious, application anti_cheat_logs update.
- `autoSubmitExpiredSession()`: expired/security session ko process job se submit.

### Candidate Task

`Candidate\TaskController`

Candidate apne assigned task par file submit karta hai. Task ownership, assigned status, deadline, duplicate submission check hota hai. File save hoti hai, `task_submissions` record create hota hai, task `submitted` hota hai.

### Candidate Internships

`Candidate\InternshipController`

- `index()`: own internships list.
- `store()`: company name, duration, supervisor email, certificate required. Certificate store, SHA256 hash, metadata text create. Duplicate certificate service, metadata similarity, fake document detection run. Fraud logs create ho sakte hain. Internship pending create hoti hai. Supervisor email ko verification request mail hoti hai.

### Candidate Notifications

`Candidate\NotificationController`

Own notifications list, one read, all read.

## 12. Public Jobs Module

`Public\JobController`

- `index()`: live jobs list with filters search/location/type/work_mode/hiring_urgency; urgency and latest sorting; public-friendly transformed response.
- `show()`: live job detail with company public info and application stats.
- `categories()`: popular skills, job type distribution, work mode distribution, top locations.
- `featuredCompanies()`: approved companies with live jobs.
- `statistics()`: total live jobs, approved companies, new jobs this week, urgent jobs.
- `search()`: advanced q/skills/experience/sort filters.
- `truncateDescription()`: preview text short karta hai.
- `formatJobType()`, `formatWorkMode()`: display labels.

Important note: Controller ready hai lekin route nesting mismatch ke wajah se public jobs frontend ko API path issue aa sakta hai.

## 13. Services

### ActivityLogger

Central audit logger. `log(action, module, description, request, userId)` activity_logs table mein user, action, module, description, IP aur user agent save karta hai.

### CandidateProfileScoreService

Candidate rating calculate karta hai. Profile completeness, skills, education, experience, internships, assessments waghera ko score breakdown mein convert karta hai aur candidate rating update karta hai.

### AssessmentAttemptService

Assessment attempt start/submit rules handle karta hai. Candidate/application ownership check, assessment lookup, one attempt/cooldown logic, submission create/update karta hai.

### ProcessSubmissionJob

Assessment submit hone ke baad score, plagiarism report, cheating flag, application status, session status, notification aur fraud log process karta hai.

Scoring logic:

- Total marks questions se.
- MCQ answer exact match ho to marks.
- Coding/case/file expected answer se `similar_text` 70% ya zyada ho to marks.
- Score percentage calculate.
- 60 ya zyada score pass.
- Auto-submitted status separate.
- Duplicate exact answer payload detect ho to plagiarism report `Duplicate answer pattern detected`.
- 5+ violations cheating, 3+ warnings ya plagiarism suspicious.

### AdvancedSkillMatchingService

Job skills aur candidate skills compare karta hai. Skill normalization, synonyms, string similarity, experience bonus, education bonus, portfolio bonus, breakdown, job applications ke skill match recalculation, skill weights/synonyms update methods provide karta hai.

### AdvancedNLPPlagiarismService

Assessment answers ke liye advanced plagiarism detection service. Exact, semantic, n-gram/phrase/structure similarity style analysis karta hai, suspicious phrases/patterns detect karta hai, detailed report store/return kar sakta hai. Current main submission flow simple duplicate payload check use kar raha hai; advanced service future/extra analysis ke liye available hai.

### AdvancedDeviceDetectionService

Assessment device activity monitor karta hai:

- Device fingerprint track.
- Multiple active sessions/devices detect.
- Rapid tab switching detect.
- Window focus/blur patterns detect.
- IP/screen/timezone inconsistencies detect.
- Suspicion score and recommendations generate.
- Session warning/violation counts update.
- Candidate device activity report and cache cleanup methods provide.

Note: Service code session par `cheating_flag` update karne ki koshish karta hai, lekin `assessment_sessions` migration mein `cheating_flag` field nahi dikhti.

### FakeDocumentDetectionService

Uploaded documents fake/suspicious hain ya nahi check karta hai.

Checks:

- Valid MIME type.
- Suspicious file size.
- PDF structure/patterns.
- Image dimensions/aspect ratio.
- Filename/metadata suspicious pattern.
- Template/placeholder text.
- Confidence score.
- Threshold cross ho to fraud log create.

### DuplicateCertificateDetectionService

Internship certificate duplicates detect karta hai:

- File hash exact duplicate.
- Similar certificate text.
- PDF basic text extraction.
- Image OCR placeholder warning, real OCR implemented nahi.
- Metadata similarity: company_name, duration, supervisor_email.
- Similarity threshold update.
- Fraud log create.

## 14. Frontend Behavior

Frontend Blade pages static shells hain. Har page page-specific JavaScript se `THR.api()` helper call karta hai.

Global JS `public/assets/js/app.js`:

- Auth token/role/user localStorage helper.
- `api(path, opts)`: `/api` prefix ke sath fetch.
- Error handling and 401 redirect.
- Toast notifications.
- HTML escape helper.
- Status pill helper.
- Date formatting.
- Logout binding.
- Required role auth check.
- Form fill and FormData conversion helpers.
- Secure file open helper: token ke sath file fetch, blob open.

UI pages role-wise:

- Admin: dashboard, reports, companies, supervisors, internships, users, HR monitoring, fraud logs, activity logs, jobs approval.
- Company: dashboard, profile, documents, supervisors, HR users, jobs overview, notifications, account settings.
- HR: dashboard, jobs CRUD screens, applications pipeline, assessments screens.
- Candidate: dashboard, profile, jobs, apply, applications, assessment, internships, notifications.
- Public: jobs search/list/detail modal.
- Auth: separate login/register/verify screens for admin/company/hr/candidate.

## 15. Main Business Workflows

### Company Verification Workflow

1. Company owner register karta hai.
2. Email OTP verify karta hai.
3. Company profile/documents upload karta hai.
4. Fake document detection run hoti hai.
5. Admin documents/company review karta hai.
6. Admin approve kare to company status `approved`, trust level set.
7. Approved company ke HR operations allow hote hain.

### HR User Workflow

1. Company owner HR user create karta hai.
2. HR login karta hai.
3. Middleware ensure karta hai HR active ho aur company approved ho.
4. HR assessments aur jobs create karta hai.

### Job Posting Workflow

1. HR assessment create karta hai.
2. HR job create karta hai with required assessment.
3. Job status `pending_approval`.
4. Admin approve kare to job `live`.
5. Public/candidate job listing mein visible hoti hai.
6. Admin reject kare to job `closed`.

### Candidate Apply and Assessment Workflow

1. Candidate register and email verify.
2. Candidate profile update karta hai.
3. Candidate live job apply karta hai.
4. Application status `assessment_pending`.
5. Candidate assessment start karta hai.
6. Session device/browser/IP/tab locked hoti hai.
7. Anti-cheat events log hote hain.
8. Candidate submit ya timeout/security par auto submit.
9. `ProcessSubmissionJob` score calculate karta hai.
10. Application status `passed` ya `failed`.
11. Fail par cooldown_until set hota hai.
12. Suspicious/cheating par fraud log create hota hai.

### HR Hiring Pipeline

1. HR passed candidate ko shortlist karta hai.
2. HR optional second round task assign karta hai.
3. Candidate task submit karta hai.
4. HR task review passed/failed karta hai.
5. HR interview schedule karta hai.
6. HR interview complete karta hai.
7. HR candidate hire karta hai.
8. Candidate notification receive karta hai.

### Internship Verification Workflow

1. Candidate internship certificate submit karta hai.
2. Certificate hash/text save hota hai.
3. Duplicate and fake document detection run hoti hai.
4. Supervisor ko verification mail jata hai.
5. Admin internship verify/partial/reject karta hai.
6. Internship status candidate profile/score mein affect kar sakta hai.

### Fraud Monitoring Workflow

Fraud logs multiple places se create ho sakte hain:

- Fake company/internship document.
- Duplicate internship certificate/metadata.
- Suspicious assessment pattern.
- Cheating detected during assessment.

Admin fraud log ko flag, resolve, ya confirmed fraud mark kar sakta hai.

## 16. Tests Aur Seeders

Tests:

- `tests/Feature/CandidateModuleTest.php`
- `tests/Feature/HrRemainingRulesTest.php`
- `tests/Feature/ExampleTest.php`
- `tests/Unit/ExampleTest.php`

Seeders:

- `SuperAdminSeeder`
- `TestUserSeeder`
- `UserTestSeeder`
- `CompanyTestSeeder`
- `InternshipTestSeeder`
- `FraudLogTestSeeder`
- `HrMonitoringTestSeeder`
- `DatabaseSeeder`

Composer test script:

- `composer test` pehle config clear karta hai phir `php artisan test`.

## 17. Important Validations And Rules

- Company register: unique email in users and companies.
- Company login: email verified required, 2FA optional.
- Candidate register: password min 8, email unique, optional skills/education/experience/portfolio validations.
- HR jobs: only verified company can create/update jobs.
- Job assessment cannot change after candidates apply.
- Candidate apply: job live and assessment required.
- Assessment start: application ownership, device lock, tab lock, expiry handling.
- Assessment events: allowed event types list fixed hai.
- Task submit: own task, assigned status, deadline not passed, duplicate submission block.
- HR shortlist: candidate must pass assessment first.
- Interview schedule: future date/time, task passed if task exists.
- Secure files: admin/company file endpoints storage existence and ownership check karte hain.

## 18. Known Issues / Gaps Jo Notice Hue

Yeh points project explain karte waqt mention karna useful hoga:

- Public jobs route mismatch: `routes/api.php` mein public jobs group candidate prefix ke andar nested lag raha hai, lekin frontend `/api/public/jobs` call karta hai.
- `Hr\ApplicationController::hire()` job status `filled` set karta hai, lekin `hr_jobs.status` enum migration mein `filled` value nahi dikhti.
- `Hr\ApplicationController::completeInterview()` interview model par `status`, `result`, `feedback` update karta hai, lekin `interviews` migration mein yeh fields nahi dikhti.
- `AdvancedDeviceDetectionService` session par `cheating_flag` update karta hai, lekin `assessment_sessions` table mein yeh field nahi dikhti.
- `HrJob` public controller `jobApplications()` relation call karta hai, lekin model mein relation ka naam `applications()` hai. Is se `Public\JobController::show()` mein error aa sakta hai.
- `Public\JobController::show()` salary_range, benefits, application_deadline return karta hai, lekin `hr_jobs` migration/model fillable mein yeh fields visible nahi.
- `Interview` model fillable mein sirf `application_id`, `date`, `time`, `mode` hai; result/status/feedback missing.
- Advanced plagiarism/device/skill services kaafi rich hain, lekin kuch services main controller flow mein fully integrate nahi dikh rahi.
- `ActivityLog` aur `AssessmentLog` immutable banaye gaye hain, yeh audit ke liye acha hai.

## 19. Project Ki Strengths

- Clear role separation: admin, company, HR, candidate.
- Sanctum token based APIs.
- Company verification aur trust level concept.
- Admin approvals before job live.
- Candidate assessment with anti-cheat session lock.
- Fraud log centralization.
- Activity audit logs.
- Company owner read-only hiring overview.
- Candidate profile scoring services.
- Internship verification and duplicate/fake document detection.
- Notifications throughout important workflows.

## 20. Simple Explanation Kisi Client/Teacher Ko Dene Ke Liye

TalentHR ek hiring management aur verification platform hai. Isme company pehle register hoti hai, apne documents upload karti hai, admin us company ko verify karta hai. Approved company apne HR users create kar sakti hai. HR assessments aur jobs create karta hai, lekin job live hone se pehle admin approval required hota hai. Candidate register karke jobs apply karta hai, assessment deta hai, system anti-cheat events track karta hai, score calculate hota hai aur HR candidate ko shortlist, task, interview aur hire stages se process karta hai. Candidate internships bhi submit kar sakta hai jinko duplicate/fake detection aur admin verification se guzara jata hai. System har important action ka audit log rakhta hai aur suspicious activity fraud logs mein show karta hai.

## 21. One Line Project Summary

TalentHR ek Laravel based secure hiring platform hai jo company verification, HR job posting, candidate assessment, anti-cheat monitoring, internship verification, fraud detection, notifications aur admin governance ko ek system mein combine karta hai.
