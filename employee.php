<?php

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include "db.php";

$message = "";


/* =====================================================
   UPDATE ORDER STATUS
===================================================== */

if (isset($_POST['update_status'])) {

    $order_id = intval($_POST['order_id']);
    $status   = $_POST['status'];

    /*
        Employee can set:
        In Process
        Complete
        Request to Cancel
    */

    if (
        $status == "In Process" ||
        $status == "Complete" ||
        $status == "Request to Cancel"
    ) {

        if ($status == "Request to Cancel") {

            $sql = "UPDATE orders
                    SET
                        status = 'Request to Cancel',
                        cancel_request = 1
                    WHERE id = ?
                    AND assigned_employee IS NOT NULL";

        } else {

            $sql = "UPDATE orders
                    SET
                        status = ?,
                        cancel_request = 0
                    WHERE id = ?
                    AND assigned_employee IS NOT NULL";

        }


        if ($status == "Request to Cancel") {

            $stmt = $conn->prepare($sql);

            $stmt->bind_param(
                "i",
                $order_id
            );

        } else {

            $stmt = $conn->prepare($sql);

            $stmt->bind_param(
                "si",
                $status,
                $order_id
            );

        }


        if ($stmt->execute()) {

            $message = "Order status updated successfully.";

        } else {

            $message = "Failed to update order status.";

        }


        $stmt->close();

    } else {

        $message = "Invalid status.";

    }

}


/* =====================================================
   SEND CANCEL REQUEST TO ADMIN
===================================================== */

if (isset($_POST['send_cancel_request'])) {

    $order_id = intval($_POST['order_id']);


    $sql = "UPDATE orders
            SET
                cancel_request = 1
            WHERE id = ?
            AND assigned_employee IS NOT NULL
            AND status = 'Request to Cancel'";


    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "i",
        $order_id
    );


    if ($stmt->execute()) {

        if ($stmt->affected_rows > 0) {

            $message =
                "Cancel request sent to Admin successfully.";

        } else {

            $message =
                "Cancel request could not be sent.";

        }

    } else {

        $message =
            "Failed to send cancel request.";

    }


    $stmt->close();

}


/* =====================================================
   SEND COMPLETED ORDER TO DELIVERY
===================================================== */

if (isset($_POST['send_delivery'])) {

    $order_id = intval($_POST['order_id']);


    /*
        Employee does NOT select Delivery Man.

        The order simply goes to
        Delivery Dashboard.
    */

    $sql = "UPDATE orders
            SET
                assigned_employee = NULL,
                assigned_delivery = 1,
                status = 'Ready for Delivery',
                cancel_request = 0
            WHERE id = ?
            AND assigned_employee IS NOT NULL
            AND status = 'Complete'";


    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "i",
        $order_id
    );


    if ($stmt->execute()) {

        if ($stmt->affected_rows > 0) {

            $message =
                "Order sent to Delivery successfully.";

        } else {

            $message =
                "Order must be Complete before sending to Delivery.";

        }

    } else {

        $message =
            "Failed to send order to Delivery.";

    }


    $stmt->close();

}


/* =====================================================
   ADD MATERIAL
===================================================== */

if (isset($_POST['add_material'])) {

    $material_name =
        trim($_POST['material_name']);

    $quantity =
        floatval($_POST['quantity']);

    $unit =
        trim($_POST['unit']);


    if (
        $material_name != "" &&
        $quantity > 0 &&
        $unit != ""
    ) {


        $sql = "INSERT INTO materials
                (
                    material_name,
                    quantity,
                    unit
                )
                VALUES (?, ?, ?)";


        $stmt = $conn->prepare($sql);


        $stmt->bind_param(
            "sds",
            $material_name,
            $quantity,
            $unit
        );


        if ($stmt->execute()) {

            $message =
                "Material added successfully.";

        } else {

            $message =
                "Failed to add material.";

        }


        $stmt->close();

    } else {

        $message =
            "Please enter valid material information.";

    }

}


/* =====================================================
   UPDATE MATERIAL
===================================================== */

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


/* =====================================================
   DELETE MATERIAL
===================================================== */

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


/* =====================================================
   GET EMPLOYEE ORDERS
===================================================== */

$orders = [];


$sql = "SELECT *
        FROM orders
        WHERE assigned_employee IS NOT NULL
        ORDER BY id DESC";


$result = $conn->query($sql);


if ($result) {

    while ($row = $result->fetch_assoc()) {

        $orders[] = $row;

    }

}


