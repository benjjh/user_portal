<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome | Legit Portal</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: #f0f2f5;
        }

        /* ── Navbar ── */
        .navbar {
            background: #1a1a2e;
            padding: 16px 50px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar .brand {
            color: white;
            font-size: 1.2rem;
            font-weight: 800;
            letter-spacing: 1px;
        }
        .navbar .brand span { color: #a0c4ff; }

        /* ── Hero section ── */
        .hero {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 60%, #0f3460 100%);
            color: white;
            text-align: center;
            padding: 70px 20px 80px;
        }
        .hero h1 {
            font-size: 2.6rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            margin-bottom: 14px;
        }
        .hero h1 span { color: #a0c4ff; }
        .hero p {
            font-size: 1.05rem;
            color: #a0c4ff;
            max-width: 520px;
            margin: 0 auto 40px;
            line-height: 1.6;
        }

        /* ── Login cards ── */
        .cards {
            display: flex;
            justify-content: center;
            gap: 28px;
            flex-wrap: wrap;
            padding: 0 20px;
            margin-top: -40px;
            margin-bottom: 60px;
        }

        .login-card {
            background: white;
            border-radius: 16px;
            padding: 36px 32px;
            width: 280px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0,0,0,0.12);
            text-decoration: none;
            color: inherit;
            transition: transform 0.2s, box-shadow 0.2s;
            border-top: 5px solid transparent;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }
        .login-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 40px rgba(0,0,0,0.16);
        }

        /* User card — blue */
        .card-user { border-top-color: #1a1a2e; }
        .card-user .card-icon { background: #e8eef8; color: #1a1a2e; }
        .card-user .card-btn { background: #1a1a2e; }
        .card-user .card-btn:hover { background: #2d2d5e; }

        /* Admin card — dark red/maroon */
        .card-admin { border-top-color: #7b1f1f; }
        .card-admin .card-icon { background: #f8e8e8; color: #7b1f1f; }
        .card-admin .card-btn { background: #7b1f1f; }
        .card-admin .card-btn:hover { background: #9b2f2f; }

        .card-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 4px;
        }
        .card-title {
            font-size: 1.2rem;
            font-weight: 800;
            color: #1a1a2e;
        }
        .card-desc {
            font-size: 0.88rem;
            color: #777;
            line-height: 1.5;
        }
        .card-btn {
            display: inline-block;
            margin-top: 8px;
            padding: 10px 28px;
            color: white;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.92rem;
            text-decoration: none;
            transition: background 0.15s;
            width: 100%;
            text-align: center;
        }

        /* ── Info strip ── */
        .info-strip {
            background: white;
            padding: 36px 20px;
            text-align: center;
            flex: 1;
        }
        .info-strip h2 { font-size: 1.3rem; color: #1a1a2e; margin-bottom: 20px; }
        .features {
            display: flex;
            justify-content: center;
            gap: 24px;
            flex-wrap: wrap;
            max-width: 800px;
            margin: 0 auto;
        }
        .feature {
            background: #f7f9ff;
            border-radius: 10px;
            padding: 18px 22px;
            width: 200px;
            border-left: 4px solid #1a1a2e;
        }
        .feature .f-icon { font-size: 1.6rem; margin-bottom: 8px; }
        .feature .f-title { font-weight: 700; font-size: 0.92rem; color: #1a1a2e; }
        .feature .f-desc  { font-size: 0.82rem; color: #777; margin-top: 4px; }

        /* ── Footer ── */
        .footer {
            background: #1a1a2e;
            color: #a0c4ff;
            padding: 28px 50px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 0.88rem;
        }
        .footer p { margin-top: 3px; }
        .footer .brand { font-weight: 800; font-size: 1rem; color: white; margin-bottom: 6px; }
    </style>
</head>
<body>

    <!-- Navbar -->
    <div class="navbar">
        <div class="brand">LEGIT <span>PORTAL</span></div>
    </div>

    <!-- Hero -->
    <div class="hero">
        <h1>Welcome to <span>Legit Portal</span></h1>
        <p>A web-based student management system for administrators, teachers, and students. Select your login type below to get started.</p>
    </div>

    <!-- Login cards -->
    <div class="cards">

        <div class="login-card card-user">
            <div class="card-icon">🎓</div>
            <div class="card-title">User Login</div>
            <div class="card-desc">For students and teachers. Access your dashboard, grades, attendance, and profile.</div>
            <a href="user_login.php" class="card-btn">Login as User</a>
        </div>

        <div class="login-card card-admin">
            <div class="card-icon">🛡️</div>
            <div class="card-title">Admin Login</div>
            <div class="card-desc">For system administrators. Manage users, subjects, grades, and attendance records.</div>
            <a href="admin_login.php" class="card-btn">Login as Admin</a>
        </div>

    </div>

    <!-- Features strip -->
    <div class="info-strip">
        <h2>What you can do on this portal</h2>
        <div class="features">
            <div class="feature">
                <div class="f-icon">📚</div>
                <div class="f-title">Manage Subjects</div>
                <div class="f-desc">Create and assign subjects to teachers</div>
            </div>
            <div class="feature">
                <div class="f-icon"></div>
                <div class="f-title">Track Attendance</div>
                <div class="f-desc">Record and view attendance for students and teachers</div>
            </div>
            <div class="feature">
                <div class="f-icon">📝</div>
                <div class="f-title">Record Grades</div>
                <div class="f-desc">Enter and view assessment scores per subject</div>
            </div>
            <div class="feature">
                <div class="f-icon">📎</div>
                <div class="f-title">Homework</div>
                <div class="f-desc">Teachers post tasks, students view assignments</div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <div>
            <div class="brand">LEGIT PORTAL</div>
            <p>• Address: City Bee</p>
            <p>• Phone: +37000000001</p>
            <p>• Email: info@portal.com</p>
        </div>
        <div style="align-self:flex-end;color:#666;font-size:0.82rem;">
            &copy; 2026 Legit Portal. All rights reserved.
        </div>
    </div>

</body>
</html>