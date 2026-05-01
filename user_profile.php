<?php
require_once 'config.php';

if (!isset($_SESSION["user_loggedin"]) || $_SESSION["user_loggedin"] !== true) {
    header("location: user_login.php");
    exit;
}

$user_id  = (int)$_SESSION["user_id"];
$message  = "";
$msg_type = "success";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_profile') {
    $name        = trim($_POST['name'] ?? '');
    $surname     = trim($_POST['surname'] ?? '');
    $birth_year  = trim($_POST['birth_year'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // Get current photo from DB
    $res        = $conn->query("SELECT photo FROM users WHERE id = $user_id");
    $row        = $res->fetch_assoc();
    $photo_path = $row['photo'];

    // Handle photo upload
    if (isset($_FILES["photo"]) && $_FILES["photo"]["error"] == 0 && $_FILES["photo"]["size"] > 0) {
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext          = strtolower(pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed_exts)) {
            $message  = "Only image files are allowed: JPG, JPEG, PNG, GIF, WEBP.";
            $msg_type = "error";
        } else {
            $img_info = @getimagesize($_FILES["photo"]["tmp_name"]);
            if ($img_info === false) {
                $message  = "The selected file is not a valid image. Please choose a JPG, PNG, GIF, or WEBP file.";
                $msg_type = "error";
            } else {
                // Absolute path — __DIR__ is the folder where this PHP file lives
                $upload_dir = __DIR__ . '/uploads/';

                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                // Force writable on XAMPP
                @chmod($upload_dir, 0777);

                $filename      = $user_id . '_' . time() . '.' . $ext;
                $full_path     = $upload_dir . $filename;
                $relative_path = 'uploads/' . $filename; // stored in DB, used in <img src>

                if (move_uploaded_file($_FILES["photo"]["tmp_name"], $full_path)) {
                    $photo_path = $relative_path;
                    $message    = ""; // clear so we fall through to DB update
                } else {
                    $message  = "Upload failed. PHP tried to write to: " . $full_path . " — In Windows Explorer, right-click the 'uploads' folder > Properties > Security > Edit > give 'Everyone' Write permission, then try again.";
                    $msg_type = "error";
                }
            }
        }
    }

    if ($message === "") {
        $sql  = "UPDATE users SET name=?, surname=?, birth_year=?, description=?, photo=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssissi", $name, $surname, $birth_year, $description, $photo_path, $user_id);
        if ($stmt->execute()) {
            $message  = "Profile updated successfully!";
            $msg_type = "success";
        } else {
            $message  = "Database error: " . $stmt->error;
            $msg_type = "error";
        }
        $stmt->close();
    }
}

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

