<?php

require_once __DIR__ . '/../includes/auth.php';
requireRole('Traveller');

$pageTitle = 'Compare packages';
$pdo = getDB();

$ids = array_map('intval', $_GET['ids'] ?? []);
$ids = array_slice(array_unique($ids), 0, 3);

if (!$ids) {
    setFlash('error', 'Pick at least 2 packages to compare.');
    header('Location: packages.php');
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare(
    "SELECT p.*, ta.agencyName,
            (SELECT image FROM PACKAGE_IMAGE WHERE packageID = p.packageID LIMIT 1) AS image,
            COALESCE((SELECT AVG(rating) FROM REVIEW WHERE packageID = p.packageID), 0) AS avg_rating,
            (SELECT GROUP_CONCAT(DISTINCT CONCAT(d.city, ', ', d.country) SEPARATOR ' • ')
             FROM PACKAGE_DESTINATION pd JOIN DESTINATION d ON d.destinationID = pd.destinationID
             WHERE pd.packageID = p.packageID) AS destinations,
            (SELECT COUNT(*) FROM PACKAGE_FLIGHT        WHERE packageID = p.packageID) AS num_flights,
            (SELECT COUNT(*) FROM PACKAGE_ACCOMMODATION WHERE packageID = p.packageID) AS num_acc,
            (SELECT COUNT(*) FROM PACKAGE_ATTRACTION    WHERE packageID = p.packageID) AS num_attr
     FROM PACKAGE p
     JOIN TRAVEL_AGENCY ta ON ta.userID = p.agencyID
     WHERE p.packageID IN ($placeholders)"
);
$stmt->execute($ids);
$packages = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<h1>Compare packages</h1>
<p><a href="packages.php">← Back to all packages</a></p>

<?php if (count($packages) < 2): ?>
    <div class="flash flash-info">Select at least 2 packages to make a useful comparison.</div>
<?php endif; ?>

<div class="table-wrap"><table class="data">
    <tr><th></th>
        <?php foreach ($packages as $p): ?>
            <th>
                <img src="<?= h($p['image'] ?: 'https://placehold.co/300x200?text=Tripistry') ?>"
                     style="width:100%;max-width:240px;height:120px;object-fit:cover;border-radius:6px"><br>
                <strong><?= h($p['pkgName']) ?></strong>
            </th>
        <?php endforeach; ?>
    </tr>
    <tr><td>Agency</td>
        <?php foreach ($packages as $p): ?><td><?= h($p['agencyName']) ?></td><?php endforeach; ?>
    </tr>
    <tr><td>Destinations</td>
        <?php foreach ($packages as $p): ?><td><?= h($p['destinations']) ?></td><?php endforeach; ?>
    </tr>
    <tr><td>Price</td>
        <?php foreach ($packages as $p): ?><td><strong>R<?= number_format($p['price'], 2) ?></strong></td><?php endforeach; ?>
    </tr>
    <tr><td>Duration</td>
        <?php foreach ($packages as $p): ?><td><?= (int)$p['duration'] ?> days</td><?php endforeach; ?>
    </tr>
    <tr><td>Max travellers</td>
        <?php foreach ($packages as $p): ?><td><?= (int)$p['maxPeople'] ?></td><?php endforeach; ?>
    </tr>
    <tr><td>Rating</td>
        <?php foreach ($packages as $p): ?><td><?= renderStars($p['avg_rating']) ?></td><?php endforeach; ?>
    </tr>
    <tr><td>Flights</td>
        <?php foreach ($packages as $p): ?><td><?= (int)$p['num_flights'] ?></td><?php endforeach; ?>
    </tr>
    <tr><td>Accommodations</td>
        <?php foreach ($packages as $p): ?><td><?= (int)$p['num_acc'] ?></td><?php endforeach; ?>
    </tr>
    <tr><td>Attractions</td>
        <?php foreach ($packages as $p): ?><td><?= (int)$p['num_attr'] ?></td><?php endforeach; ?>
    </tr>
    <tr><td></td>
        <?php foreach ($packages as $p): ?>
            <td><a class="btn btn-primary" href="package_detail.php?id=<?= (int)$p['packageID'] ?>">View</a></td>
        <?php endforeach; ?>
    </tr>
</table></div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
