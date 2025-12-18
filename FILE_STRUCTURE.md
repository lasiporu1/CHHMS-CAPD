# Woard & Clinic Management System - Complete File Structure

## Project Root: `d:\SW\Projects\CHHMS\`

### Directory Structure

```
CHHMS/
│
├── config/
│   ├── db.php                          # Database connection configuration
│   └── config.example.php              # Configuration template with all settings
│
├── includes/
│   ├── header.php                      # Navigation bar and HTML header
│   └── footer.php                      # HTML footer
│
├── pages/
│   ├── users/
│   │   ├── user_form.php              # Add/Edit user form
│   │   └── user_list.php              # List all users with CRUD operations
│   │
│   ├── doctors/
│   │   ├── doctor_form.php            # Add/Edit doctor form
│   │   └── doctor_list.php            # List all doctors with CRUD operations
│   │
│   ├── nursing/
│   │   ├── nursing_form.php           # Add/Edit nursing officer form
│   │   └── nursing_list.php           # List all nursing officers with CRUD operations
│   │
│   ├── patients/
│   │   ├── patient_form.php           # Add/Edit patient form (comprehensive)
│   │   ├── patient_list.php           # List patients with advanced search
│   │   └── patient_view.php           # Detailed patient profile view
│   │
│   └── laboratory/
│       ├── lab_form.php               # Add/Edit laboratory service form
│       └── lab_list.php               # List all laboratory services
│
├── assets/
│   ├── css/
│   │   └── style.css                  # (Optional) Additional CSS styles
│   │
│   └── js/
│       └── script.js                  # (Optional) Additional JavaScript
│
├── uploads/                            # (Future) For file uploads
│
├── logs/                               # (Future) For system logs
│
├── setup.php                           # Database setup and initialization script
├── login.php                           # User login page
├── logout.php                          # Session logout handler
├── index.php                           # Dashboard/Home page
│
├── README.md                           # Complete system documentation
├── QUICK_START.md                      # Quick start and installation guide
├── API_DOCUMENTATION.md                # Database schema and API reference
├── FILE_STRUCTURE.md                   # This file
│
└── .htaccess                           # Apache URL rewriting and security rules
```

## File Descriptions

### Core Files

#### `setup.php` (162 lines)
- **Purpose**: Database initialization and table creation
- **Use**: Run once during installation via browser
- **Creates**: All required database tables
- **Creates Default User**: Admin account with credentials (admin/admin123)
- **⚠️ IMPORTANT**: Delete after setup for security

#### `login.php` (103 lines)
- **Purpose**: User authentication
- **Features**: 
  - Username/password validation
  - Session creation
  - Error handling
  - Responsive login form
- **Access**: Public (before login)

#### `logout.php` (6 lines)
- **Purpose**: Session termination
- **Features**: Destroys session and redirects to login
- **Access**: Protected (requires login)

#### `index.php` (73 lines)
- **Purpose**: Dashboard homepage
- **Features**:
  - Quick module access cards
  - User greeting
  - Module shortcuts
  - Colorful gradient cards
- **Access**: Protected (requires login)

### Configuration Files

#### `config/db.php` (17 lines)
- **Purpose**: MySQL database connection
- **Contains**: 
  - Database host, user, password, name
  - Connection initialization
  - Character set configuration
- **⚠️ MODIFY**: Update credentials for your environment

#### `config/config.example.php` (240 lines)
- **Purpose**: Configuration template
- **Contains**:
  - Application settings
  - Security configuration
  - Permission matrix
  - Error/success messages
  - Timezone and format settings
- **Use**: Copy to config.php and customize

### Template Files

#### `includes/header.php` (110 lines)
- **Purpose**: HTML header and navigation
- **Features**:
  - Navigation menu
  - Links to all modules
  - Logout button
  - Session check
  - Embedded CSS styling
- **Included in**: All protected pages

#### `includes/footer.php` (3 lines)
- **Purpose**: HTML footer
- **Features**: Closes body and HTML tags
- **Included in**: All protected pages

### User Management Module

#### `pages/users/user_form.php` (185 lines)
- **Purpose**: User creation and editing
- **Fields**:
  - Username (required, unique)
  - Email (required, unique)
  - Password (required for new users)
  - User Role (Admin/Doctor/Nursing Officer/Receptionist)
- **Features**:
  - Form validation
  - Password hashing
  - Edit/Add modes
  - Error display

#### `pages/users/user_list.php` (143 lines)
- **Purpose**: Display and manage all users
- **Features**:
  - Tabular display
  - Add new user button
  - Edit functionality
  - Delete functionality
  - User role display
  - Creation date tracking

### Doctor Management Module

#### `pages/doctors/doctor_form.php` (171 lines)
- **Purpose**: Add/Edit doctor profiles
- **Fields**:
  - Doctor Name (required)
  - Specialization (required)
  - Contact Number (required)
- **Features**:
  - Form validation
  - Edit/Add modes
  - Database integration

#### `pages/doctors/doctor_list.php` (137 lines)
- **Purpose**: Display and manage doctors
- **Features**:
  - Doctor listing table
  - Specialization display
  - Contact information
  - Edit/Delete operations
  - Record count

### Nursing Officers Management Module

#### `pages/nursing/nursing_form.php` (171 lines)
- **Purpose**: Add/Edit nursing officer records
- **Fields**:
  - Nursing Officer Name (required)
  - Grade (Grade I/II/III/Senior Grade)
  - Contact Number (required)
- **Features**:
  - Grade dropdown selection
  - Form validation
  - Edit/Add modes

#### `pages/nursing/nursing_list.php` (137 lines)
- **Purpose**: Display and manage nursing staff
- **Features**:
  - Officer listing
  - Grade display
  - Contact information
  - Edit/Delete operations

