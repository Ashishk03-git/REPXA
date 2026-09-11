<?php
require_once "db.php";

/*
=========================================================
REPXA USER SYSTEM SETUP
=========================================================
Creates users table and one default Owner account.

Default login:
Username: ashish_owner
Password: Ashish2026
=========================================================
*/

$createTable = "
CREATE TABLE IF NOT EXISTS users (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('owner','agent') NOT NULL DEFAULT 'agent',
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";

if (!$conn->query($createTable)) {
    die("Unable to create users table: " . $conn->error);
}


/* =====================================================
   CHECK OWNER
===================================================== */

$check = $conn->prepare("
    SELECT id
    FROM users
    WHERE username = ?
    LIMIT 1
");

$username = "ashish_owner";

$check->bind_param("s", $username);
$check->execute();

$result = $check->get_result();


/* =====================================================
   CREATE DEFAULT OWNER
===================================================== */

if ($result->num_rows === 0) {

    $name = "REPXA Owner";

    $password = password_hash(
        "Ashish2026",
        PASSWORD_DEFAULT
    );

    $role = "owner";

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
        $password,
        $role,
        $status
    );

    if ($stmt->execute()) {

        echo "
        <!DOCTYPE html>
        <html>
        <head>
        <title>REPXA Setup</title>
        <style>
        body{
            font-family:Arial,sans-serif;
            background:#f8fafc;
            padding:40px;
            color:#0f172a;
        }
        .box{
            max-width:600px;
            margin:auto;
            background:white;
            padding:30px;
            border-radius:18px;
            border:1px solid #e2e8f0;
        }
        .success{
            color:#15803d;
            font-weight:bold;
        }
        code{
            background:#f1f5f9;
            padding:5px 8px;
            border-radius:6px;
        }
        </style>
        </head>

        <body>

        <div class='box'>

        <h2>REPXA User Setup Complete</h2>

        <p class='success'>
        Owner account created successfully.
        </p>

        <p>
        <strong>Username:</strong>
        <code>ashish_owner</code>
        </p>

        <p>
        <strong>Password:</strong>
        <code>Ashish2026</code>
        </p>

        <p>
        Users table has been created successfully.
        </p>

        </div>

        </body>
        </html>
        ";

    } else {

        die(
            "Unable to create owner account: " .
            $stmt->error
        );
    }

    $stmt->close();

} else {

    echo "
    <!DOCTYPE html>
    <html>
    <head>
    <title>REPXA Setup</title>
    </head>

    <body style='font-family:Arial;padding:40px'>

    <h2>REPXA User System</h2>

    <p>
    Users table already exists and the owner account
    is already present.
    </p>

    </body>
    </html>
    ";
}

$check->close();

?>