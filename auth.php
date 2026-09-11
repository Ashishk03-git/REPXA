<?php

session_start();

/*
=========================================================
REPXA AUTHENTICATION
=========================================================
Protects private REPXA pages and applies role-based
access control.

OWNER
- Full access

STAFF / AGENT / TECHNICIAN / OTHER
- Limited access
=========================================================
*/


/* =====================================================
   LOGIN CHECK
===================================================== */

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["username"]) ||
    !isset($_SESSION["role"])
) {

    header("Location: login.php");
    exit;
}


/* =====================================================
   USER DATA
===================================================== */

$currentUserId =
    intval($_SESSION["user_id"]);

$currentUserName =
    trim($_SESSION["user_name"] ?? "");

$currentUsername =
    trim($_SESSION["username"] ?? "");

$currentUserRole =
    strtolower(
        trim(
            $_SESSION["role"] ?? ""
        )
    );


/* =====================================================
   BASIC SESSION VALIDATION
===================================================== */

if (
    $currentUserId <= 0 ||
    $currentUsername === "" ||
    $currentUserRole === ""
) {

    session_unset();

    session_destroy();

    header("Location: login.php");

    exit;
}


/* =====================================================
   VALID STAFF ROLES
===================================================== */

$staffRoles = [

    "agent",
    "technician",
    "repair technician",
    "manager",
    "receptionist",
    "helper",
    "other"

];


/* =====================================================
   VALIDATE ROLE
===================================================== */

if (
    $currentUserRole !== "owner" &&
    !in_array(
        $currentUserRole,
        $staffRoles,
        true
    )
) {

    session_unset();

    session_destroy();

    header("Location: login.php");

    exit;
}


/* =====================================================
   CURRENT PAGE
===================================================== */

$currentAuthPage =
    basename(
        $_SERVER["PHP_SELF"]
    );


/* =====================================================
   OWNER-ONLY PAGES
===================================================== */

$ownerOnlyPages = [

    "staff.php",
    "recycle_bin.php"

];


/* =====================================================
   STAFF-BLOCKED PAGES
===================================================== */

if (
    $currentUserRole !== "owner" &&
    in_array(
        $currentAuthPage,
        $ownerOnlyPages,
        true
    )
) {

    header(
        "Location: agent_dashboard.php"
    );

    exit;
}


/* =====================================================
   NORMALIZED ROLE
===================================================== */

$currentUserRole =
    strtolower(
        trim(
            $currentUserRole
        )
    );

?>
