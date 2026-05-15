<?php
require_once 'config.php';

if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    header("location: admin_login.php");
    exit;
}

$subject_id    = (int)($_GET["subject_id"] ?? 0);
$score_filter  = $_GET["score_filter"] ?? "all";

// Load subjects
$subjects = [];
$res = $conn->query(
    "SELECT s.id, s.subject_name, s.subject_code, u.username AS teacher_username
     FROM subjects s JOIN users u ON u.id = s.teacher_id
     ORDER BY s.subject_name ASC"
);
while ($row = $res->fetch_assoc()) $subjects[] = $row;

// Build grade query with filters
$where  = ["1=1"];
$params = [];
$types  = "";

if ($subject_id > 0) {
    $where[] = "g.subject_id = ?";
    $params[] = $subject_id;
    $types   .= "i";
}

// Score range filter
switch ($score_filter) {
    case 'failed':    $where[] = "g.score < 5";   break;
    case 'average':   $where[] = "g.score >= 5 AND g.score < 8"; break;
    case 'good':      $where[] = "g.score >= 8 AND g.score < 10"; break;
    case 'excellent': $where[] = "g.score = 10";  break;
}

$where_sql = implode(" AND ", $where);

$sql = "SELECT g.id, g.assessment, g.score, g.max_score, g.graded_at,
               st.id AS student_id, st.username AS student_username,
               st.name AS student_name, st.surname AS student_surname,
               s.subject_name, s.subject_code,
               u.username AS grader_username, u.name AS grader_name,
               u.surname AS grader_surname, u.role AS grader_role
        FROM grades g
        JOIN users st ON st.id = g.student_id
        JOIN subjects s ON s.id = g.subject_id
        LEFT JOIN users u ON u.id = g.graded_by
        WHERE $where_sql
        ORDER BY g.graded_at DESC, st.username ASC";

if ($params) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $grades_res = $stmt->get_result();
} else {
    $grades_res = $conn->query($sql);
}
$grades = [];
while ($row = $grades_res->fetch_assoc()) $grades[] = $row;

// ── ANALYTICS ──
// Per-student average scores across ALL grades (no subject filter for analytics)
$analytics_sql = "SELECT st.id, st.username, st.name, st.surname,
                         COUNT(g.id) AS total_grades,
                         ROUND(AVG(g.score), 2) AS avg_score,
                         SUM(CASE WHEN g.score < 5 THEN 1 ELSE 0 END) AS failed_count,
                         SUM(CASE WHEN g.score >= 5 THEN 1 ELSE 0 END) AS passed_count
                  FROM users st
                  JOIN grades g ON g.student_id = st.id
                  WHERE st.role = 'student'
                  GROUP BY st.id
                  ORDER BY avg_score DESC";
$analytics_res = $conn->query($analytics_sql);
$student_analytics = [];
while ($row = $analytics_res->fetch_assoc()) $student_analytics[] = $row;

// Failed students per subject
$failed_sql = "SELECT st.username, st.name, st.surname,
                      s.subject_name, s.subject_code,
                      g.assessment, g.score
               FROM grades g
               JOIN users st ON st.id = g.student_id
               JOIN subjects s ON s.id = g.subject_id
               WHERE g.score < 5
               ORDER BY s.subject_name ASC, st.username ASC";
$failed_res = $conn->query($failed_sql);
$failed_students = [];
while ($row = $failed_res->fetch_assoc()) $failed_students[] = $row;

// Summary counts for current filter
$total_grades = count($grades);
$passed  = 0; $failed_cnt = 0; $excellent_cnt = 0;
foreach ($grades as $g) {
    if ((float)$g['score'] >= 5)  $passed++;
    if ((float)$g['score'] < 5)   $failed_cnt++;
    if ((float)$g['score'] == 10) $excellent_cnt++;
}

