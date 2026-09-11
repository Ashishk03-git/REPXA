<?php

/* =========================================================
   AUTHENTICATION
========================================================= */

require_once "auth.php";
require_once "db.php";

$currentUserRole = strtolower(trim($_SESSION["role"] ?? ""));
$isOwner = ($currentUserRole === "owner");

if (!$isOwner) {
    header("Location: agent_dashboard.php");
    exit;
}



$message = "";
$messageType = "success";


/* =========================================
   FIND INVENTORY PRIMARY KEY AUTOMATICALLY
========================================= */

$idColumn = "";

$columnsResult = $conn->query(
    "SHOW COLUMNS FROM inventory"
);

if ($columnsResult) {

    while (
        $column =
        $columnsResult->fetch_assoc()
    ) {

        if (
            $column["Key"] === "PRI"
        ) {

            $idColumn =
                $column["Field"];

            break;
        }
    }
}



/* =========================================
   ADD / UPDATE / REMOVE
========================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
) {

    $action =
        $_POST["action"] ?? "";



    /* =====================================
       ADD PART
    ===================================== */

    if (
        $action === "add"
    ) {

        $partName =
            trim(
                $_POST["part_name"] ?? ""
            );


        $compatibleDevice =
            trim(
                $_POST["compatible_device"] ?? ""
            );


        $stockQuantity =
            max(
                0,
                intval(
                    $_POST["stock_quantity"] ?? 0
                )
            );


        $reorderLevel =
            max(
                0,
                intval(
                    $_POST["reorder_level"] ?? 5
                )
            );


        if (
            $partName === ""
        ) {

            $message =
                "Part name is required.";

            $messageType =
                "error";

        } else {

            $stmt =
                $conn->prepare("
                    INSERT INTO inventory
                    (
                        part_name,
                        compatible_device,
                        stock_quantity,
                        reorder_level
                    )
                    VALUES (?, ?, ?, ?)
                ");


            $stmt->bind_param(
                "ssii",
                $partName,
                $compatibleDevice,
                $stockQuantity,
                $reorderLevel
            );


            if (
                $stmt->execute()
            ) {

                $message =
                    "Spare part added successfully!";

            } else {

                $message =
                    "Unable to add spare part.";

                $messageType =
                    "error";
            }


            $stmt->close();
        }
    }



    /* =====================================
       UPDATE STOCK
    ===================================== */

    if (
        $action === "update"
    ) {

        $recordId =
            intval(
                $_POST["record_id"] ?? 0
            );


        $stockQuantity =
            max(
                0,
                intval(
                    $_POST["stock_quantity"] ?? 0
                )
            );


        $reorderLevel =
            max(
                0,
                intval(
                    $_POST["reorder_level"] ?? 0
                )
            );


        if (
            $recordId > 0
            &&
            $idColumn !== ""
        ) {

            $sql = "
                UPDATE inventory
                SET
                    stock_quantity = ?,
                    reorder_level = ?
                WHERE `$idColumn` = ?
            ";


            $stmt =
                $conn->prepare(
                    $sql
                );


            $stmt->bind_param(
                "iii",
                $stockQuantity,
                $reorderLevel,
                $recordId
            );


            if (
                $stmt->execute()
            ) {

                $message =
                    "Stock updated successfully!";

            } else {

                $message =
                    "Unable to update stock.";

                $messageType =
                    "error";
            }


            $stmt->close();
        }
    }



    /* =====================================
       REMOVE PART
    ===================================== */

    if (
        $action === "delete"
    ) {

        $recordId =
            intval(
                $_POST["record_id"] ?? 0
            );


        if (
            $recordId > 0
            &&
            $idColumn !== ""
        ) {

            $sql = "
                DELETE FROM inventory
                WHERE `$idColumn` = ?
            ";


            $stmt =
                $conn->prepare(
                    $sql
                );


            $stmt->bind_param(
                "i",
                $recordId
            );


            if (
                $stmt->execute()
            ) {

                $message =
                    "Spare part removed successfully!";

            } else {

                $message =
                    "Unable to remove spare part.";

                $messageType =
                    "error";
            }


            $stmt->close();
        }
    }
}



/* =========================================
   SEARCH
========================================= */

$search =
    trim(
        $_GET["search"] ?? ""
    );


