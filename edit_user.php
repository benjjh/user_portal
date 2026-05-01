<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    header("location: admin_login.php");
    exit;
}

$message = "";

// DELETE USER LOGIC
if (isset($_GET["delete_id"])) {
    $delete_id = (int)$_GET["delete_id"];

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $delete_id);

    if ($stmt->execute()) {
        $message = "User deleted successfully.";
    } else {
        $message = "Error deleting user.";
    }
    $stmt->close();
}

// UPDATE USER LOGIC
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "update_user") {
    $id = (int)$_POST["id"];
    $username = trim($_POST["username"]);
    $role = trim($_POST["role"]);
    $name = trim($_POST["name"]);
    $surname = trim($_POST["surname"]);
    $birth_year_raw = trim($_POST["birth_year"]);
    $description = trim($_POST["description"]);

    $birth_year = ($birth_year_raw === "") ? 0 : (int)$birth_year_raw;

    $stmt = $conn->prepare("UPDATE users SET username = ?, role = ?, name = ?, surname = ?, birth_year = ?, description = ? WHERE id = ?");
    $stmt->bind_param("ssssisi", $username, $role, $name, $surname, $birth_year, $description, $id);

    if ($stmt->execute()) {
        $message = "User updated successfully!";
    } else {
        $message = "Error updating user: " . $stmt->error;
    }
    $stmt->close();
}

// LOAD USER FOR EDITING
$edit_user = null;
if (isset($_GET["edit_id"])) {
    $edit_id = (int)$_GET["edit_id"];
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_user = $result->fetch_assoc();
    $stmt->close();
}

// FETCH ALL USERS FOR THE TABLE
$users = $conn->query("SELECT * FROM users ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Users | Admin</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; padding: 30px; background: #fdfdfd; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #333; color: white; }
        .form-box { background: #f4f4f4; border: 1px solid #ccc; padding: 20px; margin-bottom: 25px; border-radius: 8px; }
        input, select, textarea { width: 100%; padding: 10px; margin: 8px 0; border: 1px solid #ccc; border-radius: 4px; }
        .msg { padding: 10px; background: #e7f3ef; color: #2d5a27; border: 1px solid #d0e9df; margin-bottom: 20px; border-radius: 4px; }
        .btn-save { background: #28a745; color: white; border: none; padding: 10px 20px; cursor: pointer; }
        .actions a { text-decoration: none; color: #007bff; font-weight: bold; margin-right: 10px; }
        .actions a.delete { color: #dc3545; }
    </style>
</head>
<body>

    <div class="top">
        <a href="admin_dashboard.php">← Back to Dashboard</a>
    </div>

    <h1>User Management</h1>

    <?php if ($message): ?>
        <div class="msg"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($edit_user): ?>
        <div class="form-box">
            <h2>Editing: <?php echo htmlspecialchars($edit_user['username']); ?></h2>
            <form method="post" action="edit_user.php">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="id" value="<?php echo (int)$edit_user['id']; ?>">

                <label>Username</label>
                <input type="text" name="username" value="<?php echo htmlspecialchars($edit_user['username']); ?>" required>

                <label>Role</label>
                <select name="role">
                    <option value="student" <?php echo ($edit_user['role'] === 'student') ? 'selected' : ''; ?>>Student</option>
                    <option value="teacher" <?php echo ($edit_user['role'] === 'teacher') ? 'selected' : ''; ?>>Teacher</option>
                </select>

                <label>Name</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($edit_user['name']); ?>">

                <label>Surname</label>
                <input type="text" name="surname" value="<?php echo htmlspecialchars($edit_user['surname']); ?>">

                <label>Birth Year</label>
                <input type="number" name="birth_year" value="<?php echo $edit_user['birth_year']; ?>">

                <label>Description</label>
                <textarea name="description"><?php echo htmlspecialchars($edit_user['description']); ?></textarea>

                <button type="submit" class="btn-save">Update User Info</button>
                <a href="edit_user.php">Cancel</a>
            </form>
        </div>
    <?php endif; ?>

    <h2>Current Users</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Role</th>
                <th>Full Name</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $users->fetch_assoc()): ?>
            <tr>
                <td><?php echo (int)$row['id']; ?></td>
                <td><strong><?php echo htmlspecialchars($row['username']); ?></strong></td>
                <td><?php echo ucfirst(htmlspecialchars($row['role'])); ?></td>
                <td><?php echo htmlspecialchars($row['name'] . " " . $row['surname']); ?></td>
                <td class="actions">
                    <a href="edit_user.php?edit_id=<?php echo $row['id']; ?>">Edit</a>
                    <a href="edit_user.php?delete_id=<?php echo $row['id']; ?>" class="delete" onclick="return confirm('Really delete?')">Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

</body>
</html>