<?php
require_once 'config.php';

if (!isset($_SESSION["user_loggedin"]) || $_SESSION["user_loggedin"] !== true) {
    header("location: user_login.php");
    exit;
}
if (($_SESSION["role"] ?? '') !== 'student') {
    header("location: teacher_dashboard.php");
    exit;
}

$student_id = (int)$_SESSION["user_id"];

// filter by subject
$subject_id = (int)($_GET["subject_id"] ?? 0);

// Load subjects this student is enrolled in
$stmt = $conn->prepare(
    "SELECT s.id, s.subject_name, s.subject_code
     FROM enrollments e
     JOIN subjects s ON s.id = e.subject_id
     WHERE e.student_id = ?
     ORDER BY s.subject_name ASC"
);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$subjects_res = $stmt->get_result();
$subjects = [];
while ($row = $subjects_res->fetch_assoc()) $subjects[] = $row;
$stmt->close();

// Load homework for enrolled subjects (filtered or all)
if ($subject_id > 0) {
    $stmt = $conn->prepare(
        "SELECT h.id, h.title, h.description, h.due_date, h.created_at,
                s.subject_name, s.subject_code,
                u.username AS teacher_username, u.name AS teacher_name, u.surname AS teacher_surname
         FROM homework h
         JOIN subjects s ON s.id = h.subject_id
         JOIN users u ON u.id = h.teacher_id
         JOIN enrollments e ON e.subject_id = h.subject_id AND e.student_id = ?
         WHERE h.subject_id = ?
         ORDER BY h.created_at DESC"
    );
    $stmt->bind_param("ii", $student_id, $subject_id);
} else {
    $stmt = $conn->prepare(
        "SELECT h.id, h.title, h.description, h.due_date, h.created_at,
                s.subject_name, s.subject_code,
                u.username AS teacher_username, u.name AS teacher_name, u.surname AS teacher_surname
         FROM homework h
         JOIN subjects s ON s.id = h.subject_id
         JOIN users u ON u.id = h.teacher_id
         JOIN enrollments e ON e.subject_id = h.subject_id AND e.student_id = ?
         ORDER BY h.created_at DESC"
    );
    $stmt->bind_param("i", $student_id);
}
$stmt->execute();
$hw_result = $stmt->get_result();

