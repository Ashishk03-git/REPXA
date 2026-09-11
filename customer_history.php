<?php

require_once "auth.php";
require_once "db.php";

/*
=========================================================
REPXA - CUSTOMER HISTORY
=========================================================
OWNER
- Can view every customer.
- Can edit customer details.
- Can view all repair and bill history.

STAFF
- Can view customer details only when the customer has
  at least one repair assigned to the logged-in staff.
- Can view only the repairs assigned to them.
- Can view only bills belonging to their assigned repairs.
- Cannot edit customer details.
=========================================================
*/

$customer_id = intval($_GET["id"] ?? 0);

if ($customer_id <= 0) {
    die("Invalid customer ID");
}

$currentUserId = intval($_SESSION["user_id"] ?? 0);
$currentUserRole = strtolower(trim($_SESSION["role"] ?? ""));
$isOwner = ($currentUserRole === "owner");

$message = "";


/* =====================================================
   FIND CURRENT STAFF
===================================================== */

$staffId = 0;

if (!$isOwner) {

    $stmt = $conn->prepare("
        SELECT staff_id
        FROM staff
        WHERE user_id = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param("i", $currentUserId);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $staffId = intval($result->fetch_assoc()["staff_id"]);
        }

        $stmt->close();
    }

    /* Fallback for older sessions/staff records. */
    if ($staffId <= 0) {

        $currentUsername =
            trim($_SESSION["username"] ?? "");

        if ($currentUsername !== "") {

            $stmt = $conn->prepare("
                SELECT staff_id
                FROM staff
                WHERE agent_id = ?
                LIMIT 1
            ");

            if ($stmt) {

                $stmt->bind_param(
                    "s",
                    $currentUsername
                );

                $stmt->execute();

                $result =
                    $stmt->get_result();

                if (
                    $result &&
                    $result->num_rows === 1
                ) {
                    $staffId =
                        intval(
                            $result->fetch_assoc()["staff_id"]
                        );
                }

                $stmt->close();
            }
        }
    }

    /*
     A non-owner must have a valid staff record.
    */
    if ($staffId <= 0) {

        header(
            "Location: agent_dashboard.php"
        );

        exit;
    }
}


/* =====================================================
   STAFF CUSTOMER ACCESS CHECK
===================================================== */