if (
    $search !== ""
) {

    $stmt =
        $conn->prepare("
            SELECT *
            FROM inventory
            WHERE
                part_name LIKE ?
                OR compatible_device LIKE ?
            ORDER BY part_name ASC
        ");


    $term =
        "%" . $search . "%";


    $stmt->bind_param(
        "ss",
        $term,
        $term
    );


    $stmt->execute();


    $inventory =
        $stmt->get_result();

} else {

    $inventory =
        $conn->query("
            SELECT *
            FROM inventory
            ORDER BY part_name ASC
        ");
}



/* =========================================
   SUMMARY
========================================= */

$totalParts =
    0;

$totalQuantity =
    0;

$lowStock =
    0;


if (
    $inventory
) {

    while (
        $item =
        $inventory->fetch_assoc()
    ) {

        $totalParts++;


        $stock =
            intval(
                $item["stock_quantity"]
            );


        $reorder =
            intval(
                $item["reorder_level"]
            );


        $totalQuantity +=
            $stock;


        if (
            $stock <= $reorder
        ) {

            $lowStock++;
        }
    }


    $inventory->data_seek(0);
}

?>


<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0">

<title>REPXA - Inventory</title>

<script
src="https://cdn.tailwindcss.com">
</script>

<link
href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"
rel="stylesheet">

</head>


<body
class="bg-slate-50 text-slate-900">


<!-- HEADER -->

<header
class="bg-white border-b sticky top-0 z-50">


<div
class="max-w-5xl mx-auto px-5 h-16
flex items-center gap-3">


<a
href="dashboard.php"
class="w-10 h-10 rounded-full
flex items-center justify-center
hover:bg-slate-100">


<span
class="material-symbols-outlined
text-blue-700">

arrow_back

</span>


</a>


<div>

<h1
class="text-lg font-bold text-blue-700">

Inventory

</h1>


<p
class="text-xs text-slate-500">

Spare parts management

</p>


</div>


</div>


</header>



<main
class="max-w-5xl mx-auto px-5 py-6">


<!-- TITLE -->

<div
class="flex flex-col sm:flex-row
sm:items-center sm:justify-between
gap-4 mb-5">


<div>

<p
class="text-xs font-semibold
tracking-wider text-slate-500">

STOCK MANAGEMENT

</p>


<h2
class="text-2xl font-bold mt-1">

Spare Parts

</h2>


</div>


<button
type="button"
id="addSparePartBtn"
class="bg-blue-700
hover:bg-blue-800
text-white px-5 py-3
rounded-xl font-semibold
flex items-center
justify-center gap-2">


<span
class="material-symbols-outlined">

add

</span>


Add Spare Part


</button>


</div>



<!-- MESSAGE -->

<?php if (
    $message !== ""
): ?>


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



<!-- ADD FORM -->

<div
id="addForm"
class="hidden bg-white
border rounded-2xl p-5 mb-6">


<div
class="flex justify-between
items-center mb-4">


<div>

<h3
class="font-bold text-lg">

Add Spare Part

</h3>


<p
class="text-xs text-slate-500">

Enter spare part details

</p>


</div>


<button
type="button"
id="closeAddFormBtn"
class="w-9 h-9 rounded-full
hover:bg-slate-100
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
class="grid grid-cols-1
md:grid-cols-2 gap-4">


<input
type="hidden"
name="action"
value="add">


<div>

<label
class="text-sm font-semibold">

Part Name

</label>


<input
type="text"
name="part_name"
required
placeholder="Samsung S21 Display"
class="w-full mt-1
border border-slate-300
rounded-xl px-4 py-3
outline-none
focus:border-blue-600">


</div>



<div>

<label
class="text-sm font-semibold">

Compatible Device

</label>


<input
type="text"
name="compatible_device"
placeholder="Samsung S21"
class="w-full mt-1
border border-slate-300
rounded-xl px-4 py-3
outline-none
focus:border-blue-600">


</div>



<div>

<label
class="text-sm font-semibold">

Stock Quantity

</label>


<input
type="number"
name="stock_quantity"
min="0"
value="0"
class="w-full mt-1
border border-slate-300
rounded-xl px-4 py-3">


</div>



<div>

<label
class="text-sm font-semibold">

Reorder Level

</label>


<input
type="number"
name="reorder_level"
min="0"
value="5"
class="w-full mt-1
border border-slate-300
rounded-xl px-4 py-3">


</div>



<div
class="md:col-span-2
flex justify-end">


<button
type="submit"
class="bg-blue-700
hover:bg-blue-800
text-white px-6 py-3
rounded-xl font-semibold">


Save Spare Part


</button>


</div>


</form>


</div>



<!-- SUMMARY -->

<div
class="grid grid-cols-3
gap-3 mb-5">


<div
class="bg-white border
rounded-xl p-3">


<p
class="text-xs text-slate-500">

Parts

</p>


<p
class="text-xl font-bold
text-blue-700">

<?= $totalParts ?>

</p>


</div>



<div
class="bg-white border
rounded-xl p-3">


<p
class="text-xs text-slate-500">

Quantity

</p>


<p
class="text-xl font-bold
text-green-600">

<?= $totalQuantity ?>

</p>


</div>



<div
class="bg-white border
rounded-xl p-3">


<p
class="text-xs text-slate-500">

Low Stock

</p>


<p
class="text-xl font-bold
text-orange-500">

<?= $lowStock ?>

</p>


</div>


</div>



<!-- SEARCH -->

<form
method="GET"
class="mb-5">


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
placeholder="Search spare part..."
class="w-full bg-white
border border-slate-300
rounded-xl pl-12 pr-4 py-3
outline-none
focus:border-blue-600">


</div>


</form>



<!-- INVENTORY LIST -->

<div
class="space-y-3">


<?php if (
    $inventory
    &&
    $inventory->num_rows > 0
): ?>


<?php while (
    $item =
    $inventory->fetch_assoc()
): ?>


<?php

$recordId =
    0;


if (
    $idColumn !== ""
    &&
    isset(
        $item[$idColumn]
    )
) {

    $recordId =
        intval(
            $item[$idColumn]
        );
}


$partName =
    $item["part_name"];


$stock =
    intval(
        $item["stock_quantity"]
    );


$reorder =
    intval(
        $item["reorder_level"]
    );


if (
    $stock <= $reorder
) {

    $stockClass =
        "bg-red-100 text-red-700";

    $stockText =
        "Low Stock";

} else {

    $stockClass =
        "bg-green-100 text-green-700";

    $stockText =
        "In Stock";
}

?>


<!-- ITEM CARD -->

<div
class="bg-white border
border-slate-200
rounded-xl px-4 py-3">


<!-- TOP -->

<div
class="flex items-center
justify-between gap-3">


<div
class="flex items-center
gap-3 min-w-0">


<div
class="w-10 h-10
rounded-lg bg-blue-50
flex items-center
justify-center shrink-0">


<span
class="material-symbols-outlined
text-blue-700">

memory

</span>


</div>


<div
class="min-w-0">


<h3
class="font-semibold truncate">

<?= htmlspecialchars(
    $partName
) ?>


</h3>


<p
class="text-xs text-slate-500
truncate">

<?= htmlspecialchars(
    $item["compatible_device"]
    ?: "General"
) ?>


</p>


</div>


</div>



<div
class="flex items-center
gap-3 shrink-0">


<div
class="text-center">


<p
class="text-[10px]
text-slate-400">

Qty

</p>


<p
class="font-bold">

<?= $stock ?>

</p>


</div>


<span
class="<?= $stockClass ?>
px-2.5 py-1
rounded-full
text-[11px]
font-semibold">

<?= $stockText ?>

</span>


</div>


</div>



<!-- BUTTONS -->

<div
class="flex justify-end
gap-2 mt-3 pt-3
border-t border-slate-100">


<!-- UPDATE -->

<button
type="button"
class="update-stock-btn
px-3 py-1.5
rounded-lg bg-blue-50
text-blue-700
hover:bg-blue-100
text-xs font-semibold
flex items-center gap-1"
data-record-id="<?= $recordId ?>"
data-part-name='<?= json_encode(
    $partName
) ?>'
data-stock="<?= $stock ?>"
data-reorder="<?= $reorder ?>">


<span
class="material-symbols-outlined
text-sm">

edit

</span>


Update


</button>



<!-- REMOVE -->

<form
method="POST"
style="display:inline;"
onsubmit="return confirm(
'Remove this spare part?'
);">


