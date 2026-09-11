<?php
// =====================================================
// 1. BACKEND LOGIC (PHP & DATABASE)
// =====================================================
include "db.php";

$total_orders = 0;
$in_process_orders = 0;
$ready_delivery_orders = 0;
$completed_orders = 0;

if (isset($conn) && $conn) {
    // Total Orders
    $res = $conn->query("SELECT COUNT(*) AS total FROM orders");
    if ($res) {
        $total_orders = $res->fetch_assoc()['total'];
    }

    // In Process Orders
    $res = $conn->query("SELECT COUNT(*) AS total FROM orders WHERE status = 'In Process'");
    if ($res) {
        $in_process_orders = $res->fetch_assoc()['total'];
    }

    // Ready for Delivery Orders
    $res = $conn->query("SELECT COUNT(*) AS total FROM orders WHERE status = 'Ready for Delivery'");
    if ($res) {
        $ready_delivery_orders = $res->fetch_assoc()['total'];
    }

    // Delivery Done / Completed Orders
    $res = $conn->query("SELECT COUNT(*) AS total FROM orders WHERE status IN ('Complete', 'Delivery Done')");
    if ($res) {
        $completed_orders = $res->fetch_assoc()['total'];
    }
}
?>

<!-- =====================================================
     2. FRONTEND DESIGN (HTML & CSS)
====================================================== -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Order Management System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 25px;
        }
        .stat-card {
            background: #ffffff;
            border: 1px solid #ddd;
            padding: 20px;
            text-align: center;
        }
        .stat-card h4 {
            margin: 0 0 10px 0;
            color: #555;
            font-size: 14px;
            text-transform: uppercase;
        }
        .stat-card .number {
            font-size: 28px;
            font-weight: bold;
            color: #222;
        }
    </style>
</head>
<body>

    <h1>Shanjer Crafts</h1>
    <center><h2>Order Management System</h2></center>

    <div class="menu">
        <a href="admin.php">Admin Panel</a>
        <a href="employee.php">Employee Panel</a>
        <a href="delivery.php">Delivery Agent</a>
        <a href="sales_summary.php">Sales Summary</a>
    </div>

    <!-- Quick Overview Section -->
    <div class="container" style="margin-top: 40px;">
        <div class="box">
            <h3>System Quick Overview</h3>
            <div class="stats-grid">
                <div class="stat-card">
                    <h4>Total Orders</h4>
                    <div class="number"><?php echo $total_orders; ?></div>
                </div>
                <div class="stat-card">
                    <h4>In Process</h4>
                    <div class="number"><?php echo $in_process_orders; ?></div>
                </div>
                <div class="stat-card">
                    <h4>Ready For Delivery</h4>
                    <div class="number"><?php echo $ready_delivery_orders; ?></div>
                </div>
                <div class="stat-card">
                    <h4>Completed / Delivered</h4>
                    <div class="number"><?php echo $completed_orders; ?></div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
