<?php
// Use shared header/footer to match system UI (include header later to allow POST redirects)
include '../../config/db.php';

// create table if not exists
$sql = "CREATE TABLE IF NOT EXISTS medicine_master (
    master_id INT AUTO_INCREMENT PRIMARY KEY,
    medicine_name VARCHAR(255) NOT NULL,
    generic_name VARCHAR(255) NULL,
    strength VARCHAR(100) NULL,
    default_dosage VARCHAR(100) NULL,
    default_route VARCHAR(50) NULL,
    default_frequency VARCHAR(100) NULL,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($sql);

$error = '';
$master_id = 0;
$medicine_name = $default_route = '';
$active = 1;

// handle POST actions: create, update, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'create' || $action === 'update') {
        $medicine_name = $conn->real_escape_string(trim($_POST['medicine_name']));
        $default_route = $conn->real_escape_string(trim($_POST['default_route']));
        $active = isset($_POST['active']) ? 1 : 0;

        if (empty($medicine_name)) {
            $error = 'Medicine name is required.';
        } else {
            if ($action === 'create') {
                $ins = "INSERT INTO medicine_master (medicine_name, default_route, active) VALUES ('" . $medicine_name . "', '" . $default_route . "', " . $active . ")";
                $conn->query($ins);
            } else {
                $master_id = isset($_POST['master_id']) ? (int)$_POST['master_id'] : 0;
                $upd = "UPDATE medicine_master SET medicine_name = '" . $medicine_name . "', default_route = '" . $default_route . "', active = " . $active . " WHERE master_id = " . $master_id;
                $conn->query($upd);
            }
            header('Location: medicine_master.php'); exit();
        }
    } elseif ($action === 'delete') {
        $master_id = isset($_POST['master_id']) ? (int)$_POST['master_id'] : 0;
        if ($master_id) {
            $conn->query("DELETE FROM medicine_master WHERE master_id = " . $master_id);
        }
        header('Location: medicine_master.php'); exit();
    }
}

// if editing via GET
if (isset($_GET['edit'])) {
    $master_id = (int)$_GET['edit'];
    $r = $conn->query("SELECT * FROM medicine_master WHERE master_id = $master_id");
    if ($r && $r->num_rows) {
        $row = $r->fetch_assoc();
        $medicine_name = $row['medicine_name'];
        $default_route = $row['default_route'];
        $active = $row['active'];
    }
}

$search = '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$where = "WHERE 1=1";
if (isset($_GET['search']) && strlen(trim($_GET['search'])) > 0) {
    $search = $conn->real_escape_string(trim($_GET['search']));
    $where .= " AND (medicine_name LIKE '%$search%')";
}

