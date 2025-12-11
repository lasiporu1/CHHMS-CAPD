<?php
include '../../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Create admission_reasons table if it doesn't exist
$create_table_sql = "CREATE TABLE IF NOT EXISTS admission_reasons (
    reason_id INT AUTO_INCREMENT PRIMARY KEY,
    reason_name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
if (!$conn->query($create_table_sql)) {
    die("Error creating admission_reasons table: " . $conn->error);
}

// Insert default admission reasons if table is empty
$check_sql = "SELECT COUNT(*) as count FROM admission_reasons";
$check_result = $conn->query($check_sql);
$count = $check_result->fetch_assoc()['count'];

if ($count == 0) {
    $default_reasons = [
        ['Medical Treatment', 'General medical care and treatment'],
        ['Surgical Procedure', 'Scheduled surgical intervention'],
        ['Post-operative Care', 'Recovery and monitoring after surgery'],
        ['Infection Treatment', 'Treatment of various infections'],
        ['Chronic Disease Management', 'Management of ongoing chronic conditions'],
        ['Emergency Care', 'Urgent medical intervention required'],
        ['Diagnostic Procedures', 'Medical tests and diagnostic evaluations'],
        ['Medication Management', 'Adjustment or monitoring of medications'],
        ['Hypertension Control', 'Blood pressure management and monitoring'],
        ['Nutritional Assessment', 'Evaluation and management of nutritional status'],
        ['Routine Follow-up', 'Regular monitoring and check-up'],
        ['Complication Management', 'Treatment of medical complications'],
        ['Pre-operative Evaluation', 'Assessment before surgical procedures'],
        ['Rehabilitation', 'Physical or medical rehabilitation services'],
        ['Pain Management', 'Treatment and control of chronic or acute pain']
    ];
    
    foreach ($default_reasons as $reason) {
        $insert_sql = "INSERT INTO admission_reasons (reason_name, description) VALUES (?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("ss", $reason[0], $reason[1]);
        $stmt->execute();
    }
}

// Delete admission reason if requested
if (isset($_GET['delete'])) {
    $id = $conn->real_escape_string($_GET['delete']);
    $sql = "UPDATE admission_reasons SET is_active = 0 WHERE reason_id = $id";
    if ($conn->query($sql) === TRUE) {
        header("Location: admission_reasons_list.php?deleted=success");
        exit();
    } else {
        header("Location: admission_reasons_list.php?deleted=error");
        exit();
    }
}

// Reactivate admission reason if requested
if (isset($_GET['reactivate'])) {
    $id = $conn->real_escape_string($_GET['reactivate']);
    $sql = "UPDATE admission_reasons SET is_active = 1 WHERE reason_id = $id";
    if ($conn->query($sql) === TRUE) {
        header("Location: admission_reasons_list.php?reactivated=success");
        exit();
    } else {
        header("Location: admission_reasons_list.php?reactivated=error");
        exit();
    }
}

// Search functionality
$search = '';
$show_inactive = isset($_GET['show_inactive']);
$active_status = $show_inactive ? 0 : 1;

$sql = "SELECT ar.*, 
               COUNT(wa.admission_id) as usage_count,
               MAX(wa.admission_date) as last_used
        FROM admission_reasons ar
        LEFT JOIN ward_admissions wa ON ar.reason_id = wa.admission_reason_id
        WHERE ar.is_active = $active_status";

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $sql .= " AND (ar.reason_name LIKE '%$search%' OR ar.description LIKE '%$search%')";
}