if (!$isOwner) {

    $accessStmt = $conn->prepare("
        SELECT r.repair_id
        FROM repairs r
        WHERE
            r.customer_id = ?
            AND r.assigned_staff_id = ?
        LIMIT 1
    ");

    if (!$accessStmt) {
        die("Unable to verify customer access.");
    }

    $accessStmt->bind_param(
        "ii",
        $customer_id,
        $staffId
    );

    $accessStmt->execute();

    $accessResult =
        $accessStmt->get_result();

    $hasAccess =
        $accessResult &&
        $accessResult->num_rows > 0;

    $accessStmt->close();

    if (!$hasAccess) {

        http_response_code(403);

        die("
            <div style='font-family:Arial,sans-serif;padding:40px;text-align:center'>
                <h2>Access Denied</h2>
                <p>You are not assigned to any repair for this customer.</p>
                <p><a href='agent_dashboard.php'>Back to Dashboard</a></p>
            </div>
        ");
    }
}


/* =====================================================
   EDIT REPAIR
   OWNER:
   - Can edit repair fields and assignment.
   STAFF:
   - Can edit only their own assigned repair.
   - Cannot change assignment.
===================================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["action"] ?? "") === "update_repair"
) {

    $repair_id = intval($_POST["repair_id"] ?? 0);
    $device_type = trim($_POST["device_type"] ?? "");
    $brand = trim($_POST["brand"] ?? "");
    $model = trim($_POST["model"] ?? "");
    $issue = trim($_POST["issue"] ?? "");
    $status = trim($_POST["status"] ?? "");

    $allowedStatuses = [
        "Pending",
        "In Progress",
        "Completed",
        "Delivered"
    ];

    if (
        $repair_id <= 0 ||
        $device_type === "" ||
        $issue === "" ||
        !in_array($status, $allowedStatuses, true)
    ) {

        $message = "Please enter valid repair details.";

    } else {

        if ($isOwner) {

            $assigned_staff_id =
                intval($_POST["assigned_staff_id"] ?? 0);

            if ($assigned_staff_id > 0) {

                $updateRepair = $conn->prepare("
                    UPDATE repairs
                    SET
                        device_type = ?,
                        brand = ?,
                        model = ?,
                        issue = ?,
                        status = ?,
                        assigned_staff_id = ?
                    WHERE
                        repair_id = ?
                        AND customer_id = ?
                ");

                if ($updateRepair) {
                    $updateRepair->bind_param(
                        "sssssiii",
                        $device_type,
                        $brand,
                        $model,
                        $issue,
                        $status,
                        $assigned_staff_id,
                        $repair_id,
                        $customer_id
                    );
                }

            } else {

                $updateRepair = $conn->prepare("
                    UPDATE repairs
                    SET
                        device_type = ?,
                        brand = ?,
                        model = ?,
                        issue = ?,
                        status = ?,
                        assigned_staff_id = NULL
                    WHERE
                        repair_id = ?
                        AND customer_id = ?
                ");

                if ($updateRepair) {
                    $updateRepair->bind_param(
                        "sssssii",
                        $device_type,
                        $brand,
                        $model,
                        $issue,
                        $status,
                        $repair_id,
                        $customer_id
                    );
                }
            }

        } else {

            /*
             * IMPORTANT:
             * Staff can update only a repair that is assigned
             * to their own staff_id.
             */
            $updateRepair = $conn->prepare("
                UPDATE repairs
                SET
                    device_type = ?,
                    brand = ?,
                    model = ?,
                    issue = ?,
                    status = ?
                WHERE
                    repair_id = ?
                    AND customer_id = ?
                    AND assigned_staff_id = ?
            ");

            if ($updateRepair) {
                $updateRepair->bind_param(
                    "sssssiii",
                    $device_type,
                    $brand,
                    $model,
                    $issue,
                    $status,
                    $repair_id,
                    $customer_id,
                    $staffId
                );
            }
        }

        if (!isset($updateRepair) || !$updateRepair) {

            $message = "Unable to prepare repair update.";

        } elseif ($updateRepair->execute()) {

            $updateRepair->close();

            header(
                "Location: customer_history.php?id=" .
                $customer_id .
                "&repair_updated=1"
            );

            exit;

        } else {

            $message = "Unable to update repair.";
            $updateRepair->close();
        }
    }
}

if (isset($_GET["repair_updated"])) {
    $message = "Repair updated successfully!";
}


/* =====================================================
   EDIT CUSTOMER - OWNER ONLY
===================================================== */

