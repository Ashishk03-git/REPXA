<?php
require_once "auth.php";
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0">

<title>REPXA - Help & Support</title>

<script src="https://cdn.tailwindcss.com"></script>

<link
href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"
rel="stylesheet">

</head>


<body class="bg-slate-50 text-slate-900">


<!-- HEADER -->

<header class="bg-white border-b sticky top-0 z-50">

<div class="max-w-5xl mx-auto px-5 h-16 flex items-center justify-between">


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

<h1 class="font-bold text-lg text-blue-700">
Help & Support
</h1>

<p class="text-xs text-slate-500">
REPXA Support
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



<!-- RIGHT SIDE MENU -->

<div
id="sideMenu"
class="hidden fixed inset-0 z-[100]">


<div
onclick="toggleMenu()"
class="absolute inset-0 bg-black/40">
</div>


<aside
class="absolute right-0 top-0 bottom-0 w-[310px] max-w-[88vw] bg-white shadow-2xl">


<div class="p-5 border-b">

<div class="flex items-center justify-between">

<div>

<h2 class="font-bold text-lg text-blue-700">
REPXA
</h2>

<p class="text-xs text-slate-500">
Repair Management System
</p>

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


<div class="p-4 space-y-1">


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
class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-100">

<span class="material-symbols-outlined">
delete
</span>

<span>Recycle Bin</span>

</a>


<a
href="help.php"
class="flex items-center gap-3 p-3 rounded-xl bg-blue-50 text-blue-700 font-semibold">

<span class="material-symbols-outlined">
help
</span>

<span>Help & Support</span>

</a>


</div>


<div class="absolute bottom-0 left-0 right-0 p-4 border-t">

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



<!-- MAIN -->

<main class="max-w-5xl mx-auto px-5 py-10">


<div class="min-h-[65vh] flex items-center justify-center">


<div class="text-center max-w-md">


<div
class="w-20 h-20 mx-auto rounded-3xl bg-blue-50 flex items-center justify-center">

<span
class="material-symbols-outlined text-blue-700 text-5xl">

support_agent

</span>

</div>


<div
class="inline-flex items-center gap-2 mt-6 px-4 py-2 rounded-full bg-orange-100 text-orange-700 text-xs font-bold">

<span class="material-symbols-outlined text-sm">
schedule
</span>

COMING SOON

</div>


<h2 class="text-3xl font-bold mt-5">
Help & Support
</h2>


<p class="text-slate-500 mt-3 leading-relaxed">

Help & Support features are currently under development.
They will be available in a future update of REPXA.

</p>


<a
href="dashboard.php"
class="inline-flex items-center gap-2 mt-7 bg-blue-700 hover:bg-blue-800 text-white px-5 py-3 rounded-xl font-semibold">

<span class="material-symbols-outlined">
arrow_back
</span>

Back to Dashboard

</a>


</div>

</div>

</main>



<script>

function toggleMenu() {

    const menu =
        document.getElementById("sideMenu");

    menu.classList.toggle("hidden");

}


document.addEventListener(
    "keydown",
    function(event) {

        if (event.key === "Escape") {

            const menu =
                document.getElementById("sideMenu");

            if (!menu.classList.contains("hidden")) {
                toggleMenu();
            }

        }

    }
);

</script>


</body>
</html>