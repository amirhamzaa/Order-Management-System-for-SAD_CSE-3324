<?php

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include "db.php";

function get_status_badge($status) {
    $colors = [
        'Pending'            => '#f39c12',
        'In Process'         => '#2980b9',
        'Complete'           => '#27ae60',
        'Request to Cancel'  => '#e74c3c',
        'Ready for Delivery' => '#8e44ad',
        'Delivery Done'      => '#16a085',
        'Cancelled'          => '#7f8c8d'
    ];
    $color = $colors[$status] ?? '#333333';
    return '<span class="status-badge" style="display:inline-block; padding: 4px 10px; border-radius: 12px; background: ' . $color . '; color: #ffffff; font-size: 12px; font-weight: bold; white-space: nowrap;">' . htmlspecialchars($status) . '</span>';
}

$message = "";


// =====================================================
// ADD NEW ORDER
// =====================================================

if (isset($_POST['add_order'])) {

    $customer_name = trim($_POST['customer_name']);
    $customer_phone = trim($_POST['customer_phone']);
    $customer_address = trim($_POST['customer_address']);
    $furniture_type = trim($_POST['furniture_type']);
    $requirement = trim($_POST['requirement']);
    $price = floatval($_POST['price']);

    $design_image = NULL;


    // =================================================
    // DESIGN IMAGE UPLOAD
    // =================================================

    if (
        isset($_FILES['design_image']) &&
        $_FILES['design_image']['error'] === 0
    ) {

        $upload_dir = "uploads/";

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $original_name = basename(
            $_FILES['design_image']['name']
        );

        $extension = strtolower(
            pathinfo(
                $original_name,
                PATHINFO_EXTENSION
            )
        );

        $allowed_extensions = [
            "jpg",
            "jpeg",
            "png",
            "webp"
        ];

        if (
            in_array(
                $extension,
                $allowed_extensions
            )
        ) {

            $design_image =
                time() .
                "_" .
                uniqid() .
                "." .
                $extension;

            $target =
                $upload_dir .
                $design_image;

            if (
                !move_uploaded_file(
                    $_FILES['design_image']['tmp_name'],
                    $target
                )
            ) {

                $design_image = NULL;

            }

        }

    }


    // =================================================
    // INSERT ORDER
    // =================================================

    $sql = "
        INSERT INTO orders
        (
            customer_name,
            customer_phone,
            customer_address,
            furniture_type,
            requirement,
            price,
            design_image,
            status,
            cancel_request,
            assigned_employee,
            assigned_delivery
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            'Pending',
            0,
            NULL,
            0
        )
    ";

    $stmt = $conn->prepare($sql);

    if ($stmt) {

        $stmt->bind_param(
            "sssssds",
            $customer_name,
            $customer_phone,
            $customer_address,
            $furniture_type,
            $requirement,
            $price,
            $design_image
        );

        if ($stmt->execute()) {

            $message =
                "Order added successfully.";

        } else {

            $message =
                "Failed to add order.";

        }

        $stmt->close();

    } else {

        $message =
            "Database error.";

    }

}


// =====================================================
// SEND TO EMPLOYEE
// =====================================================

if (isset($_POST['send_employee'])) {

    $order_id =
        intval($_POST['order_id']);

    $sql = "
        UPDATE orders
        SET
            assigned_employee = 1,
            assigned_delivery = 0,
            status = 'In Process',
            cancel_request = 0
        WHERE id = ?
    ";

    $stmt = $conn->prepare($sql);

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $order_id
        );

        if ($stmt->execute()) {

            $message =
                "Order sent to Employee successfully.";

        } else {

            $message =
                "Failed to send order to Employee.";

        }

        $stmt->close();

    }

}


// =====================================================
// SEND DIRECTLY TO DELIVERY
// =====================================================

