<?php
session_start();
require 'includes/db.php'; // Include database connection

// Security check: Ensure the user is logged in and has the 'Admin' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$message = '';
$messageType = '';

// --- 0. AUTO-SETUP: CREATE SETTINGS TABLE IF IT DOESN'T EXIST ---
try {
    // Test if the table exists
    $pdo->query("SELECT 1 FROM system_settings LIMIT 1");
} catch (PDOException $e) {
    // If it throws an error, the table doesn't exist. Let's create it!
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `system_settings` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `setting_key` varchar(50) NOT NULL,
          `setting_value` text DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `setting_key` (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // Insert default settings
    $defaults = [
        ['shop_name', 'ServiceHub Workshop'],
        ['shop_email', 'contact@servicehub.com'],
        ['shop_phone', '+63 912 345 6789'],
        ['shop_address', '123 Auto Lane, Motor City'],
        ['currency', 'PHP'],
        ['email_notif', '1'],
        ['sms_alerts', '0'],
        ['auto_backup', '1']
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($defaults as $def) {
        $stmt->execute($def);
    }
}

// --- 1. HANDLE PROFILE UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);

    try {
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, username = ?, email = ? WHERE id = ?");
        $stmt->execute([$full_name, $username, $email, $admin_id]);
        $_SESSION['username'] = $full_name; // Update session name
        $message = "Profile updated successfully!";
        $messageType = "success";
    } catch (PDOException $e) {
        $message = "Error updating profile. Username or email might already be in use.";
        $messageType = "error";
    }
}

// --- 2. HANDLE PASSWORD UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Fetch current hash
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$admin_id]);
    $user = $stmt->fetch();

    if (password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_password) {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$new_hash, $admin_id]);
            $message = "Password changed successfully!";
            $messageType = "success";
        } else {
            $message = "New passwords do not match.";
            $messageType = "error";
        }
    } else {
        $message = "Current password is incorrect.";
        $messageType = "error";
    }
}

// --- 3. HANDLE SHOP SETTINGS UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_shop'])) {
    // Map the form inputs to the database keys
    $settings = [
        'shop_name' => $_POST['shop_name'],
        'shop_email' => $_POST['shop_email'],
        'shop_phone' => $_POST['shop_phone'],
        'shop_address' => $_POST['shop_address'],
        'currency' => $_POST['currency'],
        'email_notif' => isset($_POST['email_notif']) ? '1' : '0',
        'sms_alerts' => isset($_POST['sms_alerts']) ? '1' : '0',
        'auto_backup' => isset($_POST['auto_backup']) ? '1' : '0'
    ];

    // Prepare an UPDATE statement that creates the key if it somehow got deleted
    $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    foreach ($settings as $key => $value) {
        $stmt->execute([$key, $value]);
    }
    $message = "Shop settings updated successfully!";
    $messageType = "success";
}
// --- 4. HANDLE DATA EXPORT (CSV / EXCEL COMPATIBLE) ---
if (isset($_GET['export'])) {
    $table = $_GET['export'];
    $allowed_tables = ['inventory', 'invoices', 'appointments', 'job_orders', 'users'];
    
    if (in_array($table, $allowed_tables)) {
        $stmt = $pdo->query("SELECT * FROM $table");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($data) {
            $filename = $table . "_export_" . date('Y-m-d') . ".csv";
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, array_keys($data[0])); // Header row
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
            exit();
        }
    }
}

// --- FETCH CURRENT DATA FOR FORMS ---
// Get Admin User Data
$stmt = $pdo->prepare("SELECT full_name, username, email FROM users WHERE id = ?");
$stmt->execute([$admin_id]);
$currentUser = $stmt->fetch();

// Get System Settings
$settingsQuery = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
$sysSettings = $settingsQuery->fetchAll(PDO::FETCH_KEY_PAIR);

