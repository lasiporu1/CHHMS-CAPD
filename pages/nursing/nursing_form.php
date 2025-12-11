<?php
include '../../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$nursing_id = $nursing_name = $grade = $contact_number = '';
$error = '';
$success = '';

if (isset($_GET['edit'])) {
    $id = $conn->real_escape_string($_GET['edit']);
    $sql = "SELECT * FROM nursing_officers WHERE nursing_id = $id";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $nursing = $result->fetch_assoc();
        $nursing_id = $nursing['nursing_id'];
        $nursing_name = $nursing['nursing_name'];
        $grade = $nursing['grade'];
        $contact_number = $nursing['contact_number'];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nursing_name = $conn->real_escape_string($_POST['nursing_name']);
    $grade = $conn->real_escape_string($_POST['grade']);
    $contact_number = $conn->real_escape_string($_POST['contact_number']);
    $nursing_id = isset($_POST['nursing_id']) ? $conn->real_escape_string($_POST['nursing_id']) : '';
    
    if (empty($nursing_name) || empty($grade) || empty($contact_number)) {
        $error = "All fields are required!";
    } else {
        if ($nursing_id) {
            // Update nursing officer
            $sql = "UPDATE nursing_officers SET nursing_name='$nursing_name', grade='$grade', contact_number='$contact_number' WHERE nursing_id=$nursing_id";
            
            if ($conn->query($sql) === TRUE) {
                header("Location: nursing_list.php");
                exit();
            } else {
                $error = "Error updating nursing officer: " . $conn->error;
            }
        } else {
            // Create new nursing officer
            $sql = "INSERT INTO nursing_officers (nursing_name, grade, contact_number) VALUES ('$nursing_name', '$grade', '$contact_number')";
            
            if ($conn->query($sql) === TRUE) {
                header("Location: nursing_list.php");
                exit();
            } else {
                $error = "Error creating nursing officer: " . $conn->error;
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
    <title><?php echo isset($_GET['edit']) ? 'Edit Nursing Officer' : 'Add Nursing Officer'; ?></title>
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
            max-width: 600px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #2c3e50;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
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
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>🏥 Patient Management System</h1>
        <div class="nav-links">
            <a href="../../index.php">Dashboard</a>
            <a href="nursing_list.php">Nursing Officers</a>
            <a href="../../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <h2><?php echo isset($_GET['edit']) ? 'Edit Nursing Officer' : 'Add New Nursing Officer'; ?></h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <?php if ($nursing_id): ?>
                    <input type="hidden" name="nursing_id" value="<?php echo $nursing_id; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="nursing_name">Nursing Officer Name</label>
                    <input type="text" id="nursing_name" name="nursing_name" value="<?php echo $nursing_name; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="grade">Grade</label>
                    <select id="grade" name="grade" required>
                        <option value="">Select Grade</option>
                        <option value="Grade I" <?php echo $grade == 'Grade I' ? 'selected' : ''; ?>>Grade I</option>
                        <option value="Grade II" <?php echo $grade == 'Grade II' ? 'selected' : ''; ?>>Grade II</option>
                        <option value="Grade III" <?php echo $grade == 'Grade III' ? 'selected' : ''; ?>>Grade III</option>
                        <option value="Senior Grade" <?php echo $grade == 'Senior Grade' ? 'selected' : ''; ?>>Senior Grade</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="contact_number">Contact Number</label>
                    <input type="tel" id="contact_number" name="contact_number" value="<?php echo $contact_number; ?>" required>
                </div>
                
                <div>
                    <button type="submit" class="btn btn-primary">Save Nursing Officer</button>
                    <a href="nursing_list.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