if (isset($_POST['send_delivery'])) {

    $order_id =
        intval($_POST['order_id']);

    $sql = "
        UPDATE orders
        SET
            status = 'Ready for Delivery',
            assigned_employee = NULL,
            assigned_delivery = 1,
            cancel_request = 0
        WHERE id = ?
    ";

    $stmt = $conn->prepare($sql);

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $order_id
        );

        if ($stmt->execute()) {

            $message =
                "Order sent to Delivery successfully.";

        } else {

            $message =
                "Failed to send order to Delivery.";

        }

        $stmt->close();

    }

}


// =====================================================
// ADMIN CANCEL ORDER
// IMPORTANT:
// Do NOT DELETE.
// Keep record for Sales Summary.
// =====================================================

if (isset($_POST['cancel_order'])) {

    $order_id =
        intval($_POST['order_id']);

    $sql = "
        UPDATE orders
        SET
            status = 'Cancelled',
            cancel_request = 0,
            assigned_employee = NULL,
            assigned_delivery = 0
        WHERE id = ?
    ";

    $stmt = $conn->prepare($sql);

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $order_id
        );

        if ($stmt->execute()) {

            $message =
                "Order cancelled successfully.";

        } else {

            $message =
                "Failed to cancel order.";

        }

        $stmt->close();

    }

}


// =====================================================
// APPROVE EMPLOYEE CANCEL REQUEST
// =====================================================

if (isset($_POST['approve_cancel'])) {

    $order_id =
        intval($_POST['order_id']);

    $sql = "
        UPDATE orders
        SET
            status = 'Cancelled',
            cancel_request = 0,
            assigned_employee = NULL,
            assigned_delivery = 0
        WHERE id = ?
    ";

    $stmt = $conn->prepare($sql);

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $order_id
        );

        if ($stmt->execute()) {

            $message =
                "Cancel request approved.";

        } else {

            $message =
                "Failed to cancel order.";

        }

        $stmt->close();

    }

}


// =====================================================
// REJECT CANCEL REQUEST
// =====================================================

if (isset($_POST['reject_cancel'])) {

    $order_id =
        intval($_POST['order_id']);

    $sql = "
        UPDATE orders
        SET
            cancel_request = 0,
            status = 'In Process'
        WHERE id = ?
    ";

    $stmt = $conn->prepare($sql);

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $order_id
        );

        if ($stmt->execute()) {

            $message =
                "Cancel request rejected.";

        } else {

            $message =
                "Failed to reject request.";

        }

        $stmt->close();

    }

}


// =====================================================
// GET ALL ORDERS
// =====================================================

$orders = [];

$sql = "
    SELECT *
    FROM orders
    ORDER BY id DESC
";

$result = $conn->query($sql);

if ($result) {

    while (
        $row =
        $result->fetch_assoc()
    ) {

        $orders[] = $row;

    }

}


// =====================================================
// ADD MATERIAL
// =====================================================

if (isset($_POST['add_material'])) {

    $material_name = trim($_POST['material_name']);
    $quantity      = floatval($_POST['quantity']);
    $unit          = trim($_POST['unit']);

    if (
        $material_name != "" &&
        $quantity > 0 &&
        $unit != ""
    ) {

        $sql_mat_ins = "INSERT INTO materials
                        (
                            material_name,
                            quantity,
                            unit
                        )
                        VALUES (?, ?, ?)";

        $stmt_mat = $conn->prepare($sql_mat_ins);

        if ($stmt_mat) {

            $stmt_mat->bind_param(
                "sds",
                $material_name,
                $quantity,
                $unit
            );

            if ($stmt_mat->execute()) {

                $message = "Material added successfully.";

            } else {

                $message = "Failed to add material.";

            }

            $stmt_mat->close();

        } else {

            $message = "Database error.";

        }

    } else {

        $message = "Please enter valid material information.";

    }

}


// =====================================================
// UPDATE MATERIAL
// =====================================================