$adminName = $_SESSION['username'] ?? 'Admin';
$adminEmail = $currentUser['email'] ?? ''; 

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - ServiceHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        :root {
            --sidebar-bg: #101623; --sidebar-hover: #1f2937; --primary-orange: #FF7A00;
            --bg-light: #f9fafb; --text-dark: #1f2937; --text-muted: #6b7280; --border-color: #e5e7eb;
        }
        body, html { margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: var(--bg-light); display: flex; height: 100vh; width: 100vw; overflow: hidden; }

        /* Sidebar Styles */
        .sidebar { width: 250px; flex-shrink: 0; background-color: var(--sidebar-bg); color: #fff; display: flex; flex-direction: column; height: 100%; }
        .sidebar-header { padding: 20px; border-bottom: 1px solid #1f2937; display: flex; align-items: center; gap: 10px; }
        .sidebar-header h2 { margin: 0; font-size: 18px; font-weight: 600; }
        .sidebar-header p { margin: 0; font-size: 11px; color: #8b949e; }
        .nav-links { list-style: none; padding: 15px 0; margin: 0; flex-grow: 1; }
        .nav-links li { padding: 5px 20px; }
        .nav-links a { color: #c9d1d9; text-decoration: none; display: flex; align-items: center; padding: 10px 15px; border-radius: 8px; font-size: 14px; transition: 0.2s; }
        .nav-links a i { width: 20px; margin-right: 10px; font-size: 16px; }
        .nav-links a:hover { background-color: var(--sidebar-hover); color: #fff; }
        .nav-links a.active { background-color: var(--primary-orange); color: #fff; font-weight: bold; }
        
        .user-profile-container { border-top: 1px solid #1f2937; padding: 15px 20px; }
        .user-profile { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; }
        .avatar { width: 35px; height: 35px; background-color: var(--primary-orange); color: white; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-weight: bold; font-size: 16px; }
        .user-info { flex-grow: 1; }
        .user-info h4 { margin: 0; font-size: 13px; display: flex; align-items: center; gap: 8px; }
        .admin-badge { background-color: #ff7b72; color: white; font-size: 9px; padding: 2px 6px; border-radius: 10px; }
        .user-info p { margin: 2px 0 0 0; font-size: 10px; color: #8b949e; }
        .logout-btn { color: #c9d1d9; text-decoration: none; transition: 0.2s; }
        .logout-btn:hover { color: #ff7b72; }

        /* Main Content */
        .main-content { flex: 1; padding: 30px 40px; overflow-y: auto; }
        .top-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
        .top-header h1 { margin: 0 0 5px 0; font-size: 22px; color: var(--text-dark); }
        .top-header p { margin: 0; color: var(--text-muted); font-size: 13px; }

        /* Alerts */
        .alert { padding: 12px 20px; border-radius: 6px; margin-bottom: 20px; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .alert-success { background-color: #ecfdf5; color: #10b981; border: 1px solid #a7f3d0; }
        .alert-error { background-color: #fef2f2; color: #ef4444; border: 1px solid #fecaca; }

        /* Settings Grid Layout */
        .settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .settings-card { background: #fff; border-radius: 8px; border: 1px solid var(--border-color); padding: 25px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .settings-card h3 { margin: 0 0 5px 0; font-size: 16px; color: var(--text-dark); display: flex; align-items: center; gap: 8px; }
        .settings-card h3 i { color: var(--primary-orange); }
        .settings-card > p { margin: 0 0 20px 0; font-size: 12px; color: var(--text-muted); }

        /* Form Elements */
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 12px; font-weight: 600; color: var(--text-dark); margin-bottom: 6px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 13px; outline: none; transition: border-color 0.2s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: var(--primary-orange); }

        .btn-submit { background-color: var(--primary-orange); color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 13px; transition: 0.2s; margin-top: 10px; }
        .btn-submit:hover { background-color: #e66a00; }

        /* Toggles */
        .toggle-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border-color); }
        .toggle-row:last-child { border-bottom: none; }
        .toggle-info strong { display: block; font-size: 13px; color: var(--text-dark); }
        .toggle-info span { font-size: 11px; color: var(--text-muted); }

        .toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #d1d5db; transition: .4s; border-radius: 24px; }
        .toggle-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .toggle-slider { background-color: #10b981; }
        input:checked + .toggle-slider:before { transform: translateX(20px); 
    .btn-export {
    display: block;
    text-align: center;
    padding: 10px;
    background: #f3f4f6;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    text-decoration: none;
    color: var(--text-dark);
    font-size: 12px;
    font-weight: 600;
    transition: 0.2s;
}
.btn-export:hover {
    background: var(--sidebar-hover);
    color: white;
}
.btn-export i {
    margin-right: 5px;
}}
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-header">
            <i class="fa-solid fa-wrench" style="color: var(--primary-orange); font-size: 20px;"></i>
            <div>
                <h2>ServiceHub</h2>
                <p>Workshop Management</p>
            </div>
        </div>

        <ul class="nav-links">
            <li><a href="admin_dashboard.php"><i class="fa-solid fa-border-all"></i> Dashboard</a></li>
            <li><a href="appointments.php"><i class="fa-regular fa-calendar-check"></i> Appointments</a></li>
            <li><a href="job_orders.php"><i class="fa-solid fa-clipboard-list"></i> Job Orders</a></li>
            <li><a href="invoices.php"><i class="fa-solid fa-file-invoice-dollar"></i> Invoices</a></li>
            <li><a href="clients.php"><i class="fa-solid fa-users"></i> Clients</a></li>
            <li><a href="inventory.php"><i class="fa-solid fa-box"></i> Inventory</a></li>
            <li><a href="notifications.php"><i class="fa-regular fa-bell"></i> Notifications</a></li>
            <li><a href="settings.php" class="active"><i class="fa-solid fa-gear"></i> Settings</a></li>
        </ul>

        <div class="user-profile-container">
            <div class="user-profile">
                <div class="avatar"><?php echo strtoupper(substr($adminName, 0, 1)); ?></div>
                <div class="user-info">
                    <h4><?php echo htmlspecialchars($adminName); ?> <span class="admin-badge">Admin</span></h4>
                    <p><?php echo htmlspecialchars($adminEmail); ?></p>
                </div>
                <a href="logout.php" class="logout-btn" title="Logout"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <div class="top-header">
            <div>
                <h1>Settings</h1>
                <p>Manage your account and system preferences</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fa-solid <?php echo $messageType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="settings-grid">
            
            <div class="settings-column">
                
                <div class="settings-card" style="margin-bottom: 20px;">
                    <h3><i class="fa-regular fa-user"></i> Profile Settings</h3>
                    <p>Update your personal account information.</p>
                    
                    <form method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($currentUser['full_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" value="<?php echo htmlspecialchars($currentUser['username']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($currentUser['email']); ?>" required>
                        </div>
                        <button type="submit" class="btn-submit">Save Profile</button>
                    </form>
                </div>

                <div class="settings-card">
                    <h3><i class="fa-solid fa-lock"></i> Change Password</h3>
                    <p>Ensure your account uses a long, random password.</p>
                    
                    <form method="POST">
                        <input type="hidden" name="update_password" value="1">
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" name="current_password" required>
                        </div>
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" required>
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" required>
                        </div>
                        <button type="submit" class="btn-submit">Update Password</button>
                    </form>
                </div>

            </div>

            <div class="settings-column">
                
                <form method="POST">
                    <input type="hidden" name="update_shop" value="1">
                    
                    <div class="settings-card" style="margin-bottom: 20px;">
                        <h3><i class="fa-solid fa-store"></i> Shop Settings</h3>
                        <p>Manage public contact info and localization.</p>
                        
                        <div class="form-group">
                            <label>Shop Name</label>
                            <input type="text" name="shop_name" value="<?php echo htmlspecialchars($sysSettings['shop_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Contact Email</label>
                            <input type="email" name="shop_email" value="<?php echo htmlspecialchars($sysSettings['shop_email'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Contact Phone</label>
                            <input type="text" name="shop_phone" value="<?php echo htmlspecialchars($sysSettings['shop_phone'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Shop Address</label>
                            <textarea name="shop_address" rows="2" required><?php echo htmlspecialchars($sysSettings['shop_address'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Default Currency</label>
                            <select name="currency">
                                <option value="PHP" <?php echo ($sysSettings['currency'] ?? '') == 'PHP' ? 'selected' : ''; ?>>PHP - Philippine Peso (₱)</option>
                                <option value="USD" <?php echo ($sysSettings['currency'] ?? '') == 'USD' ? 'selected' : ''; ?>>USD - US Dollar ($)</option>
                            </select>
                        </div>
                    </div>

                    <div class="settings-card">
                    <h3><i class="fa-solid fa-file-export"></i> Data Management</h3>
                    <p>Export system records to CSV format for Excel reporting.</p>
                    
                    <div class="form-group">
                        <label>Select Table to Export</label>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;">
                            <a href="?export=appointments" class="btn-export"><i class="fa-solid fa-calendar-check"></i> Appointments</a>
                            <a href="?export=job_orders" class="btn-export"><i class="fa-solid fa-wrench"></i> Job Orders</a>
                            <a href="?export=invoices" class="btn-export"><i class="fa-solid fa-file-invoice-dollar"></i> Invoices</a>
                            <a href="?export=inventory" class="btn-export"><i class="fa-solid fa-box"></i> Inventory</a>
                            <a href="?export=users" class="btn-export"><i class="fa-solid fa-users"></i> Clients/Users</a>
                        </div>
                    </div>

                    <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 20px 0;">
                    
                    <h3><i class="fa-solid fa-database"></i> Database Backup</h3>
                    <p>Backup all current records into a system archive before clearing data.</p>
                    <button type="button" class="btn-submit" style="background-color: #3b82f6; width: 100%;">
                        <i class="fa-solid fa-download"></i> Download Full SQL Backup
                    </button>
                </div>
                </form>

            </div>
        </div>
    </main>
</body>
</html>