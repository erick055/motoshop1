<?php
session_start();
require 'includes/db.php'; 

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$adminName = $_SESSION['username'] ?? 'Admin';
$adminEmail = 'admin@gmail.com'; 

// Handle Job Order Creation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_job_order'])) {
    $appointment_id = $_POST['appointment_id'];
    $assignee = $_POST['assignee'];
    $status = $_POST['status'];
    $cost = $_POST['cost'];

    try {
        $stmt = $pdo->prepare("INSERT INTO job_orders (appointment_id, assignee, status, cost) VALUES (?, ?, ?, ?)");
        $stmt->execute([$appointment_id, $assignee, $status, $cost]);
        
        header("Location: job_orders.php?success=1");
        exit();
    } catch (PDOException $e) {
        $error = "Error adding job order: " . $e->getMessage();
    }
}
// Handle Job Order Update
// Handle Job Order Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_job_order'])) {
    $job_id = $_POST['job_id'];
    $assignee = $_POST['assignee'];
    $status = $_POST['status'];
    $cost = $_POST['cost'];

    try {
        $pdo->beginTransaction();

        // 1. Update the Job Order
        $stmt = $pdo->prepare("UPDATE job_orders SET assignee = ?, status = ?, cost = ? WHERE id = ?");
        $stmt->execute([$assignee, $status, $cost, $job_id]);
        
        // 2. Automatically sync the linked Appointment status
        $syncStmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = (SELECT appointment_id FROM job_orders WHERE id = ?)");
        $syncStmt->execute([$status, $job_id]);

        // 3. IF COMPLETED: Generate Invoice for the Customer
        if ($status === 'Completed') {
            // Check if invoice already exists to prevent duplicates
            $checkInvoice = $pdo->prepare("SELECT id FROM invoices WHERE job_order_id = ?");
            $checkInvoice->execute([$job_id]);
            
            if (!$checkInvoice->fetch()) {
                // Get the user_id associated with this job order through the appointment
                $userQuery = $pdo->prepare("
                    SELECT a.user_id 
                    FROM job_orders jo 
                    JOIN appointments a ON jo.appointment_id = a.id 
                    WHERE jo.id = ?
                ");
                $userQuery->execute([$job_id]);
                $userData = $userQuery->fetch();

                if ($userData) {
                    $due_date = date('Y-m-d', strtotime('+7 days')); // Set due date to 7 days from now
                    $invStmt = $pdo->prepare("INSERT INTO invoices (user_id, job_order_id, amount, status, due_date) VALUES (?, ?, ?, 'Pending', ?)");
                    $invStmt->execute([$userData['user_id'], $job_id, $cost, $due_date]);
                }
            }
        }

        $pdo->commit();
        header("Location: job_orders.php?updated=1");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error updating job order: " . $e->getMessage();
    }
}
// --- Fetch Job Order Statistics ---
$statsQuery = "SELECT status, COUNT(*) as count FROM job_orders GROUP BY status";
$statsStmt = $pdo->query($statsQuery);
$statusCounts = $statsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Assign counts to variables, defaulting to 0 if the status doesn't exist yet
$countCompleted = $statusCounts['Completed'] ?? 0;
$countInProgress = $statusCounts['In Progress'] ?? 0;
$countPending = $statusCounts['Pending'] ?? 0;
$countOnHold = $statusCounts['On Hold'] ?? 0;
$countCancelled = $statusCounts['Cancelled'] ?? 0;


// Calculate total for the table header
$totalJobOrders = array_sum($statusCounts);