<input
type="hidden"
name="action"
value="delete">


<input
type="hidden"
name="record_id"
value="<?= $recordId ?>">


<button
type="submit"
class="px-3 py-1.5
rounded-lg bg-red-50
text-red-600
hover:bg-red-100
text-xs font-semibold
flex items-center gap-1">


<span
class="material-symbols-outlined
text-sm">

delete

</span>


Remove


</button>


</form>


</div>


</div>


<?php endwhile; ?>


<?php else: ?>


<div
class="bg-white border
rounded-xl p-8
text-center">


<span
class="material-symbols-outlined
text-5xl text-slate-300">

inventory_2

</span>


<p
class="font-semibold mt-3">

No spare parts found.

</p>


</div>


<?php endif; ?>


</div>


</main>



<!-- UPDATE MODAL -->

<div
id="updateModal"
class="hidden fixed inset-0
z-[100] bg-black/40
flex items-center
justify-center px-5">


<div
class="bg-white rounded-2xl
p-5 w-full max-w-sm
shadow-xl">


<div
class="flex justify-between
items-center mb-4">


<div>

<h3
class="font-bold text-lg">

Update Stock

</h3>


<p
id="updatePartName"
class="text-xs
text-slate-500">

</p>


</div>


<button
type="button"
onclick="closeUpdate()"
class="w-9 h-9
rounded-full
hover:bg-slate-100
flex items-center
justify-center">


