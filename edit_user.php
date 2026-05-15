<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    header("location: admin_login.php");
    exit;
}

$message  = "";
$msg_type = "success";

// ── DELETE USER ──
if (isset($_GET["delete_id"])) {
    $delete_id = (int)$_GET["delete_id"];
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $message  = "User deleted successfully.";
        $msg_type = "success";
    } else {
        $message  = "Error deleting user: " . $stmt->error;
        $msg_type = "error";
    }
    $stmt->close();
}

// ── UPDATE USER ──
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "update_user") {
    $id          = (int)$_POST["id"];
    $username    = trim($_POST["username"]);
    $role        = trim($_POST["role"]);
    $name        = trim($_POST["name"]);
    $surname     = trim($_POST["surname"]);
    $birth_year  = trim($_POST["birth_year"]);
    $description = trim($_POST["description"]);
    $birth_year  = ($birth_year === "") ? 0 : (int)$birth_year;

    // Check for duplicate username (excluding current user)
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->bind_param("si", $username, $id);
    $stmt->execute();
    $dup = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($dup) {
        $message  = "Username '$username' is already taken by another user.";
        $msg_type = "error";
    } else {
        $stmt = $conn->prepare(
            "UPDATE users SET username=?, role=?, name=?, surname=?, birth_year=?, description=? WHERE id=?"
        );
        $stmt->bind_param("ssssisi", $username, $role, $name, $surname, $birth_year, $description, $id);
        if ($stmt->execute()) {
            $message  = "User updated successfully!";
            $msg_type = "success";
        } else {
            $message  = "Error updating user: " . $stmt->error;
            $msg_type = "error";
        }
        $stmt->close();
    }
}

