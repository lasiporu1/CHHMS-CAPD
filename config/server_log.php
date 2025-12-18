<?php
// Server-side automatic logger for POST requests.
// Include this from a common header to capture form submissions and server-side actions.
if (session_status() == PHP_SESSION_NONE) session_start();
include_once __DIR__ . '/db.php';

function log_server_post_action() {
    if (!function_exists('log_activity')) return;
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $uid = $_SESSION['user_id'] ?? null;
    $page = $_SERVER['REQUEST_URI'] ?? ($_SERVER['PHP_SELF'] ?? '');
    // Truncate POST payload to avoid huge logs
    $payload = [];
    foreach ($_POST as $k => $v) {
        if (is_string($v) && strlen($v) > 1000) {
            $payload[$k] = substr($v, 0, 1000) . '...';
        } else {
            $payload[$k] = $v;
        }
    }
    // Remove sensitive fields
    if (isset($payload['password'])) $payload['password'] = '***';
    if (isset($payload['confirm_password'])) $payload['confirm_password'] = '***';

    $details = json_encode(['method'=>'POST','page'=>$page,'data'=>$payload]);
    log_activity($uid, 'server_post', 'server', null, $details);
}

// auto-run when included
log_server_post_action();

?>
