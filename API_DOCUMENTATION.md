# Hospital Patient Management System - API Documentation

## Overview
This document outlines the database structure and available functionalities of the Hospital Patient Management System.

## Database Tables

### 1. USERS TABLE
```sql
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    user_role ENUM('Admin', 'Doctor', 'Nursing Officer', 'Receptionist') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**Fields:**
- `user_id`: Unique identifier
- `username`: Login username (unique)
- `password`: Hashed password
- `email`: User email address
- `user_role`: User's role in system
- `created_at`: Record creation timestamp
- `updated_at`: Last update timestamp

---

### 2. DOCTORS TABLE
```sql
CREATE TABLE doctors (
    doctor_id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_name VARCHAR(100) NOT NULL,
    specialization VARCHAR(100) NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);
```

**Fields:**
- `doctor_id`: Unique identifier
- `doctor_name`: Full name of doctor
- `specialization`: Medical specialization (Cardiology, Surgery, etc.)
- `contact_number`: Phone number
- `user_id`: Link to user account (optional)
- `created_at`: Record creation timestamp
- `updated_at`: Last update timestamp

---

### 3. NURSING_OFFICERS TABLE
```sql
CREATE TABLE nursing_officers (
    nursing_id INT AUTO_INCREMENT PRIMARY KEY,
    nursing_name VARCHAR(100) NOT NULL,
    grade VARCHAR(50) NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);
