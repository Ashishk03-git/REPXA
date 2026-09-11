<?php

/* =========================================================
   AUTHENTICATION
========================================================= */

require_once "auth.php";
require_once "db.php";

$currentUserRole = strtolower(trim($_SESSION["role"] ?? ""));
$isOwner = ($currentUserRole === "owner");



$message = "";
$messageType = "success";


/* =========================================================
   CREATE REQUIRED BILL COLUMNS AUTOMATICALLY
========================================================= */

$columns = [

    "parts_cost" =>
        "DECIMAL(10,2) DEFAULT 0",

    "labor_cost" =>
        "DECIMAL(10,2) DEFAULT 0",

    "discount" =>
        "DECIMAL(10,2) DEFAULT 0",

    "total" =>
        "DECIMAL(10,2) DEFAULT 0",

    "payment_method" =>
        "VARCHAR(50) DEFAULT 'Cash'",

    "payment_status" =>
        "VARCHAR(30) DEFAULT 'Pending'",

    "deleted_at" =>
        "DATETIME NULL DEFAULT NULL"
];


foreach ($columns as $column => $definition) {

    $check = $conn->query(
        "SHOW COLUMNS FROM bills LIKE '$column'"
    );

    if ($check && $check->num_rows === 0) {

        $conn->query(
            "ALTER TABLE bills
             ADD COLUMN $column $definition"
        );
    }
}


