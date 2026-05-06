<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Customer') {
    header("Location: login.php");
    exit();
}

$customerName = $_SESSION['username'] ?? 'Customer Name';
$customerEmail = 'customer@email.com'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - ServiceHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
        }

        :root {
            --sidebar-bg: #101623;
            --sidebar-hover: #1f2937;
            --primary-orange: #FF7A00;
            --bg-light: #f9fafb;
            --text-dark: #1f2937;
            --text-muted: #6b7280;
            --border-color: #e5e7eb;
        }

        body, html {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-light);
            display: flex;
            height: 100vh;
            width: 100vw;
            overflow: hidden;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            flex-shrink: 0;
            background-color: var(--sidebar-bg);
            color: #fff;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid #1f2937;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }

        .sidebar-header p {
            margin: 0;
            font-size: 11px;
            color: #8b949e;
        }

        .nav-links {
            list-style: none;
            padding: 15px 0;
            margin: 0;
            flex-grow: 1;
        }

        .nav-links li {
            padding: 5px 20px;
        }

        .nav-links a {
            color: #c9d1d9;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 14px;
            transition: 0.2s;
        }

        .nav-links a i {
            width: 20px;
            margin-right: 10px;
            font-size: 16px;
        }

        .nav-links a:hover {
            background-color: var(--sidebar-hover);
            color: #fff;
        }

        .nav-links a.active {
            background-color: var(--primary-orange);
            color: #fff;
            font-weight: bold;
        }

        .user-profile-container {
            border-top: 1px solid #1f2937;
            padding: 15px 20px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .avatar {
            width: 35px;
            height: 35px;
            background-color: var(--primary-orange);
            color: white;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: bold;
            font-size: 16px;
        }

        .user-info {
            flex-grow: 1;
        }

        .user-info h4 {
            margin: 0;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .customer-badge {
            background-color: #3b82f6;
            color: white;
            font-size: 9px;
            padding: 2px 6px;
            border-radius: 10px;
        }

        .user-info p {
            margin: 2px 0 0 0;
            font-size: 10px;
            color: #8b949e;
        }

        .logout-btn {
            color: #c9d1d9;
            text-decoration: none;
            transition: 0.2s;
        }

        .logout-btn:hover {
            color: #ff7b72;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            width: calc(100% - 250px);
            padding: 30px 40px;
            overflow-y: auto;
        }

        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .top-header h1 {
            margin: 0 0 5px 0;
            font-size: 22px;
            color: var(--text-dark);
        }

        .top-header p {
            margin: 0;
            color: var(--text-muted);
            font-size: 13px;
        }

        /* Profile Layout */
        .outline-card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .profile-summary {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .profile-large-avatar {
            width: 80px;
            height: 80px;
            background-color: var(--primary-orange);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            font-size: 30px;
            font-weight: bold;
        }

        .profile-info-row {
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profile-info-row i {
            color: var(--text-muted);
            width: 20px;
            text-align: center;
        }

        .profile-info-row input {
            border: 1px solid var(--border-color);
            padding: 6px;
            border-radius: 4px;
            font-size: 13px;
            outline: none;
            background-color: #f9fafb;
        }

        .outline-card h3 {
            margin: 0 0 5px 0;
            font-size: 16px;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .outline-card .sub-text {
            font-size: 11px;
            color: var(--text-muted);
            margin-bottom: 20px;
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }

        .form-group {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 5px;
        }

        .form-group input {
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 12px;
            outline: none;
        }

        .form-group input:focus {
            border-color: var(--primary-orange);
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon input {
            width: 100%;
        }

        .input-with-icon i {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            cursor: pointer;
        }

        .btn-purple {
            background-color: #d8b4fe;
            color: #581c87;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 13px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }

        .btn-purple:hover {
            background-color: #c084fc;
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-header">
            <i class="fa-solid fa-wrench" style="color: var(--primary-orange); font-size: 20px;"></i>
            <div>
                <h2>ServiceHub</h2>
                <p>Customer Portal</p>
            </div>
        </div>

        <ul class="nav-links">
            <li><a href="customer_dashboard.php"><i class="fa-solid fa-border-all"></i> Dashboard</a></li>
            <li><a href="my_vehicles.php"><i class="fa-solid fa-car"></i> My Vehicles</a></li>
            <li><a href="book_appointment.php"><i class="fa-regular fa-calendar-plus"></i> Book Appointment</a></li>
            <li><a href="service_history.php"><i class="fa-solid fa-clock-rotate-left"></i> Service History</a></li>
            <li><a href="my_invoices.php"><i class="fa-solid fa-file-invoice-dollar"></i> Invoices</a></li>
            <li><a href="support.php"><i class="fa-regular fa-circle-question"></i> Support</a></li>
            <li><a href="customer_profile.php" class="active"><i class="fa-regular fa-user"></i> Profile</a></li>
        </ul>

        <div class="user-profile-container">
            <div class="user-profile">
                <div class="avatar"><?php echo strtoupper(substr($customerName, 0, 1)); ?></div>
                <div class="user-info">
                    <h4><?php echo htmlspecialchars($customerName); ?> <span class="customer-badge">Customer</span></h4>
                    <p><?php echo htmlspecialchars($customerEmail); ?></p>
                </div>
                <a href="logout.php" class="logout-btn" title="Logout"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <div class="top-header">
            <div>
                <h1>Profile</h1>
                <p>Manage your personal information and security settings</p>
            </div>
        </div>

        <div class="outline-card profile-summary">
            <div class="profile-large-avatar">
                <?php echo strtoupper(substr($customerName, 0, 1)); ?>
            </div>
            <div>
                <div class="profile-info-row">
                    <i class="fa-solid fa-shield-halved"></i>
                    <span class="customer-badge" style="margin-left: 0; padding: 4px 10px; font-size: 11px;">Customer</span>
                </div>
                <div class="profile-info-row">
                    <i class="fa-solid fa-envelope"></i>
                    <input type="text" value="<?php echo htmlspecialchars($customerEmail); ?>" disabled>
                </div>
                <div class="profile-info-row">
                    <i class="fa-regular fa-calendar"></i>
                    <input type="text" placeholder="Member since:" disabled>
                </div>
            </div>
        </div>

        <div class="outline-card">
            <h3><i class="fa-regular fa-user"></i> Personal Information</h3>
            <p class="sub-text">Update your name and email address</p>
            
            <form action="" method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" placeholder="Name" value="<?php echo htmlspecialchars($customerName); ?>">
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" placeholder="e.g. (09XX) XXX-XXXX">
                    </div>
                </div>
                <button type="submit" class="btn-purple"><i class="fa-solid fa-gear"></i> Save changes</button>
            </form>
        </div>

        <div class="outline-card">
            <h3><i class="fa-solid fa-lock"></i> Change Password</h3>
            <p class="sub-text">Update your password to keep your account secure</p>
            
            <form action="" method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Current Password</label>
                        <div class="input-with-icon">
                            <input type="password" placeholder="Enter current password">
                            <i class="fa-regular fa-eye"></i>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>New Password</label>
                        <div class="input-with-icon">
                            <input type="password" placeholder="At least 6 characters">
                            <i class="fa-regular fa-eye"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" placeholder="Re-enter new password">
                    </div>
                </div>
                <button type="submit" class="btn-purple"><i class="fa-solid fa-gear"></i> Update Password</button>
            </form>
        </div>
    </main>

</body>
</html>