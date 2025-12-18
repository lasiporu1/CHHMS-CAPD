<?php
include '../../config/db.php';

// Handle AJAX search requests
if (isset($_GET['ajax_search']) && !empty($_GET['term'])) {
    $search_term = trim($_GET['term']);
    
    try {
        $search_sql = "SELECT p.patient_id, p.calling_name, p.full_name, p.nic, 
                              p.hospital_number, p.clinic_number, p.contact_number,
                              no.nursing_name,
                              TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age
                       FROM patients p
                       LEFT JOIN nursing_officers no ON p.assigned_nursing_officer = no.nursing_id
                       WHERE p.calling_name LIKE ? 
                          OR p.full_name LIKE ? 
                          OR p.nic LIKE ? 
                          OR p.hospital_number LIKE ? 
                          OR p.clinic_number LIKE ?
                          OR p.contact_number LIKE ?
                          OR no.nursing_name LIKE ?
                       ORDER BY p.calling_name LIMIT 10";
        
        $search_param = "%{$search_term}%";
        $stmt = $conn->prepare($search_sql);
        $stmt->bind_param("sssssss", $search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $patients = [];
        while ($row = $result->fetch_assoc()) {
            $patients[] = $row;
        }
        
        header('Content-Type: application/json');
        echo json_encode($patients);
        exit();
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
        exit();
    }
}

include '../../includes/header.php';

// Handle search
$patients = [];
$search_term = '';

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = trim($_GET['search']);
    
    // Search query with multiple fields
    $search_query = "SELECT p.*, no.nursing_name,
                     TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age,
                     DATE_FORMAT(p.date_of_birth, '%M %d, %Y') as formatted_dob
                     FROM patients p 
                     LEFT JOIN nursing_officers no ON p.assigned_nursing_officer = no.nursing_id
                     WHERE p.calling_name LIKE ? 
                        OR p.full_name LIKE ? 
                        OR p.nic LIKE ? 
                        OR p.clinic_number LIKE ? 
                        OR p.hospital_number LIKE ? 
                        OR p.contact_number LIKE ?
                     ORDER BY p.calling_name";
    
    $search_param = "%{$search_term}%";
    $stmt = $conn->prepare($search_query);
    $stmt->bind_param("ssssss", $search_param, $search_param, $search_param, $search_param, $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $patients[] = $row;
    }
}
?>

