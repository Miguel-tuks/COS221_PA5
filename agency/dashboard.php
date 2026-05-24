<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('Travel Agency');

$pageTitle = 'Agency dashboard';
$pdo = getDB();
$uid = currentUserId();

$me = $pdo->prepare('SELECT * FROM TRAVEL_AGENCY WHERE userID = :u');
$me->execute([':u' => $uid]);
$me = $me->fetch();

$pkgCount = $pdo->prepare('SELECT COUNT(*) FROM PACKAGE WHERE agencyID = :u');
$pkgCount->execute([':u' => $uid]);
$pkgCount = (int)$pkgCount->fetchColumn();

$bookCount = $pdo->prepare(
    'SELECT COUNT(*) FROM BOOKING b
     JOIN PACKAGE p ON p.packageID = b.packageID
     WHERE p.agencyID = :u'
);
$bookCount->execute([':u' => $uid]);
$bookCount = (int)$bookCount->fetchColumn();

$avgRating = $pdo->prepare(
    'SELECT COALESCE(AVG(r.rating), 0) FROM REVIEW r
     JOIN PACKAGE p ON p.packageID = r.packageID
     WHERE p.agencyID = :u'
);
$avgRating->execute([':u' => $uid]);
$avgRating = (float)$avgRating->fetchColumn();

/* Recent bookings */
$recent = $pdo->prepare(
    "SELECT b.*, p.pkgName, p.price, t.firstName, t.lastName
     FROM BOOKING b
     JOIN PACKAGE   p ON p.packageID  = b.packageID
     JOIN TRAVELLER t ON t.userID     = b.travellerID
     WHERE p.agencyID = :u
     ORDER BY b.bookingDate DESC LIMIT 5"
);
$recent->execute([':u' => $uid]);
$recent = $recent->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<h1>Hi, <?= h($me['agencyName']) ?>!</h1>
<p class="meta">Manage your packages, group trips and bookings.</p>

<div class="stats">
    <div class="stat"><div class="stat-num"><?= $pkgCount ?></div><div class="stat-lbl">Packages</div></div>
    <div class="stat"><div class="stat-num"><?= $bookCount ?></div><div class="stat-lbl">Bookings</div></div>
    <div class="stat">
        <div class="stat-num"><?= number_format($avgRating, 1) ?></div>
        <div class="stat-lbl">Avg rating</div>
    </div>
</div>

<div class="section">
    <h2>Quick actions</h2>
    <div class="btn-row">
        <a class="btn btn-primary" href="package_form.php">+ New package</a>
        <a class="btn btn-primary" href="group_trip_form.php">+ New group trip</a>
        <a class="btn" href="items.php">Manage catalogue items</a>
        <a class="btn" href="profile.php">Edit profile</a>
    </div>
</div>

<div class="section">
    <h2>Recent bookings</h2>
    <?php if (!$recent): ?>
        <p>No bookings yet.</p>
    <?php else: ?>
    <div class="table-wrap"><table class="data">
        <tr><th>Date</th><th>Traveller</th><th>Package</th><th>Pax</th><th>Total</th><th>Status</th></tr>
        <?php foreach ($recent as $r): ?>
        <tr>
            <td><?= h(date('d M Y', strtotime($r['bookingDate']))) ?></td>
            <td><?= h($r['firstName']) ?> <?= h($r['lastName']) ?></td>
            <td><?= h($r['pkgName']) ?></td>
            <td><?= (int)$r['numPax'] ?></td>
            <td>R<?= number_format($r['price'] * (int)$r['numPax'], 2) ?></td>
            <td><span class="status status-<?= h($r['status']) ?>"><?= h(ucfirst($r['status'])) ?></span></td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <p style="margin-top:10px"><a href="bookings.php">View all bookings →</a></p>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
