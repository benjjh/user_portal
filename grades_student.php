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
$subject_id = (int)($_GET["subject_id"] ?? 0);

// Subjects student is enrolled in
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

// Load grades with graded_by user info
if ($subject_id > 0) {
    $stmt = $conn->prepare(
        "SELECT g.assessment, g.score, g.max_score, g.graded_at,
                s.subject_name, s.subject_code,
                u.username AS grader_username, u.name AS grader_name,
                u.surname AS grader_surname, u.role AS grader_role
         FROM grades g
         JOIN subjects s ON s.id = g.subject_id
         LEFT JOIN users u ON u.id = g.graded_by
         WHERE g.student_id = ? AND g.subject_id = ?
         ORDER BY g.graded_at DESC"
    );
    $stmt->bind_param("ii", $student_id, $subject_id);
} else {
    $stmt = $conn->prepare(
        "SELECT g.assessment, g.score, g.max_score, g.graded_at,
                s.subject_name, s.subject_code,
                u.username AS grader_username, u.name AS grader_name,
                u.surname AS grader_surname, u.role AS grader_role
         FROM grades g
         JOIN subjects s ON s.id = g.subject_id
         LEFT JOIN users u ON u.id = g.graded_by
         WHERE g.student_id = ?
         ORDER BY g.graded_at DESC"
    );
    $stmt->bind_param("i", $student_id);
}
$stmt->execute();
$grades_res = $stmt->get_result();
$grades = [];
while ($row = $grades_res->fetch_assoc()) $grades[] = $row;
$stmt->close();

// Calculate summary stats
$total     = count($grades);
$passed    = 0;
$failed    = 0;
$total_score = 0;
foreach ($grades as $g) {
    if ((float)$g['score'] >= 5) $passed++;
    else $failed++;
    $total_score += (float)$g['score'];
}
$average = $total > 0 ? round($total_score / $total, 1) : 0;

// Performance label function
function getPerformance($score) {
    $s = (float)$score;
    if ($s == 10)       return ['label' => 'Excellent',  'class' => 'perf-excellent'];
    if ($s >= 8)        return ['label' => 'Very Good',  'class' => 'perf-verygood'];
    if ($s >= 6)        return ['label' => 'Good',       'class' => 'perf-good'];
    if ($s >= 5)        return ['label' => 'Average',    'class' => 'perf-average'];
    return               ['label' => 'Failed',     'class' => 'perf-failed'];
}

