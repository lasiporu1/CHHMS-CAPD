<?php
// CAPD Status Data Cleanup Script
// This script cleans up any invalid or empty CAPD start dates

include '../../config/db.php';
include '../../includes/header.php';

// Check for invalid dates
$invalid_dates_query = "SELECT capd_id, patient_id, catheter_insertion_date, capd_start_date 
                        FROM capd_status 
                        WHERE capd_start_date = '0000-00-00' 
                           OR capd_start_date = ''
                           OR capd_start_date IS NULL";

$result = $conn->query($invalid_dates_query);
$invalid_count = $result->num_rows;

// If cleanup is requested
if (isset($_POST['cleanup']) && $_POST['cleanup'] == 'yes') {
    // Set all invalid dates to NULL
    $cleanup_query = "UPDATE capd_status 
                      SET capd_start_date = NULL 
                      WHERE capd_start_date = '0000-00-00' 
                         OR capd_start_date = ''";
    
    if ($conn->query($cleanup_query)) {
        $message = "✅ Cleanup completed! Updated {$conn->affected_rows} records.";
        $message_type = 'success';
    } else {
        $message = "❌ Error during cleanup: " . $conn->error;
        $message_type = 'error';
    }
}
?>

<div class="container">
    <div class="header-section">
        <div class="header-content">
            <div class="title-section">
                <h1 class="main-title">🔧 CAPD Data Cleanup</h1>
                <p class="subtitle">Clean up invalid or empty CAPD Start Dates</p>
            </div>
            <div class="action-buttons">
                <a href="capd_status_report.php" class="btn btn-outline">← Back to Report</a>
                <a href="../../index.php" class="btn btn-outline">🏠 Dashboard</a>
            </div>
        </div>
    </div>

    <div class="card">
        <?php if (isset($message)): ?>
            <div style="padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; background: <?php echo $message_type == 'success' ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $message_type == 'success' ? '#155724' : '#721c24'; ?>; border: 1px solid <?php echo $message_type == 'success' ? '#c3e6cb' : '#f5c6cb'; ?>;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <h3>Invalid CAPD Start Dates Found: <strong><?php echo $invalid_count; ?></strong></h3>
        
        <?php if ($invalid_count > 0): ?>
            <div style="margin-bottom: 2rem;">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>CAPD ID</th>
                            <th>Patient ID</th>
                            <th>Catheter Insertion Date</th>
                            <th>CAPD Start Date (Invalid)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['capd_id']; ?></td>
                                <td><?php echo $row['patient_id']; ?></td>
                                <td><?php echo $row['catheter_insertion_date']; ?></td>
                                <td>
                                    <span style="background: #ffebee; color: #c62828; padding: 0.3rem 0.6rem; border-radius: 4px; font-weight: 600;">
                                        <?php echo $row['capd_start_date'] ?: '(empty)'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <form method="POST" action="" style="margin-top: 2rem;">
                <div style="padding: 1rem; background: #fff3cd; border-radius: 8px; border: 1px solid #ffc107; margin-bottom: 1.5rem;">
                    <p style="margin: 0; color: #856404;"><strong>⚠️ Warning:</strong> This will set all invalid dates to NULL (empty/not started)</p>
                </div>
                
                <button type="submit" name="cleanup" value="yes" style="padding: 0.75rem 1.5rem; background: #28a745; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 1rem;">
                    ✅ Proceed with Cleanup
                </button>
                <a href="capd_status_report.php" style="padding: 0.75rem 1.5rem; background: #6c757d; color: white; border: none; border-radius: 8px; text-decoration: none; display: inline-block; font-weight: 600;">
                    ❌ Cancel
                </a>
            </form>
        <?php else: ?>
            <div style="padding: 2rem; background: #d4edda; border-radius: 8px; text-align: center; color: #155724;">
                <h3>✅ All CAPD Start Dates are valid!</h3>
                <p>No cleanup needed.</p>
                <a href="capd_status_report.php" class="btn btn-primary" style="margin-top: 1rem;">Back to Report</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

.header-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    border-radius: 12px;
    margin-bottom: 2rem;
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 2rem;
}

.title-section h1 {
    margin: 0;
    font-size: 2rem;
}

.title-section p {
    margin: 0.5rem 0 0 0;
    opacity: 0.9;
}

.action-buttons {
    display: flex;
    gap: 1rem;
}

.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-outline {
    background: rgba(255,255,255,0.2);
    color: white;
    border: 2px solid white;
}

.btn-outline:hover {
    background: white;
    color: #667eea;
}

.btn-primary {
    background: #667eea;
    color: white;
    border: none;
}

.btn-primary:hover {
    background: #5568d3;
}

.card {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.modern-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.modern-table thead {
    background: #f8f9fa;
}

.modern-table th {
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: #2c3e50;
    border-bottom: 2px solid #ddd;
}

.modern-table td {
    padding: 1rem;
    border-bottom: 1px solid #eee;
}

.modern-table tbody tr:hover {
    background: #f8f9fa;
}
</style>

<?php include '../../includes/footer.php'; ?>
