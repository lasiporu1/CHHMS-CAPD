<?php
// Endpoint to receive UI click logs from the client and write to activity_log via log_activity()
if (session_status() === PHP_SESSION_NONE) session_start();
include_once __DIR__ . '/db.php';

$uid = $_SESSION['user_id'] ?? null;

$data = null;
// Accept JSON payload or form-encoded
$raw = file_get_contents('php://input');
if ($raw) {
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) $data = $decoded;
}
if ($data === null) {
    $data = $_POST ?: [];
}

$action = $data['action'] ?? ($data['dataAction'] ?? 'click');
$element = $data['element'] ?? ($data['tag'] ?? 'unknown');
$text = $data['text'] ?? '';
$href = $data['href'] ?? '';
$page = $data['page'] ?? ($_SERVER['REQUEST_URI'] ?? '');

$details = json_encode(['element'=>$element, 'text'=>$text, 'href'=>$href, 'page'=>$page]);

if (function_exists('log_activity')) {
    log_activity($uid, $action, 'ui', null, $details);
}

// Return simple JSON response
header('Content-Type: application/json');
echo json_encode(['status'=>'ok']);
exit();

?>
