<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';

if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    header("location: admin_login.php");
    exit;
}

$add_user_message = "";

// Create user
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'create_user') {
    $username = trim($_POST['new_username']);
    $plain_password = $_POST['new_password'];
    $role = $_POST['role'];

    if ($username === "" || $plain_password === "") {
        $add_user_message = "Please fill in all fields.";
    } else {
        $password = password_hash($plain_password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, password, role, name, surname, birth_year, description, photo)
                VALUES (?, ?, ?, '', '', 0, '', '')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $username, $password, $role);
        if ($stmt->execute()) {
            $add_user_message = "User created successfully!";
        } else {
            $add_user_message = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

$users = $conn->query("SELECT id, username, role, name, surname, birth_year, description, photo FROM users ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; min-height: 100vh; display: flex; flex-direction: column; }
        .navbar { background: #1a1a2e; color: white; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; }
        .navbar strong { font-size: 1.1rem; letter-spacing: 0.5px; }
        .navbar a { color: #a0c4ff; text-decoration: none; margin-left: 20px; font-weight: 600; }
        .navbar a:hover { color: white; }
        .content { flex: 1; padding: 36px 40px; max-width: 1200px; margin: 0 auto; width: 100%; }
        h1 { font-size: 1.8rem; color: #1a1a2e; margin-bottom: 6px; }
        h2 { font-size: 1.2rem; color: #1a1a2e; margin-bottom: 16px; }
        h3 { font-size: 1rem; color: #333; margin-bottom: 14px; }

        /* Quick links */
        .admin-links { background: white; border-radius: 10px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); margin-bottom: 28px; }
        .admin-links h3 { border-bottom: 2px solid #f0f2f5; padding-bottom: 10px; margin-bottom: 16px; }
        .link-grid { display: flex; flex-wrap: wrap; gap: 12px; }
        .link-grid a {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 18px; background: #f0f2f5; color: #1a1a2e;
            text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 0.9rem;
            border: 1px solid #dde3f0; transition: all 0.15s;
        }
        .link-grid a:hover { background: #1a1a2e; color: white; border-color: #1a1a2e; }

        /* Create user box */
        .create-box { background: white; border-radius: 10px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); margin-bottom: 28px; }
        .create-box h3 { border-bottom: 2px solid #f0f2f5; padding-bottom: 10px; margin-bottom: 16px; }
        .form-inline { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; }
        .form-inline > div { display: flex; flex-direction: column; }
        .form-inline label { font-size: 0.82rem; font-weight: 600; color: #555; margin-bottom: 4px; }
        .form-inline input[type="text"],
        .form-inline input[type="password"],
        .form-inline select {
            padding: 9px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 0.9rem; background: #fafafa;
        }
        .form-inline input:focus, .form-inline select:focus { outline: none; border-color: #4a90d9; }
        .msg { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px 16px; border-radius: 6px; margin-bottom: 14px; font-size: 0.92rem; }
        .btn { padding: 9px 22px; background: #1a1a2e; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 0.92rem; font-weight: 600; }
        .btn:hover { background: #2d2d5e; }

        /* Users table */
        .table-card { background: white; border-radius: 10px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1a1a2e; color: white; padding: 12px 14px; text-align: left; font-size: 0.88rem; }
        td { padding: 11px 14px; border-bottom: 1px solid #eee; font-size: 0.9rem; vertical-align: middle; }
        tr:hover td { background: #f9fafb; }
        .role-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; }
        .role-teacher { background: #d4edda; color: #155724; }
        .role-student { background: #cce5ff; color: #004085; }
        .user-photo { width: 46px; height: 46px; object-fit: cover; border-radius: 50%; border: 2px solid #ddd; }
        .no-photo { width: 46px; height: 46px; border-radius: 50%; background: #dde3f0; display: inline-flex; align-items: center; justify-content: center; font-size: 1.2rem; border: 2px solid #ddd; }
        .actions a { text-decoration: none; font-weight: 600; font-size: 0.85rem; }
        .actions a.edit { color: #007bff; margin-right: 10px; }
        .actions a.del { color: #dc3545; }

        /* Footer */
        .footer { background: #1a1a2e; color: #ccc; padding: 24px 40px; font-size: 0.88rem; }
        .footer p { margin-top: 4px; }
    </style>
</head>
<body>
<div class="navbar">
    <strong>Admin Panel</strong>
    <div>
        <a href="index.php">Home</a>
        <a href="admin_logout.php">Logout</a>
    </div>
</div>

<div class="content">
    <h1>Welcome, Admin <?php echo htmlspecialchars($_SESSION["admin_username"]); ?>!</h1>
    <p style="color:#666;margin-bottom:28px;">Manage users, subjects, grades, and attendance from here.</p>

    <!-- Quick links -->
    <div class="admin-links">
        <h3>System Management</h3>
        <div class="link-grid">
            <a href="manage_subjects.php">📚 Manage Subjects</a>
            <a href="enroll_students.php">📋 Enroll Students</a>
            <a href="attendance_admin.php"> Student Attendance</a>
            <a href="teacher_attendance_admin.php">🧑‍🏫 Teacher Attendance</a>
            <a href="view_attendance_admin.php">👁 View Attendance</a>
            <a href="grades_admin.php">📝 Upload Grades</a>
            <a href="view_grades_admin.php">📊 View Grades</a>
            <a href="edit_user.php">👤 Edit Users</a>
        </div>
    </div>

    <!-- Add new user -->
    <div class="create-box">
        <h3>Add New User</h3>
        <?php if ($add_user_message): ?>
            <div class="msg"><?php echo htmlspecialchars($add_user_message); ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="action" value="create_user">
            <div class="form-inline">
                <div>
                    <label>Username</label>
                    <input type="text" name="new_username" placeholder="e.g. john_doe" required>
                </div>
                <div>
                    <label>Password</label>
                    <input type="password" name="new_password" placeholder="Password" required>
                </div>
                <div>
                    <label>Role</label>
                    <select name="role" required>
                        <option value="student">Student</option>
                        <option value="teacher">Teacher</option>
                    </select>
                </div>
                <div>
                    <label>&nbsp;</label>
                    <button type="submit" class="btn">Add User</button>
                </div>
            </div>
        </form>
    </div>

    <!-- User list -->
    <div class="table-card">
        <h2>All Users</h2>
        <table>
            <tr>
                <th>Photo</th>
                <th>ID</th>
                <th>Username</th>
                <th>Role</th>
                <th>Name</th>
                <th>Birth Year</th>
                <th>About</th>
                <th>Actions</th>
            </tr>
            <?php while ($row = $users->fetch_assoc()):
                // Fix photo path: handle both 'uploads/file.jpg' and blank
                $photo = $row['photo'] ?? '';
            ?>
            <tr>
                <td>
                    <?php if (!empty($photo) && file_exists($photo)): ?>
                        <img src="<?php echo htmlspecialchars($photo); ?>?t=<?php echo time(); ?>" class="user-photo" alt="photo">
                    <?php else: ?>
                        <span class="no-photo">👤</span>
                    <?php endif; ?>
                </td>
                <td><?php echo (int)$row['id']; ?></td>
                <td><strong><?php echo htmlspecialchars($row['username']); ?></strong></td>
                <td><span class="role-badge <?php echo $row['role'] === 'teacher' ? 'role-teacher' : 'role-student'; ?>"><?php echo ucfirst($row['role']); ?></span></td>
                <td><?php echo htmlspecialchars(trim(($row['name'] ?? '') . ' ' . ($row['surname'] ?? '')) ?: '—'); ?></td>
                <td><?php echo htmlspecialchars($row['birth_year'] ?: '—'); ?></td>
                <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($row['description'] ?: '—'); ?></td>
                <td class="actions">
                    <a href="edit_user.php?edit_id=<?php echo $row['id']; ?>" class="edit">Edit</a>
                    <a href="edit_user.php?delete_id=<?php echo $row['id']; ?>" class="del" onclick="return confirm('Delete this user? This cannot be undone.')">Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>

<div class="footer">
    <p>• Phone: +37000000002</p>
    <p>• Email: admin@portal.com</p>
</div>
</body>
</html>