if (
    $isOwner &&
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["action"] ?? "") === "update_customer"
) {

    $name =
        trim($_POST["name"] ?? "");

    $phone =
        trim($_POST["phone"] ?? "");

    $email =
        trim($_POST["email"] ?? "");

    $address =
        trim($_POST["address"] ?? "");

    if (
        $name === "" ||
        $phone === ""
    ) {

        $message =
            "Name and phone are required.";

    } else {

        $updateStmt =
            $conn->prepare("
                UPDATE customers
                SET
                    name = ?,
                    phone = ?,
                    email = ?,
                    address = ?
                WHERE customer_id = ?
            ");

        if (!$updateStmt) {

            $message =
                "Unable to prepare customer update.";

        } else {

            $updateStmt->bind_param(
                "ssssi",
                $name,
                $phone,
                $email,
                $address,
                $customer_id
            );

            if ($updateStmt->execute()) {

                $updateStmt->close();

                header(
                    "Location: customer_history.php?id="
                    . $customer_id
                    . "&updated=1"
                );

                exit;

            } else {

                $message =
                    "Unable to update customer.";

                $updateStmt->close();
            }
        }
    }
}


if (isset($_GET["updated"])) {

    $message =
        "Customer updated successfully!";
}


/* =====================================================
   CUSTOMER DETAILS
===================================================== */

$stmt =
    $conn->prepare("
        SELECT
            customer_id,
            name,
            phone,
            email,
            address
        FROM customers
        WHERE customer_id = ?
    ");

$stmt->bind_param(
    "i",
    $customer_id
);

$stmt->execute();

$customer =
    $stmt
        ->get_result()
        ->fetch_assoc();

$stmt->close();

if (!$customer) {

    die("Customer not found");
}


/* =====================================================
   REPAIR HISTORY
===================================================== */

if ($isOwner) {

    $stmt =
        $conn->prepare("
            SELECT
                r.repair_id,
                r.device_type,
                r.brand,
                r.model,
                r.issue,
                r.status,
                r.created_at,
                s.name AS assigned_staff_name,
                s.agent_id AS assigned_staff_code,
                s.role AS assigned_staff_role
            FROM repairs r
            LEFT JOIN staff s
                ON r.assigned_staff_id = s.staff_id
            WHERE r.customer_id = ?
            ORDER BY r.repair_id DESC
        ");

    $stmt->bind_param(
        "i",
        $customer_id
    );

} else {

    $stmt =
        $conn->prepare("
            SELECT
                r.repair_id,
                r.device_type,
                r.brand,
                r.model,
                r.issue,
                r.status,
                r.created_at,
                s.name AS assigned_staff_name,
                s.agent_id AS assigned_staff_code,
                s.role AS assigned_staff_role
            FROM repairs r
            LEFT JOIN staff s
                ON r.assigned_staff_id = s.staff_id
            WHERE
                r.customer_id = ?
                AND r.assigned_staff_id = ?
            ORDER BY r.repair_id DESC
        ");

    $stmt->bind_param(
        "ii",
        $customer_id,
        $staffId
    );
}

$stmt->execute();

$repairs =
    $stmt->get_result();

$stmt->close();


/* =====================================================
   BILL HISTORY
===================================================== */

if ($isOwner) {

    $stmt =
        $conn->prepare("
            SELECT
                b.bill_id,
                b.repair_id,
                b.amount,
                b.parts_cost,
                b.labor_cost,
                b.discount,
                b.total,
                b.payment_method,
                b.payment_status,
                b.bill_date
            FROM bills b
            WHERE b.customer_id = ?
            ORDER BY b.bill_id DESC
        ");

    $stmt->bind_param(
        "i",
        $customer_id
    );

} else {

    /*
     Staff sees only bills for repairs assigned to them.
    */
    $stmt =
        $conn->prepare("
            SELECT
                b.bill_id,
                b.repair_id,
                b.amount,
                b.parts_cost,
                b.labor_cost,
                b.discount,
                b.total,
                b.payment_method,
                b.payment_status,
                b.bill_date
            FROM bills b
            INNER JOIN repairs r
                ON b.repair_id = r.repair_id
            WHERE
                b.customer_id = ?
                AND r.assigned_staff_id = ?
            ORDER BY b.bill_id DESC
        ");

    $stmt->bind_param(
        "ii",
        $customer_id,
        $staffId
    );
}

$stmt->execute();

$bills =
    $stmt->get_result();

$stmt->close();

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0">

<title>Customer History - REPXA</title>

<script src="https://cdn.tailwindcss.com"></script>

<link
href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"
rel="stylesheet">

</head>


<body class="bg-slate-50 text-slate-900">


<!-- =====================================================
     CUSTOMER HISTORY HEADER
===================================================== -->

<header
class="bg-white border-b sticky top-0 z-50">

<div
class="max-w-5xl mx-auto px-5 h-16
flex items-center">


<a
href="customers.php"
class="w-10 h-10 rounded-full
flex items-center justify-center
hover:bg-slate-100"
title="Back to Customers">

<span
class="material-symbols-outlined
text-slate-700">

arrow_back

</span>

</a>


<div class="ml-3">

<h1
class="text-lg font-bold text-blue-700">

Customer History

</h1>


<p
class="text-xs text-slate-500">

Customer service & payment history

</p>

</div>


</div>

</header>



<main
class="max-w-5xl mx-auto px-5 py-7">


<!-- =====================================================
     MESSAGE
===================================================== -->

<?php if ($message !== ""): ?>

<div
class="mb-6 p-4 rounded-xl
<?= str_contains(
    $message,
    "successfully"
)
    ? "bg-green-100 text-green-700 border border-green-200"
    : "bg-red-100 text-red-700 border border-red-200"
?>">


<?= htmlspecialchars(
    $message
) ?>


</div>

<?php endif; ?>



<!-- =====================================================
     CUSTOMER CARD
===================================================== -->

<div
class="bg-white border border-slate-200
rounded-2xl p-6 mb-7">


<div
class="flex items-center
justify-between gap-4">


<div
class="flex items-center gap-4">


<div
class="w-14 h-14 rounded-2xl
bg-blue-50 flex items-center
justify-center">


<span
class="material-symbols-outlined
text-blue-700 text-3xl">

person

</span>


</div>


<div>


<h2
class="text-xl font-bold">

<?= htmlspecialchars(
    $customer["name"]
) ?>

</h2>


<p
class="text-sm text-slate-500">

<?= htmlspecialchars(
    $customer["phone"]
) ?>

</p>


<?php if (
    !empty(
        $customer["email"]
    )
): ?>


<p
class="text-xs text-slate-400 mt-1">

<?= htmlspecialchars(
    $customer["email"]
) ?>

</p>


<?php endif; ?>


</div>


</div>


<?php if ($isOwner): ?>

<a
href="customer_history.php?id=<?= $customer_id ?>&edit=1"
class="shrink-0 flex items-center
gap-2 bg-blue-50 text-blue-700
hover:bg-blue-100 px-4 py-2.5
rounded-xl text-sm font-semibold">


<span
class="material-symbols-outlined
text-lg">

edit

</span>


<span class="hidden sm:inline">

Edit Customer

</span>


</a>

<?php endif; ?>


</div>



<?php if (
    !empty(
        $customer["address"]
    )
): ?>


<div
class="mt-5 pt-4
border-t border-slate-100
flex gap-2">


<span
class="material-symbols-outlined
text-slate-400">

location_on

</span>


<p
class="text-sm text-slate-500">

<?= htmlspecialchars(
    $customer["address"]
) ?>

</p>


</div>


<?php endif; ?>


</div>



<!-- =====================================================
     EDIT CUSTOMER
===================================================== -->

<?php if (
    $isOwner &&
    isset($_GET["edit"])
    &&
    $_GET["edit"] === "1"
): ?>


<div
class="bg-white border
border-slate-200 rounded-2xl
p-6 mb-7">


<div
class="flex items-center
justify-between gap-4 mb-5">


<div>


<p
class="text-xs font-semibold
tracking-wider text-slate-500">

CUSTOMER SETTINGS

</p>


<h2
class="text-xl font-bold mt-1">

Edit Customer

</h2>


<p
class="text-sm text-slate-500 mt-1">

Update customer details without
affecting repair or billing history.

</p>


</div>


<a
href="customer_history.php?id=<?= $customer_id ?>"
class="w-10 h-10 rounded-full
flex items-center justify-center
hover:bg-slate-100">


<span
class="material-symbols-outlined">

close

</span>


</a>


</div>



<form
method="POST"
class="space-y-5">


<input
type="hidden"
name="action"
value="update_customer">



<!-- NAME -->

<div>


<label
class="block text-sm
font-semibold mb-2">

Customer Name *

</label>


<input
type="text"
name="name"
value="<?= htmlspecialchars(
    $customer["name"] ?? ""
) ?>"
required
class="w-full border
border-slate-300 rounded-xl
px-4 py-3 outline-none
focus:border-blue-600">

</div>



<!-- PHONE -->

<div>


<label
class="block text-sm
font-semibold mb-2">

Phone Number *

</label>


<input
type="text"
name="phone"
value="<?= htmlspecialchars(
    $customer["phone"] ?? ""
) ?>"
required
class="w-full border
border-slate-300 rounded-xl
px-4 py-3 outline-none
focus:border-blue-600">

</div>



<!-- EMAIL -->

<div>


<label
class="block text-sm
font-semibold mb-2">

Email

</label>


<input
type="email"
name="email"
value="<?= htmlspecialchars(
    $customer["email"] ?? ""
) ?>"
class="w-full border
border-slate-300 rounded-xl
px-4 py-3 outline-none
focus:border-blue-600">

</div>



<!-- ADDRESS -->

<div>


<label
class="block text-sm
font-semibold mb-2">

Address

</label>


<textarea
name="address"
rows="3"
class="w-full border
border-slate-300 rounded-xl
px-4 py-3 outline-none
focus:border-blue-600"><?= htmlspecialchars(
    $customer["address"] ?? ""
) ?></textarea>


</div>



<!-- BUTTONS -->

<div
class="flex gap-3 pt-1">


<a
href="customer_history.php?id=<?= $customer_id ?>"
class="flex-1 text-center
border border-slate-300
rounded-xl py-3.5
font-semibold">

Cancel

</a>


<button
type="submit"
class="flex-1 bg-blue-700
hover:bg-blue-800
text-white rounded-xl
py-3.5 font-semibold
flex items-center
justify-center gap-2">


<span
class="material-symbols-outlined">

save

</span>


Save Changes

</button>


</div>


</form>


</div>


<?php endif; ?>



<!-- =====================================================
     REPAIR HISTORY
===================================================== -->

<div class="mb-8">


<div
class="flex items-center
justify-between mb-4">


<div>


<p
class="text-xs font-semibold
text-slate-500 tracking-wider">

SERVICE HISTORY

</p>


<h2
class="text-xl font-bold">

Repair Jobs

</h2>


</div>


<span
class="bg-blue-50 text-blue-700
px-3 py-1 rounded-full
text-sm font-semibold">

<?= $repairs->num_rows ?>

</span>


</div>



<div class="space-y-4">


<?php if (
    $repairs->num_rows > 0
): ?>


<?php while (
    $repair =
    $repairs->fetch_assoc()
): ?>


<?php

$status =
    $repair["status"]
    ?? "Pending";


if (
    $status === "Completed"
    ||
    $status === "Delivered"
) {

    $statusClass =
        "bg-green-100 text-green-700";

} elseif (
    $status === "In Progress"
) {

    $statusClass =
        "bg-blue-100 text-blue-700";

} else {

    $statusClass =
        "bg-orange-100 text-orange-700";
}

?>


<div
class="bg-white border
border-slate-200
rounded-2xl p-5">


<div
class="flex justify-between
gap-4">


<div>


<p
class="text-xs text-slate-400">

Repair
#<?= intval(
    $repair["repair_id"]
) ?>

</p>


<h3
class="font-bold text-lg mt-1">

<?= htmlspecialchars(
    $repair["device_type"]
    ?? "-"
) ?>

</h3>


<p
class="text-sm text-slate-500">

<?= htmlspecialchars(
    $repair["brand"]
    ?? "-"
) ?>


<?php if (
    !empty(
        $repair["model"]
    )
): ?>

• <?= htmlspecialchars(
    $repair["model"]
) ?>

<?php endif; ?>


</p>


</div>


<span
class="<?= $statusClass ?>
px-3 py-1 rounded-full
text-xs font-semibold
h-fit">


<?= htmlspecialchars(
    $status
) ?>


</span>


</div>



<!-- DETAILS -->

<div
class="grid md:grid-cols-2
gap-4 mt-5">


<div
class="bg-slate-50
rounded-xl p-4">


<p
class="text-xs text-slate-400">

Reported Issue

</p>


<p
class="font-medium mt-1">

<?= htmlspecialchars(
    $repair["issue"]
    ?? "-"
) ?>


</p>


</div>


<div
class="bg-slate-50
rounded-xl p-4">


<p
class="text-xs text-slate-400">

Assigned To

</p>


<p
class="font-medium mt-1">

<?= htmlspecialchars(
    $repair["assigned_staff_name"]
    ?? "Not Assigned"
) ?>

</p>

<?php if (!empty($repair["assigned_staff_code"])): ?>

<p class="text-xs text-slate-500 mt-1">

<?= htmlspecialchars($repair["assigned_staff_code"]) ?>

<?php if (!empty($repair["assigned_staff_role"])): ?>
    • <?= htmlspecialchars($repair["assigned_staff_role"]) ?>
<?php endif; ?>

</p>

<?php endif; ?>


</div>


</div>



<?php
$canEditThisRepair =
    $isOwner ||
    intval($repair["assigned_staff_id"] ?? 0) === $staffId;
?>

<?php if ($canEditThisRepair): ?>

<details class="mt-5 border border-blue-100 rounded-2xl overflow-hidden">

    <summary
        class="cursor-pointer list-none px-4 py-3
        bg-blue-50 text-blue-700
        font-semibold text-sm flex items-center
        justify-between">

        <span class="flex items-center gap-2">
            <span class="material-symbols-outlined text-lg">
                edit
            </span>
            Edit Repair
        </span>

        <span class="material-symbols-outlined text-lg">
            expand_more
        </span>

    </summary>

    <div class="p-5 bg-white border-t border-blue-100">

        <form method="POST" class="space-y-4">

            <input
                type="hidden"
                name="action"
                value="update_repair">

            <input
                type="hidden"
                name="repair_id"
                value="<?= intval($repair["repair_id"]) ?>">

            <div>
                <label class="block text-sm font-semibold mb-2">
                    Device Type *
                </label>

                <input
                    type="text"
                    name="device_type"
                    required
                    value="<?= htmlspecialchars(
                        $repair["device_type"] ?? ""
                    ) ?>"
                    class="w-full border border-slate-300
                    rounded-xl px-4 py-3 outline-none
                    focus:border-blue-600">
            </div>

            <div class="grid md:grid-cols-2 gap-4">

                <div>
                    <label class="block text-sm font-semibold mb-2">
                        Brand
                    </label>

                    <input
                        type="text"
                        name="brand"
                        value="<?= htmlspecialchars(
                            $repair["brand"] ?? ""
                        ) ?>"
                        class="w-full border border-slate-300
                        rounded-xl px-4 py-3 outline-none
                        focus:border-blue-600">
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-2">
                        Model
                    </label>

                    <input
                        type="text"
                        name="model"
                        value="<?= htmlspecialchars(
                            $repair["model"] ?? ""
                        ) ?>"
                        class="w-full border border-slate-300
                        rounded-xl px-4 py-3 outline-none
                        focus:border-blue-600">
                </div>

            </div>

            <div>
                <label class="block text-sm font-semibold mb-2">
                    Reported Issue *
                </label>

                <textarea
                    name="issue"
                    rows="4"
                    required
                    class="w-full border border-slate-300
                    rounded-xl px-4 py-3 outline-none
                    focus:border-blue-600"><?= htmlspecialchars(
                        $repair["issue"] ?? ""
                    ) ?></textarea>
            </div>

            <div>
                <label class="block text-sm font-semibold mb-2">
                    Status *
                </label>

                <select
                    name="status"
                    required
                    class="w-full border border-slate-300
                    rounded-xl px-4 py-3 outline-none
                    focus:border-blue-600">

                    <?php
                    $repairStatuses = [
                        "Pending",
                        "In Progress",
                        "Completed",
                        "Delivered"
                    ];
                    ?>

                    <?php foreach ($repairStatuses as $repairStatus): ?>

                        <option
                            value="<?= htmlspecialchars($repairStatus) ?>"
                            <?= ($repair["status"] ?? "") === $repairStatus
                                ? "selected"
                                : "" ?>>

                            <?= htmlspecialchars($repairStatus) ?>

                        </option>

                    <?php endforeach; ?>

                </select>
            </div>

            <?php if ($isOwner): ?>

                <div>
                    <label class="block text-sm font-semibold mb-2">
                        Assigned Staff
                    </label>

                    <select
                        name="assigned_staff_id"
                        class="w-full border border-slate-300
                        rounded-xl px-4 py-3 outline-none
                        focus:border-blue-600">

                        <option value="0">
                            Not Assigned
                        </option>

                        <?php
                        $staffOptions = $conn->query("
                            SELECT
                                staff_id,
                                name,
                                agent_id,
                                role
                            FROM staff
                            WHERE
                                LOWER(COALESCE(status, 'Active')) = 'active'
                            ORDER BY name ASC
                        ");
                        ?>

                        <?php if ($staffOptions): ?>

                            <?php while (
                                $staffOption =
                                $staffOptions->fetch_assoc()
                            ): ?>

                                <option
                                    value="<?= intval(
                                        $staffOption["staff_id"]
                                    ) ?>"
                                    <?= intval(
                                        $repair["assigned_staff_id"] ?? 0
                                    ) === intval(
                                        $staffOption["staff_id"]
                                    )
                                        ? "selected"
                                        : "" ?>>

                                    <?= htmlspecialchars(
                                        $staffOption["name"]
                                    ) ?>
                                    —
                                    <?= htmlspecialchars(
                                        $staffOption["agent_id"]
                                    ) ?>
                                    —
                                    <?= htmlspecialchars(
                                        $staffOption["role"]
                                    ) ?>

                                </option>

                            <?php endwhile; ?>

                        <?php endif; ?>

                    </select>

                    <p class="text-xs text-slate-500 mt-2">
                        Only the Owner can change the assigned staff.
                    </p>

                </div>

            <?php else: ?>

                <div
                    class="bg-slate-50 border border-slate-200
                    rounded-xl p-3 text-xs text-slate-500">

                    <strong>Assigned To:</strong>
                    <?= htmlspecialchars(
                        $repair["assigned_staff_name"] ?? "You"
                    ) ?>

                    <?php if (!empty($repair["assigned_staff_code"])): ?>
                        — <?= htmlspecialchars(
                            $repair["assigned_staff_code"]
                        ) ?>
                    <?php endif; ?>

                    <br>
                    Assignment cannot be changed by staff.

                </div>

            <?php endif; ?>

            <div class="flex gap-3 pt-1">

                <button
                    type="submit"
                    class="flex-1 bg-blue-700 hover:bg-blue-800
                    text-white rounded-xl py-3
                    font-semibold flex items-center
                    justify-center gap-2">

                    <span class="material-symbols-outlined">
                        save
                    </span>

                    Save Repair

                </button>

            </div>

        </form>

    </div>