### Patient Management Module

#### `pages/patients/patient_form.php` (346 lines)
- **Purpose**: Comprehensive patient registration
- **Sections**:
  1. **Personal Information**
     - Calling Name
     - Full Name
     - National Identity Card (NIC)
     - Date of Birth
     - Sex/Gender
     - Blood Group
  
  2. **Hospital Information**
     - Hospital Number (PHN)
     - Clinic Number
  
  3. **Contact Information**
     - Contact Number
     - Address
  
  4. **Guardian Information**
     - Guardian Name
     - Guardian Contact Number
- **Features**:
  - Multi-section form
  - Date picker
  - Dropdown selections
  - Form validation
  - Edit/Add modes
  - Grid-based layout

#### `pages/patients/patient_list.php` (167 lines)
- **Purpose**: Patient listing and search
- **Features**:
  - Advanced search functionality
  - Search by: Name, NIC, Hospital Number, Contact
  - Tabular display
  - Record count
  - Compact view of essential information
  - View/Edit/Delete operations
  - Clear search button

#### `pages/patients/patient_view.php` (214 lines)
- **Purpose**: Detailed patient profile
- **Displays**:
  - All patient information
  - Formatted dates
  - Complete address
  - Guardian details
  - System metadata (created/updated dates)
  - Edit and Back buttons
- **Sections**: Organized into logical groups

### Laboratory Services Module

#### `pages/laboratory/lab_form.php` (149 lines)
- **Purpose**: Add/Edit laboratory services
- **Fields**:
  - Report Type (dropdown list)
  - Laboratory Location
- **Predefined Types**:
  - Blood Test, X-Ray, Ultrasound, CT Scan
  - ECG, Urine Test, MRI, Other
- **Features**: Form validation, Edit/Add modes

#### `pages/laboratory/lab_list.php` (129 lines)
- **Purpose**: Display laboratory services
- **Features**:
  - Service listing table
  - Report type display
  - Lab location information
  - Edit/Delete operations
  - Add new service button

### Documentation Files

#### `README.md` (365 lines)
- Complete system documentation
- Features overview
- Installation instructions
- Database schema description
- Usage guide
- Security information
- Troubleshooting
- Future enhancements

#### `QUICK_START.md` (180 lines)
- Quick setup guide
- Initial configuration steps
- Database setup instructions
- File location guide
- Default credentials
- Common tasks
- Security checklist

#### `API_DOCUMENTATION.md` (420 lines)
- Database table schemas
- Field descriptions
- SQL query examples
- Authentication flow
- Data validation rules
- Future API endpoints
- Error codes
- Performance tips
- Backup procedures

#### `FILE_STRUCTURE.md` (This file)
- Complete file listing
- File descriptions
- Line counts
- Usage information

### System Files

#### `.htaccess` (30 lines)
- Apache server configuration
- URL rewriting rules
- Security headers
- Directory protection
- File access restrictions

## Statistics

| Category | Count |
|----------|-------|
| PHP Files | 16 |
| Documentation Files | 4 |
| Configuration Files | 2 |
| Template Files | 2 |
| Database Modules | 10 |
| Total Lines of Code | 3,500+ |

## Module Breakdown

| Module | Files | Purpose |
|--------|-------|---------|
| Users | 2 | User management and authentication |
| Doctors | 2 | Doctor profiles and specializations |
| Nursing | 2 | Nursing staff management |
| Patients | 3 | Patient registration and information |
| Laboratory | 2 | Laboratory service management |
| Core | 6 | Authentication, dashboard, templates |
| Config | 2 | Database and application configuration |
| Documentation | 4 | System documentation and guides |

## Database Tables

| Table | Fields | Purpose |
|-------|--------|---------|
| users | 7 | System user accounts |
| doctors | 6 | Doctor information |
| nursing_officers | 6 | Nursing staff information |
| patients | 15 | Patient medical records |
| laboratory_services | 4 | Available lab services |

## Required Directories

Must exist or be created:
- `config/` - Database configuration
- `includes/` - Template files
- `pages/` - All modules
- `pages/users/` - User management
- `pages/doctors/` - Doctor management
- `pages/nursing/` - Nursing management
- `pages/patients/` - Patient management
- `pages/laboratory/` - Laboratory management
- `assets/` - CSS and JavaScript (optional)
- `uploads/` - File uploads (future)
- `logs/` - System logs (future)

## How to Navigate the System

1. Start at `login.php` - User authentication
2. Navigate to `index.php` - Dashboard with module shortcuts
3. Access desired module from navigation or dashboard
4. Each module has:
   - `*_form.php` - Add/Edit pages
   - `*_list.php` - View and manage pages
   - `*_view.php` - Detail pages (patients only)

## Security Measures in Place

- Password hashing (password_hash/verify)
- SQL injection prevention (real_escape_string)
- Session-based authentication
- Role-based access control
- Apache security headers
- Directory protection via .htaccess
- Input validation on all forms

## Performance Considerations

- Single database connection shared across all pages
- Efficient SQL queries with ORDER BY
- Pagination-ready structure
- CSS and styling embedded for simplicity
- No external dependencies (pure PHP/MySQL)

## Maintenance & Updates

Files to potentially customize:
1. `config/db.php` - Database credentials
2. `config/config.example.php` - Application settings
3. `includes/header.php` - Navigation and branding
4. Individual module forms for business logic changes

Files to delete after setup:
1. `setup.php` - Security risk if left on server
2. `config/config.example.php` - After copying to config.php

## Version Information

- System Version: 1.0
- Created: December 2024
- PHP Requirement: 7.0+
- MySQL Requirement: 5.7+
- Total Size: Approximately 500KB (excluding documentation)

---

For detailed information, refer to README.md, QUICK_START.md, or API_DOCUMENTATION.md
