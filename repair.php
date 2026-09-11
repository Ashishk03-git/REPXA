<?php

/* =========================================================
   AUTHENTICATION
========================================================= */

require_once "auth.php";
require_once "db.php";

/* =========================================================
   CURRENT USER / ASSIGNMENT
========================================================= */

$currentUserId = intval($_SESSION["user_id"] ?? 0);
$currentUserRole = strtolower(trim($_SESSION["role"] ?? ""));

$isOwner = ($currentUserRole === "owner");

$staffMembers = null;
$selfStaffId = 0;

if ($isOwner) {

    $staffMembers = $conn->query("
        SELECT
            staff_id,
            agent_id,
            user_id,
            name,
            role,
            status
        FROM staff
        WHERE LOWER(TRIM(status)) = 'active'
        ORDER BY name ASC
    ");

} else {

    $selfStmt = $conn->prepare("
        SELECT staff_id
        FROM staff
        WHERE user_id = ?
        LIMIT 1
    ");

    if ($selfStmt) {
        $selfStmt->bind_param("i", $currentUserId);
        $selfStmt->execute();
        $selfResult = $selfStmt->get_result();

        if ($selfResult && $selfResult->num_rows === 1) {
            $selfStaffId = intval($selfResult->fetch_assoc()["staff_id"]);
        }

        $selfStmt->close();
    }
}


/* COMMON MENU DATA */

$menuProfile = [
    "owner_name" => "REPXA Owner",
    "shop_name" => "Your Shop Name",
    "owner_photo" => ""
];

$menuResult = $conn->query("
    SELECT owner_name, shop_name, owner_photo
    FROM company_profile
    ORDER BY id ASC
    LIMIT 1
");

if ($menuResult && $menuResult->num_rows > 0) {
    $menuProfile = array_merge(
        $menuProfile,
        $menuResult->fetch_assoc()
    );
}

$menuPhotoExists =
    !empty($menuProfile["owner_photo"])
    && file_exists(
        __DIR__ . "/" . $menuProfile["owner_photo"]
    );

$currentPage =
    basename($_SERVER["PHP_SELF"]);

$message = "";
$messageType = "";


/* =========================
   CREATE REPAIR
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $customerName =
        trim($_POST["customer_name"] ?? "");

    $phone =
        trim($_POST["phone_number"] ?? "");

    $deviceType =
        trim($_POST["device_type"] ?? "Mobile");

    $brand =
        trim($_POST["device_brand"] ?? "");

    $model =
        trim($_POST["device_model"] ?? "");


    /* Inline Other values */

    $deviceTypeOther =
        trim($_POST["device_type_other"] ?? "");

    $brandOther =
        trim($_POST["device_brand_other"] ?? "");

    $issueOther =
        trim($_POST["issue_other"] ?? "");

    /* Assignment */

    $assignedStaffId =
        intval($_POST["assigned_staff_id"] ?? 0);

    /*
     * Owner may assign any active staff member.
     * Staff members are automatically assigned to themselves.
     */

    if (!$isOwner) {
        $assignedStaffId = $selfStaffId;
    }


    if (
        $deviceType === "Other"
        && $deviceTypeOther !== ""
    ) {

        $deviceType =
            $deviceTypeOther;
    }


    if (
        $brand === "Other"
        && $brandOther !== ""
    ) {

        $brand =
            $brandOther;
    }


    $issues =
        $_POST["issues"] ?? [];


    /* Replace the Other issue option
       with the user's custom issue */

    if (
        in_array(
            "Other",
            $issues,
            true
        )
    ) {

        $issues =
            array_values(
                array_filter(
                    $issues,
                    function ($item) {
                        return $item !== "Other";
                    }
                )
            );


        if ($issueOther !== "") {

            $issues[] =
                "Other: " . $issueOther;
        }
    }


    $issue =
        implode(", ", $issues);


    if (
        $customerName === ""
        || $phone === ""
        || $brand === ""
        || $model === ""
        || $issue === ""
        || $assignedStaffId <= 0
    ) {

        $message =
            "Please fill all required details.";

        $messageType =
            "error";

    } else {

        /* Validate assignment against active staff */

        if ($isOwner) {

            $assignStmt = $conn->prepare("
                SELECT staff_id
                FROM staff
                WHERE staff_id = ?
                AND LOWER(TRIM(status)) = 'active'
                LIMIT 1
            ");

            $assignStmt->bind_param("i", $assignedStaffId);
            $assignStmt->execute();
            $assignResult = $assignStmt->get_result();

            if (!$assignResult || $assignResult->num_rows !== 1) {

                $message = "Please select a valid active staff member.";
                $messageType = "error";

                $assignStmt->close();

            } else {

                $assignStmt->close();

                try {

                    $conn->begin_transaction();


            /* FIND CUSTOMER */

            $stmt =
                $conn->prepare("
                    SELECT customer_id
                    FROM customers
                    WHERE phone = ?
                    LIMIT 1
                ");

            $stmt->bind_param(
                "s",
                $phone
            );

            $stmt->execute();

            $result =
                $stmt->get_result();


            if ($result->num_rows > 0) {

                $customer =
                    $result->fetch_assoc();

                $customerId =
                    intval(
                        $customer["customer_id"]
                    );


                /* Update name if needed */

                $stmt =
                    $conn->prepare("
                        UPDATE customers
                        SET name = ?
                        WHERE customer_id = ?
                    ");

                $stmt->bind_param(
                    "si",
                    $customerName,
                    $customerId
                );

                $stmt->execute();

            } else {

                $stmt =
                    $conn->prepare("
                        INSERT INTO customers
                        (name, phone)
                        VALUES (?, ?)
                    ");

                $stmt->bind_param(
                    "ss",
                    $customerName,
                    $phone
                );

                $stmt->execute();

                $customerId =
                    $conn->insert_id;
            }


            /* CREATE REPAIR */

            $stmt =
                $conn->prepare("
                    INSERT INTO repairs
                    (
                        customer_id,
                        device_type,
                        brand,
                        model,
                        issue,
                        status,
                        assigned_staff_id
                    )
                    VALUES (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        'Pending',
                        ?
                    )
                ");

            $stmt->bind_param(
                "issssi",
                $customerId,
                $deviceType,
                $brand,
                $model,
                $issue,
                $assignedStaffId
            );

            $stmt->execute();


            $conn->commit();


            $message =
                "Repair Job created successfully!";

            $messageType =
                "success";


                } catch (Exception $e) {

                    $conn->rollback();

                    $message =
                        "Unable to create repair job.";

                    $messageType =
                        "error";
                }
            }
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0">

<title>REPXA - New Repair</title>

<script src="https://cdn.tailwindcss.com"></script>

<link
href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"
rel="stylesheet">

</head>


<body class="bg-slate-50 text-slate-900">


<!-- =====================================================
     HEADER
===================================================== -->

<header
class="bg-white border-b sticky top-0 z-50">

<div
class="max-w-4xl mx-auto px-5 h-16
flex items-center justify-between gap-3">


<div
class="flex items-center gap-3 min-w-0">

<a
href="<?= $isOwner ? "dashboard.php" : "agent_dashboard.php" ?>"
class="w-10 h-10 rounded-full
flex items-center justify-center
hover:bg-slate-100 shrink-0"
title="Back to Dashboard">

<span
class="material-symbols-outlined">

arrow_back

</span>

</a>


<div class="min-w-0">

<h1
class="font-bold text-lg text-blue-700">

New Repair

</h1>

<p
class="text-xs text-slate-500">

Create repair service request

</p>

</div>

</div>


<div
class="flex items-center gap-2 shrink-0">

<a
href="status.php"
class="flex items-center gap-2
bg-blue-50 text-blue-700
hover:bg-blue-100
border border-blue-100
px-3 py-2 rounded-xl
text-sm font-semibold"
title="Repair Status">

<span
class="material-symbols-outlined text-lg">

track_changes

</span>

<span class="hidden sm:inline">

Repair Status

</span>

</a>


<button
type="button"
onclick="toggleMenu()"
class="w-10 h-10 rounded-xl
hover:bg-slate-100
flex items-center justify-center"
aria-label="Open menu"
title="Menu">

<span
class="material-symbols-outlined">

menu

</span>

</button>

</div>

</div>

</header>



<!-- =====================================================
     COMMON SIDE MENU
===================================================== -->

<div
id="sideMenu"
class="hidden fixed inset-0 z-[100]"
aria-hidden="true">


<!-- Overlay -->

<div
onclick="toggleMenu()"
class="absolute inset-0 bg-black/40">
</div>



<!-- Drawer -->

<aside
class="absolute right-0 top-0 bottom-0
w-[310px] max-w-[88vw] bg-white
shadow-2xl flex flex-col">


<!-- Drawer Header -->

<div class="p-5 border-b">

<div
class="flex items-center justify-between">


<div
class="flex items-center gap-3">


<div
class="w-11 h-11 rounded-xl
overflow-hidden bg-blue-50
flex items-center justify-center">


<?php if ($menuPhotoExists): ?>

<img
src="<?= htmlspecialchars(
    $menuProfile["owner_photo"]
) ?>"
alt="Owner"
class="w-full h-full object-cover">

<?php else: ?>

<span
class="material-symbols-outlined
text-blue-700 text-2xl">

person

</span>

<?php endif; ?>


</div>


<div class="min-w-0">

<p class="font-bold truncate">

<?= htmlspecialchars(
    $menuProfile["owner_name"]
) ?>

</p>


<p
class="text-xs text-slate-500 truncate">

<?= htmlspecialchars(
    $menuProfile["shop_name"]
) ?>

</p>

</div>

</div>


<button
type="button"
onclick="toggleMenu()"
class="w-9 h-9 rounded-full
hover:bg-slate-100
flex items-center justify-center">

<span
class="material-symbols-outlined">

close

</span>

</button>

</div>

</div>



<!-- Menu -->

<div
class="flex-1 overflow-y-auto p-4">


<p
class="px-3 mb-2 text-[11px]
font-bold tracking-wider
text-slate-400">

MAIN MENU

</p>


<div class="space-y-1">


<a
href="dashboard.php"
class="flex items-center gap-3 p-3 rounded-xl
<?= $currentPage === "dashboard.php"
    ? "bg-blue-50 text-blue-700 font-semibold"
    : "hover:bg-slate-100"
?>">

<span
class="material-symbols-outlined">

dashboard

</span>

<span>
Home
</span>

</a>


<a
href="repair.php"
class="flex items-center gap-3 p-3 rounded-xl
<?= $currentPage === "repair.php"
    ? "bg-blue-50 text-blue-700 font-semibold"
    : "hover:bg-slate-100"
?>">

<span
class="material-symbols-outlined">

build

</span>

<span>
Repairs
</span>

</a>


<a
href="status.php"
class="flex items-center gap-3 p-3 rounded-xl
<?= $currentPage === "status.php"
    ? "bg-blue-50 text-blue-700 font-semibold"
    : "hover:bg-slate-100"
?>">

<span
class="material-symbols-outlined">

track_changes

</span>

<span>
Repair Status
</span>

</a>


<a
href="customers.php"
class="flex items-center gap-3 p-3 rounded-xl
<?= $currentPage === "customers.php"
    ? "bg-blue-50 text-blue-700 font-semibold"
    : "hover:bg-slate-100"
?>">

<span
class="material-symbols-outlined">

groups

</span>

<span>
Customers
</span>

</a>


<a
href="inventory.php"
class="flex items-center gap-3 p-3 rounded-xl
<?= $currentPage === "inventory.php"
    ? "bg-blue-50 text-blue-700 font-semibold"
    : "hover:bg-slate-100"
?>">

<span
class="material-symbols-outlined">

inventory_2

</span>

<span>
Inventory
</span>

</a>


<a
href="billing.php"
class="flex items-center gap-3 p-3 rounded-xl
<?= $currentPage === "billing.php"
    ? "bg-blue-50 text-blue-700 font-semibold"
    : "hover:bg-slate-100"
?>">

<span
class="material-symbols-outlined">

receipt_long

</span>

<span>
Billing
</span>

</a>


</div>



<p
class="px-3 mt-6 mb-2
text-[11px] font-bold
tracking-wider text-slate-400">

MANAGEMENT

</p>


<div class="space-y-1">


<a
href="profile.php"
class="flex items-center gap-3 p-3 rounded-xl
<?= $currentPage === "profile.php"
    ? "bg-blue-50 text-blue-700 font-semibold"
    : "hover:bg-slate-100"
?>">

<span
class="material-symbols-outlined">

person

</span>

<span>
Profile
</span>

</a>


<a
href="staff.php"
class="flex items-center gap-3 p-3 rounded-xl
<?= $currentPage === "staff.php"
    ? "bg-blue-50 text-blue-700 font-semibold"
    : "hover:bg-slate-100"
?>">

<span
class="material-symbols-outlined">

badge

</span>

<span>
Staff
</span>

</a>


<a
href="recycle_bin.php"
class="flex items-center gap-3 p-3 rounded-xl
<?= $currentPage === "recycle_bin.php"
    ? "bg-blue-50 text-blue-700 font-semibold"
    : "hover:bg-slate-100"
?>">

<span
class="material-symbols-outlined">

delete

</span>

<span>
Recycle Bin
</span>

</a>


<a
href="help.php"
class="flex items-center gap-3 p-3 rounded-xl
<?= $currentPage === "help.php"
    ? "bg-blue-50 text-blue-700 font-semibold"
    : "hover:bg-slate-100"
?>">

<span
class="material-symbols-outlined">

help

</span>

<span>
Help & Support
</span>

</a>


</div>

</div>



<!-- Owner / Logout -->

<div
class="p-4 border-t bg-slate-50">


<div
class="bg-white border rounded-2xl
p-3 flex items-center gap-3">


<div
class="w-11 h-11 rounded-xl
overflow-hidden bg-blue-50
flex items-center justify-center
shrink-0">


<?php if ($menuPhotoExists): ?>

<img
src="<?= htmlspecialchars(
    $menuProfile["owner_photo"]
) ?>"
alt="Owner"
class="w-full h-full object-cover">

<?php else: ?>

<span
class="material-symbols-outlined
text-blue-700">

person

</span>

<?php endif; ?>


</div>


<div class="min-w-0 flex-1">

<p
class="font-semibold text-sm truncate">

<?= htmlspecialchars(
    $menuProfile["owner_name"]
) ?>

</p>


<p
class="text-xs text-slate-500 truncate">

<?= htmlspecialchars(
    $menuProfile["shop_name"]
) ?>

</p>

</div>


<a
href="logout.php"
title="Logout"
class="w-9 h-9 rounded-lg
bg-red-50 text-red-600
hover:bg-red-100
flex items-center justify-center
shrink-0">

<span
class="material-symbols-outlined text-lg">

logout

</span>

</a>


</div>

</div>


</aside>

</div>



<!-- =====================================================
     MAIN
===================================================== -->

<main
class="max-w-4xl mx-auto px-5 py-6">


<!-- MESSAGE -->

<?php if ($message !== ""): ?>

<div
class="mb-5 p-4 rounded-xl
<?= $messageType === "success"
    ? "bg-green-100 text-green-700 border border-green-200"
    : "bg-red-100 text-red-700 border border-red-200"
?>">

<?= htmlspecialchars($message) ?>

</div>

<?php endif; ?>



<!-- TITLE -->

<div class="mb-6">

<p
class="text-xs font-semibold
tracking-wider text-slate-500">

REPAIR MANAGEMENT

</p>


<h2 class="text-2xl font-bold mt-1">

Create Repair Job

</h2>


<p
class="text-sm text-slate-500 mt-1">

Enter customer, device and issue details.

</p>

</div>



<form
method="POST"
class="space-y-5">



<!-- =====================================================
     CUSTOMER
===================================================== -->

<div
class="bg-white border rounded-2xl p-5">


<div
class="flex items-center gap-3 mb-5">


<div
class="w-10 h-10 rounded-xl
bg-blue-50 flex items-center justify-center">

<span
class="material-symbols-outlined
text-blue-700">

person

</span>

</div>


<div>

<h3 class="font-bold">

Customer Details

</h3>


<p
class="text-xs text-slate-500">

Customer contact information

</p>

</div>

</div>


<div
class="grid md:grid-cols-2 gap-4">


<div>

<label
class="text-sm font-semibold">

Customer Name *

</label>


<input
type="text"
name="customer_name"
required
placeholder="Enter customer name"
class="w-full mt-1
border border-slate-300
rounded-xl px-4 py-3
outline-none
focus:border-blue-600">

</div>


<div>

<label
class="text-sm font-semibold">

Phone Number *

</label>


<input
type="tel"
name="phone_number"
required
placeholder="+91 00000 00000"
class="w-full mt-1
border border-slate-300
rounded-xl px-4 py-3
outline-none
focus:border-blue-600">

</div>


</div>

</div>



<!-- =====================================================
     DEVICE
===================================================== -->

<div
class="bg-white border rounded-2xl p-5">


<div
class="flex items-center gap-3 mb-5">


<div
class="w-10 h-10 rounded-xl
bg-blue-50 flex items-center justify-center">

<span
class="material-symbols-outlined
text-blue-700">

smartphone

</span>

</div>


<div>

<h3 class="font-bold">

Device Information

</h3>


<p
class="text-xs text-slate-500">

Device details for repair

</p>

</div>

</div>


<div
class="grid md:grid-cols-2 gap-4">


<div>

<label
class="text-sm font-semibold">

Device Type *

</label>


<select
name="device_type"
id="deviceType"
required
class="w-full mt-1
border border-slate-300
rounded-xl px-4 py-3
bg-white">

<option value="Mobile">
Mobile
</option>

<option value="Laptop">
Laptop
</option>

<option value="Tablet">
Tablet
</option>

<option value="Desktop">
Desktop
</option>

<option value="Other">
Other
</option>

</select>


<input
type="text"
name="device_type_other"
id="deviceTypeOther"
placeholder="Write device type"
class="hidden w-full mt-2
border border-slate-300
rounded-xl px-4 py-3
outline-none
focus:border-blue-600">

</div>


<div>

<label
class="text-sm font-semibold">

Brand *

</label>


<select
name="device_brand"
id="deviceBrand"
required
class="w-full mt-1
border border-slate-300
rounded-xl px-4 py-3
bg-white">

<option value="">
Select Brand
</option>

<option value="Samsung">
Samsung
</option>

<option value="Redmi">
Redmi
</option>

<option value="Vivo">
Vivo
</option>

<option value="Oppo">
Oppo
</option>

<option value="Realme">
Realme
</option>

<option value="Apple">
Apple
</option>

<option value="OnePlus">
OnePlus
</option>

<option value="Dell">
Dell
</option>

<option value="HP">
HP
</option>

<option value="Lenovo">
Lenovo
</option>

<option value="Asus">
Asus
</option>

<option value="Other">
Other
</option>

</select>


<input
type="text"
name="device_brand_other"
id="deviceBrandOther"
placeholder="Write brand name"
class="hidden w-full mt-2
border border-slate-300
rounded-xl px-4 py-3
outline-none
focus:border-blue-600">

</div>


<div class="md:col-span-2">

<label
class="text-sm font-semibold">

Model *

</label>


<input
type="text"
name="device_model"
required
placeholder="Example: Samsung S21"
class="w-full mt-1
border border-slate-300
rounded-xl px-4 py-3
outline-none
focus:border-blue-600">

</div>


</div>

</div>



<!-- =====================================================
     ISSUE
===================================================== -->

<div
class="bg-white border rounded-2xl p-5">


<div
class="flex items-center gap-3 mb-5">


<div
class="w-10 h-10 rounded-xl
bg-orange-50 flex items-center justify-center">

<span
class="material-symbols-outlined
text-orange-600">

build

</span>

</div>


<div>

<h3 class="font-bold">

Issue Type

</h3>


<p
class="text-xs text-slate-500">

Select one or more issues

</p>

</div>

</div>


<div
class="grid grid-cols-2 md:grid-cols-3 gap-3">


<label class="cursor-pointer">

<input
type="checkbox"
name="issues[]"
value="Screen"
class="issue hidden">


<div
class="issue-box border
border-slate-300 rounded-xl p-4
text-center hover:bg-blue-50">

<span
class="material-symbols-outlined
text-blue-600">

smartphone

</span>


<p
class="text-sm font-medium mt-1">

Screen

</p>

</div>

</label>


<label class="cursor-pointer">

<input
type="checkbox"
name="issues[]"
value="Battery"
class="issue hidden">


<div
class="issue-box border
border-slate-300 rounded-xl p-4
text-center hover:bg-blue-50">

<span
class="material-symbols-outlined
text-blue-600">

battery_charging_full

</span>


<p
class="text-sm font-medium mt-1">

Battery

</p>

</div>

</label>


<label class="cursor-pointer">

<input
type="checkbox"
name="issues[]"
value="Charging Port"
class="issue hidden">


<div
class="issue-box border
border-slate-300 rounded-xl p-4
text-center hover:bg-blue-50">

<span
class="material-symbols-outlined
text-blue-600">

power

</span>


<p
class="text-sm font-medium mt-1">

Charging Port

</p>

</div>

</label>


<label class="cursor-pointer">

<input
type="checkbox"
name="issues[]"
value="Water Damage"
class="issue hidden">


<div
class="issue-box border
border-slate-300 rounded-xl p-4
text-center hover:bg-blue-50">

<span
class="material-symbols-outlined
text-blue-600">

water_drop

</span>


<p
class="text-sm font-medium mt-1">

Water Damage

</p>

</div>

</label>


<label class="cursor-pointer">

<input
type="checkbox"
name="issues[]"
value="Software"
class="issue hidden">


<div
class="issue-box border
border-slate-300 rounded-xl p-4
text-center hover:bg-blue-50">

<span
class="material-symbols-outlined
text-blue-600">

settings_suggest

</span>


<p
class="text-sm font-medium mt-1">

Software

</p>

</div>

</label>


<label class="cursor-pointer">

<input
type="checkbox"
name="issues[]"
value="Camera"
class="issue hidden">


<div
class="issue-box border
border-slate-300 rounded-xl p-4
text-center hover:bg-blue-50">

<span
class="material-symbols-outlined
text-blue-600">

photo_camera

</span>


<p
class="text-sm font-medium mt-1">

Camera

</p>

</div>

</label>


<label class="cursor-pointer">

<input
type="checkbox"
name="issues[]"
value="Other"
id="otherIssueCheckbox"
class="issue hidden">


<div
class="issue-box border
border-slate-300 rounded-xl p-4
text-center hover:bg-blue-50">

<span
class="material-symbols-outlined
text-blue-600">

more_horiz

</span>


<p
class="text-sm font-medium mt-1">

Other

</p>

</div>

</label>


<div
class="col-span-2 md:col-span-3">


<input
type="text"
name="issue_other"
id="issueOther"
placeholder="Write your issue here..."
class="hidden w-full
border border-slate-300
rounded-xl px-4 py-3
outline-none
focus:border-blue-600">

</div>


</div>

</div>



<!-- =====================================================
     ASSIGN REPAIR
===================================================== -->

<div
class="bg-white border rounded-2xl p-5">

<div
class="flex items-center gap-3 mb-5">

<div
class="w-10 h-10 rounded-xl bg-purple-50
flex items-center justify-center">

<span
class="material-symbols-outlined text-purple-700">

assignment_ind

</span>

</div>

<div>

<h3 class="font-bold">
Assign Repair
</h3>

<p class="text-xs text-slate-500">
Choose who will handle this repair.
</p>

</div>

</div>

<?php if ($isOwner): ?>

<label class="text-sm font-semibold">
Assign To *
</label>

<select
name="assigned_staff_id"
required
class="w-full mt-1 border border-slate-300
rounded-xl px-4 py-3 bg-white outline-none
focus:border-blue-600">

<option value="">
Select staff member
</option>

<?php if ($staffMembers && $staffMembers->num_rows > 0): ?>

<?php while ($staff = $staffMembers->fetch_assoc()): ?>

<option
value="<?= intval($staff["staff_id"]) ?>">

<?= htmlspecialchars($staff["name"]) ?>
—
<?= htmlspecialchars($staff["agent_id"]) ?>
—
<?= htmlspecialchars(ucwords($staff["role"])) ?>

</option>

<?php endwhile; ?>

<?php else: ?>

<option value="" disabled>
No active staff available
</option>

<?php endif; ?>

</select>

<p class="text-xs text-slate-400 mt-2">
Name, Staff ID and role are shown so the correct person can be selected.
</p>

<?php else: ?>

<input
type="hidden"
name="assigned_staff_id"
value="<?= intval($selfStaffId) ?>">

<div
class="bg-blue-50 border border-blue-100
rounded-xl p-4">

<p class="text-sm font-semibold text-blue-800">
Assigned to you
</p>

<p class="text-xs text-blue-600 mt-1">
Your repair request will automatically be assigned to your staff account.
</p>

</div>

<?php endif; ?>

</div>



<!-- =====================================================
     SAVE
===================================================== -->

<button
type="submit"
class="w-full bg-blue-700
hover:bg-blue-800 text-white
py-4 rounded-xl font-semibold
flex items-center justify-center
gap-2">


<span
class="material-symbols-outlined">

save

</span>


Create Repair Job

</button>


</form>


</main>



<!-- =====================================================
     MENU SCRIPT
===================================================== -->

<script>

function toggleMenu() {

    const menu =
        document.getElementById(
            "sideMenu"
        );

    if (!menu) return;

    menu.classList.toggle(
        "hidden"
    );

    menu.setAttribute(
        "aria-hidden",
        menu.classList.contains("hidden")
            ? "true"
            : "false"
    );
}


document.addEventListener(
    "keydown",
    function(event) {

        if (
            event.key === "Escape"
        ) {

            const menu =
                document.getElementById(
                    "sideMenu"
                );

            if (
                menu &&
                !menu.classList.contains(
                    "hidden"
                )
            ) {

                toggleMenu();

            }
        }
    }
);

</script>



<!-- =====================================================
     DEVICE / BRAND / ISSUE SCRIPT
===================================================== -->

<script>

/* Device Type -> Other */

const deviceType =
    document.getElementById(
        "deviceType"
    );

const deviceTypeOther =
    document.getElementById(
        "deviceTypeOther"
    );


function toggleDeviceTypeOther() {

    const show =
        deviceType.value === "Other";


    deviceTypeOther.classList.toggle(
        "hidden",
        !show
    );


    deviceTypeOther.required =
        show;


    if (!show) {

        deviceTypeOther.value =
            "";
    }
}


deviceType.addEventListener(
    "change",
    toggleDeviceTypeOther
);

toggleDeviceTypeOther();



/* Brand -> Other */

const deviceBrand =
    document.getElementById(
        "deviceBrand"
    );

const deviceBrandOther =
    document.getElementById(
        "deviceBrandOther"
    );


function toggleDeviceBrandOther() {

    const show =
        deviceBrand.value === "Other";


    deviceBrandOther.classList.toggle(
        "hidden",
        !show
    );


    deviceBrandOther.required =
        show;


    if (!show) {

        deviceBrandOther.value =
            "";
    }
}


deviceBrand.addEventListener(
    "change",
    toggleDeviceBrandOther
);

toggleDeviceBrandOther();



/* Issue selection styling + Other input */

document
.querySelectorAll(".issue")
.forEach(function(input) {

    input.addEventListener(
        "change",
        function() {

            const box =
                this.parentElement
                .querySelector(
                    ".issue-box"
                );


            if (this.checked) {

                box.classList.add(
                    "bg-blue-100",
                    "border-blue-600",
                    "text-blue-700"
                );

            } else {

                box.classList.remove(
                    "bg-blue-100",
                    "border-blue-600",
                    "text-blue-700"
                );
            }


            if (
                this.id ===
                "otherIssueCheckbox"
            ) {

                const otherInput =
                    document.getElementById(
                        "issueOther"
                    );


                otherInput.classList.toggle(
                    "hidden",
                    !this.checked
                );


                otherInput.required =
                    this.checked;


                if (!this.checked) {

                    otherInput.value =
                        "";
                }
            }

        }
    );

});

</script>


</body>

</html>