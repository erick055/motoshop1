<?php
session_start();
require 'includes/db.php'; // Include database connection
// Handle Client Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_client'])) {
    $client_id = $_POST['client_id'];

    try {
        // Delete the user. ON DELETE CASCADE will handle their vehicles and appointments automatically.
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'Customer'");
        $stmt->execute([$client_id]);
        
        header("Location: clients.php?success=deleted");
        exit();
    } catch (PDOException $e) {
        $error = "Error deleting client: " . $e->getMessage();
    }
}
// Security check: Ensure the user is logged in and has the 'Admin' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$adminName = $_SESSION['username'] ?? 'Admin';
$adminEmail = 'admin@gmail.com'; 

// Fetch Dashboard Statistics
$totalClients = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Customer'")->fetchColumn();
$totalVehicles = $pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients - ServiceHub</title>
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
            background-color: #f9fafb; /* Different header color in mockup */
            color: var(--text-dark);
            padding: 12px 15px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            border-bottom: 1px solid var(--border-color);
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
            font-size: 13px;
            color: var(--text-dark);
        }

        .text-green { color: #10b981; }
        
        .action-btn {
            border: none;
            background: none;
            cursor: pointer;
            color: #9ca3af;
            transition: 0.2s;
        }

        .action-btn:hover {
            color: var(--primary-orange);
        }
        /* Table Card & Client Styles */
.table-card {
    background: #fff;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.table-card-header {
    margin-bottom: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
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
table { width: 100%; border-collapse: collapse; }
th {
    background-color: #f9fafb;
    color: var(--text-muted);
    padding: 12px 15px;
    text-align: left;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    border-bottom: 1px solid var(--border-color);
}
td {
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
    font-size: 13px;
    color: var(--text-dark);
}
tbody tr:hover { background-color: #f3f4f6; }

/* Client Avatar and Info */
.client-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}
.client-avatar {
    width: 32px;
    height: 32px;
    background-color: var(--sidebar-hover);
    color: white;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    font-weight: bold;
    font-size: 14px;
}
.client-details strong { display: block; color: var(--text-dark); }
.client-details span { font-size: 11px; color: var(--text-muted); }

.vehicle-badge {
    background: #e0e7ff;
    color: #4f46e5;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}
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
            <li><a href="invoices.php"><i class="fa-solid fa-file-invoice-dollar"></i> Invoices</a></li>
            <li><a href="clients.php" class="active"><i class="fa-solid fa-users"></i> Clients</a></li>
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
                <h1>Clients</h1>
                <p>Manage client information and records</p>
            </div>
        </div>

        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 20px;">
            <div class="stat-card" style="background: #fff; padding: 20px; border-radius: 6px; border: 1px solid var(--border-color);">
                <p style="margin: 0 0 10px 0; font-size: 12px; color: var(--text-muted);">Total Registered Clients</p>
                <h3 style="margin: 0; font-size: 24px; color: var(--text-dark);"><?php echo $totalClients; ?></h3>
            </div>
            <div class="stat-card" style="background: #fff; padding: 20px; border-radius: 6px; border: 1px solid var(--border-color);">
                <p style="margin: 0 0 10px 0; font-size: 12px; color: var(--text-muted);">Total Serviced Vehicles</p>
                <h3 style="margin: 0; font-size: 24px; color: var(--primary-orange);"><?php echo $totalVehicles; ?></h3>
            </div>
        </div>

        <div class="search-container" style="position: relative; max-width: 300px; margin-bottom: 20px;">
            <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af;"></i>
            <input type="text" placeholder="Search Clients..." style="width: 100%; padding: 10px 35px; border: 1px solid var(--border-color); border-radius: 20px; outline: none;">
        </div>

        <div class="table-card">
            <div class="table-card-header">
                <h2><i class="fa-solid fa-users"></i> Client Directory</h2>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Client Profile</th>
                            <th>Contact Email</th>
                            <th>Registered Date</th>
                            <th>Registered Vehicles</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Fetch clients and count their vehicles
                        $query = "
                            SELECT u.id, u.full_name, u.email, u.created_at, 
                                   COUNT(v.id) as vehicle_count
                            FROM users u
                            LEFT JOIN vehicles v ON u.id = v.user_id
                            WHERE u.role = 'Customer'
                            GROUP BY u.id
                            ORDER BY u.created_at DESC
                        ";
                        $stmt = $pdo->query($query);
                        $clients = $stmt->fetchAll();

                        if (count($clients) > 0) {
                            foreach ($clients as $client) {
                                $initial = strtoupper(substr($client['full_name'], 0, 1));
                                $joinDate = date("M d, Y", strtotime($client['created_at']));
                                
                                echo "<tr>";
                                
                                // Client Info Cell with Avatar
                                echo "<td>
                                        <div class='client-cell'>
                                            <div class='client-avatar'>{$initial}</div>
                                            <div class='client-details'>
                                                <strong>" . htmlspecialchars($client['full_name']) . "</strong>
                                                <span>CUST-" . str_pad($client['id'], 4, '0', STR_PAD_LEFT) . "</span>
                                            </div>
                                        </div>
                                      </td>";
                                      
                                echo "<td>" . htmlspecialchars($client['email']) . "</td>";
                                echo "<td>{$joinDate}</td>";
                                
                                // Vehicle Badge
                                echo "<td><span class='vehicle-badge'><i class='fa-solid fa-car'></i> {$client['vehicle_count']} Vehicles</span></td>";
                                
                                // Actions
                                // Actions
                                echo "<td style='display: flex; gap: 5px;'>
                                        <a href='view_client.php?id={$client['id']}' class='action-btn' title='View Profile' style='text-decoration: none;'>
                                            <i class='fa-solid fa-eye'></i>
                                        </a>
                                        <a href='mailto:" . htmlspecialchars($client['email']) . "' class='action-btn' title='Send Email' style='text-decoration: none;'>
                                            <i class='fa-solid fa-envelope'></i>
                                        </a>
                                        <form method='POST' style='display:inline; margin: 0;' onsubmit=\"return confirm('Are you sure you want to delete this client? This will permanently erase their vehicles and service history.');\">
                                            <input type='hidden' name='delete_client' value='1'>
                                            <input type='hidden' name='client_id' value='{$client['id']}'>
                                            <button type='submit' class='action-btn' title='Delete Client' style='color: #ef4444;'>
                                                <i class='fa-solid fa-trash'></i>
                                            </button>
                                        </form>
                                      </td>";
                                      
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' style='text-align:center; padding: 20px; color: var(--text-muted);'>No clients registered yet.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

</body>
</html>