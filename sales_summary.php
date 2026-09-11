<?php
// =====================================================
// 1. BACKEND LOGIC (PHP & DATABASE)
// =====================================================
include "db.php";

// -----------------------------------------------------
// 1.1 ALL-TIME / LIFETIME TOTAL SALES
// -----------------------------------------------------
$lifetime_sales = 0;
$lifetime_completed_orders = 0;
$lifetime_all_orders = 0;

if (isset($conn) && $conn) {
    // Total lifetime sales (Only Complete & Delivery Done)
    $sql_lifetime = "
        SELECT 
            COUNT(*) AS total_completed,
            COALESCE(SUM(price), 0) AS total_sales
        FROM orders
        WHERE status IN ('Complete', 'Delivery Done')
    ";
    $res_lifetime = $conn->query($sql_lifetime);
    if ($res_lifetime) {
        $row = $res_lifetime->fetch_assoc();
        $lifetime_sales = (float)$row['total_sales'];
        $lifetime_completed_orders = (int)$row['total_completed'];
    }

    // Total orders count in system (all statuses)
    $res_all = $conn->query("SELECT COUNT(*) AS total_orders FROM orders");
    if ($res_all) {
        $lifetime_all_orders = (int)$res_all->fetch_assoc()['total_orders'];
    }
}

// -----------------------------------------------------
// 1.2 VIEW MODES: 'search' | 'all' | 'date'
// -----------------------------------------------------
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$is_view_all = isset($_GET['view_all']) && $_GET['view_all'] == '1';

$mode = 'date';
if (!empty($search_query)) {
    $mode = 'search';
} elseif ($is_view_all) {
    $mode = 'all';
} else {
    $mode = 'date';
}

// -----------------------------------------------------
// 1.3 MODE HANDLERS
// -----------------------------------------------------
// --- MODE: SEARCH ---
$search_orders = [];
$search_accounts = [];
$search_total_price = 0;
$search_completed_orders = 0;
$search_total_orders = 0;