<span
class="material-symbols-outlined">

close

</span>


</button>


</div>



<form
method="POST">


<input
type="hidden"
name="action"
value="update">


<input
type="hidden"
id="updateId"
name="record_id">


<label
class="text-sm font-semibold">

Stock Quantity

</label>


<input
type="number"
id="updateStock"
name="stock_quantity"
min="0"
required
class="w-full mt-1 mb-4
border border-slate-300
rounded-xl px-4 py-3">


<label
class="text-sm font-semibold">

Reorder Level

</label>


<input
type="number"
id="updateReorder"
name="reorder_level"
min="0"
required
class="w-full mt-1 mb-5
border border-slate-300
rounded-xl px-4 py-3">


<button
type="submit"
class="w-full bg-blue-700
hover:bg-blue-800
text-white rounded-xl
py-3 font-semibold">

Save Changes

</button>


</form>


</div>


</div>



<script>

document.addEventListener(
    "DOMContentLoaded",
    function () {


    const addForm =
        document.getElementById(
            "addForm"
        );


    const addButton =
        document.getElementById(
            "addSparePartBtn"
        );


    const closeAddButton =
        document.getElementById(
            "closeAddFormBtn"
        );


    if (
        addButton &&
        addForm
    ) {

        addButton.addEventListener(
            "click",
            function () {

                addForm.classList.toggle(
                    "hidden"
                );

            }
        );

    }


    if (
        closeAddButton &&
        addForm
    ) {

        closeAddButton.addEventListener(
            "click",
            function () {

                addForm.classList.add(
                    "hidden"
                );

            }
        );

    }



    const updateModal =
        document.getElementById(
            "updateModal"
        );


    const updateId =
        document.getElementById(
            "updateId"
        );


    const updatePartName =
        document.getElementById(
            "updatePartName"
        );


    const updateStock =
        document.getElementById(
            "updateStock"
        );


    const updateReorder =
        document.getElementById(
            "updateReorder"
        );



    document
        .querySelectorAll(
            ".update-stock-btn"
        )
        .forEach(
            function (button) {


            button.addEventListener(
                "click",
                function () {


                updateId.value =
                    this.dataset.recordId;


                updatePartName.textContent =
                    this.dataset.partName;


                updateStock.value =
                    this.dataset.stock;


                updateReorder.value =
                    this.dataset.reorder;


                updateModal.classList.remove(
                    "hidden"
                );


                }
            );

        }
    );



    window.closeUpdate =
        function () {

            if (
                updateModal
            ) {

                updateModal.classList.add(
                    "hidden"
                );

            }

        };



    if (
        updateModal
    ) {

        updateModal.addEventListener(
            "click",
            function (event) {

                if (
                    event.target ===
                    updateModal
                ) {

                    window.closeUpdate();

                }

            }
        );

    }



    document.addEventListener(
        "keydown",
        function (event) {

            if (
                event.key ===
                "Escape"
            ) {

                if (
                    addForm
                ) {

                    addForm.classList.add(
                        "hidden"
                    );

                }


                window.closeUpdate();

            }

        }
    );


});

</script>


</body>

</html>