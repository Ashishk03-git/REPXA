<?php
require_once "auth.php";
require_once "db.php";

/* =========================================================
   RECYCLE BIN TABLE
========================================================= */

$conn->query("
CREATE TABLE IF NOT EXISTS recycle_bin (
    recycle_id INT NOT NULL AUTO_INCREMENT,
    record_type VARCHAR(50) NOT NULL,
    record_id INT NOT NULL,
    record_title VARCHAR(255) NOT NULL DEFAULT '',
    data_json LONGTEXT NOT NULL,
    deleted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (recycle_id),
    INDEX(record_type),
    INDEX(record_id),
    INDEX(deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");


/* =========================================================
   COMMON MENU PROFILE
========================================================= */

$menuProfile = [
    "owner_name" => "REPXA Owner",
    "shop_name" => "Your Shop Name",
    "owner_photo" => ""
];

$result = $conn->query("
    SELECT owner_name, shop_name, owner_photo
    FROM company_profile
    ORDER BY id ASC
    LIMIT 1
");

if ($result && $result->num_rows > 0) {
    $menuProfile = array_merge(
        $menuProfile,
        $result->fetch_assoc()
    );
}

$menuPhotoExists =
    !empty($menuProfile["owner_photo"]) &&
    file_exists(__DIR__ . "/" . $menuProfile["owner_photo"]);

$currentPage = basename($_SERVER["PHP_SELF"]);


/* =========================================================
   SYNC DELETED BILLS INTO RECYCLE BIN
   billing.php uses bills.deleted_at for soft delete.
========================================================= */

$deletedBills = $conn->query("
    SELECT *
    FROM bills
    WHERE deleted_at IS NOT NULL
");

if ($deletedBills && $deletedBills->num_rows > 0) {

    while ($deletedBill = $deletedBills->fetch_assoc()) {

        $billId = intval($deletedBill["bill_id"]);

        $checkStmt = $conn->prepare("
            SELECT recycle_id
            FROM recycle_bin
            WHERE record_type = 'bill'
            AND record_id = ?
            LIMIT 1
        ");

        $checkStmt->bind_param("i", $billId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $alreadyExists = $checkResult && $checkResult->num_rows > 0;
        $checkStmt->close();

        if ($alreadyExists) {
            continue;
        }

        $deletedAt = $deletedBill["deleted_at"];
        $title = "Bill #" . $billId;

        if (!empty($deletedBill["customer_id"])) {

            $customerStmt = $conn->prepare("
                SELECT name
                FROM customers
                WHERE customer_id = ?
                LIMIT 1
            ");

            $customerId = intval($deletedBill["customer_id"]);
            $customerStmt->bind_param("i", $customerId);
            $customerStmt->execute();
            $customerResult = $customerStmt->get_result();

            if ($customerResult && $customerResult->num_rows > 0) {
                $customerRow = $customerResult->fetch_assoc();
                if (!empty($customerRow["name"])) {
                    $title .= " - " . $customerRow["name"];
                }
            }

            $customerStmt->close();
        }

        /* Restored bill should become active again. */
        $deletedBill["deleted_at"] = null;

        $dataJson = json_encode($deletedBill, JSON_UNESCAPED_UNICODE);

        $insertStmt = $conn->prepare("
            INSERT INTO recycle_bin
            (record_type, record_id, record_title, data_json, deleted_at)
            VALUES ('bill', ?, ?, ?, ?)
        ");

        if ($insertStmt) {
            $insertStmt->bind_param(
                "isss",
                $billId,
                $title,
                $dataJson,
                $deletedAt
            );

            if ($insertStmt->execute()) {
                /* Only remove the original after recycle copy succeeds. */
                $deleteStmt = $conn->prepare("
                    DELETE FROM bills
                    WHERE bill_id = ?
                    AND deleted_at IS NOT NULL
                ");
                $deleteStmt->bind_param("i", $billId);
                $deleteStmt->execute();
                $deleteStmt->close();
            }

            $insertStmt->close();
        }
    }
}


/* =========================================================
   AUTO DELETE AFTER 15 DAYS
========================================================= */

$conn->query("
    DELETE FROM recycle_bin
    WHERE deleted_at <= DATE_SUB(NOW(), INTERVAL 15 DAY)
");


/* =========================================================
   MESSAGE
========================================================= */

$message = "";
$messageType = "";


/* =========================================================
   RESTORE
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["action"] ?? "") === "restore"
) {

    $recycleId = intval($_POST["recycle_id"] ?? 0);

    if ($recycleId > 0) {

        $stmt = $conn->prepare("
            SELECT *
            FROM recycle_bin
            WHERE recycle_id = ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "i",
            $recycleId
        );

        $stmt->execute();

        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {

            $record = $result->fetch_assoc();

            $type = $record["record_type"];

            $data = json_decode(
                $record["data_json"],
                true
            );


            if (is_array($data)) {

                $restored = false;


                /* =========================================
                   RESTORE BILL
                ========================================= */

                if ($type === "bill") {

                    $columns = [];
                    $values = [];
                    $types = "";

                    foreach ($data as $column => $value) {

                        if ($column === "bill_id") {
                            continue;
                        }

                        $columns[] = "`" . $column . "`";
                        $values[] = $value;

                        if (is_int($value)) {
                            $types .= "i";
                        } else {
                            $types .= "s";
                        }
                    }


                    if (!empty($columns)) {

                        $placeholders =
                            implode(
                                ", ",
                                array_fill(
                                    0,
                                    count($columns),
                                    "?"
                                )
                            );

                        $sql = "
                            INSERT INTO bills
                            (" . implode(", ", $columns) . ")
                            VALUES ($placeholders)
                        ";

                        $restoreStmt =
                            $conn->prepare($sql);

                        if ($restoreStmt) {

                            $restoreStmt->bind_param(
                                $types,
                                ...$values
                            );

                            $restored =
                                $restoreStmt->execute();

                            $restoreStmt->close();
                        }
                    }
                }


                /* =========================================
                   RESTORE CUSTOMER
                ========================================= */

                if ($type === "customer") {

                    $columns = [];
                    $values = [];
                    $types = "";

                    foreach ($data as $column => $value) {

                        if ($column === "customer_id") {
                            continue;
                        }

                        $columns[] = "`" . $column . "`";
                        $values[] = $value;
                        $types .= is_int($value)
                            ? "i"
                            : "s";
                    }

                    if (!empty($columns)) {

                        $placeholders =
                            implode(
                                ", ",
                                array_fill(
                                    0,
                                    count($columns),
                                    "?"
                                )
                            );

                        $sql = "
                            INSERT INTO customers
                            (" . implode(", ", $columns) . ")
                            VALUES ($placeholders)
                        ";

                        $restoreStmt =
                            $conn->prepare($sql);

                        if ($restoreStmt) {

                            $restoreStmt->bind_param(
                                $types,
                                ...$values
                            );

                            $restored =
                                $restoreStmt->execute();

                            $restoreStmt->close();
                        }
                    }
                }


                /* =========================================
                   RESTORE REPAIR
                ========================================= */

                if ($type === "repair") {

                    $columns = [];
                    $values = [];
                    $types = "";

                    foreach ($data as $column => $value) {

                        if ($column === "repair_id") {
                            continue;
                        }

                        $columns[] = "`" . $column . "`";
                        $values[] = $value;
                        $types .= is_int($value)
                            ? "i"
                            : "s";
                    }

                    if (!empty($columns)) {

                        $placeholders =
                            implode(
                                ", ",
                                array_fill(
                                    0,
                                    count($columns),
                                    "?"
                                )
                            );

                        $sql = "
                            INSERT INTO repairs
                            (" . implode(", ", $columns) . ")
                            VALUES ($placeholders)
                        ";

                        $restoreStmt =
                            $conn->prepare($sql);

                        if ($restoreStmt) {

                            $restoreStmt->bind_param(
                                $types,
                                ...$values
                            );

                            $restored =
                                $restoreStmt->execute();

                            $restoreStmt->close();
                        }
                    }
                }


                if ($restored) {

                    $deleteStmt = $conn->prepare("
                        DELETE FROM recycle_bin
                        WHERE recycle_id = ?
                    ");

                    $deleteStmt->bind_param(
                        "i",
                        $recycleId
                    );

                    $deleteStmt->execute();
                    $deleteStmt->close();

                    $message =
                        "Record restored successfully.";

                    $messageType =
                        "success";

                } else {

                    $message =
                        "Unable to restore this record.";

                    $messageType =
                        "error";
                }

            } else {

                $message =
                    "Invalid recycle bin data.";

                $messageType =
                    "error";
            }

        } else {

            $message =
                "Recycle bin record not found.";

            $messageType =
                "error";
        }

        $stmt->close();
    }
}


/* =========================================================
   PERMANENT DELETE
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["action"] ?? "") === "permanent_delete"
) {

    $recycleId =
        intval($_POST["recycle_id"] ?? 0);

    if ($recycleId > 0) {

        $stmt = $conn->prepare("
            DELETE FROM recycle_bin
            WHERE recycle_id = ?
        ");

        $stmt->bind_param(
            "i",
            $recycleId
        );

        if ($stmt->execute()) {

            $message =
                "Record permanently deleted.";

            $messageType =
                "success";

        } else {

            $message =
                "Unable to permanently delete record.";

            $messageType =
                "error";
        }

        $stmt->close();
    }
}


/* =========================================================
   SEARCH
========================================================= */

$search =
    trim($_GET["search"] ?? "");


if ($search !== "") {

    $stmt = $conn->prepare("
        SELECT *
        FROM recycle_bin
        WHERE
            record_title LIKE ?
            OR record_type LIKE ?
        ORDER BY deleted_at DESC
    ");

    $term =
        "%" . $search . "%";

    $stmt->bind_param(
        "ss",
        $term,
        $term
    );

    $stmt->execute();

    $records =
        $stmt->get_result();

} else {

    $records = $conn->query("
        SELECT *
        FROM recycle_bin
        ORDER BY deleted_at DESC
    ");
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0">

<title>REPXA - Recycle Bin</title>

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
class="max-w-5xl mx-auto px-5 h-16 flex items-center justify-between">


<div class="flex items-center gap-3">

<a
href="dashboard.php"
class="w-10 h-10 rounded-full flex items-center justify-center hover:bg-slate-100"
title="Back to Dashboard">

<span class="material-symbols-outlined">
arrow_back
</span>

</a>


<div>

<h1
class="font-bold text-lg text-blue-700">

Recycle Bin

</h1>

<p
class="text-xs text-slate-500">

Deleted Records

</p>

</div>

</div>


<!-- THREE LINE MENU -->

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
     RIGHT MENU
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


<div class="flex items-center gap-3">

<div
class="w-11 h-11 rounded-xl overflow-hidden bg-blue-50 flex items-center justify-center">

<?php if ($menuPhotoExists): ?>

<img
src="<?= htmlspecialchars($menuProfile["owner_photo"]) ?>"
class="w-full h-full object-cover">

<?php else: ?>

<span
class="material-symbols-outlined text-blue-700">

person

</span>

<?php endif; ?>

</div>


<div>

<p class="font-bold">

<?= htmlspecialchars(
$menuProfile["owner_name"]
) ?>

</p>

<p class="text-xs text-slate-500">

<?= htmlspecialchars(
$menuProfile["shop_name"]
) ?>

</p>

</div>

</div>


<button
onclick="toggleMenu()"
class="w-9 h-9 rounded-full hover:bg-slate-100 flex items-center justify-center">

<span class="material-symbols-outlined">
close
</span>

</button>

</div>

</div>



<div class="flex-1 overflow-y-auto p-4">

<p
class="px-3 mb-2 text-[11px] font-bold tracking-wider text-slate-400">

MAIN MENU

</p>


<div class="space-y-1">


<a
href="dashboard.php"
class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-100">

<span class="material-symbols-outlined">
dashboard
</span>

<span>Home</span>

</a>


<a
href="repair.php"
class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-100">

<span class="material-symbols-outlined">
build
</span>

<span>Repairs</span>

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


</div>


<p
class="px-3 mt-6 mb-2 text-[11px] font-bold tracking-wider text-slate-400">

MANAGEMENT

</p>


<div class="space-y-1">


<a
href="profile.php"
class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-100">

<span class="material-symbols-outlined">
person
</span>

<span>Profile</span>

</a>


<a
href="staff.php"
class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-100">

<span class="material-symbols-outlined">
badge
</span>

<span>Staff</span>

</a>


<a
href="recycle_bin.php"
class="flex items-center gap-3 p-3 rounded-xl bg-blue-50 text-blue-700 font-semibold">

<span class="material-symbols-outlined">
delete
</span>

<span>Recycle Bin</span>

</a>


<a
href="help.php"
class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-100">

<span class="material-symbols-outlined">
help
</span>

<span>Help & Support</span>

</a>


</div>

</div>



<div class="p-4 border-t bg-slate-50">

<div
class="bg-white border rounded-2xl p-3 flex items-center gap-3">


<div
class="w-11 h-11 rounded-xl overflow-hidden bg-blue-50 flex items-center justify-center">

<?php if ($menuPhotoExists): ?>

<img
src="<?= htmlspecialchars($menuProfile["owner_photo"]) ?>"
class="w-full h-full object-cover">

<?php else: ?>

<span class="material-symbols-outlined text-blue-700">
person
</span>

<?php endif; ?>

</div>


<div class="flex-1 min-w-0">

<p class="font-semibold text-sm truncate">

<?= htmlspecialchars(
$menuProfile["owner_name"]
) ?>

</p>

<p class="text-xs text-slate-500 truncate">

<?= htmlspecialchars(
$menuProfile["shop_name"]
) ?>

</p>

</div>


<a
href="logout.php"
class="w-9 h-9 rounded-lg bg-red-50 text-red-600 flex items-center justify-center">

<span class="material-symbols-outlined">
logout
</span>

</a>


</div>

</div>


</aside>

</div>



<main
class="max-w-5xl mx-auto px-5 py-7">


<!-- =====================================================
     TITLE
===================================================== -->

<div class="mb-6">

<p
class="text-xs font-semibold tracking-wider text-slate-500">

STORAGE MANAGEMENT

</p>

<h2 class="text-2xl font-bold mt-1">

Recycle Bin

</h2>

<p class="text-sm text-slate-500 mt-1">

Deleted records are automatically removed after 15 days.

</p>

</div>



<!-- =====================================================
     INFO BANNER
===================================================== -->

<div
class="bg-orange-50 border border-orange-200 rounded-2xl p-5 mb-7">


<div class="flex items-start gap-4">


<div
class="w-11 h-11 rounded-xl bg-orange-100 flex items-center justify-center shrink-0">

<span
class="material-symbols-outlined text-orange-600">

schedule

</span>

</div>


<div>

<h3
class="font-bold text-orange-800">

15-Day Automatic Deletion

</h3>

<p
class="text-sm text-orange-700 mt-1">

Deleted records remain here for 15 days. After 15 days, they are permanently deleted automatically.

</p>

<div
class="inline-flex items-center gap-2 mt-3 bg-white border border-orange-200 rounded-lg px-3 py-1.5">

<span
class="material-symbols-outlined text-orange-600 text-lg">

timer

</span>

<span
class="text-xs font-bold text-orange-700">

Timer starts from 0/15 days

</span>

</div>

</div>

</div>

</div>



<!-- =====================================================
     SEARCH
===================================================== -->

<form
method="GET"
class="mb-6">


<div class="flex gap-3">


<div class="relative flex-1">

<span
class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">

search

</span>

<input
type="text"
name="search"
value="<?= htmlspecialchars($search) ?>"
placeholder="Search deleted records..."

class="w-full bg-white border border-slate-300 rounded-xl pl-12 pr-4 py-3 outline-none focus:border-blue-600">

</div>


<button
type="submit"
class="bg-blue-700 hover:bg-blue-800 text-white px-6 rounded-xl font-semibold">

Search

</button>


<?php if ($search !== ""): ?>

<a
href="recycle_bin.php"
class="border border-slate-300 px-5 rounded-xl flex items-center justify-center font-semibold">

Clear

</a>

<?php endif; ?>


</div>

</form>



<!-- =====================================================
     RECORDS
===================================================== -->

<div class="space-y-4">


<?php if ($records && $records->num_rows > 0): ?>


<?php while ($record = $records->fetch_assoc()): ?>


<?php

$deletedTime =
    strtotime($record["deleted_at"]);

$daysElapsed =
    max(
        0,
        floor(
            (time() - $deletedTime)
            / 86400
        )
    );

if ($daysElapsed > 15) {
    $daysElapsed = 15;
}

$daysRemaining =
    max(
        0,
        15 - $daysElapsed
    );

?>


<div
class="bg-white border border-slate-200 rounded-2xl p-5">


<div
class="flex flex-col md:flex-row md:items-center justify-between gap-5">


<!-- RECORD -->

<div class="flex items-center gap-4">


<div
class="w-12 h-12 rounded-xl bg-red-50 flex items-center justify-center">

<span
class="material-symbols-outlined text-red-600">

delete

</span>

</div>


<div>

<h3 class="font-bold">

<?= htmlspecialchars(
$record["record_title"]
) ?>

</h3>


<p
class="text-sm text-slate-500 mt-1 capitalize">

<?= htmlspecialchars(
$record["record_type"]
) ?>

</p>


<p
class="text-xs text-slate-400 mt-1">

Deleted:

<?= date(
"d M Y, h:i A",
$deletedTime
) ?>

</p>

</div>

</div>



<!-- TIMER + ACTIONS -->

<div
class="flex flex-wrap items-center gap-3">


<div
class="bg-orange-50 border border-orange-200 rounded-xl px-3 py-2 text-center">


<p
class="text-[10px] font-bold text-orange-600 uppercase">

Auto Delete

</p>


<p
class="text-sm font-bold text-orange-700">

<?= $daysElapsed ?>/15 days

</p>


<p
class="text-[10px] text-orange-500">

<?= $daysRemaining ?> day<?= $daysRemaining == 1 ? "" : "s" ?> left

</p>

</div>



<!-- RESTORE -->

<form method="POST">

<input
type="hidden"
name="action"
value="restore">

<input
type="hidden"
name="recycle_id"
value="<?= intval($record["recycle_id"]) ?>">


<button
type="submit"
class="w-10 h-10 rounded-xl bg-green-50 text-green-700 hover:bg-green-100 flex items-center justify-center"
title="Restore">

<span class="material-symbols-outlined">
restore
</span>

</button>

</form>



<!-- PERMANENT DELETE -->

<form
method="POST"
onsubmit="return confirm('This record will be permanently deleted. Continue?');">

<input
type="hidden"
name="action"
value="permanent_delete">

<input
type="hidden"
name="recycle_id"
value="<?= intval($record["recycle_id"]) ?>">


<button
type="submit"
class="w-10 h-10 rounded-xl bg-red-50 text-red-600 hover:bg-red-100 flex items-center justify-center"
title="Permanent Delete">

<span class="material-symbols-outlined">
delete_forever
</span>

</button>

</form>


</div>


</div>


</div>


<?php endwhile; ?>


<?php else: ?>


<div
class="bg-white border border-slate-200 rounded-2xl p-10 text-center">


<div
class="w-16 h-16 mx-auto rounded-2xl bg-slate-100 flex items-center justify-center">

<span
class="material-symbols-outlined text-slate-400 text-4xl">

delete_sweep

</span>

</div>


<h3
class="font-bold text-lg mt-4">

Recycle Bin is Empty

</h3>


<p
class="text-sm text-slate-500 mt-1">

Deleted records will appear here.

</p>


</div>

<?php endif; ?>


</div>


</main>



<script>

/* =========================================================
   SIDE MENU
========================================================= */

function toggleMenu() {

    const menu =
        document.getElementById("sideMenu");

    if (!menu) {
        return;
    }

    menu.classList.toggle("hidden");
}


/* ESC */

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