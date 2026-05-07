@extends('layouts.guest')
@section('title', 'Candidate Registration')
@section('content')
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <h3 class="fw-bold">Create Candidate Account</h3>
                    <p class="text-muted">Join our talent platform and find your dream job</p>
                </div>

                <form id="registerForm">
                    <div class="row g-3">
                        <!-- Personal Information -->
                        <div class="col-12">
                            <h5 class="border-bottom pb-2 mb-3">Personal Information</h5>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Full Name *</label>
                            <input type="text" class="form-control" name="name" required>
                            <div class="invalid-feedback">Please enter your full name</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email Address *</label>
                            <input type="email" class="form-control" name="email" required>
                            <div class="invalid-feedback">Please enter a valid email address</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="phone" placeholder="+92 300 1234567">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password *</label>
                            <input type="password" class="form-control" name="password" required>
                            <div class="invalid-feedback">Password must be at least 8 characters</div>
                            <small class="text-muted">Minimum 8 characters</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm Password *</label>
                            <input type="password" class="form-control" name="password_confirmation" required>
                            <div class="invalid-feedback">Passwords must match</div>
                        </div>

                        <!-- Professional Information -->
                        <div class="col-12">
                            <h5 class="border-bottom pb-2 mb-3 mt-4">Professional Information</h5>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Skills *</label>
                            <input type="text" class="form-control" name="skills" placeholder="e.g. PHP, Laravel, React, JavaScript" required>
                            <small class="text-muted">Separate skills with commas</small>
                            <div class="invalid-feedback">Please enter at least one skill</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Education *</label>
                            <textarea class="form-control" name="education" rows="3" placeholder="e.g. BS Computer Science from University Name" required></textarea>
                            <small class="text-muted">Your educational background and qualifications</small>
                            <div class="invalid-feedback">Please enter your education details</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Experience *</label>
                            <textarea class="form-control" name="experience" rows="4" placeholder="e.g. 2 years of web development experience with Laravel and React..." required></textarea>
                            <small class="text-muted">Describe your work experience and achievements</small>
                            <div class="invalid-feedback">Please enter your experience details</div>
                        </div>

                        <!-- Portfolio Links -->
                        <div class="col-12">
                            <h5 class="border-bottom pb-2 mb-3 mt-4">Portfolio Links</h5>
                        </div>
                        <div class="col-12">
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
                        </div>

                        <!-- Terms and Conditions -->
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="#" onclick="showTerms()">Terms and Conditions</a> and <a href="#" onclick="showPrivacy()">Privacy Policy</a> *
                                </label>
                                <div class="invalid-feedback">You must agree to the terms</div>
                            </div>
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-primary w-100" id="registerBtn">
                                <span class="spinner-border spinner-border-sm d-none" id="spinner"></span>
                                Create Account
                            </button>
                        </div>

                        <div class="col-12 text-center">
                            <p class="mb-0">Already have an account? <a href="/candidate/login">Sign in here</a></p>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Terms Modal -->
<div class="modal fade" id="termsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Terms and Conditions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6>1. Account Registration</h6>
                <p>You must provide accurate and complete information when creating your account. You are responsible for maintaining the confidentiality of your account credentials.</p>
                
                <h6>2. Platform Usage</h6>
                <p>Our platform connects talented candidates with hiring companies. You agree to use the platform for legitimate job seeking purposes only.</p>
                
                <h6>3. Data Privacy</h6>
                <p>We respect your privacy and handle your personal data in accordance with our Privacy Policy. Your information will only be shared with potential employers with your consent.</p>
                
                <h6>4. Content Accuracy</h6>
                <p>You certify that all information provided in your profile is accurate and truthful. Misrepresentation may result in account termination.</p>
                
                <h6>5. Prohibited Activities</h6>
                <p>You may not use the platform for fraudulent activities, spamming, or any illegal purposes.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Privacy Modal -->
