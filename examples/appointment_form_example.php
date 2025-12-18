<?php
/*
 * Example implementation of patient search in a new form
 * This demonstrates how to integrate the reusable patient search component
 */

// Include the patient search helpers
include '../includes/patient_search_helpers.php';

// Handle form submission
if ($_POST) {
    $patient_id = $_POST['patient_id'];
    $appointment_date = $_POST['appointment_date'];
    $notes = $_POST['notes'];
    
    // Process the form data
    // ... your form processing logic here
    
    if ($patient_id) {
        echo "<div class='alert alert-success'>Appointment scheduled for patient ID: $patient_id</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Appointment - Example Form</title>
    
    <!-- Bootstrap CSS (if you're using it) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Include Patient Search Assets -->
    <?php includePatientSearchAssets(); ?>
    
    <style>
        .container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .btn {
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        
        .btn:hover {
            background-color: #0056b3;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Schedule Appointment - Example Form</h2>
        <p class="text-muted">This form demonstrates the reusable patient search component integration.</p>
        
        <form method="POST" action="">
            <!-- Patient Search Component -->
            <?php echo renderPatientSearch('patient_search', [
                'label' => 'Select Patient *',
                'required' => true,
                'placeholder' => '🔍 Search by patient name, NIC, hospital number...',
                'on_select' => 'handlePatientSelection',
                'show_selected_display' => true
            ]); ?>
            
            <!-- Appointment Date -->
            <div class="form-group">
                <label for="appointment_date">Appointment Date *</label>
                <input type="datetime-local" 
                       id="appointment_date" 
                       name="appointment_date" 
                       class="form-control" 
                       required>
            </div>
            
            <!-- Notes -->
            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea id="notes" 
                          name="notes" 
                          class="form-control" 
                          rows="4" 
                          placeholder="Additional notes about the appointment..."></textarea>
            </div>
            
            <!-- Patient Display Area (will be populated when patient is selected) -->
            <div id="patient-info" style="display: none;">
                <h4>Selected Patient Information</h4>
                <div class="card" style="padding: 15px; border: 1px solid #ddd; border-radius: 4px; background-color: #f8f9fa;">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Name:</strong> <span id="display-name">-</span><br>
                            <strong>NIC:</strong> <span id="display-nic">-</span><br>
                            <strong>Hospital Number:</strong> <span id="display-hospital-number">-</span>
                        </div>
                        <div class="col-md-6">
                            <strong>Contact:</strong> <span id="display-contact">-</span><br>
                            <strong>Date of Birth:</strong> <span id="display-dob">-</span><br>
                            <strong>Age:</strong> <span id="display-age">-</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <br>
            
            <!-- Submit Button -->
            <button type="submit" class="btn">Schedule Appointment</button>
            <button type="button" class="btn" style="background-color: #6c757d;" onclick="clearForm()">Clear Form</button>
        </form>
    </div>

    <script>
        /**
         * Handle patient selection from the search component
         * This function is called when a patient is selected from the autocomplete
         */
        function handlePatientSelection(patient) {
            // patient selection handled — update UI below
            
            // Update the hidden patient ID field (automatically handled by the component)
            // The component creates a hidden field with name "patient_id"
            
            // Update the patient information display
            document.getElementById('display-name').textContent = patient.calling_name || patient.full_name;
            document.getElementById('display-nic').textContent = patient.nic || '-';
            document.getElementById('display-hospital-number').textContent = patient.hospital_number || '-';
            document.getElementById('display-contact').textContent = patient.contact_number || '-';
            document.getElementById('display-dob').textContent = patient.date_of_birth || '-';
            document.getElementById('display-age').textContent = patient.age ? patient.age + ' years' : '-';
            
            // Show the patient info section
            document.getElementById('patient-info').style.display = 'block';
        }
        
        /**
         * Clear the form
         */
        function clearForm() {
            // Clear the form fields
            document.querySelector('form').reset();
            
            // Hide patient info
            document.getElementById('patient-info').style.display = 'none';
            
            // Clear the patient search component
            // The PatientSearch component will handle clearing its own state
            const patientSearchInput = document.getElementById('patient_search');
            if (patientSearchInput) {
                patientSearchInput.value = '';
                patientSearchInput.dispatchEvent(new Event('input')); // Trigger the component's clear logic
            }
        }
        
        /**
         * Form validation
         */
        document.querySelector('form').addEventListener('submit', function(e) {
            const patientId = document.getElementById('patient_search_id').value;
            const appointmentDate = document.getElementById('appointment_date').value;
            
            if (!patientId) {
                alert('Please select a patient.');
                e.preventDefault();
                return;
            }
            
            if (!appointmentDate) {
                alert('Please select an appointment date.');
                e.preventDefault();
                return;
            }
            
            // Additional validation can be added here
        });
        
        /**
         * Set minimum appointment date to today
         */
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            const dateString = now.getFullYear() + '-' + 
                              String(now.getMonth() + 1).padStart(2, '0') + '-' + 
                              String(now.getDate()).padStart(2, '0') + 'T' + 
                              String(now.getHours()).padStart(2, '0') + ':' + 
                              String(now.getMinutes()).padStart(2, '0');
            
            document.getElementById('appointment_date').min = dateString;
        });
    </script>
</body>
</html>