</details>

<?php endif; ?>


<div
class="text-xs text-slate-400
mt-4">


<?php if (
    !empty(
        $repair["created_at"]
    )
): ?>


<?= htmlspecialchars(
    $repair["created_at"]
) ?>


<?php endif; ?>


</div>


</div>


<?php endwhile; ?>


<?php else: ?>


<div
class="bg-white border
border-slate-200
rounded-2xl p-8 text-center">


<span
class="material-symbols-outlined
text-5xl text-slate-300">

build

</span>


<p
class="font-semibold mt-3">

No repair history

</p>


</div>


<?php endif; ?>


</div>


</div>



<!-- =====================================================
     BILL HISTORY
===================================================== -->

<div>


<div
class="flex items-center
justify-between mb-4">


<div>


<p
class="text-xs font-semibold
text-slate-500 tracking-wider">

PAYMENT HISTORY

</p>


<h2
class="text-xl font-bold">

Bills

</h2>


</div>


<span
class="bg-green-50 text-green-700
px-3 py-1 rounded-full
text-sm font-semibold">

<?= $bills->num_rows ?>

</span>


</div>



<div class="space-y-4">


<?php if (
    $bills->num_rows > 0
): ?>


<?php while (
    $bill =
    $bills->fetch_assoc()
): ?>


