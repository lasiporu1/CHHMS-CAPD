<?php
// Improved Counselling Report — patient search + patient-specific counselling history
include '../../config/db.php';

// ensure session + auth for both normal and AJAX requests
if (session_status() == PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    if (isset($_GET['ajax_patient_counselling'])) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    } else {
        header("Location: login.php");
        exit();
    }
}

// only include the full header when rendering the normal page (not for AJAX)
if (!isset($_GET['ajax_patient_counselling'])) include '../../includes/header.php';

// AJAX: return counselling rows for a patient
if (isset($_GET['ajax_patient_counselling']) && !empty($_GET['patient_id'])) {
    $pid = (int)$_GET['patient_id'];
    $sql = "SELECT cs.*, 
                no1.nursing_name AS nurse1, no2.nursing_name AS nurse2, no3.nursing_name AS nurse3
            FROM counselling_status cs
            LEFT JOIN nursing_officers no1 ON cs.first_nursing_officer_id = no1.nursing_id
            LEFT JOIN nursing_officers no2 ON cs.second_nursing_officer_id = no2.nursing_id
            LEFT JOIN nursing_officers no3 ON cs.third_nursing_officer_id = no3.nursing_id
            WHERE cs.patient_id = $pid
            ORDER BY cs.first_counselling_date DESC, cs.created_at DESC";
    $res = $conn->query($sql);
    $out = [];
    while ($r = $res->fetch_assoc()) $out[] = $r;
    header('Content-Type: application/json');
    echo json_encode($out);
    exit();
}

