<?php
require_once "auth.php";
require_once "db.php";

/* =========================================================
   COMPANY PROFILE TABLE
========================================================= */

$createTable = "
CREATE TABLE IF NOT EXISTS company_profile (
    id INT NOT NULL AUTO_INCREMENT,
    owner_name VARCHAR(100) NOT NULL DEFAULT 'REPXA Owner',
    shop_name VARCHAR(150) NOT NULL DEFAULT 'Your Shop Name',
    phone VARCHAR(30) NOT NULL DEFAULT '',
    email VARCHAR(100) NOT NULL DEFAULT '',
    address VARCHAR(255) NOT NULL DEFAULT '',
    business_hours VARCHAR(100) NOT NULL DEFAULT '10 AM - 9 PM',
    open_time TIME NOT NULL DEFAULT '10:00:00',
    close_time TIME NOT NULL DEFAULT '21:00:00',
    gstin VARCHAR(50) NOT NULL DEFAULT '',
    owner_photo VARCHAR(255) NOT NULL DEFAULT '',
    instagram_url VARCHAR(255) NOT NULL DEFAULT '',
    facebook_url VARCHAR(255) NOT NULL DEFAULT '',
    youtube_url VARCHAR(255) NOT NULL DEFAULT '',
    other_social_name VARCHAR(100) NOT NULL DEFAULT '',
    other_social_url VARCHAR(255) NOT NULL DEFAULT '',
    show_instagram TINYINT(1) NOT NULL DEFAULT 0,
    show_facebook TINYINT(1) NOT NULL DEFAULT 0,
    show_youtube TINYINT(1) NOT NULL DEFAULT 0,
    show_other TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";

$conn->query($createTable);


/* =========================================================
   CHECK OLD TABLE AND ADD MISSING COLUMNS
========================================================= */

$columns = [

    "owner_photo" =>
        "ALTER TABLE company_profile ADD COLUMN owner_photo VARCHAR(255) NOT NULL DEFAULT ''",

    "open_time" =>
        "ALTER TABLE company_profile ADD COLUMN open_time TIME NOT NULL DEFAULT '10:00:00'",

    "close_time" =>
        "ALTER TABLE company_profile ADD COLUMN close_time TIME NOT NULL DEFAULT '21:00:00'",

    "instagram_url" =>
        "ALTER TABLE company_profile ADD COLUMN instagram_url VARCHAR(255) NOT NULL DEFAULT ''",

    "facebook_url" =>
        "ALTER TABLE company_profile ADD COLUMN facebook_url VARCHAR(255) NOT NULL DEFAULT ''",

    "youtube_url" =>
        "ALTER TABLE company_profile ADD COLUMN youtube_url VARCHAR(255) NOT NULL DEFAULT ''",

    "other_social_name" =>
        "ALTER TABLE company_profile ADD COLUMN other_social_name VARCHAR(100) NOT NULL DEFAULT ''",

    "other_social_url" =>
        "ALTER TABLE company_profile ADD COLUMN other_social_url VARCHAR(255) NOT NULL DEFAULT ''",

    "show_instagram" =>
        "ALTER TABLE company_profile ADD COLUMN show_instagram TINYINT(1) NOT NULL DEFAULT 0",

    "show_facebook" =>
        "ALTER TABLE company_profile ADD COLUMN show_facebook TINYINT(1) NOT NULL DEFAULT 0",

    "show_youtube" =>
        "ALTER TABLE company_profile ADD COLUMN show_youtube TINYINT(1) NOT NULL DEFAULT 0",

    "show_other" =>
        "ALTER TABLE company_profile ADD COLUMN show_other TINYINT(1) NOT NULL DEFAULT 0"
];

foreach ($columns as $column => $sql) {

    $check = $conn->query("
        SHOW COLUMNS FROM company_profile
        LIKE '$column'
    ");

    if ($check && $check->num_rows === 0) {
        $conn->query($sql);
    }
}


/* =========================================================
   MESSAGE
========================================================= */

$message = "";


/* =========================================================
   GET CURRENT PROFILE
========================================================= */

$profile = [

    "owner_name" => "REPXA Owner",
    "shop_name" => "Your Shop Name",
    "phone" => "",
    "email" => "",
    "address" => "",
    "business_hours" => "10 AM - 9 PM",
    "open_time" => "10:00:00",
    "close_time" => "21:00:00",
    "gstin" => "",
    "owner_photo" => "",

    "instagram_url" => "",
    "facebook_url" => "",
    "youtube_url" => "",

    "other_social_name" => "",
    "other_social_url" => "",

    "show_instagram" => 0,
    "show_facebook" => 0,
    "show_youtube" => 0,
    "show_other" => 0
];


$result = $conn->query("
    SELECT *
    FROM company_profile
    ORDER BY id ASC
    LIMIT 1
");

if ($result && $result->num_rows > 0) {
    $profile = $result->fetch_assoc();
}


/* =========================================================
   SAVE PROFILE
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $ownerName = trim($_POST["owner_name"] ?? "");
    $shopName = trim($_POST["shop_name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $businessHours = trim($_POST["business_hours"] ?? "");
    $openTime = trim($_POST["open_time"] ?? "10:00");
    $closeTime = trim($_POST["close_time"] ?? "21:00");
    $gstin = trim($_POST["gstin"] ?? "");

    $instagramUrl = trim($_POST["instagram_url"] ?? "");
    $facebookUrl = trim($_POST["facebook_url"] ?? "");
    $youtubeUrl = trim($_POST["youtube_url"] ?? "");

    $otherSocialName = trim($_POST["other_social_name"] ?? "");
    $otherSocialUrl = trim($_POST["other_social_url"] ?? "");

    $showInstagram =
        isset($_POST["show_instagram"]) &&
        $_POST["show_instagram"] === "1"
        ? 1 : 0;

    $showFacebook =
        isset($_POST["show_facebook"]) &&
        $_POST["show_facebook"] === "1"
        ? 1 : 0;

    $showYoutube =
        isset($_POST["show_youtube"]) &&
        $_POST["show_youtube"] === "1"
        ? 1 : 0;

    $showOther =
        isset($_POST["show_other"]) &&
        $_POST["show_other"] === "1"
        ? 1 : 0;


    if ($ownerName === "" || $shopName === "" || $phone === "") {

        $message =
            "Owner name, shop name and phone are required.";

    } else {

        /* ================================================
           PHOTO
        ================================================ */

        $photoPath =
            $profile["owner_photo"] ?? "";

        $croppedPhoto =
            $_POST["cropped_photo"] ?? "";


        /* CROPPED PHOTO */

        if ($croppedPhoto !== "") {

            if (
                preg_match(
                    '/^data:image\/(jpeg|jpg|png);base64,/',
                    $croppedPhoto,
                    $matches
                )
            ) {

                $imageData = substr(
                    $croppedPhoto,
                    strpos($croppedPhoto, ",") + 1
                );

                $imageData =
                    base64_decode($imageData);

                if ($imageData !== false) {

                    $uploadDir =
                        __DIR__ . "/uploads";

                    if (!is_dir($uploadDir)) {
                        mkdir(
                            $uploadDir,
                            0777,
                            true
                        );
                    }

                    $fileName =
                        "owner_" .
                        time() .
                        "_" .
                        rand(1000, 9999) .
                        ".jpg";

                    $filePath =
                        $uploadDir .
                        "/" .
                        $fileName;

                    if (
                        file_put_contents(
                            $filePath,
                            $imageData
                        )
                    ) {

                        $photoPath =
                            "uploads/" .
                            $fileName;
                    }
                }
            }
        }


        /* DIRECT FILE UPLOAD */

        if (
            $croppedPhoto === "" &&
            isset($_FILES["owner_photo"]) &&
            $_FILES["owner_photo"]["error"] ===
            UPLOAD_ERR_OK
        ) {

            $file =
                $_FILES["owner_photo"];

            $extension =
                strtolower(
                    pathinfo(
                        $file["name"],
                        PATHINFO_EXTENSION
                    )
                );

            $allowedExtensions = [
                "jpg",
                "jpeg",
                "png"
            ];

            if (
                in_array(
                    $extension,
                    $allowedExtensions
                )
            ) {

                $uploadDir =
                    __DIR__ . "/uploads";

                if (!is_dir($uploadDir)) {
                    mkdir(
                        $uploadDir,
                        0777,
                        true
                    );
                }

                $fileName =
                    "owner_" .
                    time() .
                    "_" .
                    rand(1000, 9999) .
                    "." .
                    $extension;

                $destination =
                    $uploadDir .
                    "/" .
                    $fileName;

                if (
                    move_uploaded_file(
                        $file["tmp_name"],
                        $destination
                    )
                ) {

                    $photoPath =
                        "uploads/" .
                        $fileName;
                }
            }
        }


        /* ================================================
           CHECK EXISTING PROFILE
        ================================================ */

        $check = $conn->query("
            SELECT id
            FROM company_profile
            ORDER BY id ASC
            LIMIT 1
        ");


        if (
            $check &&
            $check->num_rows > 0
        ) {

            $row =
                $check->fetch_assoc();

            $profileId =
                intval($row["id"]);


            $stmt = $conn->prepare("
                UPDATE company_profile
                SET
                    owner_name = ?,
                    shop_name = ?,
                    phone = ?,
                    email = ?,
                    address = ?,
                    business_hours = ?,
                    open_time = ?,
                    close_time = ?,
                    gstin = ?,
                    owner_photo = ?,
                    instagram_url = ?,
                    facebook_url = ?,
                    youtube_url = ?,
                    other_social_name = ?,
                    other_social_url = ?,
                    show_instagram = ?,
                    show_facebook = ?,
                    show_youtube = ?,
                    show_other = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");


            $stmt->bind_param(
                "sssssssssssssssiiiii",
                $ownerName,
                $shopName,
                $phone,
                $email,
                $address,
                $businessHours,
                $openTime,
                $closeTime,
                $gstin,
                $photoPath,
                $instagramUrl,
                $facebookUrl,
                $youtubeUrl,
                $otherSocialName,
                $otherSocialUrl,
                $showInstagram,
                $showFacebook,
                $showYoutube,
                $showOther,
                $profileId
            );

        } else {

            $stmt = $conn->prepare("
                INSERT INTO company_profile
                (
                    owner_name,
                    shop_name,
                    phone,
                    email,
                    address,
                    business_hours,
                    open_time,
                    close_time,
                    gstin,
                    owner_photo,
                    instagram_url,
                    facebook_url,
                    youtube_url,
                    other_social_name,
                    other_social_url,
                    show_instagram,
                    show_facebook,
                    show_youtube,
                    show_other
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");


            $stmt->bind_param(
                "sssssssssssssssiiii",
                $ownerName,
                $shopName,
                $phone,
                $email,
                $address,
                $businessHours,
                $openTime,
                $closeTime,
                $gstin,
                $photoPath,
                $instagramUrl,
                $facebookUrl,
                $youtubeUrl,
                $otherSocialName,
                $otherSocialUrl,
                $showInstagram,
                $showFacebook,
                $showYoutube,
                $showOther
            );
        }


        if ($stmt->execute()) {

            header(
                "Location: profile.php?edit=1&saved=1"
            );

            exit;

        } else {

            $message =
                "Unable to save profile: " .
                $stmt->error;
        }

        $stmt->close();
    }
}


/* =========================================================
   SAVED MESSAGE
========================================================= */

if (isset($_GET["saved"])) {

    $message =
        "Profile updated successfully!";
}


/* =========================================================
   GET PROFILE AGAIN AFTER SAVE
========================================================= */

$result = $conn->query("
    SELECT *
    FROM company_profile
    ORDER BY id ASC
    LIMIT 1
");

if (
    $result &&
    $result->num_rows > 0
) {

    $profile =
        $result->fetch_assoc();
}


/* =========================================================
   EDIT MODE
========================================================= */

$editMode =
    isset($_GET["edit"]) &&
    $_GET["edit"] === "1";


/* =========================================================
   SOCIAL VISIBILITY
========================================================= */

$instagramVisible =
    !empty($profile["instagram_url"]) &&
    intval($profile["show_instagram"]) === 1;

$facebookVisible =
    !empty($profile["facebook_url"]) &&
    intval($profile["show_facebook"]) === 1;

$youtubeVisible =
    !empty($profile["youtube_url"]) &&
    intval($profile["show_youtube"]) === 1;

$otherVisible =
    !empty($profile["other_social_url"]) &&
    intval($profile["show_other"]) === 1;

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0">

<title>REPXA - Profile</title>

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

.social-icon {
    transition: .2s;
}

.social-icon:hover {
    transform: translateY(-3px);
}


/* CROP */

#cropModal {
    display: none;
}

#cropCanvas {
    max-width: 100%;
    background: #111827;
    border-radius: 14px;
    cursor: grab;
}

#cropCanvas:active {
    cursor: grabbing;
}

</style>

</head>


<body class="bg-slate-50 text-slate-900">

<!-- HEADER -->

<header class="bg-white border-b sticky top-0 z-50">

<div class="max-w-5xl mx-auto px-5 h-16 flex items-center justify-between">

<div class="flex items-center gap-3">

<a
    href="dashboard.php"
    class="w-10 h-10 rounded-full flex items-center justify-center hover:bg-slate-100">

<span class="material-symbols-outlined">
arrow_back
</span>

</a>

<div>

<h1 class="font-bold text-lg text-blue-700">
REPXA
</h1>

<p class="text-xs text-slate-500">
Business Profile
</p>

</div>

</div>


<?php if (!$editMode): ?>

<!-- RIGHT SIDE MENU -->

<button
    type="button"
    onclick="toggleProfileMenu()"
    class="w-10 h-10 rounded-full flex items-center justify-center hover:bg-slate-100">

<span class="material-symbols-outlined">
menu
</span>

</button>

<?php endif; ?>

</div>

</header>


<!-- RIGHT SIDE MENU -->

<?php if (!$editMode): ?>

<div
    id="profileMenu"
    class="hidden fixed top-20 right-5 w-64 bg-white border border-slate-200 rounded-2xl shadow-xl z-[60] overflow-hidden">

<a
    href="dashboard.php"
    class="flex items-center gap-3 px-5 py-4 hover:bg-slate-50 border-b">

<span class="material-symbols-outlined text-blue-700">
dashboard
</span>

<span class="font-medium">
Dashboard
</span>

</a>


<a
    href="repair.php"
    class="flex items-center gap-3 px-5 py-4 hover:bg-slate-50 border-b">

<span class="material-symbols-outlined text-blue-700">
build
</span>

<span class="font-medium">
Repairs
</span>

</a>


<a
    href="status.php"
    class="flex items-center gap-3 px-5 py-4 hover:bg-slate-50 border-b">

<span class="material-symbols-outlined text-blue-700">
track_changes
</span>

<span class="font-medium">
Repair Status
</span>

</a>


<a
    href="inventory.php"
    class="flex items-center gap-3 px-5 py-4 hover:bg-slate-50 border-b">

<span class="material-symbols-outlined text-blue-700">
inventory_2
</span>

<span class="font-medium">
Inventory
</span>

</a>


<a
    href="customers.php"
    class="flex items-center gap-3 px-5 py-4 hover:bg-slate-50 border-b">

<span class="material-symbols-outlined text-blue-700">
groups
</span>

<span class="font-medium">
Customers
</span>

</a>


<a
    href="billing.php"
    class="flex items-center gap-3 px-5 py-4 hover:bg-slate-50 border-b">

<span class="material-symbols-outlined text-blue-700">
receipt_long
</span>

<span class="font-medium">
Billing
</span>

</a>


<a
    href="profile.php"
    class="flex items-center gap-3 px-5 py-4 hover:bg-slate-50">

<span class="material-symbols-outlined text-blue-700">
person
</span>

<span class="font-medium">
Profile
</span>

</a>

</div>

<?php endif; ?>


<main class="max-w-5xl mx-auto px-5 py-7">


<?php if ($message !== ""): ?>

<div
    class="mb-6 p-4 rounded-xl bg-green-100 text-green-700 border border-green-200">

<?= htmlspecialchars($message) ?>

</div>

<?php endif; ?>


<?php if ($editMode): ?>


<!-- =====================================================
     EDIT PROFILE
===================================================== -->

<div class="mb-6">

<p class="text-xs font-semibold tracking-wider text-slate-500">
BUSINESS SETTINGS
</p>

<h2 class="text-2xl font-bold mt-1">
Edit Profile
</h2>

<p class="text-sm text-slate-500 mt-1">
Manage owner, business and social media information.
</p>

</div>


<form
    method="POST"
    enctype="multipart/form-data"
    class="space-y-6"
    id="profileForm"
    autocomplete="off">


<!-- OWNER PHOTO -->

<div class="bg-white border border-slate-200 rounded-2xl p-6">

<h3 class="font-bold text-lg mb-5">
Owner Profile Photo
</h3>


<div class="flex items-center gap-5">


<div id="ownerPreview">

<?php if (
    !empty($profile["owner_photo"]) &&
    file_exists(
        __DIR__ . "/" .
        $profile["owner_photo"]
    )
): ?>

<img
    src="<?= htmlspecialchars($profile["owner_photo"]) ?>"
    class="w-24 h-24 rounded-2xl object-cover border-4 border-blue-100">

<?php else: ?>

<div
    class="w-24 h-24 rounded-2xl bg-blue-50 flex items-center justify-center">

<span class="material-symbols-outlined text-blue-700 text-5xl">
person
</span>

</div>

<?php endif; ?>

</div>


<div>

<label class="block text-sm font-semibold mb-2">
Choose Owner Photo
</label>

<input
    type="file"
    id="ownerPhoto"
    name="owner_photo"
    accept=".jpg,.jpeg,.png,image/jpeg,image/png"
    class="w-full text-sm">


<p class="text-xs text-slate-400 mt-2">
Only JPG, JPEG and PNG allowed.
</p>

</div>

</div>

</div>


<!-- HIDDEN CROPPED PHOTO -->

<input
    type="hidden"
    name="cropped_photo"
    id="croppedPhoto">


<!-- BUSINESS DETAILS -->

<div class="bg-white border border-slate-200 rounded-2xl p-6">

<h3 class="font-bold text-lg mb-5">
Business Information
</h3>


<div class="space-y-5">


<div>

<label class="block text-sm font-semibold mb-2">
Owner Name *
</label>

<input
    type="text"
    name="owner_name"
    value="<?= htmlspecialchars($profile["owner_name"]) ?>"
    required
    class="w-full border border-slate-300 rounded-xl px-4 py-3 outline-none focus:border-blue-600">

</div>


<div>

<label class="block text-sm font-semibold mb-2">
Shop / Company Name *
</label>

<input
    type="text"
    name="shop_name"
    value="<?= htmlspecialchars($profile["shop_name"]) ?>"
    required
    class="w-full border border-slate-300 rounded-xl px-4 py-3 outline-none focus:border-blue-600">

</div>


<div>

<label class="block text-sm font-semibold mb-2">
Contact Number *
</label>

<input
    type="text"
    name="phone"
    value="<?= htmlspecialchars($profile["phone"]) ?>"
    required
    class="w-full border border-slate-300 rounded-xl px-4 py-3 outline-none focus:border-blue-600">

</div>


<div>

<label class="block text-sm font-semibold mb-2">
Email
</label>

<input
    type="email"
    name="email"
    value="<?= htmlspecialchars($profile["email"]) ?>"
    class="w-full border border-slate-300 rounded-xl px-4 py-3 outline-none focus:border-blue-600">

</div>


<div>

<label class="block text-sm font-semibold mb-2">
Business Address
</label>

<textarea
    name="address"
    rows="3"
    class="w-full border border-slate-300 rounded-xl px-4 py-3 outline-none focus:border-blue-600"><?= htmlspecialchars($profile["address"]) ?></textarea>

</div>


<div>

<label class="block text-sm font-semibold mb-2">
Business Hours
</label>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

<div>

<label class="block text-sm font-semibold mb-2">
Opening Time
</label>

<input
    type="time"
    name="open_time"
    value="<?= htmlspecialchars(
        substr(
            $profile["open_time"] ?? "10:00",
            0,
            5
        )
    ) ?>"
    class="w-full border border-slate-300 rounded-xl px-4 py-3 outline-none focus:border-blue-600">

</div>


<div>

<label class="block text-sm font-semibold mb-2">
Closing Time
</label>

<input
    type="time"
    name="close_time"
    value="<?= htmlspecialchars(
        substr(
            $profile["close_time"] ?? "21:00",
            0,
            5
        )
    ) ?>"
    class="w-full border border-slate-300 rounded-xl px-4 py-3 outline-none focus:border-blue-600">

</div>

</div>


<input
    type="hidden"
    name="business_hours"
    value="<?= htmlspecialchars(
        $profile["business_hours"] ??
        "10 AM - 9 PM"
    ) ?>">

