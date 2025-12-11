# Hospital Patient Management System - Quick Start Guide

## Initial Setup (Do This First!)

### 1. Database Configuration
- Open `config/db.php`
- Update these lines with your MySQL credentials:
```php
define('DB_HOST', 'localhost');      // MySQL Server (usually localhost for local setup)
define('DB_USER', 'root');            // MySQL Username
define('DB_PASSWORD', '');            // MySQL Password
define('DB_NAME', 'hospital_management');  // Database Name
```

### 2. Create the Database
Open phpMyAdmin or MySQL command line and run:
```sql
CREATE DATABASE IF NOT EXISTS hospital_management;
```

### 3. Run Setup Script
1. Open browser and go to: `http://localhost/CHHMS/setup.php`
2. Wait for all tables to be created successfully
3. Note the default admin credentials shown
4. **DELETE setup.php file immediately after setup** for security reasons

### 4. Login
- Go to: `http://localhost/CHHMS/login.php`
- Use default credentials:
  - Username: `admin`
  - Password: `admin123`

## File Location Guide

**For XAMPP Users:**
Place the entire CHHMS folder in: `C:\xampp\htdocs\CHHMS\`

**For WAMP Users:**
Place the entire CHHMS folder in: `C:\wamp64\www\CHHMS\`

**For Other PHP Servers:**
Place in your web server's document root directory

## Key Files to Modify

### Database Connection: `config/db.php`
```php
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_mysql_user');
define('DB_PASSWORD', 'your_mysql_password');
define('DB_NAME', 'hospital_management');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
```

### Change Admin Password (After First Login)
1. Login as admin
2. Go to Users > Edit admin user
3. Enter new password
4. Click Save

## Module Descriptions

### 1. User Management (`pages/users/`)
Manage system users and their roles
- Admin: Full system access
- Doctor: Access to patient and lab records
- Nursing Officer: Limited patient access
- Receptionist: Patient registration access

### 2. Doctors Management (`pages/doctors/`)
- Add doctor profiles
- Manage specializations
- Contact information

### 3. Nursing Officers Management (`pages/nursing/`)
- Register nursing staff
- Assign grades
- Manage contact details

### 4. Patient Management (`pages/patients/`)
- **patient_form.php**: Add/Edit patient records
- **patient_list.php**: View all patients with search
- **patient_view.php**: Detailed patient profile

Patient information includes:
- Personal details (name, NIC, DOB)
- Medical info (blood group)
- Hospital identifiers (PHN, clinic number)
- Contact and guardian information

### 5. Laboratory Services (`pages/laboratory/`)
- Register available lab services
- Manage lab locations
- Track service types

## Default User Roles

| Role | Access | Purpose |
|------|--------|---------|
| Admin | Full | System management, user management |
| Doctor | High | Patient records, medical history |
| Nursing Officer | Medium | Patient care, vital signs |
| Receptionist | Limited | Patient registration, appointments |

## Common Tasks

### Add a New User
1. Dashboard → Users → Add New User
2. Enter username, email, password
3. Select role
4. Click Save User

### Add a Patient
1. Dashboard → Patients → Add New Patient
2. Fill personal information (required fields marked with *)
3. Fill hospital information
4. Add contact and guardian details
5. Click Save Patient

### Search for Patient
1. Go to Patients → List
2. Use search bar (searches by name, NIC, PHN, contact)
3. Click Search button

### View Patient Details
1. Patients → List
2. Find patient
3. Click "View" button
4. See full patient profile

### Edit Records
1. Find the record (patient, doctor, etc.)
2. Click "Edit" button
3. Update information
4. Click Save

### Delete Records
1. Find the record
2. Click "Delete" button
3. Confirm deletion

## Troubleshooting Checklist

- [ ] MySQL server is running
- [ ] Database `hospital_management` exists
- [ ] `config/db.php` has correct credentials
- [ ] `setup.php` has been run
- [ ] `setup.php` has been deleted
- [ ] Browser cookies are cleared
- [ ] File permissions are correct (755 for directories)
- [ ] PHP version is 7.0+
- [ ] MySQL version is 5.7+

## Features by Module

### Dashboard
- Quick access to all modules
- System status overview
- Shortcuts to main functions

### Search & Filter
- Patient search by multiple criteria
- Filter by date range (expandable)
- Quick record lookup

### Reporting (Future)
- Patient census reports
- Doctor workload
- Laboratory utilization
- Monthly statistics

## Security Notes

1. **Always change default admin password**
2. **Delete setup.php after installation**
3. **Use strong passwords** (minimum 8 characters)
4. **Backup database regularly**
5. **Use HTTPS in production**
6. **Restrict file permissions** properly
7. **Keep PHP and MySQL updated**
8. **Use database backups** for disaster recovery

## Contact & Support

For issues, refer to:
1. README.md for detailed documentation
2. System logs for error details
3. Browser console for JavaScript errors
4. MySQL error logs for database issues

## Version Info

- System Version: 1.0
- PHP Requirement: 7.0+
- MySQL Requirement: 5.7+
- Last Updated: December 2024
