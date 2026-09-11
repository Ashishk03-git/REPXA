<?php
require_once "db.php";

$message = "";

/* =========================
   UPDATE PAYMENT STATUS
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $billId = intval($_POST["bill_id"] ?? 0);
    $paymentStatus = $_POST["payment_status"] ?? "Pending";

    $allowed = ["Pending", "Partial", "Paid"];

    if ($billId > 0 && in_array($paymentStatus, $allowed, true)) {

        $stmt = $conn->prepare("
            UPDATE bills
            SET payment_status = ?
            WHERE bill_id = ?
        ");

        $stmt->bind_param(
            "si",
            $paymentStatus,
            $billId
        );

        if ($stmt->execute()) {
            $message = "Payment status updated successfully!";
        }
    }
}


/* =========================
   GET BILLS
========================= */

$bills = $conn->query("
    SELECT
        b.bill_id,
        b.repair_id,
        b.customer_id,
        b.amount,
        b.parts_cost,
        b.labor_cost,
        b.discount,
        b.total,
        b.payment_method,
        b.payment_status,
        b.bill_date,
        c.name AS customer_name,
        c.phone AS customer_phone
    FROM bills b
    LEFT JOIN customers c
        ON b.customer_id = c.customer_id
    ORDER BY b.bill_id DESC
");


/* =========================
   TOTALS
========================= */

$totalBills = 0;
$totalRevenue = 0;
$paidAmount = 0;
$pendingAmount = 0;

if ($bills) {

    while ($row = $bills->fetch_assoc()) {

        $totalBills++;

        $amount = (float)($row["total"] ?? $row["amount"] ?? 0);

        $totalRevenue += $amount;

        if ($row["payment_status"] === "Paid") {
            $paidAmount += $amount;
        } else {
            $pendingAmount += $amount;
        }
    }

    $bills->data_seek(0);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>REPXA - Billing History</title>

<script src="https://cdn.tailwindcss.com"></script>

<link
href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"
rel="stylesheet">

</head>


<body class="bg-slate-50 text-slate-900 pb-24">


<!-- HEADER -->

<header class="bg-white border-b sticky top-0 z-50">

<div class="max-w-5xl mx-auto px-5 h-16 flex items-center gap-3">

<a
href="billing.php"
class="w-10 h-10 rounded-full flex items-center justify-center hover:bg-slate-100">

<span class="material-symbols-outlined">
arrow_back
</span>

</a>

<div>

<h1 class="text-lg font-bold text-blue-700">
Billing History
</h1>

<p class="text-xs text-slate-500">
Invoices & payments
</p>

</div>

</div>

</header>


<main class="max-w-5xl mx-auto px-5 py-7">


<!-- TITLE -->

<div class="mb-6">

<p class="text-xs font-semibold tracking-wider text-slate-500">
PAYMENT MANAGEMENT
</p>

<h2 class="text-2xl font-bold mt-1">
All Bills
</h2>

<p class="text-sm text-slate-500 mt-1">
View invoices and manage payment status.
</p>

</div>


<!-- SUCCESS -->

<?php if ($message): ?>

<div class="mb-6 bg-green-100 border border-green-200
text-green-700 p-4 rounded-xl">

<?= htmlspecialchars($message) ?>

</div>

<?php endif; ?>


<!-- SUMMARY -->

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">


<div class="bg-white border rounded-2xl p-5">

<p class="text-xs text-slate-500">
Total Bills
</p>

<p class="text-2xl font-bold text-blue-700 mt-1">
<?= $totalBills ?>
</p>

</div>


<div class="bg-white border rounded-2xl p-5">

<p class="text-xs text-slate-500">
Total Amount
</p>

<p class="text-2xl font-bold mt-1">
₹<?= number_format($totalRevenue, 2) ?>
</p>

</div>


<div class="bg-white border rounded-2xl p-5">

<p class="text-xs text-slate-500">
Paid
</p>

<p class="text-2xl font-bold text-green-600 mt-1">
₹<?= number_format($paidAmount, 2) ?>
</p>

</div>


<div class="bg-white border rounded-2xl p-5">

<p class="text-xs text-slate-500">
Pending
</p>

<p class="text-2xl font-bold text-orange-500 mt-1">
₹<?= number_format($pendingAmount, 2) ?>
</p>

</div>


</div>


<!-- BILL LIST -->

<div class="space-y-5">


<?php if ($bills && $bills->num_rows > 0): ?>


<?php while ($bill = $bills->fetch_assoc()): ?>


<?php

$status = $bill["payment_status"] ?? "Pending";

if ($status === "Paid") {

    $statusClass = "bg-green-100 text-green-700";

} elseif ($status === "Partial") {

    $statusClass = "bg-blue-100 text-blue-700";

} else {

    $statusClass = "bg-orange-100 text-orange-700";
}

$total = (float)($bill["total"] ?? $bill["amount"] ?? 0);

?>


<div class="bg-white border border-slate-200
rounded-2xl p-5">


<!-- TOP -->

<div class="flex justify-between items-start gap-4">


<div>

<p class="text-xs text-slate-400">
Bill #<?= intval($bill["bill_id"]) ?>
</p>

<h3 class="text-xl font-bold mt-1">

<?= htmlspecialchars(
$bill["customer_name"] ?? "Customer"
) ?>

</h3>

<p class="text-sm text-slate-500">

<?= htmlspecialchars(
$bill["customer_phone"] ?? ""
) ?>

</p>

</div>


<div class="text-right">

<p class="text-2xl font-bold text-blue-700">

₹<?= number_format($total, 2) ?>

</p>

<span class="<?= $statusClass ?>
inline-block mt-2 px-3 py-1
rounded-full text-xs font-semibold">

<?= htmlspecialchars($status) ?>

</span>

</div>


</div>


<!-- BILL DETAILS -->

<div class="grid grid-cols-2 md:grid-cols-4
gap-3 mt-5">


<div class="bg-slate-50 rounded-xl p-3">

<p class="text-xs text-slate-400">
Parts
</p>

<p class="font-semibold">
₹<?= number_format(
(float)($bill["parts_cost"] ?? 0),
2
) ?>
</p>

</div>


<div class="bg-slate-50 rounded-xl p-3">

<p class="text-xs text-slate-400">
Labor
</p>

<p class="font-semibold">
₹<?= number_format(
(float)($bill["labor_cost"] ?? 0),
2
) ?>
</p>

</div>


<div class="bg-slate-50 rounded-xl p-3">

<p class="text-xs text-slate-400">
Discount
</p>

<p class="font-semibold">
₹<?= number_format(
(float)($bill["discount"] ?? 0),
2
) ?>
</p>

</div>


<div class="bg-slate-50 rounded-xl p-3">

<p class="text-xs text-slate-400">
Payment
</p>

<p class="font-semibold">
<?= htmlspecialchars(
$bill["payment_method"] ?? "-"
) ?>
</p>

</div>


</div>


<!-- REPAIR -->

<div class="mt-4 flex flex-wrap gap-3
text-sm text-slate-500">

<span>
Repair #<?= intval($bill["repair_id"]) ?>
</span>

<?php if (!empty($bill["bill_date"])): ?>

<span>•</span>

<span>
<?= htmlspecialchars($bill["bill_date"]) ?>
</span>

<?php endif; ?>

</div>


<!-- STATUS UPDATE -->

<form
method="POST"
class="mt-5 pt-5 border-t
flex flex-col md:flex-row gap-3">

<input
type="hidden"
name="bill_id"
value="<?= intval($bill["bill_id"]) ?>"
>


<select
name="payment_status"
class="flex-1 border border-slate-300
rounded-xl px-4 py-3 bg-white">

<option value="Pending"
<?= $status === "Pending" ? "selected" : "" ?>>
Pending
</option>

<option value="Partial"
<?= $status === "Partial" ? "selected" : "" ?>>
Partial
</option>

<option value="Paid"
<?= $status === "Paid" ? "selected" : "" ?>>
Paid
</option>

</select>


<button
type="submit"
class="bg-blue-700 hover:bg-blue-800
text-white rounded-xl px-6 py-3
font-semibold">

Update Payment

</button>

</form>


</div>


<?php endwhile; ?>


<?php else: ?>


<div class="bg-white border rounded-2xl p-10 text-center">

<span class="material-symbols-outlined
text-6xl text-slate-300">

receipt_long

</span>

<h3 class="font-semibold text-lg mt-3">
No Bills Yet
</h3>

<p class="text-sm text-slate-500 mt-1">
Created invoices will appear here.
</p>

<a
href="billing.php"
class="inline-flex items-center gap-2
mt-5 bg-blue-700 text-white
px-5 py-3 rounded-xl font-semibold">

<span class="material-symbols-outlined">
add
</span>

Create Bill

</a>

</div>


<?php endif; ?>


</div>

</main>


<!-- BOTTOM NAV -->

<nav class="fixed bottom-0 left-0 right-0
bg-white border-t z-50">

<div class="max-w-5xl mx-auto flex justify-around py-2">


<a href="dashboard.php"
class="flex flex-col items-center text-slate-500 px-3 py-2">

<span class="material-symbols-outlined">
dashboard
</span>

<span class="text-xs mt-1">
Home
</span>

</a>


<a href="repair.php"
class="flex flex-col items-center text-slate-500 px-3 py-2">

<span class="material-symbols-outlined">
build
</span>

<span class="text-xs mt-1">
Repairs
</span>

</a>


<a href="customers.php"
class="flex flex-col items-center text-slate-500 px-3 py-2">

<span class="material-symbols-outlined">
groups
</span>

<span class="text-xs mt-1">
Customers
</span>

</a>


<a href="inventory.php"
class="flex flex-col items-center text-slate-500 px-3 py-2">

<span class="material-symbols-outlined">
inventory_2
</span>

<span class="text-xs mt-1">
Stock
</span>

</a>


<a href="billing.php"
class="flex flex-col items-center text-blue-700 px-3 py-2">

<span class="material-symbols-outlined">
receipt_long
</span>

<span class="text-xs mt-1">
Billing
</span>

</a>


</div>

</nav>


</body>
</html>