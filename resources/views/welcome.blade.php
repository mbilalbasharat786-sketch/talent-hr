<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TalentHR · Secure Hiring Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('assets/css/app.css') }}" rel="stylesheet">
    <style>
        .hero { background: linear-gradient(135deg,#1e3a8a 0%,#2563eb 100%); color: #fff; padding: 5rem 0; }
        .role-card { transition: transform .15s, box-shadow .15s; cursor: pointer; }
        .role-card:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(0,0,0,.1); }
        .role-icon { width: 60px; height: 60px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.75rem; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-transparent position-absolute w-100" style="z-index:10;">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/"><i class="bi bi-mortarboard-fill me-1"></i>TalentHR</a>
    </div>
</nav>
<section class="hero">
    <div class="container text-center">
        <h1 class="display-4 fw-bold">Hire smarter. Verify confidently.</h1>
        <p class="lead opacity-75 mt-3">Anti-fraud hiring pipeline with verified internships, secure assessments, and full audit trails.</p>
        <div class="mt-4 d-flex justify-content-center gap-2 flex-wrap">
            <a href="/candidate/register" class="btn btn-light btn-lg"><i class="bi bi-person-plus me-1"></i> Candidate Register</a>
            <a href="/company/register" class="btn btn-outline-light btn-lg"><i class="bi bi-building me-1"></i> Register Company</a>
        </div>
    </div>
</section>
<section class="container py-5">
    <h2 class="text-center mb-4">Choose your portal</h2>
    <div class="row g-4">
        <div class="col-md-6 col-lg-3">
            <a href="/candidate/login" class="text-decoration-none">
                <div class="card role-card h-100 p-4 text-center">
                    <div class="role-icon bg-primary-subtle text-primary mx-auto"><i class="bi bi-person-circle"></i></div>
                    <h5 class="mt-3">Candidate</h5>
                    <p class="text-muted small mb-0">Apply for jobs, take assessments, build your verified profile.</p>
                </div>
            </a>
        </div>
        <div class="col-md-6 col-lg-3">
            <a href="/company/login" class="text-decoration-none">
                <div class="card role-card h-100 p-4 text-center">
                    <div class="role-icon bg-success-subtle text-success mx-auto"><i class="bi bi-building"></i></div>
                    <h5 class="mt-3">Company Owner</h5>
                    <p class="text-muted small mb-0">Manage your company, supervisors, HR team, and verifications.</p>
                </div>
            </a>
        </div>
        <div class="col-md-6 col-lg-3">
            <a href="/hr/login" class="text-decoration-none">
                <div class="card role-card h-100 p-4 text-center">
                    <div class="role-icon bg-info-subtle text-info mx-auto"><i class="bi bi-people"></i></div>
                    <h5 class="mt-3">HR</h5>
                    <p class="text-muted small mb-0">Post jobs, screen applicants, run assessments and interviews.</p>
                </div>
            </a>
        </div>
        <div class="col-md-6 col-lg-3">
            <a href="/admin/login" class="text-decoration-none">
                <div class="card role-card h-100 p-4 text-center">
                    <div class="role-icon bg-warning-subtle text-warning mx-auto"><i class="bi bi-shield-check"></i></div>
                    <h5 class="mt-3">Super Admin</h5>
                    <p class="text-muted small mb-0">Verify companies, monitor fraud, manage user trust levels.</p>
                </div>
            </a>
        </div>
    </div>
</section>
<footer class="text-center text-muted small py-4 border-top bg-white">
    &copy; {{ date('Y') }} TalentHR
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
