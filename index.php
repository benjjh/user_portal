<!DOCTYPE html>
<html lang="en">
<head>
    <title>Welcome to Legit Portal</title>
    <style>
        body { font-family: Arial; margin: 0; display: flex; flex-direction: column; min-height: 100vh; }
        .navbar { background-color: #f4f4f4; border-bottom: 2px solid #ccc; padding: 15px 50px; text-align: right; }
        .content { flex: 1; text-align: center; padding: 100px; }
        .btn { padding: 15px 30px; margin: 20px; font-size: 18px; cursor: pointer; text-decoration: none; border-radius: 5px; color: white; display: inline-block; }
        .user-btn { background-color: #007bff; }
        .admin-btn { background-color: #6c757d; }
        .footer { background-color: #000; color: #fff; padding: 30px 50px; }
    </style>
</head>
<body>
    <div class="navbar"><strong>HOME</strong></div>
    
    <div class="content">
        <h1>Welcome to the Portal</h1>
        <p>Please select your login type:</p>
        <a href="user_login.php" class="btn user-btn">User Login</a>
        <a href="admin_login.php" class="btn admin-btn">Admin Login</a>
    </div>

    <div class="footer">
        <h3>Contacts: +37000000001</h3>
        <p>• Address: City Bee</p>
        <p>• Email: info@portal.com</p>
    </div>
</body>
</html>