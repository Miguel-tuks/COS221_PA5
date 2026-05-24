<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
$base = baseUrl();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= isset($pageTitle) ? h($pageTitle) . ' · Tripistry' : 'Tripistry' ?></title>
<link rel="stylesheet" href="<?= h($base) ?>css/style.css">
</head>
<body>
<header class="topbar">
    <div class="container topbar-inner">
        <a class="brand" href="<?= h($base) ?>index.php">Tripistry</a>
        <nav class="nav">
        <?php if (!isLoggedIn()): ?>
            <a href="<?= h($base) ?>login.php">Log in</a>
            <a class="btn-primary" href="<?= h($base) ?>register.php">Sign up</a>
        <?php elseif (isTraveller()): ?>
            <a href="<?= h($base) ?>traveller/dashboard.php">Home</a>
            <a href="<?= h($base) ?>traveller/packages.php">Packages</a>
            <a href="<?= h($base) ?>traveller/browse.php">Browse</a>
            <a href="<?= h($base) ?>traveller/group_trips.php">Group trips</a>
            <a href="<?= h($base) ?>traveller/bookings.php">My bookings</a>
            <a href="<?= h($base) ?>traveller/profile.php">Profile</a>
            <a class="btn-ghost" href="<?= h($base) ?>logout.php">Log out</a>
        <?php else: /* Travel Agency */ ?>
            <a href="<?= h($base) ?>agency/dashboard.php">Home</a>
            <a href="<?= h($base) ?>agency/packages.php">Packages</a>
            <a href="<?= h($base) ?>agency/group_trips.php">Group trips</a>
            <a href="<?= h($base) ?>agency/items.php">Items</a>
            <a href="<?= h($base) ?>agency/bookings.php">Bookings</a>
            <a href="<?= h($base) ?>agency/reviews.php">Reviews</a>
            <a href="<?= h($base) ?>agency/profile.php">Profile</a>
            <a class="btn-ghost" href="<?= h($base) ?>logout.php">Log out</a>
        <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container">
<?php showFlash(); ?>