if (isset($_POST['update_material'])) {

    $material_id   = intval($_POST['material_id']);
    $material_name = trim($_POST['material_name']);
    $quantity      = floatval($_POST['quantity']);
    $unit          = trim($_POST['unit']);

    if ($material_id > 0 && $material_name != "" && $quantity >= 0 && $unit != "") {

        $sql = "UPDATE materials
                SET material_name = ?, quantity = ?, unit = ?
                WHERE id = ?";

        $stmt = $conn->prepare($sql);

        if ($stmt) {

            $stmt->bind_param("sdsi", $material_name, $quantity, $unit, $material_id);

            if ($stmt->execute()) {
                $message = "Material updated successfully.";
            } else {
                $message = "Failed to update material.";
            }

            $stmt->close();

        } else {
            $message = "Database error.";
        }

    } else {
        $message = "Please enter valid material details.";
    }

}


// =====================================================
// DELETE MATERIAL
// =====================================================

if (isset($_POST['delete_material'])) {

    $material_id = intval($_POST['material_id']);

    if ($material_id > 0) {

        $sql = "DELETE FROM materials WHERE id = ?";

        $stmt = $conn->prepare($sql);

        if ($stmt) {

            $stmt->bind_param("i", $material_id);

            if ($stmt->execute()) {
                $message = "Material deleted successfully.";
            } else {
                $message = "Failed to delete material.";
            }

            $stmt->close();

        } else {
            $message = "Database error.";
        }

    }

}


// =====================================================
// GET MATERIALS
// =====================================================

$materials = [];

$sql_mat = "SELECT * FROM materials ORDER BY id DESC";

$result_mat = $conn->query($sql_mat);

if ($result_mat) {

    while ($row = $result_mat->fetch_assoc()) {

        $materials[] = $row;

    }

}

?>


<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Admin Panel
    </title>


    <link
        rel="stylesheet"
        href="style.css?v=<?php echo time(); ?>"
    >


    <style>

        /* =========================================
           MODAL (EDIT MATERIAL) PERFECT CENTER
        ========================================= */

        .modal {
            display: none;
            position: fixed;
            z-index: 999999;
            left: 0;
            top: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: #ffffff;
            padding: 25px 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 450px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            position: relative;
            box-sizing: border-box;
            margin: auto;
            border: 1px solid #ddd;
            animation: modalPop 0.2s ease-out;
        }

        @keyframes modalPop {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 19px;
            color: #222;
            font-weight: bold;
        }

        .modal-close {
            font-size: 26px;
            font-weight: bold;
            cursor: pointer;
            color: #888;
            background: transparent !important;
            border: none !important;
            padding: 0 !important;
            margin: 0 !important;
            line-height: 1;
        }

        .modal-close:hover {
            color: #000;
        }

        .modal-content label {
            display: block;
            margin-top: 12px;
            margin-bottom: 5px;
            font-weight: bold;
            font-size: 13px;
            color: #333;
        }

        .modal-content input {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
            margin-bottom: 5px;
        }

        /* =========================================
           ADMIN EXTRA CSS
        ========================================= */

        .message {

            background: #eeeeee;

            border: 1px solid #cccccc;

            padding: 12px;

            margin-bottom: 20px;

            border-radius: 5px;

        }


        .admin-menu {

            display: flex;

            gap: 10px;

            flex-wrap: wrap;

            margin-bottom: 20px;

        }


        .admin-menu a {

            text-decoration: none;

        }


        .admin-menu button {

            cursor: pointer;

        }


        .container {
            width: 96%;
            max-width: 1350px;
        }

        .action-form {

            display: inline-block;

            margin: 2px 0;

        }


        .design-preview {

            max-width: 50px;

            max-height: 50px;

            width: auto;

            height: auto;

            object-fit: contain;

            border: 1px solid #ddd;

            border-radius: 4px;

            display: block;

        }


        .no-design {

            color: #777;

            font-size: 12px;

        }


        .order-details {

            line-height: 1.7;

        }


        .status-text {

            font-weight: bold;

        }


        .table-wrapper {

            width: 100%;

            overflow-x: hidden;

        }

        .table-scroll-container {
            max-height: 580px;
            overflow-y: auto;
            overflow-x: hidden;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .table-scroll-container table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            table-layout: auto;
        }

        .table-scroll-container th,
        .table-scroll-container td {
            padding: 8px 6px;
            font-size: 13px;
            vertical-align: middle;
            box-sizing: border-box;
        }

        .table-scroll-container th {
            position: sticky;
            top: 0;
            background: #eeeeee;
            z-index: 10;
            box-shadow: 0 2px 3px -1px rgba(0, 0, 0, 0.15);
            white-space: nowrap;
            border-top: 1px solid #ddd;
            border-bottom: 2px solid #ccc;
        }

        .table-scroll-container .action-form {
            display: block;
            margin: 2px 0;
        }

        .table-scroll-container .action-form button {
            width: 100%;
            min-width: 100px;
            max-width: 115px;
            padding: 5px 6px;
            font-size: 11px;
            margin: 2px 0;
            white-space: nowrap;
            border-radius: 3px;
            box-sizing: border-box;
        }

        .table-scroll-container::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .table-scroll-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .table-scroll-container::-webkit-scrollbar-thumb {
            background: #bbb;
            border-radius: 4px;
        }

        .table-scroll-container::-webkit-scrollbar-thumb:hover {
            background: #888;
        }


        table {

            width: 100%;

        }


        textarea {

            width: 100%;

            min-height: 90px;

            resize: vertical;

            box-sizing: border-box;

        }


        input[type="text"],
        input[type="number"],
        input[type="file"] {

            width: 100%;

            box-sizing: border-box;

        }


        .design-large {

            max-width: 100%;

            width: auto;

            height: auto;

            max-height: 400px;

            object-fit: contain;

        }


    </style>