// Count upcoming (due date in the future or today)
$today = date("Y-m-d");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Homework & Assignments</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f8; min-height: 100vh; display: flex; flex-direction: column; }
        .navbar { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: white; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
        .navbar .logo { font-size: 1.1rem; font-weight: 700; }
        .navbar a { color: #a0c4ff; text-decoration: none; margin-left: 18px; font-weight: 600; font-size: 0.92rem; }
        .navbar a:hover { color: white; }
        .container { max-width: 950px; margin: 36px auto; padding: 0 20px; flex: 1; width: 100%; }
        h1 { font-size: 1.7rem; color: #1a1a2e; margin-bottom: 6px; }

        /* Filter bar */
        .filter-bar { background: white; border-radius: 10px; padding: 18px 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); margin-bottom: 24px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
        .filter-bar label { font-weight: 600; color: #444; font-size: 0.9rem; white-space: nowrap; }
        .filter-bar select { padding: 9px 14px; border: 1px solid #ccc; border-radius: 6px; font-size: 0.92rem; background: #fafafa; min-width: 220px; }
        .filter-bar select:focus { outline: none; border-color: #1a1a2e; }
        .filter-bar button { padding: 9px 20px; background: #1a1a2e; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.92rem; }
        .filter-bar button:hover { background: #2d2d5e; }

        /* Homework cards */
        .hw-list { display: flex; flex-direction: column; gap: 16px; }
        .hw-card { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); border-left: 5px solid #1a1a2e; }
        .hw-card.overdue { border-left-color: #dc3545; }
        .hw-card.upcoming { border-left-color: #28a745; }
        .hw-card.no-due { border-left-color: #6c757d; }
        .hw-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 8px; }
        .hw-title { font-size: 1.1rem; font-weight: 700; color: #1a1a2e; }
        .hw-subject { font-size: 0.83rem; background: #e8eef8; color: #1a1a2e; padding: 3px 12px; border-radius: 20px; font-weight: 600; }
        .hw-teacher { color: #666; font-size: 0.88rem; margin-top: 6px; }
        .hw-desc { color: #444; font-size: 0.93rem; margin-top: 12px; line-height: 1.5; }
        .hw-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 16px; flex-wrap: wrap; gap: 8px; }
        .due-badge { padding: 4px 14px; border-radius: 20px; font-size: 0.82rem; font-weight: 700; }
        .due-overdue { background: #f8d7da; color: #721c24; }
        .due-upcoming { background: #d4edda; color: #155724; }
        .due-today { background: #fff3cd; color: #856404; }
        .due-none { background: #e2e3e5; color: #383d41; }
        .posted-date { font-size: 0.82rem; color: #aaa; }

        .empty-state { background: white; border-radius: 12px; padding: 48px; text-align: center; box-shadow: 0 2px 12px rgba(0,0,0,0.07); color: #888; }
        .empty-state .icon { font-size: 3rem; margin-bottom: 12px; }
        .empty-state p { font-size: 1rem; }

        .footer { background: #1a1a2e; color: #a0c4ff; padding: 24px 40px; font-size: 0.88rem; margin-top: auto; }
        .footer p { margin-top: 4px; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="logo">Student Portal</div>
    <div>
        <a href="student_dashboard.php">Dashboard</a>
        <a href="user_profile.php">My Profile</a>
        <a href="user_logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <h1>Homework & Assignments</h1>
    <p style="color:#666;margin-bottom:20px;">Tasks and assignments posted by your teachers.</p>

    <!-- Filter -->
    <form method="get">
        <div class="filter-bar">
            <label>Filter by Subject:</label>
            <select name="subject_id">
                <option value="0">All Subjects</option>
                <?php foreach ($subjects as $s): ?>
                    <option value="<?php echo (int)$s['id']; ?>" <?php echo ((int)$s['id'] === $subject_id) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($s['subject_name'] . (!empty($s['subject_code']) ? " ({$s['subject_code']})" : "")); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Filter</button>
        </div>
    </form>

    <!-- Homework list -->
    <div class="hw-list">
        <?php
        $count = 0;
        while ($hw = $hw_result->fetch_assoc()):
            $count++;
            $due = $hw['due_date'];
            $card_class = 'no-due';
            $due_label  = '';
            $badge_class = 'due-none';

            if ($due) {
                if ($due < $today) {
                    $card_class  = 'overdue';
                    $badge_class = 'due-overdue';
                    $due_label   = 'Due: ' . $due . ' (Overdue)';
                } elseif ($due === $today) {
                    $card_class  = 'upcoming';
                    $badge_class = 'due-today';
                    $due_label   = 'Due: Today!';
                } else {
                    $card_class  = 'upcoming';
                    $badge_class = 'due-upcoming';
                    $due_label   = 'Due: ' . $due;
                }
            } else {
                $due_label = 'No due date';
            }

            $teacher_full = trim(($hw['teacher_name'] ?? '') . ' ' . ($hw['teacher_surname'] ?? ''));
            $teacher_label = $teacher_full ?: $hw['teacher_username'];
            $subject_label = $hw['subject_name'] . (!empty($hw['subject_code']) ? " ({$hw['subject_code']})" : "");
        ?>
        <div class="hw-card <?php echo $card_class; ?>">
            <div class="hw-header">
                <span class="hw-title"><?php echo htmlspecialchars($hw['title']); ?></span>
                <span class="hw-subject"><?php echo htmlspecialchars($subject_label); ?></span>
            </div>
            <div class="hw-teacher">Posted by: <?php echo htmlspecialchars($teacher_label); ?></div>
            <?php if (!empty($hw['description'])): ?>
                <div class="hw-desc"><?php echo nl2br(htmlspecialchars($hw['description'])); ?></div>
            <?php endif; ?>
            <div class="hw-footer">
                <span class="due-badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($due_label); ?></span>
                <span class="posted-date">Posted: <?php echo htmlspecialchars(substr($hw['created_at'], 0, 10)); ?></span>
            </div>
        </div>
        <?php endwhile; ?>

        <?php if ($count === 0): ?>
        <div class="empty-state">
            <div class="icon">📭</div>
            <p>No homework or assignments found<?php echo $subject_id > 0 ? ' for this subject' : ''; ?>.</p>
            <p style="margin-top:8px;font-size:0.88rem;">Check back later — your teachers will post assignments here.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="footer">
    <p>• Phone: +37000000001</p>
    <p>• Email: info@portal.com</p>
</div>
</body>
</html>
<?php $stmt->close(); ?>