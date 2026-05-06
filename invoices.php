<?php
session_start();
require 'includes/db.php'; // Include database connection

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$adminName = $_SESSION['username'] ?? 'Admin';
$adminEmail = 'admin@gmail.com'; 

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_invoice_status'])) {
    $invoice_id = $_POST['invoice_id'];
    $new_status = $_POST['status'];

    try {
        $stmt = $pdo->prepare("UPDATE invoices SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $invoice_id]);
        header("Location: invoices.php?success=1");
        exit();
    } catch (PDOException $e) {
        $error = "Error updating status: " . $e->getMessage();
    }
}

// Calculate Statistics
$statsQuery = $pdo->query("SELECT status, SUM(amount) as total_amount FROM invoices GROUP BY status");
$statsData = $statsQuery->fetchAll(PDO::FETCH_KEY_PAIR); // Creates an array like ['Paid' => 1000, 'Pending' => 500]

$totalRevenue = $statsData['Paid'] ?? 0;
$pendingAmount = $statsData['Pending'] ?? 0;
$overdueAmount = $statsData['Overdue'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoices - ServiceHub</title>
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

        /* User Profile */
        .user-profile-container {
            border-top: 1px solid #1f2937;
            padding: 15px 20px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
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

        .admin-badge {
            background-color: #ff7b72;
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

        .app-version {
            font-size: 9px;
            color: #4b5563;
            text-align: left;
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

        /* Stats & Search */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            text-align: left;
        }

        .stat-card p {
            margin: 0 0 10px 0;
            font-size: 12px;
            color: var(--text-muted);
        }

        .stat-card h3 {
            margin: 0;
            font-size: 20px;
            color: var(--text-dark);
        }

        .search-container {
            position: relative;
            max-width: 300px;
            margin-bottom: 20px;
        }

        .search-container input {
            width: 100%;
            padding: 10px 35px;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            background-color: #f3f4f6;
            font-size: 13px;
            outline: none;
        }

        .search-container input:focus {
            border-color: var(--primary-orange);
        }

        .search-container .fa-magnifying-glass {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 13px;
        }

        /* Table Card */
        .table-card {
            background: #fff;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .table-card-header {
            margin-bottom: 15px;
        }

        .table-card-header h2 {
            margin: 0;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .table-wrapper {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            overflow: hidden;
            min-height: 400px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background-color: var(--sidebar-bg);
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-size: 13px;
            font-weight: 500;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
            font-size: 13px;
            color: var(--text-dark);
        }
        /* Invoice Status Select Form */
.status-form {
    display: flex;
    align-items: center;
    gap: 8px;
}
.status-select {
    padding: 6px 10px;
    border-radius: 4px;
    border: 1px solid var(--border-color);
    font-size: 12px;
    font-weight: 600;
    outline: none;
    cursor: pointer;
}
.status-select.Pending { color: #f59e0b; background: #fffbeb; border-color: #fcd34d; }
.status-select.Paid { color: #10b981; background: #ecfdf5; border-color: #a7f3d0; }
.status-select.Overdue { color: #ef4444; background: #fef2f2; border-color: #fecaca; }

.btn-update {
    background: var(--text-dark);
    color: white;
    border: none;
    padding: 6px 10px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 11px;
    transition: 0.2s;
}
.btn-update:hover { background: var(--primary-orange); }

.action-btn { border: none; background: none; cursor: pointer; color: #9ca3af; font-size: 14px; margin-right: 5px; }
.action-btn:hover { color: var(--primary-orange); }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-header">
            <i class="fa-solid fa-wrench" style="color: var(--primary-orange); font-size: 20px;"></i>
            <div>
                <h2>Name</h2>
                <p>Workshop Management</p>
            </div>
        </div>

        <ul class="nav-links">
            <li><a href="admin_dashboard.php"><i class="fa-solid fa-border-all"></i> Dashboard</a></li>
            <li><a href="appointments.php"><i class="fa-regular fa-calendar-check"></i> Appointments</a></li>
            <li><a href="job_orders.php"><i class="fa-solid fa-clipboard-list"></i> Job Orders</a></li>
            <li><a href="invoices.php" class="active"><i class="fa-solid fa-file-invoice-dollar"></i> Invoices</a></li>
            <li><a href="clients.php"><i class="fa-solid fa-users"></i> Clients</a></li>
            <li><a href="inventory.php"><i class="fa-solid fa-box"></i> Inventory</a></li>
            <li><a href="notifications.php"><i class="fa-regular fa-bell"></i> Notifications</a></li>
            <li><a href="settings.php"><i class="fa-solid fa-gear"></i> Settings</a></li>
        </ul>

        <div class="user-profile-container">
            <div class="user-profile">
                <div class="avatar"><?php echo strtoupper(substr($adminName, 0, 1)); ?></div>
                <div class="user-info">
                    <h4><?php echo htmlspecialchars($adminName); ?> <span class="admin-badge">Admin</span></h4>
                    <p><?php echo htmlspecialchars($adminEmail); ?></p>
                </div>
                <a href="index.php" class="logout-btn" title="Logout"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
            </div>
            <div class="app-version">Workshop Manager v1.0</div>
        </div>
    </aside>

    <main class="main-content">
        <div class="top-header">
            <div>
                <h1>Invoices</h1>
                <p>Manage billing and payment records</p>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <p>Total Revenue (Paid)</p>
                <h3>₱<?php echo number_format($totalRevenue, 2); ?></h3>
            </div>
            <div class="stat-card">
                <p>Pending Amount</p>
                <h3 style="color: #f59e0b;">₱<?php echo number_format($pendingAmount, 2); ?></h3>
            </div>
            <div class="stat-card">
                <p>Overdue Amount</p>
                <h3 style="color: #ef4444;">₱<?php echo number_format($overdueAmount, 2); ?></h3>
            </div>
        </div>

        <div class="search-container">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" placeholder="Search Invoices...">
        </div>

        <div class="table-card">
            <div class="table-card-header">
                <h2><i class="fa-solid fa-file-invoice"></i> Invoices Records</h2>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Invoice ID</th>
                            <th>Job Order Ref</th>
                            <th>Client</th>
                            <th>Created / Due Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Fetch invoices joined with user details
                        $query = "
                            SELECT i.id, i.job_order_id, i.amount, i.status, i.due_date, i.created_at, 
                                   u.full_name 
                            FROM invoices i
                            JOIN users u ON i.user_id = u.id
                            ORDER BY i.created_at DESC
                        ";
                        $stmt = $pdo->query($query);
                        $invoices = $stmt->fetchAll();

                        if (count($invoices) > 0) {
                            foreach ($invoices as $inv) {
                                $createdDate = date("M d, Y", strtotime($inv['created_at']));
                                $dueDate = date("M d, Y", strtotime($inv['due_date']));
                                
                                echo "<tr>";
                                echo "<td><strong>INV-" . str_pad($inv['id'], 4, '0', STR_PAD_LEFT) . "</strong></td>";
                                echo "<td>JOB-" . str_pad($inv['job_order_id'], 4, '0', STR_PAD_LEFT) . "</td>";
                                echo "<td>" . htmlspecialchars($inv['full_name']) . "</td>";
                                echo "<td>{$createdDate} <br><span style='color: var(--text-muted); font-size: 11px;'>Due: {$dueDate}</span></td>";
                                echo "<td><strong>₱" . number_format($inv['amount'], 2) . "</strong></td>";
                                
                                // Status Update Dropdown Form
                                echo "<td>
                                        <form method='POST' class='status-form'>
                                            <input type='hidden' name='update_invoice_status' value='1'>
                                            <input type='hidden' name='invoice_id' value='{$inv['id']}'>
                                            <select name='status' class='status-select {$inv['status']}'>
                                                <option value='Pending' " . ($inv['status'] == 'Pending' ? 'selected' : '') . ">Pending</option>
                                                <option value='Paid' " . ($inv['status'] == 'Paid' ? 'selected' : '') . ">Paid</option>
                                                <option value='Overdue' " . ($inv['status'] == 'Overdue' ? 'selected' : '') . ">Overdue</option>
                                            </select>
                                            <button type='submit' class='btn-update' title='Update Status'><i class='fa-solid fa-check'></i></button>
                                        </form>
                                      </td>";
                                      
                                echo "<td>
                                        <button class='action-btn' title='View/Print Invoice'><i class='fa-solid fa-print'></i></button>
                                      </td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='7' style='text-align:center; padding: 20px; color: var(--text-muted);'>No invoices found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

</body>
</html>