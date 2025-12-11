<?php
include '../../config/db.php';
include '../../includes/header.php';


// Handle search
$search_query = $_GET['search'] ?? '';
$patients = [];

if (!empty($search_query)) {
    $search_sql = "SELECT p.patient_id, p.calling_name, p.full_name, p.clinic_number, p.hospital_number, p.nic, p.contact_number,
                   no.nursing_name,
                   DATE_FORMAT(p.date_of_birth, '%M %d, %Y') as formatted_dob,
                   TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age
                   FROM patients p
                   LEFT JOIN nursing_officers no ON p.assigned_nursing_officer = no.nursing_id
                   WHERE p.calling_name LIKE ? 
                   OR p.full_name LIKE ? 
                   OR p.clinic_number LIKE ? 
                   OR p.hospital_number LIKE ? 
                   OR p.nic LIKE ?
                   OR p.contact_number LIKE ?
                   ORDER BY p.calling_name ASC
                   LIMIT 50";
    
    $search_term = "%$search_query%";
    $stmt = $conn->prepare($search_sql);
    $stmt->bind_param("ssssss", $search_term, $search_term, $search_term, $search_term, $search_term, $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $patients[] = $row;
    }
}

$error_message = $_GET['error'] ?? '';
?>

<div class="container">
    <!-- Header Section -->
    <div class="header-section">
        <div class="header-content">
            <div class="title-section">
                <h1 class="main-title">🔍 Patient History Search</h1>
                <p class="subtitle">Search for a patient to view their complete medical history</p>
            </div>
            <div class="action-buttons">
                <a href="report_list.php" class="btn btn-outline">← Back to Reports</a>
                <a href="../../index.php" class="btn btn-outline">🏠 Dashboard</a>
            </div>
        </div>
    </div>

    <?php if ($error_message): ?>
        <div class="error-message">
            <?php if ($error_message === 'patient_not_found'): ?>
                <h4>❌ Patient Not Found</h4>
                <p>The requested patient could not be found in the system. Please search again.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Search Section -->
    <div class="search-card">
        <div class="card-header">
            <h3>🔍 Search Patients</h3>
            <div class="search-info">
                <span>Search by name, clinic number, hospital number, NIC, contact number, or nursing officer</span>
            </div>
        </div>
        
        <div class="search-form">
            <form method="GET" action="">
                <div class="search-input-group">
                    <div class="search-container" style="position: relative; flex: 1;">
                        <input type="text" 
                               id="patient_search"
                               name="search" 
                               value="<?php echo htmlspecialchars($search_query); ?>" 
                               placeholder="🔍 Search by name, NIC, Hospital Number, Clinic Number, Contact Number, or Nursing Officer..."
                               class="search-input"
                               autocomplete="off"
                               autofocus>
                        <div class="search-results" id="search_results" style="position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ddd; border-top: none; max-height: 200px; overflow-y: auto; z-index: 1000; display: none; border-radius: 0 0 8px 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);"></div>
                    </div>
                    <button type="submit" class="search-btn">🔍 Search</button>
                    <?php if (!empty($search_query)): ?>
                        <a href="patient_search.php" class="search-btn" style="background: #6c757d; text-decoration: none;">✖️ Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Search Results -->
    <?php if (!empty($search_query)): ?>
        <div class="results-section">
            <div class="results-header">
                <h3>📋 Search Results</h3>
                <div class="results-count">
                    <?php echo count($patients); ?> patient(s) found
                </div>
            </div>

            <?php if (!empty($patients)): ?>
                <div class="patients-grid">
                    <?php foreach ($patients as $patient): ?>
                        <div class="patient-card">
                            <div class="patient-header">
                                <h4 class="patient-name"><?php echo htmlspecialchars($patient['calling_name']); ?></h4>
                                <div class="report-buttons">
                                    <button class="btn-report btn-summary" onclick="viewPatientHistory(<?php echo $patient['patient_id']; ?>)">📊 Summary</button>
                                    <button class="btn-report btn-detail" onclick="viewPatientDetailHistory(<?php echo $patient['patient_id']; ?>)">📋 Detailed</button>
                                </div>
                            </div>
                            
                            <div class="patient-details">
                                <div class="detail-item">
                                    <span class="label">Full Name:</span>
                                    <span class="value"><?php echo htmlspecialchars($patient['full_name']); ?></span>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="detail-item">
                                        <span class="label">Clinic #:</span>
                                        <span class="value badge badge-blue"><?php echo htmlspecialchars($patient['clinic_number']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Hospital #:</span>
                                        <span class="value badge badge-purple"><?php echo htmlspecialchars($patient['hospital_number'] ?: 'N/A'); ?></span>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="detail-item">
                                        <span class="label">NIC:</span>
                                        <span class="value"><?php echo htmlspecialchars($patient['nic']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Age:</span>
                                        <span class="value"><?php echo $patient['age']; ?> years</span>
                                    </div>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="label">Date of Birth:</span>
                                    <span class="value"><?php echo $patient['formatted_dob']; ?></span>
                                </div>
                                
                                <?php if (!empty($patient['contact_number'])): ?>
                                <div class="detail-item">
                                    <span class="label">Contact:</span>
                                    <span class="value"><?php echo htmlspecialchars($patient['contact_number']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($patient['nursing_name'])): ?>
                                <div class="detail-item">
                                    <span class="label">Nursing Officer:</span>
                                    <span class="value badge badge-green"><?php echo htmlspecialchars($patient['nursing_name']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-results">
                    <div class="no-results-content">
                        <h4>🔍 No Patients Found</h4>
                        <p>No patients match your search criteria. Try adjusting your search terms.</p>
                        <div class="search-tips">
                            <strong>Search Tips:</strong>
                            <ul>
                                <li>Try searching with partial names or numbers</li>
                                <li>Check for spelling errors</li>
                                <li>Use different search terms</li>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="search-help">
            <div class="help-content">
                <h4>💡 How to Search</h4>
                <div class="help-grid">
                    <div class="help-item">
                        <strong>👤 By Name:</strong>
                        <span>Enter patient's calling name or full name</span>
                    </div>
                    <div class="help-item">
                        <strong>🏥 By Numbers:</strong>
                        <span>Use clinic number or hospital number</span>
                    </div>
                    <div class="help-item">
                        <strong>🆔 By NIC:</strong>
                        <span>Enter National Identity Card number</span>
                    </div>
                    <div class="help-item">
                        <strong>📞 By Contact:</strong>
                        <span>Search using patient's contact number</span>
                    </div>
                    <div class="help-item">
                        <strong>👩‍⚕️ By Nursing Officer:</strong>
                        <span>Find patients by their assigned nursing officer</span>
                    </div>
                    <div class="help-item">
                        <strong>🔍 Partial Search:</strong>
                        <span>Partial matches are supported for all fields</span>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function viewPatientHistory(patientId) {
    window.location.href = 'patient_history_report.php?patient_id=' + patientId;
}

function viewPatientDetailHistory(patientId) {
    window.location.href = 'patient_history_detail_report.php?patient_id=' + patientId;
}

// Auto-focus search input and AJAX search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('patient_search');
    const searchResults = document.getElementById('search_results');
    
    if (searchInput) {
        searchInput.focus();
        
        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.trim();
            
            clearTimeout(searchTimeout);
            
            if (searchTerm.length < 2) {
                searchResults.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(function() {
                console.log('Searching for:', searchTerm);
                fetch(`patient_search_ajax.php?search_term=${encodeURIComponent(searchTerm)}`)
                    .then(response => {
                        console.log('Response status:', response.status);
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(patients => {
                        console.log('Patients found:', patients);
                        if (patients.error) {
                            console.error('Server error:', patients.error);
                            if (searchResults) {
                                searchResults.innerHTML = '<div class="search-item" style="padding: 1rem; color: #f00; text-align: center;">Server error: ' + patients.error + '</div>';
                                searchResults.style.display = 'block';
                            }
                        } else if (searchResults) {
                            displaySearchResults(patients);
                        } else {
                            console.error('Search results container not found');
                        }
                    })
                    .catch(error => {
                        console.error('Search error:', error);
                        if (searchResults) {
                            searchResults.innerHTML = '<div class="search-item" style="padding: 1rem; color: #f00; text-align: center;">Network error: ' + error.message + '</div>';
                            searchResults.style.display = 'block';
                        }
                    });
            }, 300);
        });
        
        function displaySearchResults(patients) {
            if (patients.length === 0) {
                searchResults.innerHTML = '<div class="search-item" style="padding: 1rem; color: #666; text-align: center;">No patients found</div>';
            } else {
                searchResults.innerHTML = patients.map(patient => 
                    `<div class="search-item" onclick="selectPatient('${patient.calling_name}', ${patient.patient_id})" style="padding: 0.75rem; border-bottom: 1px solid #eee; cursor: pointer; transition: background-color 0.2s;">
                        <div style="font-weight: 600; color: #2c3e50; margin-bottom: 0.25rem;">
                            ${patient.calling_name} <span style="font-weight: 400; color: #666;">(${patient.full_name})</span>
                        </div>
                        <div style="font-size: 0.75rem; color: #888;">
                            NIC: ${patient.nic} | H#: ${patient.hospital_number || 'Not assigned'} | C#: ${patient.clinic_number || 'Not assigned'}
                        </div>
                    </div>`
                ).join('');
            }
            searchResults.style.display = 'block';
        }
        
        // Hide search results when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.style.display = 'none';
            }
        });
    }
});