if ($mode === 'search' && isset($conn) && $conn) {
    $search_term = "%" . $search_query . "%";
    $stmt = $conn->prepare("
        SELECT * FROM orders 
        WHERE customer_name LIKE ? OR customer_phone LIKE ? 
        ORDER BY id DESC
    ");
    if ($stmt) {
        $stmt->bind_param("ss", $search_term, $search_term);
        $stmt->execute();
        $res_search = $stmt->get_result();
        if ($res_search) {
            while ($ord = $res_search->fetch_assoc()) {
                $search_orders[] = $ord;
                $search_total_price += (float)$ord['price'];
                if (in_array($ord['status'], ['Complete', 'Delivery Done'])) {
                    $search_completed_orders++;
                }

                // Group by unique account key (Name + Phone)
                $acc_key = trim($ord['customer_name']) . '|||' . trim($ord['customer_phone']);
                if (!isset($search_accounts[$acc_key])) {
                    $search_accounts[$acc_key] = [
                        'name' => $ord['customer_name'],
                        'phone' => $ord['customer_phone'],
                        'order_count' => 0,
                        'total_spent' => 0,
                        'completed_count' => 0,
                        'statuses' => [],
                        'order_ids' => []
                    ];
                }
                $search_accounts[$acc_key]['order_count']++;
                $search_accounts[$acc_key]['total_spent'] += (float)$ord['price'];
                if (in_array($ord['status'], ['Complete', 'Delivery Done'])) {
                    $search_accounts[$acc_key]['completed_count']++;
                }
                $st = $ord['status'] ?: 'Pending';
                $search_accounts[$acc_key]['statuses'][$st] = ($search_accounts[$acc_key]['statuses'][$st] ?? 0) + 1;
                $search_accounts[$acc_key]['order_ids'][] = $ord['id'];
            }
        }
        $stmt->close();
    }
    $search_total_orders = count($search_orders);
}

// --- MODE: VIEW ALL ORDERS ---
$all_orders = [];
$all_sales_total = 0;
$all_completed_count = 0;

if ($mode === 'all' && isset($conn) && $conn) {
    $res_all_orders = $conn->query("SELECT * FROM orders ORDER BY id DESC");
    if ($res_all_orders) {
        while ($r = $res_all_orders->fetch_assoc()) {
            $all_orders[] = $r;
            if (in_array($r['status'], ['Complete', 'Delivery Done'])) {
                $all_completed_count++;
                $all_sales_total += (float)$r['price'];
            }
        }
    }
}

// --- MODE: DATE FILTER (Default) ---
$selected_date = isset($_GET['filter_date']) && !empty($_GET['filter_date']) 
    ? trim($_GET['filter_date']) 
    : date("Y-m-d");

if (!preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/", $selected_date)) {
    $selected_date = date("Y-m-d");
}

$date_sales_total = 0;
$date_completed_count = 0;
$date_all_orders_count = 0;
$date_orders = [];

if ($mode === 'date' && isset($conn) && $conn) {
    // Sales on selected date (Complete / Delivery Done)
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) AS total_completed,
            COALESCE(SUM(price), 0) AS total_sales
        FROM orders
        WHERE status IN ('Complete', 'Delivery Done')
        AND DATE(created_at) = ?
    ");
    if ($stmt) {
        $stmt->bind_param("s", $selected_date);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            $row = $res->fetch_assoc();
            $date_completed_count = (int)$row['total_completed'];
            $date_sales_total     = (float)$row['total_sales'];
        }
        $stmt->close();
    }

    // All orders of that selected date (to display in table)
    $stmt_orders = $conn->prepare("
        SELECT *
        FROM orders
        WHERE DATE(created_at) = ?
        ORDER BY id DESC
    ");
    if ($stmt_orders) {
        $stmt_orders->bind_param("s", $selected_date);
        $stmt_orders->execute();
        $res_orders = $stmt_orders->get_result();
        if ($res_orders) {
            while ($r = $res_orders->fetch_assoc()) {
                $date_orders[] = $r;
            }
        }
        $stmt_orders->close();
    }
    $date_all_orders_count = count($date_orders);
}
?>

<!-- =====================================================
     2. FRONTEND DESIGN (HTML & CSS)
