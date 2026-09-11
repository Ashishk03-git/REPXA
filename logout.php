<?php

session_start();

/* Destroy current session */
session_unset();
session_destroy();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>REPXA - Logout</title>

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

</style>

</head>


<body class="min-h-screen bg-slate-50 flex items-center justify-center p-5">


<div class="fixed inset-0 bg-slate-900/40"></div>


<div class="popup relative bg-white w-full max-w-sm rounded-3xl p-7 text-center shadow-xl">

<div class="w-16 h-16 mx-auto rounded-full bg-green-100 flex items-center justify-center">

<span class="material-symbols-outlined text-green-600 text-4xl">
check_circle
</span>

</div>


<h2 class="text-xl font-bold mt-5">
Logout Successful
</h2>


<p class="text-sm text-slate-500 mt-2">
You have been logged out successfully.
</p>


<div class="mt-5 h-1 bg-slate-100 rounded-full overflow-hidden">

<div
class="h-full bg-green-500 rounded-full"
style="
width:100%;
animation: logoutProgress 1.5s linear forwards;
">

</div>

</div>

<p class="text-xs text-slate-400 mt-3">
Redirecting to login...
</p>

</div>


<style>

@keyframes logoutProgress {

from {
    width: 100%;
}

to {
    width: 0%;
}

}

</style>


<script>

setTimeout(function () {

    window.location.href = "login.php";

}, 1500);

</script>


</body>

</html>