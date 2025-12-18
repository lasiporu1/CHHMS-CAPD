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
        <h2>Transactions</h2>
        <p>Admission and clinic transaction screens.</p>
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:12px;">
            <a class="btn btn-primary" href="<?php echo $base_path; ?>pages/admissions/admission_list.php">Ward Admissions</a>
            <a class="btn" href="<?php echo $base_path; ?>pages/clinics/clinic_admissions_list.php">Clinic Admissions</a>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
