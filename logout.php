<?php
session_start();
// Log logout if possible
$uid = $_SESSION['user_id'] ?? null;
include 'config/db.php';
if ($uid !== null && function_exists('log_activity')) {
	log_activity($uid, 'logout', 'users', $uid, json_encode(['ip'=>($_SERVER['REMOTE_ADDR'] ?? '')]));
}
session_destroy();
header("Location: login.php");
exit();
?>