$role           = $_SESSION["role"] ?? 'student';
$dashboard_link = ($role === 'teacher') ? 'teacher_dashboard.php' : 'student_dashboard.php';
$nav_grad       = ($role === 'teacher') ? 'linear-gradient(135deg,#1a3a2a,#2d5a3d)' : 'linear-gradient(135deg,#1a1a2e,#16213e)';
$nav_color      = ($role === 'teacher') ? '#1a3a2a' : '#1a1a2e';
$accent         = ($role === 'teacher') ? '#2d5a3d' : '#1a1a2e';
$link_color     = ($role === 'teacher') ? '#a8d5b5' : '#a0c4ff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; min-height: 100vh; display: flex; flex-direction: column; }
        .navbar {
            background: <?php echo $nav_grad; ?>;
            color: white; padding: 15px 40px;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .navbar .logo { font-size: 1.1rem; font-weight: 700; }
        .navbar a { color: <?php echo $link_color; ?>; text-decoration: none; margin-left: 18px; font-weight: 600; }
        .navbar a:hover { color: white; }
        .content { flex: 1; max-width: 700px; margin: 40px auto; padding: 0 20px; width: 100%; }
        .profile-header { display: flex; align-items: center; gap: 24px; background: white; border-radius: 12px; padding: 28px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); margin-bottom: 24px; }
        .avatar { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid #ddd; flex-shrink: 0; }
        .avatar-placeholder { width: 100px; height: 100px; border-radius: 50%; background: #e8eef8; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; border: 3px solid #ddd; flex-shrink: 0; }
        .profile-info h2 { font-size: 1.3rem; color: #1a1a2e; }
        .profile-info p { color: #666; margin-top: 4px; font-size: 0.95rem; }
        .role-badge { display: inline-block; padding: 3px 14px; border-radius: 20px; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 8px; }
        .role-teacher { background: #d4edda; color: #155724; }
        .role-student { background: #cce5ff; color: #004085; }
        .card { background: white; border-radius: 12px; padding: 28px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); margin-bottom: 24px; }
        .card h3 { font-size: 1rem; color: <?php echo $accent; ?>; margin-bottom: 18px; border-bottom: 2px solid #f0f2f5; padding-bottom: 10px; font-weight: 700; }
        .view-row { display: flex; padding: 10px 0; border-bottom: 1px solid #f0f2f5; font-size: 0.93rem; }
        .view-row:last-child { border-bottom: none; }
        .view-label { font-weight: 600; color: #555; width: 140px; flex-shrink: 0; }
        .view-value { color: #222; }
        label { display: block; font-weight: 600; margin-bottom: 6px; color: #444; font-size: 0.88rem; }
        input[type="text"], input[type="number"], textarea { width: 100%; padding: 10px 14px; border: 1px solid #ccc; border-radius: 6px; font-size: 0.95rem; margin-bottom: 16px; background: #fafafa; font-family: inherit; }
        input:focus, textarea:focus { outline: none; border-color: <?php echo $accent; ?>; background: white; }
        input[type="file"] { margin-bottom: 6px; font-size: 0.92rem; }
        .file-note { font-size: 0.82rem; color: #888; margin-bottom: 16px; }
        textarea { resize: vertical; }
        .btn { padding: 11px 28px; background: <?php echo $accent; ?>; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 0.95rem; font-weight: 600; }
        .btn:hover { opacity: 0.88; }
        .msg { padding: 12px 18px; border-radius: 6px; margin-bottom: 20px; font-size: 0.9rem; word-break: break-all; line-height: 1.5; }
        .msg-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .msg-error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .footer { background: <?php echo $nav_color; ?>; color: <?php echo $link_color; ?>; padding: 24px 40px; font-size: 0.88rem; }
        .footer p { margin-top: 4px; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="logo"><?php echo ($role === 'teacher') ? 'Teacher Portal' : 'Student Portal'; ?></div>
    <div>
        <a href="<?php echo $dashboard_link; ?>">Dashboard</a>
        <a href="index.php">Home</a>
        <a href="user_logout.php">Logout</a>
    </div>
</div>

<div class="content">

    <?php if ($message): ?>
        <div class="msg msg-<?php echo $msg_type; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <!-- Profile header with photo -->
    <div class="profile-header">
        <?php
        $photo     = $user_data['photo'] ?? '';
        $photo_abs = __DIR__ . '/' . $photo;
        if (!empty($photo) && file_exists($photo_abs)):
        ?>
            <img src="<?php echo htmlspecialchars($photo); ?>?t=<?php echo time(); ?>" class="avatar" alt="Profile Photo">
        <?php else: ?>
            <div class="avatar-placeholder">👤</div>
        <?php endif; ?>
        <div class="profile-info">
            <h2><?php echo htmlspecialchars($user_data['username']); ?></h2>
            <?php $full = trim(($user_data['name'] ?? '') . ' ' . ($user_data['surname'] ?? '')); ?>
            <?php if ($full): ?><p><?php echo htmlspecialchars($full); ?></p><?php endif; ?>
            <span class="role-badge <?php echo ($role === 'teacher') ? 'role-teacher' : 'role-student'; ?>">
                <?php echo ucfirst($role); ?>
            </span>
        </div>
    </div>

    <!-- View profile info -->
    <div class="card">
        <h3>Profile Information</h3>
        <div class="view-row"><span class="view-label">Username</span><span class="view-value"><?php echo htmlspecialchars($user_data['username']); ?></span></div>
        <div class="view-row"><span class="view-label">First Name</span><span class="view-value"><?php echo htmlspecialchars($user_data['name'] ?: '—'); ?></span></div>
        <div class="view-row"><span class="view-label">Surname</span><span class="view-value"><?php echo htmlspecialchars($user_data['surname'] ?: '—'); ?></span></div>
        <div class="view-row"><span class="view-label">Year of Birth</span><span class="view-value"><?php echo htmlspecialchars($user_data['birth_year'] ?: '—'); ?></span></div>
        <div class="view-row"><span class="view-label">About Me</span><span class="view-value"><?php echo nl2br(htmlspecialchars($user_data['description'] ?: '—')); ?></span></div>
    </div>

    <!-- Edit form -->
    <div class="card">
        <h3>Edit Profile</h3>
        <form action="" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_profile">
            <label>First Name</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($user_data['name'] ?? ''); ?>">
            <label>Surname</label>
            <input type="text" name="surname" value="<?php echo htmlspecialchars($user_data['surname'] ?? ''); ?>">
            <label>Year of Birth</label>
            <input type="number" name="birth_year" value="<?php echo htmlspecialchars($user_data['birth_year'] ?? ''); ?>" min="1900" max="2099">
            <label>About Me</label>
            <textarea name="description" rows="4"><?php echo htmlspecialchars($user_data['description'] ?? ''); ?></textarea>
            <label>Profile Photo</label>
            <input type="file" name="photo" accept=".jpg,.jpeg,.png,.gif,.webp">
            <p class="file-note">Accepted: JPG, JPEG, PNG, GIF, WEBP — images only. No videos or documents.</p>
            <button type="submit" class="btn">Update Profile</button>
        </form>
    </div>

</div>

<div class="footer">
    <p>• Phone: +37000000123</p>
    <p>• Email: info@portal.com</p>
</div>
</body>
</html>