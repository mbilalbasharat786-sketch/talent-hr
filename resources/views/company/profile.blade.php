@extends('layouts.app', ['role' => 'company'])
@section('title', 'Company Profile')
@section('content')
<div class="page-header"><div><h1>Company Profile</h1><p>Update public company info</p></div></div>
<div class="card"><div class="card-body">
<form id="profileForm">
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name"></div>
        <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" name="email" type="email" readonly></div>
        <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" maxlength="30"></div>
        <div class="col-md-6"><label class="form-label">Industry</label><input class="form-control" name="industry"></div>
        <div class="col-md-6"><label class="form-label">Company size</label><input class="form-control" name="company_size"></div>
        <div class="col-md-6"><label class="form-label">Website</label><input class="form-control" name="website"></div>
        <div class="col-12"><label class="form-label">About</label><textarea class="form-control" name="about" id="aboutEditor" rows="6"></textarea></div>
        <div class="col-12">
            <label class="form-label">Logo</label>
            <div class="mb-3">
                <div id="logoPreview" class="mb-2"></div>
                <input type="file" class="form-control" id="logoUpload" accept="image/*">
                <small class="text-muted">Upload company logo (PNG, JPG, max 2MB)</small>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">Cover Image</label>
            <div class="mb-3">
                <div id="coverPreview" class="mb-2"></div>
                <input type="file" class="form-control" id="coverUpload" accept="image/*">
                <small class="text-muted">Upload cover image (PNG, JPG, max 5MB)</small>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">Office Locations</label>
            <div id="officeLocationsContainer" class="mb-3">
                <div class="row g-2 mb-2" data-location-index="0">
                    <div class="col-md-5">
                        <input type="text" class="form-control" placeholder="City, Country" name="office_locations[0][city]">
                    </div>
                    <div class="col-md-5">
                        <input type="text" class="form-control" placeholder="Address" name="office_locations[0][address]">
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeLocation(this)" style="display: none;">Remove</button>
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addLocation()">+ Add Location</button>
        </div>
        <div class="col-12">
            <label class="form-label">Working Hours</label>
            <div id="workingHoursContainer">
                <div class="row g-2 mb-2" data-day-index="0">
                    <div class="col-md-3">
                        <select class="form-select" name="working_hours[0][day]">
                            <option value="monday">Monday</option>
                            <option value="tuesday">Tuesday</option>
                            <option value="wednesday">Wednesday</option>
                            <option value="thursday">Thursday</option>
                            <option value="friday">Friday</option>
                            <option value="saturday">Saturday</option>
                            <option value="sunday">Sunday</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="time" class="form-control" placeholder="Start" name="working_hours[0][start]">
                    </div>
                    <div class="col-md-3">
                        <input type="time" class="form-control" placeholder="End" name="working_hours[0][end]">
                    </div>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeWorkingHour(this)" style="display: none;">Remove</button>
                    </div>
                </div>
            </div>
            <div class="mt-2">
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addWorkingHour()">+ Add Time Slot</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setStandardHours()">Set Standard 9-5</button>
            </div>
        </div>
    </div>
    <button class="btn btn-primary mt-3">Save changes</button>
</form>
</div></div>
@push('scripts')
<!-- TinyMCE Rich Text Editor -->
<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
let aboutEditor;
let logoData = null;
let coverData = null;

// Initialize TinyMCE
document.addEventListener('DOMContentLoaded', function() {
    tinymce.init({
        selector: '#aboutEditor',
        height: 300,
        menubar: false,
        plugins: [
            'advlist autolink lists link image charmap print preview anchor',
            'searchreplace visualblocks code fullscreen',
            'insertdatetime media table paste code help wordcount'
        ],
        toolbar: 'undo redo | formatselect | bold italic backcolor | \
            alignleft aligncenter alignright alignjustify | \
            bullist numlist outdent indent | removeformat | help',
        content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px }',
        setup: function(editor) {
            aboutEditor = editor;
            // Load content after initialization
            setTimeout(() => load(), 500);
        }
    });
});

async function load() {
    try {
        const r = await THR.api('/company/profile');
        const c = r.company || r;
        const form = document.getElementById('profileForm');
        THR.fillForm(form, c);
        if (Array.isArray(c.office_locations)) form.office_locations.value = c.office_locations.join(', ');
        if (c.working_hours && typeof c.working_hours === 'object') form.working_hours.value = JSON.stringify(c.working_hours);
        
        // Set rich text editor content
        if (aboutEditor && c.about) {
            aboutEditor.setContent(c.about);
        }
        
        // Show existing images
});

// Handle file uploads
document.getElementById('logoUpload').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        if (file.size > 2097152) { // 2MB
            THR.toast('Logo file size must be less than 2MB', 'danger');
            e.target.value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            logoData = e.target.result;
            document.getElementById('logoPreview').innerHTML = 
                `<img src="${logoData}" style="max-width: 150px; max-height: 100px; border: 1px solid #ddd;" class="rounded">`;
        };
        reader.readAsDataURL(file);
    }
});

document.getElementById('coverUpload').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        if (file.size > 5242880) { // 5MB
            THR.toast('Cover image file size must be less than 5MB', 'danger');
            e.target.value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            coverData = e.target.result;
            document.getElementById('coverPreview').innerHTML = 
                `<img src="${coverData}" style="max-width: 300px; max-height: 150px; border: 1px solid #ddd;" class="rounded">`;
        };
        reader.readAsDataURL(file);
    }
});