<p class="text-xs text-slate-400 mt-2">
Profile automatically shows Open or Closed according to these timings.
</p>

</div>


<div>

<label class="block text-sm font-semibold mb-2">
GSTIN
</label>

<input
    type="text"
    name="gstin"
    value="<?= htmlspecialchars($profile["gstin"]) ?>"
    class="w-full border border-slate-300 rounded-xl px-4 py-3 outline-none focus:border-blue-600">

</div>

</div>

</div>


<!-- =====================================================
     SOCIAL MEDIA
===================================================== -->

<div class="bg-white border border-slate-200 rounded-2xl p-6">

<h3 class="font-bold text-lg">
Social Media
</h3>

<p class="text-sm text-slate-500 mt-1 mb-6">
Add your links and select which icons should appear on your profile.
</p>


<!-- INSTAGRAM -->

<div class="border border-slate-200 rounded-xl p-4 mb-4">

<div class="flex items-center gap-3 mb-3">

<div class="w-10 h-10 rounded-xl bg-pink-50 flex items-center justify-center">

<svg
    width="22"
    height="22"
    viewBox="0 0 24 24"
    fill="none"
    stroke="#E1306C"
    stroke-width="2">

<rect x="3" y="3" width="18" height="18" rx="5"/>
<circle cx="12" cy="12" r="4"/>
<circle cx="17.5" cy="6.5" r="1"/>

