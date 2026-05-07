@extends('layouts.guest')
@section('title', 'Browse Jobs')
@section('content')
<div class="container-fluid py-4">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="text-center">
                <h1 class="fw-bold mb-3">Find Your Dream Job</h1>
                <p class="lead text-muted">Browse through available positions from top companies</p>
            </div>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form id="searchForm" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Search Jobs</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" id="searchInput" placeholder="Job title, skills, or keywords...">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Location</label>
                            <select class="form-select" id="locationFilter">
                                <option value="">All Locations</option>
                                <option value="remote">Remote</option>
                                <option value="onsite">On-site</option>
                                <option value="hybrid">Hybrid</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Job Type</label>
                            <select class="form-select" id="typeFilter">
                                <option value="">All Types</option>
                                <option value="full_time">Full Time</option>
                                <option value="part_time">Part Time</option>
                                <option value="contract">Contract</option>
                                <option value="internship">Internship</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-funnel"></i> Apply Filters
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Filters Display -->
    <div class="row mb-3" id="activeFilters" style="display: none;">
        <div class="col-12">
            <div class="d-flex align-items-center flex-wrap">
                <span class="me-2">Active filters:</span>
                <div id="filterTags"></div>
                <button class="btn btn-sm btn-outline-secondary ms-2" onclick="clearFilters()">Clear All</button>
            </div>
        </div>
    </div>

    <!-- Jobs Grid/List Toggle -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span id="jobCount" class="text-muted">Loading jobs...</span>
                </div>
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-primary active" id="gridViewBtn" onclick="setView('grid')">
                        <i class="bi bi-grid-3x3-gap"></i> Grid
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="listViewBtn" onclick="setView('list')">
                        <i class="bi bi-list-ul"></i> List
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Jobs Container -->
    <div class="row" id="jobsContainer">
        <!-- Jobs will be loaded here dynamically -->
    </div>

    <!-- Loading State -->
    <div class="row" id="loadingState">
        <div class="col-12 text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 text-muted">Loading available jobs...</p>
        </div>
    </div>

    <!-- Empty State -->
    <div class="row" id="emptyState" style="display: none;">
        <div class="col-12 text-center py-5">
            <i class="bi bi-search display-1 text-muted"></i>
            <h4 class="mt-3">No jobs found</h4>
            <p class="text-muted">Try adjusting your search criteria or check back later for new opportunities.</p>
            <button class="btn btn-primary" onclick="clearFilters()">Clear Filters</button>
        </div>
    </div>

    <!-- Pagination -->
    <div class="row mt-4" id="paginationContainer">
        <div class="col-12">
            <nav aria-label="Job pagination">
                <ul class="pagination justify-content-center" id="pagination">
                    <!-- Pagination will be generated dynamically -->
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- Job Detail Modal -->
<div class="modal fade" id="jobDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalJobTitle">Job Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalJobContent">
                <!-- Job details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="applyBtn">Apply Now</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let currentPage = 1;
let currentView = 'grid';
let currentFilters = {};
let totalPages = 1;
let allJobs = [];

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    loadJobs();
    setupEventListeners();
});

function setupEventListeners() {
    // Search form submission
    document.getElementById('searchForm').addEventListener('submit', function(e) {
        e.preventDefault();
        updateFilters();
        loadJobs();
    });

    // Real-time search
    let searchTimeout;
    document.getElementById('searchInput').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            updateFilters();
            loadJobs();
        }, 500);
    });

    // Filter changes
    document.getElementById('locationFilter').addEventListener('change', function() {
        updateFilters();
        loadJobs();
    });

    document.getElementById('typeFilter').addEventListener('change', function() {
        updateFilters();
        loadJobs();
    });
}

function updateFilters() {
    currentFilters = {
        search: document.getElementById('searchInput').value.trim(),
        location: document.getElementById('locationFilter').value,
        type: document.getElementById('typeFilter').value
    };

    // Update active filters display
    updateActiveFilters();
}

