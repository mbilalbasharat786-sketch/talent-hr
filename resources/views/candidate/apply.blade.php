@extends('layouts.app', ['role' => 'candidate'])
@section('title', 'Apply for Jobs')
@section('content')
<div class="page-header"><div><h1>Apply for Jobs</h1><p>Browse and apply for available positions</p></div></div>
<div class="card"><div class="card-body p-0">
<table class="table mb-0">
<thead><tr><th>Job</th><th>Company</th><th>Location</th><th>Work Mode</th><th></th></tr></thead>
<tbody id="jobs"><tr><td colspan="5" class="empty-state">Loading…</td></tr></tbody>
</table>
</div></div>
@push('scripts')
<script>
let currentPage = 1;
let loading = false;

async function loadJobs(page = 1) {
    if (loading) return;
    loading = true;
    
    try {
        const data = await THR.api('/candidate/jobs?page=' + page);
        const jobs = data.data || data;
        const tb = document.getElementById('jobs');
        
        if (page === 1) {
            if (!jobs.length) {
                tb.innerHTML = '<tr><td colspan="5" class="empty-state">No jobs available</td></tr>';
                return;
            }
        }
        
        const jobsHtml = jobs.map(job => `
            <tr>
                <td>
                    <div class="fw-semibold">${THR.escapeHtml(job.title)}</div>
                    ${job.assessment ? '<span class="badge bg-success">Assessment Required</span>' : '<span class="badge bg-warning">No Assessment</span>'}
                </td>
                <td>${THR.escapeHtml(job.company?.name || '—')}</td>
                <td>${THR.escapeHtml(job.location || '—')}</td>
                <td>${THR.escapeHtml(job.work_mode || '—')}</td>
                <td>
                    ${job.already_applied ? 
                        '<span class="badge bg-secondary">Applied</span>' : 
                        `<button class="btn btn-sm btn-primary" onclick="applyJob(${job.id})">Apply</button>`
                    }
                </td>
            </tr>
        `).join('');
        
        if (page === 1) {
            tb.innerHTML = jobsHtml;
        } else {
            tb.innerHTML += jobsHtml;
        }
        
        // Show load more if there are more jobs
        if (jobs.length === 12) {
            const loadMoreRow = document.createElement('tr');
            loadMoreRow.innerHTML = `<td colspan="5" class="text-center py-3"><button class="btn btn-outline-primary" onclick="loadJobs(${page + 1})">Load More</button></td>`;
            tb.appendChild(loadMoreRow);
        }
        
    } catch (e) {
        THR.toast(e.message, 'danger');
    } finally {
        loading = false;
    }
}

async function applyJob(jobId) {
    if (!confirm('Are you sure you want to apply for this job? You will need to complete an assessment.')) return;
    
    try {
        const res = await THR.api('/candidate/apply', { 
            method: 'POST', 
            body: { job_id: jobId } 
        });
        
        THR.toast('Application submitted! Check your applications for assessment.', 'success');
        loadJobs(1); // Reload jobs to update applied status
        
    } catch (e) {
        THR.toast(e.message, 'danger');
    }
}

// Load jobs on page load
loadJobs();
</script>
@endpush
@endsection
