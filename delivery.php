<?php

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include "db.php";

$message = "";


/* =========================================
   MARK ORDER AS DELIVERY DONE
========================================= */

if (isset($_POST['delivery_done'])) {

    $order_id = intval($_POST['order_id']);

    $sql = "UPDATE orders
            SET status = 'Delivery Done'
            WHERE id = ?
            AND assigned_delivery = 1
            AND status = 'Ready for Delivery'";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param("i", $order_id);

    if ($stmt->execute()) {

        if ($stmt->affected_rows > 0) {

            $message = "Order marked as Delivery Done successfully.";

        } else {

            $message = "This order cannot be marked as delivered.";

        }

    } else {

        $message = "Failed to update delivery status.";

    }

    $stmt->close();
}


/* =========================================
   GET DELIVERY ORDERS
========================================= */

$orders = [];

$sql = "SELECT *
        FROM orders
        WHERE assigned_delivery = 1
        ORDER BY id DESC";

$result = $conn->query($sql);

if ($result) {

    while ($row = $result->fetch_assoc()) {

        $orders[] = $row;

    }

}

?>


<!DOCTYPE html>

<html>

<head>

    <meta charset="UTF-8">

    <title>Delivery Panel</title>

    <link rel="stylesheet" href="style.css">

</head>


<body>


<header>

    <h2>Delivery Panel</h2>

    <a href="index.php">Home</a>

</header>



<div class="container">


    <!-- =========================================
         MESSAGE
    ========================================== -->

    <?php if ($message != ""): ?>

        <div class="message">

            <?php

            echo htmlspecialchars($message);

            ?>

        </div>

    <?php endif; ?>



    <!-- =========================================
         DELIVERY ORDERS
    ========================================== -->

    <div class="box">

        <h3>Delivery Orders</h3>


        <div class="table-wrapper table-scroll-container" id="delivery-orders-wrapper">
        <table>

            <tr>

                <th>Order ID</th>

                <th>Customer</th>

                <th>Phone</th>

                <th>Address</th>

                <th>Furniture</th>

                <th>Status</th>

                <th>Design</th>

                <th>Action</th>

            </tr>


            <?php if (count($orders) > 0): ?>


                <?php foreach ($orders as $order): ?>


                    <tr>


                        <!-- Order ID -->

                        <td>

                            <?php

                            echo $order['id'];

                            ?>

                        </td>



                        <!-- Customer -->

                        <td>

                            <strong>
                                <?php
                                echo htmlspecialchars(
                                    $order['customer_name']
                                );
                                ?>
                            </strong>

                        </td>



                        <!-- Phone -->

                        <td>

                            <?php if (!empty($order['customer_phone'])): ?>
                                <?php echo htmlspecialchars($order['customer_phone']); ?>
                            <?php else: ?>
                                <span style="color: #888;">N/A</span>
                            <?php endif; ?>

                        </td>



                        <!-- Address -->

                        <td>

                            <?php

                            echo nl2br(
                                htmlspecialchars(
                                    $order['customer_address']
                                )
                            );

                            ?>

                        </td>



                        <!-- Furniture -->

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $order['furniture_type']
                            );

                            ?>

                        </td>



                        <!-- Status -->

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $order['status']
                            );

                            ?>

                        </td>



                        <!-- Design -->

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



                        <!-- Action -->

                        <td>


                            <?php

                            if (
                                $order['status']
                                == 'Ready for Delivery'
                            ):

                            ?>


                                <form
                                    method="POST"
                                    style="display:inline;"
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
                                        name="delivery_done"
                                    >

                                        Delivery Done

                                    </button>


                                </form>


                            <?php else: ?>


                                <strong>

                                    Delivered

                                </strong>


                            <?php endif; ?>


                        </td>


                    </tr>


                <?php endforeach; ?>


            <?php else: ?>


                <tr>

                    <td colspan="8">

                        No delivery orders available.

                    </td>

                </tr>


            <?php endif; ?>


        </table>
        </div>

    </div>



    <!-- =========================================
         ORDER DETAILS
    ========================================== -->

    <?php if (count($orders) > 0): ?>


        <?php

        $latest_order = $orders[0];

        ?>


        <div class="box" id="delivery-latest-wrapper">


            <h3>Order Details</h3>


            <p>

                <strong>Order ID:</strong>

                <?php

                echo $latest_order['id'];

                ?>

            </p>


            <p>

                <strong>Customer Name:</strong>

                <?php

                echo htmlspecialchars(
                    $latest_order['customer_name']
                );

                ?>

            </p>


            <p>

                <strong>Customer Phone:</strong>

                <?php

                echo htmlspecialchars(
                    $latest_order['customer_phone'] ?? ''
                );

                ?>

            </p>


            <p>

                <strong>Customer Address:</strong>

                <?php

                echo nl2br(
                    htmlspecialchars(
                        $latest_order['customer_address']
                    )
                );

                ?>

            </p>


            <p>

                <strong>Furniture Type:</strong>

                <?php

                echo htmlspecialchars(
                    $latest_order['furniture_type']
                );

                ?>

            </p>


            <p>

                <strong>Customer Requirement:</strong>

                <?php

                echo nl2br(
                    htmlspecialchars(
                        $latest_order['requirement']
                    )
                );

                ?>

            </p>



            <!-- =================================
                 DESIGN
            ================================== -->

            <h4>Furniture Design</h4>


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

                <strong>Current Status:</strong>

                <?php

                echo htmlspecialchars(
                    $latest_order['status']
                );

                ?>

            </p>



            <!-- DELIVERY BUTTON -->

            <?php

            if (
                $latest_order['status']
                == 'Ready for Delivery'
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
                        name="delivery_done"
                    >

                        Delivery Done

                    </button>


                </form>


            <?php else: ?>


                <button disabled>

                    Delivery Completed

                </button>


            <?php endif; ?>


        </div>


    <?php endif; ?>


</div>


<script>
// Real-time live sync: automatically updates orders without page reload
(function() {
    setInterval(function() {
        if (document.activeElement && document.activeElement.tagName === 'BUTTON' && document.activeElement.closest('.table-wrapper')) {
            return;
        }
        fetch(window.location.href, { cache: 'no-store' })
            .then(function(res) { return res.text(); })
            .then(function(html) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');

                var newOrders = doc.getElementById('delivery-orders-wrapper');
                var curOrders = document.getElementById('delivery-orders-wrapper');
                if (newOrders && curOrders && newOrders.innerHTML !== curOrders.innerHTML) {
                    curOrders.innerHTML = newOrders.innerHTML;
                }

                var newLatest = doc.getElementById('delivery-latest-wrapper');
                var curLatest = document.getElementById('delivery-latest-wrapper');
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