</svg>

</div>

<div>

<p class="font-semibold">
Instagram
</p>

<p class="text-xs text-slate-400">
Instagram profile link
</p>

</div>

</div>


<input
    type="url"
    name="instagram_url"
    value="<?= htmlspecialchars($profile["instagram_url"]) ?>"
    placeholder="https://instagram.com/yourpage"
    class="w-full border border-slate-300 rounded-xl px-4 py-3 mb-3">


<label class="flex items-center gap-3">

<input
    type="hidden"
    name="show_instagram"
    value="0">

<input
    type="checkbox"
    name="show_instagram"
    value="1"
    <?= intval(
        $profile["show_instagram"] ?? 0
    ) === 1 ? "checked" : "" ?>
    class="w-4 h-4">

<span class="text-sm font-medium">
Show Instagram on profile
</span>

</label>

</div>


<!-- FACEBOOK -->

<div class="border border-slate-200 rounded-xl p-4 mb-4">

<div class="flex items-center gap-3 mb-3">

<div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">

<svg
    width="22"
    height="22"
    viewBox="0 0 24 24"
    fill="#1877F2">

<path d="M14 8h3V4h-3c-3.3 0-5 1.9-5 5v3H6v4h3v8h4v-8h3.2l.8-4H13V9c0-.7.3-1 1-1z"/>

