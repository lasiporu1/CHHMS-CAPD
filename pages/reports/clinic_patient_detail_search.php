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
                    <h1 class="hero-title">📋 Clinic Patient History Search</h1>
                    <p class="hero-subtitle">Find clinic patients quickly and access clinic-specific history reports</p>
                    <div class="hero-stats">
                        <div class="stat-item">
                            <span class="stat-icon">👥</span>
                            <span class="stat-text">All Patients</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-icon">📊</span>
                            <span class="stat-text">Clinic Reports</span>
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
</div>

<script>
// Enhanced patient search functionality
function viewPatientDetailHistory(patientId) {
    window.location.href = 'clinic_patient_history_detail_report.php?patient_id=' + patientId;
}

// Handle patient selection from autocomplete
function handlePatientSelect(patient) {
    window.location.href = 'clinic_patient_history_detail_report.php?patient_id=' + patient.patient_id;
}

// Export and action functions
function exportResults() {
    alert('Export functionality - can be implemented based on requirements');
}

function clearSearch() {
    window.location.href = 'clinic_patient_detail_search.php';
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
});
</script>


<style>
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
    max-width: 1200px;
    margin: 2rem auto;
    padding: 2.5rem 1.5rem 2.5rem 1.5rem;
    background: var(--white);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-light);
}

.hero-header {
    margin-bottom: 2.5rem;
    padding: 2rem 1rem 1.5rem 1rem;
    background: linear-gradient(90deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    border-radius: var(--border-radius);
    color: var(--white);
    box-shadow: var(--shadow-light);
}
.hero-title {
    font-size: 2.2rem;
    margin-bottom: 0.5rem;
    font-weight: 700;
}
.hero-subtitle {
    font-size: 1.1rem;
    margin-bottom: 1.2rem;
    color: #e0e7ff;
}
.hero-stats {
    display: flex;
    gap: 2rem;
    margin-bottom: 1.2rem;
}
.stat-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1rem;
}
.hero-actions {
    margin-top: 1.2rem;
    display: flex;
    gap: 1rem;
}
.btn-hero {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 1.2rem;
    border-radius: var(--border-radius-small);
    font-size: 1rem;
    font-weight: 500;
    text-decoration: none;
    transition: background 0.2s;
    box-shadow: var(--shadow-light);
}
.btn-primary {
    background: var(--accent-color);
    color: var(--white);
    border: none;
}
.btn-primary:hover {
    background: var(--primary-color);
}
.btn-secondary {
    background: var(--white);
    color: var(--primary-color);
    border: 1px solid var(--primary-color);
}
.btn-secondary:hover {
    background: #f0f4ff;
}

.smart-search-section {
    margin-bottom: 2.5rem;
}
.search-container-main {
    background: var(--bg-light);
    border-radius: var(--border-radius);
    padding: 2rem 1.5rem;
    box-shadow: var(--shadow-light);
}
.search-header {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}
.search-title h2 {
    font-size: 1.5rem;
    margin: 0 0 0.3rem 0;
}
.search-title p {
    color: var(--text-light);
    margin: 0;
}
.search-filters {
    display: flex;
    gap: 0.7rem;
}
.filter-chip {
    background: var(--white);
    color: var(--primary-color);
    border: 1px solid var(--primary-color);
    border-radius: var(--border-radius-small);
    padding: 0.3rem 0.9rem;
    font-size: 0.95rem;
    cursor: pointer;
    transition: background 0.2s, color 0.2s;
}
.filter-chip.active, .filter-chip:hover {
    background: var(--primary-color);
    color: var(--white);
}
.search-box-container {
    display: flex;
    flex-wrap: wrap;
    gap: 2rem;
    align-items: flex-start;
}
.patient-search-container {
    flex: 1 1 350px;
    position: relative;
}
.smart-search-input {
    width: 100%;
    padding: 1rem 1.2rem;
    font-size: 1.1rem;
    border: 1.5px solid var(--primary-color);
    border-radius: var(--border-radius-small);
    outline: none;
    margin-bottom: 0.5rem;
    box-shadow: var(--shadow-light);
    transition: border 0.2s;
}
.smart-search-input:focus {
    border: 2px solid var(--secondary-color);
}
.search-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--white);
    border: 1px solid #e0e0e0;
    border-radius: var(--border-radius-small);
    box-shadow: var(--shadow-medium);
    z-index: 10;
    max-height: 320px;
    overflow-y: auto;
    margin-top: 0.2rem;
}
.search-result-item {
    padding: 0.8rem 1.2rem;
    cursor: pointer;
    border-bottom: 1px solid #f0f0f0;
    transition: background 0.15s;
}
.search-result-item:last-child {
    border-bottom: none;
}
.search-result-item:hover {
    background: #f5f7fa;
}
.result-name {
    font-weight: 600;
    color: var(--primary-color);
}
.result-details {
    font-size: 0.97rem;
    color: var(--text-light);
}
.result-highlight {
    background: var(--accent-color);
    color: var(--white);
    border-radius: 3px;
    padding: 0 2px;
}
.search-suggestions {
    flex: 1 1 220px;
    margin-left: 1.5rem;
}
.suggestion-category h4 {
    margin: 0 0 0.5rem 0;
    font-size: 1.1rem;
    color: var(--primary-color);
}
.suggestion-items {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}
.suggestion-item {
    font-size: 0.97rem;
    color: var(--text-light);
    background: #f0f4ff;
    border-radius: 4px;
    padding: 0.2rem 0.6rem;
    display: inline-block;
}