<?php

$paymentStatus =
    $bill["payment_status"]
    ?? "Pending";


if (
    $paymentStatus === "Paid"
) {

    $paymentClass =
        "bg-green-100 text-green-700";

} elseif (
    $paymentStatus === "Partial"
) {

    $paymentClass =
        "bg-blue-100 text-blue-700";

} else {

    $paymentClass =
        "bg-orange-100 text-orange-700";
}

?>


<div
class="bg-white border
border-slate-200
rounded-2xl p-5">


<div
class="flex justify-between
items-start gap-4">


<div>


<p
class="text-xs text-slate-400">

Bill #<?= intval(
    $bill["bill_id"]
) ?>

</p>


<h3
class="text-2xl font-bold mt-1">

₹<?= number_format(
    (float)(
        $bill["total"]
        ??
        $bill["amount"]
        ??
        0
    ),
    2
) ?>

</h3>


</div>


<span
class="<?= $paymentClass ?>
px-3 py-1 rounded-full
text-xs font-semibold">


<?= htmlspecialchars(
    $paymentStatus
) ?>


</span>


</div>



<!-- BILL BREAKDOWN -->

<div
class="grid grid-cols-2
md:grid-cols-4
gap-3 mt-5">


<div
class="bg-slate-50
rounded-xl p-3">