function perfLabel($score) {
    $s = (float)$score;
    if ($s == 10)  return ['label'=>'Excellent', 'class'=>'perf-excellent'];
    if ($s >= 8)   return ['label'=>'Very Good', 'class'=>'perf-verygood'];
    if ($s >= 6)   return ['label'=>'Good',      'class'=>'perf-good'];
    if ($s >= 5)   return ['label'=>'Average',   'class'=>'perf-average'];
    return              ['label'=>'Failed',    'class'=>'perf-failed'];
}
function graderLabel($row) {
    if (empty($row['grader_username'])) return '🛡️ Admin';
    $full = trim(($row['grader_name']??'').' '.($row['grader_surname']??''));
    $name = $full ?: $row['grader_username'];
    return ($row['grader_role']==='teacher') ? '🧑‍🏫 '.$name : '🛡️ Admin';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Grades | Admin</title>
    <style>
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'Segoe UI',Arial,sans-serif; background:#f0f2f5; min-height:100vh; display:flex; flex-direction:column; }
        .navbar { background:#1a1a2e; padding:15px 40px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 2px 8px rgba(0,0,0,0.15); }
        .navbar .logo { color:white; font-size:1.1rem; font-weight:700; }
        .navbar .nav-links a { color:#a0c4ff; text-decoration:none; margin-left:20px; font-weight:600; font-size:0.92rem; }
        .navbar .nav-links a:hover { color:white; }
        .container { max-width:1150px; margin:36px auto; padding:0 20px; flex:1; width:100%; }
        .page-title { margin-bottom:26px; }
        .page-title h1 { font-size:1.8rem; color:#1a1a2e; font-weight:800; }
        .page-title p  { color:#666; margin-top:5px; font-size:0.95rem; }

        /* Stats */
        .stats-bar { display:flex; gap:14px; margin-bottom:26px; flex-wrap:wrap; }
        .stat { background:white; border-radius:10px; padding:16px 20px; flex:1; min-width:110px; text-align:center; box-shadow:0 2px 10px rgba(0,0,0,0.06); }
        .stat .num { font-size:1.8rem; font-weight:800; }
        .stat .lbl { font-size:0.78rem; color:#777; margin-top:3px; text-transform:uppercase; letter-spacing:0.4px; }
        .stat-total     { border-left:4px solid #1a1a2e; } .stat-total .num     { color:#1a1a2e; }
        .stat-passed    { border-left:4px solid #28a745; } .stat-passed .num    { color:#28a745; }
        .stat-failed    { border-left:4px solid #dc3545; } .stat-failed .num    { color:#dc3545; }
        .stat-excellent { border-left:4px solid #fd7e14; } .stat-excellent .num { color:#fd7e14; }

        /* Cards */
        .card { background:white; border-radius:12px; padding:26px; box-shadow:0 2px 14px rgba(0,0,0,0.07); margin-bottom:26px; }
        .card h2 { font-size:1.05rem; color:#1a1a2e; font-weight:700; margin-bottom:18px; padding-bottom:10px; border-bottom:2px solid #f0f2f5; }

        /* Section tabs */
        .section-tabs { display:flex; gap:10px; margin-bottom:26px; flex-wrap:wrap; }
        .tab-btn { padding:10px 20px; border-radius:8px; text-decoration:none; font-weight:700; font-size:0.88rem; border:2px solid #e0e0e0; color:#555; background:white; cursor:pointer; transition:all 0.15s; }
        .tab-btn.active, .tab-btn:hover { background:#1a1a2e; color:white; border-color:#1a1a2e; }
        .tab-content { display:none; }
        .tab-content.active { display:block; }

        /* Filter form */
        .filter-grid { display:flex; gap:14px; flex-wrap:wrap; align-items:flex-end; }
        .filter-grid > div { flex:1; min-width:160px; }
        label { display:block; font-weight:600; color:#444; font-size:0.87rem; margin-bottom:6px; }
        select { width:100%; padding:10px 13px; border:1.5px solid #ddd; border-radius:8px; font-size:0.92rem; background:#fafafa; font-family:inherit; }
        select:focus { outline:none; border-color:#1a1a2e; background:white; }
        .btn { padding:10px 22px; border:none; border-radius:8px; cursor:pointer; font-size:0.88rem; font-weight:700; transition:opacity 0.15s; }
        .btn:hover { opacity:0.85; }
        .btn-primary { background:#1a1a2e; color:white; }
        .btn-reset   { background:#e9ecef; color:#333; text-decoration:none; display:inline-block; }

        /* Score filter pills */
        .score-pills { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:18px; }
        .score-pill { padding:7px 18px; border-radius:20px; text-decoration:none; font-size:0.84rem; font-weight:700; border:2px solid transparent; transition:all 0.15s; }
        .pill-all       { background:#f0f2f5; color:#555; border-color:#ddd; }
        .pill-failed    { background:#f8d7da; color:#721c24; border-color:#f5c6cb; }
        .pill-average   { background:#fff3cd; color:#856404; border-color:#ffc107; }
        .pill-good      { background:#cce5ff; color:#004085; border-color:#b8daff; }
        .pill-excellent { background:#d4edda; color:#155724; border-color:#c3e6cb; }
        .score-pill.active { box-shadow:0 0 0 3px rgba(26,26,46,0.3); transform:scale(1.04); }

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

        .student-name { font-weight:700; color:#1a1a2e; }
        .student-user { font-size:0.78rem; color:#aaa; }
        .sub-badge { display:inline-block; background:#e8eef8; color:#1a1a2e; padding:3px 10px; border-radius:20px; font-size:0.8rem; font-weight:700; }
        .assess-exam     { display:inline-block; background:#e8f5e9; color:#1a3a2a; padding:3px 10px; border-radius:20px; font-size:0.8rem; font-weight:700; }
        .assess-midterm  { display:inline-block; background:#e8f0ff; color:#1a1a6e; padding:3px 10px; border-radius:20px; font-size:0.8rem; font-weight:700; }
        .assess-exercise { display:inline-block; background:#f3e8ff; color:#5a1a6e; padding:3px 10px; border-radius:20px; font-size:0.8rem; font-weight:700; }
        .assess-other    { display:inline-block; background:#f0f2f5; color:#444;    padding:3px 10px; border-radius:20px; font-size:0.8rem; font-weight:700; }

        .score-display { display:flex; align-items:center; gap:8px; }
        .score-num { font-size:1.05rem; font-weight:800; }
        .score-bar-wrap { width:50px; height:5px; background:#eee; border-radius:3px; overflow:hidden; }
        .score-bar { height:100%; border-radius:3px; }
        .bar-excellent { background:#28a745; } .bar-verygood { background:#20c997; }
        .bar-good { background:#0d6efd; }      .bar-average  { background:#fd7e14; }
        .bar-failed { background:#dc3545; }

        .perf { display:inline-block; padding:3px 11px; border-radius:20px; font-size:0.78rem; font-weight:800; text-transform:uppercase; letter-spacing:0.3px; }
        .perf-excellent { background:#d4edda; color:#155724; }
        .perf-verygood  { background:#d1f2eb; color:#0e6251; }
        .perf-good      { background:#cce5ff; color:#004085; }
        .perf-average   { background:#fff3cd; color:#856404; }
        .perf-failed    { background:#f8d7da; color:#721c24; }

        .pass-badge { display:inline-block; padding:3px 11px; border-radius:20px; font-size:0.78rem; font-weight:800; }
        .badge-pass { background:#d4edda; color:#155724; }
        .badge-fail { background:#f8d7da; color:#721c24; }

        /* Analytics tables */
        .rank-num { font-size:1.2rem; font-weight:800; color:#1a1a2e; text-align:center; }
        .rank-1 { color:#f4a900; }
        .rank-2 { color:#a8a9ad; }
        .rank-3 { color:#cd7f32; }

        .avg-bar-wrap { width:80px; height:8px; background:#eee; border-radius:4px; overflow:hidden; display:inline-block; vertical-align:middle; margin-left:8px; }
        .avg-bar { height:100%; border-radius:4px; background:linear-gradient(90deg,#1a1a2e,#4a6fa5); }

        .failed-row { background:#fff8f8 !important; }
        .failed-row:hover { background:#fff0f0 !important; }

        .empty { text-align:center; padding:40px; color:#bbb; }
        .empty .icon { font-size:2.8rem; margin-bottom:12px; }
        .result-count { font-size:0.85rem; color:#888; margin-bottom:14px; }

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
        <h1>Grade Records &amp; Analytics</h1>
        <p>View all grades, filter by performance, identify failed students, and recognise top performers.</p>
    </div>

    <!-- Summary stats -->
    <div class="stats-bar">
        <div class="stat stat-total">
            <div class="num"><?php echo $total_grades; ?></div>
            <div class="lbl">Total Grades</div>
        </div>
        <div class="stat stat-passed">
            <div class="num"><?php echo $passed; ?></div>
            <div class="lbl">Passed</div>
        </div>
        <div class="stat stat-failed">
            <div class="num"><?php echo $failed_cnt; ?></div>
            <div class="lbl">Failed</div>
        </div>
        <div class="stat stat-excellent">
            <div class="num"><?php echo $excellent_cnt; ?></div>
            <div class="lbl">Excellent (10)</div>
        </div>
    </div>

    <!-- Section tabs -->
    <div class="section-tabs">
        <a href="#" class="tab-btn active" onclick="showTab('grades',this);return false;">📝 All Grades</a>
        <a href="#" class="tab-btn" onclick="showTab('analytics',this);return false;">📊 Student Rankings</a>
        <a href="#" class="tab-btn" onclick="showTab('failed',this);return false;"> Failed Students</a>
    </div>

    <!-- ── TAB 1: ALL GRADES ── -->
    <div id="tab-grades" class="tab-content active">

        <!-- Filters -->
        <div class="card">
            <h2>🔍 Filter Grades</h2>

            <!-- Score range pills -->
            <div class="score-pills">
                <span style="font-size:0.85rem;color:#666;font-weight:700;align-self:center;">Score range:</span>
                <?php
                $pills = [
                    'all'       => ['label'=>'All Scores',    'class'=>'pill-all'],
                    'failed'    => ['label'=>' Failed (0–4)','class'=>'pill-failed'],
                    'average'   => ['label'=>' Average (5–7)','class'=>'pill-average'],
                    'good'      => ['label'=>' Good (8–9)',  'class'=>'pill-good'],
                    'excellent' => ['label'=>'🌟 Excellent (10)','class'=>'pill-excellent'],
                ];
                foreach ($pills as $val => $p):
                ?>
                    <a href="?subject_id=<?php echo $subject_id; ?>&score_filter=<?php echo $val; ?>"
                       class="score-pill <?php echo $p['class']; ?> <?php echo $score_filter===$val?'active':''; ?>">
                        <?php echo $p['label']; ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <form method="get">
                <input type="hidden" name="score_filter" value="<?php echo htmlspecialchars($score_filter); ?>">
                <div class="filter-grid">
                    <div>
                        <label>Subject</label>
                        <select name="subject_id">
                            <option value="0">All Subjects</option>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?php echo (int)$s['id']; ?>" <?php echo ((int)$s['id']===$subject_id)?'selected':''; ?>>
                                    <?php echo htmlspecialchars($s['subject_name'].(!empty($s['subject_code'])?" ({$s['subject_code']})":'')." — ".$s['teacher_username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="display:flex;gap:8px;align-items:flex-end;">
                        <button type="submit" class="btn btn-primary">Apply</button>
                        <a href="view_grades_admin.php" class="btn btn-reset">Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Grades table -->
        <div class="card">
            <h2>📋 Grade Records</h2>
            <p class="result-count">Showing <strong><?php echo count($grades); ?></strong> record<?php echo count($grades)!==1?'s':''; ?></p>
            <?php if (count($grades) === 0): ?>
                <div class="empty"><div class="icon">📭</div><p>No grade records match the selected filters.</p></div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
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
                        $score    = (float)$row['score'];
                        $max      = (float)$row['max_score'];
                        $perf     = perfLabel($score);
                        $passed_r = $score >= 5;
                        $pct      = $max > 0 ? ($score/$max)*100 : 0;
                        $st_full  = trim(($row['student_name']??'').' '.($row['student_surname']??''));
                        $sub_lbl  = $row['subject_name'].(!empty($row['subject_code'])?" ({$row['subject_code']})":'');
                        $atype    = strtolower($row['assessment']);
                        if ($atype==='exam') $abadge='assess-exam';
                        elseif (strpos($atype,'mid')!==false) $abadge='assess-midterm';
                        elseif ($atype==='exercise') $abadge='assess-exercise';
                        else $abadge='assess-other';
                        $bar_class = str_replace('perf-','bar-',$perf['class']);
                    ?>
                    <tr <?php echo !$passed_r ? 'class="failed-row"' : ''; ?>>
                        <td>
                            <div class="student-name"><?php echo htmlspecialchars($st_full ?: $row['student_username']); ?></div>
                            <div class="student-user">@<?php echo htmlspecialchars($row['student_username']); ?></div>
                        </td>
                        <td><span class="sub-badge"><?php echo htmlspecialchars($sub_lbl); ?></span></td>
                        <td><span class="<?php echo $abadge; ?>"><?php echo htmlspecialchars($row['assessment']); ?></span></td>
                        <td>
                            <div class="score-display">
                                <span class="score-num" style="color:<?php echo $passed_r?'#1a1a2e':'#dc3545'; ?>"><?php echo htmlspecialchars($row['score']); ?></span>
                                <span style="color:#aaa;font-size:0.78rem;">/ <?php echo htmlspecialchars($row['max_score']); ?></span>
                                <div class="score-bar-wrap"><div class="score-bar <?php echo $bar_class; ?>" style="width:<?php echo min(100,$pct); ?>%"></div></div>
                            </div>
                        </td>
                        <td><span class="perf <?php echo $perf['class']; ?>"><?php echo $perf['label']; ?></span></td>
                        <td><span class="pass-badge <?php echo $passed_r?'badge-pass':'badge-fail'; ?>"><?php echo $passed_r?' Passed':' Failed'; ?></span></td>
                        <td style="font-size:0.85rem;color:#555;font-weight:600;"><?php echo htmlspecialchars(graderLabel($row)); ?></td>
                        <td style="color:#aaa;font-size:0.82rem;"><?php echo htmlspecialchars(substr($row['graded_at'],0,10)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

    </div><!-- end tab-grades -->

    <!-- ── TAB 2: STUDENT RANKINGS ── -->
    <div id="tab-analytics" class="tab-content">
        <div class="card">
            <h2>🏆 Student Performance Rankings</h2>
            <p style="font-size:0.88rem;color:#666;margin-bottom:18px;">
                Ranked by average score across all assessments and subjects. Students with average ≥ 8 are considered top performers.
            </p>
            <?php if (count($student_analytics) === 0): ?>
                <div class="empty"><div class="icon">📭</div><p>No grade data available yet.</p></div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th style="width:50px;">Rank</th>
                        <th>Student</th>
                        <th>Avg Score</th>
                        <th>Performance</th>
                        <th>Total Grades</th>
                        <th>Passed</th>
                        <th>Failed</th>
                        <th>Category</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($student_analytics as $i => $st):
                        $rank      = $i + 1;
                        $avg       = (float)$st['avg_score'];
                        $perf      = perfLabel($avg);
                        $pct       = ($avg / 10) * 100;
                        $st_full   = trim(($st['name']??'').' '.($st['surname']??''));
                        $rank_cls  = $rank===1?'rank-1':($rank===2?'rank-2':($rank===3?'rank-3':''));
                        // Category
                        if ($avg >= 8)      $cat = ['label'=>'🌟 Top Performer',    'bg'=>'#d4edda','color'=>'#155724'];
                        elseif ($avg >= 5)  $cat = ['label'=>' Passing',           'bg'=>'#cce5ff','color'=>'#004085'];
                        else               $cat = ['label'=>' Needs Improvement', 'bg'=>'#f8d7da','color'=>'#721c24'];
                    ?>
                    <tr>
                        <td class="rank-num <?php echo $rank_cls; ?>">
                            <?php echo $rank===1?'🥇':($rank===2?'🥈':($rank===3?'🥉':$rank)); ?>
                        </td>
                        <td>
                            <div class="student-name"><?php echo htmlspecialchars($st_full ?: $st['username']); ?></div>
                            <div class="student-user">@<?php echo htmlspecialchars($st['username']); ?></div>
                        </td>
                        <td>
                            <strong style="font-size:1.05rem;"><?php echo $avg; ?></strong>
                            <span style="color:#aaa;font-size:0.78rem;"> / 10</span>
                            <div class="avg-bar-wrap"><div class="avg-bar" style="width:<?php echo min(100,$pct); ?>%"></div></div>
                        </td>
                        <td><span class="perf <?php echo $perf['class']; ?>"><?php echo $perf['label']; ?></span></td>
                        <td style="text-align:center;font-weight:700;"><?php echo (int)$st['total_grades']; ?></td>
                        <td style="text-align:center;color:#28a745;font-weight:700;"><?php echo (int)$st['passed_count']; ?></td>
                        <td style="text-align:center;color:#dc3545;font-weight:700;"><?php echo (int)$st['failed_count']; ?></td>
                        <td><span style="background:<?php echo $cat['bg']; ?>;color:<?php echo $cat['color']; ?>;padding:4px 12px;border-radius:20px;font-size:0.8rem;font-weight:700;"><?php echo $cat['label']; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div><!-- end tab-analytics -->

    <!-- ── TAB 3: FAILED STUDENTS ── -->
    <div id="tab-failed" class="tab-content">
        <div class="card">
            <h2> Failed Grade Records — Retake Reference</h2>
            <p style="font-size:0.88rem;color:#666;margin-bottom:18px;">
                All assessments where a student scored below 5. Use this list to identify students who may need retakes or additional support.
            </p>
            <?php if (count($failed_students) === 0): ?>
                <div class="empty">
                    <div class="icon">🎉</div>
                    <p>No failed grades on record. All students are passing!</p>
                </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Subject</th>
                        <th>Assessment</th>
                        <th>Score</th>
                        <th>Action Needed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($failed_students as $row):
                        $st_full  = trim(($row['name']??'').' '.($row['surname']??''));
                        $sub_lbl  = $row['subject_name'].(!empty($row['subject_code'])?" ({$row['subject_code']})":'');
                        $atype    = strtolower($row['assessment']);
                        if ($atype==='exam') $abadge='assess-exam';
                        elseif (strpos($atype,'mid')!==false) $abadge='assess-midterm';
                        elseif ($atype==='exercise') $abadge='assess-exercise';
                        else $abadge='assess-other';
                    ?>
                    <tr class="failed-row">
                        <td>
                            <div class="student-name"><?php echo htmlspecialchars($st_full ?: $row['username']); ?></div>
                            <div class="student-user">@<?php echo htmlspecialchars($row['username']); ?></div>
                        </td>
                        <td><span class="sub-badge"><?php echo htmlspecialchars($sub_lbl); ?></span></td>
                        <td><span class="<?php echo $abadge; ?>"><?php echo htmlspecialchars($row['assessment']); ?></span></td>
                        <td>
                            <strong style="color:#dc3545;font-size:1.05rem;"><?php echo htmlspecialchars($row['score']); ?></strong>
                            <span style="color:#aaa;font-size:0.78rem;"> / 10</span>
                        </td>
                        <td>
                            <span style="background:#fff3cd;color:#856404;padding:4px 12px;border-radius:20px;font-size:0.8rem;font-weight:700;">
                                📋 Retake Required
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="font-size:0.82rem;color:#aaa;margin-top:14px;">Total failed assessments: <strong><?php echo count($failed_students); ?></strong></p>
            <?php endif; ?>
        </div>
    </div><!-- end tab-failed -->

</div>

<div class="footer">
    <div>
        <p>• Phone: +37000000002</p>
        <p>• Email: admin@portal.com</p>
    </div>
    <div style="align-self:flex-end;color:#555;font-size:0.82rem;">&copy; 2026 Legit Portal</div>
</div>

<script>
function showTab(name, btn) {
    document.querySelectorAll('.tab-content').forEach(function(t) { t.classList.remove('active'); });
    document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}
</script>

</body>
</html>