// Handle Job Order Deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_job_order'])) {
    $job_id = $_POST['job_id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM job_orders WHERE id = ?");
        $stmt->execute([$job_id]);
        
        header("Location: job_orders.php?deleted=1");
        exit();
    } catch (PDOException $e) {
        $error = "Error deleting job order: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Orders - ServiceHub</title>
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
            width: 100vw; /* Forces the body to span the entire screen width */
            overflow: hidden;
        }

        /* Sidebar Styles (Matching your dashboard) */
       .sidebar {
            width: 250px;
            flex-shrink: 0; /* Prevents sidebar from shrinking */
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

        .sidebar-header h2 { margin: 0; font-size: 18px; font-weight: 600; }
        .sidebar-header p { margin: 0; font-size: 11px; color: #8b949e; }

        .nav-links {
            list-style: none;
            padding: 15px 0;
            margin: 0;
            flex-grow: 1;
        }

        .nav-links li { padding: 5px 20px; }
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
        .nav-links a i { width: 20px; margin-right: 10px; font-size: 16px; }
        .nav-links a:hover { background-color: var(--sidebar-hover); color: #fff; }
        
        .nav-links a.active {
            background-color: var(--primary-orange);
            color: #fff;
            font-weight: bold;
        }

        /* Updated User Profile matching screenshot */
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
        .user-info { flex-grow: 1; }
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
        .user-info p { margin: 2px 0 0 0; font-size: 10px; color: #8b949e; }
        .logout-btn { color: #c9d1d9; text-decoration: none; transition: 0.2s; }
        .logout-btn:hover { color: #ff7b72; }
        .app-version { font-size: 9px; color: #4b5563; text-align: left; }

        /* Main Content Area */
       .main-content {
            flex: 1; /* Tells it to take up all remaining flex space */
            width: calc(100% - 250px); /* Strictly sets the width to fill the gap */
            padding: 30px 40px;
            overflow-y: auto;
        }

        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .top-header h1 { margin: 0 0 5px 0; font-size: 22px; color: var(--text-dark); }
        .top-header p { margin: 0; color: var(--text-muted); font-size: 13px; }

        .btn-primary {
            background-color: var(--primary-orange);
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: 0.2s;
        }
        .btn-primary:hover { background-color: #e66a00; }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .stat-card h3 { margin: 0; font-size: 20px; }
        .stat-card p { margin: 5px 0 0 0; font-size: 12px; color: var(--text-muted); font-weight: 500; }

        .text-green { color: #10b981; }
        .text-blue { color: #3b82f6; }
        .text-orange { color: #f59e0b; }
        .text-red { color: #ef4444; }

        /* Search Bar */
        .search-container {
            margin-bottom: 20px;
            position: relative;
            max-width: 300px;
        }
        .search-container input {
            width: 100%;
            padding: 10px 35px;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            background-color: #f3f4f6;
            font-size: 13px;
            box-sizing: border-box;
            outline: none;
        }
        .search-container input:focus { border-color: var(--primary-orange); }
        .search-container .fa-magnifying-glass {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 13px;
        }
        .search-container .fa-microphone {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 13px;
            cursor: pointer;
        }

        /* Table Card */
        .table-card {
            background: #fff;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .table-card-header { margin-bottom: 15px; }
        .table-card-header h2 { margin: 0 0 5px 0; font-size: 16px; display: flex; align-items: center; gap: 8px; }
        .table-card-header h2 i { color: var(--primary-orange); }
        .table-card-header p { margin: 0; font-size: 12px; color: var(--text-muted); }

        .table-wrapper {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            overflow: hidden;
            min-height: 400px; /* To match the large white space in the screenshot */
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
        /* Modal Styles */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; width: 100vw; height: 100vh;
    background-color: rgba(0, 0, 0, 0.4);
    z-index: 1000;
    justify-content: center;
    align-items: center;
}
.modal-box {
    background-color: #fff;
    width: 500px;
    border-radius: 8px;
    padding: 25px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}
.modal-header h2 { margin: 0 0 5px 0; font-size: 18px; color: var(--text-dark); }
.modal-header p { margin: 0 0 20px 0; font-size: 12px; color: var(--text-muted); }
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 25px;
}
.form-group.full-width { grid-column: span 2; }
.form-group label { font-size: 11px; font-weight: 600; color: var(--text-dark); margin-bottom: 5px; }
.form-group input, .form-group select {
    padding: 8px 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 12px;
    outline: none;
    background: #fff;
    width: 100%;
}
.form-group input:focus, .form-group select:focus { border-color: var(--primary-orange); }
.modal-actions {
    display: flex; justify-content: flex-end; gap: 10px;
    border-top: 1px solid var(--border-color); padding-top: 15px;
}
.btn-cancel {
    background: #fff; border: 1px solid var(--border-color); padding: 8px 16px;
    border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600; color: var(--text-dark);
}
.btn-cancel:hover { background: #f3f4f6; }
.btn-save {
    background: var(--primary-orange); border: none; padding: 8px 16px;
    border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600; color: white;
}
.btn-save:hover { background: #e66a00; }
.action-btn { border: none; background: none;cursor: pointer; color: #9ca3af; margin-right: 5px; }
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
            <li><a href="job_orders.php" class="active"><i class="fa-solid fa-clipboard-list"></i> Job Orders</a></li>
            <li><a href="invoices.php"><i class="fa-solid fa-file-invoice-dollar"></i> Invoices</a></li>
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
                <h1>Job Orders</h1>
                <p>Track and manage all service job orders</p>
            </div>
            <button class="btn-primary" onclick="openModal()"><i class="fa-solid fa-plus"></i> Create Job Order</button>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3 class="text-green"><?php echo $countCompleted; ?></h3>
                <p>Completed</p>
            </div>
            <div class="stat-card">
                <h3 class="text-blue"><?php echo $countInProgress; ?></h3>
                <p>In Progress</p>
            </div>
            <div class="stat-card">
                <h3 class="text-orange"><?php echo $countPending; ?></h3>
                <p>Pending</p>
            </div>
            <div class="stat-card">
                <h3 class="text-red"><?php echo $countOnHold; ?></h3>
                <p>On Hold</p>
            </div>
            <div class="stat-card">
                <h3 class="text-black"><?php echo $countCancelled; ?></h3>
                <p>Cancelled</p>
            </div>
        </div>

        <div class="search-container">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" placeholder="Search">
            <i class="fa-solid fa-microphone"></i>
        </div>

        <div class="table-card">
           <div class="table-card-header">
                <h2><i class="fa-solid fa-wrench"></i> All Job Orders</h2>
                <p>Total of <?php echo $totalJobOrders; ?> job orders</p>
            </div>
            
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Job ID</th>
                            <th>Vehicle</th>
                            <th>Service</th>
                            <th>Assignee</th>
                            <th>Status</th>
                            <th>Cost</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                                    <tbody>
                        <?php
                        // Fetch job orders joined with appointments and vehicles
                        $query = "
                            SELECT 
                                jo.id as job_id, 
                                v.make_model, 
                                v.plate_number, 
                                a.service_type, 
                                jo.assignee, 
                                jo.status, 
                                jo.cost 
                            FROM job_orders jo
                            JOIN appointments a ON jo.appointment_id = a.id
                            JOIN vehicles v ON a.vehicle_id = v.id
                            ORDER BY jo.created_at DESC
                        ";
                        $stmt = $pdo->query($query);
                        $job_orders = $stmt->fetchAll();

                        if (count($job_orders) > 0) {
                            foreach ($job_orders as $job) {
                                $statusColor = '#1f2937';
                                if ($job['status'] == 'Completed') $statusColor = '#10b981';
                                if ($job['status'] == 'In Progress') $statusColor = '#3b82f6';
                                if ($job['status'] == 'Pending') $statusColor = '#f59e0b';
                                if ($job['status'] == 'On Hold') $statusColor = '#ef4444';
                                if ($job['status'] == 'Cancelled') $statusColor = '#000000';




                                echo "<tr>";
                                echo "<td>JOB-" . str_pad($job['job_id'], 4, '0', STR_PAD_LEFT) . "</td>";
                                echo "<td>" . htmlspecialchars($job['make_model'] . " (" . $job['plate_number'] . ")") . "</td>";
                                echo "<td>" . htmlspecialchars($job['service_type']) . "</td>";
                                echo "<td>" . htmlspecialchars($job['assignee']) . "</td>";
                                echo "<td style='color: {$statusColor}; font-weight: 600;'>" . htmlspecialchars($job['status']) . "</td>";
                                echo "<td>₱" . number_format($job['cost'], 2) . "</td>";
                               // Escape strings to prevent issues with quotes in JavaScript
                                $escapedAssignee = htmlspecialchars(addslashes($job['assignee']));

                               echo "<td>
                                <button class='action-btn' onclick=\"openEditModal('{$job['job_id']}', '{$escapedAssignee}', '{$job['status']}', '{$job['cost']}')\" title='Edit'>
                                    <i class='fa-solid fa-pen'></i>
                                </button>
                                <button class='action-btn' onclick=\"openDeleteModal('{$job['job_id']}')\" title='Delete'>
                                    <i class='fa-solid fa-trash'></i>
                                </button>
                            </td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='7' style='text-align:center; padding: 20px; color: var(--text-muted);'>No job orders found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
<div class="modal-overlay" id="addJobModal">
    <div class="modal-box">
        <div class="modal-header">
            <h2>Create Job Order</h2>
            <p>Select an appointment to assign a job order.</p>
        </div>
        <form action="" method="POST">
            <input type="hidden" name="create_job_order" value="1">
            
            <div class="form-grid">
                <div class="form-group full-width">
                    <label>Select Appointment</label>
                    <select name="appointment_id" required>
                        <option value="" disabled selected>Select an appointment...</option>
                        <?php
                        // Fetch appointments that DO NOT have a job order yet
                        $appt_query = "
                            SELECT a.id, v.make_model, v.plate_number, a.service_type 
                            FROM appointments a
                            JOIN vehicles v ON a.vehicle_id = v.id
                            LEFT JOIN job_orders jo ON a.id = jo.appointment_id
                            WHERE jo.id IS NULL
                            ORDER BY a.appointment_date ASC
                        ";
                        $appts = $pdo->query($appt_query)->fetchAll();
                        
                        foreach ($appts as $appt) {
                            $displayText = "APT-" . str_pad($appt['id'], 4, '0', STR_PAD_LEFT) . " | " . 
                                           $appt['make_model'] . " (" . $appt['plate_number'] . ") - " . 
                                           $appt['service_type'];
                            echo "<option value='" . $appt['id'] . "'>" . htmlspecialchars($displayText) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group full-width">
                    <label>Assignee (Mechanic)</label>
                    <input type="text" name="assignee" placeholder="Mechanic Name" required>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" required>
                        <option value="Pending">Pending</option>
                        <option value="In Progress">In Progress</option>
                        <option value="On Hold">On Hold</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Estimated Cost (₱)</label>
                    <input type="number" name="cost" placeholder="0.00" step="0.01" required>
                </div>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-save">Create Job</button>
            </div>
        </form>
    </div>
</div>
<div class="modal-overlay" id="editJobModal">
    
    <div class="modal-box">
        <div class="modal-header">
            <h2>Edit Job Order</h2>
            <p>Update the assignee, status, or cost of the job order.</p>
        </div>
        <form action="" method="POST">
            <input type="hidden" name="edit_job_order" value="1">
            <input type="hidden" name="job_id" id="edit_job_id" value="">
            
            <div class="form-grid">
                <div class="form-group full-width">
                    <label>Assignee (Mechanic)</label>
                    <input type="text" name="assignee" id="edit_assignee" placeholder="Mechanic Name" required>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status" required>
                        <option value="Pending">Pending</option>
                        <option value="In Progress">In Progress</option>
                        <option value="On Hold">On Hold</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Estimated Cost (₱)</label>
                    <input type="number" name="cost" id="edit_cost" placeholder="0.00" step="0.01" required>
                </div>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-save">Save Changes</button>
            </div>
        </form>
    </div>
</div>
<div class="modal-overlay" id="deleteJobModal">
    <div class="modal-box" style="width: 400px; text-align: center;">
        <div class="modal-header">
            <h2>Delete Job Order</h2>
            <p>Are you sure you want to delete this job order? This action cannot be undone.</p>
        </div>
        <form action="" method="POST">
            <input type="hidden" name="delete_job_order" value="1">
            <input type="hidden" name="job_id" id="delete_job_id" value="">
            
            <div class="modal-actions" style="justify-content: center; border-top: none; padding-top: 5px;">
                <button type="button" class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
                <button type="submit" class="btn-save" style="background-color: #ef4444;">Yes, Delete</button>
            </div>
        </form>
    </div>
</div>
<script>
    const addModal = document.getElementById('addJobModal');
    const editModal = document.getElementById('editJobModal');
    const deleteModal = document.getElementById('deleteJobModal');

    // --- Create Modal Logic ---
    function openModal() {
        addModal.style.display = 'flex';
    }

    function closeModal() {
        addModal.style.display = 'none';
    }

    // --- Edit Modal Logic ---
    function openEditModal(id, assignee, status, cost) {
        document.getElementById('edit_job_id').value = id;
        document.getElementById('edit_assignee').value = assignee;
        document.getElementById('edit_status').value = status;
        document.getElementById('edit_cost').value = cost;
        
        editModal.style.display = 'flex';
    }

    function closeEditModal() {
        editModal.style.display = 'none';
    }

    // --- Delete Modal Logic ---
    function openDeleteModal(id) {
        document.getElementById('delete_job_id').value = id;
        deleteModal.style.display = 'flex';
    }

    function closeDeleteModal() {
        deleteModal.style.display = 'none';
    }

    // Close modals if the user clicks outside the modal box
    window.onclick = function(event) {
        if (event.target === addModal) {
            closeModal();
        }
        if (event.target === editModal) {
            closeEditModal();
        }
        if (event.target === deleteModal) {
            closeDeleteModal();
        }
    }
</script>
</body>
</html>