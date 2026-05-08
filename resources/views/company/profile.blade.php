@extends('layouts.app', ['role' => 'company'])
@section('title', 'Company Profile')
@section('content')
<div class="page-header"><div><h1>Company Profile</h1><p>Update public company info</p></div></div>
<div class="card"><div class="card-body">
<form id="profileForm">
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name"></div>
        {{-- Email field ko name attribute diya hai taake THR.fillForm isay pakar sakay --}}
        <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" name="email" type="email" readonly></div>
        <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" maxlength="30"></div>
        <div class="col-md-6"><label class="form-label">Industry</label><input class="form-control" name="industry"></div>
        <div class="col-md-6"><label class="form-label">Company size</label><input class="form-control" name="company_size"></div>
        <div class="col-md-6"><label class="form-label">Website</label><input class="form-control" name="website"></div>
        <div class="col-12"><label class="form-label">About</label><textarea class="form-control" name="about" id="aboutEditor" rows="6"></textarea></div>
        
        <div class="col-md-6">
            <label class="form-label">Logo</label>
            <div id="logoPreview" class="mb-2"></div>
            <input type="file" class="form-control" id="logoUpload" accept="image/*">
            <small class="text-muted">Max 2MB</small>
        </div>
        <div class="col-md-6">
            <label class="form-label">Cover Image</label>
            <div id="coverPreview" class="mb-2"></div>
            <input type="file" class="form-control" id="coverUpload" accept="image/*">
            <small class="text-muted">Max 5MB</small>
        </div>

        <div class="col-12">
            <label class="form-label">Office Locations</label>
            <div id="officeLocationsContainer" class="mb-3">
                {{-- Dynamic rows yahan load hongi --}}
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addLocation()">+ Add Location</button>
        </div>

        <div class="col-12">
            <label class="form-label">Working Hours</label>
            <div id="workingHoursContainer">
                {{-- Dynamic rows yahan load hongi --}}
            </div>
            <div class="mt-2">
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addWorkingHour()">+ Add Time Slot</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setStandardHours()">Set Standard 9-5</button>
            </div>
        </div>
    </div>
    <button class="btn btn-primary mt-4" type="submit">Save changes</button>
</form>
</div></div>

@push('scripts')
<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
let aboutEditor;

// 1. TinyMCE Initialization
document.addEventListener('DOMContentLoaded', function() {
    tinymce.init({
        selector: '#aboutEditor',
        height: 250,
        menubar: false,
        setup: function(editor) {
            aboutEditor = editor;
            editor.on('init', () => load()); // Editor load hone ke baad data mangwao
        }
    });
});

// 2. Load Data from API
async function load() {
    try {
        const r = await THR.api('/company/profile');
        const c = r.company || r;
        const form = document.getElementById('profileForm');
        
        // Basic fields (Name, Email, etc.)
        THR.fillForm(form, c);
        
        // About Editor
        if (aboutEditor) aboutEditor.setContent(c.about || '');

        // Office Locations render
        const locContainer = document.getElementById('officeLocationsContainer');
        locContainer.innerHTML = '';
        if (c.office_locations && Array.isArray(c.office_locations) && c.office_locations.length) {
            c.office_locations.forEach(loc => addLocation(loc));
        } else {
            addLocation(); // Default khali row
        }

        // Working Hours render
        const hoursContainer = document.getElementById('workingHoursContainer');
        hoursContainer.innerHTML = '';
        if (c.working_hours && Array.isArray(c.working_hours) && c.working_hours.length) {
            c.working_hours.forEach(wh => addWorkingHour(wh));
        } else {
            addWorkingHour(); // Default khali row
        }

        // Image Previews
        if (c.logo) {
            document.getElementById('logoPreview').innerHTML = `<img src="${c.logo}" class="img-thumbnail" style="height:80px">`;
        }
        if (c.cover_image) {
            document.getElementById('coverPreview').innerHTML = `<img src="${c.cover_image}" class="img-thumbnail" style="height:80px; width:100%; object-fit:cover;">`;
        }

    } catch (e) { 
        console.error('Load Error:', e);
        THR.toast('Failed to load profile', 'danger');
    }
}

