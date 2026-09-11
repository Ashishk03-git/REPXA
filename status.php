<?php

require_once "auth.php";
require_once "db.php";

/*
=========================================================
REPXA - REPAIR STATUS
=========================================================
Owner:
- Can see and update all repairs.

Staff:
- Can see and update only repairs assigned to them.
- Assignment information shows Name + Staff ID + Role.
=========================================================
*/

$message = "";
$messageType = "success";

$currentUserId = intval($_SESSION["user_id"] ?? 0);
$currentUserRole = strtolower(trim($_SESSION["role"] ?? ""));
$isOwner = ($currentUserRole === "owner");

$staffId = 0;

/* =====================================================
   FIND CURRENT STAFF RECORD
===================================================== */

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

    if ($staffId <= 0) {

        $currentUsername = trim($_SESSION["username"] ?? "");

        if ($currentUsername !== "") {

            $stmt = $conn->prepare("
                SELECT staff_id
                FROM staff
                WHERE agent_id = ?
                LIMIT 1
            ");

            if ($stmt) {

                $stmt->bind_param("s", $currentUsername);
                $stmt->execute();

                $result = $stmt->get_result();

                if ($result && $result->num_rows === 1) {
                    $staffId = intval($result->fetch_assoc()["staff_id"]);
                }

                $stmt->close();
            }
        }
    }
}


/* =====================================================
   EDIT / UPDATE REPAIR
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    if ($action === "update_repair") {

        $repairId = intval($_POST["repair_id"] ?? 0);

        $deviceType = trim($_POST["device_type"] ?? "");
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
            $repairId <= 0 ||
            $deviceType === "" ||
            $brand === "" ||
            $model === "" ||
            $issue === "" ||
            !in_array($status, $allowedStatuses, true)
        ) {

            $message = "Please fill all repair details correctly.";
            $messageType = "error";

        } else {

            /*
             Staff may update only a repair assigned to them.
             Owner may update any repair.
            */
            if ($isOwner) {

                $stmt = $conn->prepare("
                    UPDATE repairs
                    SET
                        device_type = ?,
                        brand = ?,
                        model = ?,
                        issue = ?,
                        status = ?
                    WHERE repair_id = ?
                ");

                if ($stmt) {

                    $stmt->bind_param(
                        "sssssi",
                        $deviceType,
                        $brand,
                        $model,
                        $issue,
                        $status,
                        $repairId
                    );

                }

            } else {

                $stmt = $conn->prepare("
                    UPDATE repairs
                    SET
                        device_type = ?,
                        brand = ?,
                        model = ?,
                        issue = ?,
                        status = ?
                    WHERE repair_id = ?
                    AND assigned_staff_id = ?
                ");

                if ($stmt) {

                    $stmt->bind_param(
                        "ssss sii",
                        $deviceType,
                        $brand,
                        $model,
                        $issue,
                        $status,
                        $repairId,
                        $staffId
                    );

                }
            }

            if (isset($stmt) && $stmt) {

                /* Fix binding type string for staff query. */
                if (!$isOwner) {
                    $stmt->bind_param(
                        "ssssssi",
                        $deviceType,
                        $brand,
                        $model,
                        $issue,
                        $status,
                        $repairId,
                        $staffId
                    );
                }

                if ($stmt->execute()) {

                    if ($stmt->affected_rows > 0 || $stmt->errno === 0) {

                        $message =
                            "Repair details updated successfully!";

                        $messageType =
                            "success";

                    } else {

                        $message =
                            "Repair was not found or you are not allowed to update it.";

                        $messageType =
                            "error";
                    }

                } else {

                    $message =
                        "Unable to update repair details.";

                    $messageType =
                        "error";
                }

                $stmt->close();

            } else {

                $message =
                    "Unable to prepare repair update.";

                $messageType =
                    "error";
            }
        }
    }
}


/* =====================================================
   FILTERS
===================================================== */

$selectedStatus = trim($_GET["status"] ?? "");
$search = trim($_GET["search"] ?? "");

$allowedStatuses = [
    "Pending",
    "In Progress",
    "Completed",
    "Delivered"
];

if (!in_array($selectedStatus, $allowedStatuses, true)) {
    $selectedStatus = "";
}


/* =====================================================
   COUNTS
===================================================== */

