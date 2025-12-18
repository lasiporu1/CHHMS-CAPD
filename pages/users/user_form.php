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

$user_id = $username = $email = $user_role = '';
$password = '';
$error = '';
$success = '';

if (isset($_GET['edit'])) {
    $id = $conn->real_escape_string($_GET['edit']);
    $sql = "SELECT * FROM users WHERE user_id = $id";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_id = $user['user_id'];
        $username = $user['username'];
        $email = $user['email'];
        $user_role = $user['user_role'];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $email = $conn->real_escape_string($_POST['email']);
    $user_role = $conn->real_escape_string($_POST['user_role']);
    $password = $_POST['password'];
    $user_id = isset($_POST['user_id']) ? $conn->real_escape_string($_POST['user_id']) : '';
    
    if (empty($username) || empty($email) || empty($user_role)) {
        $error = "All fields are required!";
    } elseif (!$is_admin && $user_role == 'Admin') {
        $error = "You do not have permission to create Admin users!";
    } else {
        if ($user_id) {
            // Update user
            if (!empty($password)) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET username='$username', email='$email', user_role='$user_role', password='$password_hash' WHERE user_id=$user_id";
            } else {
                $sql = "UPDATE users SET username='$username', email='$email', user_role='$user_role' WHERE user_id=$user_id";
            }
            
            if ($conn->query($sql) === TRUE) {
                $success = "User updated successfully!";
                header("Location: user_list.php");
                exit();
            } else {
                $error = "Error updating user: " . $conn->error;
            }
        } else {
            // Create new user
            if (empty($password)) {
                $error = "Password is required for new users!";
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $sql = "INSERT INTO users (username, password, email, user_role) VALUES ('$username', '$password_hash', '$email', '$user_role')";
                
                if ($conn->query($sql) === TRUE) {
                    $success = "User created successfully!";
                    header("Location: user_list.php");
                    exit();
                } else {
                    $error = "Error creating user: " . $conn->error;
                }
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
    <title><?php echo isset($_GET['edit']) ? 'Edit User' : 'Add User'; ?></title>
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
            background-color: #2c3e50;
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
        
        .btn-secondary {
            background-color: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #7f8c8d;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
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
        <h1>🏥 Woard &amp; Clinic Management System</h1>
        <div class="nav-links">
            <a href="../../index.php">Dashboard</a>
            <a href="user_list.php">Users</a>
            <a href="../../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <h2><?php echo isset($_GET['edit']) ? 'Edit User' : 'Add New User'; ?></h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <?php if ($user_id): ?>
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?php echo $username; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo $email; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password <?php echo $user_id ? '(Leave empty to keep current password)' : ''; ?></label>
                    <input type="password" id="password" name="password" <?php echo !$user_id ? 'required' : ''; ?>>
                </div>
                
                <div class="form-group">
                    <label for="user_role">User Role</label>
                    <select id="user_role" name="user_role" required>
                        <option value="">Select Role</option>
                        <?php if ($is_admin): ?>
                            <option value="Admin" <?php echo $user_role == 'Admin' ? 'selected' : ''; ?>>Admin</option>
                        <?php endif; ?>
                        <option value="User" <?php echo $user_role == 'User' ? 'selected' : ''; ?>>User</option>
                    </select>
                </div>
                
                <div>
                    <button type="submit" class="btn btn-primary">Save User</button>
                    <a href="user_list.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
