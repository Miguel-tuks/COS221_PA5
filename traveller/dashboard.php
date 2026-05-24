<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('Traveller');

$pageTitle = 'My dashboard';
$pdo = getDB();
$uid = currentUserId();

$me = $pdo->prepare('SELECT * FROM TRAVELLER WHERE userID = :u');
$me->execute([':u' => $uid]);
$me = $me->fetch();

$bookCount = $pdo->prepare('SELECT COUNT(*) FROM BOOKING WHERE travellerID = :u');
$bookCount->execute([':u' => $uid]);
$bookCount = (int)$bookCount->fetchColumn();

$joinCount = $pdo->prepare('SELECT COUNT(*) FROM JOINS WHERE travellerID = :u');
$joinCount->execute([':u' => $uid]);
$joinCount = (int)$joinCount->fetchColumn();
$recent = $pdo->prepare(
    "SELECT b.*, p.pkgName, p.price, ta.agencyName,
            (SELECT GROUP_CONCAT(DISTINCT CONCAT(d.city, ', ', d.country) SEPARATOR ' • ')
             FROM PACKAGE_DESTINATION pd JOIN DESTINATION d ON d.destinationID = pd.destinationID
             WHERE pd.packageID = p.packageID) AS destinations
     FROM BOOKING b
     JOIN PACKAGE       p  ON p.packageID = b.packageID
     JOIN TRAVEL_AGENCY ta ON ta.userID   = p.agencyID
     WHERE b.travellerID = :u
     ORDER BY b.bookingDate DESC LIMIT 3"
);
$recent->execute([':u' => $uid]);
$recent = $recent->fetchAll();

$top = $pdo->query(
    "SELECT p.*, ta.agencyName,
            (SELECT image FROM PACKAGE_IMAGE WHERE packageID = p.packageID LIMIT 1) AS image,
            COALESCE((SELECT AVG(rating) FROM REVIEW WHERE packageID = p.packageID), 0) AS avg_rating,
            (SELECT GROUP_CONCAT(DISTINCT CONCAT(d.city, ', ', d.country) SEPARATOR ' • ')
             FROM PACKAGE_DESTINATION pd JOIN DESTINATION d ON d.destinationID = pd.destinationID
             WHERE pd.packageID = p.packageID) AS destinations
     FROM PACKAGE p
     JOIN TRAVEL_AGENCY ta ON ta.userID = p.agencyID
     WHERE p.isActive = 1
     ORDER BY avg_rating DESC, p.packageID ASC LIMIT 3"
)->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<h1>Welcome back, <?= h($me['firstName']) ?>!</h1>

<div class="stats">
    <div class="stat"><div class="stat-num"><?= $bookCount ?></div><div class="stat-lbl">Bookings</div></div>
    <div class="stat"><div class="stat-num"><?= $joinCount ?></div><div class="stat-lbl">Group trips joined</div></div>
</div>

<div class="section">
    <h2>Recent bookings</h2>
    <?php if (!$recent): ?>
        <p>No bookings yet. <a href="packages.php">Browse packages</a> to get started.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table class="data">
        <tr><th>Package</th><th>Destinations</th><th>Agency</th><th>Pax</th><th>Status</th><th></th></tr>
        <?php foreach ($recent as $r): ?>
        <tr>
            <td><?= h($r['pkgName']) ?></td>
            <td><?= h($r['destinations']) ?></td>
            <td><?= h($r['agencyName']) ?></td>
            <td><?= (int)$r['numPax'] ?></td>
            <td><span class="status status-<?= h($r['status']) ?>"><?= h(ucfirst($r['status'])) ?></span></td>
            <td><a href="bookings.php">View</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
    <?php endif; ?>
</div>

<div class="section">
    <h2>Top-rated packages</h2>
    <div class="cards">
        <?php foreach ($top as $p): ?>
        <div class="card">
            <img src="<?= h($p['image'] ?: 'https://placehold.co/600x400?text=Tripistry') ?>" alt="<?= h($p['pkgName']) ?>">
            <div class="card-body">
                <h3><?= h($p['pkgName']) ?></h3>
                <div class="meta"><?= h($p['destinations']) ?> · <?= (int)$p['duration'] ?> days</div>
                <div class="meta"><?= renderStars($p['avg_rating']) ?></div>
                <div class="meta">by <?= h($p['agencyName']) ?></div>
                <div class="price">R<?= number_format($p['price'], 2) ?></div>
                <a class="btn" style="margin-top:8px" href="package_detail.php?id=<?= (int)$p['packageID'] ?>">View</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
