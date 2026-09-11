<?php

/* =========================================================
   AUTHENTICATION
========================================================= */

require_once "auth.php";
require_once "db.php";

/* =====================================================
   OWNER DASHBOARD ONLY
===================================================== */

$currentDashboardRole =
    strtolower(trim($_SESSION["role"] ?? ""));

if ($currentDashboardRole !== "owner") {
    header("Location: agent_dashboard.php");
    exit;
}



/* =========================
   DASHBOARD COUNTS
========================= */

$pending = 0;
$progress = 0;
$completed = 0;
$totalRepairs = 0;


/* =========================
   TOTAL REPAIRS
========================= */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM repairs
");

if ($result) {

    $row = $result->fetch_assoc();

    $totalRepairs =
        intval($row["total"]);
}


/* =========================
   PENDING
========================= */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM repairs
    WHERE status = 'Pending'
");

if ($result) {

    $pending =
        intval(
            $result->fetch_assoc()["total"]
        );
}


/* =========================
   IN PROGRESS
========================= */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM repairs
    WHERE status = 'In Progress'
");

if ($result) {

    $progress =
        intval(
            $result->fetch_assoc()["total"]
        );
}


/* =========================
   COMPLETED + DELIVERED
========================= */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM repairs
    WHERE status IN ('Completed','Delivered')
");

if ($result) {

    $completed =
        intval(
            $result->fetch_assoc()["total"]
        );
}


/* =========================
   RECENT REPAIRS
========================= */

