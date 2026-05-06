<?php
session_start();
require 'includes/db.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        
        // Redirect based on role
        if ($user['role'] === 'Admin') {
            header("Location: admin_dashboard.php");
        } else {
            header("Location: customer_dashboard.php");
        }
        exit();
    } else {
        $error = "Invalid username or password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In - ServiceHub</title>
    <style>
        body {
    background-color: #0d1117;
    color: #fff;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    margin: 0;
}

.card {
    background-color: #161b22;
    padding: 40px;
    border-radius: 10px;
    width: 100%;
    max-width: 400px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
}

.header {
    text-align: center;
    margin-bottom: 25px;
}

.header h2 {
    margin: 10px 0 5px;
    font-size: 24px;
}

.header p {
    color: #8b949e;
    font-size: 14px;
    margin: 0;
}

.role-toggle {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.role-btn {
    flex: 1;
    padding: 10px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: bold;
    background-color: #21262d;
    color: #8b949e;
    transition: 0.3s;
}

.role-btn.active {
    background-color: #FF7A00;
    color: #fff;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    font-size: 12px;
    margin-bottom: 5px;
    color: #c9d1d9;
}

.form-group input {
    width: 100%;
    padding: 10px;
    border-radius: 5px;
    border: 1px solid #30363d;
    background-color: #0d1117;
    color: #fff;
    box-sizing: border-box;
}

.form-group input:focus {
    outline: none;
    border-color: #FF7A00;
}

.submit-btn {
    width: 100%;
    padding: 12px;
    background: linear-gradient(90deg, #E63946 0%, #FF7A00 100%);
    border: none;
    border-radius: 5px;
    color: white;
    font-weight: bold;
    cursor: pointer;
    margin-top: 10px;
}

.footer-text {
    text-align: center;
    margin-top: 20px;
    font-size: 13px;
}

.footer-text a {
    color: #FF7A00;
    text-decoration: none;
}

.msg {
    text-align: center;
    color: #ff7b72;
    font-size: 14px;
    margin-bottom: 15px;
}
    </style>
</head>
<body>

<div class="card">
    <div class="header">
        <span style="color: #FF7A00; font-size: 24px;">🔧</span>
        <h2>Welcome Back</h2>
    </div>

    <?php if($error): ?>
        <div class="msg"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" required>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required>
        </div>

        <button type="submit" class="submit-btn">Log In</button>
    </form>

    <div class="footer-text">
        Don't have an account? <a href="register.php">Create one</a>
    </div>
</div>

</body>
</html>