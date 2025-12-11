# Hospital Patient Management System

A complete PHP and MySQL-based Hospital Patient Management System for managing patient records, doctors, nursing officers, users, and laboratory services.

## Features

### 1. User Management
- User registration and login
- Role-based access control (Admin, Doctor, Nursing Officer, Receptionist)
- User profile management with secure password handling
- Email validation

### 2. Doctors Master File
- Manage doctor profiles with:
  - Doctor Name
  - Specialization (Cardiology, Surgery, etc.)
  - Contact Number
  - Auto-generated created date tracking

### 3. Nursing Officers Master File
- Manage nursing staff with:
  - Nursing Officer Name
  - Grade (Grade I, Grade II, Grade III, Senior Grade)
  - Contact Number
  - Record management

### 4. Patient Management
- Comprehensive patient database with:
  - Calling Name and Full Name
  - National Identity Card (NIC) Number
  - Hospital Number (PHN)
  - Clinic Number
  - Date of Birth
  - Sex/Gender
  - Blood Group
  - Contact Number
  - Address
  - Guardian Name and Contact Number
- Advanced search functionality
- Detailed patient view page
- Complete patient history tracking

### 5. Laboratory Service Master
- Manage laboratory services with:
  - Report Types (Blood Test, X-Ray, Ultrasound, CT Scan, ECG, Urine Test, MRI, etc.)
  - Laboratory Location
  - Service tracking

## System Requirements

- PHP 7.0 or higher
- MySQL 5.7 or higher
- Apache Web Server (with mod_rewrite)
- Web Browser (Chrome, Firefox, Edge, Safari)

## Installation Steps

### Step 1: Set up the Database

1. Create a MySQL database:
```sql
CREATE DATABASE hospital_management;
```

2. Create a MySQL user (optional but recommended):
```sql
CREATE USER 'hospital_user'@'localhost' IDENTIFIED BY 'password';
GRANT ALL PRIVILEGES ON hospital_management.* TO 'hospital_user'@'localhost';
FLUSH PRIVILEGES;
```

### Step 2: Configure Database Connection

Edit `config/db.php` and update the following credentials:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // Change to your MySQL user
define('DB_PASSWORD', '');         // Add your password
define('DB_NAME', 'hospital_management');
```

### Step 3: Run Database Setup

1. Place the project folder in your web server's root directory (htdocs for XAMPP, www for WAMP)
2. Open your browser and navigate to: `http://localhost/CHHMS/setup.php`
3. The setup script will create all necessary tables
4. **Important**: Delete or rename `setup.php` after setup for security

### Step 4: Login to the System

- URL: `http://localhost/CHHMS/login.php`
- Default Credentials:
  - Username: `admin`
  - Password: `admin123`

## Project Structure

```
CHHMS/
├── config/
│   └── db.php                 # Database configuration
├── includes/
│   ├── header.php            # Navigation header
│   └── footer.php            # Footer
├── pages/
│   ├── users/
│   │   ├── user_form.php     # Add/Edit user form
│   │   └── user_list.php     # List all users
│   ├── doctors/
│   │   ├── doctor_form.php   # Add/Edit doctor form
│   │   └── doctor_list.php   # List all doctors
│   ├── nursing/
│   │   ├── nursing_form.php  # Add/Edit nursing officer form
│   │   └── nursing_list.php  # List all nursing officers
│   ├── patients/
│   │   ├── patient_form.php  # Add/Edit patient form
│   │   ├── patient_list.php  # List all patients with search
│   │   └── patient_view.php  # View patient details
│   └── laboratory/
│       ├── lab_form.php      # Add/Edit lab service form
│       └── lab_list.php      # List all lab services
├── assets/
│   ├── css/
│   │   └── style.css         # Main stylesheet
│   └── js/
│       └── script.js         # JavaScript functions
├── setup.php                 # Database setup script
├── login.php                 # Login page
├── logout.php                # Logout handler
└── index.php                 # Dashboard
```

## Database Schema

### Users Table
- user_id (INT, Primary Key)
- username (VARCHAR, Unique)
- password (VARCHAR, Hashed)
- email (VARCHAR, Unique)
- user_role (ENUM: Admin, Doctor, Nursing Officer, Receptionist)
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)

### Doctors Table
- doctor_id (INT, Primary Key)
- doctor_name (VARCHAR)
- specialization (VARCHAR)
- contact_number (VARCHAR)
- user_id (INT, Foreign Key)
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)

### Nursing Officers Table
- nursing_id (INT, Primary Key)
- nursing_name (VARCHAR)
- grade (VARCHAR)
- contact_number (VARCHAR)
- user_id (INT, Foreign Key)
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)

### Patients Table
- patient_id (INT, Primary Key)
- calling_name (VARCHAR)
- full_name (VARCHAR)
- nic (VARCHAR, Unique)
- hospital_number (VARCHAR)
- clinic_number (VARCHAR)
- date_of_birth (DATE)
- sex (ENUM: Male, Female, Other)
- blood_group (VARCHAR)
- contact_number (VARCHAR)
- address (TEXT)
- guardian_name (VARCHAR)
- guardian_contact_number (VARCHAR)
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)

### Laboratory Services Table
- lab_id (INT, Primary Key)
- report_type (VARCHAR)
- lab_location (VARCHAR)
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)

## Usage Guide

### Managing Users
1. Navigate to Users section from dashboard
2. Click "Add New User" to create user
3. Fill in username, email, password, and select role
4. Click "Save User"
5. Edit or delete users as needed

### Managing Doctors
1. Go to Doctors section
2. Click "Add New Doctor"
3. Enter doctor name, specialization, and contact
4. Save the doctor profile

### Managing Nursing Officers
1. Access Nursing Officers section
2. Click "Add New Nursing Officer"
3. Fill in name, grade, and contact number
4. Save the record

### Managing Patients
1. Navigate to Patients section
2. Click "Add New Patient"
3. Fill in all required patient information:
   - Personal details (name, NIC, DOB, gender, blood group)
   - Hospital information (PHN, clinic number)
   - Contact information (phone, address)
   - Guardian details
4. Use search bar to find patients by name, NIC, or contact
5. Click "View" to see complete patient details
6. Click "Edit" to update patient information

### Managing Laboratory Services
1. Go to Laboratory section
2. Click "Add New Service"
3. Select report type and enter laboratory location
4. Save the service

## Security Features

- Password hashing using PHP's password_hash() function
- SQL injection prevention with real_escape_string()
- Session-based authentication
- Automatic logout on session expiration
- Role-based access control
- Input validation on all forms

## Troubleshooting

### Issue: Cannot connect to database
- Check database credentials in `config/db.php`
- Verify MySQL server is running
- Ensure database exists

### Issue: Setup page shows errors
- Make sure MySQL user has ALL PRIVILEGES on the database
- Check MySQL version compatibility (5.7+)
- Verify folder permissions are correct

### Issue: Login not working
- Clear browser cookies and cache
- Verify admin user exists in database
- Check PHP session settings in php.ini

## Future Enhancements

- Patient appointment scheduling
- Medical history tracking
- Prescription management
- Billing and invoicing
- Report generation (PDF)
- SMS/Email notifications
- Mobile application
- API integration
- Advanced analytics and reporting

## Support

For issues or questions, please contact the development team or refer to the system logs for debugging information.

## License

This project is provided as-is for educational and commercial use.

## Version

Version 1.0 - Initial Release
Date: December 2024