/* =========================================================
   CREATE BILL
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
) {

    $action =
        $_POST["action"] ?? "";

    if (!$isOwner && in_array($action, ["create_bill", "update_bill", "remove_bill"], true)) {
        $message = "Billing changes are available to Owner only.";
        $messageType = "error";
        $action = "";
    }


    /* =====================================================
       CREATE
    ===================================================== */

    if ($action === "create_bill") {

        $repairId =
            intval($_POST["repair_id"] ?? 0);

        $partsCost =
            max(
                0,
                floatval(
                    $_POST["parts_cost"] ?? 0
                )
            );

        $laborCost =
            max(
                0,
                floatval(
                    $_POST["labor_cost"] ?? 0
                )
            );

        $discount =
            max(
                0,
                floatval(
                    $_POST["discount"] ?? 0
                )
            );

        $paymentMethod =
            $_POST["payment_method"]
            ?? "Cash";

        $paymentStatus =
            $_POST["payment_status"]
            ?? "Pending";


        $total =
            $partsCost +
            $laborCost -
            $discount;


        if ($repairId <= 0) {

            $message =
                "Please select a repair.";

            $messageType =
                "error";

        } elseif (
            $discount >
            ($partsCost + $laborCost)
        ) {

            $message =
                "Discount cannot be greater than total.";

            $messageType =
                "error";

        } else {


            /* GET REPAIR */

            $stmt =
                $conn->prepare("
                    SELECT
                        repair_id,
                        customer_id
                    FROM repairs
                    WHERE repair_id = ?
                    LIMIT 1
                ");

            $stmt->bind_param(
                "i",
                $repairId
            );

            $stmt->execute();

            $repair =
                $stmt
                ->get_result()
                ->fetch_assoc();

            $stmt->close();


            if (!$repair) {

                $message =
                    "Repair not found.";

                $messageType =
                    "error";

            } else {

                $customerId =
                    intval(
                        $repair["customer_id"]
                    );


                /* CHECK EXISTING BILL */

                $stmt =
                    $conn->prepare("
                        SELECT bill_id
                        FROM bills
                        WHERE repair_id = ?
                        AND deleted_at IS NULL
                        LIMIT 1
                    ");

                $stmt->bind_param(
                    "i",
                    $repairId
                );

                $stmt->execute();

                $existing =
                    $stmt->get_result();

                $stmt->close();


                if (
                    $existing &&
                    $existing->num_rows > 0
                ) {

                    $message =
                        "Bill already exists for this repair.";

                    $messageType =
                        "error";

                } else {


                    /* INSERT */

                    $stmt =
                        $conn->prepare("
                            INSERT INTO bills
                            (
                                repair_id,
                                customer_id,
                                amount,
                                payment_method,
                                payment_status,
                                parts_cost,
                                labor_cost,
                                discount,
                                total
                            )
                            VALUES
                            (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");


                    $stmt->bind_param(
                        "iidssdddd",
                        $repairId,
                        $customerId,
                        $total,
                        $paymentMethod,
                        $paymentStatus,
                        $partsCost,
                        $laborCost,
                        $discount,
                        $total
                    );


                    if (
                        $stmt->execute()
                    ) {

                        $message =
                            "Bill created successfully.";

                    } else {

                        $message =
                            "Unable to create bill.";

                        $messageType =
                            "error";
                    }


                    $stmt->close();
                }
            }
        }
    }


    /* =====================================================
       UPDATE BILL
    ===================================================== */

    if ($action === "update_bill") {

        $billId =
            intval(
                $_POST["bill_id"] ?? 0
            );

        $customerId =
            intval(
                $_POST["customer_id"] ?? 0
            );

        $name =
            trim(
                $_POST["customer_name"]
                ?? ""
            );

        $phone =
            trim(
                $_POST["phone"] ?? ""
            );

        $deviceType =
            trim(
                $_POST["device_type"]
                ?? ""
            );

        $brand =
            trim(
                $_POST["brand"] ?? ""
            );

        $model =
            trim(
                $_POST["model"] ?? ""
            );

        $issue =
            trim(
                $_POST["issue"] ?? ""
            );

        $partsCost =
            max(
                0,
                floatval(
                    $_POST["parts_cost"]
                    ?? 0
                )
            );

        $laborCost =
            max(
                0,
                floatval(
                    $_POST["labor_cost"]
                    ?? 0
                )
            );

        $discount =
            max(
                0,
                floatval(
                    $_POST["discount"]
                    ?? 0
                )
            );

        $paymentMethod =
            $_POST["payment_method"]
            ?? "Cash";

        $paymentStatus =
            $_POST["payment_status"]
            ?? "Pending";


        $total =
            $partsCost +
            $laborCost -
            $discount;


        if (
            $billId <= 0 ||
            $customerId <= 0
        ) {

            $message =
                "Invalid bill.";

            $messageType =
                "error";

        } elseif (
            $name === "" ||
            $phone === ""
        ) {

            $message =
                "Name and phone are required.";

            $messageType =
                "error";

        } elseif (
            $discount >
            ($partsCost + $laborCost)
        ) {

            $message =
                "Discount cannot be greater than total.";

            $messageType =
                "error";

        } else {


            /* UPDATE CUSTOMER */

            $stmt =
                $conn->prepare("
                    UPDATE customers
                    SET
                        name = ?,
                        phone = ?
                    WHERE customer_id = ?
                ");

            $stmt->bind_param(
                "ssi",
                $name,
                $phone,
                $customerId
            );

            $stmt->execute();

            $stmt->close();


            /* GET REPAIR */

            $stmt =
                $conn->prepare("
                    SELECT repair_id
                    FROM bills
                    WHERE bill_id = ?
                    LIMIT 1
                ");

            $stmt->bind_param(
                "i",
                $billId
            );

            $stmt->execute();

            $billInfo =
                $stmt
                ->get_result()
                ->fetch_assoc();

            $stmt->close();


            if ($billInfo) {

                $repairId =
                    intval(
                        $billInfo["repair_id"]
                    );


                /* UPDATE REPAIR */

                $stmt =
                    $conn->prepare("
                        UPDATE repairs
                        SET
                            device_type = ?,
                            brand = ?,
                            model = ?,
                            issue = ?
                        WHERE repair_id = ?
                    ");

                $stmt->bind_param(
                    "ssssi",
                    $deviceType,
                    $brand,
                    $model,
                    $issue,
                    $repairId
                );

                $stmt->execute();

                $stmt->close();


                /* UPDATE BILL */

                $stmt =
                    $conn->prepare("
                        UPDATE bills
                        SET
                            amount = ?,
                            payment_method = ?,
                            payment_status = ?,
                            parts_cost = ?,
                            labor_cost = ?,
                            discount = ?,
                            total = ?
                        WHERE bill_id = ?
                    ");

                $stmt->bind_param(
                    "dssddddi",
                    $total,
                    $paymentMethod,
                    $paymentStatus,
                    $partsCost,
                    $laborCost,
                    $discount,
                    $total,
                    $billId
                );


                if (
                    $stmt->execute()
                ) {

                    $message =
                        "Bill updated successfully.";

                } else {

                    $message =
                        "Unable to update bill.";

                    $messageType =
                        "error";
                }

                $stmt->close();
            }
        }
    }


    /* =====================================================
       REMOVE BILL - SOFT DELETE
    ===================================================== */

    if ($action === "remove_bill") {

        $billId =
            intval(
                $_POST["bill_id"] ?? 0
            );


        if ($billId > 0) {

            $stmt =
                $conn->prepare("
                    UPDATE bills
                    SET deleted_at = NOW()
                    WHERE bill_id = ?
                ");

            $stmt->bind_param(
                "i",
                $billId
            );


            if (
                $stmt->execute()
            ) {

                $message =
                    "Bill moved to Recycle Bin.";

            } else {

                $message =
                    "Unable to remove bill.";

                $messageType =
                    "error";
            }


            $stmt->close();
        }
    }
}


/* =========================================================
   WEEK FILTER
========================================================= */

$selectedWeek =
    $_GET["week"] ?? "current";


$today =
    new DateTime();


if ($selectedWeek === "current") {

    $weekStart =
        new DateTime(
            "monday this week"
        );

    $weekEnd =
        new DateTime(
            "sunday this week"
        );

} else {

    $weekOffset =
        intval(
            $selectedWeek
        );

    $weekStart =
        new DateTime(
            "monday this week"
        );

    if ($weekOffset < 0) {

        $weekStart->modify(
            $weekOffset . " weeks"
        );
    }

    $weekEnd =
        clone $weekStart;

    $weekEnd->modify(
        "+6 days"
    );
}


$startDate =
    $weekStart->format("Y-m-d");

$endDate =
    $weekEnd->format("Y-m-d");


$weekLabel =
    $weekStart->format("d M Y")
    . " - "
    . $weekEnd->format("d M Y");



/* =========================================================
   SEARCH
========================================================= */

$search =
    trim(
        $_GET["search"] ?? ""
    );


/* =========================================================
   SUMMARY
========================================================= */

/*
 * Collected = Paid bills
 * Pending = Pending + Partial
 */

$summaryStmt =
    $conn->prepare("
        SELECT

            COUNT(*) AS total_bills,

            COALESCE(
                SUM(
                    CASE
                        WHEN payment_status = 'Paid'
                        THEN total
                        ELSE 0
                    END
                ),
                0
            ) AS collected,

            COALESCE(
                SUM(
                    CASE
                        WHEN payment_status != 'Paid'
                        THEN total
                        ELSE 0
                    END
                ),
                0
            ) AS pending

        FROM bills

        WHERE deleted_at IS NULL

        AND DATE(bill_date)
            BETWEEN ? AND ?
    ");


$summaryStmt->bind_param(
    "ss",
    $startDate,
    $endDate
);

$summaryStmt->execute();

$summary =
    $summaryStmt
    ->get_result()
    ->fetch_assoc();

$summaryStmt->close();


$totalBills =
    intval(
        $summary["total_bills"] ?? 0
    );

$collected =
    (float)(
        $summary["collected"] ?? 0
    );

$pending =
    (float)(
        $summary["pending"] ?? 0
    );



/* =========================================================
   PREVIOUS PENDING
========================================================= */

$previousStmt =
    $conn->prepare("
        SELECT
            COALESCE(
                SUM(total),
                0
            ) AS previous_pending
        FROM bills
        WHERE deleted_at IS NULL
        AND payment_status != 'Paid'
        AND DATE(bill_date) < ?
    ");

$previousStmt->bind_param(
    "s",
    $startDate
);

$previousStmt->execute();

$previousResult =
    $previousStmt
    ->get_result()
    ->fetch_assoc();

$previousStmt->close();


$previousPending =
    (float)(
        $previousResult["previous_pending"]
        ?? 0
    );



/* =========================================================
   BILL LIST
========================================================= */

$sql = "
    SELECT

        b.bill_id,
        b.repair_id,
        b.customer_id,

        b.amount,
        b.payment_method,
        b.payment_status,

        b.parts_cost,
        b.labor_cost,
        b.discount,
        b.total,

        b.bill_date,

        c.name AS customer_name,
        c.phone,

        r.device_type,
        r.brand,
        r.model,
        r.issue,
        r.status AS repair_status

    FROM bills b

    LEFT JOIN customers c
        ON b.customer_id = c.customer_id

    LEFT JOIN repairs r
        ON b.repair_id = r.repair_id

    WHERE b.deleted_at IS NULL

    AND DATE(b.bill_date)
        BETWEEN ? AND ?
";


if ($search !== "") {

    $sql .= "
        AND (
            c.name LIKE ?
            OR c.phone LIKE ?
            OR CAST(b.bill_id AS CHAR) LIKE ?
            OR CAST(b.repair_id AS CHAR) LIKE ?
        )
    ";
}


$sql .= "
    ORDER BY b.bill_id DESC
";


$stmt =
    $conn->prepare($sql);


if ($search !== "") {

    $term =
        "%" . $search . "%";

    $stmt->bind_param(
        "ssssss",
        $startDate,
        $endDate,
        $term,
        $term,
        $term,
        $term
    );

} else {

    $stmt->bind_param(
        "ss",
        $startDate,
        $endDate
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

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>REPXA - Billing</title>


<script src="https://cdn.tailwindcss.com">
</script>


<link
href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"
rel="stylesheet">


<style>

.hidden-section {
    display: none;
}

</style>

</head>


<body
class="bg-slate-50 text-slate-900">


<!-- HEADER -->

<header class="bg-white border-b sticky top-0 z-50">
<div class="max-w-5xl mx-auto px-5 h-16 flex items-center justify-between">

<div class="flex items-center gap-3">
<a href="<?= $isOwner ? "dashboard.php" : "agent_dashboard.php" ?>"
class="w-10 h-10" rounded-full flex items-center justify-center hover:bg-slate-100">
<span class="material-symbols-outlined">arrow_back</span>
</a>

<div>
<h1 class="text-lg font-bold text-blue-700">Billing</h1>
<p class="text-xs text-slate-500">Invoice & Payment Management</p>
</div>
</div>

<button type="button" onclick="toggleMenu()"
class="w-10 h-10 rounded-xl flex items-center justify-center hover:bg-slate-100">
<span class="material-symbols-outlined">menu</span>
</button>

</div>
</header>

<!-- SIDE MENU -->

<div id="menuOverlay" onclick="toggleMenu()"
class="hidden fixed inset-0 bg-black/30 z-[60]"></div>

<aside id="sideMenu"
class="fixed top-0 right-0 bottom-0 w-80 max-w-[88vw] bg-white z-[70] shadow-2xl translate-x-full transition-transform duration-200">

<div class="h-16 px-5 border-b flex items-center justify-between">
<div>
<p class="font-bold text-blue-700">REPXA</p>
<p class="text-xs text-slate-500">Repair Management</p>
</div>

<button type="button" onclick="toggleMenu()"
class="w-9 h-9 rounded-full hover:bg-slate-100 flex items-center justify-center">
<span class="material-symbols-outlined">close</span>
</button>
</div>

<div class="p-4 space-y-1">
<a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-100">
<span class="material-symbols-outlined">dashboard</span><span>Home</span>
</a>
<a href="repair.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-100">
<span class="material-symbols-outlined">build</span><span>Repairs</span>
</a>
<a href="status.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-100">
<span class="material-symbols-outlined">track_changes</span><span>Repair Status</span>
</a>
<a href="customers.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-100">
<span class="material-symbols-outlined">groups</span><span>Customers</span>
</a>
<a href="inventory.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-100">
<span class="material-symbols-outlined">inventory_2</span><span>Stock</span>
</a>
<a href="billing_io.php" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-blue-50 text-blue-700">
<span class="material-symbols-outlined">receipt_long</span><span>Billing</span>
</a>
<a href="profile.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-100">
<span class="material-symbols-outlined">person</span><span>Profile</span>
</a>

<div class="my-3 border-t"></div>

<a href="staff.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-100">
<span class="material-symbols-outlined">badge</span><span>Staff Details</span>
</a>
<a href="recycle_bin.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-100">
<span class="material-symbols-outlined">delete</span><span>Recycle Bin</span>
</a>
<a href="help.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-100">
<span class="material-symbols-outlined">help</span><span>Help & Support</span>
</a>
<a href="logout.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-red-600 hover:bg-red-50">
<span class="material-symbols-outlined">logout</span><span>Logout</span>
</a>
</div>
</aside>



<main
class="max-w-5xl mx-auto px-5 py-6">


<!-- MESSAGE -->

<?php if ($message !== ""): ?>

<div
class="mb-5 p-3 rounded-xl text-sm
<?= $messageType === "success"

    ? "bg-green-100 text-green-700 border border-green-200"

    : "bg-red-100 text-red-700 border border-red-200"
?>">

<?= htmlspecialchars(
    $message
) ?>

</div>

<?php endif; ?>



<!-- TOP -->

<div
class="flex flex-col md:flex-row
md:items-center justify-between
gap-4 mb-5">


<div>

<p
class="text-xs font-semibold
tracking-wider text-slate-500">

BILLING OVERVIEW

</p>

<h2
class="text-2xl font-bold mt-1">

<?= htmlspecialchars(
    $weekLabel
) ?>

</h2>

</div>


<!-- WEEK SELECT -->

<form method="GET"
class="flex gap-2">


<input
type="hidden"
name="search"
value="<?= htmlspecialchars(
    $search
) ?>">


<select
name="week"
onchange="this.form.submit()"
class="border border-slate-300
rounded-xl bg-white
px-4 py-2.5 text-sm">


<option
value="current"
<?= $selectedWeek === "current"
    ? "selected"
    : "" ?>>

This Week

</option>


<?php for (
    $i = -1;
    $i >= -8;
    $i--
): ?>

<?php

$tempStart =
    new DateTime(
        "monday this week"
    );

$tempStart->modify(
    $i . " weeks"
);

$tempEnd =
    clone $tempStart;

$tempEnd->modify(
    "+6 days"
);

?>

<option
value="<?= $i ?>"
<?= (string)$selectedWeek ===
    (string)$i
    ? "selected"
    : "" ?>>

<?= $tempStart->format(
    "d M"
) ?>

 -

<?= $tempEnd->format(
    "d M Y"
) ?>

</option>

<?php endfor; ?>


</select>

</form>

</div>



<!-- SUMMARY -->

<div
class="grid grid-cols-2
md:grid-cols-4 gap-3 mb-5">


<!-- TOTAL -->

<button
type="button"
onclick="showAll()"
class="text-left bg-white
border rounded-xl p-4
hover:border-blue-400">


<p
class="text-xs text-slate-500">

Total Bills

</p>

<p
class="text-2xl font-bold mt-1">

<?= $totalBills ?>

</p>

</button>



<!-- COLLECTED -->

<button
type="button"
onclick="filterBills('paid')"
class="text-left bg-white
border rounded-xl p-4
hover:border-green-400">


<p
class="text-xs text-slate-500">

Collected

</p>

<p
class="text-xl font-bold
text-green-600 mt-1">

₹<?= number_format(
    $collected,
    2
) ?>

</p>

</button>



<!-- PENDING -->

<button
type="button"
onclick="filterBills('pending')"
class="text-left bg-white
border rounded-xl p-4
hover:border-orange-400">


<p
class="text-xs text-slate-500">

Pending

</p>

<p
class="text-xl font-bold
text-orange-600 mt-1">

₹<?= number_format(
    $pending,
    2
) ?>

</p>

</button>



<!-- PREVIOUS -->

<button
type="button"
onclick="filterBills('previous')"
class="text-left bg-white
border rounded-xl p-4
hover:border-red-400">


<p
class="text-xs text-slate-500">

Previous Pending

</p>

<p
class="text-xl font-bold
text-red-600 mt-1">

₹<?= number_format(
    $previousPending,
    2
) ?>

</p>

</button>


</div>



<?php if ($isOwner): ?>

<!-- CREATE BILL TOGGLE -->

<button
type="button"
onclick="toggleCreate()"
class="w-full bg-blue-700
hover:bg-blue-800
text-white rounded-xl
px-4 py-3.5 font-semibold
flex items-center
justify-center gap-2 mb-4">


<span
id="createIcon"
class="material-symbols-outlined">

add

</span>


<span id="createText">
Create Bill
</span>

</button>



<!-- CREATE SECTION -->

<div
id="createSection"
class="hidden-section
bg-white border rounded-2xl
p-5 mb-6">


<div
class="flex items-center gap-3 mb-5">


<div
class="w-10 h-10 rounded-xl
bg-blue-50 flex items-center
justify-center">


<span
class="material-symbols-outlined
text-blue-700">

receipt_long

</span>

</div>


<div>

<h3
class="font-bold text-lg">

New Bill

</h3>

<p
class="text-xs text-slate-500">

Enter billing details

</p>

</div>

</div>



<form method="POST">


<input
type="hidden"
name="action"
value="create_bill">


<label
class="text-sm font-semibold">

Repair Job

</label>


<select
name="repair_id"
required
class="w-full mt-1
border border-slate-300
rounded-xl px-4 py-3
bg-white">


<option value="">
Select Repair
</option>


<?php

$repairList =
    $conn->query("
        SELECT
            r.repair_id,
            r.device_type,
            r.brand,
            r.model,
            r.status,
            c.name AS customer_name
        FROM repairs r
        LEFT JOIN customers c
            ON r.customer_id =
               c.customer_id
        ORDER BY
            r.repair_id DESC
    ");

?>


<?php if ($repairList): ?>

<?php while (
    $repair =
    $repairList->fetch_assoc()
): ?>


<option
value="<?= intval(
    $repair["repair_id"]
) ?>">

#<?= intval(
    $repair["repair_id"]
) ?>

-

<?= htmlspecialchars(
    $repair["customer_name"]
    ?? "Customer"
) ?>

-

<?= htmlspecialchars(
    $repair["device_type"]
) ?>

-

<?= htmlspecialchars(
    $repair["status"]
) ?>

</option>


<?php endwhile; ?>

<?php endif; ?>


</select>



<div
class="grid grid-cols-3 gap-3 mt-4">


<div>

<label
class="text-xs font-semibold">

Parts Cost

</label>

<input
id="partsCost"
type="number"
name="parts_cost"
min="0"
step="0.01"
value="0"
class="w-full mt-1
border rounded-xl
px-3 py-2.5">

</div>


<div>

<label
class="text-xs font-semibold">

Labor Cost

</label>

<input
id="laborCost"
type="number"
name="labor_cost"
min="0"
step="0.01"
value="0"
class="w-full mt-1
border rounded-xl
px-3 py-2.5">

</div>


<div>

<label
class="text-xs font-semibold">

Discount

</label>

<input
id="discount"
type="number"
name="discount"
min="0"
step="0.01"
value="0"
class="w-full mt-1
border rounded-xl
px-3 py-2.5">

</div>

</div>



<div
class="mt-4 bg-blue-50
rounded-xl p-4">


<p
class="text-xs text-blue-600
font-semibold">

TOTAL

</p>


<p
class="text-2xl font-bold
text-blue-700">

₹<span id="totalAmount">
0.00
</span>

</p>

</div>



<div
class="grid md:grid-cols-2
gap-3 mt-4">


<div>

<label
class="text-sm font-semibold">

Payment Mode

</label>


<select
name="payment_method"
class="w-full mt-1
border rounded-xl
px-4 py-3 bg-white">

<option>Cash</option>
<option>UPI</option>
<option>Card</option>
<option>Bank Transfer</option>

</select>

</div>


<div>

<label
class="text-sm font-semibold">

Payment Status

</label>


<select
name="payment_status"
class="w-full mt-1
border rounded-xl
px-4 py-3 bg-white">

<option>Pending</option>
<option>Paid</option>
<option>Partial</option>

</select>

</div>

</div>



<button
type="submit"
class="w-full mt-4
bg-blue-700
hover:bg-blue-800
text-white py-3
rounded-xl font-semibold">

Create Bill

</button>


</form>

</div>

<?php endif; ?>


<!-- SEARCH -->

<form
method="GET"
class="mb-4">


<input
type="hidden"
name="week"
value="<?= htmlspecialchars(
    $selectedWeek
) ?>">


<div
class="relative">


<span
class="material-symbols-outlined
absolute left-4 top-1/2
-translate-y-1/2
text-slate-400">

search

</span>


<input
type="text"
name="search"
value="<?= htmlspecialchars(
    $search
) ?>"
placeholder="Search customer, phone, bill ID or repair ID..."

class="w-full bg-white
border border-slate-300
rounded-xl pl-12 pr-4
py-3 outline-none
focus:border-blue-600">


</div>

</form>



<!-- BILL RECORDS -->

<div id="billList"
class="space-y-3">


<?php if (
    $bills &&
    $bills->num_rows > 0
): ?>


<?php while (
    $bill =
    $bills->fetch_assoc()
): ?>


<?php

$billStatus =
    $bill["payment_status"]
    ?? "Pending";


$statusFilter =
    $billStatus === "Paid"
    ? "paid"
    : "pending";


$statusClass =
    $billStatus === "Paid"

    ? "bg-green-100 text-green-700"

    : (
        $billStatus === "Partial"

        ? "bg-blue-100 text-blue-700"

        : "bg-orange-100 text-orange-700"
    );


$billDate =
    !empty($bill["bill_date"])
    ? date(
        "d M Y",
        strtotime(
            $bill["bill_date"]
        )
    )
    : "-";


$billWeek =
    !empty($bill["bill_date"])
    ? date(
        "W",
        strtotime(
            $bill["bill_date"]
        )
    )
    : "-";

?>


<div
class="bill-card bg-white
border rounded-xl p-4"
data-status="<?= $statusFilter ?>">


<!-- TOP -->

<div
class="flex items-center
justify-between gap-3">


<div>

<p
class="text-xs text-slate-400">

Bill #<?= intval(
    $bill["bill_id"]
) ?>

&nbsp; • &nbsp;

<?= $billDate ?>

</p>


<h3
class="font-bold text-lg mt-1">

<?= htmlspecialchars(
    $bill["customer_name"]
    ?? "Customer"
) ?>

</h3>


<p
class="text-xs text-slate-500">

<?= htmlspecialchars(
    $bill["phone"]
    ?? ""
) ?>

</p>

</div>


<span
class="<?= $statusClass ?>
px-2.5 py-1 rounded-full
text-[11px] font-semibold">

<?= htmlspecialchars(
    $billStatus
) ?>

</span>


</div>



<!-- DEVICE -->

<div
class="mt-3 text-sm">


<strong>

<?= htmlspecialchars(
    $bill["device_type"]
    ?? ""
) ?>

</strong>


<span class="text-slate-400">
•
</span>


<?= htmlspecialchars(
    $bill["brand"]
    ?? ""
) ?>


<?php if (
    !empty($bill["model"])
): ?>

<span class="text-slate-400">
•
</span>

<?= htmlspecialchars(
    $bill["model"]
) ?>

<?php endif; ?>


</div>



<!-- AMOUNT -->

<div
class="mt-3 flex items-center
justify-between">


<div>

<p
class="text-xs text-slate-400">

Week <?= htmlspecialchars(
    $billWeek
) ?>

&nbsp; • &nbsp;

Repair #<?= intval(
    $bill["repair_id"]
) ?>

</p>


<p
class="text-xl font-bold
text-blue-700 mt-1">

₹<?= number_format(
    (float)(
        $bill["total"]
        ?? $bill["amount"]
        ?? 0
    ),
    2
) ?>

</p>

</div>


<div
class="text-right text-xs
text-slate-500">


<p>
Parts:
₹<?= number_format(
    (float)(
        $bill["parts_cost"]
        ?? 0
    ),
    2
) ?>
</p>


<p>
Labor:
₹<?= number_format(
    (float)(
        $bill["labor_cost"]
        ?? 0
    ),
    2
) ?>
</p>


<p>
Discount:
₹<?= number_format(
    (float)(
        $bill["discount"]
        ?? 0
    ),
    2
) ?>
</p>

</div>

</div>



<!-- ACTIONS -->

<div
class="mt-4 pt-3
border-t flex flex-wrap
gap-2">


<?php if ($isOwner): ?>

<button
type="button"
onclick='openEdit(
<?= intval(
    $bill["bill_id"]
) ?>,

<?= intval(
    $bill["customer_id"]
) ?>,

<?= json_encode(
    $bill["customer_name"]
    ?? ""
) ?>,

<?= json_encode(
    $bill["phone"]
    ?? ""
) ?>,

<?= json_encode(
    $bill["device_type"]
    ?? ""
) ?>,

<?= json_encode(
    $bill["brand"]
    ?? ""
) ?>,

<?= json_encode(
    $bill["model"]
    ?? ""
) ?>,

<?= json_encode(
    $bill["issue"]
    ?? ""
) ?>,

<?= (float)(
    $bill["parts_cost"]
    ?? 0
) ?>,

<?= (float)(
    $bill["labor_cost"]
    ?? 0
) ?>,

<?= (float)(
    $bill["discount"]
    ?? 0
) ?>,

<?= json_encode(
    $bill["payment_method"]
    ?? "Cash"
) ?>,

<?= json_encode(
    $bill["payment_status"]
    ?? "Pending"
) ?>
)'

class="px-3 py-2
rounded-lg bg-blue-50
text-blue-700 text-xs
font-semibold flex
items-center gap-1">


<span
class="material-symbols-outlined
text-sm">

edit

</span>

Edit

</button>



<?php endif; ?>

<a
href="bill_pdf.php?id=<?= intval(
    $bill["bill_id"]
) ?>&download=1"

class="px-3 py-2
rounded-lg bg-green-50
text-green-700 text-xs
font-semibold flex
items-center gap-1">


<span
class="material-symbols-outlined
text-sm">

download

</span>

Download PDF

</a>



<?php if ($isOwner): ?>

<form
method="POST"
onsubmit="return confirm(
'Remove this bill? It will go to Recycle Bin.'
)">


<input
type="hidden"
name="action"
value="remove_bill">


<input
type="hidden"
name="bill_id"
value="<?= intval(
    $bill["bill_id"]
) ?>">


<button
type="submit"
class="px-3 py-2
rounded-lg bg-red-50
text-red-700 text-xs
font-semibold flex
items-center gap-1">


<span
class="material-symbols-outlined
text-sm">

delete

</span>

Remove

</button>

</form>

<?php endif; ?>


</div>


</div>


<?php endwhile; ?>


<?php else: ?>


<div
class="bg-white border
rounded-2xl p-10
text-center">


<span
class="material-symbols-outlined
text-5xl text-slate-300">

receipt_long

</span>


<h3
class="font-semibold mt-3">

No Bills Found

</h3>


<p
class="text-sm text-slate-500 mt-1">

No billing records found
for this week/search.

</p>


</div>


<?php endif; ?>


</div>


</main>



<!-- EDIT MODAL -->

<?php if ($isOwner): ?>

<div
id="editModal"
class="hidden fixed inset-0
z-[100] bg-black/50
items-center justify-center
px-4">


<div
class="bg-white rounded-2xl
w-full max-w-2xl
max-h-[90vh]
overflow-y-auto p-5">


<div
class="flex items-center
justify-between mb-5">


<div>

<h2
class="font-bold text-lg">

Edit Bill

</h2>

<p
class="text-xs text-slate-500">

Update all bill details

</p>

</div>


<button
type="button"
onclick="closeEdit()"
class="w-9 h-9 rounded-full
hover:bg-slate-100">


<span
class="material-symbols-outlined">

close

</span>

</button>

</div>



<form method="POST">


<input
type="hidden"
name="action"
value="update_bill">


<input
type="hidden"
id="editBillId"
name="bill_id">


<input
type="hidden"
id="editCustomerId"
name="customer_id">



<div
class="grid md:grid-cols-2
gap-3">


<div>

<label
class="text-sm font-semibold">

Customer Name

</label>

<input
id="editName"
name="customer_name"
required
class="w-full mt-1
border rounded-xl
px-3 py-2.5">

</div>


<div>

<label
class="text-sm font-semibold">

Phone

</label>

<input
id="editPhone"
name="phone"
required
class="w-full mt-1
border rounded-xl
px-3 py-2.5">

</div>


<div>

<label
class="text-sm font-semibold">

Device

</label>

<input
id="editDevice"
name="device_type"
class="w-full mt-1
border rounded-xl
px-3 py-2.5">

</div>


<div>

<label
class="text-sm font-semibold">

Brand

</label>

<input
id="editBrand"
name="brand"
class="w-full mt-1
border rounded-xl
px-3 py-2.5">

</div>


<div>

<label
class="text-sm font-semibold">

Model

</label>

<input
id="editModel"
name="model"
class="w-full mt-1
border rounded-xl
px-3 py-2.5">

</div>


<div>

<label
class="text-sm font-semibold">

Payment Mode

</label>

<select
id="editPaymentMethod"
name="payment_method"
class="w-full mt-1
border rounded-xl
px-3 py-2.5 bg-white">

<option>Cash</option>
<option>UPI</option>
<option>Card</option>
<option>Bank Transfer</option>

</select>

</div>


<div class="md:col-span-2">

<label
class="text-sm font-semibold">

Issue

</label>

<textarea
id="editIssue"
name="issue"
rows="3"
class="w-full mt-1
border rounded-xl
px-3 py-2.5"></textarea>

</div>


<div>

<label
class="text-sm font-semibold">

Parts Cost

</label>

<input
id="editParts"
name="parts_cost"
type="number"
min="0"
step="0.01"
class="w-full mt-1
border rounded-xl
px-3 py-2.5">

</div>


<div>

<label
class="text-sm font-semibold">

Labor Cost

</label>

<input
id="editLabor"
name="labor_cost"
type="number"
min="0"
step="0.01"
class="w-full mt-1
border rounded-xl
px-3 py-2.5">

</div>


<div>

<label
class="text-sm font-semibold">

Discount

</label>

<input
id="editDiscount"
name="discount"
type="number"
min="0"
step="0.01"
class="w-full mt-1
border rounded-xl
px-3 py-2.5">

</div>


<div>

<label
class="text-sm font-semibold">

Payment Status

</label>

<select
id="editPaymentStatus"
name="payment_status"
class="w-full mt-1
border rounded-xl
px-3 py-2.5 bg-white">

<option>Pending</option>
<option>Paid</option>
<option>Partial</option>

</select>

</div>

</div>



<div
class="mt-4 bg-blue-50
rounded-xl p-4">


<p
class="text-xs text-blue-600">

NEW TOTAL

</p>


<p
class="text-2xl font-bold
text-blue-700">

₹<span id="editTotal">
0.00
</span>

</p>

</div>



<button
type="submit"
class="w-full mt-4
bg-blue-700
hover:bg-blue-800
text-white py-3
rounded-xl font-semibold">

Save Changes

</button>


</form>

</div>

</div>



<?php endif; ?>

<script>

function toggleMenu() {
    const menu = document.getElementById("sideMenu");
    const overlay = document.getElementById("menuOverlay");

    if (menu.classList.contains("translate-x-full")) {
        menu.classList.remove("translate-x-full");
        overlay.classList.remove("hidden");
    } else {
        menu.classList.add("translate-x-full");
        overlay.classList.add("hidden");
    }
}



/* =====================================================
   CREATE SECTION
===================================================== */

function toggleCreate() {

    const section =
        document.getElementById(
            "createSection"
        );

    const icon =
        document.getElementById(
            "createIcon"
        );

    const text =
        document.getElementById(
            "createText"
        );


    if (
        section.classList.contains(
            "hidden-section"
        )
    ) {

        section.classList.remove(
            "hidden-section"
        );

        icon.textContent =
            "remove";

        text.textContent =
            "Close Create Bill";

    } else {

        section.classList.add(
            "hidden-section"
        );

        icon.textContent =
            "add";

        text.textContent =
            "Create Bill";
    }
}



/* =====================================================
   CREATE TOTAL
===================================================== */

function calculateCreateTotal() {

    const parts =
        parseFloat(
            document.getElementById(
                "partsCost"
            ).value
        ) || 0;


    const labor =
        parseFloat(
            document.getElementById(
                "laborCost"
            ).value
        ) || 0;


    const discount =
        parseFloat(
            document.getElementById(
                "discount"
            ).value
        ) || 0;


    const total =
        Math.max(
            0,
            parts +
            labor -
            discount
        );


    document.getElementById(
        "totalAmount"
    ).textContent =
        total.toFixed(2);
}


[
    "partsCost",
    "laborCost",
    "discount"
].forEach(function(id) {

    document
        .getElementById(id)
        .addEventListener(
            "input",
            calculateCreateTotal
        );

});


calculateCreateTotal();



/* =====================================================
   FILTER
===================================================== */

function showAll() {

    const cards =
        document.querySelectorAll(
            ".bill-card"
        );


    cards.forEach(function(card) {

        card.style.display =
            "block";

    });
}


function filterBills(type) {

    const cards =
        document.querySelectorAll(
            ".bill-card"
        );


    cards.forEach(function(card) {

        if (
            type === "paid"
        ) {

            card.style.display =
                card.dataset.status ===
                "paid"
                ? "block"
                : "none";

        } else {

            card.style.display =
                card.dataset.status ===
                "pending"
                ? "block"
                : "none";
        }

    });
}



/* =====================================================
   EDIT MODAL
===================================================== */

function openEdit(
    billId,
    customerId,
    name,
    phone,
    device,
    brand,
    model,
    issue,
    parts,
    labor,
    discount,
    paymentMethod,
    paymentStatus
) {


    document.getElementById(
        "editBillId"
    ).value =
        billId;


    document.getElementById(
        "editCustomerId"
    ).value =
        customerId;


    document.getElementById(
        "editName"
    ).value =
        name;


    document.getElementById(
        "editPhone"
    ).value =
        phone;


    document.getElementById(
        "editDevice"
    ).value =
        device;


    document.getElementById(
        "editBrand"
    ).value =
        brand;


    document.getElementById(
        "editModel"
    ).value =
        model;


    document.getElementById(
        "editIssue"
    ).value =
        issue;


    document.getElementById(
        "editParts"
    ).value =
        parts;


    document.getElementById(
        "editLabor"
    ).value =
        labor;


    document.getElementById(
        "editDiscount"
    ).value =
        discount;


    document.getElementById(
        "editPaymentMethod"
    ).value =
        paymentMethod;


    document.getElementById(
        "editPaymentStatus"
    ).value =
        paymentStatus;


    calculateEditTotal();


    const modal =
        document.getElementById(
            "editModal"
        );


    modal.classList.remove(
        "hidden"
    );

    modal.classList.add(
        "flex"
    );
}


function closeEdit() {

    const modal =
        document.getElementById(
            "editModal"
        );


    modal.classList.add(
        "hidden"
    );

    modal.classList.remove(
        "flex"
    );
}



/* =====================================================
   EDIT TOTAL
===================================================== */

function calculateEditTotal() {

    const parts =
        parseFloat(
            document.getElementById(
                "editParts"
            ).value
        ) || 0;


    const labor =
        parseFloat(
            document.getElementById(
                "editLabor"
            ).value
        ) || 0;


    const discount =
        parseFloat(
            document.getElementById(
                "editDiscount"
            ).value
        ) || 0;


    const total =
        Math.max(
            0,
            parts +
            labor -
            discount
        );


    document.getElementById(
        "editTotal"
    ).textContent =
        total.toFixed(2);
}


[
    "editParts",
    "editLabor",
    "editDiscount"
].forEach(function(id) {

    document
        .getElementById(id)
        .addEventListener(
            "input",
            calculateEditTotal
        );

});


/* CLOSE MODAL */

document
    .getElementById("editModal")
    .addEventListener(
        "click",
        function(e) {

            if (
                e.target === this
            ) {

                closeEdit();

            }

        }
    );

</script>


</body>

</html>