</head>


<body>


<!-- =====================================================
     HEADER
====================================================== -->

<header>

    <h2>
        Admin Panel
    </h2>


    <a href="index.php">
        Home
    </a>

</header>



<div class="container">


    <!-- =================================================
         MESSAGE
    ================================================== -->

    <?php if ($message != ""): ?>

        <div class="message">

            <?php
            echo htmlspecialchars(
                $message
            );
            ?>

        </div>

    <?php endif; ?>



    <!-- =================================================
         ADMIN MENU
    ================================================== -->

    <div class="admin-menu">


        <a href="sales_summary.php">

            <button type="button">

                Sales Summary

            </button>

        </a>


    </div>



    <!-- =================================================
         ADD NEW ORDER
    ================================================== -->

    <div class="box">


        <h3>
            Add New Order
        </h3>


        <form
            method="POST"
            enctype="multipart/form-data"
        >


            <label>
                Customer Name
            </label>


            <input
                type="text"
                name="customer_name"
                placeholder="Enter customer name"
                required
            >


            <br><br>


            <label>
                Customer Phone
            </label>


            <input
                type="text"
                name="customer_phone"
                placeholder="Enter customer phone number"
                required
            >


            <br><br>


            <label>
                Customer Address
            </label>


            <textarea
                name="customer_address"
                placeholder="Enter customer address"
                required
            ></textarea>


            <br>


            <label>
                Furniture Type
            </label>


            <input
                type="text"
                name="furniture_type"
                placeholder="Example: Custom Sofa"
                required
            >


            <br><br>


            <label>
                Customer Requirement
            </label>


            <textarea
                name="requirement"
                placeholder="Enter customer requirement"
                required
            ></textarea>


            <br>


            <label>
                Price
            </label>


            <input
                type="number"
                name="price"
                step="0.01"
                min="0"
                placeholder="Enter price"
                required
            >


            <br><br>


            <label>
                Furniture Design
            </label>


            <input
                type="file"
                name="design_image"
                accept=".jpg,.jpeg,.png,.webp"
            >


            <br><br>


            <button
                type="submit"
                name="add_order"
            >

                Add New Order

            </button>


        </form>


    </div>



    <!-- =================================================
         CANCEL REQUESTS
    ================================================== -->

    <div class="box">


        <h3>
            Cancel Requests
        </h3>


        <div class="table-wrapper" id="cancel-requests-wrapper">


            <table>


                <tr>

                    <th>
                        Order ID
                    </th>

                    <th>
                        Customer
                    </th>

                    <th>
                        Phone
                    </th>

                    <th>
                        Furniture
                    </th>

                    <th>
                        Price
                    </th>

                    <th>
                        Action
                    </th>

                </tr>


                <?php

                $cancel_found = false;

                foreach (
                    $orders
                    as $order
                ):

                    if (
                        $order['cancel_request'] == 1
                        ||
                        $order['status'] == 'Request to Cancel'
                    ):

                        $cancel_found = true;

                ?>


                    <tr>


                        <td>

                            <?php
                            echo $order['id'];
                            ?>

                        </td>


                        <td>

                            <strong>
                                <?php
                                echo htmlspecialchars(
                                    $order['customer_name']
                                );
                                ?>
                            </strong>

                        </td>


                        <td>

                            <?php if (!empty($order['customer_phone'])): ?>
                                <?php echo htmlspecialchars($order['customer_phone']); ?>
                            <?php else: ?>
                                <span style="color: #888;">N/A</span>
                            <?php endif; ?>

                        </td>


                        <td>

                            <?php
                            echo htmlspecialchars(
                                $order['furniture_type']
                            );
                            ?>

                        </td>


                        <td>

                            ৳
                            <?php
                            echo number_format(
                                $order['price'],
                                2
                            );
                            ?>

                        </td>


                        <td>


                            <!-- APPROVE -->

                            <form
                                method="POST"
                                class="action-form"
                            >

                                <input
                                    type="hidden"
                                    name="order_id"
                                    value="<?php
                                    echo $order['id'];
                                    ?>"
                                >


                                <button
                                    type="submit"
                                    name="approve_cancel"
                                    onclick="return confirm(
                                        'Are you sure you want to cancel this order?'
                                    );"
                                >

                                    Remove Order

                                </button>

                            </form>



                            <!-- REJECT -->

                            <form
                                method="POST"
                                class="action-form"
                            >

                                <input
                                    type="hidden"
                                    name="order_id"
                                    value="<?php
                                    echo $order['id'];
                                    ?>"
                                >


                                <button
                                    type="submit"
                                    name="reject_cancel"
                                >

                                    Reject Request

                                </button>

                            </form>


                        </td>


                    </tr>


                <?php

                    endif;

                endforeach;


                if (!$cancel_found):

                ?>


                    <tr>

                        <td colspan="6">

                            No Cancel Requests

                        </td>

                    </tr>


                <?php endif; ?>


            </table>


        </div>


    </div>



    <!-- =================================================
         ALL ORDERS
    ================================================== -->

    <div class="box">


        <h3>
            All Orders
        </h3>


        <div class="table-wrapper table-scroll-container" id="all-orders-wrapper">


            <table>


                <tr>

                    <th>
                        Order ID
                    </th>

                    <th>
                        Customer
                    </th>

                    <th>
                        Phone
                    </th>

                    <th>
                        Address
                    </th>

                    <th>
                        Furniture
                    </th>

                    <th>
                        Requirement
                    </th>

                    <th>
                        Price
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Design
                    </th>

                    <th>
                        Action
                    </th>

                </tr>



                <?php if (
                    count($orders) > 0
                ): ?>


                    <?php foreach (
                        $orders
                        as $order
                    ): ?>


                        <tr>


                            <!-- ORDER ID -->

                            <td>

                                <?php
                                echo $order['id'];
                                ?>

                            </td>



                            <!-- CUSTOMER -->

                            <td>

                                <strong>
                                    <?php
                                    echo htmlspecialchars(
                                        $order['customer_name']
                                    );
                                    ?>
                                </strong>

                            </td>



                            <!-- PHONE NUMBER -->

                            <td>

                                <?php if (!empty($order['customer_phone'])): ?>
                                     <?php echo htmlspecialchars($order['customer_phone']); ?>
                                <?php else: ?>
                                    <span style="color: #888;">N/A</span>
                                <?php endif; ?>

                            </td>



                            <!-- ADDRESS -->

                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $order['customer_address']
                                );
                                ?>

                            </td>



                            <!-- FURNITURE -->

                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $order['furniture_type']
                                );
                                ?>

                            </td>



                            <!-- REQUIREMENT -->

                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $order['requirement']
                                );
                                ?>

                            </td>



                            <!-- PRICE -->

                            <td>

                                ৳
                                <?php
                                echo number_format(
                                    $order['price'],
                                    2
                                );
                                ?>

                            </td>



                            <!-- STATUS -->

                            <td>

                                <?php
                                echo get_status_badge($order['status']);
                                ?>

                                <?php

                                if (
                                    $order['cancel_request']
                                    == 1
                                    ||
                                    $order['status']
                                    == 'Request to Cancel'
                                ):

                                ?>

                                    <br>

                                    <small style="color: #d9534f; font-weight: bold; display: inline-block; margin-top: 4px;">
                                        ⚠️ Cancel Requested
                                    </small>

                                <?php endif; ?>

                            </td>



                            <!-- DESIGN -->

                            <td>


                                <?php

                                if (
                                    !empty(
                                        $order['design_image']
                                    )
                                ):

                                ?>


                                    <a
                                        href="uploads/<?php
                                        echo htmlspecialchars(
                                            $order['design_image']
                                        );
                                        ?>"
                                        target="_blank"
                                    >


                                        <img
                                            src="uploads/<?php
                                            echo htmlspecialchars(
                                                $order['design_image']
                                            );
                                            ?>"
                                            alt="Furniture Design"
                                            class="design-preview"
                                        >


                                    </a>


                                <?php else: ?>


                                    <span class="no-design">

                                        No Design

                                    </span>


                                <?php endif; ?>


                            </td>



                            <!-- ACTION -->

                            <td>


                                <!-- ==================================
                                     SEND TO EMPLOYEE
                                =================================== -->

                                <?php

                                if (
                                    $order['status']
                                    == "Pending"
                                ):

                                ?>


                                    <form
                                        method="POST"
                                        class="action-form"
                                    >


                                        <input
                                            type="hidden"
                                            name="order_id"
                                            value="<?php
                                            echo $order['id'];
                                            ?>"
                                        >


                                        <button
                                            type="submit"
                                            name="send_employee"
                                        >

                                            Send to Employee

                                        </button>


                                    </form>


                                <?php endif; ?>



                                <!-- ==================================
                                     DIRECT SEND TO DELIVERY
                                =================================== -->

                                <?php

                                if (
                                    $order['status']
                                    != "Delivery Done"
                                    &&
                                    $order['status']
                                    != "Cancelled"
                                ):

                                ?>


                                    <form
                                        method="POST"
                                        class="action-form"
                                    >


                                        <input
                                            type="hidden"
                                            name="order_id"
                                            value="<?php
                                            echo $order['id'];
                                            ?>"
                                        >


                                        <button
                                            type="submit"
                                            name="send_delivery"
                                        >

                                            Send to Delivery

                                        </button>


                                    </form>


                                <?php endif; ?>



                                <!-- ==================================
                                     CANCEL ORDER
                                =================================== -->

                                <?php

                                if (
                                    $order['status']
                                    != "Delivery Done"
                                    &&
                                    $order['status']
                                    != "Cancelled"
                                ):

                                ?>


                                    <form
                                        method="POST"
                                        class="action-form"
                                    >


                                        <input
                                            type="hidden"
                                            name="order_id"
                                            value="<?php
                                            echo $order['id'];
                                            ?>"
                                        >


                                        <button
                                            type="submit"
                                            name="cancel_order"
                                            onclick="return confirm(
                                                'Are you sure you want to cancel this order?'
                                            );"
                                        >

                                            Cancel Order

                                        </button>


                                    </form>


                                <?php endif; ?>


                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php else: ?>


                    <tr>

                        <td colspan="10">

                            No Orders Found

                        </td>

                    </tr>


                <?php endif; ?>


            </table>


        </div>


    </div>



    <!-- =================================================
         SELECTED ORDER DETAILS
    ================================================== -->

    <?php

    /*
        Show the latest order details.
    */

    if (
        count($orders) > 0
    ):

        $latest =
            $orders[0];

    ?>


        <div class="box" id="latest-order-wrapper">


            <h3>
                Latest Order Details
            </h3>


            <div class="order-details">


                <p>

                    <strong>
                        Order ID:
                    </strong>

                    <?php
                    echo $latest['id'];
                    ?>

                </p>


                <p>

                    <strong>
                        Customer Name:
                    </strong>

                    <?php
                    echo htmlspecialchars(
                        $latest['customer_name']
                    );
                    ?>

                </p>


                <p>

                    <strong>
                        Customer Phone:
                    </strong>

                    <?php
                    echo htmlspecialchars(
                        $latest['customer_phone'] ?? ''
                    );
                    ?>

                </p>


                <p>

                    <strong>
                        Customer Address:
                    </strong>

                    <?php
                    echo htmlspecialchars(
                        $latest['customer_address']
                    );
                    ?>

                </p>


                <p>

                    <strong>
                        Furniture Type:
                    </strong>

                    <?php
                    echo htmlspecialchars(
                        $latest['furniture_type']
                    );
                    ?>

                </p>


                <p>

                    <strong>
                        Requirement:
                    </strong>

                    <?php
                    echo nl2br(
                        htmlspecialchars(
                            $latest['requirement']
                        )
                    );
                    ?>

                </p>


                <p>

                    <strong>
                        Price:
                    </strong>

                    ৳
                    <?php
                    echo number_format(
                        $latest['price'],
                        2
                    );
                    ?>

                </p>


                <p>

                    <strong>
                        Status:
                    </strong>

                    <?php
                    echo htmlspecialchars(
                        $latest['status']
                    );
                    ?>

                </p>



                <!-- DESIGN -->

                <h4>
                    Furniture Design
                </h4>


                <?php

                if (
                    !empty(
                        $latest['design_image']
                    )
                ):

                ?>


                    <img
                        src="uploads/<?php
                        echo htmlspecialchars(
                            $latest['design_image']
                        );
                        ?>"
                        alt="Furniture Design"
                        class="design-large"
                    >


                <?php else: ?>


                    <p>
                        No Design Available
                    </p>


                <?php endif; ?>


            </div>


        </div>


    <?php endif; ?>



    <!-- =================================================
         MATERIAL ENTRY
    ================================================== -->

    <div class="box">


        <h3>
            Material Entry
        </h3>


        <form method="POST">


            <label>
                Material Name
            </label>


            <input
                type="text"
                name="material_name"
                placeholder="Enter material name"
                required
            >


            <label>
                Quantity
            </label>


            <input
                type="number"
                name="quantity"
                step="0.01"
                min="0"
                placeholder="Enter quantity"
                required
            >


            <label>
                Unit
            </label>


            <input
                type="text"
                name="unit"
                placeholder="kg / piece / meter"
                required
            >


            <button
                type="submit"
                name="add_material"
            >

                Add Material

            </button>


        </form>


    </div>



    <!-- =================================================
         AVAILABLE MATERIALS
    ================================================== -->

    <div class="box">


        <h3>
            Available Materials
        </h3>


        <div class="table-wrapper">


            <table>


                <tr>

                    <th>
                        Material
                    </th>

                    <th>
                        Quantity
                    </th>

                    <th>
                        Unit
                    </th>

                    <th style="width: 150px; text-align: center;">
                        Action
                    </th>

                </tr>



                <?php if (count($materials) > 0): ?>


                    <?php foreach (
                        $materials
                        as $material
                    ): ?>


                        <tr>


                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $material['material_name']
                                );
                                ?>

                            </td>


                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $material['quantity']
                                );
                                ?>

                            </td>


                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $material['unit']
                                );
                                ?>

                            </td>


                            <td style="text-align: center; white-space: nowrap;">

                                <button type="button"
                                    class="btn-edit"
                                    onclick="openEditModal(<?php echo $material['id']; ?>, '<?php echo htmlspecialchars(addslashes($material['material_name']), ENT_QUOTES); ?>', <?php echo floatval($material['quantity']); ?>, '<?php echo htmlspecialchars(addslashes($material['unit']), ENT_QUOTES); ?>')">
                                    ✏️ Edit
                                </button>

                                <form method="POST" style="display:inline-block; margin: 0;" onsubmit="return confirm('Are you sure you want to delete <?php echo htmlspecialchars(addslashes($material['material_name']), ENT_QUOTES); ?>?');">
                                    <input type="hidden" name="material_id" value="<?php echo $material['id']; ?>">
                                    <button type="submit" name="delete_material" class="btn-delete">
                                        🗑️ Delete
                                    </button>
                                </form>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php else: ?>


                    <tr>

                        <td colspan="4">

                            No materials available.

                        </td>

                    </tr>


                <?php endif; ?>


            </table>


        </div>


    </div>