function selectPatient(callingName, patientId) {
    // Instead of just setting the search term, directly go to patient history
    window.location.href = 'patient_history_report.php?patient_id=' + patientId;
}
</script>

<style>
/* Header Styles */
.header-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem;
}

.main-title {
    font-size: 2.5rem;
    margin: 0;
    font-weight: 700;
}

.subtitle {
    margin: 0.5rem 0 0 0;
    opacity: 0.9;
    font-size: 1.1rem;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
}

/* Error Message */
.error-message {
    background: #f8d7da;
    color: #721c24;
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    border: 1px solid #f5c6cb;
}

.error-message h4 {
    margin: 0 0 0.5rem 0;
}

/* Search Card */
.search-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    margin-bottom: 2rem;
    overflow: hidden;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 1px solid #dee2e6;
}

.card-header h3 {
    margin: 0;
    color: #495057;
}

.search-info {
    font-size: 0.9rem;
    color: #6c757d;
}

.search-form {
    padding: 2rem;
}

.search-input-group {
    display: flex;
    gap: 1rem;
    max-width: 600px;
}

.search-input {
    flex: 1;
    padding: 1rem;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.search-input:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.search-btn {
    padding: 1rem 2rem;
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.search-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(52, 152, 219, 0.3);
}

/* Results Section */
.results-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    overflow: hidden;
}

