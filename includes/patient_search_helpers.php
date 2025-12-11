<?php
/**
 * Patient Search Component Helper Functions
 * Provides easy integration of patient search functionality in forms
 */

/**
 * Renders a patient search input field with autocomplete
 * 
 * @param string $input_id - ID for the input field
 * @param array $options - Configuration options
 * @return string HTML for the patient search component
 */
function renderPatientSearch($input_id, $options = []) {
    $defaults = [
        'name' => $input_id,
        'placeholder' => '🔍 Search by patient name, NIC, hospital number, clinic number...',
        'required' => true,
        'class' => '',
        'min_chars' => 1,
        'ajax_url' => '../includes/patient_search_ajax.php',
        'show_selected_display' => true,
        'on_select' => null,
        'value' => '',
        'hidden_field' => $input_id . '_id' // Hidden field for patient ID
    ];
    
    $config = array_merge($defaults, $options);
    
    $required_attr = $config['required'] ? 'required' : '';
    $class_attr = $config['class'] ? ' ' . $config['class'] : '';
    
    $html = '<div class="form-group patient-search-group">';
    
    // Label if provided
    if (!empty($config['label'])) {
        $html .= '<label for="' . $input_id . '">' . htmlspecialchars($config['label']);
        if ($config['required']) {
            $html .= ' <span class="text-danger">*</span>';
        }
        $html .= '</label>';
    }
    
    // Patient search input
    $html .= '<input type="text" 
                     id="' . $input_id . '" 
                     name="' . $config['name'] . '"
                     class="form-control patient-search-input' . $class_attr . '"
                     placeholder="' . htmlspecialchars($config['placeholder']) . '"
                     value="' . htmlspecialchars($config['value']) . '"
                     autocomplete="off"
                     data-patient-search="true"
                     data-min-chars="' . $config['min_chars'] . '"
                     data-show-selected-display="' . ($config['show_selected_display'] ? 'true' : 'false') . '"
                     data-ajax-url="' . $config['ajax_url'] . '"';
    
    if ($config['on_select']) {
        $html .= ' data-on-select="' . $config['on_select'] . '"';
    }
    
    $html .= ' ' . $required_attr . '>';
    
    // Hidden field for patient ID
    if ($config['hidden_field']) {
        $html .= '<input type="hidden" 
                         id="' . $config['hidden_field'] . '" 
                         name="' . $config['hidden_field'] . '"
                         value="' . (isset($config['patient_id']) ? $config['patient_id'] : '') . '">';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Includes the necessary CSS and JS files for patient search
 * Call this in the <head> section of your page
 */
function includePatientSearchAssets() {
    // Get the relative path based on current directory
    $current_dir = basename(dirname($_SERVER['PHP_SELF']));
    $path_prefix = ($current_dir === 'reports' || $current_dir === 'patients' || $current_dir === 'admissions') ? '../../includes/' : '../includes/';
    
    echo '<link rel="stylesheet" href="' . $path_prefix . 'patient_search.css">';
    echo '<script src="' . $path_prefix . 'patient_search.js"></script>';
}

/**
 * Initialize patient search with custom options
 * 
 * @param string $input_id - ID of the input field
 * @param array $options - JavaScript options
 * @return string JavaScript code to initialize the search
 */
function initPatientSearch($input_id, $options = []) {
    $js_options = json_encode($options);
    
    return "
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        new PatientSearch('$input_id', $js_options);
    });
    </script>";
}

/**
 * Get patient data by ID for pre-filling forms
 * 
 * @param int $patient_id
 * @param mysqli $conn - Database connection
 * @return array|null Patient data or null if not found
 */
function getPatientById($patient_id, $conn) {
    $patient_id = (int)$patient_id;
    $sql = "SELECT p.*, no.nursing_name,
                   TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age
            FROM patients p
            LEFT JOIN nursing_officers no ON p.assigned_nursing_officer = no.nursing_id
            WHERE p.patient_id = $patient_id";
    
    $result = $conn->query($sql);
    return $result && $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

/**
 * Render patient info display (read-only)
 * 
 * @param array $patient - Patient data
 * @return string HTML for patient info display
 */
function renderPatientInfo($patient) {
    if (!$patient) {
        return '<div class="alert alert-warning">No patient selected</div>';
    }
    
    return '
    <div class="patient-info-display">
        <div class="patient-info-header">
            <h5>' . htmlspecialchars($patient['calling_name']) . ' (' . htmlspecialchars($patient['full_name']) . ')</h5>
        </div>
        <div class="patient-info-details">
            <div class="row">
                <div class="col-md-6">
                    <strong>NIC:</strong> ' . htmlspecialchars($patient['nic']) . '<br>
                    <strong>Age:</strong> ' . (isset($patient['age']) ? $patient['age'] . ' years' : 'Not specified') . '<br>
                    <strong>DOB:</strong> ' . ($patient['date_of_birth'] ? date('M j, Y', strtotime($patient['date_of_birth'])) : 'Not specified') . '
                </div>
                <div class="col-md-6">
                    <strong>Clinic #:</strong> ' . htmlspecialchars($patient['clinic_number']) . '<br>
                    <strong>Hospital #:</strong> ' . htmlspecialchars($patient['hospital_number'] ?: 'Not assigned') . '<br>
                    <strong>Contact:</strong> ' . htmlspecialchars($patient['contact_number'] ?: 'Not provided') . '
                </div>
            </div>
        </div>
    </div>';
}
?>