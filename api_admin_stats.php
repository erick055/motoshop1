<?php
session_start();
require 'includes/db.php';

header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Fetch Stats
$totalAppointments = $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
$activeJobs = $pdo->query("SELECT COUNT(*) FROM job_orders WHERE status IN ('Pending', 'In Progress')")->fetchColumn();
$totalClients = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Customer'")->fetchColumn();

$monthlyRevenue = $pdo->query("
    SELECT SUM(amount) FROM invoices 
    WHERE status = 'Paid' 
    AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
    AND YEAR(created_at) = YEAR(CURRENT_DATE())
")->fetchColumn();

echo json_encode([
    'totalAppointments' => $totalAppointments,
    'activeJobs' => $activeJobs,
    'totalClients' => $totalClients,
    'monthlyRevenue' => $monthlyRevenue ? number_format($monthlyRevenue, 2) : "0.00"
]);
?>