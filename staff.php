<?php

require_once "auth.php";
require_once "db.php";

/*
=========================================================
OWNER ONLY
=========================================================
*/

if (strtolower($_SESSION["role"] ?? "") !== "owner") {
    header("Location: dashboard.php");
    exit;
}


/*
=========================================================
HELPER
=========================================================
*/

function columnExists($conn, $table, $column)
{
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);

    $result = $conn->query("
        SHOW COLUMNS FROM `$table`
        LIKE '$column'
    ");

    return $result && $result->num_rows > 0;
}


/*
=========================================================
ENSURE STAFF TABLE
=========================================================
*/

$conn->query("
    CREATE TABLE IF NOT EXISTS staff (

        staff_id INT NOT NULL AUTO_INCREMENT,

        agent_id VARCHAR(100) NULL,

        user_id INT NULL,

        name VARCHAR(100) NOT NULL,

        phone VARCHAR(30) NOT NULL DEFAULT '',

        email VARCHAR(150) NOT NULL DEFAULT '',

        role VARCHAR(100) NOT NULL DEFAULT 'Technician',

        specialization VARCHAR(150) NOT NULL DEFAULT '',

        status ENUM('Active','Inactive')
        NOT NULL DEFAULT 'Active',

        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (staff_id)

    ) ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4
");


/*
=========================================================
ADD MISSING COLUMNS
=========================================================
*/

if (!columnExists($conn, "staff", "agent_id")) {

    $conn->query("
        ALTER TABLE staff
        ADD COLUMN agent_id VARCHAR(100) NULL
        AFTER staff_id
    ");
}


if (!columnExists($conn, "staff", "user_id")) {

    $conn->query("
        ALTER TABLE staff
        ADD COLUMN user_id INT NULL
        AFTER agent_id
    ");
}


if (!columnExists($conn, "staff", "email")) {

    $conn->query("
        ALTER TABLE staff
        ADD COLUMN email VARCHAR(150)
        NOT NULL DEFAULT ''
        AFTER phone
    ");
}


/*
=========================================================
PERMANENT ID REGISTRY
=========================================================
*/

$conn->query("
    CREATE TABLE IF NOT EXISTS agent_id_registry (

        id INT NOT NULL AUTO_INCREMENT,

        agent_id VARCHAR(100) NOT NULL,

        company_prefix VARCHAR(50) NOT NULL DEFAULT '',

        sequence_no INT NOT NULL DEFAULT 0,

        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

        PRIMARY KEY (id),

        UNIQUE KEY unique_agent_id (agent_id)

    ) ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4
");


/*
=========================================================
COMPANY NAME
=========================================================
*/

$shopName = "REPXA";

$profileResult = $conn->query("
    SELECT shop_name
    FROM company_profile
    ORDER BY id ASC
    LIMIT 1
");

if (
    $profileResult &&
    $profileResult->num_rows > 0
) {

    $profileRow =
        $profileResult->fetch_assoc();

    if (
        !empty($profileRow["shop_name"])
    ) {

        $shopName =
            trim($profileRow["shop_name"]);
    }
}


/*
=========================================================
COMPANY INITIALS
=========================================================
*/

function getCompanyInitials($name)
{
    $words =
        preg_split(
            '/\s+/',
            trim($name)
        );

    $initials = "";

    foreach ($words as $word) {

        $word =
            preg_replace(
                '/[^A-Za-z0-9]/',
                '',
                $word
            );

        if ($word !== "") {

            $initials .=
                strtoupper(
                    substr($word, 0, 1)
                );
        }
    }

    return $initials !== ""
        ? $initials
        : "REPXA";
}


$companyPrefix =
    getCompanyInitials(
        $shopName
    );


/*
=========================================================
ROLE CODE
=========================================================
*/

function getRoleCode($role)
{
    $role =
        strtolower(
            trim($role)
        );

    $roleMap = [

        "agent" =>
            "A",

        "technician" =>
            "T",

        "repair technician" =>
            "RT",

        "manager" =>
            "M",

        "receptionist" =>
            "R",

        "helper" =>
            "H",

        "other" =>
            "O"
    ];

    return $roleMap[$role] ?? "O";
}


/*
=========================================================
NAME INITIALS
=========================================================
*/

function getNameInitials($name)
{
    $words =
        preg_split(
            '/\s+/',
            trim($name)
        );

    $initials = "";

    foreach ($words as $word) {

        $word =
            preg_replace(
                '/[^A-Za-z0-9]/',
                '',
                $word
            );

        if ($word !== "") {

            $initials .=
                strtoupper(
                    substr($word, 0, 1)
                );
        }
    }

    return $initials !== ""
        ? $initials
        : "X";
}


/*
=========================================================
AUTO PASSWORD
=========================================================

First name + @ + last 4 digits
=========================================================
*/

function generateDefaultPassword(
    $name,
    $phone
) {

    $nameParts =
        preg_split(
            '/\s+/',
            trim($name)
        );

    $firstName =
        $nameParts[0] ?? "User";

    $firstName =
        preg_replace(
            '/[^A-Za-z0-9]/',
            '',
            $firstName
        );

    $digits =
        preg_replace(
            '/\D/',
            '',
            $phone
        );

    if (strlen($digits) >= 4) {

        $lastFour =
            substr(
                $digits,
                -4
            );

    } else {

        $lastFour =
            str_pad(
                $digits,
                4,
                "0",
                STR_PAD_LEFT
            );
    }

    return $firstName .
        "@" .
        $lastFour;
}


/*
=========================================================
GENERATE PERMANENT EMPLOYEE ID
=========================================================
*/

function generateEmployeeId(
    $conn,
    $companyPrefix,
    $name,
    $role
) {

    $roleCode =
        getRoleCode($role);

    $nameInitials =
        getNameInitials($name);


    $stmt =
        $conn->prepare("
            SELECT MAX(sequence_no)
            AS max_sequence

            FROM agent_id_registry

            WHERE company_prefix = ?
        ");

    $stmt->bind_param(
        "s",
        $companyPrefix
    );

    $stmt->execute();

    $row =
        $stmt
        ->get_result()
        ->fetch_assoc();

    $stmt->close();


    $nextNumber =
        intval(
            $row["max_sequence"] ?? 0
        ) + 1;


    while (true) {

        $employeeId =
            $companyPrefix .
            "_" .
            $roleCode .
            "_" .
            $nameInitials .
            "_" .
            $nextNumber;


        $check =
            $conn->prepare("
                SELECT id
                FROM agent_id_registry
                WHERE agent_id = ?
                LIMIT 1
            ");

        $check->bind_param(
            "s",
            $employeeId
        );

        $check->execute();

        $exists =
            $check
            ->get_result()
            ->num_rows > 0;

        $check->close();


        if (!$exists) {
            break;
        }

        $nextNumber++;
    }


    return [
        "agent_id" =>
            $employeeId,

        "sequence_no" =>
            $nextNumber
    ];
}


/*
=========================================================
MESSAGES
=========================================================
*/

$message = "";
$messageType = "";
$generatedPassword = "";
$generatedUsername = "";


/*
=========================================================
EDIT
=========================================================
*/

$editId =
    intval(
        $_GET["edit"] ?? 0
    );

$editStaff = null;


if ($editId > 0) {

    $stmt =
        $conn->prepare("
            SELECT *
            FROM staff
            WHERE staff_id = ?
            LIMIT 1
        ");

    $stmt->bind_param(
        "i",
        $editId
    );

    $stmt->execute();

    $result =
        $stmt->get_result();

    if (
        $result &&
        $result->num_rows > 0
    ) {

        $editStaff =
            $result->fetch_assoc();
    }

    $stmt->close();
}


/*
=========================================================
POST
=========================================================
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
) {

    $action =
        $_POST["action"] ?? "";


    /*
    =====================================================
    SYNC EXISTING STAFF LOGIN ACCOUNTS
    =====================================================
    Creates missing users accounts and links them to staff.
    Existing passwords are NOT changed.
    */

    if ($action === "sync_login_accounts") {

        $createdCount = 0;
        $linkedCount = 0;
        $failedCount = 0;

        $syncResult = $conn->query("
            SELECT
                staff_id,
                agent_id,
                user_id,
                name,
                phone,
                role,
                status
            FROM staff
            ORDER BY staff_id ASC
        ");

        if ($syncResult) {

            while ($syncStaff = $syncResult->fetch_assoc()) {

                $staffIdSync = intval($syncStaff["staff_id"]);
                $employeeIdSync = trim($syncStaff["agent_id"] ?? "");
                $nameSync = trim($syncStaff["name"] ?? "");
                $phoneSync = trim($syncStaff["phone"] ?? "");
                $roleSync = strtolower(trim($syncStaff["role"] ?? "other"));
                $statusSync = $syncStaff["status"] ?? "Active";
                $userIdSync = intval($syncStaff["user_id"] ?? 0);

                if ($employeeIdSync === "" || $nameSync === "") {
                    $failedCount++;
                    continue;
                }

                try {

                    /*
                    -----------------------------------------
                    FIND EXISTING USER BY LINKED user_id
                    -----------------------------------------
                    */

                    $existingUserId = 0;

                    if ($userIdSync > 0) {

                        $verify = $conn->prepare("
                            SELECT id
                            FROM users
                            WHERE id = ?
                            LIMIT 1
                        ");

                        if ($verify) {

                            $verify->bind_param(
                                "i",
                                $userIdSync
                            );

                            $verify->execute();

                            $verifyRow =
                                $verify
                                ->get_result()
                                ->fetch_assoc();

                            $verify->close();

                            if ($verifyRow) {
                                $existingUserId =
                                    intval($verifyRow["id"]);
                            }
                        }
                    }


                    /*
                    -----------------------------------------
                    FIND USER BY EMPLOYEE ID
                    -----------------------------------------
                    */

                    if ($existingUserId <= 0) {

                        $findUser = $conn->prepare("
                            SELECT id
                            FROM users
                            WHERE username = ?
                            LIMIT 1
                        ");

                        if (!$findUser) {
                            throw new Exception(
                                "Unable to check existing login."
                            );
                        }

                        $findUser->bind_param(
                            "s",
                            $employeeIdSync
                        );

                        $findUser->execute();

                        $foundUser =
                            $findUser
                            ->get_result()
                            ->fetch_assoc();

                        $findUser->close();

                        if ($foundUser) {
                            $existingUserId =
                                intval($foundUser["id"]);
                        }
                    }


                    /*
                    -----------------------------------------
                    CREATE MISSING USER
                    -----------------------------------------
                    */

                    if ($existingUserId <= 0) {

                        $defaultPasswordSync =
                            generateDefaultPassword(
                                $nameSync,
                                $phoneSync
                            );

                        $hashedPasswordSync =
                            password_hash(
                                $defaultPasswordSync,
                                PASSWORD_DEFAULT
                            );

                        $createUser = $conn->prepare("
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

                        if (!$createUser) {
                            throw new Exception(
                                "Unable to prepare login account."
                            );
                        }

                        $createUser->bind_param(
                            "sssss",
                            $nameSync,
                            $employeeIdSync,
                            $hashedPasswordSync,
                            $roleSync,
                            $statusSync
                        );

                        if (!$createUser->execute()) {
                            throw new Exception(
                                "Unable to create login account: " .
                                $createUser->error
                            );
                        }

                        $existingUserId =
                            intval($conn->insert_id);

                        $createUser->close();

                        $createdCount++;
                    }


                    /*
                    -----------------------------------------
                    KEEP LOGIN DATA IN SYNC
                    -----------------------------------------
                    Password is intentionally NOT changed.
                    -----------------------------------------
                    */

                    $updateUser = $conn->prepare("
                        UPDATE users
                        SET
                            name = ?,
                            username = ?,
                            role = ?,
                            status = ?
                        WHERE id = ?
                    ");

                    if (!$updateUser) {
                        throw new Exception(
                            "Unable to sync login account."
                        );
                    }

                    $updateUser->bind_param(
                        "ssssi",
                        $nameSync,
                        $employeeIdSync,
                        $roleSync,
                        $statusSync,
                        $existingUserId
                    );

                    if (!$updateUser->execute()) {
                        throw new Exception(
                            "Unable to update login account."
                        );
                    }

                    $updateUser->close();


                    /*
                    -----------------------------------------
                    LINK STAFF -> USERS
                    -----------------------------------------
                    */

                    $linkStaff = $conn->prepare("
                        UPDATE staff
                        SET user_id = ?
                        WHERE staff_id = ?
                    ");

                    if (!$linkStaff) {
                        throw new Exception(
                            "Unable to link staff account."
                        );
                    }

                    $linkStaff->bind_param(
                        "ii",
                        $existingUserId,
                        $staffIdSync
                    );

                    if (!$linkStaff->execute()) {
                        throw new Exception(
                            "Unable to link staff login."
                        );
                    }

                    $linkStaff->close();

                    $linkedCount++;

                } catch (Throwable $syncError) {

                    $failedCount++;
                }
            }

            $message =
                "Login accounts synced successfully. " .
                $createdCount .
                " missing account(s) created and " .
                $linkedCount .
                " staff record(s) linked.";

            if ($failedCount > 0) {
                $message .=
                    " " .
                    $failedCount .
                    " record(s) could not be synced.";
            }

            $messageType =
                $failedCount > 0
                    ? "error"
                    : "success";

        } else {

            $message =
                "Unable to read staff records.";

            $messageType =
                "error";
        }
    }


    /*
    =====================================================
    ADD
    =====================================================
    */

    if ($action === "add") {

        $name =
            trim(
                $_POST["name"] ?? ""
            );

        $phone =
            trim(
                $_POST["phone"] ?? ""
            );

        $email =
            trim(
                $_POST["email"] ?? ""
            );

        $role =
            trim(
                $_POST["role"] ?? "Technician"
            );

        $specialization =
            trim(
                $_POST["specialization"] ?? ""
            );

        $status =
            $_POST["status"] ?? "Active";


        /*
        ---------------------------------------------
        VALIDATION
        ---------------------------------------------
        */

        if ($name === "") {

            $message =
                "Name is required.";

            $messageType =
                "error";

        } elseif ($phone === "") {

            $message =
                "Mobile number is required.";

            $messageType =
                "error";

        } elseif ($email === "") {

            $message =
                "Email is required.";

            $messageType =
                "error";

        } elseif (
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $message =
                "Please enter a valid email containing @.";

            $messageType =
                "error";

        } elseif (
            !in_array(
                $status,
                ["Active", "Inactive"],
                true
            )
        ) {

            $message =
                "Invalid status.";

            $messageType =
                "error";

        } else {


            /*
            -----------------------------------------
            AUTO EMPLOYEE ID
            -----------------------------------------
            */

            $employeeData =
                generateEmployeeId(
                    $conn,
                    $companyPrefix,
                    $name,
                    $role
                );


            $employeeId =
                $employeeData["agent_id"];


            $sequenceNo =
                $employeeData["sequence_no"];


            /*
            -----------------------------------------
            AUTO PASSWORD
            -----------------------------------------
            */

            $defaultPassword =
                generateDefaultPassword(
                    $name,
                    $phone
                );


            /*
            -----------------------------------------
            HASH PASSWORD
            -----------------------------------------
            */

            $hashedPassword =
                password_hash(
                    $defaultPassword,
                    PASSWORD_DEFAULT
                );


            $conn->begin_transaction();


            try {

                /*
                =====================================
                CREATE LOGIN ACCOUNT
                =====================================
                */

                $loginRole =
                    strtolower(
                        $role
                    );


                /*
                 * users table
                 *
                 * Username = Employee ID
                 * Password = hashed default password
                 */

                $userStmt =
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


                if (!$userStmt) {

                    throw new Exception(
                        "Unable to prepare login account."
                    );
                }


                $userStmt->bind_param(
                    "sssss",
                    $name,
                    $employeeId,
                    $hashedPassword,
                    $loginRole,
                    $status
                );


                if (
                    !$userStmt->execute()
                ) {

                    throw new Exception(
                        "Unable to create login account."
                    );
                }


                $userId =
                    $conn->insert_id;


                $userStmt->close();


                /*
                =====================================
                STAFF RECORD
                =====================================
                */

                $staffStmt =
                    $conn->prepare("
                        INSERT INTO staff
                        (
                            agent_id,
                            user_id,
                            name,
                            phone,
                            email,
                            role,
                            specialization,
                            status
                        )
                        VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?)
                    ");


                if (!$staffStmt) {

                    throw new Exception(
                        "Unable to prepare staff record."
                    );
                }


                $staffStmt->bind_param(
                    "sissssss",
                    $employeeId,
                    $userId,
                    $name,
                    $phone,
                    $email,
                    $role,
                    $specialization,
                    $status
                );


                if (
                    !$staffStmt->execute()
                ) {

                    throw new Exception(
                        "Unable to save staff record."
                    );
                }


                $staffStmt->close();


                /*
                =====================================
                PERMANENT ID REGISTRY
                =====================================
                */

                $registryStmt =
                    $conn->prepare("
                        INSERT INTO agent_id_registry
                        (
                            agent_id,
                            company_prefix,
                            sequence_no
                        )
                        VALUES
                        (?, ?, ?)
                    ");


                if (!$registryStmt) {

                    throw new Exception(
                        "Unable to prepare ID registry."
                    );
                }


                $registryStmt->bind_param(
                    "ssi",
                    $employeeId,
                    $companyPrefix,
                    $sequenceNo
                );


                if (
                    !$registryStmt->execute()
                ) {

                    throw new Exception(
                        "Unable to reserve Employee ID."
                    );
                }


                $registryStmt->close();


                $conn->commit();


                /*
                =====================================
                SUCCESS
                =====================================
                */

                $generatedUsername =
                    $employeeId;

                $generatedPassword =
                    $defaultPassword;


                $message =
                    "Staff account created successfully.";

                $messageType =
                    "success";


            } catch (
                Throwable $e
            ) {

                $conn->rollback();


                $message =
                    "Unable to create account: " .
                    $e->getMessage();

                $messageType =
                    "error";
            }
        }
    }


    /*
    =====================================================
    UPDATE
    =====================================================
    */

    elseif (
        $action === "update"
    ) {

        $staffId =
            intval(
                $_POST["staff_id"] ?? 0
            );


        $name =
            trim(
                $_POST["name"] ?? ""
            );


        $phone =
            trim(
                $_POST["phone"] ?? ""
            );


        $email =
            trim(
                $_POST["email"] ?? ""
            );


        $specialization =
            trim(
                $_POST["specialization"] ?? ""
            );


        $status =
            $_POST["status"] ?? "Active";


        $newPassword =
            $_POST["new_password"] ?? "";


        if (
            $staffId <= 0 ||
            $name === ""
        ) {

            $message =
                "Invalid staff details.";

            $messageType =
                "error";

        } elseif (
            $phone === ""
        ) {

            $message =
                "Mobile number is required.";

            $messageType =
                "error";

        } elseif (
            $email === ""
        ) {

            $message =
                "Email is required.";

            $messageType =
                "error";

        } elseif (
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $message =
                "Please enter a valid email containing @.";

            $messageType =
                "error";

        } else {


            /*
            -----------------------------------------
            UPDATE STAFF
            -----------------------------------------
            */

            $stmt =
                $conn->prepare("
                    UPDATE staff
                    SET
                        name = ?,
                        phone = ?,
                        email = ?,
                        specialization = ?,
                        status = ?
                    WHERE staff_id = ?
                ");


            $stmt->bind_param(
                "sssssi",
                $name,
                $phone,
                $email,
                $specialization,
                $status,
                $staffId
            );


            if (
                $stmt->execute()
            ) {

                $message =
                    "Staff updated successfully.";

                $messageType =
                    "success";


                /*
                -------------------------------------
                FIND USER
                -------------------------------------
                */

                $find =
                    $conn->prepare("
                        SELECT user_id
                        FROM staff
                        WHERE staff_id = ?
                        LIMIT 1
                    ");


                $find->bind_param(
                    "i",
                    $staffId
                );


                $find->execute();


                $staffData =
                    $find
                    ->get_result()
                    ->fetch_assoc();


                $find->close();


                /*
                -------------------------------------
                UPDATE LOGIN
                -------------------------------------
                */

                if (
                    !empty(
                        $staffData["user_id"]
                    )
                ) {

                    $userId =
                        intval(
                            $staffData["user_id"]
                        );


                    $userUpdate =
                        $conn->prepare("
                            UPDATE users
                            SET
                                name = ?,
                                status = ?
                            WHERE id = ?
                        ");


                    $userUpdate->bind_param(
                        "ssi",
                        $name,
                        $status,
                        $userId
                    );


                    $userUpdate->execute();


                    $userUpdate->close();


                    /*
                    ---------------------------------
                    NEW PASSWORD
                    ---------------------------------
                    */

                    if (
                        $newPassword !== ""
                    ) {

                        if (
                            strlen(
                                $newPassword
                            ) < 6
                        ) {

                            $message =
                                "Staff updated, but new password must contain at least 6 characters.";

                        } else {

                            $newHash =
                                password_hash(
                                    $newPassword,
                                    PASSWORD_DEFAULT
                                );


                            $passUpdate =
                                $conn->prepare("
                                    UPDATE users
                                    SET password = ?
                                    WHERE id = ?
                                ");


                            $passUpdate->bind_param(
                                "si",
                                $newHash,
                                $userId
                            );


                            $passUpdate->execute();


                            $passUpdate->close();


                            $message =
                                "Staff and password updated successfully.";
                        }
                    }
                }

            } else {

                $message =
                    "Unable to update staff.";

                $messageType =
                    "error";
            }


            $stmt->close();


            $editId = 0;
        }
    }


    /*
    =====================================================
    DELETE
    =====================================================
    */

    elseif (
        $action === "delete"
    ) {

        $staffId =
            intval(
                $_POST["staff_id"] ?? 0
            );


        if (
            $staffId > 0
        ) {

            /*
            -----------------------------------------
            FIND USER
            -----------------------------------------
            */

            $find =
                $conn->prepare("
                    SELECT user_id
                    FROM staff
                    WHERE staff_id = ?
                    LIMIT 1
                ");


            $find->bind_param(
                "i",
                $staffId
            );


            $find->execute();


            $staffData =
                $find
                ->get_result()
                ->fetch_assoc();


            $find->close();


            /*
            -----------------------------------------
            DISABLE LOGIN
            -----------------------------------------
            */

            if (
                !empty(
                    $staffData["user_id"]
                )
            ) {

                $userId =
                    intval(
                        $staffData["user_id"]
                    );


                $disable =
                    $conn->prepare("
                        UPDATE users
                        SET status = 'Inactive'
                        WHERE id = ?
                    ");


                $disable->bind_param(
                    "i",
                    $userId
                );


                $disable->execute();


                $disable->close();
            }


            /*
            -----------------------------------------
            DELETE STAFF
            -----------------------------------------

            Registry is NOT deleted.

            Therefore ID will NEVER be reused.
            */

            $stmt =
                $conn->prepare("
                    DELETE FROM staff
                    WHERE staff_id = ?
                ");


            $stmt->bind_param(
                "i",
                $staffId
            );


            if (
                $stmt->execute()
            ) {

                $message =
                    "Staff removed successfully. Employee ID remains permanently reserved.";

                $messageType =
                    "success";

            } else {

                $message =
                    "Unable to remove staff.";

                $messageType =
                    "error";
            }


            $stmt->close();
        }
    }
}


/*
=========================================================
SEARCH
=========================================================
*/

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
            FROM staff
            WHERE
                name LIKE ?
                OR agent_id LIKE ?
                OR phone LIKE ?
                OR email LIKE ?
                OR role LIKE ?
                OR specialization LIKE ?
            ORDER BY staff_id DESC
        ");


    $term =
        "%" . $search . "%";


    $stmt->bind_param(
        "ssssss",
        $term,
        $term,
        $term,
        $term,
        $term,
        $term
    );


    $stmt->execute();


    $staffList =
        $stmt->get_result();

} else {

    $staffList =
        $conn->query("
            SELECT *
            FROM staff
            ORDER BY staff_id DESC
        ");
}


/*
=========================================================
COUNTS
=========================================================
*/

$totalStaff = 0;
$activeStaff = 0;
$inactiveStaff = 0;


$countResult =
    $conn->query("
        SELECT
            COUNT(*) AS total,
            SUM(status = 'Active') AS active_count,
            SUM(status = 'Inactive') AS inactive_count
        FROM staff
    ");


if (
    $countResult
) {

    $countRow =
        $countResult->fetch_assoc();


    $totalStaff =
        intval(
            $countRow["total"] ?? 0
        );


    $activeStaff =
        intval(
            $countRow["active_count"] ?? 0
        );


    $inactiveStaff =
        intval(
            $countRow["inactive_count"] ?? 0
        );
}

?>


<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0">

<title>REPXA - Staff</title>

<script src="https://cdn.tailwindcss.com"></script>

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
flex items-center justify-between">


<div
class="flex items-center gap-3">

<a
href="dashboard.php"
class="w-10 h-10 rounded-full
flex items-center justify-center
hover:bg-slate-100">

<span
class="material-symbols-outlined">

arrow_back

</span>

</a>


<div>

<h1
class="font-bold text-lg text-blue-700">

Staff

</h1>

<p
class="text-xs text-slate-500">

Staff & Agent Management

</p>

</div>

</div>


<button
type="button"
onclick="toggleMenu()"
class="w-10 h-10 rounded-xl
hover:bg-slate-100
flex items-center justify-center">

<span class="material-symbols-outlined">
menu
</span>

</button>

</div>

</header>



<!-- SIDE MENU -->

<div
id="sideMenu"
class="hidden fixed inset-0 z-[100]">


<div
onclick="toggleMenu()"
class="absolute inset-0 bg-black/40">
</div>


<aside
class="absolute right-0 top-0 bottom-0
w-[310px] max-w-[88vw]
bg-white shadow-2xl flex flex-col">


<div
class="p-5 border-b">

<div
class="flex items-center justify-between">

<div>

<h2
class="font-bold text-lg text-blue-700">

REPXA

</h2>

<p
class="text-xs text-slate-500">

Repair Management System

</p>

</div>


<button
onclick="toggleMenu()"
class="w-9 h-9 rounded-full
hover:bg-slate-100
flex items-center justify-center">

<span class="material-symbols-outlined">
close
</span>

</button>

</div>

</div>


<div
class="flex-1 overflow-y-auto p-4 space-y-1">


<a href="dashboard.php"
class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-100">

<span class="material-symbols-outlined">
dashboard
</span>

Home

</a>


<a href="repair.php"
class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-100">

<span class="material-symbols-outlined">
build
</span>

Repairs

</a>


<a href="status.php"
class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-100">

<span class="material-symbols-outlined">
track_changes
</span>

Repair Status

</a>


<a href="customers.php"
class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-100">

<span class="material-symbols-outlined">
groups
</span>

Customers

</a>


<a href="inventory.php"
class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-100">

<span class="material-symbols-outlined">
inventory_2
</span>

Inventory

</a>


<a href="billing.php"
class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-100">

<span class="material-symbols-outlined">
receipt_long
</span>

Billing

</a>


<a href="profile.php"
class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-100">

<span class="material-symbols-outlined">
person
</span>

Profile

</a>


<a href="staff.php"
class="flex items-center gap-3 p-3 rounded-xl
bg-blue-50 text-blue-700 font-semibold">

<span class="material-symbols-outlined">
badge
</span>

Staff

</a>


<a href="recycle_bin.php"
class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-100">

<span class="material-symbols-outlined">
delete
</span>

Recycle Bin

</a>


<a href="help.php"
class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-100">

<span class="material-symbols-outlined">
help
</span>

Help & Support

</a>

</div>


<div
class="p-4 border-t">

<a
href="logout.php"
class="w-full border border-red-200
text-red-600 hover:bg-red-50
rounded-xl py-3 flex items-center
justify-center gap-2 font-semibold">

<span class="material-symbols-outlined">
logout
</span>

Logout

</a>

</div>

</aside>

</div>



<!-- MAIN -->

<main
class="max-w-5xl mx-auto px-5 py-7">


<!-- MESSAGE -->

<?php if (
    $message !== ""
): ?>

<div
class="mb-6 p-4 rounded-xl border
<?= $messageType === "success"
    ? "bg-green-100 text-green-700 border-green-200"
    : "bg-red-100 text-red-700 border-red-200"
?>">

<?= htmlspecialchars(
    $message
) ?>

</div>

<?php endif; ?>


<!-- GENERATED LOGIN DETAILS -->

<?php if (
    $generatedUsername !== ""
): ?>

<div
class="mb-6 bg-blue-50 border
border-blue-200 rounded-2xl p-5">


<div
class="flex items-start gap-3">


<div
class="w-11 h-11 rounded-xl bg-white
flex items-center justify-center">

<span
class="material-symbols-outlined
text-blue-700">

verified_user

</span>

</div>


<div class="flex-1">

<h3
class="font-bold text-blue-800">

Login Account Created

</h3>


<p
class="text-sm text-blue-700 mt-1">

Save these login details.

</p>


<div
class="mt-4 grid sm:grid-cols-2 gap-3">


<div
class="bg-white rounded-xl p-3">

<p class="text-xs text-slate-500">
Username / Employee ID
</p>

<p
class="font-bold text-lg mt-1">

<?= htmlspecialchars(
    $generatedUsername
) ?>

</p>

</div>


<div
class="bg-white rounded-xl p-3">

<p class="text-xs text-slate-500">
Default Password
</p>

<p
class="font-bold text-lg mt-1">

<?= htmlspecialchars(
    $generatedPassword
) ?>

</p>

</div>

</div>


<p
class="text-xs text-slate-500 mt-3">

Password is stored securely in encrypted/hash form and is shown here only after creation.

</p>

</div>

</div>

</div>

<?php endif; ?>



<!-- TITLE -->

<div class="mb-6">

<p
class="text-xs font-semibold
tracking-wider text-slate-500">

STAFF MANAGEMENT

</p>

<h2
class="text-2xl font-bold mt-1">

Staff & Agents

</h2>

<p
class="text-sm text-slate-500 mt-1">

Create staff accounts with automatic Employee IDs and login credentials.

</p>

</div>



<!-- SYNC LOGIN ACCOUNTS -->

<div
class="mb-6 bg-amber-50 border border-amber-200
rounded-2xl p-5">

<div
class="flex flex-col sm:flex-row
sm:items-center justify-between gap-4">

<div>

<h3 class="font-bold text-amber-800">
Sync Staff Login Accounts
</h3>

<p class="text-sm text-amber-700 mt-1">
Create missing login accounts for existing staff.
Existing passwords are not changed.
</p>

</div>

<form method="POST">

<input
type="hidden"
name="action"
value="sync_login_accounts">

<button
type="submit"
onclick="return confirm('Sync login accounts for all staff members?');"
class="bg-amber-600 hover:bg-amber-700
text-white px-5 py-3 rounded-xl
font-semibold whitespace-nowrap">

Sync Login Accounts

</button>

</form>

</div>

</div>


<!-- SUMMARY -->

<div
class="grid grid-cols-1 sm:grid-cols-3
gap-4 mb-7">


<div
class="bg-white border rounded-2xl p-5">

<p class="text-sm text-slate-500">
Total Staff
</p>

<p
class="text-3xl font-bold text-blue-700 mt-2">

<?= $totalStaff ?>

</p>

</div>


<div
class="bg-white border rounded-2xl p-5">

<p class="text-sm text-slate-500">
Active
</p>

<p
class="text-3xl font-bold text-green-600 mt-2">

<?= $activeStaff ?>

</p>

</div>


<div
class="bg-white border rounded-2xl p-5">

<p class="text-sm text-slate-500">
Inactive
</p>

<p
class="text-3xl font-bold text-slate-600 mt-2">

<?= $inactiveStaff ?>

</p>

</div>

</div>



<!-- FORM -->

<div
class="bg-white border rounded-2xl p-6 mb-7">


<div class="mb-5">

<h3
class="font-bold text-lg">

<?= $editStaff
    ? "Edit Staff"
    : "Add Staff Member"
?>

</h3>


<p
class="text-xs text-slate-500 mt-1">

<?= $editStaff
    ? "Employee ID cannot be changed."
    : "Every staff member receives a permanent login ID and automatic password."
?>

</p>

</div>


<form
method="POST">


<input
type="hidden"
name="action"
value="<?= $editStaff
    ? "update"
    : "add"
?>">


<?php if (
    $editStaff
): ?>

<input
type="hidden"
name="staff_id"
value="<?= intval(
    $editStaff["staff_id"]
) ?>">

<?php endif; ?>


<div
class="grid md:grid-cols-2 gap-4">


<!-- NAME -->

<div>

<label
class="text-sm font-semibold">

Name *

</label>

<input
type="text"
name="name"
required
value="<?= htmlspecialchars(
    $editStaff["name"] ?? ""
) ?>"
placeholder="e.g. Deepika Sharma"
class="w-full mt-1 border border-slate-300
rounded-xl px-4 py-3 outline-none
focus:border-blue-600">

</div>


<!-- PHONE -->

<div>

<label
class="text-sm font-semibold">

Mobile Number *

</label>

<input
type="tel"
name="phone"
required
value="<?= htmlspecialchars(
    $editStaff["phone"] ?? ""
) ?>"
placeholder="e.g. 9876543210"
class="w-full mt-1 border border-slate-300
rounded-xl px-4 py-3 outline-none
focus:border-blue-600">

<p class="text-xs text-slate-400 mt-1">
Used to generate the default password.
</p>

</div>


<!-- EMAIL -->

<div>

<label
class="text-sm font-semibold">

Email *

</label>

<input
type="email"
name="email"
required
value="<?= htmlspecialchars(
    $editStaff["email"] ?? ""
) ?>"
placeholder="example@gmail.com"
class="w-full mt-1 border border-slate-300
rounded-xl px-4 py-3 outline-none
focus:border-blue-600">

</div>


<!-- ROLE -->

<div>

<label
class="text-sm font-semibold">

Role *

</label>

<select
name="role"
id="role"
required
class="w-full mt-1 border border-slate-300
rounded-xl px-4 py-3 bg-white
outline-none focus:border-blue-600"
<?= $editStaff
    ? "disabled"
    : ""
?>>

<?php

$roles = [

    "Agent",
    "Technician",
    "Repair Technician",
    "Manager",
    "Receptionist",
    "Helper",
    "Other"

];


$currentRole =
    $editStaff["role"]
    ?? "Agent";


foreach (
    $roles as $roleOption
):

?>

<option
value="<?= htmlspecialchars(
    $roleOption
) ?>"
<?= $currentRole ===
    $roleOption
    ? "selected"
    : ""
?>>

<?= htmlspecialchars(
    $roleOption
) ?>

</option>

<?php endforeach; ?>

</select>


<?php if (
    $editStaff
): ?>

<input
type="hidden"
name="role"
value="<?= htmlspecialchars(
    $currentRole
) ?>">

<?php endif; ?>

</div>


<!-- SPECIALIZATION -->

<div>

<label
class="text-sm font-semibold">

Specialization

</label>

<input
type="text"
name="specialization"
value="<?= htmlspecialchars(
    $editStaff["specialization"] ?? ""
) ?>"
placeholder="Mobile, Laptop, Software..."
class="w-full mt-1 border border-slate-300
rounded-xl px-4 py-3 outline-none
focus:border-blue-600">

</div>


<!-- STATUS -->

<div>

<label
class="text-sm font-semibold">

Status *

</label>

<select
name="status"
required
class="w-full mt-1 border border-slate-300
rounded-xl px-4 py-3 bg-white
outline-none focus:border-blue-600">

<option
value="Active"
<?= (
    $editStaff["status"]
    ?? "Active"
) === "Active"
    ? "selected"
    : ""
?>>

Active

</option>

<option
value="Inactive"
<?= (
    $editStaff["status"]
    ?? ""
) === "Inactive"
    ? "selected"
    : ""
?>>

Inactive

</option>

</select>

</div>

</div>



<!-- =====================================================
     PASSWORD ON EDIT
===================================================== -->

<?php if ($editStaff): ?>

<?php

$previewPassword =
    generateDefaultPassword(
        $editStaff["name"] ?? "",
        $editStaff["phone"] ?? ""
    );

?>

<div
class="mt-5 bg-blue-50 border border-blue-200
rounded-xl p-4">

<label
class="text-sm font-semibold text-slate-700">

Password

</label>

<input
type="password"
name="new_password"
id="newPassword"
value=""
autocomplete="new-password"
placeholder="Enter only if you want to change password"
class="w-full mt-1 border border-blue-200
rounded-xl px-4 py-3 bg-white outline-none
focus:border-blue-600">

<p
class="text-xs text-slate-500 mt-2">

Existing password is not displayed for security.
Leave this field blank to keep the current password.

</p>

<p
class="text-xs text-blue-700 mt-1 font-semibold">

Format: First Name + @ + Last 4 Digits

</p>

</div>

<?php endif; ?>



<!-- AUTO PASSWORD PREVIEW -->

<?php if (
    !$editStaff
): ?>

<div
id="passwordPreview"
class="mt-5 bg-blue-50
border border-blue-100
rounded-xl p-4">


<div
class="flex gap-3">


<span
class="material-symbols-outlined
text-blue-700">

lock

</span>


<div>

<p
class="font-semibold text-blue-800">

Automatic Password

</p>


<p
class="text-sm text-blue-700 mt-1">

Password will be:

<strong id="previewPassword">
FirstName@1234
</strong>

</p>


<p
class="text-xs text-slate-500 mt-1">

First name + @ + last 4 digits of mobile number.

</p>

</div>

</div>

</div>

<?php endif; ?>



<!-- BUTTONS -->

<div
class="flex gap-3 mt-5">


<?php if (
    $editStaff
): ?>

<a
href="staff.php"
class="flex-1 border border-slate-300
rounded-xl py-3.5 text-center
font-semibold hover:bg-slate-50">

Cancel

</a>

<?php endif; ?>


<button
type="submit"
class="flex-1 bg-blue-700
hover:bg-blue-800 text-white
rounded-xl py-3.5 font-semibold">

<?= $editStaff
    ? "Update Staff"
    : "Create Staff Account"
?>

</button>

</div>

</form>

</div>



<!-- SEARCH -->

<form
method="GET"
class="mb-5">


<div class="flex gap-3">

<div class="relative flex-1">

<span
class="material-symbols-outlined
absolute left-4 top-1/2
-translate-y-1/2 text-slate-400">

search

</span>

<input
type="text"
name="search"
value="<?= htmlspecialchars(
    $search
) ?>"
placeholder="Search name, Employee ID, phone, role..."
class="w-full bg-white border
border-slate-300 rounded-xl
pl-12 pr-4 py-3 outline-none
focus:border-blue-600">

</div>


<button
type="submit"
class="bg-blue-700 hover:bg-blue-800
text-white px-6 rounded-xl
font-semibold">

Search

</button>


<?php if (
    $search !== ""
): ?>

<a
href="staff.php"
class="border border-slate-300
px-5 rounded-xl flex items-center
justify-center font-semibold">

Clear

</a>

<?php endif; ?>

</div>

</form>



<!-- STAFF DIRECTORY -->

<div class="mb-4">

<p
class="text-xs font-semibold
tracking-wider text-slate-500">

STAFF DIRECTORY

</p>

<h2
class="text-xl font-bold mt-1">

Team Members

</h2>

</div>


<div class="space-y-4">


<?php if (
    $staffList &&
    $staffList->num_rows > 0
): ?>


<?php while (
    $staff =
    $staffList->fetch_assoc()
): ?>


<div
class="bg-white border rounded-2xl p-5">


<div
class="flex flex-col md:flex-row
md:items-center justify-between
gap-5">


<div
class="flex items-center gap-4">


<div
class="w-12 h-12 rounded-xl
<?= !empty($staff["agent_id"])
    ? "bg-green-50"
    : "bg-blue-50"
?>
flex items-center justify-center">


<span
class="material-symbols-outlined
<?= !empty($staff["agent_id"])
    ? "text-green-700"
    : "text-blue-700"
?>">

badge

</span>

</div>


<div>

<h3
class="font-bold text-lg">

<?= htmlspecialchars(
    $staff["name"]
) ?>

</h3>


<?php if (
    !empty(
        $staff["agent_id"]
    )
): ?>

<p
class="text-sm font-bold
text-green-700 mt-0.5">

ID:
<?= htmlspecialchars(
    $staff["agent_id"]
) ?>

</p>

<?php endif; ?>


<p
class="text-sm text-slate-500">

<?= htmlspecialchars(
    $staff["role"]
) ?>


<?php if (
    !empty(
        $staff["specialization"]
    )
): ?>

<span class="mx-1 text-slate-300">
•
</span>

<?= htmlspecialchars(
    $staff["specialization"]
) ?>

<?php endif; ?>

</p>


<?php if (
    !empty(
        $staff["phone"]
    )
): ?>

<p
class="text-xs text-slate-400 mt-1">

<?= htmlspecialchars(
    $staff["phone"]
) ?>

</p>

<?php endif; ?>


<?php if (
    !empty(
        $staff["email"]
    )
): ?>

<p
class="text-xs text-slate-400">

<?= htmlspecialchars(
    $staff["email"]
) ?>

</p>

<?php endif; ?>

</div>

</div>


<div
class="flex items-center gap-3">


<?php if (
    $staff["status"] === "Active"
): ?>

<span
class="px-3 py-1.5 rounded-full
bg-green-100 text-green-700
text-xs font-semibold">

Active

</span>

<?php else: ?>

<span
class="px-3 py-1.5 rounded-full
bg-slate-100 text-slate-600
text-xs font-semibold">

Inactive

</span>

<?php endif; ?>


<a
href="staff.php?edit=<?= intval(
    $staff["staff_id"]
) ?>"
class="w-10 h-10 rounded-xl
bg-blue-50 text-blue-700
hover:bg-blue-100
flex items-center justify-center">

<span class="material-symbols-outlined">
edit
</span>

</a>


<form
method="POST"
onsubmit="return confirm('Are you sure you want to remove this staff member?');">


<input
type="hidden"
name="action"
value="delete">


<input
type="hidden"
name="staff_id"
value="<?= intval(
    $staff["staff_id"]
) ?>">


<button
type="submit"
class="w-10 h-10 rounded-xl
bg-red-50 text-red-600
hover:bg-red-100
flex items-center justify-center">

<span class="material-symbols-outlined">
delete
</span>

</button>

</form>

</div>

</div>

</div>


<?php endwhile; ?>


<?php else: ?>


<div
class="bg-white border rounded-2xl
p-10 text-center">


<span
class="material-symbols-outlined
text-5xl text-slate-300">

groups

</span>


<h3
class="font-bold text-lg mt-4">

No Staff Members

</h3>


<p
class="text-sm text-slate-500 mt-1">

Create your first staff account above.

</p>

</div>

<?php endif; ?>


</div>

</main>



<script>

/*
=========================================================
MENU
=========================================================
*/

function toggleMenu() {

    const menu =
        document.getElementById(
            "sideMenu"
        );

    menu.classList.toggle(
        "hidden"
    );
}


/*
=========================================================
LIVE PASSWORD PREVIEW
=========================================================
*/

const nameInput =
    document.querySelector(
        'input[name="name"]'
    );


const phoneInput =
    document.querySelector(
        'input[name="phone"]'
    );


const preview =
    document.getElementById(
        "previewPassword"
    );


function updatePasswordPreview() {

    if (
        !nameInput ||
        !phoneInput ||
        !preview
    ) {
        return;
    }


    const name =
        nameInput.value.trim();


    const phone =
        phoneInput.value.replace(
            /\D/g,
            ""
        );


    let firstName =
        name.split(/\s+/)[0] ||
        "FirstName";


    firstName =
        firstName.replace(
            /[^A-Za-z0-9]/g,
            ""
        );


    let lastFour =
        phone.slice(-4);


    if (
        lastFour.length < 4
    ) {

        lastFour =
            "1234";
    }


    preview.textContent =
        firstName +
        "@" +
        lastFour;
}


if (nameInput) {

    nameInput.addEventListener(
        "input",
        updatePasswordPreview
    );
}


if (phoneInput) {

    phoneInput.addEventListener(
        "input",
        updatePasswordPreview
    );
}


updatePasswordPreview();


/*
=========================================================
ESC CLOSE
=========================================================
*/

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


</body>

</html>