<div class="container">
    <!-- Hero Header Section -->
    <div class="hero-header">
        <div class="hero-background">
            <div class="hero-content">
                <div class="hero-text">
                    <h1 class="hero-title">📋 Patient History Search</h1>
                    <p class="hero-subtitle">Find patients quickly and access their comprehensive medical history reports</p>
                    <div class="hero-stats">
                        <div class="stat-item">
                            <span class="stat-icon">👥</span>
                            <span class="stat-text">All Patients</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-icon">📊</span>
                            <span class="stat-text">Detailed Reports</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-icon">⚡</span>
                            <span class="stat-text">Instant Search</span>
                        </div>
                    </div>
                </div>
                <div class="hero-actions">
                    <a href="../../index.php" class="btn-hero btn-primary">
                        <span class="btn-icon">🏠</span>
                        Dashboard
                    </a>
                    <a href="patient_search.php" class="btn-hero btn-secondary">
                        <span class="btn-icon">📋</span>
                        Summary Reports
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Smart Search Section -->
    <div class="smart-search-section">
        <div class="search-container-main">
            <div class="search-header">
                <div class="search-title">
                    <h2>🔍 Smart Patient Search</h2>
                    <p>Type any patient information to find instantly</p>
                </div>
                <div class="search-filters">
                    <div class="filter-chip active" data-filter="all">
                        <span class="chip-icon">👥</span>
                        All Patients
                    </div>
                    <div class="filter-chip" data-filter="recent">
                        <span class="chip-icon">🕒</span>
                        Recent
                    </div>
                    <div class="filter-chip" data-filter="admitted">
                        <span class="chip-icon">🏥</span>
                        Currently Admitted
                    </div>
                </div>
            </div>
            
            <div class="search-box-container">
                <div class="patient-search-container">
                    <input type="text" 
                           id="patient-search" 
                           class="smart-search-input" 
                           placeholder="🔍 Search by name, NIC, hospital number, clinic number, contact..." 
                           autocomplete="off">
                    <div class="search-results" id="search-results" style="display: none;"></div>
                </div>
                
                <div class="search-suggestions">
                    <div class="suggestion-category">
                        <h4>💡 Search Tips</h4>
                        <div class="suggestion-items">
                            <span class="suggestion-item">Try: "John" or "J123" or "0771234567"</span>
                            <span class="suggestion-item">Use partial names or numbers</span>
                            <span class="suggestion-item">Search by nursing officer name</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Results Section -->
    <div class="results-section" id="results-section" style="<?php echo empty($search_term) ? 'display: none;' : ''; ?>">
        <div class="results-container">
            <div class="results-toolbar">
                <div class="results-info">
                    <h3>👥 Search Results</h3>
                    <div class="results-count" id="results-count">
                        <?php echo !empty($patients) ? count($patients) : '0'; ?> patient(s) found
                    </div>
                </div>
                <div class="results-actions">
                    <button class="btn-action" onclick="exportResults()">
                        <span class="action-icon">📊</span>
                        Export List
                    </button>
                    <button class="btn-action" onclick="clearSearch()">
                        <span class="action-icon">✖</span>
                        Clear Search
                    </button>
                </div>
            </div>

            <?php if (!empty($patients)): ?>
                <div class="patients-grid-modern" id="patients-grid">
                    <?php foreach ($patients as $patient): ?>
                        <div class="patient-card-modern" onclick="viewPatientDetailHistory(<?php echo $patient['patient_id']; ?>)">
                            <div class="card-header">
                                <div class="patient-avatar">
                                    <span class="avatar-text"><?php echo strtoupper(substr($patient['calling_name'], 0, 2)); ?></span>
                                </div>
                                <div class="patient-title">
                                    <h4 class="patient-name"><?php echo htmlspecialchars($patient['calling_name']); ?></h4>
                                    <p class="patient-full-name"><?php echo htmlspecialchars($patient['full_name']); ?></p>
                                </div>
                                <div class="card-action">
                                    <span class="action-btn">
                                        <span class="action-text">View History</span>
                                        <span class="action-arrow">→</span>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="card-content">
                                <div class="info-grid">
                                    <div class="info-item">
                                        <span class="info-icon">🏷️</span>
                                        <div class="info-text">
                                            <span class="info-label">Clinic Number</span>
                                            <span class="info-value"><?php echo htmlspecialchars($patient['clinic_number']); ?></span>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-icon">🏥</span>
                                        <div class="info-text">
                                            <span class="info-label">Hospital Number</span>
                                            <span class="info-value"><?php echo htmlspecialchars($patient['hospital_number'] ?: 'Not assigned'); ?></span>
                                        </div>
                                    </div>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-icon">🏇</span>
                                        <div class="info-text">
                                            <span class="info-label">NIC Number</span>
                                            <span class="info-value"><?php echo htmlspecialchars($patient['nic']); ?></span>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-icon">🎂</span>
                                        <div class="info-text">
                                            <span class="info-label">Age</span>
                                            <span class="info-value"><?php echo $patient['age']; ?> years</span>
                                        </div>
                                    </div>
                                    <?php if (!empty($patient['contact_number'])): ?>
                                    <div class="info-item">
                                        <span class="info-icon">📞</span>
                                        <div class="info-text">
                                            <span class="info-label">Contact</span>
                                            <span class="info-value"><?php echo htmlspecialchars($patient['contact_number']); ?></span>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($patient['nursing_name'])): ?>
                                    <div class="info-item">
                                        <span class="info-icon">👩‍⚕️</span>
                                        <div class="info-text">
                                            <span class="info-label">Nursing Officer</span>
                                            <span class="info-value"><?php echo htmlspecialchars($patient['nursing_name']); ?></span>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-footer">
                                    <span class="footer-text">Date of Birth: <?php echo $patient['formatted_dob']; ?></span>
                                    <span class="footer-badge">✨ Detailed Report Available</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-results-modern">
                    <div class="no-results-icon">🔍</div>
                    <h3>No Patients Found</h3>
                    <p>We couldn't find any patients matching your search criteria.</p>
                    <div class="search-suggestions-inline">
                        <strong>Try these tips:</strong>
                        <div class="tip-chips">
                            <span class="tip-chip">✨ Use partial names</span>
                            <span class="tip-chip">📞 Try phone numbers</span>
                            <span class="tip-chip">🏇 Check NIC format</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    
    <!-- Dashboard Overview -->
    <div class="dashboard-overview">
        <div class="overview-grid">
            <div class="overview-card">
                <div class="card-icon search-icon">🔍</div>
                <div class="card-content">
                    <h3>Quick Search</h3>
                    <p>Start typing any patient information to see instant results with smart autocomplete</p>
                    <ul class="feature-list">
                        <li>👤 Search by name (calling or full name)</li>
                        <li>🏇 Find using NIC number</li>
                        <li>🏥 Locate by hospital/clinic numbers</li>
                        <li>📞 Search with phone numbers</li>
                    </ul>
                </div>
            </div>
            
            <div class="overview-card">
                <div class="card-icon report-icon">📄</div>
                <div class="card-content">
                    <h3>Detailed History Reports</h3>
                    <p>Comprehensive medical history reports with all patient information</p>
                    <ul class="feature-list">
                        <li>👥 Complete patient demographics</li>
                        <li>🏥 Full admission history with doctors</li>
                        <li>🔬 All investigations and lab results</li>
                        <li>💊 Complete medication history</li>
                    </ul>
                </div>
            </div>
            
            <div class="overview-card">
                <div class="card-icon export-icon">📊</div>
                <div class="card-content">
                    <h3>Export & Print</h3>
                    <p>Multiple export options for easy sharing and record keeping</p>
                    <ul class="feature-list">
                        <li>📊 Export to CSV format</li>
                        <li>🖨️ Print-friendly layouts</li>
                        <li>📝 Professional report format</li>
                        <li>💾 Save for offline access</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="quick-actions">
            <h3>⚡ Quick Actions</h3>
            <div class="action-grid">
                <a href="../../pages/patients/patient_list.php" class="quick-action-card">
                    <span class="action-icon">👥</span>
                    <span class="action-title">All Patients</span>
                    <span class="action-desc">Browse complete patient list</span>
                </a>
                <a href="../../pages/reports/" class="quick-action-card">
                    <span class="action-icon">📄</span>
                    <span class="action-title">All Reports</span>
                    <span class="action-desc">Access all report types</span>
                </a>
                <a href="../../pages/admissions/" class="quick-action-card">
                    <span class="action-icon">🏥</span>
                    <span class="action-title">Admissions</span>
                    <span class="action-desc">Current admissions</span>
                </a>
                <a href="../../index.php" class="quick-action-card">
                    <span class="action-icon">🏠</span>
                    <span class="action-title">Dashboard</span>
                    <span class="action-desc">Main dashboard</span>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Enhanced patient search functionality