</div>


<!-- =================================================
     EDIT MATERIAL MODAL
================================================== -->
<div id="editMaterialModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Material</h3>
            <button type="button" class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="material_id" id="edit_material_id">
            
            <label for="edit_material_name">Material Name</label>
            <input type="text" name="material_name" id="edit_material_name" required>
            
            <label for="edit_quantity">Quantity</label>
            <input type="number" name="quantity" id="edit_quantity" step="0.01" min="0" required>
            
            <label for="edit_unit">Unit</label>
            <input type="text" name="unit" id="edit_unit" required>
            
            <div style="margin-top: 20px; display: flex; gap: 10px;">
                <button type="submit" name="update_material" style="background: #27ae60; flex: 1; margin-top: 0; padding: 10px; font-weight: bold; border-radius: 4px;">Save Changes</button>
                <button type="button" onclick="closeEditModal()" style="background: #7f8c8d; flex: 1; margin-top: 0; padding: 10px; border-radius: 4px;">Cancel</button>
            </div>
        </form>
    </div>
</div>


<script>
function openEditModal(id, name, quantity, unit) {
    document.getElementById('edit_material_id').value = id;
    document.getElementById('edit_material_name').value = name;
    document.getElementById('edit_quantity').value = quantity;
    document.getElementById('edit_unit').value = unit;
    var modal = document.getElementById('editMaterialModal');
    modal.style.display = 'flex';
}

