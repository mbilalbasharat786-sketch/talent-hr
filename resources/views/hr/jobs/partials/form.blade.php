<form id="jobForm">
    <div class="row g-3">
        <div class="col-md-8"><label class="form-label">Title</label><input class="form-control" name="title" required maxlength="255"></div>
        <div class="col-md-4"><label class="form-label">Type</label>
            <select class="form-select" name="type" required>
                <option value="full_time">Full time</option>
                <option value="part_time">Part time</option>
                <option value="internship">Internship</option>
                <option value="contract">Contract</option>
            </select></div>
        <div class="col-md-4"><label class="form-label">Work mode</label>
            <select class="form-select" name="work_mode" required>
                <option value="onsite">Onsite</option><option value="remote">Remote</option><option value="hybrid">Hybrid</option>
            </select></div>
        <div class="col-md-4"><label class="form-label">Location</label><input class="form-control" name="location" required></div>
        <div class="col-md-4"><label class="form-label">Experience level</label>
            <select class="form-select" name="experience_level"><option value="entry">Entry</option><option value="mid">Mid</option><option value="senior">Senior</option></select></div>
        <div class="col-md-6"><label class="form-label">Skills (comma separated)</label><input class="form-control" name="skills" placeholder="e.g. PHP, Laravel, Vue" required></div>
        <div class="col-md-3"><label class="form-label">Candidates required</label><input class="form-control" type="number" min="1" name="candidates_required" value="1" required></div>
        <div class="col-md-3"><label class="form-label">Hiring urgency</label><select class="form-select" name="hiring_urgency" required><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option></select></div>
        <div class="col-md-6"><label class="form-label">Education</label><input class="form-control" name="education"></div>
        <div class="col-md-6"><label class="form-label">Linked assessment</label><select class="form-select" name="assessment_id" required><option value="">Loading...</option></select></div>
        <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" rows="5" name="description" required></textarea></div>
        <div class="col-12"><div class="alert alert-info mb-0 py-2 small"><i class="bi bi-info-circle me-1"></i>Job will be submitted for admin approval. You cannot set it live directly.</div></div>
    </div>
    <button class="btn btn-primary mt-3">{{ $mode === 'edit' ? 'Save changes' : 'Post job' }}</button>
    <a href="/hr/jobs" class="btn btn-light mt-3">Cancel</a>
</form>