function viewPatientDetailHistory(patientId) {
    window.location.href = 'patient_history_detail_report.php?patient_id=' + patientId;
}

// Handle patient selection from autocomplete
function handlePatientSelect(patient) {
    window.location.href = 'patient_history_detail_report.php?patient_id=' + patient.patient_id;
}

// Export and action functions
function exportResults() {
    alert('Export functionality - can be implemented based on requirements');
}

function clearSearch() {
    window.location.href = 'patient_detail_search.php';
}

// Enhanced functionality for the modern UI
document.addEventListener('DOMContentLoaded', function() {
    // Focus the search input when page loads
    const searchInput = document.getElementById('patient_search');
    if (searchInput) {
        searchInput.focus();
        
        // Add search input enhancements
        searchInput.addEventListener('focus', function() {
            const container = this.closest('.search-box-container');
            if (container) {
                container.classList.add('focused');
            }
        });
        
        searchInput.addEventListener('blur', function() {
            const container = this.closest('.search-box-container');
            if (container) {
                setTimeout(() => {
                    container.classList.remove('focused');
                }, 200);
            }
        });
    }
</script>

<style>
/* Modern Variables */
:root {
    --primary-color: #667eea;
    --secondary-color: #764ba2;
    --accent-color: #f093fb;
    --success-color: #4CAF50;
    --warning-color: #ff9800;
    --error-color: #f44336;
    --text-dark: #2c3e50;
    --text-light: #7f8c8d;
    --bg-light: #f8f9fa;
    --white: #ffffff;
    --shadow-light: 0 2px 12px rgba(0,0,0,0.08);
    --shadow-medium: 0 8px 30px rgba(0,0,0,0.12);
    --shadow-heavy: 0 20px 60px rgba(0,0,0,0.15);
    --border-radius: 16px;
    --border-radius-small: 8px;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Global Styles */
* {
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', sans-serif;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    margin: 0;
    padding: 0;
    min-height: 100vh;
}

.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
}

/* Hero Header Styles */
.hero-header {
    margin-bottom: 3rem;
}

.hero-background {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    border-radius: var(--border-radius);
    padding: 3rem;
    box-shadow: var(--shadow-heavy);
    position: relative;
    overflow: hidden;
}

.hero-background::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="1.5" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="1" fill="rgba(255,255,255,0.1)"/></svg>');
    opacity: 0.3;
}

.hero-content {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 3rem;
    align-items: center;
    position: relative;
    z-index: 1;
}

.hero-title {
    font-size: 3rem;
    font-weight: 800;
    color: var(--white);
    margin: 0 0 1rem 0;
    line-height: 1.2;
}

.hero-subtitle {
    font-size: 1.2rem;
    color: rgba(255,255,255,0.9);
    margin: 0 0 2rem 0;
    line-height: 1.6;
}

.hero-stats {
    display: flex;
    gap: 2rem;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: rgba(255,255,255,0.9);
    font-weight: 600;
}

.stat-icon {
    font-size: 1.2rem;
}

.hero-actions {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.btn-hero {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 2rem;
    border-radius: var(--border-radius-small);
    text-decoration: none;
    font-weight: 600;
    transition: var(--transition);
    cursor: pointer;
}

.btn-primary {
    background: var(--white);
    color: var(--primary-color);
}

.btn-secondary {
    background: rgba(255,255,255,0.2);
    color: var(--white);
    border: 2px solid rgba(255,255,255,0.3);
}

.btn-hero:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-medium);
}