```

**Fields:**
- `nursing_id`: Unique identifier
- `nursing_name`: Full name of nursing officer
- `grade`: Staff grade (Grade I, Grade II, Grade III, Senior Grade)
- `contact_number`: Phone number
- `user_id`: Link to user account (optional)
- `created_at`: Record creation timestamp
- `updated_at`: Last update timestamp

---

### 4. PATIENTS TABLE
```sql
CREATE TABLE patients (
    patient_id INT AUTO_INCREMENT PRIMARY KEY,
    calling_name VARCHAR(50) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    nic VARCHAR(20) UNIQUE NOT NULL,
    hospital_number VARCHAR(20) UNIQUE,
    clinic_number VARCHAR(20),
    date_of_birth DATE NOT NULL,
    sex ENUM('Male', 'Female', 'Other') NOT NULL,
    blood_group VARCHAR(5),
    contact_number VARCHAR(20) NOT NULL,
    address TEXT NOT NULL,
    guardian_name VARCHAR(100),
    guardian_contact_number VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**Fields:**
- `patient_id`: Unique identifier
- `calling_name`: Name used in conversation
- `full_name`: Full legal name
- `nic`: National Identity Card number (unique)
- `hospital_number`: Patient Hospital Number (PHN)
- `clinic_number`: Clinic registration number
- `date_of_birth`: Birth date (DATE format)
- `sex`: Gender (Male/Female/Other)
- `blood_group`: Blood type (O+, O-, A+, A-, B+, B-, AB+, AB-)
- `contact_number`: Phone number
- `address`: Full residential address
- `guardian_name`: Name of legal guardian
- `guardian_contact_number`: Guardian phone number
- `created_at`: Record creation timestamp
- `updated_at`: Last update timestamp

---

### 5. LABORATORY_SERVICES TABLE
```sql
CREATE TABLE laboratory_services (
    lab_id INT AUTO_INCREMENT PRIMARY KEY,
    report_type VARCHAR(100) NOT NULL,
    lab_location VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**Fields:**
- `lab_id`: Unique identifier
- `report_type`: Type of lab report (Blood Test, X-Ray, Ultrasound, CT Scan, ECG, Urine Test, MRI, Other)
- `lab_location`: Physical location of laboratory
- `created_at`: Record creation timestamp
- `updated_at`: Last update timestamp

---

## Common Queries

### Get All Patients
```sql
SELECT * FROM patients ORDER BY created_at DESC;
```

### Search Patients
```sql
SELECT * FROM patients 
WHERE calling_name LIKE '%search_term%' 
   OR full_name LIKE '%search_term%' 
   OR nic LIKE '%search_term%'
ORDER BY created_at DESC;
```

### Get Doctor's Specialization
```sql
SELECT doctor_name, specialization, contact_number 
FROM doctors 
WHERE specialization = 'Cardiology';
```

### Get Active Laboratory Services
```sql
SELECT lab_id, report_type, lab_location 
FROM laboratory_services 
ORDER BY report_type;
```

### Get User by Role
```sql
SELECT user_id, username, email 
FROM users 
WHERE user_role = 'Doctor' 
ORDER BY username;
```

---

## Authentication Flow

1. User enters credentials on login page
2. Username is verified against `users` table
3. Password is verified using `password_verify()`
4. Session variables are created:
   - `$_SESSION['user_id']`
   - `$_SESSION['username']`
   - `$_SESSION['user_role']`
5. User redirected to dashboard
6. Session checked on each page load

---

## Data Validation Rules

### User Registration
- Username: 3-50 characters, alphanumeric
- Email: Valid email format
- Password: Minimum 8 characters (recommended)
- Role: Must be valid enum value

### Doctor Registration
- Name: 3-100 characters, letters and spaces
- Specialization: 3-100 characters
- Contact: 10-20 characters, digits only

### Nursing Officer Registration
- Name: 3-100 characters, letters and spaces
- Grade: Must be valid enum value
- Contact: 10-20 characters, digits only

### Patient Registration
- Calling Name: 2-50 characters (required)
- Full Name: 3-100 characters (required)
- NIC: Unique, 5-20 characters (required)
- Contact: 10-20 characters (required)
- Date of Birth: Valid date format (required)
- Sex: Must be valid enum (required)
- Address: Non-empty text (required)

### Laboratory Service
- Report Type: Selected from predefined list
- Location: 3-100 characters

---

## Future API Endpoints (Planned)

### Users API
- `GET /api/users` - List all users
- `POST /api/users` - Create user
- `GET /api/users/{id}` - Get user details
- `PUT /api/users/{id}` - Update user
- `DELETE /api/users/{id}` - Delete user

### Patients API
- `GET /api/patients` - List all patients
- `POST /api/patients` - Create patient
- `GET /api/patients/{id}` - Get patient details
- `PUT /api/patients/{id}` - Update patient
- `DELETE /api/patients/{id}` - Delete patient
- `GET /api/patients/search/{query}` - Search patients

### Doctors API
- `GET /api/doctors` - List all doctors
- `GET /api/doctors/{id}` - Get doctor details
- `POST /api/doctors` - Create doctor
- `PUT /api/doctors/{id}` - Update doctor
- `DELETE /api/doctors/{id}` - Delete doctor

### Reports API
- `GET /api/reports/patients-count` - Patient statistics
- `GET /api/reports/doctors-list` - Doctor report
- `GET /api/reports/nursing-list` - Nursing staff report

---

## Error Codes (Future Implementation)

| Code | Message | Action |
|------|---------|--------|
| 1000 | Authentication Failed | Retry login |
| 1001 | Invalid Credentials | Check credentials |
| 2000 | Database Error | Contact admin |
| 3000 | Invalid Input | Review input data |
| 4000 | Record Not Found | Check record ID |
| 5000 | Permission Denied | Check user role |

---

## Rate Limiting (Recommended for API)

- Login attempts: 5 per minute
- API calls: 100 per minute per user
- File uploads: 5 per minute

---

## Security Considerations

1. **SQL Injection Prevention**
   - Use prepared statements (PDO/MySQLi)
   - Escape all user inputs
   - Validate data types

2. **Authentication**
   - Use password_hash() for storage
   - Use password_verify() for validation
   - Implement account lockout after failed attempts

3. **Session Management**
   - Use secure session cookies
   - Set session timeout to 30 minutes
   - Regenerate session ID on login

4. **Data Protection**
   - Encrypt sensitive data at rest
   - Use HTTPS for data in transit
   - Implement audit logging

5. **Access Control**
   - Verify user role before allowing action
   - Log all administrative actions
   - Implement data level access controls

---

## Performance Optimization Tips

1. **Database Indexing**
   - Index frequently searched columns (NIC, hospital_number)
   - Index foreign key columns
   - Consider composite indexes for common searches

2. **Query Optimization**
   - Avoid SELECT * queries
   - Use LIMIT for large result sets
   - Implement pagination

3. **Caching**
   - Cache user roles and permissions
   - Cache frequently accessed lookups
   - Implement query result caching

---

## Backup & Recovery

### Database Backup
```bash
mysqldump -u root -p hospital_management > backup.sql
```

### Database Restore
```bash
mysql -u root -p hospital_management < backup.sql
```

### Files Backup
Regular backup of:
- `/pages` directory
- `/config` directory
- Database file

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | Dec 2024 | Initial release |
| 1.1 | Planned | Mobile responsive design |
| 1.2 | Planned | REST API implementation |
| 1.3 | Planned | Advanced reporting |
| 2.0 | Planned | Mobile app integration |

---

## Contact & Support

For technical questions about the system architecture, please contact the development team.
