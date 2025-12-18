<?php
include '../../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}
include '../../includes/header.php';
?>
<div class="container">
    <div class="card">
        <h2>Master Files</h2>
        <p>Quick links to master data used across the system.</p>
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:12px;">
            <a class="btn btn-primary" href="<?php echo $base_path; ?>pages/users/user_list.php">Users</a>
            <a class="btn" href="<?php echo $base_path; ?>pages/doctors/doctor_list.php">Doctors</a>
            <a class="btn" href="<?php echo $base_path; ?>pages/nursing/nursing_list.php">Nursing Officers</a>
            <a class="btn" href="<?php echo $base_path; ?>pages/patients/patient_list.php">Patients</a>
            <a class="btn" href="<?php echo $base_path; ?>pages/medicines/medicine_master.php">Medicine Master</a>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