<div class="modal fade" id="privacyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Privacy Policy</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6>Information We Collect</h6>
                <p>We collect personal information including your name, email, phone, skills, education, and work experience to help match you with suitable job opportunities.</p>
                
                <h6>How We Use Your Information</h6>
                <ul>
                    <li>To create and maintain your candidate profile</li>
                    <li>To match you with relevant job opportunities</li>
                    <li>To communicate with you about job applications</li>
                    <li>To improve our platform services</li>
                </ul>
                
                <h6>Data Sharing</h6>
                <p>Your profile information is shared with potential employers only when you apply for their positions. We never sell your personal data to third parties.</p>
                
                <h6>Data Security</h6>
                <p>We implement appropriate security measures to protect your personal information from unauthorized access.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
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

function showTerms() {
    new bootstrap.Modal(document.getElementById('termsModal')).show();
}

function showPrivacy() {
    new bootstrap.Modal(document.getElementById('privacyModal')).show();
}

// Form validation and submission
document.getElementById('registerForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    // Clear previous validation
    const form = e.target;
    form.classList.remove('was-validated');
    
    // Basic validation
    let isValid = true;
    
    // Check required fields
    const requiredFields = ['name', 'email', 'password', 'password_confirmation', 'skills', 'education', 'experience'];
    requiredFields.forEach(fieldName => {
        const field = form.querySelector(`[name="${fieldName}"]`);
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    // Check password match
    const password = form.password.value;
    const passwordConfirm = form.password_confirmation.value;
    if (password !== passwordConfirm) {
        form.password_confirmation.classList.add('is-invalid');
        isValid = false;
    } else {
        form.password_confirmation.classList.remove('is-invalid');
    }
    
    // Check password length
    if (password.length < 8) {
        form.password.classList.add('is-invalid');
        isValid = false;
    } else {
        form.password.classList.remove('is-invalid');
    }
    
    // Check terms agreement
    if (!form.terms.checked) {
        form.terms.classList.add('is-invalid');
        isValid = false;
    } else {
        form.terms.classList.remove('is-invalid');
    }
    
    if (!isValid) {
        form.classList.add('was-validated');
        return;
    }
    
    // Prepare form data
    const formData = THR.formData(form);
    
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
    formData.portfolio_links = portfolioLinks;
    
    // Process skills
    if (formData.skills) {
        formData.skills = formData.skills.split(',').map(s => s.trim()).filter(Boolean);
    }
    
    // Remove confirmation password
    delete formData.password_confirmation;
    delete formData.terms;
    
    // Show loading
    const spinner = document.getElementById('spinner');
    const registerBtn = document.getElementById('registerBtn');
    spinner.classList.remove('d-none');
    registerBtn.disabled = true;
    
    try {
        const response = await THR.api('/candidate/register', {
            method: 'POST',
            body: formData
        });
        
        THR.toast('Registration successful! Redirecting to login...', 'success');
        
        // Redirect to login after 2 seconds
        setTimeout(() => {
            window.location.href = '/candidate/login';
        }, 2000);
        
    } catch (error) {
        THR.toast(error.message || 'Registration failed. Please try again.', 'danger');
    } finally {
        spinner.classList.add('d-none');
        registerBtn.disabled = false;
    }
});

// Real-time validation
document.getElementById('registerForm').addEventListener('input', function(e) {
    const field = e.target;
    if (field.value.trim()) {
        field.classList.remove('is-invalid');
    }
});

// Password confirmation validation
document.getElementById('registerForm').addEventListener('input', function(e) {
    if (e.target.name === 'password' || e.target.name === 'password_confirmation') {
        const password = e.target.form.password.value;
        const passwordConfirm = e.target.form.password_confirmation.value;
        
        if (passwordConfirm && password !== passwordConfirm) {
            e.target.form.password_confirmation.classList.add('is-invalid');
        } else {
            e.target.form.password_confirmation.classList.remove('is-invalid');
        }
    }
});
</script>
@endpush