// Office locations management
let locationIndex = 1;
function addLocation() {
    const container = document.getElementById('officeLocationsContainer');
    const newLocation = document.createElement('div');
    newLocation.className = 'row g-2 mb-2';
    newLocation.setAttribute('data-location-index', locationIndex);
    newLocation.innerHTML = `
        <div class="col-md-5">
            <input type="text" class="form-control" placeholder="City, Country" name="office_locations[${locationIndex}][city]">
        </div>
        <div class="col-md-5">
            <input type="text" class="form-control" placeholder="Address" name="office_locations[${locationIndex}][address]">
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeLocation(this)">Remove</button>
        </div>
    `;
    container.appendChild(newLocation);
    locationIndex++;
    updateRemoveButtons();
}

function removeLocation(button) {
    button.closest('[data-location-index]').remove();
    updateRemoveButtons();
}

// Working hours management
let dayIndex = 1;
function addWorkingHour() {
    const container = document.getElementById('workingHoursContainer');
    const newHour = document.createElement('div');
    newHour.className = 'row g-2 mb-2';
    newHour.setAttribute('data-day-index', dayIndex);
    newHour.innerHTML = `
        <div class="col-md-3">
            <select class="form-select" name="working_hours[${dayIndex}][day]">
                <option value="">Select Day</option>
                <option value="monday">Monday</option>
                <option value="tuesday">Tuesday</option>
                <option value="wednesday">Wednesday</option>
                <option value="thursday">Thursday</option>
                <option value="friday">Friday</option>
                <option value="saturday">Saturday</option>
                <option value="sunday">Sunday</option>
            </select>
        </div>
        <div class="col-md-3">
            <input type="time" class="form-control" placeholder="Start" name="working_hours[${dayIndex}][start]">
        </div>
        <div class="col-md-3">
            <input type="time" class="form-control" placeholder="End" name="working_hours[${dayIndex}][end]">
        </div>
        <div class="col-md-3">
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeWorkingHour(this)">Remove</button>
        </div>
    `;
    container.appendChild(newHour);
    dayIndex++;
    updateRemoveButtons();
}

function removeWorkingHour(button) {
    button.closest('[data-day-index]').remove();
    updateRemoveButtons();
}

function setStandardHours() {
    const container = document.getElementById('workingHoursContainer');
    container.innerHTML = '';
    dayIndex = 0;
    
    const weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    weekdays.forEach(day => {
        addWorkingHour();
        const lastRow = container.querySelector(`[data-day-index="${dayIndex - 1}"]`);
        if (lastRow) {
            const daySelect = lastRow.querySelector(`select[name="working_hours[${dayIndex - 1}][day]"]`);
            const startInput = lastRow.querySelector(`input[name="working_hours[${dayIndex - 1}][start]"]`);
            const endInput = lastRow.querySelector(`input[name="working_hours[${dayIndex - 1}][end]"]`);
            
            if (daySelect) daySelect.value = day;
            if (startInput) startInput.value = '09:00';
            if (endInput) endInput.value = '17:00';
        }
    });
    updateRemoveButtons();
}

function updateRemoveButtons() {
    const locationButtons = document.querySelectorAll('#officeLocationsContainer button');
    locationButtons.forEach(btn => {
        btn.style.display = locationButtons.length > 1 ? 'block' : 'none';
    });
    
    const hourButtons = document.querySelectorAll('#workingHoursContainer button');
    hourButtons.forEach(btn => {
        btn.style.display = hourButtons.length > 1 ? 'block' : 'none';
    });
}

load();
document.getElementById('profileForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = THR.formData(e.target);
    
    // Get rich text editor content
    if (aboutEditor) {
        data.about = aboutEditor.getContent();
    }
    
    // Process office locations
    const officeLocations = [];
    document.querySelectorAll('#officeLocationsContainer [data-location-index]').forEach(row => {
        const index = row.getAttribute('data-location-index');
        const city = row.querySelector(`input[name="office_locations[${index}][city]"]`).value;
        const address = row.querySelector(`input[name="office_locations[${index}][address]"]`).value;
        if (city || address) {
            officeLocations.push({ city, address });
        }
    });
    data.office_locations = officeLocations;
    
    // Process working hours
    const workingHours = [];
    document.querySelectorAll('#workingHoursContainer [data-day-index]').forEach(row => {
        const index = row.getAttribute('data-day-index');
        const day = row.querySelector(`select[name="working_hours[${index}][day]"]`).value;
        const start = row.querySelector(`input[name="working_hours[${index}][start]"]`).value;
        const end = row.querySelector(`input[name="working_hours[${index}][end]"]`).value;
        if (day && start && end) {
            workingHours.push({ day, start, end });
        }
    });
    data.working_hours = workingHours;
    
    delete data.email;
    
    // Handle image uploads
    const formData = new FormData();
    
    // Add regular form data
    Object.keys(data).forEach(key => {
        if (key !== 'logo' && key !== 'cover_image') {
            formData.append(key, JSON.stringify(data[key]));
        }
    });
    
    // Add image files if they exist
    const logoFile = document.getElementById('logoUpload').files[0];
    const coverFile = document.getElementById('coverUpload').files[0];
    
    if (logoFile) {
        formData.append('logo', logoFile);
    }
    if (coverFile) {
        formData.append('cover_image', coverFile);
    }
    
    try { 
        await THR.api('/company/profile', { 
            method: 'PUT', 
            body: formData,
            headers: {} // Let browser set Content-Type for FormData
        }); 
        THR.toast('Profile updated successfully','success'); 
        load(); // Reload to show new images
    }
    catch (err) { THR.toast(err.message, 'danger'); }
});
</script>
@endpush
@endsection
