<?php
require_once 'config.php';

if (!isset($_SESSION["user_loggedin"]) || $_SESSION["user_loggedin"] !== true) {
    header("location: user_login.php");
    exit;
}
if (($_SESSION["role"] ?? '') !== 'teacher') {
    header("location: student_dashboard.php");
    exit;
}

$teacher_id = (int)$_SESSION["user_id"];
$message    = "";
$msg_type   = "success";

// Add subject
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $subject_name = trim($_POST["subject_name"]);
    $subject_code = trim($_POST["subject_code"]);

    if ($subject_name === "") {
        $message  = "Subject name is required.";
        $msg_type = "error";
    } else {
        $stmt = $conn->prepare("INSERT INTO subjects (subject_name, subject_code, teacher_id) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $subject_name, $subject_code, $teacher_id);
        if ($stmt->execute()) {
            $message  = "Subject added successfully!";
            $msg_type = "success";
        } else {
            $message  = "Error: " . $stmt->error;
            $msg_type = "error";
        }
        $stmt->close();
    }
}

// List subjects for this teacher
$stmt = $conn->prepare("SELECT id, subject_name, subject_code, created_at FROM subjects WHERE teacher_id = ? ORDER BY id DESC");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$subjects = [];
while ($row = $result->fetch_assoc()) $subjects[] = $row;
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Subjects</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f4f0; min-height: 100vh; display: flex; flex-direction: column; }

        /* Navbar */
        .navbar {
            background: linear-gradient(135deg, #1a3a2a 0%, #2d5a3d 100%);
            color: white; padding: 15px 40px;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .navbar .logo { font-size: 1.1rem; font-weight: 700; }
        .navbar a { color: #a8d5b5; text-decoration: none; margin-left: 18px; font-weight: 600; font-size: 0.92rem; }
        .navbar a:hover { color: white; }

        /* Page container */
        .container { max-width: 900px; margin: 40px auto; padding: 0 20px; flex: 1; width: 100%; }

        /* Page title */
        .page-title { margin-bottom: 28px; }
        .page-title h1 { font-size: 1.8rem; color: #1a3a2a; }
        .page-title p  { color: #666; margin-top: 6px; font-size: 0.95rem; }

        /* Messages */
        .msg { padding: 12px 18px; border-radius: 8px; margin-bottom: 22px; font-size: 0.93rem; font-weight: 600; }
        .msg-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .msg-error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* Cards */
        .card { background: white; border-radius: 12px; padding: 28px; box-shadow: 0 2px 14px rgba(0,0,0,0.07); margin-bottom: 28px; }
        .card h2 { font-size: 1.1rem; color: #1a3a2a; font-weight: 700; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #f0f4f0; }

        /* Form */
        label { display: block; font-weight: 600; color: #444; font-size: 0.88rem; margin-bottom: 6px; }
        input[type="text"] {
            width: 100%; padding: 11px 14px; border: 1px solid #ccc; border-radius: 7px;
            font-size: 0.95rem; margin-bottom: 18px; background: #fafafa;
            transition: border-color 0.15s, background 0.15s;
        }
        input[type="text"]:focus { outline: none; border-color: #2d5a3d; background: white; }
        .btn {
            padding: 11px 28px; background: #1a3a2a; color: white;
            border: none; border-radius: 7px; cursor: pointer;
            font-size: 0.95rem; font-weight: 700; letter-spacing: 0.3px;
            transition: background 0.15s;
        }
        .btn:hover { background: #2d5a3d; }

        /* Stats bar */
        .stats-bar { display: flex; gap: 14px; margin-bottom: 28px; }
        .stat { background: white; border-radius: 10px; padding: 18px 24px; flex: 1; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.06); border-left: 4px solid #2d5a3d; }
        .stat .num { font-size: 2rem; font-weight: 800; color: #1a3a2a; }
        .stat .lbl { font-size: 0.82rem; color: #666; margin-top: 3px; }

        /* Table */
        table { width: 100%; border-collapse: collapse; }
        thead tr { background: #1a3a2a; }
        thead th { color: white; padding: 13px 16px; text-align: left; font-size: 0.88rem; font-weight: 700; letter-spacing: 0.3px; }
        thead th:first-child { border-radius: 6px 0 0 0; }
        thead th:last-child  { border-radius: 0 6px 0 0; }
        tbody tr { border-bottom: 1px solid #eef2ee; transition: background 0.1s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #f6fbf7; }
        tbody td { padding: 14px 16px; font-size: 0.93rem; color: #333; vertical-align: middle; }

        /* Subject name highlight */
        .subject-name { font-weight: 700; color: #1a3a2a; }

        /* Code badge */
        .code-badge {
            display: inline-block; background: #e8f5e9; color: #2d5a3d;
            padding: 3px 12px; border-radius: 20px; font-size: 0.82rem; font-weight: 700;
        }
        .code-none { color: #bbb; font-size: 0.88rem; }

        /* Date */
        .date-cell { color: #888; font-size: 0.88rem; }

        /* Empty state */
        .empty { text-align: center; padding: 40px; color: #aaa; }
        .empty .icon { font-size: 2.5rem; margin-bottom: 10px; }

        /* Footer */
        .footer { background: #1a3a2a; color: #a8d5b5; padding: 22px 40px; font-size: 0.88rem; }
        .footer p { margin-top: 4px; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="logo">Teacher Portal</div>
    <div>
        <a href="teacher_dashboard.php">Dashboard</a>
        <a href="user_profile.php">My Profile</a>
        <a href="user_logout.php">Logout</a>
    </div>
</div>

<div class="container">

    <div class="page-title">
        <h1>Manage Subjects</h1>
        <p>Add and view the subjects you teach.</p>
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
    </div>

    <!-- Add subject form -->
    <div class="card">
        <h2>➕ Add New Subject</h2>
        <form method="post">
            <label>Subject Name <span style="color:#dc3545;">*</span></label>
            <input type="text" name="subject_name" placeholder="e.g. Mathematics" required>

            <label>Subject Code <span style="color:#aaa;font-weight:400;">(optional)</span></label>
            <input type="text" name="subject_code" placeholder="e.g. MTH101">

            <button type="submit" class="btn">Add Subject</button>
        </form>
    </div>

    <!-- Subjects table -->
    <div class="card">
        <h2>📚 My Subjects</h2>
        <?php if (count($subjects) === 0): ?>
            <div class="empty">
                <div class="icon">📭</div>
                <p>You have not added any subjects yet.</p>
            </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Subject Name</th>
                    <th>Code</th>
                    <th>Date Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subjects as $i => $row): ?>
                <tr>
                    <td style="color:#aaa;font-size:0.85rem;"><?php echo (int)$row['id']; ?></td>
                    <td><span class="subject-name"><?php echo htmlspecialchars($row['subject_name']); ?></span></td>
                    <td>
                        <?php if (!empty($row['subject_code'])): ?>
                            <span class="code-badge"><?php echo htmlspecialchars($row['subject_code']); ?></span>
                        <?php else: ?>
                            <span class="code-none">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="date-cell"><?php echo htmlspecialchars(substr($row['created_at'], 0, 10)); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div>

<div class="footer">
    <p>• Phone: +37000000001</p>
    <p>• Email: info@portal.com</p>
</div>

</body>
</html>