/* Smart Search Section */
.smart-search-section {
    margin-bottom: 3rem;
}

.search-container-main {
    background: var(--white);
    border-radius: var(--border-radius);
    padding: 2rem;
    box-shadow: var(--shadow-light);
    border: 1px solid rgba(0,0,0,0.05);
}

.search-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.search-title h2 {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0 0 0.5rem 0;
}

.search-title p {
    color: var(--text-light);
    margin: 0;
    font-size: 1rem;
}

.search-filters {
    display: flex;
    gap: 0.75rem;
}

.filter-chip {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    background: var(--bg-light);
    border-radius: 25px;
    cursor: pointer;
    transition: var(--transition);
    font-weight: 500;
    color: var(--text-dark);
    border: 2px solid transparent;
}

.filter-chip.active {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: var(--white);
    transform: translateY(-2px);
    box-shadow: var(--shadow-light);
}

.filter-chip:hover {
    background: var(--primary-color);
    color: var(--white);
    transform: translateY(-1px);
}

.search-box-container {
    position: relative;
}

.search-box-container.focused .search-suggestions {
    opacity: 1;
    transform: translateY(0);
    pointer-events: auto;
}

/* Override patient search component styles for modern design */
.patient-search-container .patient-search-input,
.smart-search-input,
#patient_search {
    width: 100% !important;
    padding: 1.5rem 2rem !important;
    border: 2px solid #e1e8ed !important;
    border-radius: var(--border-radius-small) !important;
    font-size: 1.1rem !important;
    transition: var(--transition) !important;
    background: var(--white) !important;
    box-sizing: border-box !important;
}

.patient-search-container .patient-search-input:focus,
.smart-search-input:focus,
#patient_search:focus {
    outline: none !important;
    border-color: var(--primary-color) !important;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1) !important;
}

/* Style the patient search results dropdown */
.patient-search-results {
    background: var(--white) !important;
    border: 2px solid #e1e8ed !important;
    border-top: none !important;
    border-radius: 0 0 var(--border-radius-small) var(--border-radius-small) !important;
    box-shadow: var(--shadow-medium) !important;
    max-height: 400px !important;
    z-index: 1000 !important;
}

.patient-search-result-item {
    padding: 1rem 1.5rem !important;
    cursor: pointer !important;
    transition: var(--transition) !important;
    border-bottom: 1px solid #f0f0f0 !important;
}