// total count for pagination
$count_res = $conn->query("SELECT COUNT(*) AS cnt FROM medicine_master $where");
$total = ($count_res && $count_res->num_rows) ? (int)$count_res->fetch_assoc()['cnt'] : 0;
$total_pages = max(1, ceil($total / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;

$list = $conn->query("SELECT * FROM medicine_master $where ORDER BY medicine_name ASC LIMIT $per_page OFFSET $offset");

// include header after POST handling so header() redirects work correctly
include '../../includes/header.php';

// determine if current user is admin for permissioned actions
if (session_status() == PHP_SESSION_NONE) session_start();
$is_admin = false;
if (isset($_SESSION['user_id'])) {
    $cur = $conn->query("SELECT user_role FROM users WHERE user_id = " . (int)$_SESSION['user_id']);
    if ($cur && $cur->num_rows) {
        $rowu = $cur->fetch_assoc();
        $is_admin = (isset($rowu['user_role']) && $rowu['user_role'] === 'Admin');
    }
}
?>

<style>
    .mm-layout { display:flex; gap:24px; align-items:flex-start; }
    .mm-left { width:360px; flex:0 0 360px; }
    .mm-right { flex:1; }
    @media (max-width:900px) {
        .mm-layout { flex-direction:column; }
        .mm-left { width:100%; flex:unset; }
    }
</style>

<div class="container">
    <div class="mm-layout">
        <!-- Left: form card -->
        <div class="mm-left">
            <div class="card" style="padding:16px;">
                <h3 style="margin:0 0 12px 0">Add / Edit Medicine</h3>
                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <form method="post" action="<?php echo $base_path; ?>pages/medicines/medicine_master.php">
                    <input type="hidden" name="master_id" value="<?php echo (int)$master_id; ?>">
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        <label>Medicine name</label>
                        <input name="medicine_name" placeholder="Medicine name" value="<?php echo htmlspecialchars($medicine_name); ?>" required style="padding:8px;border:1px solid #ddd;border-radius:6px;">

                        <label>Route</label>
                        <select name="default_route" id="default_route" style="padding:8px;border:1px solid #ddd;border-radius:6px;">
                            <option value="">Select Route</option>
                            <option value="Oral" <?php echo ($default_route == 'Oral') ? 'selected' : ''; ?>>Oral</option>
                            <option value="Intravenous (IV)" <?php echo ($default_route == 'Intravenous (IV)') ? 'selected' : ''; ?>>Intravenous (IV)</option>
                            <option value="Intramuscular (IM)" <?php echo ($default_route == 'Intramuscular (IM)') ? 'selected' : ''; ?>>Intramuscular (IM)</option>
                            <option value="Subcutaneous (SC)" <?php echo ($default_route == 'Subcutaneous (SC)') ? 'selected' : ''; ?>>Subcutaneous (SC)</option>
                            <option value="Intraperitoneal (IP)" <?php echo ($default_route == 'Intraperitoneal (IP)') ? 'selected' : ''; ?>>Intraperitoneal (IP)</option>
                            <option value="Intradermal (ID)" <?php echo ($default_route == 'Intradermal (ID)') ? 'selected' : ''; ?>>Intradermal (ID)</option>
                            <option value="Local (LA)" <?php echo ($default_route == 'Local (LA)') ? 'selected' : ''; ?>>Local (LA)</option>
                            <option value="Topical" <?php echo ($default_route == 'Topical') ? 'selected' : ''; ?>>Topical</option>
                            <option value="Inhaled" <?php echo ($default_route == 'Inhaled') ? 'selected' : ''; ?>>Inhaled</option>
                            <option value="Rectal" <?php echo ($default_route == 'Rectal') ? 'selected' : ''; ?>>Rectal</option>
                            <option value="Other" <?php echo ($default_route == 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>

                        <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="active" <?php echo $active? 'checked':''; ?>> Active</label>

                        <div style="display:flex;gap:8px;">
                            <button type="submit" name="action" value="<?php echo $master_id? 'update':'create'; ?>" class="btn btn-primary" style="flex:1"><?php echo $master_id? 'Update':'Create'; ?></button>
                            <a href="<?php echo $base_path; ?>pages/medicines/medicine_master.php" class="btn btn-secondary" style="flex:1;text-align:center;display:inline-block;line-height:32px;">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right: list and search -->
        <div class="mm-right">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <h2 style="margin:0">Medicine Master</h2>
                <form method="get" style="display:flex;gap:8px;align-items:center;">
                    <input type="text" name="search" placeholder="Search master list..." value="<?php echo htmlspecialchars($search); ?>" style="padding:8px;border:1px solid #ddd;border-radius:6px;">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <?php if (!empty($search)): ?><a href="<?php echo $base_path; ?>pages/medicines/medicine_master.php" class="btn btn-secondary">Clear</a><?php endif; ?>
                </form>
            </div>

            <div class="card">
                <div style="overflow:auto;">
                <table style="min-width:640px;">
                    <thead>
                        <tr>
                            <th style="width:60px">#</th>
                            <th>Medicine</th>
                            <th style="width:180px">Route</th>
                            <th style="width:80px">Active</th>
                            <th style="width:160px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    if ($list && $list->num_rows) {
                        while ($r = $list->fetch_assoc()) {
                            echo '<tr>';
                            echo '<td>' . $r['master_id'] . '</td>';
                            echo '<td>' . htmlspecialchars($r['medicine_name']) . '</td>';
                            echo '<td>' . htmlspecialchars($r['default_route']) . '</td>';
                            echo '<td>' . ($r['active']? 'Yes':'No') . '</td>';
                            $actions = '';
                            $actions .= '<a href="' . $base_path . 'pages/medicines/medicine_master.php?edit=' . $r['master_id'] . '" class="btn">Edit</a>';
                            if (!empty($is_admin)) {
                                $actions .= ' <form method="post" class="inline" action="' . $base_path . 'pages/medicines/medicine_master.php" onsubmit="return confirm(\'Delete this medicine?\')" style="display:inline">';
                                $actions .= '<input type="hidden" name="master_id" value="' . $r['master_id'] . '">';
                                $actions .= '<button type="submit" name="action" value="delete" class="btn btn-danger btn-sm">Delete</button>';
                                $actions .= '</form>';
                            }
                            echo '<td>' . $actions . '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="5" style="text-align:center;color:#666">No medicines in master list</td></tr>';
                    }
                    ?>
                    </tbody>
                </table>
                </div>
            </div>

            <?php if ($total > $per_page): ?>
            <div style="margin-top:12px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <?php
                $base_q = '';
                if (!empty($search)) $base_q = 'search=' . urlencode($search) . '&';
                $prev = $page - 1;
                $next = $page + 1;
                if ($page > 1) echo '<a class="btn btn-secondary" href="' . $base_path . 'pages/medicines/medicine_master.php?' . $base_q . 'page=' . $prev . '">&larr; Prev</a>';
                for ($p = 1; $p <= $total_pages; $p++) {
                    if ($p == $page) echo '<span style="padding:8px 12px;background:#eee;border-radius:6px;">' . $p . '</span>';
                    else echo '<a class="btn" href="' . $base_path . 'pages/medicines/medicine_master.php?' . $base_q . 'page=' . $p . '" style="background:#fff;border:1px solid #ddd;">' . $p . '</a>';
                }
                if ($page < $total_pages) echo '<a class="btn btn-secondary" href="' . $base_path . 'pages/medicines/medicine_master.php?' . $base_q . 'page=' . $next . '">Next &rarr;</a>';
                ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
