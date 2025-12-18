# Navigation & Header Guide

This project uses a simplified top navigation. The header (`includes/header.php`) now exposes the following top-level items:

- Dashboard — links to `/index.php`
- Master Files — dropdown with quick links to master pages:
  - Users: `pages/users/user_list.php`
  - Doctors: `pages/doctors/doctor_list.php`
  - Nursing Officers: `pages/nursing/nursing_list.php`
  - Patients: `pages/patients/patient_list.php`
  - Medicine Master: `pages/medicines/medicine_master.php`
- Transactions — dropdown with:
  - Ward Admissions: `pages/admissions/admission_list.php`
  - Clinic Admissions: `pages/clinics/clinic_admissions_list.php`
  - Bed Management: `pages/admissions/bed_management.php`
- Reports — dropdown with:
  - Reports List: `pages/reports/report_list.php`
  - Activity Log (Admin only): `pages/admin/activity_log.php`
- Logout — `logout.php`

Notes for adding future pages:
- To add a new Master link, edit `includes/header.php` and add an `<a>` entry inside the Master Files dropdown menu.
- Use `<?php echo $base_path; ?>` as the prefix inside `includes/header.php` for correct relative linking.
- The dropdowns support hover on desktop and click-to-toggle for touch devices.

If you want the header to show additional items (e.g., a quick link to a specific report), tell me which target and I'll add it.