if ($isOwner) {

    $countResult = $conn->query("
        SELECT
            SUM(status = 'Pending') AS pending,
            SUM(status = 'In Progress') AS progress,
            SUM(status = 'Completed') AS completed,
            SUM(status = 'Delivered') AS delivered
        FROM repairs
    ");

} else {

    $stmtCount = $conn->prepare("
        SELECT
            SUM(status = 'Pending') AS pending,
            SUM(status = 'In Progress') AS progress,
            SUM(status = 'Completed') AS completed,
            SUM(status = 'Delivered') AS delivered
        FROM repairs
        WHERE assigned_staff_id = ?
    ");

    if ($stmtCount) {
        $stmtCount->bind_param("i", $staffId);
        $stmtCount->execute();
        $countResult = $stmtCount->get_result();
    } else {
        $countResult = false;
    }
}

$counts = $countResult ? $countResult->fetch_assoc() : [];

$pending = intval($counts["pending"] ?? 0);
$progress = intval($counts["progress"] ?? 0);
$completed = intval($counts["completed"] ?? 0);
$delivered = intval($counts["delivered"] ?? 0);


/* =====================================================
   GET REPAIRS
===================================================== */

$sql = "
    SELECT
        r.repair_id,
        r.device_type,
        r.brand,
        r.model,
        r.issue,
        r.status,
        r.created_at,
        c.name AS customer_name,
        c.phone AS customer_phone,

        s.name AS assigned_staff_name,
        s.agent_id AS assigned_staff_code,
        s.role AS assigned_staff_role

    FROM repairs r

    LEFT JOIN customers c
        ON r.customer_id = c.customer_id

    LEFT JOIN staff s
        ON r.assigned_staff_id = s.staff_id

    WHERE 1=1
";

$params = [];
$types = "";

if (!$isOwner) {

    $sql .= "
        AND r.assigned_staff_id = ?
    ";

    $params[] = $staffId;
    $types .= "i";
}

if ($selectedStatus !== "") {

    $sql .= "
        AND r.status = ?
    ";

    $params[] = $selectedStatus;
    $types .= "s";
}

if ($search !== "") {

    $sql .= "
        AND (
            c.name LIKE ?
            OR c.phone LIKE ?
            OR r.device_type LIKE ?
            OR r.brand LIKE ?
            OR r.model LIKE ?
            OR r.issue LIKE ?
            OR CAST(r.repair_id AS CHAR) LIKE ?
            OR s.name LIKE ?
            OR s.agent_id LIKE ?
        )
    ";

    $term = "%" . $search . "%";

    for ($i = 0; $i < 9; $i++) {
        $params[] = $term;
        $types .= "s";
    }
}

$sql .= "
    ORDER BY r.repair_id DESC
";

$previewMode =
    ($selectedStatus === "" && $search === "");

if ($previewMode) {
    $sql .= " LIMIT 5";
}

$stmt = $conn->prepare($sql);

if ($stmt) {

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $repairs = $stmt->get_result();

} else {

    $repairs = false;
}


/* =====================================================
   EXISTING PAGE HTML
===================================================== */

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0">

<title>REPXA - Repair Status</title>

<script src="https://cdn.tailwindcss.com"></script>

<link
href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"
rel="stylesheet">


<style>

.status-card {
    transition: .2s;
}

.status-card:hover {
    transform: translateY(-2px);
}

</style>

</head>


<body class="bg-slate-50 text-slate-900">


<?php

$menuProfile = [

    "owner_name"  => "REPXA Owner",
    "shop_name"   => "Your Shop Name",
    "owner_photo" => ""

];


$menuResult = $conn->query("
    SELECT owner_name, shop_name, owner_photo
    FROM company_profile
    ORDER BY id ASC
    LIMIT 1
");


if (
    $menuResult &&
    $menuResult->num_rows > 0
) {

    $menuProfile =
        array_merge(
            $menuProfile,
            $menuResult->fetch_assoc()
        );
}


$menuPhotoExists =
    !empty(
        $menuProfile["owner_photo"]
    )
    &&
    file_exists(
        __DIR__ . "/" .
        $menuProfile["owner_photo"]
    );


$currentPage =
    basename(
        $_SERVER["PHP_SELF"]
    );

?>


<header
class="bg-white border-b sticky top-0 z-50">

<div
class="max-w-5xl mx-auto px-5 h-16
flex items-center justify-between">


<div
class="flex items-center gap-3">


<a
href="repair.php"
class="w-10 h-10 rounded-full
hover:bg-slate-100
flex items-center justify-center"
aria-label="Back to Repair">

<span
class="material-symbols-outlined">

arrow_back

</span>

</a>


<div>

<h1
class="font-bold text-lg text-blue-700">

Repair Status

</h1>


<p
class="text-xs text-slate-500">

Repair Management

</p>

</div>


</div>


</div>

</header>



<main
class="max-w-5xl mx-auto px-5 py-7">


<!-- =====================================================
     SUCCESS / ERROR
===================================================== -->

<?php if ($message !== ""): ?>

<div
class="mb-6 p-4 rounded-xl font-medium
<?= $messageType === "success"
    ? "bg-green-100 text-green-700 border border-green-200"
    : "bg-red-100 text-red-700 border border-red-200"
?>">


<span
class="material-symbols-outlined
align-middle mr-1">

<?= $messageType === "success"
    ? "check_circle"
    : "error"
?>

</span>


<?= htmlspecialchars($message) ?>


</div>

<?php endif; ?>



<!-- =====================================================
     TITLE
===================================================== -->

<div class="mb-6">

<p
class="text-xs font-semibold
text-slate-500 tracking-wider">

SERVICE TRACKING

</p>


<h2
class="text-2xl font-bold mt-1">

Repair Status

</h2>


<p
class="text-sm text-slate-500 mt-1">

Monitor, search and update repair jobs.

</p>

</div>



<!-- =====================================================
     STATUS SUMMARY
===================================================== -->

<div
class="grid grid-cols-2 md:grid-cols-4
gap-4 mb-7">


<!-- PENDING -->

<a
href="status.php?status=Pending"
class="status-card bg-white border
rounded-2xl p-5
<?= $selectedStatus === "Pending"
    ? "ring-2 ring-orange-400"
    : ""
?>">


<div
class="flex items-center justify-between">


<div>

<p
class="text-sm text-slate-500">

Pending

</p>


<p
class="text-3xl font-bold
text-orange-500 mt-1">

<?= $pending ?>

</p>

</div>


<div
class="w-11 h-11 rounded-xl
bg-orange-50 flex items-center
justify-center">

<span
class="material-symbols-outlined
text-orange-500">

pending

</span>

</div>


</div>


<p
class="text-xs text-orange-600
mt-3 font-semibold">

View Pending Jobs →

</p>


</a>



<!-- IN PROGRESS -->

<a
href="status.php?status=In%20Progress"
class="status-card bg-white border
rounded-2xl p-5
<?= $selectedStatus === "In Progress"
    ? "ring-2 ring-blue-400"
    : ""
?>">


<div
class="flex items-center justify-between">


<div>

<p
class="text-sm text-slate-500">

In Progress

</p>


<p
class="text-3xl font-bold
text-blue-600 mt-1">

<?= $progress ?>

</p>

</div>


<div
class="w-11 h-11 rounded-xl
bg-blue-50 flex items-center
justify-center">

<span
class="material-symbols-outlined
text-blue-600">

autorenew

</span>

</div>


</div>


<p
class="text-xs text-blue-600
mt-3 font-semibold">

View In Progress Jobs →

</p>


</a>



<!-- COMPLETED -->

<a
href="status.php?status=Completed"
class="status-card bg-white border
rounded-2xl p-5
<?= $selectedStatus === "Completed"
    ? "ring-2 ring-green-400"
    : ""
?>">


<div
class="flex items-center justify-between">


<div>

<p
class="text-sm text-slate-500">

Completed

</p>


<p
class="text-3xl font-bold
text-green-600 mt-1">

<?= $completed ?>

</p>

</div>


<div
class="w-11 h-11 rounded-xl
bg-green-50 flex items-center
justify-center">

<span
class="material-symbols-outlined
text-green-600">

check_circle

</span>

</div>


</div>


<p
class="text-xs text-green-600
mt-3 font-semibold">

View Completed Jobs →

</p>


</a>



<!-- DELIVERED -->

<a
href="status.php?status=Delivered"
class="status-card bg-white border
rounded-2xl p-5
<?= $selectedStatus === "Delivered"
    ? "ring-2 ring-purple-400"
    : ""
?>">


<div
class="flex items-center justify-between">


<div>

<p
class="text-sm text-slate-500">

Delivered

</p>


<p
class="text-3xl font-bold
text-purple-600 mt-1">

<?= $delivered ?>

</p>

</div>


<div
class="w-11 h-11 rounded-xl
bg-purple-50 flex items-center
justify-center">

<span
class="material-symbols-outlined
text-purple-600">

local_shipping

</span>

</div>


</div>


<p
class="text-xs text-purple-600
mt-3 font-semibold">

View Delivered Jobs →

</p>


</a>


</div>



<!-- =====================================================
     SEARCH
===================================================== -->

<form
method="GET"
class="mb-5">


<?php if ($selectedStatus !== ""): ?>

<input
type="hidden"
name="status"
value="<?= htmlspecialchars(
    $selectedStatus
) ?>">

<?php endif; ?>


<div
class="flex flex-col sm:flex-row gap-3">


<div
class="relative flex-1">


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
placeholder="Search customer, phone, device, brand, model or issue..."
class="w-full bg-white
border border-slate-300
rounded-xl pl-12 pr-4 py-3.5
outline-none
focus:border-blue-600">

</div>


<button
type="submit"
class="bg-blue-700
hover:bg-blue-800
text-white px-6 py-3.5
rounded-xl font-semibold
flex items-center
justify-center gap-2">


<span
class="material-symbols-outlined">

search

</span>


Search

</button>


</div>

</form>



<!-- =====================================================
     ACTIVE FILTER
===================================================== -->

<?php if (
    $selectedStatus !== "" ||
    $search !== ""
): ?>

<div
class="flex flex-wrap items-center
gap-3 mb-5">


<?php if ($selectedStatus !== ""): ?>

<span
class="inline-flex items-center gap-2
bg-blue-50 text-blue-700
px-3 py-2 rounded-xl
text-sm font-semibold">


<span
class="material-symbols-outlined
text-base">

filter_alt

</span>


<?= htmlspecialchars(
    $selectedStatus
) ?>


</span>

<?php endif; ?>


<?php if ($search !== ""): ?>

<span
class="inline-flex items-center gap-2
bg-slate-100 text-slate-700
px-3 py-2 rounded-xl
text-sm font-semibold">


Search:

<?= htmlspecialchars(
    $search
) ?>


</span>

<?php endif; ?>


<a
href="status.php"
class="inline-flex items-center gap-2
text-sm font-semibold
text-slate-500
hover:text-blue-700">


<span
class="material-symbols-outlined
text-base">

close

</span>


Clear

</a>


</div>

<?php endif; ?>



<!-- =====================================================
     RECORD HEADER
===================================================== -->

<div
class="flex items-center
justify-between mb-4">


<div>

<h2
class="text-xl font-bold">


<?php if ($selectedStatus !== ""): ?>

<?= htmlspecialchars(
    $selectedStatus
) ?>

Jobs


<?php elseif ($search !== ""): ?>

Search Results


<?php else: ?>

Recent Repair Jobs


<?php endif; ?>


</h2>


<p
class="text-sm text-slate-500 mt-1">


<?php if ($previewMode): ?>

Showing latest 5 repair records.


<?php else: ?>

All matching repair records are shown.


<?php endif; ?>


</p>

</div>



<?php if (
    $previewMode &&
    (
        $pending +
        $progress +
        $completed +
        $delivered
    ) > 5
): ?>

<a
href="status.php?status=Pending"
class="text-sm font-semibold
text-blue-700">

View by Status →

</a>

<?php endif; ?>


</div>



<!-- =====================================================
     REPAIR RECORDS
===================================================== -->

<div class="space-y-5">


<?php if (
    $repairs &&
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


$statusClass =
    "bg-orange-100 text-orange-700";

$statusIcon =
    "pending";


if (
    $status === "In Progress"
) {

    $statusClass =
        "bg-blue-100 text-blue-700";

    $statusIcon =
        "autorenew";
}


if (
    $status === "Completed"
) {

    $statusClass =
        "bg-green-100 text-green-700";

    $statusIcon =
        "check_circle";
}


if (
    $status === "Delivered"
) {

    $statusClass =
        "bg-purple-100 text-purple-700";

    $statusIcon =
        "local_shipping";
}


$repairId =
    intval(
        $repair["repair_id"]
    );

?>


<div
class="bg-white
border border-slate-200
rounded-2xl p-5">


<!-- CUSTOMER + STATUS -->

<div
class="flex justify-between
items-start gap-4">


<div
class="flex items-center gap-3">


<div
class="w-12 h-12 rounded-xl
bg-blue-50 flex items-center
justify-center">

<span
class="material-symbols-outlined
text-blue-600">

person

</span>

</div>


<div>

<h3
class="font-bold text-lg">

<?= htmlspecialchars(
    $repair["customer_name"]
    ?? "Customer"
) ?>

</h3>


<p
class="text-sm text-slate-500">

<?= htmlspecialchars(
    $repair["customer_phone"]
    ?? ""
) ?>

</p>

</div>


</div>


<span
class="<?= $statusClass ?>
px-3 py-1.5 rounded-full
text-xs font-semibold
whitespace-nowrap">


<span
class="material-symbols-outlined
text-sm align-middle">

<?= $statusIcon ?>

</span>


<?= htmlspecialchars(
    $status
) ?>


</span>


</div>



<!-- REPAIR DETAILS -->

<div
class="grid grid-cols-2 md:grid-cols-4
gap-3 mt-5">


<div
class="bg-slate-50
rounded-xl p-4">

<p
class="text-xs text-slate-500">

Device

</p>


<p
class="font-semibold mt-1">

<?= htmlspecialchars(
    $repair["device_type"]
    ?? "-"
) ?>

</p>

</div>



<div
class="bg-slate-50
rounded-xl p-4">

<p
class="text-xs text-slate-500">

Brand

</p>


<p
class="font-semibold mt-1">

<?= htmlspecialchars(
    $repair["brand"]
    ?? "-"
) ?>

</p>

</div>



<div
class="bg-slate-50
rounded-xl p-4">

<p
class="text-xs text-slate-500">

Model

</p>


<p
class="font-semibold mt-1">

<?= htmlspecialchars(
    $repair["model"]
    ?? "-"
) ?>

</p>

</div>



<div
class="bg-slate-50
rounded-xl p-4">

<p
class="text-xs text-slate-500">

Assigned To

</p>


<p
class="font-semibold mt-1">

<?= htmlspecialchars(
    $repair["assigned_staff_name"]
    ?? "Not Assigned"
) ?>

</p>

<p class="text-xs text-slate-500 mt-1">
<?= htmlspecialchars($repair["assigned_staff_code"] ?? "") ?>
<?php if (!empty($repair["assigned_staff_role"])): ?>
    • <?= htmlspecialchars($repair["assigned_staff_role"]) ?>
<?php endif; ?>
</p>

</div>


</div>



<!-- ISSUE -->

<div
class="mt-3 bg-slate-50
rounded-xl p-4">


<p
class="text-xs text-slate-500">

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



<!-- REPAIR ID -->

<div
class="mt-4 text-xs
text-slate-400">


Repair ID:

<strong>

#<?= $repairId ?>

</strong>


<?php if (
    !empty(
        $repair["created_at"]
    )
): ?>

&nbsp; • &nbsp;

<?= htmlspecialchars(
    $repair["created_at"]
) ?>

<?php endif; ?>


</div>



<!-- EDIT BUTTON -->

<div
class="mt-5 pt-5
border-t">


<button
type="button"
onclick="toggleEdit(
    <?= $repairId ?>
)"
class="inline-flex items-center
gap-2 bg-blue-50
text-blue-700
hover:bg-blue-100
px-4 py-2.5 rounded-xl
font-semibold text-sm">


<span
class="material-symbols-outlined
text-lg">

edit

</span>


Edit Repair


</button>


</div>



<!-- INLINE EDIT -->

<div
id="editBox<?= $repairId ?>"
class="hidden mt-4
bg-slate-50
border border-slate-200
rounded-2xl p-5">


<div
class="flex items-center
justify-between mb-5">


<div>

<h4 class="font-bold">

Edit Repair Details

</h4>


<p
class="text-xs text-slate-500 mt-1">

Customer name and phone
cannot be changed here.

</p>

</div>


<button
type="button"
onclick="toggleEdit(
    <?= $repairId ?>
)"
class="w-9 h-9 rounded-full
hover:bg-white
flex items-center
justify-center">


<span
class="material-symbols-outlined">

close

</span>


</button>


</div>



<form
method="POST"
class="space-y-4">


<input
type="hidden"
name="action"
value="update_repair">


<input
type="hidden"
name="repair_id"
value="<?= $repairId ?>">


<div
class="grid md:grid-cols-2
gap-4">


<div>

<label
class="text-sm font-semibold">

Device Type

</label>


<input
type="text"
name="device_type"
value="<?= htmlspecialchars(
    $repair["device_type"]
    ?? ""
) ?>"
required
class="w-full mt-1
border border-slate-300
rounded-xl px-4 py-3
bg-white outline-none
focus:border-blue-600">

