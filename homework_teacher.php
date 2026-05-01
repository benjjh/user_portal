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
$message = "";

// Create homework table if not exists (run once)
$conn->query(
    "CREATE TABLE IF NOT EXISTS `homework` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `subject_id` int(11) NOT NULL,
        `teacher_id` int(11) NOT NULL,
        `title` varchar(200) NOT NULL,
        `description` text DEFAULT NULL,
        `due_date` date DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `subject_id` (`subject_id`),
        KEY `teacher_id` (`teacher_id`),
        CONSTRAINT `hw_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
        CONSTRAINT `hw_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);

// Load teacher's subjects
$stmt = $conn->prepare("SELECT id, subject_name, subject_code FROM subjects WHERE teacher_id = ? ORDER BY subject_name ASC");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$subjects_res = $stmt->get_result();
$subjects = [];
while ($row = $subjects_res->fetch_assoc()) $subjects[] = $row;
$stmt->close();

// Save homework
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $subject_id  = (int)($_POST["subject_id"] ?? 0);
    $title       = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $due_date    = $_POST["due_date"] ?? "";

    if ($subject_id <= 0 || $title === "") {
        $message = "Please select a subject and enter a title.";
    } else {
        // Verify subject belongs to teacher
        $stmt = $conn->prepare("SELECT id FROM subjects WHERE id = ? AND teacher_id = ?");
        $stmt->bind_param("ii", $subject_id, $teacher_id);
        $stmt->execute();
        $ok = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$ok) {
            $message = "Invalid subject selection.";
        } else {
            $due_val = ($due_date !== "") ? $due_date : null;
            $stmt = $conn->prepare("INSERT INTO homework (subject_id, teacher_id, title, description, due_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisss", $subject_id, $teacher_id, $title, $description, $due_val);
            if ($stmt->execute()) {
                $message = "Homework posted successfully.";
            } else {
                $message = "Error: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Load existing homework
$stmt = $conn->prepare(
    "SELECT h.id, h.title, h.description, h.due_date, h.created_at,
            s.subject_name, s.subject_code
     FROM homework h
     JOIN subjects s ON s.id = h.subject_id
     WHERE h.teacher_id = ?
     ORDER BY h.created_at DESC"
);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$hw_result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Homework & Tasks</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f4f0; min-height: 100vh; display: flex; flex-direction: column; }
        .navbar { background: linear-gradient(135deg, #1a3a2a 0%, #2d5a3d 100%); color: white; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; }
        .navbar .logo { font-size: 1.1rem; font-weight: 700; }
        .navbar a { color: #a8d5b5; text-decoration: none; margin-left: 18px; font-weight: 600; font-size: 0.92rem; }
        .navbar a:hover { color: white; }
        .container { max-width: 950px; margin: 36px auto; padding: 0 20px; flex: 1; }
        h1 { font-size: 1.7rem; color: #1a3a2a; margin-bottom: 6px; }
        h2 { font-size: 1.1rem; color: #1a3a2a; margin-bottom: 16px; }
        .msg { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 12px 18px; border-radius: 6px; margin-bottom: 20px; }
        .msg-err { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .card { background: white; border-radius: 10px; padding: 28px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); margin-bottom: 28px; }
        label { display: block; font-weight: 600; margin-bottom: 6px; color: #333; font-size: 0.9rem; }
        select, input[type="text"], input[type="date"], textarea {
            width: 100%; padding: 10px 14px; border: 1px solid #ccc; border-radius: 6px;
            font-size: 0.95rem; margin-bottom: 18px; background: #fafafa;
        }
        select:focus, input:focus, textarea:focus { outline: none; border-color: #2d5a3d; background: white; }
        textarea { resize: vertical; }
        .btn { padding: 11px 24px; background: #1a3a2a; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 0.95rem; font-weight: 600; }
        .btn:hover { background: #2d5a3d; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 11px 14px; text-align: left; border-bottom: 1px solid #eee; font-size: 0.92rem; }
        th { background: #f4f6fb; color: #333; font-weight: 700; }
        tr:hover td { background: #f6fbf7; }
        .due-date { color: #dc3545; font-weight: 600; }
        .hw-desc { color: #555; font-size: 0.88rem; max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
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
    <h1>Homework & Tasks</h1>
    <p style="color:#666;margin-bottom:24px;">Post assignments and tasks for your students.</p>

    <?php if ($message): ?>
        <div class="msg <?php echo (strpos($message, 'Error') !== false || strpos($message, 'Invalid') !== false || strpos($message, 'Please') !== false) ? 'msg-err' : ''; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>Post New Homework / Task</h2>
        <form method="post">
            <label>Subject</label>
            <select name="subject_id" required>
                <option value="">-- choose subject --</option>
                <?php foreach ($subjects as $s): ?>
                    <option value="<?php echo (int)$s['id']; ?>">
                        <?php echo htmlspecialchars($s['subject_name'] . (!empty($s['subject_code']) ? " ({$s['subject_code']})" : "")); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Title</label>
            <input type="text" name="title" placeholder="e.g. Chapter 3 Exercise" required>

            <label>Description / Instructions</label>
            <textarea name="description" rows="4" placeholder="Describe the task or assignment..."></textarea>

            <label>Due Date (optional)</label>
            <input type="date" name="due_date">

            <button type="submit" class="btn">Post Homework</button>
        </form>
    </div>

    <div class="card">
        <h2>Posted Homework</h2>
        <table>
            <tr>
                <th>Subject</th>
                <th>Title</th>
                <th>Description</th>
                <th>Due Date</th>
                <th>Posted</th>
            </tr>
            <?php
            $count = 0;
            while ($hw = $hw_result->fetch_assoc()):
                $count++;
            ?>
            <tr>
                <td><?php echo htmlspecialchars($hw['subject_name'] . (!empty($hw['subject_code']) ? " ({$hw['subject_code']})" : "")); ?></td>
                <td><strong><?php echo htmlspecialchars($hw['title']); ?></strong></td>
                <td><span class="hw-desc"><?php echo htmlspecialchars($hw['description'] ?: '—'); ?></span></td>
                <td>
                    <?php if ($hw['due_date']): ?>
                        <span class="due-date"><?php echo htmlspecialchars($hw['due_date']); ?></span>
                    <?php else: ?>
                        <span style="color:#aaa;">—</span>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars(substr($hw['created_at'], 0, 10)); ?></td>
            </tr>
            <?php endwhile; ?>
            <?php if ($count === 0): ?>
            <tr><td colspan="5" style="text-align:center;color:#888;padding:24px;">No homework posted yet.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</div>
</body>
</html>
<?php $stmt->close(); ?>