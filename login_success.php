<?php

session_start();

/*
=========================================================
 REPXA - LOGIN SUCCESS
 Owner = Full Access
 Other Staff = Limited Access
=========================================================
*/

/* -------------------------------------------------------
   SESSION CHECK
------------------------------------------------------- */

if (
    !isset($_SESSION["user_id"]) ||
    empty($_SESSION["user_id"])
) {
    header("Location: login.php");
    exit;
}


/* -------------------------------------------------------
   USER DATA
------------------------------------------------------- */

$userName = $_SESSION["user_name"] ?? "User";

$userRole = strtolower(
    trim($_SESSION["role"] ?? "")
);


/* -------------------------------------------------------
   DISPLAY ROLE
------------------------------------------------------- */

if ($userRole === "owner") {

    $displayRole = "Owner";
    $accessText = "Full access";

} else {

    $displayRole = "Staff";
    $accessText = "Limited access";

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0">

<title>REPXA - Login Successful</title>

<script src="https://cdn.tailwindcss.com"></script>

<link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"
    rel="stylesheet">

<link
    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"
    rel="stylesheet">


<style>

body {
    font-family: Inter, sans-serif;
}

.popup {
    animation: popup .25s ease-out;
}

@keyframes popup {

    from {
        opacity: 0;
        transform: scale(.9);
    }

    to {
        opacity: 1;
        transform: scale(1);
    }

}

.progress {
    width: 100%;
    animation: progress 1.5s linear forwards;
}

@keyframes progress {

    from {
        width: 100%;
    }

    to {
        width: 0%;
    }

}

</style>

</head>


<body
class="min-h-screen bg-slate-50
flex items-center justify-center p-5">


<!-- OVERLAY -->

<div
class="fixed inset-0 bg-slate-900/40">
</div>


<!-- SUCCESS POPUP -->

<div
class="popup relative bg-white
w-full max-w-sm rounded-3xl
p-7 text-center shadow-xl">


<!-- SUCCESS ICON -->

<div
class="w-16 h-16 mx-auto
rounded-full bg-green-100
flex items-center justify-center">

<span
class="material-symbols-outlined
text-green-600 text-4xl">

check_circle

</span>

</div>


<!-- TITLE -->

<h2
class="text-xl font-bold mt-5">

Login Successful

</h2>


<!-- USER NAME -->

<p
class="text-sm text-slate-500 mt-2">

Welcome,

<strong class="text-slate-700">

<?= htmlspecialchars($userName) ?>

</strong>

</p>


<!-- ROLE -->

<p
class="text-xs text-slate-400 mt-2">

<?= htmlspecialchars($displayRole) ?>

&nbsp;•&nbsp;

<?= htmlspecialchars($accessText) ?>

</p>


<!-- PROGRESS -->

<div
class="mt-5 h-1 bg-slate-100
rounded-full overflow-hidden">

<div
class="progress h-full
bg-green-500 rounded-full">
</div>

</div>


<!-- REDIRECT TEXT -->

<p
class="text-xs text-slate-400 mt-3">

Opening your dashboard...

</p>


</div>


<script>

/*
=========================================================
 REDIRECT
=========================================================
*/

const userRole = <?= json_encode($userRole) ?>;


/* -------------------------------------------------------
   REDIRECT FUNCTION
------------------------------------------------------- */

function openDashboard() {

    if (userRole === "owner") {

        window.location.href = "dashboard.php";

    } else {

        window.location.href = "agent_dashboard.php";

    }

}


/* -------------------------------------------------------
   AUTOMATIC REDIRECT
------------------------------------------------------- */

setTimeout(function () {

    openDashboard();

}, 1500);

</script>


</body>

</html>