# Death Status Management Implementation

## Overview
This implementation adds the ability to track patient deaths in the Hospital Management System. The system now supports:
1. Recording patient death during discharge from ward
2. Recording patient death without ward admission (direct death registration)

## Database Changes

### 1. Patients Table - New Columns
```sql
ALTER TABLE patients 
ADD COLUMN patient_status ENUM('Active', 'Deceased', 'Inactive') DEFAULT 'Active',
ADD COLUMN death_date DATE NULL,
ADD COLUMN death_notes TEXT NULL;
```

**Fields:**
- `patient_status`: Current status of the patient (Active/Deceased/Inactive)
- `death_date`: Date when patient passed away
- `death_notes`: Cause of death or additional notes

### 2. Ward Admissions Table - Updated Column
```sql
ALTER TABLE ward_admissions 
MODIFY COLUMN discharge_status ENUM('Complete', 'Pending', 'Death') DEFAULT 'Complete';
```

**Added Value:**
- `Death`: New discharge status option when patient dies during admission

## Modified Files

### 1. discharge_form.php
**Location:** `pages/admissions/discharge_form.php`

**Changes:**
- Added "Death" option to discharge_status dropdown
- Automatic schema migration on first use (adds necessary columns)
- When discharge_status = "Death", automatically updates patient master record:
  - Sets `patient_status` = 'Deceased'
  - Records `death_date` = discharge_date
  - Copies discharge_notes to `death_notes`

**Usage:**
1. Navigate to discharge form from active admission
2. Select "Death" from Discharge Status dropdown
3. Enter discharge date and notes (cause of death)
4. Submit form - both admission and patient records are updated

### 2. death_registration.php (NEW)
**Location:** `pages/admissions/death_registration.php`

**Features:**
- Register patient death without ward admission
- Patient search by name, NIC, or clinic number
- Prevents duplicate death registrations
- Validates patient exists and is not already deceased
- Records death date and cause/notes

**Usage:**
1. Access via navigation menu: "⚰️ Register Death"
2. Search for patient using search box
3. Select patient from search results
4. Enter death date (defaults to today, max = today)
5. Enter death notes/cause of death
6. Submit form to mark patient as deceased

**Auto-Migration:**
- Form automatically adds required columns on first use
- No manual database setup required

## Navigation Updates

### 1. admission_list.php
- Added "⚰️ Register Death" link to navbar

### 2. patient_list.php
- Added "⚰️ Register Death" link to navbar

## Migration Script (Optional)
**Location:** `pages/admissions/add_death_status.php`

This standalone migration script can be run to add all necessary database columns:
- Adds patient_status, death_date, death_notes to patients table
- Updates discharge_status enum to include Death option
- Provides status messages for each operation

**Usage:**
Navigate to: `http://localhost/CHHMS/pages/admissions/add_death_status.php`

**Note:** Migration is also handled automatically by both main forms on first use.

## Features Summary

### 1. Discharge with Death Status
✅ "Death" option in discharge status dropdown
✅ Automatic patient status update to "Deceased"
✅ Records death date from discharge date
✅ Copies discharge notes to death notes
✅ Maintains discharge record in ward_admissions

### 2. Direct Death Registration
✅ Standalone death registration form
✅ Patient search functionality
✅ Prevents duplicate death registrations
✅ Validates patient status before recording
✅ Records death date and notes
✅ No ward admission required

### 3. Data Integrity
✅ Automatic schema migration on first use
✅ Prevents registering death for already deceased patients
✅ Death date validation (cannot be in future)
✅ Required field validation
✅ Transaction safety with proper error handling

## User Workflow Examples

### Scenario 1: Patient dies during ward admission
1. Patient is admitted to ward
2. Staff navigates to admission details
3. Clicks "Discharge Patient" button
4. Selects "Death" from discharge status
5. Enters death date/time and cause
6. Submits form
7. **Result:** Admission marked as discharged with death status, patient master record updated to Deceased

