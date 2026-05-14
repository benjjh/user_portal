<?php
require_once 'config.php';

$message  = "";
$msg_type = "success";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $message  = "Please fill in all fields.";
        $msg_type = "error";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql  = "INSERT INTO admins (username, password) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $username, $hashed_password);

        if ($stmt->execute()) {
            $message  = "Administrator registered successfully! You can now log in.";
            $msg_type = "success";
        } else {
            if ($conn->errno == 1062) {
                $message = "That username is already taken. Please choose a different one.";
            } else {
                $message = "Error: Could not register. " . $stmt->error;
            }
            $msg_type = "error";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Registration | Legit Portal</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: #f5f0f0;
        }

        /* Navbar */
        .navbar {
            background: #4a0f0f;
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar .brand { color: white; font-size: 1.1rem; font-weight: 800; letter-spacing: 1px; }
        .navbar .brand span { color: #ffb3b3; }
        .navbar a { color: #ffb3b3; text-decoration: none; font-weight: 600; font-size: 0.92rem; }
        .navbar a:hover { color: white; }

        /* Page center */
        .page {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        /* Registration box */
        .register-box {
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(74,15,15,0.13);
            width: 100%;
            max-width: 420px;
            overflow: hidden;
        }

        /* Top banner */
        .register-banner {
            background: linear-gradient(135deg, #4a0f0f 0%, #7b1f1f 100%);
            padding: 32px 36px 28px;
            text-align: center;
            color: white;
        }
        .banner-icon {
            width: 70px;
            height: 70px;
            background: rgba(255,255,255,0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 14px;
            border: 2px solid rgba(255,255,255,0.3);
        }
        .banner-title { font-size: 1.4rem; font-weight: 800; letter-spacing: 0.5px; }
        .banner-sub   { font-size: 0.88rem; color: #ffb3b3; margin-top: 5px; }

        /* Form area */
        .form-area { padding: 32px 36px 36px; }

        /* Colour strip */
        .role-strip {
            height: 4px;
            background: linear-gradient(90deg, #4a0f0f, #c0392b);
            border-radius: 2px;
            margin-bottom: 24px;
        }

        /* Messages */
        .msg {
            padding: 11px 14px;
            border-radius: 7px;
            font-size: 0.88rem;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .msg-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .msg-error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* Info note */
        .info-note {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffc107;
            border-radius: 7px;
            padding: 9px 14px;
            font-size: 0.83rem;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 8px;
            line-height: 1.5;
        }

        /* Form fields */
        label {
            display: block;
            font-weight: 700;
            font-size: 0.85rem;
            color: #444;
            margin-bottom: 6px;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid #ddd;
            border-radius: 8px;
            font-size: 0.95rem;
            margin-bottom: 18px;
            background: #fafafa;
            font-family: inherit;
            transition: border-color 0.15s, background 0.15s;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #7b1f1f;
            background: white;
        }

        /* Password strength hint */
        .field-hint {
            font-size: 0.8rem;
            color: #aaa;
            margin-top: -14px;
            margin-bottom: 18px;
        }

        /* Submit button */
        .btn-register {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 8px;
            background: linear-gradient(135deg, #4a0f0f, #7b1f1f);
            color: white;
            font-size: 1rem;
            font-weight: 800;
            cursor: pointer;
            letter-spacing: 0.4px;
            transition: opacity 0.15s;
            margin-top: 4px;
        }
        .btn-register:hover { opacity: 0.88; }

        /* Bottom link */
        .bottom-link {
            text-align: center;
            margin-top: 20px;
            font-size: 0.88rem;
            color: #888;
        }
        .bottom-link a { color: #7b1f1f; font-weight: 700; text-decoration: none; }
        .bottom-link a:hover { text-decoration: underline; }

        /* Footer */
        .footer {
            background: #4a0f0f;
            color: #ffb3b3;
            padding: 22px 40px;
            font-size: 0.88rem;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
        }
        .footer p { margin-top: 3px; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="brand">LEGIT <span>PORTAL</span> — Admin</div>
    <a href="index.php">← Back to Home</a>
</div>

<div class="page">
    <div class="register-box">

        <!-- Banner -->
        <div class="register-banner">
            <div class="banner-icon">🛡️</div>
            <div class="banner-title">Admin Registration</div>
            <div class="banner-sub">Create a new administrator account</div>
        </div>

        <!-- Form -->
        <div class="form-area">
            <div class="role-strip"></div>

            <div class="info-note">
                ⚠️ Only authorised personnel should register as an administrator. This account will have full access to the system.
            </div>

            <?php if ($message): ?>
                <div class="msg msg-<?php echo $msg_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                    <?php if ($msg_type === 'success'): ?>
                        <br><a href="admin_login.php" style="color:#155724;font-weight:700;">Click here to log in →</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <label for="username">Username</label>
                <input type="text" id="username" name="username"
                       placeholder="Choose an admin username" required
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">

                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       placeholder="Create a strong password" required>
                <p class="field-hint">Use a mix of letters, numbers, and symbols for a strong password.</p>

                <button type="submit" class="btn-register">Register Admin Account</button>
            </form>

            <div class="bottom-link">
                Already have an account? <a href="admin_login.php">Log in here</a>
            </div>
        </div>

    </div>
</div>

<div class="footer">
    <div>
        <p>• Phone: +37000000002</p>
        <p>• Email: admin@portal.com</p>
    </div>
    <div style="align-self:flex-end;color:#8a4a4a;font-size:0.82rem;">&copy; 2026 Legit Portal</div>
</div>

</body>
</html>