.patient-search-result-item:hover {
    background: linear-gradient(135deg, #f8f9fa, #ffffff) !important;
    border-left: 4px solid var(--primary-color) !important;
}

.patient-search-result-item:last-child {
    border-bottom: none !important;
}

/* Ensure the search box container works with the component */
.search-box-container .patient-search-container {
    position: relative;
    width: 100%;
}

/* Fallback styles in case external CSS doesn't load */
.patient-search-container {
    position: relative;
    width: 100%;
}

.patient-search-input {
    width: 100%;
    padding: 1.5rem 2rem;
    border: 2px solid #e1e8ed;
    border-radius: 8px;
    font-size: 1.1rem;
    transition: all 0.3s ease;
    background: white;
    box-sizing: border-box;
}

.patient-search-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 2px solid #e1e8ed;
    border-top: none;
    border-radius: 0 0 8px 8px;
    max-height: 400px;
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
}

.patient-search-result-item {
    padding: 1rem 1.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    border-bottom: 1px solid #f0f0f0;
}

.patient-search-result-item:hover {
    background: #f8f9fa;
    border-left: 4px solid var(--primary-color, #667eea);
}

.patient-search-result-item:last-child {
    border-bottom: none;
}

.search-suggestions {
    margin-top: 1.5rem;
    padding: 1.5rem;
    background: var(--bg-light);
    border-radius: var(--border-radius-small);
    border: 1px solid #e1e8ed;
    opacity: 0.7;
    transform: translateY(-10px);
    transition: var(--transition);
    pointer-events: none;
}

.suggestion-category h4 {
    color: var(--text-dark);
    margin: 0 0 1rem 0;
    font-size: 1rem;
    font-weight: 600;
}

.suggestion-items {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.suggestion-item {
    background: var(--white);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    color: var(--text-light);
    font-size: 0.9rem;
    border: 1px solid #e1e8ed;
}

/* Results Section Styles */
.results-section {
    margin-bottom: 3rem;
}

.results-container {
    background: var(--white);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-light);
    overflow: hidden;
}

.results-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 2rem;
    background: linear-gradient(135deg, var(--bg-light), #ffffff);
    border-bottom: 1px solid #e1e8ed;
}

.results-info h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0 0 0.5rem 0;
}

.results-count {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: var(--white);
    padding: 0.5rem 1.25rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
}

.results-actions {
    display: flex;
    gap: 1rem;
}

.btn-action {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    background: var(--white);
    border: 2px solid #e1e8ed;
    border-radius: var(--border-radius-small);
    color: var(--text-dark);
    cursor: pointer;
    transition: var(--transition);
    font-weight: 500;
}

.btn-action:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
    transform: translateY(-2px);
    box-shadow: var(--shadow-light);
}

.action-icon {
    font-size: 1rem;
}

/* Modern Patient Cards */
.patients-grid-modern {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 2rem;
    padding: 2rem;
}

.patient-card-modern {
    background: var(--white);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-light);
    border: 1px solid rgba(0,0,0,0.05);
    cursor: pointer;
    transition: var(--transition);
    overflow: hidden;
    position: relative;
}

.patient-card-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    transform: scaleY(0);
    transition: var(--transition);
}

.patient-card-modern:hover::before {
    transform: scaleY(1);
}

.patient-card-modern:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-heavy);
}

.card-header {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    padding: 2rem 2rem 1rem;
    border-bottom: 1px solid var(--bg-light);
}

.patient-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
    font-weight: 700;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.patient-title {
    flex: 1;
}

.patient-name {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0 0 0.25rem 0;
}

.patient-full-name {
    color: var(--text-light);
    margin: 0;
    font-size: 0.95rem;
}

.card-action {
    opacity: 0;
    transition: var(--transition);
}

.patient-card-modern:hover .card-action {
    opacity: 1;
}

.action-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--primary-color);
    font-weight: 600;
    font-size: 0.9rem;
}

.action-arrow {
    transition: var(--transition);
}

.patient-card-modern:hover .action-arrow {
    transform: translateX(4px);
}

.card-content {
    padding: 1rem 2rem 2rem;
}

.info-grid {
    display: grid;
    gap: 1.25rem;
    margin-bottom: 1.5rem;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.info-icon {
    font-size: 1.2rem;
    width: 24px;
    text-align: center;
}

.info-text {
    flex: 1;
}

.info-label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-light);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.25rem;
}

.info-value {
    display: block;
    font-size: 0.95rem;
    font-weight: 500;
    color: var(--text-dark);
}

.card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 1rem;
    border-top: 1px solid var(--bg-light);
}

.footer-text {
    font-size: 0.85rem;
    color: var(--text-light);
}

.footer-badge {
    background: linear-gradient(135deg, var(--success-color), #66bb6a);
    color: var(--white);
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.75rem;
    font-weight: 600;
}

/* No Results Styles */
.no-results-modern {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--white);
}

