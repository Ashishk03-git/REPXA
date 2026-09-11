<?php
session_start();
require_once "db.php";

/* =========================================================
   OWNER ACCESS ONLY
========================================================= */

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "owner"
) {
    header("Location: login.php");
    exit;
}


/* =========================================================
   MESSAGE
========================================================= */

$message = "";
$messageType = "";


/* =========================================================
   CREATE AGENT
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create_agent"])) {

    $name = trim($_POST["name"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($name === "" || $username === "" || $password === "") {

        $message = "Please fill all required details.";
        $messageType = "error";

    } elseif (strlen($password) < 6) {

        $message = "Password must be at least 6 characters.";
        $messageType = "error";

    } else {

        /* Check username */

        $check = $conn->prepare("
            SELECT id
            FROM users
            WHERE username = ?
            LIMIT 1
        ");

        $check->bind_param("s", $username);
        $check->execute();

        $result = $check->get_result();

        if ($result && $result->num_rows > 0) {

            $message = "Username already exists.";
            $messageType = "error";

        } else {

            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $role = "agent";
            $status = "active";

            $stmt = $conn->prepare("
                INSERT INTO users
                (
                    name,
                    username,
                    password,
                    role,
                    status
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "sssss",
                $name,
                $username,
                $hashedPassword,
                $role,
                $status
            );

            if ($stmt->execute()) {

                $message = "Agent account created successfully.";
                $messageType = "success";

            } else {

                $message = "Unable to create agent.";
                $messageType = "error";
            }

            $stmt->close();
        }

        $check->close();
    }
}


/* =========================================================
   CHANGE AGENT STATUS
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["toggle_status"])) {

    $agentId = intval($_POST["agent_id"] ?? 0);

    if ($agentId > 0) {

        $stmt = $conn->prepare("
            UPDATE users
            SET status =
                CASE
                    WHEN status = 'active' THEN 'inactive'
                    ELSE 'active'
                END
            WHERE id = ?
              AND role = 'agent'
        ");

        $stmt->bind_param("i", $agentId);

        if ($stmt->execute()) {

            $message = "Agent status updated.";
            $messageType = "success";

        } else {

            $message = "Unable to update agent status.";
            $messageType = "error";
        }

        $stmt->close();
    }
}


/* =========================================================
   GET USERS
========================================================= */