====================================================== -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Sales Summary & Orders Search</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .stat-card {
            background: #fafafa;
            border: 1px solid #ddd;
            padding: 18px;
            text-align: center;
            border-radius: 4px;
        }
        .stat-card h4 {
            margin: 0 0 8px 0;
            color: #555;
            font-size: 14px;
        }
        .stat-card .amount {
            font-size: 26px;
            font-weight: bold;
            color: #111;
        }
        .stat-card .amount.green {
            color: #2e7d32;
        }
        .stat-card .amount.blue {
            color: #1565c0;
        }
        .stat-card .amount.purple {
            color: #6a1b9a;
        }
        .stat-card .sub-text {
            font-size: 12px;
            color: #777;
            margin-top: 5px;
        }
        
        /* Control toolbar styling */
        .controls-container {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .filter-row {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .filter-divider {
            color: #ccc;
            font-size: 20px;
            margin: 0 4px;
            user-select: none;
        }
        .btn-action {
            background: #222;
            color: white;
            border: 1px solid #222;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            border-radius: 4px;
            transition: background 0.15s;
        }
        .btn-action:hover {
            background: #444;
        }
        .btn-link {
            display: inline-block;
            text-decoration: none;
            padding: 8px 14px;
            font-size: 14px;
            font-weight: 500;
            border-radius: 4px;
            text-align: center;
            transition: opacity 0.15s;
        }
        .btn-link:hover {
            opacity: 0.9;
        }
        .btn-today {
            background: #666;
            color: white;
        }
        .btn-all-orders {
            background: #1565c0;
            color: white;
            font-weight: bold;
        }
        .btn-all-orders.active-btn {
            background: #0d47a1;
            box-shadow: 0 0 0 2px #fff, 0 0 0 4px #1565c0;
        }
        .btn-search {
            background: #2e7d32;
            color: white;
            border-color: #2e7d32;
        }
        .btn-search:hover {
            background: #1b5e20;
        }
        .btn-reset {
            background: #e0e0e0;
            color: #333;
            font-weight: bold;
        }
        .btn-reset:hover {
            background: #d5d5d5;
        }
        .search-input-box {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-grow: 1;
            max-width: 500px;
        }
        .search-input-box input[type="text"] {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            flex: 1;
        }
        .badge-count {
            display: inline-block;
            background: #e3f2fd;
            color: #1565c0;
            font-weight: bold;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 13px;
        }
        .badge-status {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            background: #eee;
            color: #333;
            margin-right: 4px;
            margin-bottom: 2px;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-complete, .status-delivery-done {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .status-cancelled {
            background: #ffebee;
            color: #c62828;
        }
        .status-pending {
            background: #fff8e1;
            color: #f57f17;
        }
        .status-ready-for-delivery, .status-in-process {
            background: #e3f2fd;
            color: #1565c0;
        }
    </style>
</head>
<body>

<!-- HEADER -->
<header>
    <h2>Sales Summary & Orders Search</h2>
    <div>
        <a href="index.php" style="margin-right: 15px;">Home</a>
        <a href="admin.php">Admin Panel</a>
    </div>
</header>

<div class="container">

    <!-- =================================================
         1. ALL-TIME TOTAL SALES
    ================================================== -->
    <div class="box">
        <h3>All-Time Lifetime Overview</h3>
        <p style="color: #666; margin-top: -10px; font-size: 13px;">
            Total sales and orders summary since system start.
        </p>
        <div class="summary-grid">
            <div class="stat-card">
                <h4>Total Sales</h4>
                <div class="amount green">
                    ৳<?php echo number_format($lifetime_sales, 2); ?>
                </div>
                <div class="sub-text">From completed & delivered orders</div>
            </div>

            <div class="stat-card">
                <h4>Delivered Orders</h4>
                <div class="amount blue">
                    <?php echo $lifetime_completed_orders; ?> orders
                </div>
                <div class="sub-text">Successfully completed sales</div>
            </div>

            <div class="stat-card">
                <h4>Total Orders Placed</h4>
                <div class="amount">
                    <?php echo $lifetime_all_orders; ?> orders
                </div>
                <div class="sub-text">All orders across all statuses</div>
            </div>
        </div>
    </div>


    <!-- =================================================
         2. CONTROLS: DATE FILTER + ALL ORDERS + SEARCH
    ================================================== -->
    <div class="box">
        <h3>Order Filter & Search</h3>
        
        <div class="controls-container">
            <!-- Row 1: Date Filter + View All Orders button -->
            <div class="filter-row">
                <form method="GET" action="sales_summary.php" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin: 0;">
                    <label for="filter_date" style="margin: 0; font-weight: bold; white-space: nowrap;">Filter by Date:</label>
                    <input 
                        type="date" 
                        id="filter_date" 
                        name="filter_date" 
                        style="width: auto; min-width: 170px; padding: 7px 10px;"
                        value="<?php echo ($mode === 'date') ? htmlspecialchars($selected_date) : date('Y-m-d'); ?>" 
                        required
                    >
                    <button type="submit" class="btn-action">View Report</button>
                    <a href="sales_summary.php?filter_date=<?php echo date('Y-m-d'); ?>" class="btn-link btn-today">Today</a>
                </form>

                <div class="filter-divider">|</div>

                <a href="sales_summary.php?view_all=1" class="btn-link btn-all-orders <?php echo ($mode === 'all') ? 'active-btn' : ''; ?>">
                    View All Orders
                </a>
            </div>

            <!-- Row 2: Search by Name or Phone Number -->
            <div style="padding-top: 12px; border-top: 1px dashed #ddd;">
                <form method="GET" action="sales_summary.php" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin: 0;">
                    <label for="search" style="margin: 0; font-weight: bold; white-space: nowrap;">Search Customer / Phone:</label>
                    <div class="search-input-box">
                        <input 
                            type="text" 
                            id="search" 
                            name="search" 
                            placeholder="Enter customer name or phone number..." 
                            value="<?php echo htmlspecialchars($search_query); ?>" 
                            required
                        >
                        <button type="submit" class="btn-action btn-search">Search</button>
                    </div>

                    <?php if ($mode !== 'date' || (isset($_GET['filter_date']) && $_GET['filter_date'] !== date('Y-m-d'))): ?>
                        <a href="sales_summary.php" class="btn-link btn-reset">Reset / Today</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>


    <!-- =================================================
         3. DYNAMIC CONTENT: SEARCH | ALL ORDERS | DATE
    ================================================== -->

    <?php if ($mode === 'search'): ?>
        <!-- =============================================
             VIEW: SEARCH RESULTS
        ============================================== -->
        <div class="box">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-bottom: 15px; gap: 10px;">
                <h3 style="margin: 0; border: none; padding: 0;">
                    Search Results for: <span style="color: #1565c0;">"<?php echo htmlspecialchars($search_query); ?>"</span>
                </h3>
                <span class="badge-count" style="font-size: 14px;">
                    Total <?php echo count($search_accounts); ?> Accounts &nbsp;|&nbsp; Total <?php echo $search_total_orders; ?> Orders
                </span>
            </div>

            <!-- Search Stat Cards -->
            <div class="summary-grid" style="margin-bottom: 25px;">
                <div class="stat-card">
                    <h4>Total Orders</h4>
                    <div class="amount blue">
                        <?php echo $search_total_orders; ?> orders
                    </div>
                    <div class="sub-text">Total orders matching this search</div>
                </div>

                <div class="stat-card">
                    <h4>Linked Accounts</h4>
                    <div class="amount purple">
                        <?php echo count($search_accounts); ?> accounts
                    </div>
                    <div class="sub-text">Accounts associated with this search</div>
                </div>

                <div class="stat-card">
                    <h4>Total Order Amount</h4>
                    <div class="amount green">
                        ৳<?php echo number_format($search_total_price, 2); ?>
                    </div>
                    <div class="sub-text">Total order spending value</div>
                </div>

                <div class="stat-card">
                    <h4>Delivered Orders</h4>
                    <div class="amount" style="color: #2e7d32;">
                        <?php echo $search_completed_orders; ?> orders
                    </div>
                    <div class="sub-text">Successfully delivered orders</div>
                </div>
            </div>

            <!-- 1. ACCOUNTS BREAKDOWN TABLE -->
            <h4 style="margin-top: 25px; margin-bottom: 12px; color: #333;">
                👤 Accounts & Orders Summary (Per Account Breakdown):
            </h4>
            <div style="overflow-x: auto; margin-bottom: 30px;">
                <table>
                    <thead>
                        <tr style="background: #f0f4f8;">
                            <th style="width: 60px;">SL</th>
                            <th>Account / Customer Name</th>
                            <th>Phone Number</th>
                            <th style="text-align: center;">Total Orders</th>
                            <th style="text-align: right;">Total Spent</th>
                            <th>Status Breakdown</th>
                            <th>Order IDs</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($search_accounts) > 0): ?>
                            <?php $acc_i = 1; foreach ($search_accounts as $acc): ?>
                                <tr>
                                    <td><?php echo $acc_i++; ?></td>
                                    <td>
                                        <strong style="color: #1565c0; font-size: 15px;">
                                            <?php echo htmlspecialchars($acc['name']); ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <?php if (!empty($acc['phone'])): ?>
                                            <span style="font-family: monospace; font-size: 14px; font-weight: bold;">
                                                <?php echo htmlspecialchars($acc['phone']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #999;">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="badge-count">
                                            <?php echo $acc['order_count']; ?> orders
                                        </span>
                                    </td>
                                    <td style="text-align: right; font-weight: bold; color: #2e7d32;">
                                        ৳<?php echo number_format($acc['total_spent'], 2); ?>
                                    </td>
                                    <td>
                                        <?php foreach ($acc['statuses'] as $st => $cnt): ?>
                                            <span class="badge-status">
                                                <?php echo htmlspecialchars($st); ?>: <strong><?php echo $cnt; ?></strong>
                                            </span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td style="font-family: monospace; font-size: 13px;">
                                        #<?php echo implode(', #', $acc['order_ids']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #888; padding: 20px;">
                                    No accounts found matching "<?php echo htmlspecialchars($search_query); ?>".
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- 2. MATCHING ORDERS DETAILS TABLE -->
            <h4 style="margin-top: 25px; margin-bottom: 12px; color: #333;">
                📦 Matching Orders Detailed List:
            </h4>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr style="background: #f9f9f9;">
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Furniture</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Order Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($search_orders) > 0): ?>
                            <?php foreach ($search_orders as $ord): ?>
                                <tr>
                                    <td><?php echo $ord['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($ord['customer_name']); ?></strong></td>
                                    <td>
                                        <?php if (!empty($ord['customer_phone'])): ?>
                                            <?php echo htmlspecialchars($ord['customer_phone']); ?>
                                        <?php else: ?>
                                            <span style="color: #999;">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($ord['furniture_type']); ?></td>
                                    <td>৳<?php echo number_format($ord['price'], 2); ?></td>
                                    <td>
                                        <?php 
                                            $st_class = 'status-pending';
                                            if (in_array($ord['status'], ['Complete', 'Delivery Done'])) {
                                                $st_class = 'status-complete';
                                            } elseif ($ord['status'] === 'Cancelled') {
                                                $st_class = 'status-cancelled';
                                            } elseif (in_array($ord['status'], ['Ready for Delivery', 'In Process'])) {
                                                $st_class = 'status-in-process';
                                            }
                                        ?>
                                        <span class="status-badge <?php echo $st_class; ?>">
                                            <?php echo htmlspecialchars($ord['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                            if (!empty($ord['created_at'])) {
                                                echo date("d M, Y - h:i A", strtotime($ord['created_at']));
                                            } else {
                                                echo "-";
                                            }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #888; padding: 20px;">
                                    No matching orders found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($mode === 'all'): ?>
        <!-- =============================================
             VIEW: ALL ORDERS
        ============================================== -->
        <div class="box">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-bottom: 15px; gap: 10px;">
                <h3 style="margin: 0; border: none; padding: 0;">
                    📋 All Orders List
                </h3>
                <span class="badge-count" style="font-size: 14px; background: #e8f5e9; color: #2e7d32;">
                    Total <?php echo count($all_orders); ?> Orders
                </span>
            </div>

            <!-- All Orders Stat Cards -->
            <div class="summary-grid" style="margin-bottom: 25px;">
                <div class="stat-card">
                    <h4>Total Orders</h4>
                    <div class="amount"><?php echo count($all_orders); ?> orders</div>
                    <div class="sub-text">All orders across all statuses</div>
                </div>

                <div class="stat-card">
                    <h4>Delivered Orders</h4>
                    <div class="amount blue"><?php echo $all_completed_count; ?> orders</div>
                    <div class="sub-text">Successfully completed sales</div>
                </div>

                <div class="stat-card">
                    <h4>Total Completed Sales</h4>
                    <div class="amount green">৳<?php echo number_format($all_sales_total, 2); ?></div>
                    <div class="sub-text">Total value from completed orders</div>
                </div>
            </div>

            <h4>All Orders List:</h4>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr style="background: #f9f9f9;">
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Furniture</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Order Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($all_orders) > 0): ?>
                            <?php foreach ($all_orders as $ord): ?>
                                <tr>
                                    <td><?php echo $ord['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($ord['customer_name']); ?></strong></td>
                                    <td>
                                        <?php if (!empty($ord['customer_phone'])): ?>
                                            <?php echo htmlspecialchars($ord['customer_phone']); ?>
                                        <?php else: ?>
                                            <span style="color: #999;">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($ord['furniture_type']); ?></td>
                                    <td>৳<?php echo number_format($ord['price'], 2); ?></td>
                                    <td>
                                        <?php 
                                            $st_class = 'status-pending';
                                            if (in_array($ord['status'], ['Complete', 'Delivery Done'])) {
                                                $st_class = 'status-complete';
                                            } elseif ($ord['status'] === 'Cancelled') {
                                                $st_class = 'status-cancelled';
                                            } elseif (in_array($ord['status'], ['Ready for Delivery', 'In Process'])) {
                                                $st_class = 'status-in-process';
                                            }
                                        ?>
                                        <span class="status-badge <?php echo $st_class; ?>">
                                            <?php echo htmlspecialchars($ord['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                            if (!empty($ord['created_at'])) {
                                                echo date("d M, Y - h:i A", strtotime($ord['created_at']));
                                            } else {
                                                echo "-";
                                            }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #888; padding: 20px;">
                                    No orders found in database.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: ?>
        <!-- =============================================
             VIEW: DATE SUMMARY
        ============================================== -->
        <div class="box">
            <h3>
                Report for Date: 
                <span style="color: #1565c0;">
                    <?php echo date("d M, Y (l)", strtotime($selected_date)); ?>
                </span>
            </h3>

            <div class="summary-grid" style="margin-bottom: 25px;">
                <div class="stat-card">
                    <h4>Total Sales on this Date</h4>
                    <div class="amount green">
                        ৳<?php echo number_format($date_sales_total, 2); ?>
                    </div>
                    <div class="sub-text">From completed & delivered orders</div>
                </div>

                <div class="stat-card">
                    <h4>Delivered Orders</h4>
                    <div class="amount blue">
                        <?php echo $date_completed_count; ?> orders
                    </div>
                    <div class="sub-text">Successfully delivered</div>
                </div>

                <div class="stat-card">
                    <h4>Total Orders Placed</h4>
                    <div class="amount">
                        <?php echo $date_all_orders_count; ?> orders
                    </div>
                    <div class="sub-text">Orders received on this date</div>
                </div>
            </div>

            <!-- Orders Table for Selected Date -->
            <h4>Orders Placed on this Date:</h4>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr style="background: #f9f9f9;">
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Furniture</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($date_orders) > 0): ?>
                            <?php foreach ($date_orders as $ord): ?>
                                <tr>
                                    <td><?php echo $ord['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($ord['customer_name']); ?></strong></td>
                                    <td>
                                        <?php if (!empty($ord['customer_phone'])): ?>
                                            <?php echo htmlspecialchars($ord['customer_phone']); ?>
                                        <?php else: ?>
                                            <span style="color: #999;">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($ord['furniture_type']); ?></td>
                                    <td>৳<?php echo number_format($ord['price'], 2); ?></td>
                                    <td>
                                        <?php 
                                            $st_class = 'status-pending';
                                            if (in_array($ord['status'], ['Complete', 'Delivery Done'])) {
                                                $st_class = 'status-complete';
                                            } elseif ($ord['status'] === 'Cancelled') {
                                                $st_class = 'status-cancelled';
                                            } elseif (in_array($ord['status'], ['Ready for Delivery', 'In Process'])) {
                                                $st_class = 'status-in-process';
                                            }
                                        ?>
                                        <span class="status-badge <?php echo $st_class; ?>">
                                            <?php echo htmlspecialchars($ord['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                            if (!empty($ord['created_at'])) {
                                                echo date("h:i A", strtotime($ord['created_at']));
                                            } else {
                                                echo "-";
                                            }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #888; padding: 20px;">
                                    No orders found for <?php echo date("d M, Y", strtotime($selected_date)); ?>.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    <?php endif; ?>

</div>

</body>
</html>