function updateActiveFilters() {
    const container = document.getElementById('activeFilters');
    const tagsContainer = document.getElementById('filterTags');
    
    const filters = Object.entries(currentFilters).filter(([key, value]) => value !== '');
    
    if (filters.length === 0) {
        container.style.display = 'none';
        return;
    }

    container.style.display = 'block';
    
    const tags = filters.map(([key, value]) => {
        const label = key.charAt(0).toUpperCase() + key.slice(1);
        return `<span class="badge bg-primary me-2">${label}: ${value}</span>`;
    }).join('');
    
    tagsContainer.innerHTML = tags;
}

function clearFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('locationFilter').value = '';
    document.getElementById('typeFilter').value = '';
    currentFilters = {};
    updateActiveFilters();
    currentPage = 1;
    loadJobs();
}

async function loadJobs() {
    try {
        showLoadingState();
        
        const params = new URLSearchParams({
            page: currentPage,
            ...currentFilters
        });

        const response = await fetch(`/api/public/jobs?${params}`);
        const data = await response.json();
        
        if (response.ok) {
            allJobs = data.data || [];
            totalPages = data.last_page || 1;
            
            displayJobs(allJobs);
            updatePagination();
            updateJobCount(data.total || 0);
        } else {
            throw new Error(data.message || 'Failed to load jobs');
        }
    } catch (error) {
        console.error('Error loading jobs:', error);
        showErrorState();
    }
}

function showLoadingState() {
    document.getElementById('loadingState').style.display = 'block';
    document.getElementById('jobsContainer').style.display = 'none';
    document.getElementById('emptyState').style.display = 'none';
}

function hideLoadingState() {
    document.getElementById('loadingState').style.display = 'none';
    document.getElementById('jobsContainer').style.display = 'flex';
    document.getElementById('emptyState').style.display = 'none';
}

function showErrorState() {
    hideLoadingState();
    document.getElementById('jobsContainer').innerHTML = `
        <div class="col-12">
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i>
                Failed to load jobs. Please try again later.
            </div>
        </div>
    `;
}

function updateJobCount(count) {
    const countText = count === 1 ? '1 job found' : `${count} jobs found`;
    document.getElementById('jobCount').textContent = countText;
}

function displayJobs(jobs) {
    hideLoadingState();
    
    if (jobs.length === 0) {
        document.getElementById('jobsContainer').style.display = 'none';
        document.getElementById('emptyState').style.display = 'block';
        return;
    }

    const container = document.getElementById('jobsContainer');
    
    if (currentView === 'grid') {
        container.className = 'row g-4';
        container.innerHTML = jobs.map(job => createJobCard(job)).join('');
    } else {
        container.className = 'row';
        container.innerHTML = `
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        ${jobs.map(job => createJobListItem(job)).join('')}
                    </div>
                </div>
            </div>
        `;
    }
}