// ── LOAD USER FOR EDITING ──
$edit_user = null;
if (isset($_GET["edit_id"])) {
    $edit_id = (int)$_GET["edit_id"];
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// ── FETCH ALL USERS ──
$users = $conn->query("SELECT * FROM users ORDER BY role ASC, username ASC");

// Count by role
$count_res = $conn->query("SELECT role, COUNT(*) AS cnt FROM users GROUP BY role");
$counts = ['student' => 0, 'teacher' => 0];
while ($r = $count_res->fetch_assoc()) $counts[$r['role']] = (int)$r['cnt'];
$total_users = array_sum($counts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Users | Admin</title>
    <style>
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'Segoe UI',Arial,sans-serif; background:#f0f2f5; min-height:100vh; display:flex; flex-direction:column; }

        /* Navbar */
        .navbar { background:#1a1a2e; padding:15px 40px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 2px 8px rgba(0,0,0,0.15); }
        .navbar .logo { color:white; font-size:1.1rem; font-weight:700; }
        .navbar .nav-links a { color:#a0c4ff; text-decoration:none; margin-left:20px; font-weight:600; font-size:0.92rem; }
        .navbar .nav-links a:hover { color:white; }

        .container { max-width:1100px; margin:36px auto; padding:0 20px; flex:1; width:100%; }

        .page-title { margin-bottom:26px; }
        .page-title h1 { font-size:1.8rem; color:#1a1a2e; font-weight:800; }
        .page-title p  { color:#666; margin-top:5px; font-size:0.95rem; }

        /* Messages */
        .msg { padding:12px 18px; border-radius:8px; margin-bottom:22px; font-size:0.93rem; font-weight:600; display:flex; align-items:center; gap:10px; }
        .msg-success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
        .msg-error   { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }

        /* Stats */
        .stats-bar { display:flex; gap:14px; margin-bottom:26px; flex-wrap:wrap; }
        .stat { background:white; border-radius:10px; padding:16px 22px; flex:1; min-width:120px; text-align:center; box-shadow:0 2px 10px rgba(0,0,0,0.06); }
        .stat .num { font-size:1.9rem; font-weight:800; }
        .stat .lbl { font-size:0.78rem; color:#777; margin-top:3px; text-transform:uppercase; letter-spacing:0.4px; }
        .stat-total   { border-left:4px solid #1a1a2e; } .stat-total .num   { color:#1a1a2e; }
        .stat-teacher { border-left:4px solid #1a3a2a; } .stat-teacher .num { color:#1a3a2a; }
        .stat-student { border-left:4px solid #0d6efd; } .stat-student .num { color:#0d6efd; }

        /* Cards */
        .card { background:white; border-radius:12px; padding:26px; box-shadow:0 2px 14px rgba(0,0,0,0.07); margin-bottom:26px; }
        .card h2 { font-size:1.05rem; color:#1a1a2e; font-weight:700; margin-bottom:20px; padding-bottom:10px; border-bottom:2px solid #f0f2f5; }
        .card.editing { border:2px solid #fd7e14; }
        .card.editing h2 { color:#fd7e14; }

        /* Form grid */
        .form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:0 20px; }
        .form-full { grid-column:1 / -1; }

        label { display:block; font-weight:600; color:#444; font-size:0.87rem; margin-bottom:6px; margin-top:4px; }
        input[type="text"], input[type="number"],
        select, textarea {
            width:100%; padding:10px 13px; border:1.5px solid #ddd; border-radius:8px;
            font-size:0.93rem; margin-bottom:16px; background:#fafafa; font-family:inherit;
            transition:border-color 0.15s, background 0.15s;
        }
        input:focus, select:focus, textarea:focus { outline:none; border-color:#1a1a2e; background:white; }
        textarea { resize:vertical; }

        .btn { padding:10px 22px; border:none; border-radius:8px; cursor:pointer; font-size:0.88rem; font-weight:700; transition:opacity 0.15s; text-decoration:none; display:inline-block; }
        .btn:hover { opacity:0.85; }
        .btn-primary   { background:#1a1a2e; color:white; }
        .btn-warning   { background:#fd7e14; color:white; }
        .btn-danger    { background:#dc3545; color:white; }
        .btn-secondary { background:#6c757d; color:white; }
        .btn-sm { padding:5px 13px; font-size:0.8rem; }
        .btn-row { display:flex; gap:10px; margin-top:4px; }

        /* Search */
        .search-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px; }
        .search-row input {
            padding:9px 14px; border:1.5px solid #ddd; border-radius:8px;
            font-size:0.9rem; width:260px; background:#fafafa; font-family:inherit;
        }
        .search-row input:focus { outline:none; border-color:#1a1a2e; }

        /* Table */
        table { width:100%; border-collapse:collapse; }
        thead tr { background:#1a1a2e; }
        thead th { color:white; padding:12px 14px; text-align:left; font-size:0.84rem; font-weight:700; }
        thead th:first-child { border-radius:6px 0 0 0; }
        thead th:last-child  { border-radius:0 6px 0 0; }
        tbody tr { border-bottom:1px solid #eee; transition:background 0.1s; }
        tbody tr:last-child { border-bottom:none; }
        tbody tr:hover { background:#f7f9ff; }
        tbody td { padding:12px 14px; font-size:0.88rem; color:#333; vertical-align:middle; }

        /* User info cell */
        .user-cell { display:flex; align-items:center; gap:12px; }
        .u-avatar { width:40px; height:40px; border-radius:50%; object-fit:cover; border:2px solid #ddd; flex-shrink:0; }
        .u-placeholder { width:40px; height:40px; border-radius:50%; background:#e8eef8; display:flex; align-items:center; justify-content:center; font-size:1rem; border:2px solid #ddd; flex-shrink:0; }
        .u-name { font-weight:700; color:#1a1a2e; font-size:0.9rem; }
        .u-user { font-size:0.78rem; color:#aaa; }

        /* Role badges */
        .role-badge { display:inline-block; padding:3px 12px; border-radius:20px; font-size:0.78rem; font-weight:800; text-transform:uppercase; letter-spacing:0.4px; }
        .role-teacher { background:#d4edda; color:#155724; }
        .role-student { background:#cce5ff; color:#004085; }

        /* Action buttons */
        .actions { display:flex; gap:6px; flex-wrap:wrap; }

        /* Bio cell */
        .bio-cell { max-width:160px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#666; font-size:0.84rem; }

        /* Empty */
        .empty { text-align:center; padding:36px; color:#bbb; }

        .footer { background:#1a1a2e; color:#a0c4ff; padding:22px 40px; font-size:0.88rem; display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px; }
        .footer p { margin-top:3px; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="logo">Admin Panel</div>
    <div class="nav-links">
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="admin_logout.php">Logout</a>
    </div>
</div>

<div class="container">

    <div class="page-title">
        <h1>User Management</h1>
        <p>Edit user details, change roles, or remove accounts from the system.</p>
    </div>

    <?php if ($message): ?>
        <div class="msg msg-<?php echo $msg_type; ?>">
            <?php echo $msg_type === 'success' ? '' : ''; ?>
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-bar">
        <div class="stat stat-total">
            <div class="num"><?php echo $total_users; ?></div>
            <div class="lbl">Total Users</div>
        </div>
        <div class="stat stat-teacher">
            <div class="num"><?php echo $counts['teacher']; ?></div>
            <div class="lbl">Teachers</div>
        </div>
        <div class="stat stat-student">
            <div class="num"><?php echo $counts['student']; ?></div>
            <div class="lbl">Students</div>
        </div>
    </div>

    <!-- Edit form (shown only when editing) -->
    <?php if ($edit_user): ?>
    <div class="card editing">
        <h2>✏️ Editing User — <?php echo htmlspecialchars($edit_user['username']); ?></h2>
        <form method="post" action="edit_user.php">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="id"     value="<?php echo (int)$edit_user['id']; ?>">

            <div class="form-grid-2">
                <div>
                    <label>Username <span style="color:#dc3545;">*</span></label>
                    <input type="text" name="username"
                           value="<?php echo htmlspecialchars($edit_user['username']); ?>" required>
                </div>
                <div>
                    <label>Role <span style="color:#dc3545;">*</span></label>
                    <select name="role" required>
                        <option value="student" <?php echo $edit_user['role']==='student'?'selected':''; ?>>Student</option>
                        <option value="teacher" <?php echo $edit_user['role']==='teacher'?'selected':''; ?>>Teacher</option>
                    </select>
                </div>
                <div>
                    <label>First Name</label>
                    <input type="text" name="name"
                           value="<?php echo htmlspecialchars($edit_user['name'] ?? ''); ?>">
                </div>
                <div>
                    <label>Surname</label>
                    <input type="text" name="surname"
                           value="<?php echo htmlspecialchars($edit_user['surname'] ?? ''); ?>">
                </div>
                <div>
                    <label>Year of Birth</label>
                    <input type="number" name="birth_year" min="1900" max="2099"
                           value="<?php echo htmlspecialchars($edit_user['birth_year'] ?? ''); ?>">
                </div>
                <div class="form-full">
                    <label>Description / About</label>
                    <textarea name="description" rows="3"><?php echo htmlspecialchars($edit_user['description'] ?? ''); ?></textarea>
                </div>
            </div>

            <div class="btn-row">
                <button type="submit" class="btn btn-warning">Save Changes</button>
                <a href="edit_user.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Users table -->
    <div class="card">
        <h2>👥 All Users</h2>

        <div class="search-row">
            <span style="font-size:0.85rem;color:#888;">
                <strong><?php echo $total_users; ?></strong> user<?php echo $total_users !== 1 ? 's' : ''; ?> registered
            </span>
            <input type="text" id="searchInput" placeholder="Search by name, username, or role..." onkeyup="filterTable()">
        </div>

        <?php if ($total_users === 0): ?>
            <div class="empty">No users found.</div>
        <?php else: ?>
        <table id="usersTable">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Full Name</th>
                    <th>Birth Year</th>
                    <th>About</th>
                    <th>Photo</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $users->fetch_assoc()):
                    $full  = trim(($row['name']??'').' '.($row['surname']??''));
                    $photo = $row['photo'] ?? '';
                    $photo_abs = __DIR__ . '/' . $photo;
                ?>
                <tr>
                    <td>
                        <div class="user-cell">
                            <?php if (!empty($photo) && file_exists($photo_abs)): ?>
                                <img src="<?php echo htmlspecialchars($photo); ?>?t=<?php echo time(); ?>" class="u-avatar" alt="">
                            <?php else: ?>
                                <div class="u-placeholder">👤</div>
                            <?php endif; ?>
                            <div>
                                <div class="u-name"><?php echo htmlspecialchars($row['username']); ?></div>
                                <div class="u-user">ID: <?php echo (int)$row['id']; ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="role-badge <?php echo $row['role']==='teacher'?'role-teacher':'role-student'; ?>">
                            <?php echo $row['role']==='teacher' ? '🧑‍🏫 Teacher' : '🎓 Student'; ?>
                        </span>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($full ?: '—'); ?>
                    </td>
                    <td style="color:#888;">
                        <?php echo htmlspecialchars($row['birth_year'] && $row['birth_year'] != '0000' ? $row['birth_year'] : '—'); ?>
                    </td>
                    <td>
                        <span class="bio-cell" title="<?php echo htmlspecialchars($row['description'] ?? ''); ?>">
                            <?php echo htmlspecialchars($row['description'] ?: '—'); ?>
                        </span>
                    </td>
                    <td>
                        <?php if (!empty($photo) && file_exists($photo_abs)): ?>
                            <img src="<?php echo htmlspecialchars($photo); ?>?t=<?php echo time(); ?>"
                                 style="width:38px;height:38px;border-radius:8px;object-fit:cover;border:1px solid #ddd;" alt="">
                        <?php else: ?>
                            <span style="color:#ccc;font-size:0.82rem;">No photo</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="actions">
                            <a href="edit_user.php?edit_id=<?php echo (int)$row['id']; ?>"
                               class="btn btn-warning btn-sm">✏️ Edit</a>
                            <a href="edit_user.php?delete_id=<?php echo (int)$row['id']; ?>"
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Delete user \'<?php echo htmlspecialchars(addslashes($row['username'])); ?>\'?\n\nThis will also remove all their grades, attendance, and enrollments. This cannot be undone.')">
                               🗑️ Delete
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div>

<div class="footer">
    <div>
        <p>• Phone: +37000000002</p>
        <p>• Email: admin@portal.com</p>
    </div>
    <div style="align-self:flex-end;color:#555;font-size:0.82rem;">&copy; 2026 Legit Portal</div>
</div>

<script>
function filterTable() {
    var input = document.getElementById("searchInput").value.toLowerCase();
    var rows  = document.querySelectorAll("#usersTable tbody tr");
    rows.forEach(function(row) {
        row.style.display = row.innerText.toLowerCase().includes(input) ? "" : "none";
    });
}
</script>

</body>
</html>