// 3. Dynamic Row Handlers
function addLocation(data = {}) {
    const container = document.getElementById('officeLocationsContainer');
    const div = document.createElement('div');
    div.className = 'row g-2 mb-2 loc-row';
    div.innerHTML = `
        <div class="col-md-5"><input type="text" class="form-control" placeholder="City, Country" value="${data.city || ''}" required></div>
        <div class="col-md-5"><input type="text" class="form-control" placeholder="Full Address" value="${data.address || ''}" required></div>
        <div class="col-md-2"><button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="this.parentElement.parentElement.remove()">Remove</button></div>
    `;
    container.appendChild(div);
}

function addWorkingHour(data = {}) {
    const container = document.getElementById('workingHoursContainer');
    const div = document.createElement('div');
    div.className = 'row g-2 mb-2 hour-row';
    const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    let options = days.map(d => `<option value="${d}" ${data.day === d ? 'selected' : ''}>${d.charAt(0).toUpperCase()+d.slice(1)}</option>`).join('');
    
    div.innerHTML = `
        <div class="col-md-3"><select class="form-select">${options}</select></div>
        <div class="col-md-3"><input type="time" class="form-control" value="${data.start || ''}" required></div>
        <div class="col-md-3"><input type="time" class="form-control" value="${data.end || ''}" required></div>
        <div class="col-md-3"><button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="this.parentElement.parentElement.remove()">Remove</button></div>
    `;
    container.appendChild(div);
}

function setStandardHours() {
    document.getElementById('workingHoursContainer').innerHTML = '';
    ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'].forEach(d => {
        addWorkingHour({day: d, start: '09:00', end: '17:00'});
    });
}

// 4. File Preview Logic
function setupFilePreview(inputId, previewId) {
    document.getElementById(inputId).addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (ex) => {
                document.getElementById(previewId).innerHTML = `<img src="${ex.target.result}" class="img-thumbnail" style="height:80px">`;
            };
            reader.readAsDataURL(file);
        }
    });
}
setupFilePreview('logoUpload', 'logoPreview');
setupFilePreview('coverUpload', 'coverPreview');

// 5. Submit Form
document.getElementById('profileForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData();
    
    // Regular inputs
    formData.append('name', e.target.name.value);
    formData.append('phone', e.target.phone.value);
    formData.append('industry', e.target.industry.value);
    formData.append('company_size', e.target.company_size.value);
    formData.append('website', e.target.website.value);
    formData.append('about', aboutEditor.getContent());

    // Office Locations (Array to String)
    const locations = [];
    document.querySelectorAll('.loc-row').forEach(row => {
        const inputs = row.querySelectorAll('input');
        if(inputs[0].value) locations.push({ city: inputs[0].value, address: inputs[1].value });
    });
    formData.append('office_locations', JSON.stringify(locations));

    // Working Hours (Array to String)
    const hours = [];
    document.querySelectorAll('.hour-row').forEach(row => {
        const select = row.querySelector('select');
        const inputs = row.querySelectorAll('input');
        if(inputs[0].value) hours.push({ day: select.value, start: inputs[0].value, end: inputs[1].value });
    });
    formData.append('working_hours', JSON.stringify(hours));

    // Images
    const logoFile = document.getElementById('logoUpload').files[0];
    const coverFile = document.getElementById('coverUpload').files[0];
    if (logoFile) formData.append('logo', logoFile);
    if (coverFile) formData.append('cover_image', coverFile);

    try {
        // Laravel PUT with Files fix: POST method + Method Override Header
        await THR.api('/company/profile', { 
            method: 'POST', 
            body: formData,
            headers: { 'X-HTTP-Method-Override': 'PUT' }
        });
        THR.toast('Profile updated successfully!', 'success');
        load(); // Data refresh taake UI update ho jaye
    } catch (err) { 
        THR.toast(err.message || 'Update failed', 'danger'); 
    }
});
</script>
@endpush
@endsection
