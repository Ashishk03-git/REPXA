<?php

session_start();
require_once "db.php";


/*
=========================================================
REPXA LOGIN SYSTEM
=========================================================
Owner = Full Access
Other = All Staff / Limited Access
=========================================================
*/


/*
=========================================================
HELPER FUNCTIONS
=========================================================
*/

function generateStaffPassword($name, $phone)
{
    $parts = preg_split('/\s+/', trim($name));

    $firstName = $parts[0] ?? "User";

    $firstName = preg_replace(
        '/[^A-Za-z0-9]/',
        '',
        $firstName
    );

    $digits = preg_replace(
        '/\D/',
        '',
        $phone
    );

    $lastFour = substr(
        $digits,
        -4
    );

    if (strlen($lastFour) < 4) {

        $lastFour = str_pad(
            $lastFour,
            4,
            "0",
            STR_PAD_LEFT
        );
    }

    return $firstName . "@" . $lastFour;
}


/*
=========================================================
CAPTCHA GENERATOR
=========================================================
*/

function generateCaptcha()
{
    $num1 = random_int(2, 9);
    $num2 = random_int(1, 9);

    $_SESSION["captcha_answer"] =
        $num1 + $num2;

    $_SESSION["captcha_question"] =
        $num1 . " + " . $num2 . " = ?";

    $_SESSION["captcha_created"] =
        time();
}


/*
=========================================================
GENERATE CAPTCHA IF NOT AVAILABLE
=========================================================
*/

if (
    !isset($_SESSION["captcha_answer"]) ||
    !isset($_SESSION["captcha_question"])
) {

    generateCaptcha();
}


/*
=========================================================
CAPTCHA REFRESH
=========================================================
*/

if (
    isset($_GET["refresh_captcha"])
) {

    generateCaptcha();

    header(
        "Location: login.php"
    );

    exit;
}


/*
=========================================================
MESSAGES
=========================================================
*/

$message = "";
$messageType = "";
$loginSuccess = false;
$redirectPage = "agent_dashboard.php";


/*
=========================================================
ALREADY LOGGED IN
=========================================================
*/

if (
    isset($_SESSION["user_id"])
) {

    $existingRole = strtolower(trim($_SESSION["role"] ?? ""));
    header(
        "Location: " . ($existingRole === "owner"
            ? "dashboard.php"
            : "agent_dashboard.php")
    );
    exit;
}


