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

// Handle Add New Inventory Item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $item_name = $_POST['item_name'];
    $category = $_POST['category'];
    $quantity = (int)$_POST['quantity'];
    $min_stock = (int)$_POST['min_stock'];
    $price = (float)$_POST['price'];

    // Determine status automatically based on quantity
    $status = 'In Stock';
    if ($quantity == 0) {
        $status = 'Out of Stock';
    } elseif ($quantity <= $min_stock) {
        $status = 'Low Stock';
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO inventory (item_name, category, quantity, min_stock, price, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$item_name, $category, $quantity, $min_stock, $price, $status]);
        header("Location: inventory.php?success=added");
        exit();
    } catch (PDOException $e) {
        $error = "Error adding item: " . $e->getMessage();
    }
}
// Handle Edit Inventory Item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_item'])) {
    $item_id = $_POST['item_id'];
    $item_name = $_POST['item_name'];
    $category = $_POST['category'];
    $quantity = (int)$_POST['quantity'];
    $min_stock = (int)$_POST['min_stock'];
    $price = (float)$_POST['price'];

    // Determine status automatically based on new quantity
    $status = 'In Stock';
    if ($quantity == 0) {
        $status = 'Out of Stock';
    } elseif ($quantity <= $min_stock) {
        $status = 'Low Stock';
    }

    try {
        $stmt = $pdo->prepare("UPDATE inventory SET item_name = ?, category = ?, quantity = ?, min_stock = ?, price = ?, status = ? WHERE id = ?");
        $stmt->execute([$item_name, $category, $quantity, $min_stock, $price, $status, $item_id]);
        header("Location: inventory.php?success=updated");
        exit();
    } catch (PDOException $e) {
        $error = "Error updating item: " . $e->getMessage();
    }
}

// Handle Delete Item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    $item_id = $_POST['item_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
        $stmt->execute([$item_id]);
        header("Location: inventory.php?success=deleted");
        exit();
    } catch (PDOException $e) {
        $error = "Error deleting item: " . $e->getMessage();
    }
}