</div>



<div>

<label
class="text-sm font-semibold">

Brand

</label>


<input
type="text"
name="brand"
value="<?= htmlspecialchars(
    $repair["brand"]
    ?? ""
) ?>"
required
class="w-full mt-1
border border-slate-300
rounded-xl px-4 py-3
bg-white outline-none
focus:border-blue-600">

</div>



<div>

<label
class="text-sm font-semibold">

Model

</label>


<input
type="text"
name="model"
value="<?= htmlspecialchars(
    $repair["model"]
    ?? ""
) ?>"
required
class="w-full mt-1
border border-slate-300
rounded-xl px-4 py-3
bg-white outline-none
focus:border-blue-600">

</div>



<div>

<label
class="text-sm font-semibold">

Repair Status

</label>


<select
name="status"
required
class="w-full mt-1
border border-slate-300
rounded-xl px-4 py-3
bg-white">


<option
value="Pending"
<?= $status === "Pending"
    ? "selected"
    : ""
?>>

Pending

</option>


<option
value="In Progress"
<?= $status === "In Progress"
    ? "selected"
    : ""
?>>

In Progress

</option>


<option
value="Completed"
<?= $status === "Completed"
    ? "selected"
    : ""
?>>

Completed

</option>


<option
value="Delivered"
<?= $status === "Delivered"
    ? "selected"
    : ""
