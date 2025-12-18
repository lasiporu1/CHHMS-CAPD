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
        <h2>Reports</h2>
        <p>Central reports and activity log.</p>
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:12px;">
            <a class="btn btn-primary" href="<?php echo $base_path; ?>pages/reports/report_list.php">Reports List</a>
            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin'): ?>
                <a class="btn" href="<?php echo $base_path; ?>pages/admin/activity_log.php">Activity Log</a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
