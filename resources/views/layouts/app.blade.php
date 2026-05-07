<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'TalentHR') - TalentHR</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('assets/css/app.css') }}" rel="stylesheet">
    @stack('head')
</head>
<body>
@php
    $role = $role ?? 'admin';
    $brand = match($role) {
        'admin' => ['label' => 'Admin Console', 'home' => '/admin/dashboard'],
        'company' => ['label' => 'Company Portal', 'home' => '/company/dashboard'],
        'hr' => ['label' => 'HR Workspace', 'home' => '/hr/dashboard'],
        'candidate' => ['label' => 'Candidate Hub', 'home' => '/candidate/dashboard'],
        default => ['label' => 'TalentHR', 'home' => '/'],
    };
@endphp

<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
    <div class="container-fluid">
        <button class="btn btn-light me-2" type="button" id="sidebarToggle" title="Toggle navigation"><i class="bi bi-layout-sidebar-inset"></i></button>
        <a class="navbar-brand fw-bold text-primary" href="{{ $brand['home'] }}">
            <span class="brand-mark"><i class="bi bi-mortarboard-fill"></i></span>TalentHR
            <span class="text-muted fs-6 fw-normal ms-2 d-none d-md-inline">/ {{ $brand['label'] }}</span>
        </a>
        <div class="ms-auto d-flex align-items-center gap-2">
            @if(in_array($role, ['company','candidate']))
                <a href="/{{ $role }}/notifications" class="btn btn-light position-relative" title="Notifications">
                    <i class="bi bi-bell"></i>
                    <span id="notifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none">0</span>
                </a>
            @endif
            <div class="dropdown">
                <button class="btn btn-light dropdown-toggle d-flex align-items-center" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-1"></i><span id="navUserName">Account</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    @if($role === 'company')
                        <li><a class="dropdown-item" href="/company/profile"><i class="bi bi-building me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="/company/account-settings"><i class="bi bi-gear me-2"></i>Settings</a></li>
                    @elseif($role === 'candidate')
                        <li><a class="dropdown-item" href="/candidate/profile"><i class="bi bi-person me-2"></i>Profile</a></li>
                    @endif
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" data-logout><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<div class="app-shell">
    <aside class="app-sidebar" id="appSidebar">
        @include('layouts.partials.sidebar-' . $role)
    </aside>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <main class="app-main">
        @yield('content')
    </main>
</div>

<footer class="text-center text-muted small py-3 border-top bg-white">
    &copy; {{ date('Y') }} TalentHR - Secure hiring platform
</footer>

<div id="toastStack"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('assets/js/app.js') }}"></script>
<script>
    window.THR.role = @json($role);
    if (!window.THR.requireAuth(@json($role))) { /* redirected */ }
    document.addEventListener('DOMContentLoaded', () => {
        const u = window.THR.Auth.user;
        if (u) document.getElementById('navUserName').textContent = u.name || u.email || 'Account';
        window.THR.bindLogout(@json($role));

        const tog = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('appSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const closeMobileSidebar = () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
        };
        if (localStorage.getItem('thr_sidebar_collapsed') === '1') document.body.classList.add('sidebar-collapsed');
        if (tog) tog.addEventListener('click', () => {
            if (window.matchMedia('(max-width: 992px)').matches) {
                sidebar.classList.toggle('open');
                overlay.classList.toggle('show', sidebar.classList.contains('open'));
            } else {
                document.body.classList.toggle('sidebar-collapsed');
                localStorage.setItem('thr_sidebar_collapsed', document.body.classList.contains('sidebar-collapsed') ? '1' : '0');
            }
        });
        if (overlay) overlay.addEventListener('click', closeMobileSidebar);
        sidebar.querySelectorAll('.nav-link').forEach(a => a.addEventListener('click', closeMobileSidebar));

        const path = window.location.pathname;
        document.querySelectorAll('.app-sidebar .nav-link').forEach(a => {
            if (a.getAttribute('href') === path) a.classList.add('active');
        });
        @if(in_array($role, ['company','candidate']))
            (async () => {
                try {
                    const data = await window.THR.api('/{{ $role }}/notifications');
                    const items = data.data || data || [];
                    const unread = items.filter(n => !n.read_at).length;
                    if (unread > 0) {
                        const b = document.getElementById('notifBadge');
                        b.textContent = unread; b.classList.remove('d-none');
                    }
                } catch (e) { /* ignore */ }
            })();
        @endif
    });
</script>
@stack('scripts')
</body>
</html>
