<?php
include '../../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Get current user role
$current_user_sql = "SELECT user_role FROM users WHERE user_id = {$_SESSION['user_id']}";
$current_user_result = $conn->query($current_user_sql);
$current_user = $current_user_result->fetch_assoc();
$is_admin = ($current_user['user_role'] == 'Admin');

// Delete doctor if requested (only for Admin)
if (isset($_GET['delete']) && $is_admin) {
    $id = $conn->real_escape_string($_GET['delete']);
    $sql = "DELETE FROM doctors WHERE doctor_id = $id";
    if ($conn->query($sql) === TRUE) {
        header("Location: doctor_list.php");
        exit();
    }
}

$sql = "SELECT * FROM doctors ORDER BY created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctors Management</title>
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
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s;
        }
        
        .btn-primary {
            background-color: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
        }
        
        .btn-warning {
            background-color: #f39c12;
            color: white;
        }
        
        .btn-warning:hover {
            background-color: #e67e22;
        }
        
        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        table thead {
            background-color: #34495e;
            color: white;
        }
        
        table th, table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .btn-group {
            display: flex;
            gap: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>🏥 Woard &amp; Clinic Management System</h1>
        <div class="nav-links">
            <a href="../../index.php">Dashboard</a>
            <a href="../../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h2>Doctors Management</h2>
                <a href="doctor_form.php" class="btn btn-primary">+ Add New Doctor</a>
            </div>
            
            <?php if ($result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Doctor ID</th>
                            <th>Name</th>
                            <th>Specialization</th>
                            <th>Contact Number</th>
                            <th>Created Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($doctor = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $doctor['doctor_id']; ?></td>
                                <td><?php echo $doctor['doctor_name']; ?></td>
                                <td><?php echo $doctor['specialization']; ?></td>
                                <td><?php echo $doctor['contact_number']; ?></td>
                                <td><?php echo date('Y-m-d', strtotime($doctor['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="doctor_form.php?edit=<?php echo $doctor['doctor_id']; ?>" class="btn btn-warning">Edit</a>
                                        <?php if ($is_admin): ?>
                                            <a href="doctor_list.php?delete=<?php echo $doctor['doctor_id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure?');">Delete</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No doctors found. <a href="doctor_form.php">Add one</a></p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