.no-results-icon {
    font-size: 4rem;
    margin-bottom: 1.5rem;
    opacity: 0.5;
}

.no-results-modern h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0 0 1rem 0;
}

.no-results-modern p {
    color: var(--text-light);
    font-size: 1rem;
    margin: 0 0 2rem 0;
}

.search-suggestions-inline {
    background: var(--bg-light);
    padding: 1.5rem;
    border-radius: var(--border-radius-small);
    display: inline-block;
    text-align: left;
}

.tip-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-top: 1rem;
}

.tip-chip {
    background: var(--white);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.85rem;
    color: var(--text-dark);
    border: 1px solid #e1e8ed;
}

/* Dashboard Overview Styles */
.dashboard-overview {
    background: var(--white);
    border-radius: var(--border-radius);
    padding: 3rem;
    box-shadow: var(--shadow-light);
    margin-bottom: 2rem;
}

.overview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 2rem;
    margin-bottom: 3rem;
}

.overview-card {
    background: linear-gradient(135deg, var(--bg-light), #ffffff);
    border-radius: var(--border-radius);
    padding: 2rem;
    border: 1px solid rgba(0,0,0,0.05);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.overview-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
}

.overview-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-medium);
}

.card-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--white);
    margin-bottom: 1.5rem;
}

.search-icon {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
}

.report-icon {
    background: linear-gradient(135deg, var(--success-color), #66bb6a);
}

.export-icon {
    background: linear-gradient(135deg, var(--warning-color), #ffb74d);
}

.card-content h3 {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0 0 1rem 0;
}

.card-content p {
    color: var(--text-light);
    margin: 0 0 1.5rem 0;
    line-height: 1.6;
}

.feature-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.feature-list li {
    padding: 0.5rem 0;
    color: var(--text-dark);
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.feature-list li:before {
    content: '✓';
    color: var(--success-color);
    font-weight: bold;
    font-size: 1rem;
}

/* Quick Actions */
.quick-actions {
    border-top: 1px solid var(--bg-light);
    padding-top: 2rem;
}

.quick-actions h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0 0 1.5rem 0;
}

.action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}

.quick-action-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 1.5rem;
    background: var(--white);
    border: 2px solid var(--bg-light);
    border-radius: var(--border-radius-small);
    text-decoration: none;
    transition: var(--transition);
    color: var(--text-dark);
}

.quick-action-card:hover {
    border-color: var(--primary-color);
    transform: translateY(-4px);
    box-shadow: var(--shadow-light);
}

.quick-action-card .action-icon {
    font-size: 2rem;
    margin-bottom: 1rem;
    opacity: 0.8;
}

.action-title {
    font-weight: 600;
    font-size: 1rem;
    margin-bottom: 0.5rem;
}

.action-desc {
    font-size: 0.85rem;
    color: var(--text-light);
}

/* Responsive Design */
@media (max-width: 1024px) {
    .hero-content {
        grid-template-columns: 1fr;
        text-align: center;
        gap: 2rem;
    }
    
    .hero-actions {
        flex-direction: row;
        justify-content: center;
    }
    
    .patients-grid-modern {
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 1.5rem;
    }
}

@media (max-width: 768px) {
    .container {
        padding: 1rem;
    }
    
    .hero-background {
        padding: 2rem 1.5rem;
    }
    
    .hero-title {
        font-size: 2.2rem;
    }
    
    .hero-stats {
        flex-direction: column;
        gap: 1rem;
    }
    
    .search-container-main {
        padding: 1.5rem;
    }
    
    .search-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1.5rem;
    }
    
    .search-filters {
        flex-wrap: wrap;
    }
    
    .results-toolbar {
        flex-direction: column;
        align-items: flex-start;
        gap: 1.5rem;
    }
    
    .results-actions {
        width: 100%;
        justify-content: stretch;
    }
    
    .btn-action {
        flex: 1;
    }
    
    .patients-grid-modern {
        grid-template-columns: 1fr;
        gap: 1rem;
        padding: 1rem;
    }
    
    .card-header {
        padding: 1.5rem 1.5rem 1rem;
    }
    
    .card-content {
        padding: 1rem 1.5rem 1.5rem;
    }
    
    .overview-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .dashboard-overview {
        padding: 2rem 1.5rem;
    }
}