// If patient_id provided non-AJAX: show patient-specific page
$patient = null;
if (isset($_GET['patient_id']) && !empty($_GET['patient_id'])) {
    $pid = (int)$_GET['patient_id'];
    $p_res = $conn->query("SELECT calling_name, full_name, nic, hospital_number FROM patients WHERE patient_id = $pid LIMIT 1");
    if ($p_res && $p_res->num_rows) $patient = $p_res->fetch_assoc();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Counselling Report</title>
    <style>
        :root{--primary:#6a1b9a;--accent:#8e24aa;--muted:#6c757d}
        body{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);font-family:Segoe UI,Helvetica,Arial,sans-serif;color:#222}
        .container{max-width:1200px;margin:2rem auto;padding:1rem}
        .page-header{background:linear-gradient(135deg,var(--accent),#7b1fa2);color:white;padding:1rem 1.25rem;border-radius:10px;box-shadow:0 8px 30px rgba(0,0,0,0.12);display:flex;align-items:center;justify-content:space-between}
        .page-header h2{margin:0;font-size:1.4rem}
        .search-box{display:flex;gap:.5rem;margin-top:1rem}
        .patient-card{padding:1rem;border:1px solid rgba(255,255,255,0.15);border-radius:8px;margin-bottom:.75rem;background:rgba(255,255,255,0.06);cursor:pointer;display:block;text-decoration:none;color:inherit}
        .results{margin-top:1rem}
        .panel{background:white;border-radius:10px;padding:1rem;margin-top:1rem;box-shadow:0 8px 30px rgba(0,0,0,0.06)}
        table{width:100%;border-collapse:collapse;background:#fff;border-radius:6px;overflow:hidden}
        th,td{padding:.65rem;border-bottom:1px solid #eef2f6;text-align:left}
        th{background:#f7f9fb;font-weight:700}
        tr:nth-child(even) td{background:#fbfcfe}
        .muted{color:var(--muted);font-size:.95rem}
        .btn{padding:.5rem .75rem;border-radius:6px;border:none;cursor:pointer}
        .btn-primary{background:linear-gradient(135deg,#42a5f5,#1e88e5);color:white}
        .btn-ghost{background:transparent;border:1px solid rgba(0,0,0,0.06)}
    </style>
</head>
<body>

<div class="container">
    <!-- Header Section -->
    <div class="header-section">
        <div class="header-content">
            <div class="title-section">
                <h1 class="main-title">🗣️ Counselling Report</h1>
                <p class="subtitle">Search and view patient-specific counselling history</p>
            </div>
            <div class="action-buttons">
                <button onclick="window.print()" class="btn btn-export">🖨️ Print</button>
                <a href="report_list.php" class="btn btn-outline">← Back to Reports</a>
            </div>
        </div>
    </div>

    <!-- Search Section -->
    <div class="report-section">
        <div class="section-header">
            <h2>🔎 Search Patient</h2>
        </div>
        <form class="filter-form" onsubmit="return false;">
            <div class="filter-row">
                <input id="searchTerm" type="text" placeholder="Search patient by name, NIC, hospital number...">
                <button id="clearBtn" class="btn btn-primary" type="button">Clear</button>
            </div>
        </form>
        <div id="searchResults" class="results"></div>
    </div>

    <!-- Patient Counselling Section -->
    <div id="patientPanel" style="margin-top:1.5rem; display:<?php echo $patient ? 'block' : 'none'; ?>;">
        <?php if ($patient): ?>
        <div class="report-section">
            <div class="section-header">
                <h2>👤 Patient Information</h2>
            </div>
            <div class="patient-info-grid">
                <div class="info-card">
                    <div class="info-row">
                        <span class="info-label">Calling Name</span>
                        <span class="info-value"><?php echo htmlspecialchars($patient['calling_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Full Name</span>
                        <span class="info-value"><?php echo htmlspecialchars($patient['full_name']); ?></span>
                    </div>
                </div>
                <div class="info-card">
                    <div class="info-row">
                        <span class="info-label">NIC</span>
                        <span class="info-value"><?php echo htmlspecialchars($patient['nic']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">PHN</span>
                        <span class="info-value"><?php echo htmlspecialchars($patient['hospital_number'] ?: '-'); ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="report-section">
            <div class="section-header">
                <h2>📋 Counselling History</h2>
            </div>
            <div class="table-responsive">
                <?php
                $csql = "SELECT cs.*, 
                            no1.nursing_name AS nurse1, no2.nursing_name AS nurse2, no3.nursing_name AS nurse3
                          FROM counselling_status cs
                          LEFT JOIN nursing_officers no1 ON cs.first_nursing_officer_id = no1.nursing_id
                          LEFT JOIN nursing_officers no2 ON cs.second_nursing_officer_id = no2.nursing_id
                          LEFT JOIN nursing_officers no3 ON cs.third_nursing_officer_id = no3.nursing_id
                          WHERE cs.patient_id = $pid
                          ORDER BY cs.first_counselling_date DESC, cs.created_at DESC";
                $cres = $conn->query($csql);
                if ($cres && $cres->num_rows > 0) {
                    echo '<table class="data-table"><thead><tr><th>Date</th><th>Nursing Officer</th><th>Notes</th><th>Created At</th></tr></thead><tbody>';
                    while ($row = $cres->fetch_assoc()) {
                        $d = $row['first_counselling_date'] ?: ($row['second_counselling_date'] ?: ($row['third_counselling_date'] ?: ''));
                        $n = $row['nurse1'] ?: ($row['nurse2'] ?: ($row['nurse3'] ?: ''));
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($d) . '</td>';
                        echo '<td>' . htmlspecialchars($n) . '</td>';
                        echo '<td>' . htmlspecialchars($row['notes']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['created_at']) . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                } else {
                    echo '<div class="muted">No counselling records for this patient.</div>';
                }
                ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
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
.title-section h1 {
    margin: 0;
    font-size: 2.5rem;
    font-weight: 700;
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
    border: 2px solid transparent;
}
.btn-export {
    background: rgba(255,255,255,0.2);
    color: white;
    border: 2px solid rgba(255,255,255,0.3);
}
.btn-export:hover {
    background: rgba(255,255,255,0.3);
    transform: translateY(-2px);
}
.btn-outline {
    background: transparent;
    color: white;
    border: 2px solid rgba(255,255,255,0.3);
}
.btn-outline:hover {
    background: rgba(255,255,255,0.1);
    border-color: rgba(255,255,255,0.5);
}
.btn-primary {
    background: #6a1b9a;
    color: white;
    border: 2px solid #6a1b9a;
}
.btn-primary:hover {
    background: #4a148c;
    border-color: #4a148c;
}
.report-section {
    background: white;
    margin-bottom: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.1);
    overflow: hidden;
}
.section-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #dee2e6;
}
.section-header h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    color: #495057;
}
.filter-form {
    padding: 2rem;
}
.filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: center;
}
.filter-row input {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    border: 1px solid #ced4da;
    font-size: 1rem;
    flex: 1;
}
.table-responsive {
    padding: 0 2rem 2rem;
    overflow-x: auto;
}
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.95rem;
    min-width: 700px;
}
.data-table th,
.data-table td {
    padding: 1rem 0.75rem;
    text-align: left;
    border-bottom: 1px solid #dee2e6;
    vertical-align: top;
}
.data-table th {
    background: linear-gradient(135deg, #495057 0%, #6c757d 100%);
    color: white;
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    position: sticky;
    top: 0;
    z-index: 10;
}
.data-table tbody tr:hover {
    background-color: #f8f9fa;
}
.data-table td:empty::after {
    content: "";
    display: inline-block;
}
.patient-info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    padding: 2rem;
}
.info-card {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    border-left: 4px solid #007bff;
}
.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}
.info-row:last-child {
    margin-bottom: 0;
}
.info-label {
    font-weight: 600;
    color: #6c757d;
    font-size: 0.9rem;
}
.info-value {
    font-weight: 500;
    color: #212529;
}
@media (max-width: 768px) {
    .header-content {
        flex-direction: column;
        text-align: center;
        gap: 1.5rem;
        padding: 0 1rem;
    }
    .title-section h1 {
        font-size: 2rem;
    }
    .filter-form {
        padding: 1rem;
    }
    .filter-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    .table-responsive {
        padding: 0 1rem 1rem;
    }
    .data-table {
        font-size: 0.85rem;
    }
    .data-table th,
    .data-table td {
        padding: 0.75rem 0.5rem;
    }
    .patient-info-grid {
        grid-template-columns: 1fr;
        padding: 1rem;
    }
}
@media print {
    .action-buttons { display: none !important; }
    .header-section { background: white !important; color: black !important; }
    .data-table th { background: #f8f9fa !important; color: black !important; }
}
</style>

<script>
// Use patient's search AJAX from patient_detail_search.php
const searchInput = document.getElementById('searchTerm');
const results = document.getElementById('searchResults');
let to;
searchInput.addEventListener('input', ()=>{
    clearTimeout(to);
    const t = searchInput.value.trim();
    if (!t || t.length<2) { results.innerHTML=''; return; }
    to = setTimeout(()=>{
        fetch(`patient_detail_search.php?ajax_search=1&term=${encodeURIComponent(t)}`)
            .then(r=>r.json())
            .then(data=>{
                if (!data || data.length==0) { results.innerHTML='<div class="muted">No patients found</div>'; return; }
                results.innerHTML = data.map(p=>{
                    return `<a class="patient-card" data-id="${p.patient_id}" href="?patient_id=${p.patient_id}"><strong>${escapeHtml(p.calling_name)}</strong> (${escapeHtml(p.full_name)})<div class="muted">NIC: ${escapeHtml(p.nic)} | PHN: ${escapeHtml(p.hospital_number||'-')}</div></a>`;
                }).join('');
                // ensure results container is scrolled to top
                results.scrollTop = 0;
            })
            .catch(err=>{ console.error(err); results.innerHTML='<div class="muted">Search error</div>'; });
    },250);
});

document.getElementById('clearBtn').addEventListener('click', ()=>{ searchInput.value=''; results.innerHTML=''; document.getElementById('patientPanel').style.display='none'; document.getElementById('patientCounselling').innerHTML=''; });

// Use event delegation on results container to handle clicks on dynamic patient cards
results.addEventListener('click', function(e){
    const card = e.target.closest('.patient-card');
    if (!card) return;
    const id = card.getAttribute('data-id');
    if (id) loadPatientCounselling(id);
});

window.loadPatientCounselling = function(patientId){
    try {
        const panel = document.getElementById('patientPanel');
        const container = document.getElementById('patientCounselling');
        if (panel) panel.style.display='block';
        if (container) container.innerHTML = '<div class="muted">Loading counselling records...</div>';
        // request the current script with ajax flag (use pathname to avoid relative path issues)
        const url = window.location.pathname + '?ajax_patient_counselling=1&patient_id=' + encodeURIComponent(patientId);
        fetch(url)
            .then(resp => {
                if (!resp.ok) throw new Error('Network response not ok: ' + resp.status);
                const ct = resp.headers.get('content-type') || '';
                if (ct.indexOf('application/json') !== -1) return resp.json();
                // if not JSON, return text so we can show server HTML/error for debugging
                return resp.text().then(text => { throw new Error('Non-JSON response:\n' + text); });
            })
            .then(rows => {
                if (!rows || rows.length === 0) {
                    container.innerHTML = '<div class="muted">No counselling records for this patient.</div>';
                    return;
                }
                let html = '<table><thead><tr><th>Date</th><th>Nursing Officer</th><th>Notes</th><th>Created At</th></tr></thead><tbody>';
                rows.forEach(r => {
                    const d = r.first_counselling_date || r.second_counselling_date || r.third_counselling_date || '';
                    const nurse = r.nurse1 || r.nurse2 || r.nurse3 || '';
                    html += `<tr><td>${escapeHtml(d)}</td><td>${escapeHtml(nurse)}</td><td>${escapeHtml(r.notes||'')}</td><td>${escapeHtml(r.created_at)}</td></tr>`;
                });
                html += '</tbody></table>';
                container.innerHTML = html;
            })
            .catch(err => {
                console.error('loadPatientCounselling error:', err);
                if (container) container.innerHTML = `<div class="muted">Error loading counselling records: ${escapeHtml(err.message || String(err))}</div>`;
            });
    } catch (ex) {
        console.error('loadPatientCounselling outer error:', ex);
    }
};

function changeReportPatient(){
    try {
        // hide patient panel and clear counselling data
        const panel = document.getElementById('patientPanel');
        const container = document.getElementById('patientCounselling');
        if (panel) panel.style.display = 'none';
        if (container) container.innerHTML = '';
        // hide the Change Patient button(s) rendered server-side
        const changeBtn = document.getElementById('changePatientTop');
        if (changeBtn) changeBtn.style.display = 'none';
        // clear patient_id from URL without reloading
        try{
            const url = new URL(window.location.href);
            url.searchParams.delete('patient_id');
            history.replaceState(null, '', url.toString());
        } catch(e) {}
        // focus the search box
        searchInput.focus();
        // clear previous search results so user can search again
        results.innerHTML = '';
    } catch (e) { console.error('changeReportPatient error', e); }
}

function escapeHtml(s){ if(!s) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// If server loaded with patient_id parameter, request its data
<?php if ($patient): ?>
window.addEventListener('DOMContentLoaded', function(){ loadPatientCounselling(<?php echo isset($pid)?(int)$pid:0; ?>); });
<?php endif; ?>
</script>
</body>
</html>