function closeEditModal() {
    var modal = document.getElementById('editMaterialModal');
    modal.style.display = 'none';
}

window.addEventListener('click', function(event) {
    var modal = document.getElementById('editMaterialModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
});

// Real-time live sync: automatically updates orders without page reload
(function() {
    setInterval(function() {
        // Pause live sync if modal is open or user is actively typing
        var modal = document.getElementById('editMaterialModal');
        if (modal && modal.style.display === 'flex') {
            return;
        }
        if (document.activeElement && (document.activeElement.tagName === 'INPUT' || document.activeElement.tagName === 'SELECT' || document.activeElement.tagName === 'BUTTON') && document.activeElement.closest('.table-wrapper')) {
            return;
        }
        fetch(window.location.href, { cache: 'no-store' })
            .then(function(res) { return res.text(); })
            .then(function(html) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');

                var newCancel = doc.getElementById('cancel-requests-wrapper');
                var curCancel = document.getElementById('cancel-requests-wrapper');
                if (newCancel && curCancel && newCancel.innerHTML !== curCancel.innerHTML) {
                    curCancel.innerHTML = newCancel.innerHTML;
                }

                var newAll = doc.getElementById('all-orders-wrapper');
                var curAll = document.getElementById('all-orders-wrapper');
                if (newAll && curAll && newAll.innerHTML !== curAll.innerHTML) {
                    curAll.innerHTML = newAll.innerHTML;
                }

                var newLatest = doc.getElementById('latest-order-wrapper');
                var curLatest = document.getElementById('latest-order-wrapper');
                if (newLatest && curLatest && newLatest.innerHTML !== curLatest.innerHTML) {
                    curLatest.innerHTML = newLatest.innerHTML;
                }
            })
            .catch(function(e) {});
    }, 2500);
})();
</script>

</body>

</html>