</svg>

</div>

<div>

<p class="font-semibold">
Facebook
</p>

<p class="text-xs text-slate-400">
Facebook page link
</p>

</div>

</div>


<input
    type="url"
    name="facebook_url"
    value="<?= htmlspecialchars($profile["facebook_url"]) ?>"
    placeholder="https://facebook.com/yourpage"
    class="w-full border border-slate-300 rounded-xl px-4 py-3 mb-3">


<label class="flex items-center gap-3">

<input
    type="hidden"
    name="show_facebook"
    value="0">

<input
    type="checkbox"
    name="show_facebook"
    value="1"
    <?= intval(
        $profile["show_facebook"] ?? 0
    ) === 1 ? "checked" : "" ?>
    class="w-4 h-4">

<span class="text-sm font-medium">
Show Facebook on profile
</span>

</label>

</div>


<!-- YOUTUBE -->

<div class="border border-slate-200 rounded-xl p-4 mb-4">

<div class="flex items-center gap-3 mb-3">

<div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center">

<svg
    width="25"
    height="25"
    viewBox="0 0 24 24"
    fill="#FF0000">

<path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.6 12 3.6 12 3.6s-7.5 0-9.4.5A3 3 0 0 0 .5 6.2 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.1A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.8zM9.6 15.6V8.4l6.3 3.6-6.3 3.6z"/>

