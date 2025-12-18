<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Global page view logging (uses log_activity from config/db.php if available)
$page = (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ($_SERVER['PHP_SELF'] ?? 'unknown'));
if (function_exists('log_activity') && isset($_SESSION['user_id'])) {
    // Do not block page load on logging errors
    try {
        log_activity($_SESSION['user_id'], 'view', 'page', null, $page);
    } catch (Exception $e) {
        // ignore logging failures
    }
}

// Include server-side POST logger to capture server CRUD actions centrally
include_once __DIR__ . '/../config/server_log.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#667eea">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Woard & Clinic Management System</title>
    <link rel="manifest" href="<?php echo $base_path ?? ''; ?>manifest.json">
    <link rel="stylesheet" href="<?php echo $base_path ?? ''; ?>assets/css/style.css">
    <link rel="stylesheet" href="<?php echo $base_path ?? ''; ?>assets/css/responsive.css">
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
            margin-bottom: 2rem;
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
        
        .btn-success {
            background-color: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background-color: #229954;
        }
        
        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .btn-warning {
            background-color: #f39c12;
            color: white;
        }
        
        .btn-warning:hover {
            background-color: #e67e22;
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
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .form-group textarea {
            resize: vertical;
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
        
        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        /* Dropdown navigation (dark themed to match navbar) */
        .dropdown { position: relative; }
        .dropdown-toggle { cursor: pointer; display: inline-block; color: #fff; padding: 0.45rem 0.6rem; border-radius: 6px; transition: background 0.18s; }
        .dropdown-toggle::after { content: ' ▾'; margin-left:6px; opacity:0.95; font-size:0.9em; }
        .dropdown-menu {
            display: none;
            position: absolute;
            /* place menu flush to the toggle with a small overlap to avoid hover gaps */
            top: calc(100% - 2px);
            left: 0;
            background: rgba(44,62,80,0.98);
            color: #fff;
            border-radius: 8px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.25);
            min-width: 220px;
            padding: 6px 0;
            z-index: 200;
        }
        .dropdown-menu a { display: block; padding: 10px 14px; color: #ecf0f1; text-decoration: none; }
        .dropdown-menu a:hover { background: rgba(255,255,255,0.06); color: #fff; }
        .dropdown-menu .muted { font-size:0.85em; color: rgba(255,255,255,0.78); padding: 8px 14px; }
        .dropdown.open .dropdown-menu { display: block; }
        /* show on hover for desktop */
        @media (hover: hover) {
            .dropdown:hover .dropdown-menu { display:block; }
            .dropdown-toggle:hover { background: rgba(255,255,255,0.03); }
        }
        
        /* Mobile hamburger menu */
        .mobile-menu-toggle {
            display: none;
            flex-direction: column;
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
            z-index: 101;
        }
        
        .mobile-menu-toggle span {
            width: 25px;
            height: 3px;
            background-color: white;
            margin: 3px 0;
            transition: 0.3s;
            border-radius: 3px;
        }
        
        /* Responsive table wrapper */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Mobile responsive styles */
        @media screen and (max-width: 768px) {
            .navbar {
                padding: 1rem;
                flex-wrap: wrap;
            }
            
            .navbar h1 {
                font-size: 1rem;
                flex: 1;
            }
            
            .mobile-menu-toggle {
                display: flex;
            }
            
            .nav-links {
                display: none;
                flex-direction: column;
                width: 100%;
                gap: 0;
                margin-top: 1rem;
                background: rgba(44, 62, 80, 0.98);
                border-radius: 8px;
                padding: 1rem 0;
            }
            
            .nav-links.active {
                display: flex;
            }
            
            .nav-links > a,
            .nav-links > .dropdown {
                width: 100%;
                text-align: left;
            }
            
            .nav-links a {
                padding: 1rem 1.5rem;
                border-radius: 0;
            }
            
            .dropdown {
                position: relative;
            }
            
            .dropdown-menu {
                position: static;
                display: none;
                box-shadow: none;
                background: rgba(52, 73, 94, 0.95);
                margin: 0;
                border-radius: 0;
            }
            
            .dropdown.open .dropdown-menu {
                display: block;
            }
            
            .container {
                padding: 1rem 0.5rem;
            }
            
            .card {
                padding: 1.5rem;
                margin-bottom: 1rem;
            }
            
            .card h2 {
                font-size: 1.5rem;
            }
            
            /* Make tables responsive */
            table {
                font-size: 0.9rem;
            }
            
            table th,
            table td {
                padding: 0.5rem 0.25rem;
                white-space: nowrap;
            }
            
            /* Stack form elements on mobile */
            .form-group input,
            .form-group select,
            .form-group textarea {
                font-size: 16px; /* Prevents zoom on iOS */
            }
            
            /* Touch-friendly buttons */
            .btn {
                padding: 0.75rem 1rem;
                font-size: 1rem;
                min-height: 44px; /* Apple's recommended touch target */
            }
            
            /* Better spacing for mobile */
            .form-group {
                margin-bottom: 1.25rem;
            }
        }
        
        /* Tablet styles */
        @media screen and (min-width: 769px) and (max-width: 1024px) {
            .container {
                max-width: 100%;
                padding: 1.5rem 1rem;
            }
            
            .navbar h1 {
                font-size: 1.25rem;
            }
            
            .nav-links {
                gap: 1rem;
            }
            
            table {
                font-size: 0.95rem;
            }
        }
        
        /* Utility classes for responsive design */
        .hide-mobile {
            display: block;
        }
        
        .show-mobile {
            display: none;
        }
        
        @media screen and (max-width: 768px) {
            .hide-mobile {
                display: none;
            }
            
            .show-mobile {
                display: block;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>🏥 Woard &amp; Clinic Management System</h1>
        <button class="mobile-menu-toggle" aria-label="Toggle navigation">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <div class="nav-links">
            <?php
            // Determine the correct base path based on current directory
            $current_dir = dirname($_SERVER['PHP_SELF']);
            $depth = substr_count($current_dir, '/') - 1; // Subtract 1 for the root
            $base_path = str_repeat('../', $depth);
            ?>
            <a href="<?php echo $base_path; ?>index.php">Dashboard</a>

            <div class="dropdown">
                <a class="dropdown-toggle">Master Files</a>
                <div class="dropdown-menu">
                    <a href="<?php echo $base_path; ?>pages/users/user_list.php">Users</a>
                    <a href="<?php echo $base_path; ?>pages/doctors/doctor_list.php">Doctors</a>
                    <a href="<?php echo $base_path; ?>pages/nursing/nursing_list.php">Nursing Officers</a>
                    <a href="<?php echo $base_path; ?>pages/patients/patient_list.php">Patients</a>
                    <a href="<?php echo $base_path; ?>pages/medicines/medicine_master.php">Medicine Master</a>
                </div>
            </div>

            <div class="dropdown">
                <a class="dropdown-toggle">Transactions</a>
                <div class="dropdown-menu">
                    <a href="<?php echo $base_path; ?>pages/admissions/admission_list.php">Ward Admissions</a>
                    <a href="<?php echo $base_path; ?>pages/clinics/clinic_admissions_list.php">Clinic Admissions</a>
                    <a href="<?php echo $base_path; ?>pages/admissions/bed_management.php">Bed Management</a>
                </div>
            </div>

            <div class="dropdown">
                <a class="dropdown-toggle">Reports</a>
                <div class="dropdown-menu">
                    <a href="<?php echo $base_path; ?>pages/reports/report_list.php">Reports List</a>
                    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin'): ?>
                        <a href="<?php echo $base_path; ?>pages/admin/activity_log.php">Activity Log</a>
                    <?php endif; ?>
                </div>
            </div>

            <a href="<?php echo $base_path; ?>logout.php">Logout</a>
        </div>
    </div>
        <script>
        (function(){
            var endpoint = '<?php echo $base_path; ?>config/log.php';
            function sendLog(payload){
                try{
                    var blob = new Blob([JSON.stringify(payload)], {type:'application/json'});
                    if (navigator.sendBeacon) {
                        navigator.sendBeacon(endpoint, blob);
                    } else {
                        fetch(endpoint, {method:'POST', body:JSON.stringify(payload), headers:{'Content-Type':'application/json'}, keepalive:true}).catch(()=>{});
                    }
                }catch(e){/* ignore */}
            }

            document.addEventListener('click', function(e){
                var el = e.target.closest('a,button,input[type="submit"],[data-log],.dropdown-toggle');
                if (!el) return;

                // Dropdown toggle behaviour for touch devices / click
                if (el.classList && el.classList.contains('dropdown-toggle')) {
                    var parent = el.parentElement;
                    if (parent && parent.classList) {
                        parent.classList.toggle('open');
                    }
                    e.preventDefault();
                    return;
                }

                var tag = el.tagName.toLowerCase();
                var text = (el.innerText || el.value || '').trim().slice(0,200);
                var href = el.getAttribute('href') || '';
                var dataAction = el.getAttribute('data-action') || el.dataset.action || '';
                var payload = { action: dataAction || 'click', element: tag, text: text, href: href, page: window.location.pathname + window.location.search };
                sendLog(payload);
            }, true);

            // Close any open dropdown when clicking outside
            document.addEventListener('click', function(e){
                if (e.target.closest('.dropdown')) return;
                document.querySelectorAll('.dropdown.open').forEach(function(d){ d.classList.remove('open'); });
            });
            
            // Mobile menu toggle
            var mobileToggle = document.querySelector('.mobile-menu-toggle');
            var navLinks = document.querySelector('.nav-links');
            
            if (mobileToggle && navLinks) {
                mobileToggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    navLinks.classList.toggle('active');
                    mobileToggle.classList.toggle('active');
                });
                
                // Close mobile menu when clicking a link
                navLinks.addEventListener('click', function(e) {
                    if (e.target.tagName === 'A' && !e.target.classList.contains('dropdown-toggle')) {
                        navLinks.classList.remove('active');
                        mobileToggle.classList.remove('active');
                    }
                });
                
                // Close mobile menu when clicking outside
                document.addEventListener('click', function(e) {
                    if (!e.target.closest('.navbar')) {
                        navLinks.classList.remove('active');
                        mobileToggle.classList.remove('active');
                    }
                });
            }
        })();
        </script>
        <script src="<?php echo $base_path ?? ''; ?>assets/js/mobile-helpers.js"></script>