$recentRepairs = $conn->query("
    SELECT
        r.repair_id,
        r.device_type,
        r.brand,
        r.model,
        r.issue,
        r.status,
        c.name AS customer_name
    FROM repairs r
    LEFT JOIN customers c
        ON r.customer_id = c.customer_id
    ORDER BY r.repair_id DESC
    LIMIT 5
");

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0">

<title>REPXA Dashboard</title>

<script src="https://cdn.tailwindcss.com"></script>

<link
href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"
rel="stylesheet">

</head>


<body class="bg-slate-50 text-slate-900">


<!-- =====================================================
     COMMON HEADER + MENU
===================================================== -->

<?php require_once "common_menu.php"; ?>






<!-- =====================================================
     MAIN DASHBOARD
===================================================== -->

<main class="max-w-5xl mx-auto px-5 py-7">


<!-- =====================================================
     TITLE
===================================================== -->

<div
class="flex items-center justify-between mb-6">

<div>

<h2 class="text-2xl font-bold">
Today's Summary
</h2>

<p class="text-sm text-slate-500 mt-1">
REPXA repair operations overview
</p>

</div>


<div class="text-sm text-slate-500">

<?= date("d M Y") ?>

</div>

</div>



<!-- =====================================================
     SUMMARY CARDS
===================================================== -->

<div
class="grid grid-cols-1 sm:grid-cols-3
gap-4 mb-7">


<!-- =====================================================
     PENDING
===================================================== -->

<div
class="bg-white border rounded-2xl p-5">

<div
class="flex justify-between items-start">

<div>

<p class="text-sm text-slate-500">
Pending
</p>

<p
class="text-3xl font-bold
text-orange-500 mt-2">

<?= $pending ?>

</p>

</div>


<div
class="w-11 h-11 rounded-xl
bg-orange-50 flex items-center justify-center">

<span
class="material-symbols-outlined
text-orange-500">

pending_actions

</span>

</div>

</div>


<span
class="inline-block mt-4 px-3 py-1
rounded-full bg-orange-100
text-orange-700 text-xs font-semibold">

ATTENTION

</span>

</div>



<!-- =====================================================
     IN PROGRESS
===================================================== -->

<div
class="bg-white border rounded-2xl p-5">

<div
class="flex justify-between items-start">

<div>

<p class="text-sm text-slate-500">
In Progress
</p>

<p
class="text-3xl font-bold
text-blue-600 mt-2">

<?= $progress ?>

</p>

</div>


<div
class="w-11 h-11 rounded-xl
bg-blue-50 flex items-center justify-center">

<span
class="material-symbols-outlined
text-blue-600">

engineering

</span>

</div>

</div>


<span
class="inline-block mt-4 px-3 py-1
rounded-full bg-blue-100
text-blue-700 text-xs font-semibold">

ACTIVE

</span>

</div>



<!-- =====================================================
     COMPLETED
===================================================== -->

<div
class="bg-white border rounded-2xl p-5">

<div
class="flex justify-between items-start">

<div>

<p class="text-sm text-slate-500">
Completed
</p>

<p
class="text-3xl font-bold
text-green-600 mt-2">

<?= $completed ?>

</p>

</div>


<div
class="w-11 h-11 rounded-xl
bg-green-50 flex items-center justify-center">

<span
class="material-symbols-outlined
text-green-600">

task_alt

</span>

</div>

</div>


<span
class="inline-block mt-4 px-3 py-1
rounded-full bg-green-100
text-green-700 text-xs font-semibold">

SUCCESS

</span>

</div>


</div>



<!-- =====================================================
     QUICK ACTIONS
===================================================== -->

<h2 class="text-xl font-bold mb-4">
Quick Actions
</h2>


<div
class="grid grid-cols-1 md:grid-cols-3
gap-4 mb-8">


<!-- NEW REPAIR -->

<a
href="repair.php"
class="bg-blue-700 hover:bg-blue-800
text-white rounded-2xl p-6">

<div
class="w-12 h-12 rounded-full
bg-white/20 flex items-center justify-center">

<span
class="material-symbols-outlined text-3xl">

add

</span>

</div>


<h3 class="font-bold text-lg mt-4">
New Repair Job
</h3>


<p class="text-sm text-blue-100 mt-1">
Create a new customer repair request
</p>

</a>



<!-- CREATE BILL -->

<a
href="billing.php"
class="bg-white border rounded-2xl p-6
hover:border-blue-400">

<div
class="w-12 h-12 rounded-full
bg-blue-50 flex items-center justify-center">

<span
class="material-symbols-outlined
text-blue-700 text-3xl">

receipt_long

</span>

</div>


<h3 class="font-bold text-lg mt-4">
Create Bill
</h3>


<p class="text-sm text-slate-500 mt-1">
Generate invoice for completed repair
</p>

</a>



<!-- MANAGE STOCK -->

<a
href="inventory.php"
class="bg-white border rounded-2xl p-6
hover:border-blue-400">

<div
class="w-12 h-12 rounded-full
bg-green-50 flex items-center justify-center">

<span
class="material-symbols-outlined
text-green-700 text-3xl">

inventory_2

</span>

</div>


<h3 class="font-bold text-lg mt-4">
Manage Stock
</h3>


<p class="text-sm text-slate-500 mt-1">
Check and manage spare parts
</p>

</a>


</div>



<!-- =====================================================
     RECENT REQUESTS
===================================================== -->

<div
class="flex justify-between
items-center mb-4">

<h2 class="text-xl font-bold">
Recent Requests
</h2>


<a
href="status.php"
class="text-blue-700
text-sm font-semibold">

View All

</a>

</div>



<div class="space-y-3">


<?php if (
    $recentRepairs &&
    $recentRepairs->num_rows > 0
): ?>


<?php while (
    $repair =
    $recentRepairs->fetch_assoc()
): ?>


<a
href="status.php"
class="block bg-white border
rounded-2xl p-4
hover:border-blue-400">


<div
class="flex items-center
justify-between gap-3">


<!-- REPAIR INFO -->

<div
class="flex items-center gap-4">


<div
class="w-12 h-12 rounded-xl
bg-blue-50 flex items-center justify-center">

<span
class="material-symbols-outlined
text-blue-700">

smartphone

</span>

</div>


<div>

<h3 class="font-semibold">

<?= htmlspecialchars(
    $repair["customer_name"]
    ?? "Customer"
) ?>

</h3>


<p class="text-sm text-slate-500">

<?= htmlspecialchars(
    $repair["brand"]
) ?>

<?= htmlspecialchars(
    $repair["model"]
) ?>

• <?= htmlspecialchars(
    $repair["issue"]
) ?>

</p>

</div>

</div>



<!-- STATUS -->

<?php

$status =
    $repair["status"];

$class =
    "bg-slate-100 text-slate-700";


if ($status === "Pending") {

    $class =
        "bg-orange-100 text-orange-700";
}


if ($status === "In Progress") {

    $class =
        "bg-blue-100 text-blue-700";
}


if ($status === "Completed") {

    $class =
        "bg-green-100 text-green-700";
}


if ($status === "Delivered") {

    $class =
        "bg-purple-100 text-purple-700";
}

?>


<span
class="shrink-0 px-3 py-1.5
rounded-full text-xs font-semibold
<?= $class ?>">

<?= htmlspecialchars($status) ?>

</span>


</div>

</a>


<?php endwhile; ?>


<?php else: ?>


<!-- NO REQUESTS -->

<div
class="bg-white border rounded-2xl
p-8 text-center">

<span
class="material-symbols-outlined
text-5xl text-slate-300">

inbox

</span>


<p class="font-semibold mt-3">
No repair requests yet
</p>


<a
href="repair.php"
class="inline-block mt-4
bg-blue-700 text-white
px-5 py-2.5 rounded-xl
text-sm font-semibold">

Create First Repair

</a>

</div>


<?php endif; ?>


</div>


</main>


</body>

</html>