</svg>

</div>

<div>

<p class="font-semibold">
YouTube
</p>

<p class="text-xs text-slate-400">
YouTube channel link
</p>

</div>

</div>


<input
    type="url"
    name="youtube_url"
    value="<?= htmlspecialchars($profile["youtube_url"]) ?>"
    placeholder="https://youtube.com/@yourchannel"
    class="w-full border border-slate-300 rounded-xl px-4 py-3 mb-3">


<label class="flex items-center gap-3">

<input
    type="hidden"
    name="show_youtube"
    value="0">

<input
    type="checkbox"
    name="show_youtube"
    value="1"
    <?= intval(
        $profile["show_youtube"] ?? 0
    ) === 1 ? "checked" : "" ?>
    class="w-4 h-4">

<span class="text-sm font-medium">
Show YouTube on profile
</span>

</label>

</div>


<!-- OTHER -->

<div class="border border-slate-200 rounded-xl p-4">

<div class="flex items-center gap-3 mb-3">

<div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center">

<span class="material-symbols-outlined text-slate-700">
person_add
</span>

</div>

<div>

<p class="font-semibold">
Other Social Media
</p>

<p class="text-xs text-slate-400">
Add another platform
</p>

</div>

</div>


<input
    type="text"
    name="other_social_name"
    value="<?= htmlspecialchars($profile["other_social_name"]) ?>"
    placeholder="Platform name e.g. LinkedIn, X, Telegram"
    class="w-full border border-slate-300 rounded-xl px-4 py-3 mb-3">


<input
    type="url"
    name="other_social_url"
    value="<?= htmlspecialchars($profile["other_social_url"]) ?>"
    placeholder="https://example.com/yourpage"
    class="w-full border border-slate-300 rounded-xl px-4 py-3 mb-3">


<label class="flex items-center gap-3">

<input
    type="hidden"
    name="show_other"
    value="0">

<input
    type="checkbox"
    name="show_other"
    value="1"
    <?= intval(
        $profile["show_other"] ?? 0
    ) === 1 ? "checked" : "" ?>
    class="w-4 h-4">

<span class="text-sm font-medium">
Show Other Social Media on profile
</span>

</label>

</div>

</div>


<!-- SAVE -->

<div class="flex gap-3">

<a
    href="profile.php"
    class="flex-1 text-center border border-slate-300 rounded-xl py-3.5 font-semibold">

Cancel

</a>


<button
    type="submit"
    class="flex-1 bg-blue-700 hover:bg-blue-800 text-white rounded-xl py-3.5 font-semibold flex items-center justify-center gap-2">

<span class="material-symbols-outlined">
save
</span>

Save Profile

</button>

</div>


</form>


<?php else: ?>


<!-- =====================================================
     UPGRADED OWNER CARD
===================================================== -->

<div class="bg-white border border-slate-200 rounded-2xl overflow-hidden mb-8 shadow-sm">


    <!-- BLUE PROFILE BANNER -->

    <div class="h-28 sm:h-32 bg-gradient-to-r from-blue-600 to-indigo-700">
    </div>


    <!-- OWNER CONTENT -->

    <div class="px-5 sm:px-7 pb-6">

        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-5">


            <!-- LEFT SIDE -->

            <div class="flex items-end gap-4 -mt-12">


                <!-- PROFILE PHOTO -->

                <div class="shrink-0">

                    <?php if (
                        !empty($profile["owner_photo"]) &&
                        file_exists(
                            __DIR__ . "/" .
                            $profile["owner_photo"]
                        )
                    ): ?>

                        <img
                            src="<?= htmlspecialchars($profile["owner_photo"]) ?>"
                            alt="Owner Photo"
                            class="w-24 h-24 sm:w-28 sm:h-28 rounded-2xl object-cover border-4 border-white shadow-md bg-white">

                    <?php else: ?>

                        <div
                            class="w-24 h-24 sm:w-28 sm:h-28 rounded-2xl bg-blue-50 border-4 border-white shadow-md flex items-center justify-center">

                            <span class="material-symbols-outlined text-blue-700 text-5xl">
                                person
                            </span>

                        </div>

                    <?php endif; ?>

                </div>


                <!-- OWNER INFORMATION -->

                <div class="pb-1 min-w-0">

                    <p class="text-xs font-bold tracking-[0.18em] text-white/90 uppercase mb-1">
                    Business Owner
                    </p>

                    <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 leading-tight">
                        <?= htmlspecialchars($profile["owner_name"]) ?>
                    </h2>

                    <p class="text-sm sm:text-base font-medium text-slate-500 mt-1">
                        <?= htmlspecialchars($profile["shop_name"]) ?>
                    </p>

                </div>

            </div>


            <!-- EDIT PROFILE -->

            <div class="sm:pb-1">

                <a
                    href="profile.php?edit=1"
                    class="inline-flex items-center justify-center gap-2 bg-blue-700 hover:bg-blue-800 text-white px-5 py-3 rounded-xl font-semibold text-sm shadow-sm transition">

                    <span class="material-symbols-outlined text-lg">
                        edit
                    </span>

                    Edit Profile

                </a>

            </div>

        </div>


        <!-- QUICK INFORMATION -->

        <div class="flex flex-wrap gap-3 mt-6 pt-5 border-t border-slate-100">


            <?php

            date_default_timezone_set("Asia/Kolkata");

            $currentMinutes =
                ((int)date("H") * 60) +
                (int)date("i");

            $openParts =
                explode(
                    ":",
                    $profile["open_time"] ?? "10:00"
                );

            $closeParts =
                explode(
                    ":",
                    $profile["close_time"] ?? "21:00"
                );

            $openMinutes =
                ((int)($openParts[0] ?? 10) * 60) +
                (int)($openParts[1] ?? 0);

            $closeMinutes =
                ((int)($closeParts[0] ?? 21) * 60) +
                (int)($closeParts[1] ?? 0);

            $isOpen =
                ($openMinutes < $closeMinutes)
                ?
                (
                    $currentMinutes >= $openMinutes &&
                    $currentMinutes < $closeMinutes
                )
                :
                (
                    $currentMinutes >= $openMinutes ||
                    $currentMinutes < $closeMinutes
                );

            $hoursBadge =
                $isOpen
                ? "bg-green-50 text-green-700 border-green-200"
                : "bg-red-50 text-red-700 border-red-200";

            $hoursText =
                $isOpen
                ? "Open Now"
                : "Closed";

            ?>


            <!-- OPEN STATUS -->

            <span
                class="<?= $hoursBadge ?> border px-4 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2">

                <span class="w-2 h-2 rounded-full bg-current"></span>

                <?= $hoursText ?>

            </span>


            <!-- BUSINESS HOURS -->

            <span
                class="bg-slate-50 border border-slate-200 text-slate-700 px-4 py-2.5 rounded-xl text-sm font-medium flex items-center gap-2">

                <span class="material-symbols-outlined text-base">
                    schedule
                </span>

                <?= date(
                    "g:i A",
                    strtotime(
                        $profile["open_time"] ??
                        "10:00"
                    )
                ) ?>

                –

                <?= date(
                    "g:i A",
                    strtotime(
                        $profile["close_time"] ??
                        "21:00"
                    )
                ) ?>

            </span>


            <!-- PHONE -->

            <?php if (!empty($profile["phone"])): ?>

            <span
                class="bg-slate-50 border border-slate-200 text-slate-700 px-4 py-2.5 rounded-xl text-sm font-medium flex items-center gap-2">

                <span class="material-symbols-outlined text-base">
                    call
                </span>

                <?= htmlspecialchars(
                    $profile["phone"]
                ) ?>

            </span>

            <?php endif; ?>


        </div>

    </div>

