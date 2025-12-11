<?php
include '../../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$reason_id = $reason_name = $description = '';
$error = '';

// Check if editing
if (isset($_GET['edit'])) {
    $id = $conn->real_escape_string($_GET['edit']);
    $sql = "SELECT * FROM admission_reasons WHERE reason_id = $id";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $reason = $result->fetch_assoc();
        $reason_id = $reason['reason_id'];
        $reason_name = $reason['reason_name'];
        $description = $reason['description'];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $reason_name = trim($conn->real_escape_string($_POST['reason_name']));
    $description = trim($conn->real_escape_string($_POST['description']));
    
    // Validation
    if (empty($reason_name)) {
        $error = "Reason name is required!";
    } else {
        // Check for duplicates (excluding current record if editing)
        $check_sql = "SELECT reason_id FROM admission_reasons WHERE reason_name = '$reason_name' AND is_active = 1";
        if (!empty($reason_id)) {
            $check_sql .= " AND reason_id != $reason_id";
        }
        
        $check_result = $conn->query($check_sql);
        if ($check_result->num_rows > 0) {
            $error = "This admission reason already exists!";
        } else {
            if (!empty($reason_id)) {
                // Update existing reason
                $sql = "UPDATE admission_reasons SET reason_name = '$reason_name', description = '$description' WHERE reason_id = $reason_id";
            } else {
                // Create new reason
                $sql = "INSERT INTO admission_reasons (reason_name, description) VALUES ('$reason_name', '$description')";
            }
            
            if ($conn->query($sql) === TRUE) {
                header("Location: admission_reasons_list.php");
                exit();
            } else {
                $error = "Error saving admission reason: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo !empty($reason_id) ? 'Edit' : 'Add'; ?> Admission Reason</title>
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
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 2.5rem;
            overflow: hidden;
        }
        
        .card h2 {
            color: #2c3e50;
            margin: 0 0 2rem 0;
            font-size: 2rem;
            font-weight: 600;
            border-bottom: 3px solid #3498db;
            padding-bottom: 1rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.75rem;
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            background: white;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
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
        
        .alert {
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>🏥 Ward Management System</h1>
        <div class="nav-links">
            <a href="../../index.php">Dashboard</a>
            <a href="admission_reasons_list.php">Admission Reasons</a>
            <a href="../../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <h2><?php echo !empty($reason_id) ? '✏️ Edit' : '➕ Add New'; ?> Admission Reason</h2>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="reason_name">Reason Name *</label>
                    <input type="text" id="reason_name" name="reason_name" value="<?php echo htmlspecialchars($reason_name); ?>" required maxlength="255" placeholder="Enter admission reason name">
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Enter detailed description of the admission reason"><?php echo htmlspecialchars($description); ?></textarea>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">
                        <?php echo !empty($reason_id) ? '💾 Update Reason' : '➕ Add Reason'; ?>
                    </button>
                    <a href="admission_reasons_list.php" class="btn btn-secondary">← Back to List</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>