<?php

session_start();
require_once "db.php";

/*
=========================================================
 REPXA - STAFF / AGENT DASHBOARD
 Owner uses dashboard.php
 All non-owner staff use this page.
=========================================================
*/

/* =====================================================
   AUTHENTICATION
===================================================== */

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$currentUserId = intval($_SESSION["user_id"] ?? 0);
$currentUserName = trim($_SESSION["user_name"] ?? "");
$currentUsername = trim($_SESSION["username"] ?? "");
$currentRole = strtolower(trim($_SESSION["role"] ?? ""));

/* Recover session values from users if any value is missing. */
if ($currentUserId <= 0) {
    header("Location: login.php");
    exit;
}

if ($currentUserName === "" || $currentUsername === "" || $currentRole === "") {
    $userStmt = $conn->prepare("
        SELECT id, name, username, role
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    if ($userStmt) {
        $userStmt->bind_param("i", $currentUserId);
        $userStmt->execute();
        $userResult = $userStmt->get_result();

        if ($userResult && $userResult->num_rows === 1) {
            $dbUser = $userResult->fetch_assoc();
            $currentUserName = trim($dbUser["name"] ?? $currentUserName);
            $currentUsername = trim($dbUser["username"] ?? $currentUsername);
            $currentRole = strtolower(trim($dbUser["role"] ?? $currentRole));

            $_SESSION["user_name"] = $currentUserName;
            $_SESSION["username"] = $currentUsername;
            $_SESSION["role"] = $currentRole;
        }
        $userStmt->close();
    }
}

if ($currentUsername === "" || $currentRole === "") {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

/* Owner should always use the full dashboard. */
if ($currentRole === "owner") {
    header("Location: dashboard.php");
    exit;
}

/* =====================================================
   FIND STAFF RECORD
===================================================== */

$staffId = 0;
$staffName = $currentUserName;
$staffRole = $currentRole;
$staffCode = $currentUsername;

$stmt = $conn->prepare("
    SELECT staff_id, name, agent_id, role
    FROM staff
    WHERE user_id = ?
    LIMIT 1
");

if ($stmt) {
    $stmt->bind_param("i", $currentUserId);
    $stmt->execute();
    $staffResult = $stmt->get_result();

    if ($staffResult && $staffResult->num_rows === 1) {
        $staff = $staffResult->fetch_assoc();

        $staffId = intval($staff["staff_id"]);
        $staffName = $staff["name"] ?: $staffName;
        $staffRole = strtolower(trim($staff["role"] ?: $staffRole));
        $staffCode = $staff["agent_id"] ?: $staffCode;
    }

    $stmt->close();
}

/*
 If the staff row is not linked yet, try the staff ID/username.
 This also helps older staff records.
*/
if ($staffId <= 0 && $staffCode !== "") {
    $stmt = $conn->prepare("
        SELECT staff_id, name, agent_id, role, user_id
        FROM staff
        WHERE agent_id = ?
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param("s", $staffCode);
        $stmt->execute();
        $staffResult = $stmt->get_result();

        if ($staffResult && $staffResult->num_rows === 1) {
            $staff = $staffResult->fetch_assoc();

            $staffId = intval($staff["staff_id"]);
            $staffName = $staff["name"] ?: $staffName;
            $staffRole = strtolower(trim($staff["role"] ?: $staffRole));
            $staffCode = $staff["agent_id"] ?: $staffCode;
        }

        $stmt->close();
    }
}

/* =====================================================
   COUNTS
===================================================== */

$pending = 0;
$progress = 0;
$completed = 0;
$totalAssigned = 0;

if ($staffId > 0) {

    $stmt = $conn->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(status = 'Pending') AS pending,
            SUM(status = 'In Progress') AS progress,
            SUM(status IN ('Completed','Delivered')) AS completed
        FROM repairs
        WHERE assigned_staff_id = ?
    ");

    if ($stmt) {
        $stmt->bind_param("i", $staffId);
        $stmt->execute();
        $countResult = $stmt->get_result();

        if ($countResult && $countResult->num_rows === 1) {
            $counts = $countResult->fetch_assoc();

            $totalAssigned = intval($counts["total"] ?? 0);
            $pending = intval($counts["pending"] ?? 0);
            $progress = intval($counts["progress"] ?? 0);
            $completed = intval($counts["completed"] ?? 0);
        }

        $stmt->close();
    }
}

/* =====================================================
   ASSIGNED REPAIRS
===================================================== */

$repairs = null;

if ($staffId > 0) {

    $stmt = $conn->prepare("
        SELECT
            r.repair_id,
            r.device_type,
            r.brand,
            r.model,
            r.issue,
            r.status,
            c.name AS customer_name,
            c.phone AS customer_phone
        FROM repairs r
        LEFT JOIN customers c
            ON r.customer_id = c.customer_id
        WHERE r.assigned_staff_id = ?
        ORDER BY r.repair_id DESC
        LIMIT 20
    ");

    if ($stmt) {
        $stmt->bind_param("i", $staffId);
        $stmt->execute();
        $repairs = $stmt->get_result();
    }
}

function statusClass($status)
{
    switch ($status) {
        case "Pending":
            return "bg-orange-100 text-orange-700";
        case "In Progress":
            return "bg-blue-100 text-blue-700";
        case "Completed":
            return "bg-green-100 text-green-700";
        case "Delivered":
            return "bg-purple-100 text-purple-700";
        default:
            return "bg-slate-100 text-slate-700";
    }
}

function roleLabel($role)
{
    $role = strtolower(trim($role));

    $map = [
        "agent" => "Agent",
        "technician" => "Technician",
        "repair technician" => "Repair Technician",
        "manager" => "Manager",
        "receptionist" => "Receptionist",
        "helper" => "Helper",
        "other" => "Staff"
    ];

    return $map[$role] ?? ucwords($role);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>REPXA - Staff Dashboard</title>

<script src="https://cdn.tailwindcss.com"></script>

<link
href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"
rel="stylesheet">

</head>

<body class="bg-slate-50 text-slate-900">

<!-- =====================================================
     HEADER
===================================================== -->

<header class="bg-white border-b sticky top-0 z-50">

<div class="max-w-5xl mx-auto px-5 h-16 flex items-center justify-between">

<div>

<h1 class="font-bold text-lg text-blue-700">
REPXA
</h1>

<p class="text-xs text-slate-500">
Staff Dashboard
</p>

</div>

<button
type="button"
onclick="toggleMenu()"
class="w-10 h-10 rounded-xl hover:bg-slate-100 flex items-center justify-center">

<span class="material-symbols-outlined">
menu
</span>

</button>

</div>

</header>


<!-- =====================================================
     SIDE MENU
===================================================== -->

<div
id="sideMenu"
class="hidden fixed inset-0 z-[100]">

<div
onclick="toggleMenu()"
class="absolute inset-0 bg-black/40">
</div>

<aside
class="absolute right-0 top-0 bottom-0 w-[310px] max-w-[88vw] bg-white shadow-2xl flex flex-col">

<div class="p-5 border-b">

<div class="flex items-center justify-between">

<div>

<h2 class="font-bold text-lg text-blue-700">
REPXA
</h2>

<p class="text-xs text-slate-500">
<?= htmlspecialchars(roleLabel($staffRole)) ?>
</p>

</div>

<button
type="button"
onclick="toggleMenu()"
class="w-9 h-9 rounded-full hover:bg-slate-100 flex items-center justify-center">

<span class="material-symbols-outlined">
close
</span>

</button>

</div>

</div>


<div class="flex-1 overflow-y-auto p-4 space-y-1">

<a
href="agent_dashboard.php"
class="flex items-center gap-3 p-3 rounded-xl bg-blue-50 text-blue-700 font-semibold">

<span class="material-symbols-outlined">
dashboard
</span>

<span>Dashboard</span>

</a>


<a
href="repair.php"
class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-100">

<span class="material-symbols-outlined">
build
</span>

<span>New Repair Request</span>

</a>


<a
href="status.php"
class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-100">

<span class="material-symbols-outlined">
track_changes
</span>

<span>Repair Status</span>

</a>


<a
href="customers.php"
class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-100">

<span class="material-symbols-outlined">
groups
</span>

<span>Customers</span>

</a>


<a
href="inventory.php"
class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-100">

<span class="material-symbols-outlined">
inventory_2
</span>

<span>Inventory</span>

</a>


<a
href="billing.php"
class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-100">

<span class="material-symbols-outlined">
receipt_long
</span>

<span>Billing</span>

</a>


<a
href="profile.php"
class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-100">

<span class="material-symbols-outlined">
person
</span>

<span>My Profile</span>

</a>

</div>


<div class="p-4 border-t">

<div class="bg-slate-50 border rounded-2xl p-3 mb-3">

<p class="font-semibold text-sm truncate">
<?= htmlspecialchars($staffName) ?>
</p>

<p class="text-xs text-slate-500 mt-1">
<?= htmlspecialchars($staffCode) ?>
</p>

<p class="text-xs text-slate-500 mt-1">
<?= htmlspecialchars(roleLabel($staffRole)) ?>
</p>

</div>


<a
href="logout.php"
class="w-full border border-red-200 text-red-600 hover:bg-red-50 rounded-xl py-3 flex items-center justify-center gap-2 font-semibold">

<span class="material-symbols-outlined">
logout
</span>

Logout

</a>

</div>

</aside>

</div>


<!-- =====================================================
     MAIN
===================================================== -->

<main class="max-w-5xl mx-auto px-5 py-7">


<!-- WELCOME -->

<div class="mb-6">

<p class="text-xs font-semibold tracking-wider text-slate-500">
STAFF WORKSPACE
</p>

<h2 class="text-2xl font-bold mt-1">
Welcome, <?= htmlspecialchars($staffName) ?>
</h2>

<p class="text-sm text-slate-500 mt-1">
<?= htmlspecialchars(roleLabel($staffRole)) ?>
&nbsp;•&nbsp;
<?= htmlspecialchars($staffCode) ?>
</p>

</div>


<!-- SUMMARY -->

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">


<div class="bg-white border rounded-2xl p-5">

<p class="text-sm text-slate-500">
Assigned
</p>

<p class="text-3xl font-bold text-blue-700 mt-2">
<?= $totalAssigned ?>
</p>

</div>


<div class="bg-white border rounded-2xl p-5">

<p class="text-sm text-slate-500">
Pending
</p>

<p class="text-3xl font-bold text-orange-500 mt-2">
<?= $pending ?>
</p>

</div>


<div class="bg-white border rounded-2xl p-5">

<p class="text-sm text-slate-500">
In Progress
</p>

<p class="text-3xl font-bold text-blue-600 mt-2">
<?= $progress ?>
</p>

</div>


<div class="bg-white border rounded-2xl p-5">

<p class="text-sm text-slate-500">
Completed
</p>

<p class="text-3xl font-bold text-green-600 mt-2">
<?= $completed ?>
</p>

</div>

</div>


<!-- QUICK ACTIONS -->

<h2 class="text-xl font-bold mb-4">
Quick Actions
</h2>


<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">


<a
href="repair.php"
class="bg-blue-700 hover:bg-blue-800 text-white rounded-2xl p-6">

<div
class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center">

<span class="material-symbols-outlined text-3xl">
add
</span>

</div>

<h3 class="font-bold text-lg mt-4">
New Repair Request
</h3>

<p class="text-sm text-blue-100 mt-1">
Create a customer repair request
</p>

</a>


<a
href="status.php"
class="bg-white border rounded-2xl p-6 hover:border-blue-400">

<div
class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center">

<span class="material-symbols-outlined text-blue-700 text-3xl">
track_changes
</span>

</div>

<h3 class="font-bold text-lg mt-4">
Repair Status
</h3>

<p class="text-sm text-slate-500 mt-1">
View and manage repair progress
</p>

</a>


<a
href="customers.php"
class="bg-white border rounded-2xl p-6 hover:border-blue-400">

<div
class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center">

<span class="material-symbols-outlined text-slate-700 text-3xl">
groups
</span>

</div>

<h3 class="font-bold text-lg mt-4">
Customers
</h3>

<p class="text-sm text-slate-500 mt-1">
View customer repair information
</p>

</a>

</div>


<!-- ASSIGNED REPAIRS -->

<div class="flex items-center justify-between mb-4">

<div>

<h2 class="text-xl font-bold">
My Assigned Repairs
</h2>

<p class="text-sm text-slate-500 mt-1">
Repairs assigned to you by the owner.
</p>

</div>

<a
href="status.php"
class="text-blue-700 text-sm font-semibold">

View All

</a>

</div>


<div class="space-y-4">


<?php if ($repairs && $repairs->num_rows > 0): ?>

<?php while ($repair = $repairs->fetch_assoc()): ?>

<div
class="bg-white border rounded-2xl p-5">


<div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">


<div class="flex gap-4">


<div
class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center shrink-0">

<span class="material-symbols-outlined text-blue-700">
smartphone
</span>

</div>


<div class="min-w-0">

<p class="text-xs font-semibold text-slate-400">
REPAIR #<?= intval($repair["repair_id"]) ?>
</p>

<h3 class="font-bold text-lg mt-1">
<?= htmlspecialchars($repair["customer_name"] ?? "Customer") ?>
</h3>

<p class="text-sm text-slate-500 mt-1">
<?= htmlspecialchars($repair["customer_phone"] ?? "") ?>
</p>

<p class="text-sm text-slate-700 mt-3">
<strong><?= htmlspecialchars($repair["brand"] ?? "") ?></strong>
<?= htmlspecialchars($repair["model"] ?? "") ?>
</p>

<p class="text-sm text-slate-500 mt-1">
<?= htmlspecialchars($repair["device_type"] ?? "") ?>
&nbsp;•&nbsp;
<?= htmlspecialchars($repair["issue"] ?? "") ?>
</p>

</div>

</div>


<span
class="shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold <?= statusClass($repair["status"] ?? "") ?>">

<?= htmlspecialchars($repair["status"] ?? "Pending") ?>

</span>


</div>


<div class="mt-5 pt-4 border-t flex flex-wrap gap-3">

<a
href="status.php"
class="inline-flex items-center gap-2 bg-slate-100 hover:bg-blue-50 text-slate-700 hover:text-blue-700 px-4 py-2.5 rounded-xl text-sm font-semibold">

<span class="material-symbols-outlined text-lg">
visibility
</span>

View Repair

</a>


<a
href="repair.php"
class="inline-flex items-center gap-2 bg-blue-50 hover:bg-blue-100 text-blue-700 px-4 py-2.5 rounded-xl text-sm font-semibold">

<span class="material-symbols-outlined text-lg">
add
</span>

New Repair

</a>

</div>

</div>

<?php endwhile; ?>

<?php else: ?>

<div
class="bg-white border rounded-2xl p-10 text-center">

<span
class="material-symbols-outlined text-5xl text-slate-300">
assignment
</span>

<h3 class="font-semibold text-lg mt-3">
No Repairs Assigned
</h3>

<p class="text-sm text-slate-500 mt-1">
Repairs assigned to you will appear here.
</p>

<a
href="repair.php"
class="inline-flex items-center gap-2 mt-5 bg-blue-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold">

<span class="material-symbols-outlined">
add
</span>

Create Repair Request

</a>

</div>

<?php endif; ?>

</div>


</main>


<script>

function toggleMenu() {

    const menu =
        document.getElementById("sideMenu");

    if (!menu) return;

    menu.classList.toggle("hidden");

}


document.addEventListener(
    "keydown",
    function(event) {

        if (event.key === "Escape") {

            const menu =
                document.getElementById("sideMenu");

            if (
                menu &&
                !menu.classList.contains("hidden")
            ) {
                toggleMenu();
            }

        }

    }
);

</script>

</body>
</html>