</div>


<!-- =====================================================
     BUSINESS INFORMATION
===================================================== -->

<div class="mb-4">

<p class="text-xs font-semibold tracking-wider text-slate-500">
BUSINESS INFORMATION
</p>

<h2 class="text-xl font-bold mt-1">
Business Details
</h2>

</div>


<div class="grid md:grid-cols-2 gap-3">


<!-- SHOP -->

<div class="bg-white border rounded-2xl p-5 flex items-center gap-4">

<div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center">

<span class="material-symbols-outlined text-blue-700">
store
</span>

</div>

<div>

<p class="text-xs font-semibold text-slate-500">
SHOP / COMPANY
</p>

<p class="font-bold mt-1">
<?= htmlspecialchars(
    $profile["shop_name"]
) ?>
</p>

</div>

</div>


<!-- CONTACT -->

<div class="bg-white border rounded-2xl p-5 flex items-center gap-4">

<div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center">

<span class="material-symbols-outlined text-green-700">
call
</span>

</div>

<div>

<p class="text-xs font-semibold text-slate-500">
CONTACT NUMBER
</p>

<p class="font-bold mt-1">
<?= htmlspecialchars(
    $profile["phone"] ?: "Not added"
) ?>
</p>

</div>

</div>


<!-- EMAIL -->

<div class="bg-white border rounded-2xl p-5 flex items-center gap-4">

<div class="w-12 h-12 rounded-xl bg-purple-50 flex items-center justify-center">

<span class="material-symbols-outlined text-purple-700">
mail
</span>

</div>

<div>

<p class="text-xs font-semibold text-slate-500">
EMAIL
</p>

<p class="font-bold mt-1 break-all">
<?= htmlspecialchars(
    $profile["email"] ?: "Not added"
) ?>
</p>

</div>

</div>


<!-- ADDRESS -->

<div class="bg-white border rounded-2xl p-5 flex items-center gap-4">

<div class="w-12 h-12 rounded-xl bg-orange-50 flex items-center justify-center">

<span class="material-symbols-outlined text-orange-600">
location_on
</span>

</div>

<div>

<p class="text-xs font-semibold text-slate-500">
BUSINESS ADDRESS
</p>

<p class="font-bold mt-1">
<?= htmlspecialchars(
    $profile["address"] ?: "Not added"
) ?>
</p>

</div>

</div>


<!-- HOURS -->

<div class="bg-white border rounded-2xl p-5 flex items-center gap-4">

<div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center">

<span class="material-symbols-outlined text-blue-700">
schedule
</span>

</div>

<div class="flex-1">

<p class="text-xs font-semibold text-slate-500">
BUSINESS HOURS
</p>

<p class="font-bold mt-1">

<?= date(
    "g:i A",
    strtotime(
        $profile["open_time"] ?? "10:00"
    )
) ?>

–

<?= date(
    "g:i A",
    strtotime(
        $profile["close_time"] ?? "21:00"
    )
) ?>

</p>

</div>

<span
    class="<?= $hoursBadge ?> px-3 py-1.5 rounded-full text-xs font-semibold">

<?= $hoursText ?>

</span>

</div>


<!-- GST -->

<div class="bg-white border rounded-2xl p-5 flex items-center gap-4">

<div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center">

<span class="material-symbols-outlined text-blue-700">
receipt_long
</span>

</div>

<div>

<p class="text-xs font-semibold text-slate-500">
GSTIN
</p>

<p class="font-bold mt-1">
<?= htmlspecialchars(
    $profile["gstin"] ?: "Not added"
) ?>
</p>

</div>

</div>

</div>


<!-- =====================================================
     SOCIAL MEDIA
===================================================== -->

<?php if (
    $instagramVisible ||
    $facebookVisible ||
    $youtubeVisible ||
    $otherVisible
): ?>

<div class="mt-8">

<p class="text-xs font-semibold tracking-wider text-slate-500">
CONNECT WITH US
</p>

<h2 class="text-xl font-bold mt-1 mb-4">
Social Media
</h2>


<div class="flex flex-wrap gap-4">


<!-- INSTAGRAM -->

<?php if ($instagramVisible): ?>

<a
    href="<?= htmlspecialchars(
        $profile["instagram_url"]
    ) ?>"
    target="_blank"
    rel="noopener noreferrer"
    title="Instagram"
    class="social-icon w-14 h-14 rounded-2xl bg-pink-50 flex items-center justify-center">

<svg
    width="26"
    height="26"
    viewBox="0 0 24 24"
    fill="none"
    stroke="#E1306C"
    stroke-width="2">