/*
=========================================================
LOGIN
=========================================================
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
) {

    $username =
        trim(
            $_POST["username"] ?? ""
        );


    $password =
        $_POST["password"] ?? "";


    $loginType =
        strtolower(
            trim(
                $_POST["role"] ?? ""
            )
        );


    $captchaInput =
        trim(
            $_POST["captcha"] ?? ""
        );


    /*
    =====================================================
    BASIC VALIDATION
    =====================================================
    */

    if (
        $username === "" ||
        $password === "" ||
        $loginType === ""
    ) {

        $message =
            "Please enter username, password and select login type.";

        $messageType =
            "error";

    }


    /*
    =====================================================
    CAPTCHA VALIDATION
    =====================================================
    */

    elseif (
        $captchaInput === "" ||
        !isset(
            $_SESSION["captcha_answer"]
        ) ||
        intval($captchaInput) !==
        intval(
            $_SESSION["captcha_answer"]
        )
    ) {

        $message =
            "Incorrect security answer.";

        $messageType =
            "error";


        generateCaptcha();

    }


    /*
    =====================================================
    LOGIN TYPE VALIDATION
    =====================================================
    */

    elseif (
        !in_array(
            $loginType,
            [
                "owner",
                "other"
            ],
            true
        )
    ) {

        $message =
            "Invalid login type.";

        $messageType =
            "error";

    }


    else {


        /*
        =================================================
        OWNER LOGIN
        =================================================
        */

        if (
            $loginType === "owner"
        ) {

            $stmt =
                $conn->prepare("
                    SELECT
                        id,
                        name,
                        username,
                        password,
                        role,
                        status
                    FROM users
                    WHERE username = ?
                    AND LOWER(TRIM(role)) = 'owner'
                    LIMIT 1
                ");


            $stmt->bind_param(
                "s",
                $username
            );

        }


        /*
        =================================================
        STAFF LOGIN
        =================================================

        Other means every NON-OWNER account.
        =================================================
        */

        else {

            $stmt =
                $conn->prepare("
                    SELECT
                        id,
                        name,
                        username,
                        password,
                        role,
                        status
                    FROM users
                    WHERE username = ?
                    AND LOWER(TRIM(role)) <> 'owner'
                    LIMIT 1
                ");


            $stmt->bind_param(
                "s",
                $username
            );
        }


        /*
        =================================================
        EXECUTE USER SEARCH
        =================================================
        */

        $stmt->execute();


        $result =
            $stmt->get_result();


        /*
        =================================================
        IF USER DOES NOT EXIST
        =================================================

        This handles old staff records that were created
        before login accounts were connected.
        =================================================
        */

        if (
            !$result ||
            $result->num_rows === 0
        ) {


            /*
            =============================================
            ONLY FOR STAFF / OTHER
            =============================================
            */

            if (
                $loginType === "other"
            ) {

                $staffStmt =
                    $conn->prepare("
                        SELECT
                            staff_id,
                            agent_id,
                            user_id,
                            name,
                            phone,
                            role,
                            status
                        FROM staff
                        WHERE agent_id = ?
                        LIMIT 1
                    ");


                $staffStmt->bind_param(
                    "s",
                    $username
                );


                $staffStmt->execute();


                $staffResult =
                    $staffStmt
                    ->get_result();


                if (
                    $staffResult &&
                    $staffResult->num_rows === 1
                ) {

                    $staff =
                        $staffResult
                        ->fetch_assoc();


                    /*
                    =====================================
                    OLD STAFF WITHOUT USER ACCOUNT
                    =====================================
                    */

                    if (
                        empty(
                            $staff["user_id"]
                        )
                    ) {

                        $defaultPassword =
                            generateStaffPassword(
                                $staff["name"],
                                $staff["phone"]
                            );


                        /*
                        ---------------------------------
                        HASH PASSWORD
                        ---------------------------------
                        */

                        $hashedPassword =
                            password_hash(
                                $defaultPassword,
                                PASSWORD_DEFAULT
                            );


                        /*
                        ---------------------------------
                        CREATE USERS ACCOUNT
                        ---------------------------------
                        */

                        $createUser =
                            $conn->prepare("
                                INSERT INTO users
                                (
                                    name,
                                    username,
                                    password,
                                    role,
                                    status
                                )
                                VALUES
                                (?, ?, ?, ?, ?)
                            ");


                        $staffRole =
                            strtolower(
                                trim(
                                    $staff["role"]
                                )
                            );


                        $createUser->bind_param(
                            "sssss",
                            $staff["name"],
                            $staff["agent_id"],
                            $hashedPassword,
                            $staffRole,
                            $staff["status"]
                        );


                        if (
                            $createUser->execute()
                        ) {

                            $newUserId =
                                $conn->insert_id;


                            $createUser->close();


                            /*
                            -----------------------------
                            LINK STAFF TO USERS
                            -----------------------------
                            */

                            $linkStaff =
                                $conn->prepare("
                                    UPDATE staff
                                    SET user_id = ?
                                    WHERE staff_id = ?
                                ");


                            $linkStaff->bind_param(
                                "ii",
                                $newUserId,
                                $staff["staff_id"]
                            );


                            $linkStaff->execute();


                            $linkStaff->close();


                            /*
                            -----------------------------
                            LOAD NEW USER
                            -----------------------------
                            */

                            $userStmt =
                                $conn->prepare("
                                    SELECT
                                        id,
                                        name,
                                        username,
                                        password,
                                        role,
                                        status
                                    FROM users
                                    WHERE id = ?
                                    LIMIT 1
                                ");


                            $userStmt->bind_param(
                                "i",
                                $newUserId
                            );


                            $userStmt->execute();


                            $result =
                                $userStmt
                                ->get_result();


                            $userStmt->close();

                        }

                        else {

                            $createUser->close();
                        }
                    }

                    else {

                        /*
                        =================================
                        STAFF HAS USER ID
                        =================================
                        */

                        $userId =
                            intval(
                                $staff["user_id"]
                            );


                        $userStmt =
                            $conn->prepare("
                                SELECT
                                    id,
                                    name,
                                    username,
                                    password,
                                    role,
                                    status
                                FROM users
                                WHERE id = ?
                                LIMIT 1
                            ");


                        $userStmt->bind_param(
                            "i",
                            $userId
                        );


                        $userStmt->execute();


                        $result =
                            $userStmt
                            ->get_result();


                        $userStmt->close();
                    }
                }


                $staffStmt->close();
            }
        }


        /*
        =================================================
        USER FOUND
        =================================================
        */

        if (
            $result &&
            $result->num_rows === 1
        ) {

            $user =
                $result->fetch_assoc();


            /*
            =============================================
            ACCOUNT STATUS
            =============================================
            */

            if (
                strtolower(
                    trim(
                        $user["status"]
                    )
                ) !== "active"
            ) {

                $message =
                    "This account is inactive.";

                $messageType =
                    "error";

            }


            /*
            =============================================
            PASSWORD
            =============================================
            */

            elseif (
                password_verify(
                    $password,
                    $user["password"]
                )
            ) {


                /*
                =========================================
                SESSION SECURITY
                =========================================
                */

                session_regenerate_id(
                    true
                );


                /*
                =========================================
                SAVE SESSION
                =========================================
                */

                $_SESSION["user_id"] =
                    intval(
                        $user["id"]
                    );


                $_SESSION["user_name"] =
                    $user["name"];


                $_SESSION["username"] =
                    $user["username"];


                $_SESSION["role"] =
                    strtolower(
                        trim(
                            $user["role"]
                        )
                    );


                /*
                =========================================
                LAST LOGIN
                =========================================
                */

                $update =
                    $conn->prepare("
                        UPDATE users
                        SET last_login =
                            CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");


                $update->bind_param(
                    "i",
                    $user["id"]
                );


                $update->execute();

                $update->close();


                /*
                =========================================
                REMOVE USED CAPTCHA
                =========================================
                */

                unset(
                    $_SESSION["captcha_answer"]
                );

                unset(
                    $_SESSION["captcha_question"]
                );


                /*
                =========================================
                LOGIN SUCCESS
                =========================================
                */

                /*
                 * LOGIN SUCCESS
                 * Keep the session in this same request and show the
                 * success screen here. This avoids losing/looping the
                 * session through a separate login_success.php request.
                 */
                $loginSuccess = true;
                $redirectPage =
                    (strtolower(trim($user["role"] ?? "")) === "owner")
                    ? "dashboard.php"
                    : "agent_dashboard.php";

                /*
                 * Force the authenticated session to be written before
                 * the browser follows the dashboard redirect.
                 */
                session_write_close();

            }


            /*
            =============================================
            WRONG PASSWORD
            =============================================
            */

            else {

                $message =
                    "Invalid username or password.";

                $messageType =
                    "error";

                generateCaptcha();
            }

        }


        /*
        =================================================
        USER NOT FOUND
        =================================================
        */

        else {

            $message =
                "Invalid username or password.";

            $messageType =
                "error";

            generateCaptcha();
        }


        $stmt->close();
    }
}

?>

<?php if ($loginSuccess): ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>REPXA - Login Successful</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
</head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center p-5">

<div class="w-full max-w-md bg-white border border-slate-200 rounded-3xl p-8 shadow-sm text-center">
    <div class="w-16 h-16 mx-auto rounded-full bg-green-100 flex items-center justify-center">
        <span class="material-symbols-outlined text-green-600 text-4xl">check_circle</span>
    </div>

    <h1 class="text-2xl font-extrabold text-slate-900 mt-5">
        Login Successful
    </h1>

    <p class="text-sm text-slate-500 mt-2">
        Welcome back, <?= htmlspecialchars($_SESSION["user_name"] ?? "User") ?>.
    </p>

    <p class="text-xs text-slate-400 mt-4">
        Redirecting to your dashboard...
    </p>
</div>

<script>
setTimeout(function () {
    window.location.replace(
        <?= json_encode($redirectPage) ?>
    );
}, 1200);
</script>

</body>
</html>
<?php exit; endif; ?>


<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0">

<title>REPXA - Login</title>


<script
src="https://cdn.tailwindcss.com">
</script>


<link
href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
rel="stylesheet">


<link
href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"
rel="stylesheet">


<style>

body {

    font-family:
        Inter,
        sans-serif;

}


.login-option {

    transition:
        .2s ease;

}


.login-option:hover {

    transform:
        translateY(-2px);

}


.login-option.active {

    border-color:
        #2563eb;

    background:
        #eff6ff;

}

</style>

</head>


<body
class="min-h-screen bg-slate-50
text-slate-900 flex items-center
justify-center p-5">


<div
class="w-full max-w-md">


<!-- =====================================================
     BRAND
===================================================== -->

<div
class="text-center mb-7">


<div
class="w-16 h-16 mx-auto
rounded-2xl bg-blue-700
flex items-center justify-center
shadow-lg">


<span
class="material-symbols-outlined
text-white text-4xl">

build

</span>

</div>


<h1
class="text-3xl font-extrabold
text-blue-700 mt-4">

REPXA

</h1>


<p
class="text-sm text-slate-500 mt-1">

Repair Management System

</p>


</div>



<!-- =====================================================
     LOGIN CARD
===================================================== -->

<div
class="bg-white border
border-slate-200
rounded-3xl p-6 shadow-sm">


<div class="mb-6">


<h2
class="text-xl font-bold">

Welcome Back

</h2>


<p
class="text-sm text-slate-500 mt-1">

Login to continue to your dashboard.

</p>


</div>



<!-- =====================================================
     MESSAGE
===================================================== -->

<?php if (
    $message !== ""
): ?>


<div
class="mb-5 p-3 rounded-xl
text-sm bg-red-50
text-red-700
border border-red-200
flex items-center gap-2">


<span
class="material-symbols-outlined
text-lg">

error

</span>


<?= htmlspecialchars(
    $message
) ?>


</div>


<?php endif; ?>



<form
method="POST"
autocomplete="off">


<!-- =====================================================
     LOGIN TYPE
===================================================== -->

<label
class="block text-sm
font-semibold mb-3">

Login As

</label>


<div
class="grid grid-cols-2
gap-3 mb-3">


<!-- OWNER -->

<label
class="login-option
border border-slate-200
rounded-2xl p-4 cursor-pointer">


<input
type="radio"
name="role"
value="owner"
class="hidden login-role"
required>


<div
class="text-center">


<div
class="w-11 h-11 mx-auto
rounded-xl bg-blue-50
flex items-center justify-center">


<span
class="material-symbols-outlined
text-blue-700">

admin_panel_settings

</span>


</div>


<p
class="font-semibold mt-2">

Owner

</p>


<p
class="text-xs text-slate-500 mt-1">

Full access

</p>


</div>


</label>



<!-- OTHER -->

<label
class="login-option
border border-slate-200
rounded-2xl p-4 cursor-pointer">


<input
type="radio"
name="role"
value="other"
class="hidden login-role">


<div
class="text-center">


<div
class="w-11 h-11 mx-auto
rounded-xl bg-slate-50
flex items-center justify-center">


<span
class="material-symbols-outlined
text-slate-700">

badge

</span>


</div>


<p
class="font-semibold mt-2">

Other

</p>


<p
class="text-xs text-slate-500 mt-1">

Limited access

</p>


</div>


</label>


</div>


<p
class="text-xs text-slate-400 mb-5">

Owner has full access. Staff members have limited, role-based access.

</p>



<!-- =====================================================
     USERNAME
===================================================== -->

<div class="mb-4">


<label
class="block text-sm
font-semibold mb-2">

Username / ID

</label>


<div class="relative">


<span
class="material-symbols-outlined
absolute left-4 top-1/2
-translate-y-1/2
text-slate-400">

person

</span>


<input
type="text"
name="username"
required
autocomplete="off"
placeholder="Enter username or Staff ID"
class="w-full border
border-slate-300
rounded-xl pl-12 pr-4 py-3
outline-none
focus:border-blue-600">


</div>

</div>



<!-- =====================================================
     PASSWORD
===================================================== -->

<div class="mb-5">


<div
class="flex items-center
justify-between mb-2">


<label
class="block text-sm
font-semibold">

Password

</label>


<a
href="forgot_password.php"
class="text-xs text-blue-700
font-semibold hover:underline">

Forgot Password?

</a>


</div>


<div class="relative">


<span
class="material-symbols-outlined
absolute left-4 top-1/2
-translate-y-1/2
text-slate-400">

lock

</span>


<input
type="password"
name="password"
id="password"
required
autocomplete="off"
placeholder="Enter password"
class="w-full border
border-slate-300
rounded-xl pl-12 pr-12 py-3
outline-none
focus:border-blue-600">


<button
type="button"
onclick="togglePassword()"
class="absolute right-3
top-1/2
-translate-y-1/2
text-slate-400">


<span
id="passwordIcon"
class="material-symbols-outlined">

visibility

</span>


</button>


</div>

</div>



<!-- =====================================================
     CAPTCHA
===================================================== -->

<div class="mb-6">


<div
class="flex items-center
justify-between mb-2">


<label
class="text-sm font-semibold">

Security Check

</label>


<a
href="login.php?refresh_captcha=1"
class="text-xs text-blue-700
font-semibold
flex items-center gap-1">


<span
class="material-symbols-outlined
text-sm">

refresh

</span>

Refresh

</a>


</div>


<div
class="grid grid-cols-2
gap-3">


<div
class="h-12 rounded-xl
bg-slate-100
border border-slate-200
flex items-center
justify-center
font-bold text-lg
tracking-wide">


<?= htmlspecialchars(
    $_SESSION["captcha_question"]
) ?>


</div>


<input
type="number"
name="captcha"
required
autocomplete="off"
placeholder="Answer"
class="w-full border
border-slate-300
rounded-xl px-4 py-3
outline-none
focus:border-blue-600">


</div>


<p
class="text-xs text-slate-400 mt-2">

Solve the simple security question to continue.

</p>


</div>



<!-- =====================================================
     LOGIN BUTTON
===================================================== -->

<button
type="submit"
class="w-full bg-blue-700
hover:bg-blue-800
text-white rounded-xl
py-3.5 font-semibold
flex items-center
justify-center gap-2">


<span
class="material-symbols-outlined">

login

</span>


Login


</button>


</form>


</div>



<p
class="text-center text-xs
text-slate-400 mt-6">

REPXA Repair Management System

</p>


</div>



<script>

/*
=========================================================
ROLE SELECTION
=========================================================
*/

document
.querySelectorAll(".login-role")
.forEach(function(input) {

    input.addEventListener(
        "change",
        function() {

            document
            .querySelectorAll(".login-option")
            .forEach(function(option) {

                option.classList.remove(
                    "active"
                );

            });


            this
            .closest(".login-option")
            .classList.add("active");

        }
    );

});


/*
=========================================================
PASSWORD VISIBILITY
=========================================================
*/

function togglePassword() {

    const password =
        document.getElementById(
            "password"
        );


    const icon =
        document.getElementById(
            "passwordIcon"
        );


    if (
        password.type === "password"
    ) {

        password.type = "text";

        icon.textContent =
            "visibility_off";

    }

    else {

        password.type = "password";

        icon.textContent =
            "visibility";

    }

}


/*
=========================================================
PREVENT DOUBLE SUBMIT
=========================================================
*/

document
.querySelector("form")
.addEventListener(
    "submit",
    function() {

        const button =
            this.querySelector(
                'button[type="submit"]'
            );


        if (button) {

            button.disabled =
                true;

            button.innerHTML =
                '<span class="material-symbols-outlined animate-spin">progress_activity</span> Checking...';

        }

    }
);

</script>


</body>

</html>