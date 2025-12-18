<?php
// pages/reports/detail_counselling_report.php
// Detail Counselling Report with filters: date range, Nursing Officer

include_once '../../config/db.php';
include_once '../../includes/header.php';

// Fetch Nursing Officers for filter dropdown
$nursingOfficers = [];
$officerQuery = "SELECT nursing_id, nursing_name FROM nursing_officers ORDER BY nursing_name";
$officerResult = mysqli_query($conn, $officerQuery);
if ($officerResult) {
    while ($row = mysqli_fetch_assoc($officerResult)) {
        $nursingOfficers[] = $row;
    }
}

// Handle filters
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$nursingOfficer = isset($_GET['nursing_officer']) ? $_GET['nursing_officer'] : '';

// Build query for all three counselling slots
$where1 = [];
if ($dateFrom) $where1[] = "cs.first_counselling_date >= '" . mysqli_real_escape_string($conn, $dateFrom) . "'";
if ($dateTo) $where1[] = "cs.first_counselling_date <= '" . mysqli_real_escape_string($conn, $dateTo) . "'";
if ($nursingOfficer) $where1[] = "cs.first_nursing_officer_id = '" . mysqli_real_escape_string($conn, $nursingOfficer) . "'";
$whereSql1 = $where1 ? ('WHERE ' . implode(' AND ', $where1)) : '';

$where2 = [];
if ($dateFrom) $where2[] = "cs.second_counselling_date >= '" . mysqli_real_escape_string($conn, $dateFrom) . "'";
if ($dateTo) $where2[] = "cs.second_counselling_date <= '" . mysqli_real_escape_string($conn, $dateTo) . "'";
if ($nursingOfficer) $where2[] = "cs.second_nursing_officer_id = '" . mysqli_real_escape_string($conn, $nursingOfficer) . "'";
$whereSql2 = $where2 ? ('WHERE ' . implode(' AND ', $where2)) : '';

$where3 = [];
if ($dateFrom) $where3[] = "cs.third_counselling_date >= '" . mysqli_real_escape_string($conn, $dateFrom) . "'";
if ($dateTo) $where3[] = "cs.third_counselling_date <= '" . mysqli_real_escape_string($conn, $dateTo) . "'";
if ($nursingOfficer) $where3[] = "cs.third_nursing_officer_id = '" . mysqli_real_escape_string($conn, $nursingOfficer) . "'";
$whereSql3 = $where3 ? ('WHERE ' . implode(' AND ', $where3)) : '';

// Main data query: flatten all counselling slots into rows
$sql = "
SELECT cs.counselling_id, cs.patient_id, p.calling_name, p.full_name, p.clinic_number,
    cs.first_counselling_date AS counselling_date, cs.notes, no1.nursing_name AS nursing_officer
FROM counselling_status cs
LEFT JOIN patients p ON cs.patient_id = p.patient_id
LEFT JOIN nursing_officers no1 ON cs.first_nursing_officer_id = no1.nursing_id
$whereSql1
UNION ALL
SELECT cs.counselling_id, cs.patient_id, p.calling_name, p.full_name, p.clinic_number,
    cs.second_counselling_date AS counselling_date, cs.notes, no2.nursing_name AS nursing_officer
FROM counselling_status cs
LEFT JOIN patients p ON cs.patient_id = p.patient_id
LEFT JOIN nursing_officers no2 ON cs.second_nursing_officer_id = no2.nursing_id
$whereSql2
UNION ALL
SELECT cs.counselling_id, cs.patient_id, p.calling_name, p.full_name, p.clinic_number,
    cs.third_counselling_date AS counselling_date, cs.notes, no3.nursing_name AS nursing_officer
FROM counselling_status cs
LEFT JOIN patients p ON cs.patient_id = p.patient_id
LEFT JOIN nursing_officers no3 ON cs.third_nursing_officer_id = no3.nursing_id
$whereSql3
ORDER BY counselling_date DESC";
$result = mysqli_query($conn, $sql);
?>


<div class="container">
    <!-- Header Section -->
    <div class="header-section">
        <div class="header-content">
            <div class="title-section">
                <h1 class="main-title">🗣️ Detail Counselling Report</h1>
                <p class="subtitle">Detailed counselling sessions with filters</p>
            </div>
            <div class="action-buttons">
                <button onclick="window.print()" class="btn btn-export">🖨️ Print</button>
                <a href="report_list.php" class="btn btn-outline">← Back to Reports</a>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="report-section">
        <div class="section-header">
            <h2>🔎 Filter Counselling Records</h2>
        </div>
        <form method="get" class="filter-form">
            <div class="filter-row">
                <label for="date_from">Date From:</label>
                <input type="date" name="date_from" id="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                <label for="date_to">Date To:</label>
                <input type="date" name="date_to" id="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                <label for="nursing_officer">Nursing Officer:</label>
                <select name="nursing_officer" id="nursing_officer">
                    <option value="">All</option>
                    <?php foreach ($nursingOfficers as $officer): ?>
                        <option value="<?= htmlspecialchars($officer['nursing_id']) ?>" <?= $nursingOfficer == $officer['nursing_id'] ? 'selected' : '' ?>><?= htmlspecialchars($officer['nursing_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
        </form>
    </div>

    <!-- Counselling Table Section -->
    <div class="report-section">
        <div class="section-header">
            <h2>📋 Counselling Records</h2>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Counselling Date</th>
                        <th>Remarks</th>
                        <th>Patient Calling Name</th>
                        <th>Patient Full Name</th>
                        <th>Clinic Number</th>
                        <th>Nursing Officer</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && mysqli_num_rows($result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <?php if (!empty($row['counselling_date'])): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['counselling_date']) ?></td>
                                <td><?= htmlspecialchars($row['notes']) ?></td>
                                <td><?= htmlspecialchars($row['calling_name']) ?></td>
                                <td><?= htmlspecialchars($row['full_name']) ?></td>
                                <td><?= htmlspecialchars($row['clinic_number']) ?></td>
                                <td><?= htmlspecialchars($row['nursing_officer']) ?></td>
                            </tr>
                            <?php endif; ?>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6">No records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
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
.filter-row label {
    font-weight: 600;
    color: #6c757d;
    font-size: 0.95rem;
}
.filter-row input,
.filter-row select {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    border: 1px solid #ced4da;
    font-size: 1rem;
}
.table-responsive {
    padding: 0 2rem 2rem;
    overflow-x: auto;
}
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.95rem;
    min-width: 900px;
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
}
@media print {
    .action-buttons { display: none !important; }
    .header-section { background: white !important; color: black !important; }
    .data-table th { background: #f8f9fa !important; color: black !important; }
}
</style>

<?php
include_once '../../includes/footer.php';
?>
