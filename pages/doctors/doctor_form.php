<?php
include '../../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$doctor_id = $doctor_name = $specialization = $contact_number = '';
$error = '';
$success = '';

if (isset($_GET['edit'])) {
    $id = $conn->real_escape_string($_GET['edit']);
    $sql = "SELECT * FROM doctors WHERE doctor_id = $id";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $doctor = $result->fetch_assoc();
        $doctor_id = $doctor['doctor_id'];
        $doctor_name = $doctor['doctor_name'];
        $specialization = $doctor['specialization'];
        $contact_number = $doctor['contact_number'];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $doctor_name = $conn->real_escape_string($_POST['doctor_name']);
    $specialization = $conn->real_escape_string($_POST['specialization']);
    $contact_number = $conn->real_escape_string($_POST['contact_number']);
    $doctor_id = isset($_POST['doctor_id']) ? $conn->real_escape_string($_POST['doctor_id']) : '';
    
    if (empty($doctor_name) || empty($specialization) || empty($contact_number)) {
        $error = "All fields are required!";
    } else {
        if ($doctor_id) {
            // Update doctor
            $sql = "UPDATE doctors SET doctor_name='$doctor_name', specialization='$specialization', contact_number='$contact_number' WHERE doctor_id=$doctor_id";
            
            if ($conn->query($sql) === TRUE) {
                header("Location: doctor_list.php");
                exit();
            } else {
                $error = "Error updating doctor: " . $conn->error;
            }
        } else {
            // Create new doctor
            $sql = "INSERT INTO doctors (doctor_name, specialization, contact_number) VALUES ('$doctor_name', '$specialization', '$contact_number')";
            
            if ($conn->query($sql) === TRUE) {
                header("Location: doctor_list.php");
                exit();
            } else {
                $error = "Error creating doctor: " . $conn->error;
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
    <title><?php echo isset($_GET['edit']) ? 'Edit Doctor' : 'Add Doctor'; ?></title>
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
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
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
            <a href="doctor_list.php">Doctors</a>
            <a href="../../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <h2><?php echo isset($_GET['edit']) ? 'Edit Doctor' : 'Add New Doctor'; ?></h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <?php if ($doctor_id): ?>
                    <input type="hidden" name="doctor_id" value="<?php echo $doctor_id; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="doctor_name">Doctor Name</label>
                    <input type="text" id="doctor_name" name="doctor_name" value="<?php echo $doctor_name; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="specialization">Specialization</label>
                    <input type="text" id="specialization" name="specialization" value="<?php echo $specialization; ?>" placeholder="e.g., Cardiology, Surgery, etc." required>
                </div>
                
                <div class="form-group">
                    <label for="contact_number">Contact Number</label>
                    <input type="tel" id="contact_number" name="contact_number" value="<?php echo $contact_number; ?>" required>
                </div>
                
                <div>
                    <button type="submit" class="btn btn-primary">Save Doctor</button>
                    <a href="doctor_list.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