function getGraderLabel($row) {
    if (empty($row['grader_username'])) return 'Admin';
    $full = trim(($row['grader_name'] ?? '') . ' ' . ($row['grader_surname'] ?? ''));
    $name = $full ?: $row['grader_username'];
    $role = $row['grader_role'] ?? '';
    if ($role === 'teacher') return '🧑‍🏫 ' . $name;
    return '🛡️ Admin';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Grades</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f8; min-height: 100vh; display: flex; flex-direction: column; }

        /* Navbar */
        .navbar { background: linear-gradient(135deg,#1a1a2e,#16213e); padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
        .navbar .logo { color: white; font-size: 1.1rem; font-weight: 700; }
        .navbar .nav-links a { color: #a0c4ff; text-decoration: none; margin-left: 20px; font-weight: 600; font-size: 0.92rem; }
        .navbar .nav-links a:hover { color: white; }

        .container { max-width: 1100px; margin: 36px auto; padding: 0 20px; flex: 1; width: 100%; }

        .page-title { margin-bottom: 26px; }
        .page-title h1 { font-size: 1.8rem; color: #1a1a2e; font-weight: 800; }
        .page-title p  { color: #666; margin-top: 5px; font-size: 0.95rem; }

        /* Stats bar */
        .stats-bar { display: flex; gap: 14px; margin-bottom: 26px; flex-wrap: wrap; }
        .stat { background: white; border-radius: 10px; padding: 18px 22px; flex: 1; min-width: 120px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.06); border-left: 4px solid #1a1a2e; }
        .stat .num { font-size: 1.9rem; font-weight: 800; color: #1a1a2e; }
        .stat .lbl { font-size: 0.8rem; color: #777; margin-top: 3px; text-transform: uppercase; letter-spacing: 0.4px; }
        .stat-passed .num { color: #28a745; }
        .stat-failed .num { color: #dc3545; }
        .stat-avg    .num { color: #0d6efd; }

        /* Filter card */
        .card { background: white; border-radius: 12px; padding: 26px; box-shadow: 0 2px 14px rgba(0,0,0,0.07); margin-bottom: 26px; }
        .card h2 { font-size: 1.05rem; color: #1a1a2e; font-weight: 700; margin-bottom: 18px; padding-bottom: 10px; border-bottom: 2px solid #f0f2f8; }

        label { display: block; font-weight: 600; color: #444; font-size: 0.87rem; margin-bottom: 6px; }
        select { width: 100%; max-width: 400px; padding: 10px 14px; border: 1.5px solid #ddd; border-radius: 8px; font-size: 0.93rem; background: #fafafa; font-family: inherit; }
        select:focus { outline: none; border-color: #1a1a2e; background: white; }

        /* Table */
        table { width: 100%; border-collapse: collapse; }
        thead tr { background: #1a1a2e; }
        thead th { color: white; padding: 13px 15px; text-align: left; font-size: 0.85rem; font-weight: 700; letter-spacing: 0.3px; }
        thead th:first-child { border-radius: 6px 0 0 0; }
        thead th:last-child  { border-radius: 0 6px 0 0; }
        tbody tr { border-bottom: 1px solid #eef0f8; transition: background 0.1s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #f5f7ff; }
        tbody td { padding: 13px 15px; font-size: 0.9rem; color: #333; vertical-align: middle; }

        /* Subject badge */
        .sub-badge { display: inline-block; background: #e8eef8; color: #1a1a2e; padding: 3px 11px; border-radius: 20px; font-size: 0.82rem; font-weight: 700; }

        /* Assessment type badge */
        .assess-exam     { display:inline-block; background:#e8f5e9; color:#1a3a2a; padding:3px 11px; border-radius:20px; font-size:0.82rem; font-weight:700; }
        .assess-midterm  { display:inline-block; background:#e8f0ff; color:#1a1a6e; padding:3px 11px; border-radius:20px; font-size:0.82rem; font-weight:700; }
        .assess-exercise { display:inline-block; background:#f3e8ff; color:#5a1a6e; padding:3px 11px; border-radius:20px; font-size:0.82rem; font-weight:700; }
        .assess-other    { display:inline-block; background:#f0f2f5; color:#444;    padding:3px 11px; border-radius:20px; font-size:0.82rem; font-weight:700; }

        /* Score display */
        .score-display { display: flex; align-items: center; gap: 8px; }
        .score-num { font-size: 1.1rem; font-weight: 800; }
        .score-max { font-size: 0.8rem; color: #aaa; }
        .score-bar-wrap { width: 60px; height: 6px; background: #eee; border-radius: 3px; overflow: hidden; }
        .score-bar { height: 100%; border-radius: 3px; }
        .bar-excellent { background: #28a745; }
        .bar-verygood  { background: #20c997; }
        .bar-good      { background: #0d6efd; }
        .bar-average   { background: #fd7e14; }
        .bar-failed    { background: #dc3545; }

        /* Performance badges */
        .perf { display: inline-block; padding: 4px 13px; border-radius: 20px; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.4px; }
        .perf-excellent { background: #d4edda; color: #155724; }
        .perf-verygood  { background: #d1f2eb; color: #0e6251; }
        .perf-good      { background: #cce5ff; color: #004085; }
        .perf-average   { background: #fff3cd; color: #856404; }
        .perf-failed    { background: #f8d7da; color: #721c24; }

        /* Pass / Fail badge */
        .pass-badge { display: inline-block; padding: 4px 13px; border-radius: 20px; font-size: 0.8rem; font-weight: 800; }
        .badge-pass { background: #d4edda; color: #155724; }
        .badge-fail { background: #f8d7da; color: #721c24; }

        /* Grader cell */
        .grader-cell { font-size: 0.88rem; color: #555; font-weight: 600; }

        /* Date cell */
        .date-cell { color: #aaa; font-size: 0.82rem; }

        /* Empty state */
        .empty { text-align: center; padding: 48px; color: #bbb; }
        .empty .icon { font-size: 3rem; margin-bottom: 12px; }

        /* Legend */
        .legend { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; }
        .leg-item { display: flex; align-items: center; gap: 5px; font-size: 0.8rem; color: #555; }
        .leg-dot  { width: 10px; height: 10px; border-radius: 50%; }

        .footer { background: #1a1a2e; color: #a0c4ff; padding: 22px 40px; font-size: 0.88rem; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
        .footer p { margin-top: 3px; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="logo">Student Portal</div>
    <div class="nav-links">
        <a href="student_dashboard.php">Dashboard</a>
        <a href="classmates_student.php">My Classmates</a>
        <a href="user_profile.php">My Profile</a>
        <a href="user_logout.php">Logout</a>
    </div>
</div>

<div class="container">

    <div class="page-title">
        <h1>My Grades</h1>
        <p>View all your assessment results, performance ratings, and who recorded each grade.</p>
    </div>

    <!-- Summary stats -->
    <div class="stats-bar">
        <div class="stat">
            <div class="num"><?php echo $total; ?></div>
            <div class="lbl">Total Grades</div>
        </div>
        <div class="stat stat-passed">
            <div class="num"><?php echo $passed; ?></div>
            <div class="lbl">Passed</div>
        </div>
        <div class="stat stat-failed">
            <div class="num"><?php echo $failed; ?></div>
            <div class="lbl">Failed</div>
        </div>
        <div class="stat stat-avg">
            <div class="num"><?php echo $average; ?></div>
            <div class="lbl">Average Score</div>
        </div>
    </div>

    <!-- Filter -->
    <div class="card">
        <h2>🔍 Filter by Subject</h2>
        <form method="get">
            <label>Select Subject</label>
            <select name="subject_id" onchange="this.form.submit()">
                <option value="0">All Subjects</option>
                <?php foreach ($subjects as $s): ?>
                    <option value="<?php echo (int)$s['id']; ?>" <?php echo ((int)$s['id'] === $subject_id) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($s['subject_name'] . (!empty($s['subject_code']) ? " ({$s['subject_code']})" : "")); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <!-- Grades table -->
    <div class="card">
        <h2>📝 Grade Records</h2>

        <!-- Performance legend -->
        <div class="legend">
            <div class="leg-item"><div class="leg-dot" style="background:#28a745;"></div> Excellent (10)</div>
            <div class="leg-item"><div class="leg-dot" style="background:#20c997;"></div> Very Good (8–9)</div>
            <div class="leg-item"><div class="leg-dot" style="background:#0d6efd;"></div> Good (6–7)</div>
            <div class="leg-item"><div class="leg-dot" style="background:#fd7e14;"></div> Average (5)</div>
            <div class="leg-item"><div class="leg-dot" style="background:#dc3545;"></div> Failed (below 5)</div>
        </div>

        <?php if (count($grades) === 0): ?>
            <div class="empty">
                <div class="icon">📭</div>
                <p>No grade records found<?php echo $subject_id > 0 ? ' for this subject' : ''; ?>.</p>
                <p style="margin-top:8px;font-size:0.88rem;">Check back after your teacher has entered grades.</p>
            </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Assessment</th>
                    <th>Score</th>
                    <th>Performance</th>
                    <th>Result</th>
                    <th>Graded By</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grades as $row):
                    $score     = (float)$row['score'];
                    $max       = (float)$row['max_score'];
                    $perf      = getPerformance($score);
                    $passed_r  = $score >= 5;
                    $pct       = $max > 0 ? ($score / $max) * 100 : 0;

                    // Assessment badge class
                    $atype = strtolower($row['assessment']);
                    if ($atype === 'exam')      $abadge = 'assess-exam';
                    elseif (strpos($atype, 'mid') !== false) $abadge = 'assess-midterm';
                    elseif ($atype === 'exercise') $abadge = 'assess-exercise';
                    else $abadge = 'assess-other';

                    // Score bar colour
                    $bar_class = str_replace('perf-', 'bar-', $perf['class']);
                    $sub_label = $row['subject_name'] . (!empty($row['subject_code']) ? " ({$row['subject_code']})" : "");
                ?>
                <tr>
                    <td><span class="sub-badge"><?php echo htmlspecialchars($sub_label); ?></span></td>
                    <td><span class="<?php echo $abadge; ?>"><?php echo htmlspecialchars($row['assessment']); ?></span></td>
                    <td>
                        <div class="score-display">
                            <span class="score-num" style="color:<?php echo $passed_r ? '#1a1a2e' : '#dc3545'; ?>">
                                <?php echo htmlspecialchars($row['score']); ?>
                            </span>
                            <span class="score-max">/ <?php echo htmlspecialchars($row['max_score']); ?></span>
                            <div class="score-bar-wrap">
                                <div class="score-bar <?php echo $bar_class; ?>" style="width:<?php echo min(100, $pct); ?>%"></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="perf <?php echo $perf['class']; ?>">
                            <?php echo $perf['label']; ?>
                        </span>
                    </td>
                    <td>
                        <span class="pass-badge <?php echo $passed_r ? 'badge-pass' : 'badge-fail'; ?>">
                            <?php echo $passed_r ? 'Passed' : 'Failed'; ?>
                        </span>
                    </td>
                    <td class="grader-cell">
                        <?php echo htmlspecialchars(getGraderLabel($row)); ?>
                    </td>
                    <td class="date-cell">
                        <?php echo htmlspecialchars(substr($row['graded_at'], 0, 10)); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div>

<div class="footer">
    <div>
        <p>• Phone: +37000000001</p>
        <p>• Email: info@portal.com</p>
    </div>
    <div style="align-self:flex-end;color:#3a3a6e;font-size:0.82rem;">&copy; 2026 Legit Portal</div>
</div>

</body>
</html>