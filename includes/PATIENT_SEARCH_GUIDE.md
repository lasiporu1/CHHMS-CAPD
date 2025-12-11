# Patient Search Component - Usage Guide

## Overview
This reusable patient search component provides autocomplete functionality for patient selection across all forms in the CHHMS system. It ensures consistency and ease of implementation for both current and future forms.

## Files Structure
```
includes/
├── patient_search_ajax.php     # AJAX endpoint for search
├── patient_search.css          # Component styling
├── patient_search.js           # JavaScript functionality
└── patient_search_helpers.php  # PHP helper functions
```

## Quick Start

### Method 1: Automatic Integration (Easiest)
```html
<!DOCTYPE html>
<html>
<head>
    <?php include '../includes/patient_search_helpers.php'; ?>
    <?php includePatientSearchAssets(); ?>
</head>
<body>
    <form method="POST">
        <?php echo renderPatientSearch('patient_search', [
            'label' => 'Select Patient',
            'required' => true,
            'on_select' => 'handlePatientSelection'
        ]); ?>
        
        <button type="submit">Submit</button>
    </form>
    
    <script>
    function handlePatientSelection(patient) {
        console.log('Selected patient:', patient);
        // Update hidden field with patient ID
        document.getElementById('patient_search_id').value = patient.patient_id;
        
        // You can also populate other form fields
        // document.getElementById('patient_name').value = patient.full_name;
        // document.getElementById('patient_nic').value = patient.nic;
    }
    </script>
</body>
</html>
```

### Method 2: Manual Integration
```html
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="../includes/patient_search.css">
    <script src="../includes/patient_search.js"></script>
</head>
<body>
    <form method="POST">
        <div class="form-group">
            <label for="patient_search">Select Patient *</label>
            <input type="text" 
                   id="patient_search" 
                   name="patient_search"
                   class="form-control"
                   data-patient-search="true"
                   data-min-chars="1"
                   data-show-selected-display="true"
                   placeholder="🔍 Search by patient name, NIC, hospital number..."
                   required>
            <input type="hidden" id="patient_id" name="patient_id">
        </div>
        
        <button type="submit">Submit</button>
    </form>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        new PatientSearch('patient_search', {
            onSelect: function(patient) {
                document.getElementById('patient_id').value = patient.patient_id;
                console.log('Patient selected:', patient);
            },
            onClear: function() {
                document.getElementById('patient_id').value = '';
            }
        });
    });
    </script>
</body>
</html>
```

### Method 3: Data Attribute Integration
```html
<input type="text" 
       id="patient_search" 
       name="patient_search"
       data-patient-search="true"
       data-min-chars="1"
       data-placeholder="🔍 Search patients..."
       data-on-select="myPatientSelectFunction"
       data-show-selected-display="true">

<script>
function myPatientSelectFunction(patient) {
    // Handle patient selection
    console.log('Selected:', patient);
}
</script>
```

## Configuration Options

### PHP Helper Options (renderPatientSearch)
```php
$options = [
    'name' => 'patient_search',           // Input name attribute
    'label' => 'Select Patient',         // Label text
    'placeholder' => 'Search patients...', // Placeholder text
    'required' => true,                   // Required field
    'class' => 'custom-class',           // Additional CSS classes
    'min_chars' => 1,                    // Minimum characters to search
    'show_selected_display' => true,     // Show selected patient info
    'on_select' => 'functionName',       // JavaScript callback
    'value' => 'John Doe',              // Pre-filled value
    'patient_id' => 123,                // Pre-selected patient ID
    'hidden_field' => 'patient_id'      // Hidden field name for patient ID
];
```

### JavaScript Options
```javascript
new PatientSearch('input_id', {
    minChars: 1,                        // Minimum characters to trigger search
    delay: 200,                         // Delay before search (ms)
    maxResults: 15,                     // Maximum results to show
    placeholder: 'Search patients...',  // Input placeholder
    showSelectedDisplay: true,          // Show selected patient display
    ajaxUrl: '../includes/patient_search_ajax.php', // Custom AJAX endpoint
    onSelect: function(patient) {       // Selection callback
        console.log('Selected:', patient);
    },
    onClear: function() {              // Clear callback
        console.log('Selection cleared');
    }
});
```

## Patient Object Structure
When a patient is selected, the callback receives a patient object with:
```javascript
{
    patient_id: "123",
    calling_name: "John Doe",
    full_name: "John Michael Doe",
    nic: "123456789V",
    hospital_number: "H001",
    clinic_number: "C001",
    contact_number: "0771234567",
    nursing_name: "Mary Smith",
    date_of_birth: "1990-05-15",
    age: "33"
}
```

## Methods Available

### JavaScript Methods
```javascript
const patientSearch = new PatientSearch('input_id');

// Get selected patient
const patient = patientSearch.getSelectedPatient();

// Set selected patient programmatically
patientSearch.setSelectedPatient(patientObject);

// Clear selection
patientSearch.clearSelection();

// Destroy component
patientSearch.destroy();
```

### PHP Helper Functions
```php
// Render patient search component
echo renderPatientSearch('patient_search', $options);

// Include CSS and JS assets
includePatientSearchAssets();

// Initialize with custom options
echo initPatientSearch('patient_search', $jsOptions);

// Get patient by ID
$patient = getPatientById($patient_id, $conn);

// Render patient info display
echo renderPatientInfo($patient);
```

## Implementation in Existing Forms

### For Admission Forms:
```php
// Replace existing patient selection with:
<?php echo renderPatientSearch('patient_search', [
    'label' => 'Select Patient for Admission',
    'required' => true,
    'on_select' => 'populatePatientDetails'
]); ?>

<script>
function populatePatientDetails(patient) {
    document.getElementById('patient_id').value = patient.patient_id;
    document.getElementById('display_name').textContent = patient.calling_name;
    document.getElementById('display_nic').textContent = patient.nic;
    // Update other fields as needed
}
</script>
```

### For Investigation/Medicine Forms:
```php
<?php echo renderPatientSearch('patient_search', [
    'label' => 'Patient',
    'required' => true,
    'show_selected_display' => true
]); ?>
```

## Styling Customization
The component uses CSS classes that can be customized:
- `.patient-search-container`
- `.patient-search-input`
- `.patient-search-results`
- `.patient-search-result-item`
- `.selected-patient-display`

## Security Features
- SQL injection prevention with prepared statements
- XSS protection with HTML escaping
- Input validation and sanitization
- CSRF protection ready (add tokens as needed)

## Browser Support
- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+
- IE 11 (with polyfills)

## Troubleshooting

### Common Issues:
1. **AJAX errors**: Check file paths and server configuration
2. **No results**: Verify database connection and patient data
3. **Styling issues**: Ensure CSS file is loaded correctly
4. **JavaScript errors**: Check console for detailed error messages

### Debug Mode:
Add `console.log` statements in the onSelect callback to debug:
```javascript
new PatientSearch('input_id', {
    onSelect: function(patient) {
        console.log('Debug - Selected patient:', patient);
    }
});
```

## Future Enhancements
- Multi-select capability
- Recent searches history
- Favorite patients
- Custom result templates
- Offline search capability