@media (max-width: 480px) {
    .hero-title {
        font-size: 1.8rem;
    }
    
    .hero-actions {
        flex-direction: column;
    }
    
    .btn-hero {
        padding: 0.875rem 1.5rem;
    }
    
    .patient-avatar {
        width: 50px;
        height: 50px;
        font-size: 1rem;
    }
    
    .patient-name {
        font-size: 1.1rem;
    }
    
    .action-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Print Styles */
@media print {
    .hero-header,
    .smart-search-section,
    .dashboard-overview {
        display: none !important;
    }
    
    .results-toolbar {
        background: var(--white) !important;
    }
    
    .patient-card-modern {
        break-inside: avoid;
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
}

.subtitle {
    margin: 0.5rem 0 0 0;
    font-size: 1.1rem;
    opacity: 0.9;
}

.action-buttons {
    display: flex;
    gap: 1rem;
}

.btn {
    padding: 0.75rem 1.5rem;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border: none;
    cursor: pointer;
    font-size: 0.9rem;
}

.btn-outline {
    background: rgba(255,255,255,0.2);
    color: white;
    border: 2px solid rgba(255,255,255,0.3);
}

.btn-outline:hover {
    background: rgba(255,255,255,0.3);
    transform: translateY(-2px);
}

.btn-search {
    background: linear-gradient(135deg, #2e7d32, #1b5e20);
    color: white;
}

.btn-search:hover {
    background: linear-gradient(135deg, #1b5e20, #0d4f17);
    transform: translateY(-2px);
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1rem;
}

/* Search Section */
.search-section {
    margin-bottom: 2rem;
}

.search-card {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    border: 1px solid #e9ecef;
}

.search-card h3 {
    color: #2c3e50;
    margin-bottom: 1.5rem;
    font-size: 1.5rem;
}

.search-form {
    position: relative;
}

.search-input-group {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.search-input {
    flex: 1;
    padding: 1rem 1.5rem;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}

.search-input:focus {
    outline: none;
    border-color: #2e7d32;
    box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);
}

/* Search Container */
.search-container {
    position: relative;
    flex: 1;
}

/* Search Results Dropdown */
.search-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    border-top: none;
    border-radius: 0 0 8px 8px;
    max-height: 300px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.search-result-item {
    padding: 1rem;
    border-bottom: 1px solid #eee;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.search-result-item:hover {
    background-color: #f8f9fa;
}

.search-result-item:last-child {
    border-bottom: none;
}

.result-name {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 0.25rem;
}

.result-details {
    display: flex;
    gap: 0.75rem;
    font-size: 0.875rem;
    color: #666;
    flex-wrap: wrap;
}

.result-nic, .result-clinic, .result-hospital, .result-nursing {
    background: #f8f9fa;
    padding: 0.125rem 0.5rem;
    border-radius: 4px;
    white-space: nowrap;
}

/* Results Section */
.results-section {
    margin-bottom: 2rem;
}

.results-header {
    background: white;
    padding: 1.5rem 2rem;
    border-radius: 12px 12px 0 0;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0;
}

.results-header h3 {
    color: #2c3e50;
    margin: 0;
}

.results-count {
    color: #666;
    font-weight: 600;
    background: #f8f9fa;
    padding: 0.5rem 1rem;
    border-radius: 20px;
}

/* Patient Cards */
.patients-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    padding: 2rem;
    background: white;
    border-radius: 0 0 12px 12px;
}

.patient-card {
    background: white;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 1.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.patient-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.1);
    border-color: #2e7d32;
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

.view-detail-btn {
    color: #2e7d32;
    font-weight: 600;
    font-size: 0.9rem;
    opacity: 0.7;
    transition: opacity 0.3s ease;
}

.patient-card:hover .view-detail-btn {
    opacity: 1;
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

.label {
    font-weight: 600;
    color: #495057;
}

.value {
    color: #2c3e50;
    text-align: right;
}

.detail-row .value {
    text-align: left;
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

.badge-green {
    background: linear-gradient(135deg, #e8f5e8, #c8e6c9);
    color: #2e7d32;
}

/* Help Section */
.search-help {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.help-content h4 {
    color: #2c3e50;
    margin-bottom: 1.5rem;
    font-size: 1.5rem;
}

.help-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.help-item {
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #2e7d32;
}

.help-item strong {
    color: #2e7d32;
    display: block;
    margin-bottom: 0.5rem;
}

/* Feature Highlight */
.feature-highlight {
    margin-top: 2rem;
    padding: 1.5rem;
    background: linear-gradient(135deg, #e8f5e8, #f1f8e9);
    border-radius: 8px;
    border: 1px solid #c8e6c9;
}

.feature-highlight h5 {
    color: #2e7d32;
    margin-bottom: 1rem;
    font-size: 1.2rem;
}

.feature-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.feature-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: white;
    border-radius: 6px;
    color: #2c3e50;
}

.feature-item .icon {
    font-size: 1.2rem;
}

/* No Results */
.no-results {
    padding: 3rem 2rem;
    text-align: center;
    background: white;
    border-radius: 0 0 12px 12px;
}

.no-results-content h4 {
    color: #2c3e50;
    margin-bottom: 1rem;
}

.search-tips {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    margin-top: 1rem;
    text-align: left;
    display: inline-block;
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
    
    .search-results {
        right: 0;
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

/* Patient Search Specific Styles */
.patient-search-container {
    position: relative;
    width: 100%;
}

.smart-search-input {
    width: 100%;
    padding: 16px 20px;
    font-size: 16px;
    border: 2px solid var(--border-color);
    border-radius: 25px;
    background: white;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.smart-search-input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 6px 25px rgba(74, 144, 226, 0.3);
    transform: translateY(-2px);
}

.search-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    z-index: 1000;
    max-height: 400px;
    overflow-y: auto;
    margin-top: 5px;
}

.search-result-item {
    padding: 15px 20px;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.search-result-item:hover {
    background-color: #f8f9ff;
}

.search-result-item:last-child {
    border-bottom: none;
}

.result-name {
    font-weight: 600;
    color: var(--text-color);
    margin-bottom: 5px;
}

.result-details {
    font-size: 14px;
    color: var(--text-secondary);
}

.result-highlight {
    background-color: #fff3cd;
    padding: 2px 4px;
    border-radius: 3px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('patient-search');
    const resultsContainer = document.getElementById('search-results');
    let searchTimeout;

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.trim();
            
            clearTimeout(searchTimeout);
            
            if (searchTerm.length >= 2) {
                searchTimeout = setTimeout(() => {
                    performSearch(searchTerm);
                }, 300);
            } else {
                hideResults();
            }
        });

        // Hide results when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !resultsContainer.contains(e.target)) {
                hideResults();
            }
        });
    }

    function performSearch(term) {
        fetch(`?ajax_search=1&term=${encodeURIComponent(term)}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('Search error:', data.error);
                    return;
                }
                
                displayResults(data, term);
            })
            .catch(error => {
                console.error('Search failed:', error);
            });
    }

    function displayResults(patients, searchTerm) {
        if (patients.length === 0) {
            resultsContainer.innerHTML = '<div class="search-result-item"><div class="result-name">No patients found</div></div>';
            showResults();
            return;
        }

        const html = patients.map(patient => {
            const name = highlightTerm(patient.calling_name || patient.full_name, searchTerm);
            const details = [
                patient.nic ? `NIC: ${patient.nic}` : '',
                patient.hospital_number ? `Hospital: ${patient.hospital_number}` : '',
                patient.clinic_number ? `Clinic: ${patient.clinic_number}` : '',
                patient.contact_number ? `Contact: ${patient.contact_number}` : '',
                patient.nursing_name ? `Nursing Officer: ${patient.nursing_name}` : '',
                patient.age ? `Age: ${patient.age}` : ''
            ].filter(d => d).join(' | ');

            return `
                <div class="search-result-item" onclick="selectPatient(${patient.patient_id})">
                    <div class="result-name">${name}</div>
                    <div class="result-details">${details}</div>
                </div>
            `;
        }).join('');

        resultsContainer.innerHTML = html;
        showResults();
    }

    function highlightTerm(text, term) {
        if (!text || !term) return text || '';
        const regex = new RegExp(`(${term})`, 'gi');
        return text.replace(regex, '<span class="result-highlight">$1</span>');
    }

    function showResults() {
        resultsContainer.style.display = 'block';
    }

    function hideResults() {
        resultsContainer.style.display = 'none';
    }

    // Global function for patient selection
    window.selectPatient = function(patientId) {
        window.location.href = `patient_history_detail_report.php?patient_id=${patientId}`;
    };
});
</script>

<?php include '../../includes/footer.php'; ?>