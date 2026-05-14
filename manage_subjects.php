<?php
require_once 'config.php';

if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    header("location: admin_login.php");
    exit;
}

$message  = "";
$msg_type = "success";
$edit_subject = null;

// Load teachers
$teachers = [];
$res = $conn->query("SELECT id, username, name, surname FROM users WHERE role = 'teacher' ORDER BY username ASC");
while ($row = $res->fetch_assoc()) $teachers[] = $row;

// ── DELETE SUBJECT ──
if (isset($_GET["delete_id"])) {
    $delete_id = (int)$_GET["delete_id"];
    $stmt = $conn->prepare("DELETE FROM subjects WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $message  = "Subject deleted successfully. All related enrollments, attendance, and grades for this subject have also been removed.";
        $msg_type = "success";
    } else {
        $message  = "Error deleting subject: " . $stmt->error;
        $msg_type = "error";
    }
    $stmt->close();
}

// ── LOAD SUBJECT FOR EDITING ──
if (isset($_GET["edit_id"])) {
    $edit_id = (int)$_GET["edit_id"];
    $stmt = $conn->prepare("SELECT id, subject_name, subject_code, teacher_id FROM subjects WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_subject = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// ── UPDATE SUBJECT ──
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "update_subject") {
    $edit_id      = (int)$_POST["edit_id"];
    $subject_name = trim($_POST["subject_name"]);
    $subject_code = trim($_POST["subject_code"]);
    $teacher_id   = (int)$_POST["teacher_id"];

    if ($subject_name === "" || $teacher_id <= 0) {
        $message  = "Subject name and teacher are required.";
        $msg_type = "error";
    } else {
        // Check for duplicate code (excluding current subject)
        if ($subject_code !== "") {
            $stmt = $conn->prepare("SELECT id FROM subjects WHERE subject_code = ? AND id != ?");
            $stmt->bind_param("si", $subject_code, $edit_id);
            $stmt->execute();
            $dup = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($dup) {
                $message  = "Subject code '$subject_code' is already used by another subject. Each subject must have a unique code.";
                $msg_type = "error";
                // Reload edit form
                $stmt = $conn->prepare("SELECT id, subject_name, subject_code, teacher_id FROM subjects WHERE id = ?");
                $stmt->bind_param("i", $edit_id);
                $stmt->execute();
                $edit_subject = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }
        }

        if ($message === "") {
            $stmt = $conn->prepare("UPDATE subjects SET subject_name=?, subject_code=?, teacher_id=? WHERE id=?");
            $stmt->bind_param("ssii", $subject_name, $subject_code, $teacher_id, $edit_id);
            if ($stmt->execute()) {
                $message  = "Subject updated successfully.";
                $msg_type = "success";
                $edit_subject = null;
            } else {
                $message  = "Error updating subject: " . $stmt->error;
                $msg_type = "error";
            }
            $stmt->close();
        }
    }
}

// ── CREATE SUBJECT ──
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "create_subject") {
    $subject_name = trim($_POST["subject_name"]);
    $subject_code = trim($_POST["subject_code"]);
    $teacher_id   = (int)$_POST["teacher_id"];

    if ($subject_name === "" || $teacher_id <= 0) {
        $message  = "Please fill in the subject name and assign a teacher.";
        $msg_type = "error";
    } else {
        // Check for duplicate code
        if ($subject_code !== "") {
            $stmt = $conn->prepare("SELECT id FROM subjects WHERE subject_code = ?");
            $stmt->bind_param("s", $subject_code);
            $stmt->execute();
            $dup = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($dup) {
                $message  = "Subject code '$subject_code' is already used by another subject. Please use a unique code.";
                $msg_type = "error";
            }
        }

        if ($message === "") {
            $stmt = $conn->prepare("INSERT INTO subjects (subject_name, subject_code, teacher_id) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $subject_name, $subject_code, $teacher_id);
            if ($stmt->execute()) {
                $message  = "Subject created successfully!";
                $msg_type = "success";
            } else {
                $message  = "Error: " . $stmt->error;
                $msg_type = "error";
            }
            $stmt->close();
        }
    }
}

// Load all subjects
$sql = "SELECT s.id, s.subject_name, s.subject_code, s.created_at,
               u.username AS teacher_username, u.name AS teacher_name, u.surname AS teacher_surname
        FROM subjects s
        JOIN users u ON u.id = s.teacher_id
        ORDER BY s.id DESC";
$subjects_res = $conn->query($sql);
$subjects = [];
while ($row = $subjects_res->fetch_assoc()) $subjects[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Subjects | Admin</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; min-height: 100vh; display: flex; flex-direction: column; }
        .navbar { background: #1a1a2e; color: white; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; }
        .navbar .logo { font-size: 1.1rem; font-weight: 700; }
        .navbar a { color: #a0c4ff; text-decoration: none; margin-left: 18px; font-weight: 600; font-size: 0.92rem; }
        .navbar a:hover { color: white; }
        .container { max-width: 1000px; margin: 40px auto; padding: 0 20px; flex: 1; width: 100%; }
        .page-title { margin-bottom: 28px; }
        .page-title h1 { font-size: 1.8rem; color: #1a1a2e; }
        .page-title p  { color: #666; margin-top: 6px; font-size: 0.95rem; }
        .msg { padding: 12px 18px; border-radius: 8px; margin-bottom: 22px; font-size: 0.93rem; font-weight: 600; }
        .msg-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .msg-error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .stats-bar { display: flex; gap: 16px; margin-bottom: 28px; flex-wrap: wrap; }
        .stat { background: white; border-radius: 10px; padding: 18px 24px; flex: 1; min-width: 130px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.06); border-left: 4px solid #1a1a2e; }
        .stat .num { font-size: 2rem; font-weight: 800; color: #1a1a2e; }
        .stat .lbl { font-size: 0.82rem; color: #666; margin-top: 3px; }
        .card { background: white; border-radius: 12px; padding: 28px; box-shadow: 0 2px 14px rgba(0,0,0,0.07); margin-bottom: 28px; }
        .card h2 { font-size: 1.1rem; color: #1a1a2e; font-weight: 700; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #f0f2f5; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 20px; }
        .form-full { grid-column: 1 / -1; }
        label { display: block; font-weight: 600; color: #444; font-size: 0.88rem; margin-bottom: 6px; }
        input[type="text"], select { width: 100%; padding: 11px 14px; border: 1px solid #ccc; border-radius: 7px; font-size: 0.95rem; margin-bottom: 18px; background: #fafafa; font-family: inherit; transition: border-color 0.15s; }
        input[type="text"]:focus, select:focus { outline: none; border-color: #1a1a2e; background: white; }
        .btn { padding: 11px 24px; border: none; border-radius: 7px; cursor: pointer; font-size: 0.92rem; font-weight: 700; transition: opacity 0.15s; }
        .btn:hover { opacity: 0.88; }
        .btn-primary { background: #1a1a2e; color: white; }
        .btn-warning { background: #fd7e14; color: white; }
        .btn-danger  { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-sm { padding: 6px 14px; font-size: 0.82rem; }
        table { width: 100%; border-collapse: collapse; }
        thead tr { background: #1a1a2e; }
        thead th { color: white; padding: 13px 16px; text-align: left; font-size: 0.88rem; font-weight: 700; }
        thead th:first-child { border-radius: 6px 0 0 0; }
        thead th:last-child  { border-radius: 0 6px 0 0; }
        tbody tr { border-bottom: 1px solid #eee; transition: background 0.1s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #f7f9ff; }
        tbody td { padding: 13px 16px; font-size: 0.92rem; color: #333; vertical-align: middle; }
        .subject-name { font-weight: 700; color: #1a1a2e; }
        .code-badge { display: inline-block; background: #e8eef8; color: #1a1a2e; padding: 3px 12px; border-radius: 20px; font-size: 0.82rem; font-weight: 700; }
        .code-none { color: #ccc; }
        .teacher-badge { display: inline-flex; align-items: center; gap: 6px; background: #fff3cd; color: #856404; padding: 4px 12px; border-radius: 20px; font-size: 0.82rem; font-weight: 700; }
        .date-cell { color: #999; font-size: 0.87rem; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .empty { text-align: center; padding: 48px; color: #bbb; }
        .empty .icon { font-size: 2.8rem; margin-bottom: 12px; }
        .search-row { display: flex; justify-content: flex-end; margin-bottom: 16px; }
        .search-row input { padding: 9px 14px; border: 1px solid #ddd; border-radius: 7px; font-size: 0.9rem; width: 240px; background: #fafafa; }
        .search-row input:focus { outline: none; border-color: #1a1a2e; }
        /* Edit form highlight */
        .card.editing { border: 2px solid #fd7e14; }
        .card.editing h2 { color: #fd7e14; }
        .footer { background: #1a1a2e; color: #a0c4ff; padding: 22px 40px; font-size: 0.88rem; }
        .footer p { margin-top: 4px; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="logo">Admin Panel</div>
    <div>
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="admin_logout.php">Logout</a>
    </div>
</div>

<div class="container">

    <div class="page-title">
        <h1>Manage Subjects</h1>
        <p>Create, edit, and delete subjects. Assign or reassign teachers at any time.</p>
    </div>

    <?php if ($message): ?>
        <div class="msg msg-<?php echo $msg_type; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-bar">
        <div class="stat">
            <div class="num"><?php echo count($subjects); ?></div>
            <div class="lbl">Total Subjects</div>
        </div>
        <div class="stat">
            <div class="num"><?php echo count($teachers); ?></div>
            <div class="lbl">Teachers</div>
        </div>
        <div class="stat">
            <div class="num"><?php echo count(array_unique(array_column($subjects, 'teacher_username'))); ?></div>
            <div class="lbl">Teachers with Subjects</div>
        </div>
    </div>

    <!-- Edit subject form (shown only when editing) -->
    <?php if ($edit_subject): ?>
    <div class="card editing">
        <h2>✏️ Editing Subject — <?php echo htmlspecialchars($edit_subject['subject_name']); ?></h2>
        <form method="post">
            <input type="hidden" name="action" value="update_subject">
            <input type="hidden" name="edit_id" value="<?php echo (int)$edit_subject['id']; ?>">
            <div class="form-grid">
                <div>
                    <label>Subject Name <span style="color:#dc3545;">*</span></label>
                    <input type="text" name="subject_name" value="<?php echo htmlspecialchars($edit_subject['subject_name']); ?>" required>
                </div>
                <div>
                    <label>Subject Code <span style="color:#aaa;font-weight:400;">(must be unique)</span></label>
                    <input type="text" name="subject_code" value="<?php echo htmlspecialchars($edit_subject['subject_code'] ?? ''); ?>">
                </div>
                <div class="form-full">
                    <label>Assigned Teacher <span style="color:#dc3545;">*</span></label>
                    <select name="teacher_id" required>
                        <option value="">-- choose teacher --</option>
                        <?php foreach ($teachers as $t):
                            $full  = trim(($t['name'] ?? '') . ' ' . ($t['surname'] ?? ''));
                            $label = $t['username'] . ($full ? " ($full)" : "");
                        ?>
                            <option value="<?php echo (int)$t['id']; ?>" <?php echo ($t['id'] == $edit_subject['teacher_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div style="display:flex;gap:12px;">
                <button type="submit" class="btn btn-warning">Save Changes</button>
                <a href="manage_subjects.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Create subject form -->
    <div class="card">
        <h2>➕ Create New Subject</h2>
        <form method="post">
            <input type="hidden" name="action" value="create_subject">
            <div class="form-grid">
                <div>
                    <label>Subject Name <span style="color:#dc3545;">*</span></label>
                    <input type="text" name="subject_name" placeholder="e.g. Mathematics" required>
                </div>
                <div>
                    <label>Subject Code <span style="color:#aaa;font-weight:400;">(optional, must be unique)</span></label>
                    <input type="text" name="subject_code" placeholder="e.g. MTH101">
                </div>
                <div class="form-full">
                    <label>Assign Teacher <span style="color:#dc3545;">*</span></label>
                    <select name="teacher_id" required>
                        <option value="">-- choose teacher --</option>
                        <?php foreach ($teachers as $t):
                            $full  = trim(($t['name'] ?? '') . ' ' . ($t['surname'] ?? ''));
                            $label = $t['username'] . ($full ? " ($full)" : "");
                        ?>
                            <option value="<?php echo (int)$t['id']; ?>">
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php if (count($teachers) === 0): ?>
                <p style="color:#dc3545;font-size:0.88rem;margin-bottom:16px;">
                    ⚠️ No teachers found. <a href="admin_dashboard.php" style="color:#dc3545;">Create a teacher account first.</a>
                </p>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">Create Subject</button>
        </form>
    </div>

    <!-- Subjects table -->
    <div class="card">
        <h2>📚 All Subjects</h2>

        <?php if (count($subjects) === 0): ?>
            <div class="empty">
                <div class="icon">📭</div>
                <p>No subjects have been created yet.</p>
            </div>
        <?php else: ?>
        <div class="search-row">
            <input type="text" id="searchInput" placeholder="Search subjects or teachers..." onkeyup="filterTable()">
        </div>
        <table id="subjectsTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Subject Name</th>
                    <th>Code</th>
                    <th>Assigned Teacher</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subjects as $row):
                    $teacher_full  = trim(($row['teacher_name'] ?? '') . ' ' . ($row['teacher_surname'] ?? ''));
                    $teacher_label = $teacher_full ?: $row['teacher_username'];
                ?>
                <tr>
                    <td style="color:#bbb;font-size:0.85rem;"><?php echo (int)$row['id']; ?></td>
                    <td><span class="subject-name"><?php echo htmlspecialchars($row['subject_name']); ?></span></td>
                    <td>
                        <?php if (!empty($row['subject_code'])): ?>
                            <span class="code-badge"><?php echo htmlspecialchars($row['subject_code']); ?></span>
                        <?php else: ?>
                            <span class="code-none">—</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="teacher-badge">🧑‍🏫 <?php echo htmlspecialchars($teacher_label); ?></span></td>
                    <td class="date-cell"><?php echo htmlspecialchars(substr($row['created_at'], 0, 10)); ?></td>
                    <td>
                        <div class="actions">
                            <a href="manage_subjects.php?edit_id=<?php echo (int)$row['id']; ?>" class="btn btn-warning btn-sm">✏️ Edit</a>
                            <a href="manage_subjects.php?delete_id=<?php echo (int)$row['id']; ?>"
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Delete subject \'<?php echo htmlspecialchars(addslashes($row['subject_name'])); ?>\'?\n\nThis will also remove all enrollments, attendance records, and grades linked to this subject. This cannot be undone.')">
                               🗑️ Delete
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div>

<div class="footer">
    <p>• Phone: +37000000002</p>
    <p>• Email: admin@portal.com</p>
</div>

<script>
function filterTable() {
    var input = document.getElementById("searchInput").value.toLowerCase();
    var rows  = document.querySelectorAll("#subjectsTable tbody tr");
    rows.forEach(function(row) {
        row.style.display = row.innerText.toLowerCase().includes(input) ? "" : "none";
    });
}
</script>

</body>
</html>