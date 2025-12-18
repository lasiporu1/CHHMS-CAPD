# Woard & Clinic Management System - Installation Checklist

## Pre-Installation Requirements

### System Requirements
- [ ] PHP 7.0 or higher installed
- [ ] MySQL 5.7 or higher installed and running
- [ ] Apache web server with mod_rewrite enabled
- [ ] Modern web browser (Chrome, Firefox, Edge, Safari)
- [ ] Administrator access to server

### Software Verification
Run these commands to verify requirements:

**Check PHP Version:**
```
php -v
```
Should output: PHP 7.0.0 or higher

**Check MySQL Status:**
```
MySQL is running and accessible
```

---

## Installation Steps

### Step 1: Download & Extract Project
- [ ] Extract CHHMS folder to web root
  - XAMPP: `C:\xampp\htdocs\CHHMS\`
  - WAMP: `C:\wamp64\www\CHHMS\`
  - Other: Your server's document root
- [ ] Verify all files are present (use FILE_STRUCTURE.md)
- [ ] Set correct file permissions (755 for directories, 644 for files)

### Step 2: Database Preparation
- [ ] Create database in MySQL:
```sql
CREATE DATABASE hospital_management;
```
- [ ] Create MySQL user (optional):
```sql
CREATE USER 'hospital_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON hospital_management.* TO 'hospital_user'@'localhost';
FLUSH PRIVILEGES;
```
- [ ] Verify database connection

### Step 3: Configuration
- [ ] Edit `config/db.php`:
  - [ ] Set DB_HOST (usually 'localhost')
  - [ ] Set DB_USER (MySQL username)
  - [ ] Set DB_PASSWORD (MySQL password)
  - [ ] Keep DB_NAME as 'hospital_management'
- [ ] Test file exists and is readable
- [ ] Verify no syntax errors in config file

### Step 4: Database Initialization
- [ ] Open browser and navigate to:
```
http://localhost/CHHMS/setup.php
```
- [ ] Wait for tables to be created
- [ ] Verify all tables created successfully:
  - [ ] users
  - [ ] doctors
  - [ ] nursing_officers
  - [ ] patients
  - [ ] laboratory_services
- [ ] Note default admin credentials displayed
- [ ] Save a backup copy of admin password

### Step 5: Post-Setup Security
- [ ] Delete setup.php file immediately:
```
rm setup.php  (Linux/Mac)
del setup.php (Windows)
```
- [ ] Verify setup.php is no longer accessible
- [ ] Check that .htaccess file is in place
- [ ] Verify config folder is protected

### Step 6: Initial Login
- [ ] Navigate to login page:
```
http://localhost/CHHMS/login.php
```
- [ ] Login with default credentials:
  - Username: `admin`
  - Password: `admin123`
- [ ] Verify dashboard loads successfully
- [ ] Check all navigation links work

### Step 7: Change Default Password
- [ ] Go to Users section
- [ ] Edit admin user
- [ ] Enter new, strong password
- [ ] Save changes
- [ ] Test logout and login with new password
- [ ] Verify new password works

---

## Post-Installation Configuration

### Optional: Customization
- [ ] Edit includes/header.php to change branding
- [ ] Customize CSS in header.php if needed
- [ ] Set timezone in config file (optional)
- [ ] Configure email settings (optional, for future use)

### Optional: Create Additional Users
- [ ] Create at least one user for each role:
  - [ ] Admin user
  - [ ] Doctor user
  - [ ] Nursing Officer user
  - [ ] Receptionist user
- [ ] Test login with each user
- [ ] Verify role-based permissions work

---

## Verification Tests

### Database Connectivity
- [ ] Login page loads
- [ ] Admin can login
- [ ] Dashboard displays without errors
- [ ] All navigation links work

### User Management
- [ ] Can add new user
- [ ] Can edit user information
- [ ] Can delete user
- [ ] Can view user list
- [ ] Password hashing works (verify in database)

### Doctor Management
- [ ] Can add new doctor
- [ ] Can edit doctor record
- [ ] Can view doctor list
- [ ] Can delete doctor
- [ ] Contact number stores correctly

### Nursing Management
- [ ] Can add new nursing officer
- [ ] Can edit nursing officer record
- [ ] Can view nursing list
- [ ] Can delete nursing officer
- [ ] Grade selection works

### Patient Management
- [ ] Can add new patient
- [ ] All patient fields save correctly:
  - [ ] Calling name
  - [ ] Full name
  - [ ] NIC (should be unique)
  - [ ] Date of birth
  - [ ] Gender
  - [ ] Blood group
  - [ ] Hospital number
  - [ ] Contact number
  - [ ] Address
  - [ ] Guardian information
- [ ] Can view patient list
- [ ] Search functionality works
- [ ] Can view patient details
- [ ] Can edit patient record
- [ ] Can delete patient

### Laboratory Management
- [ ] Can add new lab service
- [ ] Can select report type
- [ ] Can enter lab location
- [ ] Can view lab list
- [ ] Can edit and delete services

### Session & Security
- [ ] Logout works properly
- [ ] Session timeout works (after inactivity)
- [ ] Cannot access pages without login
- [ ] Cannot access setup.php
- [ ] Cannot directly access config folder
- [ ] HTTPS not required for localhost

---

## Performance Checklist

- [ ] All pages load within 2 seconds
- [ ] Database queries are optimized
- [ ] No PHP errors in logs
- [ ] No MySQL errors in logs
- [ ] Memory usage is reasonable
- [ ] CPU usage is normal

---

## Security Checklist

- [ ] Default admin password is changed
- [ ] setup.php file is deleted
- [ ] config/db.php has strong database password
- [ ] File permissions are correct (755/644)
- [ ] .htaccess file is in place
- [ ] Sensitive files are protected
- [ ] No error messages expose system details
- [ ] Session cookies are secure
- [ ] All input is validated
- [ ] SQL injection prevention works

---

## Backup & Disaster Recovery

- [ ] Create database backup:
```bash
mysqldump -u root -p hospital_management > backup.sql
```
- [ ] Create files backup
- [ ] Store backup in safe location
- [ ] Test backup restoration process
- [ ] Document backup procedures
- [ ] Set up automated backups (recommended)

---

## Documentation Review

- [ ] Read README.md for system overview
- [ ] Review QUICK_START.md for common tasks
- [ ] Check API_DOCUMENTATION.md for database info
- [ ] Understand FILE_STRUCTURE.md for project layout
- [ ] Keep documentation accessible for users

---

## User Training

- [ ] Train admin on user management
- [ ] Train doctors on patient records
- [ ] Train nursing officers on their workflows
- [ ] Train receptionists on patient registration
- [ ] Create user manual for each role
- [ ] Provide support contact information

---

## Ongoing Maintenance

### Weekly Tasks
- [ ] Check system logs for errors
- [ ] Verify database backup completed
- [ ] Test backup restoration

### Monthly Tasks
- [ ] Review user access logs
- [ ] Update PHP and MySQL if patches available
- [ ] Check disk space usage
- [ ] Verify data integrity

### Quarterly Tasks
- [ ] Review security settings
- [ ] Test disaster recovery plan
- [ ] Performance optimization review
- [ ] Database optimization (OPTIMIZE TABLE)

### Annually Tasks
- [ ] Security audit
- [ ] System performance review
- [ ] Technology stack updates
- [ ] Data archival/cleanup

---

## Troubleshooting Reference

### Problem: Cannot connect to database
**Solution:**
1. Check MySQL server is running
2. Verify credentials in config/db.php
3. Ensure database exists
4. Check user permissions

### Problem: Setup script shows errors
**Solution:**
1. Verify PHP version (7.0+)
2. Check MySQL version (5.7+)
3. Ensure user has CREATE privilege
4. Check error logs for details

### Problem: Login not working
**Solution:**
1. Clear browser cookies
2. Verify database has users table
3. Check if admin user exists
4. Verify password hashing works

### Problem: Pages show blank
**Solution:**
1. Check PHP error logs
2. Verify database connection
3. Check file permissions
4. Enable debug mode in config

### Problem: Slow performance
**Solution:**
1. Optimize database queries
2. Add database indexes
3. Check server resources
4. Enable query caching

---

## Success Criteria

System is successfully installed when:

- [ ] All pages load without errors
- [ ] Database operations work correctly
- [ ] User authentication works
- [ ] All modules are functional
- [ ] Search functionality works
- [ ] CRUD operations work for all entities
- [ ] Session management works
- [ ] Security measures are in place
- [ ] Documentation is available
- [ ] Users are trained and confident

---

## Support Resources

| Resource | Location |
|----------|----------|
| Documentation | README.md |
| Quick Start | QUICK_START.md |
| API Reference | API_DOCUMENTATION.md |
| File Structure | FILE_STRUCTURE.md |
| Error Logs | PHP/MySQL logs |
| Browser Console | F12 key |

---

## Completion Confirmation

- [ ] Installation completed successfully
- [ ] All tests passed
- [ ] Security verified
- [ ] Backups created
- [ ] Users trained
- [ ] Documentation reviewed
- [ ] Support plan in place

**Installation Date:** _______________

**Installed By:** _______________

**Verified By:** _______________

---

## Next Steps

1. **Immediate**: Change default admin password
2. **Day 1**: Create additional user accounts
3. **Week 1**: Enter initial data (doctors, nurses)
4. **Week 2**: Begin patient registration
5. **Month 1**: Full system operation
6. **Ongoing**: Regular backups and maintenance

---

For detailed information, refer to the included documentation files.
