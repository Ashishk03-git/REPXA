<?php
/*
=========================================================
REPXA - DATABASE BACKUP
=========================================================

This file creates a complete SQL backup of the REPXA
MySQL database using the existing db.php connection.

Backup location:
REPXA/backup/

Old backups:
Older than 30 days are automatically removed.
=========================================================
*/

require_once __DIR__ . "/db.php";

date_default_timezone_set("Asia/Kolkata");


/* =====================================================
   BACKUP FOLDER
===================================================== */

$backupDir = __DIR__ . DIRECTORY_SEPARATOR . "backup";

if (!is_dir($backupDir)) {

    if (!mkdir($backupDir, 0777, true)) {

        die("ERROR: Could not create backup folder.");

    }
}


/* =====================================================
   GET DATABASE NAME
===================================================== */

$result = $conn->query("SELECT DATABASE() AS db_name");

if (!$result) {

    die(
        "ERROR: Could not detect database. " .
        $conn->error
    );

}

$row = $result->fetch_assoc();

$dbName = $row["db_name"] ?? "";

if ($dbName === "") {

    die(
        "ERROR: No database selected. " .
        "Please check db.php."
    );

}


/* =====================================================
   BACKUP FILE NAME
===================================================== */

$fileName =
    "repxa_backup_" .
    date("Y-m-d_H-i-s") .
    ".sql";

$filePath =
    $backupDir .
    DIRECTORY_SEPARATOR .
    $fileName;


/* =====================================================
   CREATE FILE
===================================================== */

$handle = fopen($filePath, "w");

if (!$handle) {

    die(
        "ERROR: Could not create backup file."
    );

}


/* =====================================================
   BACKUP HEADER
===================================================== */

fwrite(
    $handle,
    "-- =====================================================\n"
);

fwrite(
    $handle,
    "-- REPXA DATABASE BACKUP\n"
);

fwrite(
    $handle,
    "-- Database: " . $dbName . "\n"
);

fwrite(
    $handle,
    "-- Created: " .
    date("d-m-Y H:i:s") .
    " IST\n"
);

fwrite(
    $handle,
    "-- =====================================================\n\n"
);

fwrite(
    $handle,
    "SET FOREIGN_KEY_CHECKS=0;\n\n"
);


/* =====================================================
   GET TABLES
===================================================== */

$tablesResult = $conn->query("SHOW TABLES");

if (!$tablesResult) {

    fclose($handle);

    @unlink($filePath);

    die(
        "ERROR: Could not read database tables. " .
        $conn->error
    );

}


/* =====================================================
   LOOP THROUGH TABLES
===================================================== */

while (
    $tableRow =
    $tablesResult->fetch_array(MYSQLI_NUM)
) {

    $tableName = $tableRow[0];

    $safeTable =
        "`" .
        str_replace(
            "`",
            "``",
            $tableName
        ) .
        "`";


    /* =============================================
       TABLE HEADER
    ============================================= */

    fwrite(
        $handle,
        "\n-- -----------------------------------------------------\n"
    );

    fwrite(
        $handle,
        "-- TABLE: " .
        $tableName .
        "\n"
    );

    fwrite(
        $handle,
        "-- -----------------------------------------------------\n\n"
    );


    /* =============================================
       DROP TABLE
    ============================================= */

    fwrite(
        $handle,
        "DROP TABLE IF EXISTS " .
        $safeTable .
        ";\n\n"
    );


    /* =============================================
       CREATE TABLE
    ============================================= */

    $createResult =
        $conn->query(
            "SHOW CREATE TABLE " .
            $safeTable
        );

    if (!$createResult) {
        continue;
    }


    $createRow =
        $createResult->fetch_assoc();


    $createSQL = "";


    foreach ($createRow as $key => $value) {

        if (
            stripos(
                $key,
                "Create Table"
            ) !== false
        ) {

            $createSQL = $value;

            break;
        }
    }


    if ($createSQL !== "") {

        fwrite(
            $handle,
            $createSQL .
            ";\n\n"
        );

    }


    /* =============================================
       GET TABLE DATA
    ============================================= */

    $dataResult =
        $conn->query(
            "SELECT * FROM " .
            $safeTable
        );


    if (!$dataResult) {
        continue;
    }


    while (
        $dataRow =
        $dataResult->fetch_assoc()
    ) {

        $columns = [];
        $values = [];


        foreach (
            $dataRow as $column => $value
        ) {

            $columns[] =
                "`" .
                str_replace(
                    "`",
                    "``",
                    $column
                ) .
                "`";


            if ($value === null) {

                $values[] = "NULL";

            } else {

                $values[] =
                    "'" .
                    $conn->real_escape_string(
                        $value
                    ) .
                    "'";

            }
        }


        $insertSQL =
            "INSERT INTO " .
            $safeTable .
            " (" .
            implode(
                ", ",
                $columns
            ) .
            ") VALUES (" .
            implode(
                ", ",
                $values
            ) .
            ");\n";


        fwrite(
            $handle,
            $insertSQL
        );
    }


    fwrite(
        $handle,
        "\n"
    );
}


/* =====================================================
   FINISH BACKUP
===================================================== */

fwrite(
    $handle,
    "\nSET FOREIGN_KEY_CHECKS=1;\n"
);

fwrite(
    $handle,
    "\n-- BACKUP COMPLETED\n"
);


fclose($handle);


/* =====================================================
   DELETE BACKUPS OLDER THAN 30 DAYS
===================================================== */

$backupFiles =
    glob(
        $backupDir .
        DIRECTORY_SEPARATOR .
        "repxa_backup_*.sql"
    );


if ($backupFiles !== false) {

    $deleteBefore =
        time() -
        (30 * 24 * 60 * 60);


    foreach (
        $backupFiles as $oldFile
    ) {

        if (
            is_file($oldFile) &&
            filemtime($oldFile) <
            $deleteBefore
        ) {

            @unlink($oldFile);

        }
    }
}


/* =====================================================
   SUCCESS MESSAGE
===================================================== */

echo "\n";
echo "=============================================\n";
echo " REPXA BACKUP CREATED SUCCESSFULLY\n";
echo "=============================================\n";
echo "Database : " . $dbName . "\n";
echo "File     : " . $fileName . "\n";
echo "Location : " . $backupDir . "\n";
echo "=============================================\n";
echo "\n";

?>