<p
class="text-xs text-slate-400">

Parts

</p>


<p class="font-semibold">

₹<?= number_format(
    (float)(
        $bill["parts_cost"]
        ?? 0
    ),
    2
) ?>

</p>


</div>



<div
class="bg-slate-50
rounded-xl p-3">


<p
class="text-xs text-slate-400">

Labor

</p>


<p class="font-semibold">

₹<?= number_format(
    (float)(
        $bill["labor_cost"]
        ?? 0
    ),
    2
) ?>

</p>


</div>



<div
class="bg-slate-50
rounded-xl p-3">


<p
class="text-xs text-slate-400">

Discount

</p>


<p class="font-semibold">

₹<?= number_format(
    (float)(
        $bill["discount"]
        ?? 0
    ),
    2
) ?>

</p>


</div>



<div
class="bg-slate-50
rounded-xl p-3">


<p
class="text-xs text-slate-400">

Payment

</p>


<p class="font-semibold">

<?= htmlspecialchars(
    $bill["payment_method"]
    ?? "-"
) ?>

</p>


</div>


</div>



<div
class="mt-4 flex flex-wrap
gap-3 text-sm text-slate-500">


<span>

Repair #<?= intval(
    $bill["repair_id"]
) ?>

</span>


<?php if (
    !empty(
        $bill["bill_date"]
    )
): ?>


<span>•</span>


<span>

<?= htmlspecialchars(
    $bill["bill_date"]
) ?>

</span>


<?php endif; ?>


</div>


</div>


<?php endwhile; ?>


<?php else: ?>


<div
class="bg-white border
border-slate-200
rounded-2xl p-8 text-center">


<span
class="material-symbols-outlined
text-5xl text-slate-300">

receipt_long

</span>


<p
class="font-semibold mt-3">

No bills found

</p>


<p
class="text-sm text-slate-500 mt-1">

Billing records will appear here.

</p>


</div>


<?php endif; ?>


</div>


</div>


</main>


</body>

</html>