$sql .= " GROUP BY ar.reason_id ORDER BY ar.reason_name ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission Reasons Management</title>
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
        
        .page-header {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .page-header h2 {
            color: #2c3e50;
            margin: 0;
            font-size: 2rem;
            font-weight: 600;
        }
        
        .search-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .data-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
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
        
        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(149, 165, 166, 0.3);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
        }
        
        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(243, 156, 18, 0.3);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }
        
        .btn-sm {
            padding: 0.4rem 0.6rem;
            font-size: 0.75rem;
            min-width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-group {
            display: flex;
            gap: 0.25rem;
            justify-content: center;
            flex-wrap: nowrap;
        }
        
        .quick-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .search-box {
            margin: 0;
            display: flex;
            gap: 1rem;
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
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            background: white;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2rem;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        table thead {
            background: linear-gradient(135deg, #34495e, #2c3e50);
            color: white;
        }
        
        table th, table td {
            padding: 1rem 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
            font-size: 0.95rem;
        }
        
        table tbody tr:hover {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            transform: scale(1.01);
            transition: all 0.3s ease;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        /* Tooltip styles */
        .btn[title] {
            position: relative;
        }
        
        .btn[title]:hover::after {
            content: attr(title);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 0.5rem;
            border-radius: 4px;
            white-space: nowrap;
            font-size: 0.875rem;
            z-index: 1000;
            margin-bottom: 5px;
        }
        
        .btn[title]:hover::before {
            content: '';
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 5px solid transparent;
            border-top-color: rgba(0, 0, 0, 0.8);
            z-index: 1000;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>🏥 Ward Management System</h1>
        <div class="nav-links">
            <a href="../../index.php">Dashboard</a>
            <a href="admission_list.php">Admissions</a>
            <a href="../../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="page-header">
            <div>
                <h2>🔧 Admission Reasons Master</h2>
                <p style="margin: 0.5rem 0 0 0; color: #7f8c8d;">Manage admission reasons for Ward</p>
            </div>
            <div class="quick-actions">
                <a href="admission_reason_form.php" class="btn btn-primary">➕ Add New Reason</a>
                <?php if (!isset($_GET['show_inactive'])): ?>
                    <a href="admission_reasons_list.php?show_inactive=1" class="btn btn-secondary">👁️ View Inactive</a>
                <?php else: ?>
                    <a href="admission_reasons_list.php" class="btn btn-secondary">👁️ View Active</a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (isset($_GET['deleted'])): ?>
            <?php if ($_GET['deleted'] == 'success'): ?>
                <div class="alert alert-success">Admission reason deactivated successfully!</div>
            <?php elseif ($_GET['deleted'] == 'error'): ?>
                <div class="alert alert-danger">Error deactivating admission reason. Please try again.</div>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (isset($_GET['reactivated'])): ?>
            <?php if ($_GET['reactivated'] == 'success'): ?>
                <div class="alert alert-success">Admission reason reactivated successfully!</div>
            <?php elseif ($_GET['reactivated'] == 'error'): ?>
                <div class="alert alert-danger">Error reactivating admission reason. Please try again.</div>
            <?php endif; ?>
        <?php endif; ?>
        
        <div class="search-section">
            <form style="margin: 0;">
                <div class="search-box">
                    <input type="text" name="search" placeholder="🔍 Search admission reasons..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    <button type="submit" class="btn btn-primary">🔍 Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="admission_reasons_list.php" class="btn btn-secondary">✖️ Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <div class="data-section">
            <div class="section-header" style="margin-bottom: 1.5rem;">
                <h3 style="color: #2c3e50; margin: 0; font-size: 1.25rem; font-weight: 600;">
                    📋 <?php echo $show_inactive ? 'Inactive' : 'Active'; ?> Admission Reasons (<?php echo $result->num_rows; ?> records)
                </h3>
            </div>
            <div class="table-container" style="overflow-x: auto;">
                <?php if ($result->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Reason Name</th>
                                <th>Description</th>
                                <th>Usage Statistics</th>
                                <th>Created Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($reason = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><span style="background: linear-gradient(135deg, #3498db, #2980b9); color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-weight: 600;"><?php echo $reason['reason_id']; ?></span></td>
                                    <td>
                                        <div style="font-weight: 600; color: #2c3e50;"><?php echo $reason['reason_name']; ?></div>
                                    </td>
                                    <td style="color: #555; max-width: 300px;"><?php echo $reason['description']; ?></td>
                                    <td>
                                        <div style="font-weight: 600; color: #2c3e50;">Used: <?php echo $reason['usage_count']; ?> times</div>
                                        <?php if ($reason['last_used']): ?>
                                            <small style="color: #666;">Last used: <?php echo date('M j, Y', strtotime($reason['last_used'])); ?></small>
                                        <?php else: ?>
                                            <small style="color: #999;">Never used</small>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size: 0.875rem; color: #555;"><?php echo date('M j, Y', strtotime($reason['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <?php if (!$show_inactive): ?>
                                                <a href="admission_reason_form.php?edit=<?php echo $reason['reason_id']; ?>" class="btn btn-warning btn-sm" title="Edit">✏️</a>
                                                <a href="admission_reasons_list.php?delete=<?php echo $reason['reason_id']; ?>" class="btn btn-danger btn-sm" title="Deactivate" onclick="return confirm('Are you sure you want to deactivate this admission reason?');">🗑️</a>
                                            <?php else: ?>
                                                <a href="admission_reasons_list.php?reactivate=<?php echo $reason['reason_id']; ?>" class="btn btn-success btn-sm" title="Reactivate" onclick="return confirm('Are you sure you want to reactivate this admission reason?');">🔄</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 3rem; color: #6c757d;">
                        <h3>🔧 No Admission Reasons Found</h3>
                        <p style="margin: 1rem 0;">Start by adding your first admission reason.</p>
                        <a href="admission_reason_form.php" class="btn btn-primary" style="margin-top: 1rem;">➕ Add First Reason</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>