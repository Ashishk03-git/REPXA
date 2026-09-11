<?php

/* =========================================================
   ROLE / SESSION
========================================================= */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$menuUserRole = strtolower(trim($_SESSION["role"] ?? ""));
$menuIsOwner = ($menuUserRole === "owner");

$menuUserName = $_SESSION["user_name"] ?? "Staff";
$menuUsername = $_SESSION["username"] ?? "";

/*
 * REPXA COMMON HEADER + HAMBURGER MENU
 * Include this file near the top of every page after db.php:
 *
 * require_once "common_menu.php";
 *
 * It reads the owner/shop/photo directly from company_profile,
 * so Profile changes automatically appear everywhere.
 */

$menuProfile = [
    "owner_name"  => "REPXA Owner",
    "shop_name"   => "Your Shop Name",
    "owner_photo" => ""
];

$menuResult = $conn->query("
    SELECT owner_name, shop_name, owner_photo
    FROM company_profile
    ORDER BY id ASC
    LIMIT 1
");

if ($menuResult && $menuResult->num_rows > 0) {
    $menuProfile = array_merge(
        $menuProfile,
        $menuResult->fetch_assoc()
    );
}

$menuPhotoExists = !empty($menuProfile["owner_photo"])
    && file_exists(__DIR__ . "/" . $menuProfile["owner_photo"]);

$currentPage = basename($_SERVER["PHP_SELF"]);
?>

<header class="bg-white border-b sticky top-0 z-50">
    <div class="max-w-5xl mx-auto px-5 h-16 flex items-center justify-between">

        <div class="flex items-center gap-3">

            <button
                type="button"
                onclick="toggleMenu()"
                class="w-10 h-10 rounded-full hover:bg-slate-100 flex items-center justify-center"
                aria-label="Open menu">

                <span class="material-symbols-outlined">menu</span>

            </button>

            <div>
                <h1 class="font-bold text-lg text-blue-700">REPXA</h1>
                <p class="text-xs text-slate-500">Repair Management</p>
            </div>

        </div>

        <a
            href="profile.php"
            class="w-10 h-10 rounded-full overflow-hidden border-2 border-blue-100 bg-blue-50 flex items-center justify-center"
            title="Profile">

            <?php if ($menuPhotoExists): ?>

                <img
                    src="<?= htmlspecialchars($menuProfile["owner_photo"]) ?>"
                    alt="Owner"
                    class="w-full h-full object-cover">

            <?php else: ?>

                <span class="material-symbols-outlined text-blue-700">
                    person
                </span>

            <?php endif; ?>

        </a>

    </div>
</header>


<!-- =========================================================
     COMMON SIDE MENU
========================================================= -->

<div
    id="sideMenu"
    class="hidden fixed inset-0 z-[100]"
    aria-hidden="true">

    <!-- Overlay -->
    <div
        onclick="toggleMenu()"
        class="absolute inset-0 bg-black/40">
    </div>


    <!-- Drawer -->
    <aside
        class="absolute left-0 top-0 bottom-0 w-[310px] max-w-[88vw] bg-white shadow-2xl flex flex-col">

        <!-- Drawer Header -->
        <div class="p-5 border-b">

            <div class="flex items-center justify-between">

                <div class="flex items-center gap-3">

                    <div class="w-11 h-11 rounded-xl overflow-hidden bg-blue-50 flex items-center justify-center">

                        <?php if ($menuPhotoExists): ?>

                            <img
                                src="<?= htmlspecialchars($menuProfile["owner_photo"]) ?>"
                                alt="Owner"
                                class="w-full h-full object-cover">

                        <?php else: ?>

                            <span class="material-symbols-outlined text-blue-700 text-2xl">
                                person
                            </span>

                        <?php endif; ?>

                    </div>

                    <div class="min-w-0">

                        <p class="font-bold truncate">
                            <?= htmlspecialchars($menuProfile["owner_name"]) ?>
                        </p>

                        <p class="text-xs text-slate-500 truncate">
                            <?= htmlspecialchars($menuProfile["shop_name"]) ?>
                        </p>

                    </div>

                </div>

                <button
                    type="button"
                    onclick="toggleMenu()"
                    class="w-9 h-9 rounded-full hover:bg-slate-100 flex items-center justify-center">

                    <span class="material-symbols-outlined">
                        close
                    </span>

                </button>

            </div>

        </div>


        <!-- Menu -->
        <div class="flex-1 overflow-y-auto p-4">

            <p class="px-3 mb-2 text-[11px] font-bold tracking-wider text-slate-400">
                MAIN MENU
            </p>

            <div class="space-y-1">

                <?php if ($menuIsOwner): ?>

                <a
                    href="dashboard.php"
                    class="flex items-center gap-3 p-3 rounded-xl <?= $currentPage === "dashboard.php" ? "bg-blue-50 text-blue-700 font-semibold" : "hover:bg-slate-100" ?>">

                    <span class="material-symbols-outlined">dashboard</span>
                    <span>Home</span>

                </a>

                <?php else: ?>

                <a
                    href="agent_dashboard.php"
                    class="flex items-center gap-3 p-3 rounded-xl <?= $currentPage === "agent_dashboard.php" ? "bg-blue-50 text-blue-700 font-semibold" : "hover:bg-slate-100" ?>">

                    <span class="material-symbols-outlined">dashboard</span>
                    <span>Dashboard</span>

                </a>

                <?php endif; ?>


                <a
                    href="repair.php"
                    class="flex items-center gap-3 p-3 rounded-xl <?= $currentPage === "repair.php" ? "bg-blue-50 text-blue-700 font-semibold" : "hover:bg-slate-100" ?>">

                    <span class="material-symbols-outlined">build</span>
                    <span><?= $menuIsOwner ? "Repairs" : "New Repair Request" ?></span>

                </a>


                <a
                    href="status.php"
                    class="flex items-center gap-3 p-3 rounded-xl <?= $currentPage === "status.php" ? "bg-blue-50 text-blue-700 font-semibold" : "hover:bg-slate-100" ?>">

                    <span class="material-symbols-outlined">track_changes</span>
                    <span>Repair Status</span>

                </a>


                <a
                    href="customers.php"
                    class="flex items-center gap-3 p-3 rounded-xl <?= $currentPage === "customers.php" ? "bg-blue-50 text-blue-700 font-semibold" : "hover:bg-slate-100" ?>">

                    <span class="material-symbols-outlined">groups</span>
                    <span>Customers</span>

                </a>


                <a
                    href="inventory.php"
                    class="flex items-center gap-3 p-3 rounded-xl <?= $currentPage === "inventory.php" ? "bg-blue-50 text-blue-700 font-semibold" : "hover:bg-slate-100" ?>">

                    <span class="material-symbols-outlined">inventory_2</span>
                    <span>Inventory</span>

                </a>


                <a
                    href="billing.php"
                    class="flex items-center gap-3 p-3 rounded-xl <?= $currentPage === "billing.php" ? "bg-blue-50 text-blue-700 font-semibold" : "hover:bg-slate-100" ?>">

                    <span class="material-symbols-outlined">receipt_long</span>
                    <span>Billing</span>

                </a>

            </div>


            <?php if ($menuIsOwner): ?>

            <p class="px-3 mt-6 mb-2 text-[11px] font-bold tracking-wider text-slate-400">
                MANAGEMENT
            </p>

            <div class="space-y-1">

                <a
                    href="profile.php"
                    class="flex items-center gap-3 p-3 rounded-xl <?= $currentPage === "profile.php" ? "bg-blue-50 text-blue-700 font-semibold" : "hover:bg-slate-100" ?>">

                    <span class="material-symbols-outlined">person</span>
                    <span>Profile</span>

                </a>


                <a
                    href="staff.php"
                    class="flex items-center gap-3 p-3 rounded-xl <?= $currentPage === "staff.php" ? "bg-blue-50 text-blue-700 font-semibold" : "hover:bg-slate-100" ?>">

                    <span class="material-symbols-outlined">badge</span>
                    <span>Staff</span>

                </a>


                <a
                    href="recycle_bin.php"
                    class="flex items-center gap-3 p-3 rounded-xl <?= $currentPage === "recycle_bin.php" ? "bg-blue-50 text-blue-700 font-semibold" : "hover:bg-slate-100" ?>">

                    <span class="material-symbols-outlined">delete</span>
                    <span>Recycle Bin</span>

                </a>


                <a
                    href="help.php"
                    class="flex items-center gap-3 p-3 rounded-xl <?= $currentPage === "help.php" ? "bg-blue-50 text-blue-700 font-semibold" : "hover:bg-slate-100" ?>">

                    <span class="material-symbols-outlined">help</span>
                    <span>Help &amp; Support</span>

                </a>

            </div>

            <?php else: ?>

            <p class="px-3 mt-6 mb-2 text-[11px] font-bold tracking-wider text-slate-400">
                MY ACCOUNT
            </p>

            <div class="space-y-1">

                <a
                    href="profile.php"
                    class="flex items-center gap-3 p-3 rounded-xl <?= $currentPage === "profile.php" ? "bg-blue-50 text-blue-700 font-semibold" : "hover:bg-slate-100" ?>">

                    <span class="material-symbols-outlined">person</span>
                    <span>My Profile</span>

                </a>


                <a
                    href="help.php"
                    class="flex items-center gap-3 p-3 rounded-xl <?= $currentPage === "help.php" ? "bg-blue-50 text-blue-700 font-semibold" : "hover:bg-slate-100" ?>">

                    <span class="material-symbols-outlined">help</span>
                    <span>Help &amp; Support</span>

                </a>

            </div>

            <?php endif; ?>

        </div>


        <!-- User / Logout -->
        <div class="p-4 border-t bg-slate-50">

            <?php if ($menuIsOwner): ?>

            <div class="bg-white border rounded-2xl p-3 flex items-center gap-3">

                <div class="w-11 h-11 rounded-xl overflow-hidden bg-blue-50 flex items-center justify-center shrink-0">

                    <?php if ($menuPhotoExists): ?>

                        <img
                            src="<?= htmlspecialchars($menuProfile["owner_photo"]) ?>"
                            alt="Owner"
                            class="w-full h-full object-cover">

                    <?php else: ?>

                        <span class="material-symbols-outlined text-blue-700">
                            person
                        </span>

                    <?php endif; ?>

                </div>

                <div class="min-w-0 flex-1">

                    <p class="font-semibold text-sm truncate">
                        <?= htmlspecialchars($menuProfile["owner_name"]) ?>
                    </p>

                    <p class="text-xs text-slate-500 truncate">
                        <?= htmlspecialchars($menuProfile["shop_name"]) ?>
                    </p>

                </div>

                <a
                    href="logout.php"
                    title="Logout"
                    class="w-9 h-9 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 flex items-center justify-center shrink-0">

                    <span class="material-symbols-outlined text-lg">
                        logout
                    </span>

                </a>

            </div>

            <?php else: ?>

            <div class="bg-white border rounded-2xl p-3 flex items-center gap-3">

                <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center shrink-0">

                    <span class="material-symbols-outlined text-blue-700">
                        person
                    </span>

                </div>

                <div class="min-w-0 flex-1">

                    <p class="font-semibold text-sm truncate">
                        <?= htmlspecialchars($menuUserName) ?>
                    </p>

                    <p class="text-xs text-slate-500 truncate">
                        <?= htmlspecialchars(ucwords($menuUserRole)) ?>
                        <?php if ($menuUsername !== ""): ?>
                            • <?= htmlspecialchars($menuUsername) ?>
                        <?php endif; ?>
                    </p>

                </div>

                <a
                    href="logout.php"
                    title="Logout"
                    class="w-9 h-9 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 flex items-center justify-center shrink-0">

                    <span class="material-symbols-outlined text-lg">
                        logout
                    </span>

                </a>

            </div>

            <?php endif; ?>

        </div>

    </aside>

</div>


<script>
function toggleMenu() {
    const menu = document.getElementById("sideMenu");

    if (!menu) {
        return;
    }

    menu.classList.toggle("hidden");

    menu.setAttribute(
        "aria-hidden",
        menu.classList.contains("hidden") ? "true" : "false"
    );
}

/*
 * Close menu with Escape.
 */
document.addEventListener("keydown", function (event) {

    if (event.key === "Escape") {

        const menu = document.getElementById("sideMenu");

        if (menu && !menu.classList.contains("hidden")) {
            toggleMenu();
        }
    }
});
</script>