<rect x="3" y="3" width="18" height="18" rx="5"/>
<circle cx="12" cy="12" r="4"/>
<circle cx="17.5" cy="6.5" r="1"/>

</svg>

</a>

<?php endif; ?>


<!-- FACEBOOK -->

<?php if ($facebookVisible): ?>

<a
    href="<?= htmlspecialchars(
        $profile["facebook_url"]
    ) ?>"
    target="_blank"
    rel="noopener noreferrer"
    title="Facebook"
    class="social-icon w-14 h-14 rounded-2xl bg-blue-50 flex items-center justify-center">

<svg
    width="26"
    height="26"
    viewBox="0 0 24 24"
    fill="#1877F2">

<path d="M14 8h3V4h-3c-3.3 0-5 1.9-5 5v3H6v4h3v8h4v-8h3.2l.8-4H13V9c0-.7.3-1 1-1z"/>

</svg>

</a>

<?php endif; ?>


<!-- YOUTUBE -->

<?php if ($youtubeVisible): ?>

<a
    href="<?= htmlspecialchars(
        $profile["youtube_url"]
    ) ?>"
    target="_blank"
    rel="noopener noreferrer"
    title="YouTube"
    class="social-icon w-14 h-14 rounded-2xl bg-red-50 flex items-center justify-center">

<svg
    width="29"
    height="29"
    viewBox="0 0 24 24"
    fill="#FF0000">

<path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.6 12 3.6 12 3.6s-7.5 0-9.4.5A3 3 0 0 0 .5 6.2 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.1A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.8zM9.6 15.6V8.4l6.3 3.6-6.3 3.6z"/>

</svg>

</a>

<?php endif; ?>


<!-- OTHER -->

<?php if ($otherVisible): ?>

<a
    href="<?= htmlspecialchars(
        $profile["other_social_url"]
    ) ?>"
    target="_blank"
    rel="noopener noreferrer"
    title="<?= htmlspecialchars(
        $profile["other_social_name"]
    ) ?>"
    class="social-icon w-14 h-14 rounded-2xl bg-slate-100 flex items-center justify-center">

<span class="material-symbols-outlined text-slate-700 text-3xl">
person_add
</span>

</a>

<?php endif; ?>


</div>

</div>

<?php endif; ?>


<!-- =====================================================
     MORE OPTIONS
===================================================== -->

<div class="mt-8 mb-4">

<p class="text-xs font-semibold tracking-wider text-slate-500">
MORE OPTIONS
</p>

<h2 class="text-xl font-bold mt-1">
Quick Actions
</h2>

</div>


<div class="bg-white border rounded-2xl overflow-hidden">


<!-- STAFF -->

<a
    href="staff.php"
    class="flex items-center gap-4 p-5 hover:bg-slate-50 border-b">

<div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center">

<span class="material-symbols-outlined text-blue-700">
groups
</span>

</div>

<div class="flex-1">

<p class="font-semibold">
Staff Details
</p>

<p class="text-xs text-slate-500 mt-1">
Manage staff and technicians
</p>

</div>

<span class="material-symbols-outlined text-slate-400">
chevron_right
</span>

</a>


<!-- RECEIPTS -->

<a
    href="billing.php"
    class="flex items-center gap-4 p-5 hover:bg-slate-50 border-b">

<div class="w-11 h-11 rounded-xl bg-green-50 flex items-center justify-center">

<span class="material-symbols-outlined text-green-700">
receipt_long
</span>

</div>

<div class="flex-1">

<p class="font-semibold">
Receipts
</p>

<p class="text-xs text-slate-500 mt-1">
View and download bills
</p>

</div>

<span class="material-symbols-outlined text-slate-400">
chevron_right
</span>

</a>


<!-- RECYCLE BIN -->

<a
    href="recycle_bin.php"
    class="flex items-center gap-4 p-5 hover:bg-slate-50 border-b">

<div class="w-11 h-11 rounded-xl bg-orange-50 flex items-center justify-center">

<span class="material-symbols-outlined text-orange-600">
delete
</span>

</div>

<div class="flex-1">

<p class="font-semibold">
Recycle Bin
</p>

<p class="text-xs text-slate-500 mt-1">
View removed records
</p>

</div>

<span class="material-symbols-outlined text-slate-400">
chevron_right
</span>

</a>


<!-- HELP -->

<a
    href="help.php"
    class="flex items-center gap-4 p-5 hover:bg-slate-50">

<div class="w-11 h-11 rounded-xl bg-purple-50 flex items-center justify-center">

<span class="material-symbols-outlined text-purple-700">
help
</span>

</div>

<div class="flex-1">

<p class="font-semibold">
Help & Support
</p>

<p class="text-xs text-slate-500 mt-1">
Get help with REPXA
</p>

</div>

<span class="material-symbols-outlined text-slate-400">
chevron_right
</span>

</a>


</div>


<!-- LOGOUT -->

<div class="mt-7">

<a
    href="logout.php"
    class="w-full border border-red-200 text-red-600 hover:bg-red-50 rounded-xl py-3.5 font-semibold flex items-center justify-center gap-2">

<span class="material-symbols-outlined">
logout
</span>

Logout

</a>

</div>


<p class="text-center text-xs text-slate-400 mt-7">
REPXA v2.4.0 • Repair Management System
</p>


<?php endif; ?>


</main>



<!-- =====================================================
     CROP MODAL
===================================================== -->

<div
    id="cropModal"
    class="fixed inset-0 bg-black/70 z-[100] items-center justify-center p-5">

<div class="bg-white rounded-2xl p-5 max-w-md w-full">

<div class="flex items-center justify-between mb-4">

<div>

<h3 class="font-bold text-lg">
Crop Owner Photo
</h3>

<p class="text-xs text-slate-500">
Drag photo and adjust zoom.
</p>

</div>

<button
    type="button"
    id="closeCrop"
    class="w-9 h-9 rounded-full hover:bg-slate-100">

<span class="material-symbols-outlined">
close
</span>

</button>

</div>


<canvas
    id="cropCanvas"
    width="320"
    height="320"
    class="w-full">
</canvas>


<div class="mt-4">

<label class="text-sm font-semibold">
Zoom
</label>

<input
    type="range"
    id="zoomRange"
    min="1"
    max="3"
    step="0.01"
    value="1"
    class="w-full mt-2">

</div>