/* =====================================================
   GET MATERIALS
===================================================== */

$materials = [];


$sql = "SELECT *
        FROM materials
        ORDER BY id DESC";


$result = $conn->query($sql);


if ($result) {

    while ($row = $result->fetch_assoc()) {

        $materials[] = $row;

    }

}

?>


<!DOCTYPE html>

<html>

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Employee Panel</title>

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

        .btn-edit {
            background: #2980b9 !important;
            color: #fff !important;
            padding: 5px 10px !important;
            font-size: 12px !important;
            border-radius: 3px !important;
            border: none !important;
            cursor: pointer !important;
            margin: 2px !important;
            display: inline-block !important;
            text-decoration: none !important;
        }

        .btn-edit:hover {
            background: #1c5980 !important;
        }

        .btn-delete {
            background: #c0392b !important;
            color: #fff !important;
            padding: 5px 10px !important;
            font-size: 12px !important;
            border-radius: 3px !important;
            border: none !important;
            cursor: pointer !important;
            margin: 2px !important;
            display: inline-block !important;
        }

        .btn-delete:hover {
            background: #962d22 !important;
        }
    </style>

</head>


<body>


<header>

    <h2>Employee Panel</h2>

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
         ORDERS
    ================================================== -->

    <div class="box">

        <h3>Orders</h3>


        <div class="table-wrapper table-scroll-container" id="employee-orders-wrapper">
        <table>

            <tr>

                <th>Order ID</th>

                <th>Customer</th>

                <th>Phone</th>

                <th>Furniture</th>

                <th>Price</th>

                <th>Status</th>

                <th>Design</th>

                <th>Action</th>

            </tr>



            <?php if (count($orders) > 0): ?>


                <?php foreach ($orders as $order): ?>


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



                        <!-- PHONE -->

                        <td>

                            <?php if (!empty($order['customer_phone'])): ?>
                                <?php echo htmlspecialchars($order['customer_phone']); ?>
                            <?php else: ?>
                                <span style="color: #888;">N/A</span>
                            <?php endif; ?>

                        </td>



                        <!-- FURNITURE -->

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $order['furniture_type']
                            );

                            ?>

                        </td>



                        <!-- PRICE -->

                        <td>

                            <?php

                            echo number_format(
                                $order['price'],
                                2
                            );

                            ?>

                        </td>



                        <!-- STATUS -->

                        <td>


                            <form
                                method="POST"
                            >


                                <input
                                    type="hidden"
                                    name="order_id"
                                    value="<?php
                                    echo $order['id'];
                                    ?>"
                                >


                                <select
                                    name="status"
                                >


                                    <option
                                        value="In Process"

                                        <?php

                                        if (
                                            $order['status']
                                            == "In Process"
                                        ) {

                                            echo "selected";

                                        }

                                        ?>
                                    >

                                        In Process

                                    </option>


                                    <option
                                        value="Complete"

                                        <?php

                                        if (
                                            $order['status']
                                            == "Complete"
                                        ) {

                                            echo "selected";

                                        }

                                        ?>
                                    >

                                        Complete

                                    </option>


                                    <option
                                        value="Request to Cancel"

                                        <?php

                                        if (
                                            $order['status']
                                            == "Request to Cancel"
                                        ) {

                                            echo "selected";

                                        }

                                        ?>
                                    >

                                        Request to Cancel

                                    </option>


                                </select>


                                <button
                                    type="submit"
                                    name="update_status"
                                >

                                    Update Status

                                </button>


                            </form>


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

                                    View Design

                                </a>


                            <?php else: ?>


                                No Design


                            <?php endif; ?>


                        </td>



                        <!-- ACTION -->

                        <td>


                            <!-- =================================
                                 REQUEST TO CANCEL
                            ================================== -->

                            <?php

                            if (
                                $order['status']
                                == "Request to Cancel"
                                &&
                                $order['cancel_request']
                                == 0
                            ):

                            ?>


                                <form
                                    method="POST"
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
                                        name="send_cancel_request"
                                    >

                                        Send to Admin

                                    </button>


                                </form>


                            <?php

                            elseif (
                                $order['status']
                                == "Request to Cancel"
                                &&
                                $order['cancel_request']
                                == 1
                            ):

                            ?>


                                <button disabled>

                                    Sent to Admin

                                </button>


                            <?php endif; ?>



                            <!-- =================================
                                 SEND TO DELIVERY
                            ================================== -->

                            <?php

                            if (
                                $order['status']
                                == "Complete"
                            ):

                            ?>


                                <form
                                    method="POST"
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


                        </td>


                    </tr>


                <?php endforeach; ?>


            <?php else: ?>


                <tr>

                    <td colspan="8">

                        No orders assigned.

                    </td>

                </tr>


            <?php endif; ?>


        </table>
        </div>

    </div>



    <!-- =================================================
         ORDER DETAILS
    ================================================== -->

    <?php if (count($orders) > 0): ?>


        <?php

        $latest_order = $orders[0];

        ?>


        <div class="box" id="employee-latest-wrapper">


            <h3>Order Details</h3>


            <p>

                <strong>
                    Order ID:
                </strong>

                <?php

                echo $latest_order['id'];

                ?>

            </p>


            <p>

                <strong>
                    Customer Name:
                </strong>

                <?php

                echo htmlspecialchars(
                    $latest_order['customer_name']
                );

                ?>

            </p>


            <p>

                <strong>
                    Customer Phone:
                </strong>

                <?php

                echo htmlspecialchars(
                    $latest_order['customer_phone'] ?? ''
                );

                ?>

            </p>


            <p>

                <strong>
                    Customer Address:
                </strong>

                <?php

                echo nl2br(
                    htmlspecialchars(
                        $latest_order['customer_address']
                    )
                );

                ?>

            </p>


            <p>

                <strong>
                    Furniture Type:
                </strong>

                <?php

                echo htmlspecialchars(
                    $latest_order['furniture_type']
                );

                ?>

            </p>


            <p>

                <strong>
                    Price:
                </strong>

                <?php

                echo number_format(
                    $latest_order['price'],
                    2
                );

                ?>

            </p>


            <p>

                <strong>
                    Customer Requirement:
                </strong>

                <?php

                echo nl2br(
                    htmlspecialchars(
                        $latest_order['requirement']
                    )
                );

                ?>

            </p>



            <!-- =========================================
                 DESIGN
            ========================================== -->

            <h4>
                Furniture Design
            </h4>


            <?php

            if (
                !empty(
                    $latest_order['design_image']
                )
            ):

            ?>


                <div class="design-box">

                    <img
                        src="uploads/<?php
                        echo htmlspecialchars(
                            $latest_order['design_image']
                        );
                        ?>"
                        alt="Furniture Design"
                    >

                </div>


            <?php else: ?>


                <div class="design-box">

                    <p>
                        No Design Available
                    </p>

                </div>


            <?php endif; ?>



            <p>

                <strong>
                    Current Status:
                </strong>

                <?php

                echo htmlspecialchars(
                    $latest_order['status']
                );

                ?>

            </p>



            <!-- =========================================
                 SEND CANCEL REQUEST
            ========================================== -->

            <?php

            if (
                $latest_order['status']
                == "Request to Cancel"
                &&
                $latest_order['cancel_request']
                == 0
            ):

            ?>


                <form method="POST">


                    <input
                        type="hidden"
                        name="order_id"
                        value="<?php
                        echo $latest_order['id'];
                        ?>"
                    >


                    <button
                        type="submit"
                        name="send_cancel_request"
                    >

                        Send to Admin

                    </button>


                </form>


            <?php endif; ?>



            <!-- =========================================
                 SEND TO DELIVERY
            ========================================== -->

            <?php

            if (
                $latest_order['status']
                == "Complete"
            ):

            ?>


                <form method="POST">


                    <input
                        type="hidden"
                        name="order_id"
                        value="<?php
                        echo $latest_order['id'];
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
        if (document.activeElement && (document.activeElement.tagName === 'SELECT' || document.activeElement.tagName === 'BUTTON' || document.activeElement.tagName === 'INPUT') && document.activeElement.closest('.table-wrapper')) {
            return;
        }
        fetch(window.location.href, { cache: 'no-store' })
            .then(function(res) { return res.text(); })
            .then(function(html) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');

                var newOrders = doc.getElementById('employee-orders-wrapper');
                var curOrders = document.getElementById('employee-orders-wrapper');
                if (newOrders && curOrders && newOrders.innerHTML !== curOrders.innerHTML) {
                    curOrders.innerHTML = newOrders.innerHTML;
                }

                var newLatest = doc.getElementById('employee-latest-wrapper');
                var curLatest = document.getElementById('employee-latest-wrapper');
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