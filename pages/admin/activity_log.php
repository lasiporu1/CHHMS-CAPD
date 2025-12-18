<?php
include '../../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}
// Only Admins
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    include '../../includes/header.php';
    echo '<div class="container"><div class="card"><h2>Unauthorized</h2><p>You do not have permission to view this page.</p></div></div>'; 
    include '../../includes/footer.php';
    exit();
}

include '../../includes/header.php';

// Filters
$from = isset($_GET['from']) ? $conn->real_escape_string($_GET['from']) : '';
$to = isset($_GET['to']) ? $conn->real_escape_string($_GET['to']) : '';
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$action = isset($_GET['action']) ? $conn->real_escape_string($_GET['action']) : '';
$q = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';
$page = isset($_GET['page']) ? max(1,(int)$_GET['page']) : 1;
$limit = 50;
$offset = ($page-1)*$limit;

$where = [];
$where[] = '1';
if ($from) {
    $where[] = "created_at >= '" . $from . " 00:00:00'";
}
if ($to) {
    $where[] = "created_at <= '" . $to . " 23:59:59'";
}
if ($user_id) {
    $where[] = "al.user_id = " . (int)$user_id;
}
if ($action) {
    $where[] = "al.action = '" . $action . "'";
}
if ($q) {
    $like = '%' . $q . '%';
    $likeEsc = $conn->real_escape_string($like);
    $where[] = "(al.details LIKE '" . $likeEsc . "' OR al.table_name LIKE '" . $likeEsc . "' OR al.action LIKE '" . $likeEsc . "' OR al.record_id LIKE '" . $likeEsc . "')";
}

$where_sql = implode(' AND ', $where);

// Count total
$count_sql = "SELECT COUNT(*) AS c FROM activity_log al LEFT JOIN users u ON al.user_id = u.user_id WHERE " . $where_sql;
$cres = $conn->query($count_sql);
$total = 0;
if ($cres) $total = (int)$cres->fetch_assoc()['c'];

$sql = "SELECT al.*, u.username FROM activity_log al LEFT JOIN users u ON al.user_id = u.user_id WHERE " . $where_sql . " ORDER BY al.created_at DESC LIMIT " . (int)$offset . "," . (int)$limit;
$res = $conn->query($sql);

// Fetch users for filter dropdown
$users = [];
$ur = $conn->query("SELECT user_id, username FROM users ORDER BY username");
if ($ur) { while ($urow = $ur->fetch_assoc()) $users[] = $urow; }

// Recent activity (always show top 20 recent entries)
$recent = [];
$rr = $conn->query("SELECT al.*, u.username FROM activity_log al LEFT JOIN users u ON al.user_id = u.user_id ORDER BY al.created_at DESC LIMIT 20");
if ($rr) { while ($rrow = $rr->fetch_assoc()) $recent[] = $rrow; }

?>
<div class="container">
    <div class="card">
        <h2>Activity Log</h2>
        <?php if (!empty($recent)): ?>
            <div style="margin-bottom:1rem;padding:0.6rem;border:1px solid #eee;background:#fafafa;border-radius:6px;">
                <strong>Recent activity (latest 20)</strong>
                <div style="margin-top:0.5rem;max-height:160px;overflow:auto;padding-top:0.5rem;">
                    <table style="width:100%;font-size:0.9rem;border-collapse:collapse;">
                        <thead><tr><th style="text-align:left;">Date</th><th>User</th><th>Action</th><th>Details</th></tr></thead>
                        <tbody>
                        <?php foreach($recent as $rrr): ?>
                            <tr style="border-bottom:1px solid #f1f1f1;"><td><?php echo htmlspecialchars($rrr['created_at']); ?></td><td><?php echo htmlspecialchars($rrr['username'] ?: $rrr['user_id']); ?></td><td><?php echo htmlspecialchars($rrr['action']); ?></td><td><?php echo htmlspecialchars(substr($rrr['details'],0,200)); ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        <form method="get" style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem;align-items:end;">
            <div>
                <label>From</label><br>
                <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>">
            </div>
            <div>
                <label>To</label><br>
                <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>">
            </div>
            <div>
                <label>User</label><br>
                <select name="user_id">
                    <option value="0">All</option>
                    <?php foreach($users as $u): ?>
                        <option value="<?php echo $u['user_id']; ?>" <?php if ($user_id==$u['user_id']) echo 'selected'; ?>><?php echo htmlspecialchars($u['username']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Action</label><br>
                <input type="text" name="action" placeholder="login,create,update,delete" value="<?php echo htmlspecialchars($action); ?>">
            </div>
            <div style="flex:1;">
                <label>Search</label><br>
                <input type="text" name="q" placeholder="search details, table, record id" value="<?php echo htmlspecialchars($q); ?>" style="width:100%;">
            </div>
            <div>
                <button class="btn btn-primary">Filter</button>
            </div>
        </form>

        <div style="margin-bottom:0.5rem;color:#666;">Showing <?php echo min($total, $offset+1); ?> - <?php echo min($total, $offset+$limit); ?> of <?php echo $total; ?> entries</div>

        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Table</th>
                        <th>Record ID</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($res && $res->num_rows): while($row = $res->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                        <td><?php echo htmlspecialchars($row['username'] ?: $row['user_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['action']); ?></td>
                        <td><?php echo htmlspecialchars($row['table_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['record_id']); ?></td>
                        <td style="max-width:420px;white-space:pre-wrap;word-break:break-word;"><?php echo htmlspecialchars($row['details']); ?></td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="6" style="text-align:center;color:#666;padding:1rem;">No logs found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top:1rem;display:flex;gap:0.5rem;align-items:center;">
            <?php
            $pages = ceil($total / $limit);
            for ($i=1;$i<=$pages;$i++):
                $qs = $_GET; $qs['page']=$i; $link = '?' . http_build_query($qs);
            ?>
                <a href="<?php echo $link; ?>" class="btn <?php echo $i==$page ? 'btn-primary' : 'btn-secondary'; ?>" style="padding:0.4rem 0.6rem"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