### Scenario 2: Patient dies outside ward (outpatient)
1. Staff navigates to "Register Death" from menu
2. Searches for patient by name/NIC/clinic number
3. Selects correct patient from results
4. Enters death date and cause/circumstances
5. Submits form
6. **Result:** Patient master record updated to Deceased with death information

## Security & Validation

### Form Validation
- All required fields enforced (patient, death date)
- Death date cannot be in future
- Discharge date/time cannot be before admission date/time
- Patient must exist in database
- Patient cannot be already deceased

### Database Protection
- SQL injection prevention using mysqli_real_escape_string()
- Proper error handling with user-friendly messages
- Schema checks before ALTER TABLE operations
- Default values for new columns

### User Confirmation
- JavaScript confirmation dialogs before submission
- Warning messages about permanent status changes
- Clear labels and help text

## Testing Checklist

### Test Case 1: Discharge with Death
- [ ] Access discharge form for active admission
- [ ] Select "Death" from status dropdown
- [ ] Verify death date/time validation
- [ ] Submit form and verify success redirect
- [ ] Check ward_admissions: discharge_status = 'Death'
- [ ] Check patients: patient_status = 'Deceased', death_date recorded

### Test Case 2: Direct Death Registration
- [ ] Access death registration form
- [ ] Search for active patient
- [ ] Select patient from results
- [ ] Enter death date and notes
- [ ] Submit and verify success message
- [ ] Check patients: patient_status = 'Deceased'
- [ ] Try to register same patient again - should show error

### Test Case 3: Validation
- [ ] Try to select future death date - should prevent
- [ ] Try to discharge before admission - should show error
- [ ] Try to register death for already deceased patient - should show error
- [ ] Leave required fields empty - should show validation errors

### Test Case 4: Navigation
- [ ] Verify "Register Death" link appears in admission_list.php navbar
- [ ] Verify "Register Death" link appears in patient_list.php navbar
- [ ] Click links and verify correct form loads

## Database Schema Status

After implementation, the database will have:

**patients table:**
```
patient_id (PK)
calling_name
full_name
nic
hospital_number
clinic_number
date_of_birth
sex
blood_group
contact_number
address
guardian_name
guardian_contact_number
assigned_nursing_officer (FK)
patient_status (NEW) - ENUM('Active', 'Deceased', 'Inactive')
death_date (NEW) - DATE NULL
death_notes (NEW) - TEXT NULL
created_at
updated_at
```

**ward_admissions table:**
```
admission_id (PK)
patient_id (FK)
admission_date
admission_time
admission_reason_id (FK)
ward_bed
attending_doctor_id (FK)
nursing_officer_id (FK)
admission_status
discharge_date
discharge_time
discharge_notes
discharge_status (MODIFIED) - ENUM('Complete', 'Pending', 'Death')
created_by
created_at
updated_at
```

## Future Enhancements (Optional)

1. **Death Report:** Create a report showing all deceased patients with death dates and causes
2. **Death Certificate:** Generate printable death certificates
3. **Statistics Dashboard:** Add death count to dashboard statistics
4. **Audit Trail:** Log who registered each death and when
5. **Bulk Operations:** Filter patient lists to exclude/include deceased patients
6. **Notifications:** Alert relevant staff when death is registered
7. **Family Contact:** Automatic notification to guardian/family
8. **Medical Records:** Link to final medical reports and autopsy records

## Support & Troubleshooting

### If columns don't auto-create:
1. Run migration script: `pages/admissions/add_death_status.php`
2. Check database permissions
3. Verify config/db.php connection settings

### If "Death" option doesn't appear:
1. Clear browser cache
2. Check if discharge_status enum was updated
3. Run migration script manually

### If duplicate error on death registration:
- Check if patient_status is already 'Deceased'
- Review patients table for existing death_date
- Verify patient hasn't been discharged with death status

## Conclusion

The death status management system is now fully integrated into the Hospital Management System. Both ward-based deaths (through discharge) and non-ward deaths (direct registration) are properly tracked in the patient master file. All necessary database migrations are handled automatically on first use, ensuring smooth deployment.
