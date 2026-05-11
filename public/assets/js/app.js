/* TalentHR global helpers — vanilla JS, no build step */
(function () {
    const TOKEN_KEY = 'thr_token';
    const ROLE_KEY = 'thr_role';
    const USER_KEY = 'thr_user';

    const Auth = {
        get token() { return localStorage.getItem(TOKEN_KEY); },
        get role() { return localStorage.getItem(ROLE_KEY); },
        get user() {
            try { return JSON.parse(localStorage.getItem(USER_KEY) || 'null'); } catch (e) { return null; }
        },
        set(token, role, user) {
            if (token) localStorage.setItem(TOKEN_KEY, token);
            if (role) localStorage.setItem(ROLE_KEY, role);
            if (user) localStorage.setItem(USER_KEY, JSON.stringify(user));
        },
        clear() {
            localStorage.removeItem(TOKEN_KEY);
            localStorage.removeItem(ROLE_KEY);
            localStorage.removeItem(USER_KEY);
        },
        loginRedirect(role) {
            const map = { admin: '/admin/login', company: '/company/login', hr: '/hr/login', candidate: '/candidate/login' };
            window.location.href = map[role] || '/';
        },
    };

    async function api(path, opts = {}) {
        const headers = Object.assign({ 'Accept': 'application/json' }, opts.headers || {});
        if (Auth.token) headers['Authorization'] = 'Bearer ' + Auth.token;
        if (opts.body && !(opts.body instanceof FormData) && !headers['Content-Type']) {
            headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(opts.body);
        }
        const res = await fetch('/api' + path, Object.assign({}, opts, { headers }));
        let data = null;
        try { data = await res.json(); } catch (e) { /* ignore */ }
        if (res.status === 401) {
            Auth.clear();
            const role = (window.THR && window.THR.role) || Auth.role || 'admin';
            toast('Session expired. Please log in again.', 'danger');
            setTimeout(() => Auth.loginRedirect(role), 800);
        }
        if (!res.ok) {
            const err = new Error((data && data.message) || ('Request failed: ' + res.status));
            err.status = res.status; err.data = data;
            throw err;
        }
        return data;
    }

    function toast(msg, variant = 'primary') {
        let stack = document.getElementById('toastStack');
        if (!stack) { stack = document.createElement('div'); stack.id = 'toastStack'; document.body.appendChild(stack); }
        const el = document.createElement('div');
        el.className = `toast align-items-center text-bg-${variant} border-0 show`;
        el.role = 'alert';
        el.innerHTML = `<div class="d-flex"><div class="toast-body">${escapeHtml(msg)}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
        stack.appendChild(el);
        setTimeout(() => el.remove(), 4500);
        el.querySelector('.btn-close').addEventListener('click', () => el.remove());
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, s => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[s]));
    }

    function statusPill(status) {
        const map = {
            active: 'success', live: 'success', approved: 'success', verified: 'success', passed: 'success', resolved: 'success', read: 'secondary',
            pending: 'warning', pending_approval: 'warning', assessment_pending: 'warning', shortlisted: 'info', second_task_assigned: 'info', interview_scheduled: 'info', submitted: 'info', partial: 'warning', flagged: 'warning',
            rejected: 'danger', failed: 'danger', inactive: 'secondary', deactivated: 'secondary', fraud: 'danger', closed: 'secondary',
        };
        const v = map[status] || 'secondary';
        return `<span class="status-pill bg-${v}-subtle text-${v}-emphasis">${escapeHtml(status || 'n/a')}</span>`;
    }

    function fmtDate(s) {
        if (!s) return '—';
        try { return new Date(s).toLocaleString(); } catch (e) { return s; }
    }

    function bindLogout(role) {
        const path = `/${role}/logout`;
        document.querySelectorAll('[data-logout]').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                try { await api(path, { method: 'POST' }); } catch (e) { /* ignore */ }
                Auth.clear();
                Auth.loginRedirect(role);
            });
        });
    }

    function requireAuth(expectedRole) {
        if (!Auth.token) { Auth.loginRedirect(expectedRole); return false; }
        return true;
    }

    function fillForm(form, data) {
        if (!form || !data) return;
        Object.keys(data).forEach(k => {
            const el = form.querySelector(`[name="${k}"]`);
            if (el && data[k] !== null && data[k] !== undefined) el.value = data[k];
        });
    }

    function formData(form) {
        const fd = new FormData(form);
        const obj = {};
        fd.forEach((v, k) => { obj[k] = v; });
        return obj;
    }

    /**
     * Securely fetch a protected API file (PDF/image) using the bearer token,
     * then open the resulting blob in a new browser tab. Plain <a target="_blank">
     * cannot send the Authorization header, so the API rejects with 401 which
     * Laravel tries to redirect to a non-existent `login` route.
     */
    async function openFile(path) {
        try {
            const headers = { 'Accept': '*/*' };
            if (Auth.token) headers['Authorization'] = 'Bearer ' + Auth.token;
            const url = path.startsWith('http') ? path : (path.startsWith('/api') ? path : '/api' + path);
            const res = await fetch(url, { headers });
            if (!res.ok) {
                let msg = 'File not available (' + res.status + ')';
                try { const j = await res.json(); if (j && j.message) msg = j.message; } catch (e) {}
                throw new Error(msg);
            }
            const blob = await res.blob();
            const blobUrl = URL.createObjectURL(blob);
            const win = window.open(blobUrl, '_blank');
            if (!win) toast('Pop-up blocked. Please allow pop-ups to view files.', 'warning');
            setTimeout(() => URL.revokeObjectURL(blobUrl), 60000);
        } catch (e) { toast(e.message || 'Could not open file', 'danger'); }
    }

    window.THR = { api, toast, escapeHtml, statusPill, fmtDate, Auth, bindLogout, requireAuth, fillForm, formData, openFile };
})();
