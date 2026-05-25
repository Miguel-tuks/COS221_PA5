<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . baseUrl() . (isTraveller() ? 'traveller/' : 'agency/') . 'dashboard.php');
    exit;
}

$pageTitle = 'Welcome';
$pdo = getDB();

$featured = $pdo->query(
    "SELECT p.*, ta.agencyName,
            (SELECT image FROM PACKAGE_IMAGE WHERE packageID = p.packageID LIMIT 1) AS image,
            COALESCE((SELECT AVG(rating) FROM REVIEW WHERE packageID = p.packageID), 0) AS avg_rating,
            (SELECT GROUP_CONCAT(DISTINCT CONCAT(d.city, ', ', d.country) SEPARATOR ' • ')
             FROM PACKAGE_DESTINATION pd
             JOIN DESTINATION d ON d.destinationID = pd.destinationID
             WHERE pd.packageID = p.packageID) AS destinations
     FROM PACKAGE p
     JOIN TRAVEL_AGENCY ta ON ta.userID = p.agencyID
     WHERE p.isActive = 1
     ORDER BY avg_rating DESC, p.packageID ASC
     LIMIT 3"
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="hero">
    <h1>Plan your next great vacation</h1>
    <p>Compare travel packages from trusted agencies. Browse destinations, flights and stays — all in one place.</p>
    <a class="btn-primary" href="register.php">Get started</a>
    <a class="btn-ghost" href="login.php">I have an account</a>
</div>

<div class="section">
    <h2>Featured packages</h2>
    <div class="cards">
        <?php foreach ($featured as $p): ?>
        <div class="card">
            <img src="<?= h($p['image'] ?: 'https://placehold.co/600x400?text=Tripistry') ?>" alt="<?= h($p['pkgName']) ?>">
            <div class="card-body">
                <h3><?= h($p['pkgName']) ?></h3>
                <div class="meta"><?= h($p['destinations']) ?></div>
                <div class="meta"><?= (int)$p['duration'] ?> days · by <?= h($p['agencyName']) ?></div>
                <div class="meta"><?= renderStars($p['avg_rating']) ?></div>
                <div class="price">R<?= number_format($p['price'], 2) ?></div>
                <a class="btn" style="margin-top:8px" href="login.php">Log in to view</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
