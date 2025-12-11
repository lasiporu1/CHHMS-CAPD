<?php
session_start();
include 'config/db.php';

$login_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];
    
    $sql = "SELECT user_id, username, password, user_role FROM users WHERE username = '$username'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['user_role'];
            
            // Check for CAPD records where CAPD hasn't started 14+ days after catheter insertion
            $capd_alert_query = "SELECT COUNT(*) as count 
                                FROM capd_status 
                                WHERE capd_start_date IS NULL 
                                AND DATE_ADD(catheter_insertion_date, INTERVAL 14 DAY) <= CURDATE()";
            $capd_alert_result = $conn->query($capd_alert_query);
            $capd_alert_count = $capd_alert_result->fetch_assoc()['count'];
            
            if ($capd_alert_count > 0) {
                $_SESSION['capd_alert'] = $capd_alert_count;
            }
            
            header("Location: index.php");
            exit();
        } else {
            $login_error = "Invalid password!";
        }
    } else {
        $login_error = "User not found!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Management System - Login</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .login-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            padding: 2rem;
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header h1 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
            color: #7f8c8d;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #2c3e50;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn-login {
            width: 100%;
            padding: 0.75rem;
            background-color: #667eea;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 1rem;
        }
        
        .btn-login:hover {
            background-color: #764ba2;
        }
        
        .alert {
            padding: 1rem;
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        
        .info {
            background-color: #d1ecf1;
            color: #0c5460;
            padding: 1rem;
            border-radius: 4px;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🏥 Patient Management</h1>
            <p>Patient Management System</p>
        </div>
        
        <?php if ($login_error): ?>
            <div class="alert"><?php echo $login_error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn-login">Login</button>
        </form>
    </div>
    
    <div style="text-align: center; padding: 20px; color: #666; position: fixed; bottom: 0; left: 0; right: 0; background-color: rgba(255,255,255,0.9);">
        © Copyrights National Hospital - Kandy. All rights reserved 2025 | Developed by <a href="http://www.coleeholdings.com" target="_blank" style="color: #3498db; text-decoration: none;">Colee Holdings (Pvt) Ltd</a>.
    </div>
</body>
</html>