@media (max-width: 900px) {
    .container {
        padding: 1rem;
    }
    .search-box-container {
        flex-direction: column;
        gap: 1.2rem;
    }
    .search-suggestions {
        margin-left: 0;
    }
}

.results-section {
    margin-top: 2.5rem;
    margin-bottom: 2.5rem;
}
.results-toolbar {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.2rem;
    gap: 1rem;
}
.results-info h3 {
    margin: 0 0 0.2rem 0;
    font-size: 1.2rem;
}
.results-count {
    color: var(--text-light);
    font-size: 1rem;
}
.results-actions {
    display: flex;
    gap: 0.7rem;
}
.btn-action {
    background: var(--primary-color);
    color: var(--white);
    border: none;
    border-radius: var(--border-radius-small);
    padding: 0.5rem 1.1rem;
    font-size: 1rem;
    cursor: pointer;
    transition: background 0.2s;
    box-shadow: var(--shadow-light);
}
.btn-action:hover {
    background: var(--secondary-color);
}

.patients-grid-modern {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 2rem;
}
.patient-card-modern {
    background: var(--white);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-light);
    padding: 1.5rem 1.2rem 1.2rem 1.2rem;
    transition: box-shadow 0.2s, transform 0.2s;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.patient-card-modern:hover {
    box-shadow: var(--shadow-medium);
    transform: translateY(-4px) scale(1.02);
}
.card-header {
    display: flex;
    align-items: center;
    gap: 1.2rem;
}
.patient-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: var(--accent-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--white);
    font-weight: 700;
    box-shadow: var(--shadow-light);
}
.patient-title {
    flex: 1;
}
.patient-name {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 600;
    color: var(--primary-color);
}
.patient-full-name {
    margin: 0;
    font-size: 0.98rem;
    color: var(--text-light);
}
.card-action {
    margin-left: auto;
}
.action-btn {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    color: var(--secondary-color);
    font-weight: 500;
    font-size: 1rem;
    transition: color 0.2s;
}
.action-btn:hover {
    color: var(--primary-color);
}
.card-content {
    margin-top: 0.7rem;
}
.info-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 1.2rem 2.5rem;
    margin-bottom: 0.7rem;
}
.info-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.98rem;
    color: var(--text-dark);
}
.info-icon {
    font-size: 1.1rem;
    color: var(--accent-color);
}
.info-label {
    font-weight: 500;
    color: var(--text-light);
    margin-right: 0.2rem;
}
.info-value {
    color: var(--text-dark);
}
.card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.97rem;
    color: var(--text-light);
    margin-top: 0.5rem;
}
.footer-badge {
    background: var(--success-color);
    color: var(--white);
    border-radius: 6px;
    padding: 0.2rem 0.7rem;
    font-size: 0.95rem;
    margin-left: 1rem;
}

.no-results-modern {
    text-align: center;
    padding: 2.5rem 1rem;
    color: var(--text-light);
}
.no-results-icon {
    font-size: 2.5rem;
    margin-bottom: 0.7rem;
}
.search-suggestions-inline {
    margin-top: 1.2rem;
}
.tip-chips {
    display: flex;
    gap: 0.7rem;
    justify-content: center;
    margin-top: 0.5rem;
}
.tip-chip {
    background: #f0f4ff;
    color: var(--primary-color);
    border-radius: 4px;
    padding: 0.2rem 0.7rem;
    font-size: 0.97rem;
}

/* Dashboard Overview and Quick Actions */
.dashboard-overview {
    margin-top: 2.5rem;
}
.overview-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 2rem;
    margin-bottom: 2rem;
}
.overview-card {
    flex: 1 1 300px;
    background: var(--bg-light);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-light);
    padding: 1.5rem 1.2rem;
    display: flex;
    flex-direction: column;
    gap: 0.7rem;
}
.card-icon {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}
.feature-list {
    margin: 0.5rem 0 0 0;
    padding: 0 0 0 1.2rem;
    color: var(--text-light);
    font-size: 0.97rem;
}
.quick-actions {
    margin-top: 1.5rem;
}
.action-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 1.2rem;
}
.quick-action-card {
    flex: 1 1 220px;
    background: var(--primary-color);
    color: var(--white);
    border-radius: var(--border-radius-small);
    padding: 1.2rem 1rem;
    text-decoration: none;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.3rem;
    box-shadow: var(--shadow-light);
    transition: background 0.2s, transform 0.2s;
}
.quick-action-card:hover {
    background: var(--secondary-color);
    transform: translateY(-2px) scale(1.01);
}
.action-icon {
    font-size: 1.3rem;
    margin-bottom: 0.2rem;
}
.action-title {
    font-weight: 600;
    font-size: 1.05rem;
}
.action-desc {
    font-size: 0.97rem;
    color: #e0e7ff;
}

@media (max-width: 700px) {
    .overview-grid, .action-grid, .patients-grid-modern {
        flex-direction: column;
        gap: 1.2rem;
    }
    .container {
        padding: 0.5rem;
    }
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
        window.location.href = `clinic_patient_history_detail_report.php?patient_id=${patientId}`;
    };
});
</script>

<?php include '../../includes/footer.php'; ?>