.results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 1px solid #dee2e6;
}

.results-header h3 {
    margin: 0;
    color: #495057;
}

.results-count {
    font-size: 0.9rem;
    color: #6c757d;
    font-weight: 600;
}

/* Patients Grid */
.patients-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 1.5rem;
    padding: 2rem;
}

.patient-card {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 1.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.patient-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.1);
    border-color: #3498db;
    background: white;
}

.patient-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e9ecef;
}

.patient-name {
    margin: 0;
    color: #2c3e50;
    font-size: 1.2rem;
}

/* Report Buttons */
.report-buttons {
    display: flex;
    gap: 0.5rem;
}

.btn-report {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btn-summary {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
}

.btn-summary:hover {
    background: linear-gradient(135deg, #2980b9, #21618c);
    transform: translateY(-1px);
}

.btn-detail {
    background: linear-gradient(135deg, #9b59b6, #8e44ad);
    color: white;
}

.btn-detail:hover {
    background: linear-gradient(135deg, #8e44ad, #7d3c98);
    transform: translateY(-1px);
}

.patient-details {
    space-y: 0.75rem;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.detail-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 0.75rem;
}

.detail-row .detail-item {
    margin-bottom: 0;
}

.label {
    font-weight: 600;
    color: #495057;
    font-size: 0.9rem;
}

.value {
    color: #212529;
}

/* No Results */
.no-results {
    padding: 4rem 2rem;
    text-align: center;
}

.no-results-content h4 {
    color: #7f8c8d;
    margin-bottom: 1rem;
}

.no-results-content p {
    color: #adb5bd;
    margin-bottom: 2rem;
}

.search-tips {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    text-align: left;
    max-width: 400px;
    margin: 0 auto;
}

.search-tips ul {
    margin: 0.5rem 0 0 1.5rem;
    color: #6c757d;
}

/* Search Dropdown Styles */
.search-container {
    position: relative;
}

.search-item {
    padding: 0.75rem;
    border-bottom: 1px solid #eee;
    cursor: pointer;
    transition: background-color 0.2s;
}

.search-item:hover {
    background-color: #f8f9fa;
}

.search-item:last-child {
    border-bottom: none;
}

/* Additional Badge Styles */
.badge-green {
    background: linear-gradient(135deg, #e8f5e8, #c3e6cb);
    color: #2d6a2d;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.85rem;
    font-weight: 600;
}

/* Search Help */
.search-help {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    padding: 2rem;
}

.help-content h4 {
    color: #2c3e50;
    margin-bottom: 2rem;
    text-align: center;
}

.help-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.help-item {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    text-align: center;
}

.help-item strong {
    display: block;
    color: #3498db;
    margin-bottom: 0.5rem;
    font-size: 1rem;
}

.help-item span {
    color: #6c757d;
    font-size: 0.9rem;
}

/* Button Styles */
.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    display: inline-block;
    text-align: center;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    margin: 0;
    font-size: 0.9rem;
}

.btn-outline {
    background: rgba(255,255,255,0.2);
    color: white;
    border: 2px solid rgba(255,255,255,0.3);
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.btn-outline:hover {
    background: rgba(255,255,255,0.3);
}

/* Badge Styles */
.badge {
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-block;
}

.badge-blue {
    background: linear-gradient(135deg, #e3f2fd, #bbdefb);
    color: #1976d2;
}

.badge-purple {
    background: linear-gradient(135deg, #f3e5f5, #e1bee7);
    color: #7b1fa2;
}

/* Responsive Design */
@media (max-width: 768px) {
    .header-content {
        flex-direction: column;
        text-align: center;
        gap: 1.5rem;
    }
    
    .main-title {
        font-size: 2rem;
    }
    
    .search-input-group {
        flex-direction: column;
    }
    
    .patients-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .help-grid {
        grid-template-columns: 1fr;
    }
    
    .detail-row {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include '../../includes/footer.php'; ?>