?>>

Delivered

</option>


</select>

</div>



<div
class="md:col-span-2">


<label
class="text-sm font-semibold">

Reported Issue

</label>


<textarea
name="issue"
rows="4"
required
class="w-full mt-1
border border-slate-300
rounded-xl px-4 py-3
bg-white outline-none
focus:border-blue-600"><?= htmlspecialchars(
    $repair["issue"]
    ?? ""
) ?></textarea>


</div>


</div>



<div
class="flex gap-3">


<button
type="button"
onclick="toggleEdit(
    <?= $repairId ?>
)"
class="flex-1
border border-slate-300
bg-white rounded-xl
py-3 font-semibold">

Cancel

</button>


<button
type="submit"
class="flex-1 bg-blue-700
hover:bg-blue-800
text-white rounded-xl
py-3 font-semibold
flex items-center
justify-center gap-2">


<span
class="material-symbols-outlined">

save

</span>


Save & Update

</button>


</div>


</form>


</div>


</div>


<?php endwhile; ?>


<?php else: ?>


<div
class="bg-white border
rounded-2xl p-12 text-center">


<span
class="material-symbols-outlined
text-6xl text-slate-300">

build

</span>


<h3
class="font-semibold text-lg mt-3">

No Repair Jobs

</h3>


<p
class="text-sm text-slate-500 mt-1">


<?php if ($search !== ""): ?>

No repair matched your search.


<?php elseif ($selectedStatus !== ""): ?>

No
<?= htmlspecialchars(
    $selectedStatus
) ?>
jobs found.


<?php else: ?>

Create a repair job first.


<?php endif; ?>


</p>


<?php if (
    $selectedStatus === "" &&
    $search === ""
): ?>

<a
href="repair.php"
class="inline-flex items-center
gap-2 mt-5 bg-blue-700
text-white px-5 py-3
rounded-xl font-semibold">


<span
class="material-symbols-outlined">

add

</span>


New Repair Job

</a>

<?php endif; ?>


</div>


<?php endif; ?>


</div>


</main>



<script>

function toggleEdit(id) {

    const box =
        document.getElementById(
            "editBox" + id
        );


    if (!box) {
        return;
    }


    box.classList.toggle(
        "hidden"
    );
}

</script>


</body>

</html>