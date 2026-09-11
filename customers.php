<?php

/* =========================================================
   AUTHENTICATION
========================================================= */

require_once "auth.php";
require_once "db.php";


$search = trim($_GET["search"] ?? "");


if ($search !== "") {

    $stmt = $conn->prepare("
        SELECT
            c.customer_id,
            c.name,
            c.phone,
            c.email,
            c.address,
            COUNT(r.repair_id) AS repair_count

        FROM customers c

        LEFT JOIN repairs r
            ON c.customer_id = r.customer_id

        WHERE
            c.name LIKE ?
            OR c.phone LIKE ?
            OR c.email LIKE ?

        GROUP BY c.customer_id

        ORDER BY c.customer_id DESC
    ");


    $term =
        "%" . $search . "%";


    $stmt->bind_param(
        "sss",
        $term,
        $term,
        $term
    );


    $stmt->execute();


    $customers =
        $stmt->get_result();

} else {

    $customers = $conn->query("
        SELECT
            c.customer_id,
            c.name,
            c.phone,
            c.email,
            c.address,
            COUNT(r.repair_id) AS repair_count

        FROM customers c

        LEFT JOIN repairs r
            ON c.customer_id = r.customer_id

        GROUP BY c.customer_id

        ORDER BY c.customer_id DESC
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

<title>REPXA - Customers</title>

<script src="https://cdn.tailwindcss.com"></script>

<link
href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"
rel="stylesheet">

</head>


<body class="bg-slate-50 text-slate-900">


<?php require_once "common_menu.php"; ?>


<main
class="max-w-5xl mx-auto px-5 py-7">


<div class="mb-6">


<p
class="text-xs font-semibold
tracking-wider text-slate-500">

CUSTOMER DATABASE

</p>


<h2
class="text-2xl font-bold mt-1">

All Customers

</h2>


<p
class="text-sm text-slate-500 mt-1">

View customers and their repair activity.

</p>


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
class="material-symbols-outlined
absolute left-4 top-1/2
-translate-y-1/2
text-slate-400">

search

</span>


<input
type="text"
name="search"
value="<?= htmlspecialchars($search) ?>"
placeholder="Search by name, phone or email"
class="w-full bg-white
border border-slate-300
rounded-xl pl-12 pr-4 py-3
outline-none
focus:border-blue-600">

</div>


<button
type="submit"
class="bg-blue-700
hover:bg-blue-800
text-white px-6 rounded-xl
font-semibold">

Search

</button>


</div>

</form>



<!-- =====================================================
     CUSTOMER LIST
===================================================== -->

<div class="space-y-4">


<?php if (
    $customers &&
    $customers->num_rows > 0
): ?>


<?php while (
    $customer =
    $customers->fetch_assoc()
): ?>


<div
class="bg-white
border border-slate-200
rounded-2xl p-5">


<div
class="flex flex-col
md:flex-row md:items-center
justify-between gap-5">


<!-- CUSTOMER INFO -->

<div
class="flex items-center gap-4">


<div
class="w-12 h-12 rounded-xl
bg-blue-50 flex items-center
justify-center">


<span
class="material-symbols-outlined
text-blue-700">

person

</span>


</div>


<div>


<h3
class="font-bold text-lg">

<?= htmlspecialchars(
    $customer["name"]
    ?? "Customer"
) ?>

</h3>


<p
class="text-sm text-slate-500">

<?= htmlspecialchars(
    $customer["phone"]
    ?? ""
) ?>

</p>


<?php if (
    !empty(
        $customer["email"]
    )
): ?>


<p
class="text-xs text-slate-400
mt-1">

<?= htmlspecialchars(
    $customer["email"]
) ?>

</p>


<?php endif; ?>


</div>


</div>



<!-- REPAIR COUNT + HISTORY -->

<div
class="flex items-center gap-4">


<div class="text-center">


<p
class="text-xs text-slate-500">

Repairs

</p>


<p
class="text-xl font-bold
text-blue-700">

<?= intval(
    $customer["repair_count"]
) ?>

</p>


</div>


<a
href="customer_history.php?id=<?= intval(
    $customer["customer_id"]
) ?>"
class="bg-slate-100
hover:bg-blue-50
text-slate-700
hover:text-blue-700
px-4 py-2.5 rounded-xl
font-semibold text-sm
flex items-center gap-2">


View History


<span
class="material-symbols-outlined
text-lg">

arrow_forward

</span>


</a>


</div>


</div>



<!-- ADDRESS -->

<?php if (
    !empty(
        $customer["address"]
    )
): ?>


<div
class="mt-4 pt-4
border-t border-slate-100
flex gap-2">


<span
class="material-symbols-outlined
text-slate-400 text-lg">

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


<?php endwhile; ?>


<?php else: ?>


<!-- =====================================================
     NO CUSTOMERS
===================================================== -->

<div
class="bg-white
border border-slate-200
rounded-2xl p-10 text-center">


<span
class="material-symbols-outlined
text-5xl text-slate-300">

person_search

</span>


<h3
class="font-semibold text-lg mt-3">

No Customers Found

</h3>


<p
class="text-sm text-slate-500 mt-1">


<?php if (
    $search !== ""
): ?>

No customer matched your search.


<?php else: ?>

Customers will appear here
after creating repair jobs.

<?php endif; ?>


</p>


</div>


<?php endif; ?>


</div>


</main>


</body>

</html>