<?php
include '../../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Get admission ID from URL
if (!isset($_GET['admission_id']) || empty($_GET['admission_id'])) {
    header("Location: admission_list.php");
    exit();
}

$admission_id = $conn->real_escape_string($_GET['admission_id']);

// Create investigations table if it doesn't exist
$create_table_sql = "CREATE TABLE IF NOT EXISTS investigations (
    investigation_id INT AUTO_INCREMENT PRIMARY KEY,
    admission_id INT NOT NULL,
    investigation_type VARCHAR(100) NOT NULL,
    investigation_name VARCHAR(255) NOT NULL,
    ordered_date DATE NOT NULL,
    ordered_time TIME NOT NULL,
    ordered_by INT NOT NULL,
    sample_collected TINYINT(1) DEFAULT 0,
    collection_date DATE NULL,
    collection_time TIME NULL,
    result_status ENUM('Pending', 'In Progress', 'Completed', 'Cancelled') DEFAULT 'Pending',
    result_date DATE NULL,
    result_time TIME NULL,
    result_values TEXT NULL,
    normal_range VARCHAR(255) NULL,
    interpretation TEXT NULL,
    remarks TEXT NULL,
    urgent TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($create_table_sql);

// Delete investigation if requested
if (isset($_GET['delete'])) {
    $id = $conn->real_escape_string($_GET['delete']);
    $sql = "DELETE FROM investigations WHERE investigation_id = $id AND admission_id = $admission_id";
    if ($conn->query($sql) === TRUE) {
        header("Location: investigations.php?admission_id=$admission_id");
        exit();
    }
}

// Get admission details
$admission_sql = "SELECT wa.*, p.calling_name, p.full_name, p.nic 
                  FROM ward_admissions wa
                  LEFT JOIN patients p ON wa.patient_id = p.patient_id
                  WHERE wa.admission_id = $admission_id";
$admission_result = $conn->query($admission_sql);

if ($admission_result->num_rows == 0) {
    header("Location: admission_list.php");
    exit();
}

$admission = $admission_result->fetch_assoc();

// Search functionality
$search = '';
$sql = "SELECT i.*, u.username as ordered_by_name 
        FROM investigations i
        LEFT JOIN users u ON i.ordered_by = u.user_id
        WHERE i.admission_id = $admission_id";

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $sql .= " AND (i.investigation_name LIKE '%$search%' OR i.investigation_type LIKE '%$search%' OR i.result_values LIKE '%$search%')";
}