$users = $conn->query("
    SELECT
        id,
        name,
        username,
        role,
        status,
        created_at,
        last_login
    FROM users
    ORDER BY
        CASE
            WHEN role = 'owner' THEN 0
            ELSE 1
        END,
        id ASC
");

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>REPXA - Users</title>

<script src="https://cdn.tailwindcss.com"></script>

<link
href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
rel="stylesheet">

<link
href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"
rel="stylesheet">

<style>

body {
    font-family: Inter, sans-serif;
}

</style>

</head>


<body class="bg-slate-50 text-slate-900 pb-10">


<!-- HEADER -->

<header class="bg-white border-b sticky top-0 z-50">

<div class="max-w-5xl mx-auto px-5 h-16 flex items-center gap-3">

<a
href="profile.php"
class="w-10 h-10 rounded-full flex items-center justify-center hover:bg-slate-100">

<span class="material-symbols-outlined">
arrow_back
</span>

</a>

<div>

<h1 class="font-bold text-lg text-blue-700">
Users
</h1>

<p class="text-xs text-slate-500">
Manage REPXA users
</p>

</div>

</div>

</header>


<main class="max-w-5xl mx-auto px-5 py-7">


<!-- TITLE -->

<div class="mb-6">

<p class="text-xs font-semibold tracking-wider text-slate-500">
USER MANAGEMENT
</p>

<h2 class="text-2xl font-bold mt-1">
Owner & Agents
</h2>

<p class="text-sm text-slate-500 mt-1">
Create and manage users who can access REPXA.
</p>

</div>


<!-- MESSAGE -->

<?php if ($message !== ""): ?>

<div class="mb-6 p-4 rounded-xl
<?= $messageType === "success"
    ? "bg-green-100 text-green-700 border border-green-200"
    : "bg-red-100 text-red-700 border border-red-200"
?>">

<?= htmlspecialchars($message) ?>

</div>

<?php endif; ?>


<!-- CREATE AGENT -->

<div class="bg-white border border-slate-200 rounded-2xl p-6 mb-7">

<div class="flex items-center gap-3 mb-5">

<div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center">

<span class="material-symbols-outlined text-blue-700">
person_add
</span>

</div>

<div>

<h3 class="font-bold text-lg">
Create Agent
</h3>

<p class="text-xs text-slate-500">
Create a login account for a repair agent.
</p>

</div>

</div>


<form method="POST" class="space-y-4">


<div>

<label class="block text-sm font-semibold mb-2">
Agent Name *
</label>

<input
type="text"
name="name"
required
placeholder="Enter agent name"
class="w-full border border-slate-300 rounded-xl px-4 py-3 outline-none focus:border-blue-600">

</div>


<div>

<label class="block text-sm font-semibold mb-2">
Username / ID *
</label>

<input
type="text"
name="username"
required
placeholder="Example: rahul_agent"
class="w-full border border-slate-300 rounded-xl px-4 py-3 outline-none focus:border-blue-600">

</div>


<div>

<label class="block text-sm font-semibold mb-2">
Password *
</label>

<input
type="password"
name="password"
required
minlength="6"
placeholder="Minimum 6 characters"
class="w-full border border-slate-300 rounded-xl px-4 py-3 outline-none focus:border-blue-600">

</div>


<button
type="submit"
name="create_agent"
value="1"
class="w-full bg-blue-700 hover:bg-blue-800 text-white rounded-xl py-3.5 font-semibold flex items-center justify-center gap-2">

<span class="material-symbols-outlined">
person_add
</span>

Create Agent Account

</button>

</form>

</div>


<!-- USER LIST -->

<div class="flex items-center justify-between mb-4">

<div>

<p class="text-xs font-semibold tracking-wider text-slate-500">
REGISTERED USERS
</p>

<h2 class="text-xl font-bold mt-1">
All Users
</h2>

</div>

</div>


<div class="space-y-3">


<?php if ($users && $users->num_rows > 0): ?>

<?php while ($user = $users->fetch_assoc()): ?>

<div class="bg-white border border-slate-200 rounded-2xl p-5">

<div class="flex flex-col md:flex-row md:items-center justify-between gap-4">


<div class="flex items-center gap-4">

<div class="w-12 h-12 rounded-xl
<?= $user["role"] === "owner"
    ? "bg-blue-50"
    : "bg-green-50"
?>
flex items-center justify-center">

<span class="material-symbols-outlined
<?= $user["role"] === "owner"
    ? "text-blue-700"
    : "text-green-700"
?>">

<?= $user["role"] === "owner"
    ? "admin_panel_settings"
    : "engineering"
?>

</span>

</div>


<div>

<h3 class="font-bold">

<?= htmlspecialchars($user["name"]) ?>

</h3>

<p class="text-sm text-slate-500">

@<?= htmlspecialchars($user["username"]) ?>

</p>

</div>

</div>


<div class="flex items-center gap-3">


<span class="px-3 py-1.5 rounded-full text-xs font-semibold
<?= $user["role"] === "owner"
    ? "bg-blue-100 text-blue-700"
    : "bg-slate-100 text-slate-700"
?>">

<?= ucfirst(htmlspecialchars($user["role"])) ?>

</span>


<span class="px-3 py-1.5 rounded-full text-xs font-semibold
<?= $user["status"] === "active"
    ? "bg-green-100 text-green-700"
    : "bg-red-100 text-red-700"
?>">

<?= ucfirst(htmlspecialchars($user["status"])) ?>

</span>


<?php if ($user["role"] === "agent"): ?>

<form method="POST">

<input
type="hidden"
name="agent_id"
value="<?= intval($user["id"]) ?>">

<button
type="submit"
name="toggle_status"
value="1"
class="border border-slate-300 hover:bg-slate-50 px-3 py-2 rounded-xl text-sm font-semibold">

<?= $user["status"] === "active"
    ? "Deactivate"
    : "Activate"
?>

</button>

</form>

<?php endif; ?>


</div>

</div>


<div class="mt-4 pt-4 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs text-slate-500">

<div>

<strong class="text-slate-600">
Created:
</strong>

<?= !empty($user["created_at"])
    ? date("d M Y, h:i A", strtotime($user["created_at"]))
    : "—"
?>

</div>


<div>

<strong class="text-slate-600">
Last Login:
</strong>

<?= !empty($user["last_login"])
    ? date("d M Y, h:i A", strtotime($user["last_login"]))
    : "Never"
?>

</div>

</div>


</div>

<?php endwhile; ?>

<?php else: ?>

<div class="bg-white border rounded-2xl p-10 text-center">

<span class="material-symbols-outlined text-5xl text-slate-300">
group_off
</span>

<h3 class="font-semibold text-lg mt-3">
No Users Found
</h3>

</div>

<?php endif; ?>


</div>


<!-- BACK -->

<div class="mt-7">

<a
href="profile.php"
class="inline-flex items-center gap-2 border border-slate-300 bg-white hover:bg-slate-50 px-5 py-3 rounded-xl font-semibold text-sm">

<span class="material-symbols-outlined">
arrow_back
</span>

Back to Profile

</a>

</div>


</main>

</body>
</html>