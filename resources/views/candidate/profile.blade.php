@extends('layouts.app', ['role' => 'candidate'])
@section('title', 'My Profile')
@section('content')
<div class="page-header"><div><h1>My Profile</h1><p>Verified candidate profile & score</p></div></div>
<div class="row g-3">
    <div class="col-lg-8"><div class="card"><div class="card-body">
        <form id="profileForm">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" readonly></div>
                <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" name="email" readonly></div>
                <div class="col-12"><label class="form-label">Skills (comma separated)</label><input class="form-control" name="skills" placeholder="e.g. PHP, Laravel, React"></div>
                <div class="col-12"><label class="form-label">Education</label><textarea class="form-control" name="education" rows="3"></textarea></div>
                <div class="col-12"><label class="form-label">Experience</label><textarea class="form-control" name="experience" rows="4"></textarea></div>
                <div class="col-12">
                    <label class="form-label">Portfolio Links</label>
                    <div id="portfolioContainer" class="mb-3">
                        <div class="row g-2 mb-2" data-portfolio-index="0">
                            <div class="col-md-4">
                                <input type="text" class="form-control" placeholder="Portfolio Title" name="portfolio[0][title]">
                            </div>
                            <div class="col-md-6">
                                <input type="url" class="form-control" placeholder="https://example.com" name="portfolio[0][url]">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="removePortfolio(this)" style="display: none;">Remove</button>
                            </div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="addPortfolio()">+ Add Portfolio Link</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addCommonPortfolioLinks()">Add Common Links</button>
                    </div>
                    <div id="portfolioPreview" class="mb-3"></div>
                </div>
            </div>
            <button class="btn btn-primary mt-3">Save profile</button>
        </form>
    </div></div></div>
    <div class="col-lg-4"><div class="card"><div class="card-header">Score breakdown</div><div class="card-body" id="scoreBox">—</div></div></div>
</div>
@push('scripts')
<script>
async function load() {
    try {
        const r = await THR.api('/candidate/profile');
        const p = r.profile || r;
        const f = document.getElementById('profileForm');
        f.name.value = p.name || '';
        f.email.value = p.email || '';
        f.skills.value = Array.isArray(p.skills) ? p.skills.join(', ') : (p.skills || '');
        f.education.value = p.education || '';
        f.experience.value = p.experience || '';
        const s = r.score_breakdown || {};
        document.getElementById('scoreBox').innerHTML = Object.keys(s).length ? Object.entries(s).map(([k,v]) => `<div class="d-flex justify-content-between border-bottom py-2"><span>${THR.escapeHtml(k)}</span><span class="fw-semibold">${THR.escapeHtml(JSON.stringify(v))}</span></div>`).join('') : '<p class="text-muted mb-0">No score yet</p>';
    } catch (e) { THR.toast(e.message, 'danger'); }
}
// Portfolio management
let portfolioIndex = 1;
function addPortfolio() {
    const container = document.getElementById('portfolioContainer');
    const newPortfolio = document.createElement('div');
    newPortfolio.className = 'row g-2 mb-2';
    newPortfolio.setAttribute('data-portfolio-index', portfolioIndex);
    newPortfolio.innerHTML = `
        <div class="col-md-4">
            <input type="text" class="form-control" placeholder="Portfolio Title" name="portfolio[${portfolioIndex}][title]">
        </div>
        <div class="col-md-6">
            <input type="url" class="form-control" placeholder="https://example.com" name="portfolio[${portfolioIndex}][url]">
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removePortfolio(this)">Remove</button>
        </div>
    `;
    container.appendChild(newPortfolio);
    portfolioIndex++;
    updatePortfolioRemoveButtons();
}

function removePortfolio(button) {
    button.closest('[data-portfolio-index]').remove();
    updatePortfolioRemoveButtons();
    updatePortfolioPreview();
}

function addCommonPortfolioLinks() {
    const commonLinks = [
        { title: 'GitHub', url: 'https://github.com/username' },
        { title: 'LinkedIn', url: 'https://linkedin.com/in/username' },
        { title: 'Portfolio Website', url: 'https://yourwebsite.com' },
        { title: 'Behance', url: 'https://behance.net/username' },
        { title: 'Dribbble', url: 'https://dribbble.com/username' }
    ];

    commonLinks.forEach(link => {
        addPortfolio();
        const lastIndex = portfolioIndex - 1;
        const lastRow = document.querySelector(`[data-portfolio-index="${lastIndex}"]`);
        lastRow.querySelector(`input[name="portfolio[${lastIndex}][title]"]`).value = link.title;
        lastRow.querySelector(`input[name="portfolio[${lastIndex}][url]"]`).value = link.url;
    });
}

function updatePortfolioRemoveButtons() {
    const buttons = document.querySelectorAll('#portfolioContainer button');
    buttons.forEach(btn => {
        btn.style.display = buttons.length > 1 ? 'block' : 'none';
    });
}

function updatePortfolioPreview() {
    const previewContainer = document.getElementById('portfolioPreview');
    const portfolioItems = [];
    
    document.querySelectorAll('#portfolioContainer [data-portfolio-index]').forEach(row => {
        const index = row.getAttribute('data-portfolio-index');
        const title = row.querySelector(`input[name="portfolio[${index}][title]"]`).value;
        const url = row.querySelector(`input[name="portfolio[${index}][url]"]`).value;
        
        if (title && url) {
            portfolioItems.push({ title, url });
        }
    });

    if (portfolioItems.length > 0) {
        const previewHtml = portfolioItems.map(item => 
            `<div class="d-flex justify-content-between align-items-center p-2 border rounded mb-1">
                <div>
                    <strong>${THR.escapeHtml(item.title)}</strong><br>
                    <small class="text-muted">${THR.escapeHtml(item.url)}</small>
                </div>
                <a href="${item.url}" target="_blank" class="btn btn-sm btn-outline-primary">Visit</a>
            </div>`
        ).join('');
        
        previewContainer.innerHTML = `<h6>Portfolio Preview:</h6>${previewHtml}`;
    } else {
        previewContainer.innerHTML = '';
    }
}

// Add input event listeners for live preview
document.addEventListener('input', function(e) {
    if (e.target.name && e.target.name.includes('portfolio')) {
        updatePortfolioPreview();
    }
});

load();
document.getElementById('profileForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = THR.formData(e.target);
    delete data.name; delete data.email;
    if (data.skills) data.skills = data.skills.split(',').map(s=>s.trim()).filter(Boolean);
    
    // Process portfolio data
    const portfolioLinks = [];
    document.querySelectorAll('#portfolioContainer [data-portfolio-index]').forEach(row => {
        const index = row.getAttribute('data-portfolio-index');
        const title = row.querySelector(`input[name="portfolio[${index}][title]"]`).value;
        const url = row.querySelector(`input[name="portfolio[${index}][url]"]`).value;
        
        if (title && url) {
            portfolioLinks.push({ title, url });
        }
    });
    data.portfolio_links = portfolioLinks;
    
    try { 
        await THR.api('/candidate/profile', { method: 'PUT', body: data }); 
        THR.toast('Profile saved successfully','success'); 
        load(); 
    }
    catch (err) { THR.toast(err.message, 'danger'); }
});
</script>
@endpush
@endsection