$sql .= " ORDER BY i.ordered_date DESC, i.ordered_time DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Investigations - <?php echo htmlspecialchars($admission['calling_name']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
        }
        
        .navbar {
            background: rgba(44, 62, 80, 0.95);
            backdrop-filter: blur(10px);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .navbar h1 {
            font-size: 1.5rem;
            margin: 0;
        }
        
        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        
        .nav-links a:hover {
            background-color: #34495e;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .card-header {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
            padding: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h2 {
            font-size: 2rem;
            margin: 0;
        }
        
        .patient-info {
            background: rgba(255, 255, 255, 0.1);
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .search-add-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .search-box {
            display: flex;
            gap: 1rem;
            flex: 1;
            max-width: 400px;
        }
        
        .search-box input {
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            flex: 1;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #2ecc71;
            box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.1);
            background: white;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(149, 165, 166, 0.3);
        }
        
        .btn-sm {
            padding: 0.4rem 0.6rem;
            font-size: 0.75rem;
            min-width: 30px;
            height: 30px;
        }
        
        .btn-group {
            display: flex;
            gap: 0.25rem;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .table th {
            background: linear-gradient(135deg, #34495e, #2c3e50);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.875rem;
        }
        
        .table tr:hover {
            background-color: #f8f9fa;
        }
        
        .table tr:last-child td {
            border-bottom: none;
        }
        
        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
        }
        
        .status-in-progress {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        
        .status-completed {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
        }
        
        .status-cancelled {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
        }
        
        .urgent-badge {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .search-add-section {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                max-width: none;
            }
            
            .table {
                font-size: 0.875rem;
            }
            
            .table th,
            .table td {
                padding: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>🏥 Ward Management System</h1>
        <div class="nav-links">
            <a href="../../index.php">Dashboard</a>
            <a href="admission_list.php">Admissions</a>
            <a href="admission_view.php?id=<?php echo $admission_id; ?>">View Admission</a>
            <a href="../../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <div class="card-header">
                <div>
                    <h2>🔬 Laboratory Investigations</h2>
                    <div class="patient-info">
                        <strong>Patient:</strong> <?php echo htmlspecialchars($admission['calling_name']) . ' (' . htmlspecialchars($admission['full_name']) . ')'; ?><br>
                        <strong>NIC:</strong> <?php echo htmlspecialchars($admission['nic']); ?> | 
                        <strong>Admission ID:</strong> #<?php echo str_pad($admission_id, 4, '0', STR_PAD_LEFT); ?>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                <div class="search-add-section">
                    <form method="GET" class="search-box">
                        <input type="hidden" name="admission_id" value="<?php echo $admission_id; ?>">
                        <input type="text" name="search" placeholder="Search investigations..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <?php if (!empty($search)): ?>
                            <a href="investigations.php?admission_id=<?php echo $admission_id; ?>" class="btn btn-secondary">Clear</a>
                        <?php endif; ?>
                    </form>
                    
                    <a href="investigation_form.php?admission_id=<?php echo $admission_id; ?>" class="btn btn-success">
                        🔬 Order Investigation
                    </a>
                </div>
                
                <?php if ($result->num_rows > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Investigation</th>
                                <th>Type</th>
                                <th>Ordered Date/Time</th>
                                <th>Status</th>
                                <th>Results</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($investigation = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600; color: #2c3e50;">
                                            <?php echo htmlspecialchars($investigation['investigation_name']); ?>
                                            <?php if ($investigation['urgent']): ?>
                                                <span class="urgent-badge">URGENT</span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size: 0.875rem; color: #666;">
                                            Ordered by: <?php echo htmlspecialchars($investigation['ordered_by_name']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="background: #e8f5e8; color: #2d6a2d; padding: 0.25rem 0.5rem; border-radius: 4px; font-weight: 600; font-size: 0.875rem;">
                                            <?php echo htmlspecialchars($investigation['investigation_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div><?php echo date('M j, Y', strtotime($investigation['ordered_date'])); ?></div>
                                        <div style="font-size: 0.875rem; color: #777;"><?php echo date('g:i A', strtotime($investigation['ordered_time'])); ?></div>
                                    </td>
                                    <td>
                                        <?php 
                                        $status_class = 'status-' . strtolower(str_replace(' ', '-', $investigation['result_status']));
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>"><?php echo $investigation['result_status']; ?></span>
                                        <?php if ($investigation['sample_collected']): ?>
                                            <br><small style="color: #2ecc71;">✓ Sample Collected</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($investigation['result_values'])): ?>
                                            <div style="font-size: 0.875rem; max-width: 200px;">
                                                <?php echo nl2br(htmlspecialchars(substr($investigation['result_values'], 0, 100))); ?>
                                                <?php if (strlen($investigation['result_values']) > 100): ?>...<?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #999; font-style: italic;">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="investigation_view.php?id=<?php echo $investigation['investigation_id']; ?>&admission_id=<?php echo $admission_id; ?>" class="btn btn-primary btn-sm" title="View">👁️</a>
                                            <a href="investigation_form.php?edit=<?php echo $investigation['investigation_id']; ?>&admission_id=<?php echo $admission_id; ?>" class="btn btn-secondary btn-sm" title="Edit">✏️</a>
                                            <a href="?delete=<?php echo $investigation['investigation_id']; ?>&admission_id=<?php echo $admission_id; ?>" class="btn btn-danger btn-sm" title="Delete" onclick="return confirm('Are you sure you want to delete this investigation?')">🗑️</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 3rem; color: #6c757d;">
                        <h3>🔬 No Investigations Found</h3>
                        <p style="margin: 1rem 0;">
                            <?php echo !empty($search) ? "No investigations match your search criteria." : "No investigations have been ordered for this patient yet."; ?>
                        </p>
                        <a href="investigation_form.php?admission_id=<?php echo $admission_id; ?>" class="btn btn-success" style="margin-top: 1rem;">🔬 Order First Investigation</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>