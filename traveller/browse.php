<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('Traveller');
$pageTitle = 'Browse catalogue';
$pdo = getDB();
$validTypes = ['destinations','flights','accommodations','attractions','restaurants'];
$type = $_GET['type'] ?? 'destinations';
if (!in_array($type, $validTypes, true)) $type = 'destinations';

$rows = [];
if ($type === 'destinations') {
    $rows = $pdo->query('SELECT * FROM DESTINATION ORDER BY country, city')->fetchAll();
} elseif ($type === 'flights') {
    $rows = $pdo->query(
        "SELECT f.*, d.city, d.country
         FROM FLIGHT f JOIN DESTINATION d ON d.destinationID = f.destinationID
         ORDER BY f.departure"
    )->fetchAll();
} elseif ($type === 'accommodations') {
    $rows = $pdo->query(
        "SELECT a.*, d.city, d.country
         FROM ACCOMMODATION a JOIN DESTINATION d ON d.destinationID = a.destinationID
         ORDER BY a.accName"
    )->fetchAll();
} elseif ($type === 'attractions') {
    $rows = $pdo->query('SELECT * FROM TOURIST_ATTRACTION ORDER BY name')->fetchAll();
} else { 
    $rows = $pdo->query(
        "SELECT r.*, d.city, d.country
         FROM RESTAURANT r JOIN DESTINATION d ON d.destinationID = r.destinationID
         ORDER BY r.name"
    )->fetchAll();
}

require __DIR__ . '/../includes/header.php';
?>

<h1>Browse the catalogue</h1>

<div class="tabs">
    <?php foreach ($validTypes as $t): ?>
        <a class="<?= $type === $t ? 'active' : '' ?>"
           href="browse.php?type=<?= h($t) ?>"><?= h(ucfirst($t)) ?></a>
    <?php endforeach; ?>
</div>

<?php if (!$rows): ?>
    <p>No <?= h($type) ?> available.</p>
<?php else: ?>
<div class="table-wrap"><table class="data">

<?php if ($type === 'destinations'): ?>
    <tr><th>City</th><th>Country</th><th>Continent</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr><td><?= h($r['city']) ?></td><td><?= h($r['country']) ?></td><td><?= h($r['continent']) ?></td></tr>
    <?php endforeach; ?>

<?php elseif ($type === 'flights'): ?>
    <tr><th>Airline</th><th>Flight #</th><th>From</th><th>To</th><th>Departs</th><th>Arrives</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
        <td><?= h($r['airline']) ?></td>
        <td><?= h($r['flightNumber']) ?></td>
        <td><?= h($r['origin']) ?></td>
        <td><?= h($r['city']) ?>, <?= h($r['country']) ?></td>
        <td><?= h(date('d M Y H:i', strtotime($r['departure']))) ?></td>
        <td><?= h(date('d M Y H:i', strtotime($r['arrival']))) ?></td>
    </tr>
    <?php endforeach; ?>

<?php elseif ($type === 'accommodations'): ?>
    <tr><th>Name</th><th>Type</th><th>Destination</th><th>Rating</th><th>Address</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
        <td><?= h($r['accName']) ?></td>
        <td><?= h($r['type']) ?></td>
        <td><?= h($r['city']) ?>, <?= h($r['country']) ?></td>
        <td><?= renderStars($r['rating']) ?></td>
        <td><?= h($r['address']) ?></td>
    </tr>
    <?php endforeach; ?>

<?php elseif ($type === 'attractions'): ?>
    <tr><th>Name</th><th>Category</th><th>Entrance fee</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
        <td><?= h($r['name']) ?></td>
        <td><?= h($r['category']) ?></td>
        <td><?= $r['entranceFee'] > 0 ? 'R' . number_format($r['entranceFee'], 2) : 'Free' ?></td>
    </tr>
    <?php endforeach; ?>

<?php else:  ?>
    <tr><th>Name</th><th>Cuisine</th><th>Price</th><th>Destination</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
        <td><?= h($r['name']) ?></td>
        <td><?= h($r['cuisine']) ?></td>
        <td><?= h($r['priceRange']) ?></td>
        <td><?= h($r['city']) ?>, <?= h($r['country']) ?></td>
    </tr>
    <?php endforeach; ?>
<?php endif; ?>

</table></div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