function createJobCard(job) {
    const postedDate = new Date(job.created_at).toLocaleDateString();
    const urgencyColor = getUrgencyColor(job.hiring_urgency);
    
    return `
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm job-card" data-job-id="${job.id}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="card-title">${THR.escapeHtml(job.title)}</h5>
                        <span class="badge bg-${urgencyColor}">${job.hiring_urgency || 'medium'}</span>
                    </div>
                    
                    <div class="mb-3">
                        <div class="text-muted small mb-1">
                            <i class="bi bi-geo-alt"></i> ${THR.escapeHtml(job.location || 'Not specified')}
                        </div>
                        <div class="text-muted small mb-1">
                            <i class="bi bi-briefcase"></i> ${THR.escapeHtml(job.type || 'full_time')}
                        </div>
                        <div class="text-muted small">
                            <i class="bi bi-calendar"></i> Posted ${postedDate}
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <p class="card-text text-truncate" style="max-height: 3em; overflow: hidden;">
                            ${THR.escapeHtml(job.description || 'No description available')}
                        </p>
                    </div>
                    
                    ${job.skills && job.skills.length > 0 ? `
                        <div class="mb-3">
                            ${job.skills.slice(0, 3).map(skill => 
                                `<span class="badge bg-light text-dark me-1">${THR.escapeHtml(skill)}</span>`
                            ).join('')}
                            ${job.skills.length > 3 ? `<span class="text-muted small">+${job.skills.length - 3} more</span>` : ''}
                        </div>
                    ` : ''}
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <button class="btn btn-outline-primary btn-sm" onclick="showJobDetails(${job.id})">
                            <i class="bi bi-eye"></i> View Details
                        </button>
                        <button class="btn btn-primary btn-sm" onclick="quickApply(${job.id})">
                            <i class="bi bi-send"></i> Quick Apply
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function createJobListItem(job) {
    const postedDate = new Date(job.created_at).toLocaleDateString();
    const urgencyColor = getUrgencyColor(job.hiring_urgency);
    
    return `
        <div class="border-bottom pb-3 mb-3 job-list-item" data-job-id="${job.id}">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center mb-2">
                        <h5 class="mb-0 me-3">${THR.escapeHtml(job.title)}</h5>
                        <span class="badge bg-${urgencyColor}">${job.hiring_urgency || 'medium'}</span>
                    </div>
                    
                    <div class="text-muted small mb-2">
                        <span class="me-3"><i class="bi bi-geo-alt"></i> ${THR.escapeHtml(job.location || 'Not specified')}</span>
                        <span class="me-3"><i class="bi bi-briefcase"></i> ${THR.escapeHtml(job.type || 'full_time')}</span>
                        <span><i class="bi bi-calendar"></i> Posted ${postedDate}</span>
                    </div>
                    
                    <p class="mb-2 text-truncate" style="max-height: 2.4em; overflow: hidden;">
                        ${THR.escapeHtml(job.description || 'No description available')}
                    </p>
                    
                    ${job.skills && job.skills.length > 0 ? `
                        <div>
                            ${job.skills.slice(0, 5).map(skill => 
                                `<span class="badge bg-light text-dark me-1">${THR.escapeHtml(skill)}</span>`
                            ).join('')}
                            ${job.skills.length > 5 ? `<span class="text-muted small">+${job.skills.length - 5} more</span>` : ''}
                        </div>
                    ` : ''}
                </div>
                
                <div class="col-md-4 text-end">
                    <div class="d-flex flex-column align-items-end gap-2">
                        <button class="btn btn-outline-primary" onclick="showJobDetails(${job.id})">
                            <i class="bi bi-eye"></i> View Details
                        </button>
                        <button class="btn btn-primary" onclick="quickApply(${job.id})">
                            <i class="bi bi-send"></i> Apply Now
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function getUrgencyColor(urgency) {
    switch (urgency) {
        case 'high': return 'danger';
        case 'medium': return 'warning';
        case 'low': return 'success';
        default: return 'secondary';
    }
}

function setView(view) {
    currentView = view;
    
    // Update button states
    document.getElementById('gridViewBtn').classList.toggle('active', view === 'grid');
    document.getElementById('listViewBtn').classList.toggle('active', view === 'list');
    
    // Re-display jobs
    displayJobs(allJobs);
}

async function showJobDetails(jobId) {
    try {
        const response = await fetch(`/api/public/jobs/${jobId}`);
        const job = await response.json();
        
        if (response.ok) {
            displayJobModal(job);
        } else {
            throw new Error(job.message || 'Failed to load job details');
        }
    } catch (error) {
        console.error('Error loading job details:', error);
        THR.toast('Failed to load job details', 'danger');
    }
}

function displayJobModal(job) {
    const modal = new bootstrap.Modal(document.getElementById('jobDetailModal'));
    
    document.getElementById('modalJobTitle').textContent = job.title;
    
    const postedDate = new Date(job.created_at).toLocaleDateString();
    const urgencyColor = getUrgencyColor(job.hiring_urgency);
    
    document.getElementById('modalJobContent').innerHTML = `
        <div class="row">
            <div class="col-md-8">
                <div class="mb-4">
                    <h6>Job Description</h6>
                    <p>${THR.escapeHtml(job.description || 'No description available')}</p>
                </div>
                
                ${job.skills && job.skills.length > 0 ? `
                    <div class="mb-4">
                        <h6>Required Skills</h6>
                        <div>
                            ${job.skills.map(skill => 
                                `<span class="badge bg-primary me-2">${THR.escapeHtml(skill)}</span>`
                            ).join('')}
                        </div>
                    </div>
                ` : ''}
                
                ${job.education ? `
                    <div class="mb-4">
                        <h6>Education Requirements</h6>
                        <p>${THR.escapeHtml(job.education)}</p>
                    </div>
                ` : ''}
                
                ${job.experience_level ? `
                    <div class="mb-4">
                        <h6>Experience Level</h6>
                        <p>${THR.escapeHtml(job.experience_level)}</p>
                    </div>
                ` : ''}
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h6>Job Details</h6>
                        
                        <div class="mb-3">
                            <strong>Location:</strong><br>
                            ${THR.escapeHtml(job.location || 'Not specified')}
                        </div>
                        
                        <div class="mb-3">
                            <strong>Type:</strong><br>
                            <span class="badge bg-secondary">${THR.escapeHtml(job.type || 'full_time')}</span>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Work Mode:</strong><br>
                            <span class="badge bg-info">${THR.escapeHtml(job.work_mode || 'onsite')}</span>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Urgency:</strong><br>
                            <span class="badge bg-${urgencyColor}">${job.hiring_urgency || 'medium'}</span>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Posted:</strong><br>
                            ${postedDate}
                        </div>
                        
                        ${job.candidates_required ? `
                            <div class="mb-3">
                                <strong>Positions Available:</strong><br>
                                ${job.candidates_required}
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Set apply button
    document.getElementById('applyBtn').onclick = function() {
        quickApply(job.id);
        modal.hide();
    };
    
    modal.show();
}

function quickApply(jobId) {
    // Check if user is logged in
    const token = localStorage.getItem('candidate_token');
    
    if (!token) {
        // Redirect to login with job info
        sessionStorage.setItem('redirect_after_login', '/candidate/jobs');
        sessionStorage.setItem('job_to_apply', jobId);
        window.location.href = '/candidate/login';
        return;
    }
    
    // Redirect to application page
    window.location.href = `/candidate/apply?job=${jobId}`;
}

function updatePagination() {
    const container = document.getElementById('pagination');
    
    if (totalPages <= 1) {
        container.parentElement.style.display = 'none';
        return;
    }
    
    container.parentElement.style.display = 'block';
    
    let pagination = '';
    
    // Previous button
    pagination += `
        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${currentPage - 1})">Previous</a>
        </li>
    `;
    
    // Page numbers
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);
    
    if (startPage > 1) {
        pagination += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(1)">1</a></li>`;
        if (startPage > 2) {
            pagination += `<li class="page-item disabled"><a class="page-link" href="#">...</a></li>`;
        }
    }
    
    for (let i = startPage; i <= endPage; i++) {
        pagination += `
            <li class="page-item ${i === currentPage ? 'active' : ''}">
                <a class="page-link" href="#" onclick="changePage(${i})">${i}</a>
            </li>
        `;
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            pagination += `<li class="page-item disabled"><a class="page-link" href="#">...</a></li>`;
        }
        pagination += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${totalPages})">${totalPages}</a></li>`;
    }
    
    // Next button
    pagination += `
        <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${currentPage + 1})">Next</a>
        </li>
    `;
    
    container.innerHTML = pagination;
}

function changePage(page) {
    if (page < 1 || page > totalPages || page === currentPage) {
        return;
    }
    
    currentPage = page;
    loadJobs();
    
    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>
@endpush