// Fetch Statistics
$totalProducts = $pdo->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
$lowStockCount = $pdo->query("SELECT COUNT(*) FROM inventory WHERE status = 'Low Stock' OR (quantity <= min_stock AND quantity > 0)")->fetchColumn();
$outOfStockCount = $pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity = 0")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - ServiceHub</title>
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

        /* User Profile in Sidebar */
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
            margin: 2px 0;
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

        /* Main Content Area */
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
            transition: 0.2s;
        }

        .btn-primary:hover {
            background-color: #e66a00;
        }

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

        .stat-card h3 {
            margin: 0;
            font-size: 20px;
        }

        .stat-card p {
            margin: 5px 0 0 0;
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .text-green { color: #10b981; }
        .text-blue { color: #3b82f6; }
        .text-orange { color: #f59e0b; }
        .text-red { color: #ef4444; }

        /* Filters and Search Row */
        .controls-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .filters {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filter-btn {
            background: #fff;
            border: 1px solid var(--border-color);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            color: var(--text-dark);
            cursor: pointer;
            transition: 0.2s;
        }

        .filter-btn:hover, 
        .filter-btn.active {
            border-color: var(--text-dark);
        }

        .search-container {
            position: relative;
            width: 250px;
        }

        .search-container input {
            width: 100%;
            padding: 8px 35px;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            background-color: #f3f4f6;
            font-size: 12px;
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
            font-size: 12px;
        }

        .search-container .fa-microphone {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 12px;
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

        .table-card-header {
            margin-bottom: 15px;
        }

        .table-card-header h2 {
            margin: 0 0 5px 0;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .table-card-header h2 i {
            color: var(--text-muted);
        }

        .table-card-header p {
            margin: 0;
            font-size: 11px;
            color: var(--text-muted);
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

        /* --- MODAL STYLES --- */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
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

        .modal-header h2 {
            margin: 0 0 5px 0;
            font-size: 16px;
            color: var(--text-dark);
        }

        .modal-header p {
            margin: 0 0 20px 0;
            font-size: 12px;
            color: var(--text-muted);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 25px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 5px;
        }

        .form-group input,
        .form-group select {
            padding: 8px 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 12px;
            outline: none;
            background: #fff;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--primary-orange);
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            border-top: 1px solid var(--border-color);
            padding-top: 15px;
        }

        .btn-cancel {
            background: #fff;
            border: 1px solid var(--border-color);
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .btn-cancel:hover {
            background: #f3f4f6;
        }

        .btn-save {
            background: var(--primary-orange);
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            color: white;
        }

        .btn-save:hover {
            background: #e66a00;
        }
        /* Status Badges */
.stock-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}
.stock-badge.In-Stock { background: #ecfdf5; color: #10b981; }
.stock-badge.Low-Stock { background: #fffbeb; color: #f59e0b; }
.stock-badge.Out-of-Stock { background: #fef2f2; color: #ef4444; }

/* Buttons */
.btn-primary {
    background: var(--primary-orange); color: white; border: none;
    padding: 10px 20px; border-radius: 6px; cursor: pointer;
    font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; gap: 8px;
}
.btn-primary:hover { background: #e66a00; }
.action-btn { border: none; background: none; cursor: pointer; color: #9ca3af; font-size: 14px; margin-right: 5px; }
.action-btn:hover { color: var(--primary-orange); }

/* Modal Styles */
.modal-overlay {
    display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
    background-color: rgba(0, 0, 0, 0.4); z-index: 1000; justify-content: center; align-items: center;
}
.modal-box { background-color: #fff; width: 500px; border-radius: 8px; padding: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); }
.modal-header h2 { margin: 0 0 5px 0; font-size: 18px; color: var(--text-dark); }
.modal-header p { margin: 0 0 20px 0; font-size: 12px; color: var(--text-muted); }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 25px; }
.form-group.full-width { grid-column: span 2; }
.form-group label { font-size: 11px; font-weight: 600; color: var(--text-dark); margin-bottom: 5px; display: block; }
.form-group input, .form-group select { width: 100%; padding: 8px 10px; border: 1px solid var(--border-color); border-radius: 4px; font-size: 12px; outline: none; background: #fff; }
.form-group input:focus, .form-group select:focus { border-color: var(--primary-orange); }
.modal-actions { display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid var(--border-color); padding-top: 15px; }
.btn-cancel { background: #fff; border: 1px solid var(--border-color); padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600; }
.btn-save { background: var(--primary-orange); border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600; color: white; }
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
            <li><a href="clients.php"><i class="fa-solid fa-users"></i> Clients</a></li>
            <li><a href="inventory.php" class="active"><i class="fa-solid fa-box"></i> Inventory</a></li>
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
                <h1>Inventory</h1>
                <p>Manage auto parts, fluids, and workshop supplies</p>
            </div>
            <button class="btn-primary" onclick="openModal()"><i class="fa-solid fa-plus"></i> Add New Item</button>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <p>Total Unique Items</p>
                <h3><?php echo $totalProducts; ?></h3>
            </div>
            <div class="stat-card">
                <p>Low Stock Alerts</p>
                <h3 style="color: #f59e0b;"><?php echo $lowStockCount; ?></h3>
            </div>
            <div class="stat-card">
                <p>Out of Stock</p>
                <h3 style="color: #ef4444;"><?php echo $outOfStockCount; ?></h3>
            </div>
        </div>

        <div class="search-container">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" placeholder="Search Parts or Categories...">
        </div>

        <div class="table-card">
            <div class="table-card-header">
                <h2><i class="fa-solid fa-box-open"></i> Inventory Stock</h2>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>SKU / ID</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Stock Level</th>
                            <th>Unit Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->query("SELECT * FROM inventory ORDER BY category ASC, item_name ASC");
                        $inventory_items = $stmt->fetchAll();

                        if (count($inventory_items) > 0) {
                            foreach ($inventory_items as $item) {
                                // Dynamic Status Calculation
                                $status = 'In Stock';
                                $statusClass = 'In-Stock';
                                if ($item['quantity'] == 0) {
                                    $status = 'Out of Stock';
                                    $statusClass = 'Out-of-Stock';
                                } elseif ($item['quantity'] <= $item['min_stock']) {
                                    $status = 'Low Stock';
                                    $statusClass = 'Low-Stock';
                                }
                                
                                echo "<tr>";
                                echo "<td><strong>ITM-" . str_pad($item['id'], 4, '0', STR_PAD_LEFT) . "</strong></td>";
                                echo "<td>" . htmlspecialchars($item['item_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($item['category']) . "</td>";
                                echo "<td><strong>{$item['quantity']}</strong> <span style='font-size: 10px; color: var(--text-muted);'>(Min: {$item['min_stock']})</span></td>";
                                echo "<td>₱" . number_format($item['price'], 2) . "</td>";
                                echo "<td><span class='stock-badge {$statusClass}'>{$status}</span></td>";
                                
                                // Actions
                                echo "<td style='display: flex; gap: 5px;'>
                                        <button type='button' class='action-btn' title='Edit Stock' onclick='openEditModal({$item['id']}, \"" . htmlspecialchars(addslashes($item['item_name'])) . "\", \"{$item['category']}\", {$item['quantity']}, {$item['min_stock']}, {$item['price']})'>
                                            <i class='fa-solid fa-pen'></i>
                                        </button>
                                        <form method='POST' style='display:inline; margin: 0;' onsubmit=\"return confirm('Are you sure you want to delete this item from inventory?');\">
                                            <input type='hidden' name='delete_item' value='1'>
                                            <input type='hidden' name='item_id' value='{$item['id']}'>
                                            <button type='submit' class='action-btn' title='Delete Item' style='color: #ef4444;'>
                                                <i class='fa-solid fa-trash'></i>
                                            </button>
                                        </form>
                                      </td>";
                            }
                        } else {
                            echo "<tr><td colspan='7' style='text-align:center; padding: 20px; color: var(--text-muted);'>No items found in inventory.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    <div class="modal-overlay" id="addItemModal">
            <div class="modal-box">
                <div class="modal-header">
                    <h2>Add New Inventory Item</h2>
                    <p>Register a new part, fluid, or accessory to the system.</p>
                </div>
                <form action="" method="POST">
                    <input type="hidden" name="add_item" value="1">
                    
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>Item Name</label>
                            <input type="text" name="item_name" placeholder="e.g. Fully Synthetic Motor Oil 1L" required>
                        </div>
                        <div class="form-group full-width">
                            <label>Category</label>
                            <select name="category" required>
                                <option value="" disabled selected>Select Category...</option>
                                <option value="Fluids">Fluids & Oils</option>
                                <option value="Filters">Filters</option>
                                <option value="Brakes">Brakes</option>
                                <option value="Ignition">Ignition & Spark</option>
                                <option value="Electrical">Electrical & Battery</option>
                                <option value="Accessories">Accessories</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Initial Stock Quantity</label>
                            <input type="number" name="quantity" placeholder="0" min="0" required>
                        </div>
                        <div class="form-group">
                            <label>Low Stock Warning Level</label>
                            <input type="number" name="min_stock" placeholder="e.g. 5" min="0" required>
                        </div>
                        <div class="form-group full-width">
                            <label>Unit Price (₱)</label>
                            <input type="number" name="price" placeholder="0.00" step="0.01" min="0" required>
                        </div>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn-save">Add Item</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal-overlay" id="editItemModal">
            <div class="modal-box">
                <div class="modal-header">
                    <h2>Edit Inventory Item</h2>
                    <p>Update stock details, pricing, or warning levels.</p>
                </div>
                <form action="" method="POST">
                    <input type="hidden" name="edit_item" value="1">
                    <input type="hidden" name="item_id" id="edit_item_id" value="">
                    
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>Item Name</label>
                            <input type="text" name="item_name" id="edit_item_name" required>
                        </div>
                        <div class="form-group full-width">
                            <label>Category</label>
                            <select name="category" id="edit_category" required>
                                <option value="Fluids">Fluids & Oils</option>
                                <option value="Filters">Filters</option>
                                <option value="Brakes">Brakes</option>
                                <option value="Ignition">Ignition & Spark</option>
                                <option value="Electrical">Electrical & Battery</option>
                                <option value="Accessories">Accessories</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Current Stock Quantity</label>
                            <input type="number" name="quantity" id="edit_quantity" min="0" required>
                        </div>
                        <div class="form-group">
                            <label>Low Stock Warning Level</label>
                            <input type="number" name="min_stock" id="edit_min_stock" min="0" required>
                        </div>
                        <div class="form-group full-width">
                            <label>Unit Price (₱)</label>
                            <input type="number" name="price" id="edit_price" step="0.01" min="0" required>
                        </div>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                        <button type="submit" class="btn-save">Update Item</button>
                    </div>
                </form>
            </div>
        </div>

    </main>

    <script>
        const addModal = document.getElementById('addItemModal');
        const editModal = document.getElementById('editItemModal');

        // Add Modal Functions
        function openModal() { addModal.style.display = 'flex'; }
        function closeModal() { addModal.style.display = 'none'; }

        // Edit Modal Functions
        function openEditModal(id, name, category, qty, min, price) {
            document.getElementById('edit_item_id').value = id;
            document.getElementById('edit_item_name').value = name;
            document.getElementById('edit_category').value = category;
            document.getElementById('edit_quantity').value = qty;
            document.getElementById('edit_min_stock').value = min;
            document.getElementById('edit_price').value = price;
            
            editModal.style.display = 'flex';
        }
        function closeEditModal() { editModal.style.display = 'none'; }

        // Close modals when clicking outside the box
        window.onclick = function(event) { 
            if (event.target === addModal) closeModal(); 
            if (event.target === editModal) closeEditModal();
        }
    </script>
</body>
</html>
</body>
</html>