<div class="flex gap-3 mt-5">

<button
    type="button"
    id="cancelCrop"
    class="flex-1 border border-slate-300 rounded-xl py-3 font-semibold">

Cancel

</button>


<button
    type="button"
    id="applyCrop"
    class="flex-1 bg-blue-700 text-white rounded-xl py-3 font-semibold">

Apply Crop

</button>

</div>

</div>

</div>


<script>

/* =========================================================
   PROFILE MENU
========================================================= */

function toggleProfileMenu() {

    const menu =
        document.getElementById(
            "profileMenu"
        );

    if (!menu) {
        return;
    }

    menu.classList.toggle("hidden");
}


/* Close menu when clicking outside */

document.addEventListener(
    "click",
    function (event) {

        const menu =
            document.getElementById(
                "profileMenu"
            );

        const button =
            event.target.closest(
                'button[onclick="toggleProfileMenu()"]'
            );

        if (
            menu &&
            !menu.contains(event.target) &&
            !button
        ) {

            menu.classList.add(
                "hidden"
            );
        }
    }
);


/* =========================================================
   PHOTO CROP
========================================================= */

const photoInput =
    document.getElementById(
        "ownerPhoto"
    );

const cropModal =
    document.getElementById(
        "cropModal"
    );

const canvas =
    document.getElementById(
        "cropCanvas"
    );

const ctx =
    canvas
    ? canvas.getContext("2d")
    : null;

const zoomRange =
    document.getElementById(
        "zoomRange"
    );

const croppedPhoto =
    document.getElementById(
        "croppedPhoto"
    );

const ownerPreview =
    document.getElementById(
        "ownerPreview"
    );

const applyCrop =
    document.getElementById(
        "applyCrop"
    );

const cancelCrop =
    document.getElementById(
        "cancelCrop"
    );

const closeCrop =
    document.getElementById(
        "closeCrop"
    );


let image =
    new Image();

let scale = 1;

let offsetX = 0;

let offsetY = 0;

let dragging = false;

let startX = 0;

let startY = 0;


/* =========================================================
   FILE SELECT
========================================================= */

if (photoInput) {

    photoInput.addEventListener(
        "change",
        function () {

            const file =
                this.files[0];

            if (!file) {
                return;
            }


            const allowed = [
                "image/jpeg",
                "image/png"
            ];


            if (!allowed.includes(
                file.type
            )) {

                alert(
                    "Only JPG, JPEG and PNG images are allowed."
                );

                this.value = "";

                return;
            }


            const reader =
                new FileReader();


            reader.onload =
                function (e) {

                    image.onload =
                        function () {

                            scale = 1;

                            offsetX = 0;

                            offsetY = 0;

                            zoomRange.value = 1;

                            drawCrop();

                            cropModal.style.display =
                                "flex";
                        };


                    image.src =
                        e.target.result;
                };


            reader.readAsDataURL(file);
        }
    );

}


/* =========================================================
   DRAW
========================================================= */

function drawCrop() {

    if (!canvas || !ctx) {
        return;
    }

    ctx.clearRect(
        0,
        0,
        canvas.width,
        canvas.height
    );


    const baseScale =
        Math.max(
            canvas.width / image.width,
            canvas.height / image.height
        );


    const finalScale =
        baseScale * scale;


    const width =
        image.width * finalScale;

    const height =
        image.height * finalScale;


    const x =
        (canvas.width - width) / 2 +
        offsetX;

    const y =
        (canvas.height - height) / 2 +
        offsetY;


    ctx.drawImage(
        image,
        x,
        y,
        width,
        height
    );


    ctx.strokeStyle =
        "#ffffff";

    ctx.lineWidth =
        3;

    ctx.strokeRect(
        2,
        2,
        canvas.width - 4,
        canvas.height - 4
    );
}


/* =========================================================
   ZOOM
========================================================= */

if (zoomRange) {

    zoomRange.addEventListener(
        "input",
        function () {

            scale =
                parseFloat(
                    this.value
                );

            drawCrop();
        }
    );

}


/* =========================================================
   DRAG
========================================================= */

if (canvas) {

    canvas.addEventListener(
        "mousedown",
        function (e) {

            dragging = true;

            startX =
                e.clientX - offsetX;

            startY =
                e.clientY - offsetY;
        }
    );


    window.addEventListener(
        "mousemove",
        function (e) {

            if (!dragging) {
                return;
            }

            offsetX =
                e.clientX - startX;

            offsetY =
                e.clientY - startY;

            drawCrop();
        }
    );


    window.addEventListener(
        "mouseup",
        function () {

            dragging = false;
        }
    );


    /* TOUCH */

    canvas.addEventListener(
        "touchstart",
        function (e) {

            const touch =
                e.touches[0];

            dragging = true;

            startX =
                touch.clientX - offsetX;

            startY =
                touch.clientY - offsetY;
        }
    );


    canvas.addEventListener(
        "touchmove",
        function (e) {

            if (!dragging) {
                return;
            }

            e.preventDefault();

            const touch =
                e.touches[0];

            offsetX =
                touch.clientX - startX;

            offsetY =
                touch.clientY - startY;

            drawCrop();
        },
        {
            passive: false
        }
    );


    canvas.addEventListener(
        "touchend",
        function () {

            dragging = false;
        }
    );

}


/* =========================================================
   APPLY CROP
========================================================= */

if (applyCrop) {

    applyCrop.addEventListener(
        "click",
        function () {

            const cropped =
                canvas.toDataURL(
                    "image/jpeg",
                    0.90
                );


            croppedPhoto.value =
                cropped;


            ownerPreview.innerHTML = `

                <img
                    src="${cropped}"
                    class="w-24 h-24 rounded-2xl object-cover border-4 border-blue-100">

            `;


            cropModal.style.display =
                "none";
        }
    );

}


/* =========================================================
   CANCEL CROP
========================================================= */

function closeCropModal() {

    if (cropModal) {
        cropModal.style.display =
            "none";
    }

    if (photoInput) {
        photoInput.value =
            "";
    }
}


if (cancelCrop) {

    cancelCrop.addEventListener(
        "click",
        closeCropModal
    );

}


if (closeCrop) {

    closeCrop.addEventListener(
        "click",
        closeCropModal
    );

}

</script>


</body>

</html>