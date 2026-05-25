<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('Travel Agency');

$pageTitle = 'Catalogue items';
$pdo = getDB();

$validTypes = ['destinations','flights','accommodations','attractions','restaurants'];
$type = $_GET['type'] ?? 'destinations';
if (!in_array($type, $validTypes, true)) $type = 'destinations';

$tables = [
    'destinations'    => ['DESTINATION',        'destinationID'],
    'flights'         => ['FLIGHT',             'flightID'],
    'accommodations'  => ['ACCOMMODATION',      'accommodationID'],
    'attractions'     => ['TOURIST_ATTRACTION', 'attractionID'],
    'restaurants'     => ['RESTAURANT',         'restaurantID'],
];
$linkTables = [
    'destinations'   => 'PACKAGE_DESTINATION',
    'flights'        => 'PACKAGE_FLIGHT',
    'accommodations' => 'PACKAGE_ACCOMMODATION',
    'attractions'    => 'PACKAGE_ATTRACTION',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    [$table, $idCol] = $tables[$type];
    $itemId = (int)($_POST['item_id'] ?? 0);

    try {
        if (isset($linkTables[$type])) {
            $link = $linkTables[$type];
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM $link WHERE $idCol = :i");
            $stmt->execute([':i' => $itemId]);
            if ($stmt->fetchColumn() > 0) {
                throw new RuntimeException('This item is used in one or more packages.');
            }
        }
        $stmt = $pdo->prepare("DELETE FROM $table WHERE $idCol = :i");
        $stmt->execute([':i' => $itemId]);
        setFlash('success', 'Item deleted.');
    } catch (Exception $e) {
        setFlash('error', 'Cannot delete: ' . $e->getMessage());
    }
    header('Location: items.php?type=' . urlencode($type));
    exit;
}

if ($type === 'destinations') {
    $rows = $pdo->query('SELECT * FROM DESTINATION ORDER BY country, city')->fetchAll();
} elseif ($type === 'flights') {
    $rows = $pdo->query(
        "SELECT f.*, d.city, d.country FROM FLIGHT f
         JOIN DESTINATION d ON d.destinationID = f.destinationID
         ORDER BY f.departure"
    )->fetchAll();
} elseif ($type === 'accommodations') {
    $rows = $pdo->query(
        "SELECT a.*, d.city, d.country FROM ACCOMMODATION a
         JOIN DESTINATION d ON d.destinationID = a.destinationID
         ORDER BY a.accName"
    )->fetchAll();
} elseif ($type === 'attractions') {
    $rows = $pdo->query('SELECT * FROM TOURIST_ATTRACTION ORDER BY name')->fetchAll();
} else {
    $rows = $pdo->query(
        "SELECT r.*, d.city, d.country FROM RESTAURANT r
         JOIN DESTINATION d ON d.destinationID = r.destinationID
         ORDER BY r.name"
    )->fetchAll();
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Catalogue items</h1>
    <a class="btn btn-primary" href="item_form.php?type=<?= h($type) ?>">+ New <?= h(rtrim($type, 's')) ?></a>
</div>

<p class="meta">
    These items are part of a shared global catalogue. Any agency can add to or edit
    them, since the schema does not assign ownership.
</p>

<div class="tabs">
    <?php foreach ($validTypes as $t): ?>
        <a class="<?= $type === $t ? 'active' : '' ?>"
           href="items.php?type=<?= h($t) ?>"><?= h(ucfirst($t)) ?></a>
    <?php endforeach; ?>
</div>

<?php if (!$rows): ?>
    <p>No <?= h($type) ?> yet.</p>
<?php else: ?>
<div class="table-wrap"><table class="data">

<?php if ($type === 'destinations'): ?>
    <tr><th>City</th><th>Country</th><th>Continent</th><th></th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
        <td><?= h($r['city']) ?></td>
        <td><?= h($r['country']) ?></td>
        <td><?= h($r['continent']) ?></td>
        <td>
            <a class="btn" href="item_form.php?type=destinations&id=<?= (int)$r['destinationID'] ?>">Edit</a>
            <form method="post" style="display:inline">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="item_id" value="<?= (int)$r['destinationID'] ?>">
                <button type="submit" class="btn btn-danger" data-confirm="Delete this destination?">Del</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>

<?php elseif ($type === 'flights'): ?>
    <tr><th>Airline</th><th>Flight #</th><th>From</th><th>To</th><th>Departs</th><th>Arrives</th><th></th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
        <td><?= h($r['airline']) ?></td>
        <td><?= h($r['flightNumber']) ?></td>
        <td><?= h($r['origin']) ?></td>
        <td><?= h($r['city']) ?>, <?= h($r['country']) ?></td>
        <td><?= h(date('d M Y H:i', strtotime($r['departure']))) ?></td>
        <td><?= h(date('d M Y H:i', strtotime($r['arrival']))) ?></td>
        <td>
            <a class="btn" href="item_form.php?type=flights&id=<?= (int)$r['flightID'] ?>">Edit</a>
            <form method="post" style="display:inline">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="item_id" value="<?= (int)$r['flightID'] ?>">
                <button type="submit" class="btn btn-danger" data-confirm="Delete this flight?">Del</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>

<?php elseif ($type === 'accommodations'): ?>
    <tr><th>Name</th><th>Type</th><th>Destination</th><th>Rating</th><th>Address</th><th></th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
        <td><?= h($r['accName']) ?></td>
        <td><?= h($r['type']) ?></td>
        <td><?= h($r['city']) ?>, <?= h($r['country']) ?></td>
        <td><?= renderStars($r['rating']) ?></td>
        <td><?= h($r['address']) ?></td>
        <td>
            <a class="btn" href="item_form.php?type=accommodations&id=<?= (int)$r['accommodationID'] ?>">Edit</a>
            <form method="post" style="display:inline">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="item_id" value="<?= (int)$r['accommodationID'] ?>">
                <button type="submit" class="btn btn-danger" data-confirm="Delete?">Del</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>

<?php elseif ($type === 'attractions'): ?>
    <tr><th>Name</th><th>Category</th><th>Entrance fee</th><th></th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
        <td><?= h($r['name']) ?></td>
        <td><?= h($r['category']) ?></td>
        <td><?= $r['entranceFee'] > 0 ? 'R' . number_format($r['entranceFee'], 2) : 'Free' ?></td>
        <td>
            <a class="btn" href="item_form.php?type=attractions&id=<?= (int)$r['attractionID'] ?>">Edit</a>
            <form method="post" style="display:inline">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="item_id" value="<?= (int)$r['attractionID'] ?>">
                <button type="submit" class="btn btn-danger" data-confirm="Delete?">Del</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>

<?php else:  ?>
    <tr><th>Name</th><th>Cuisine</th><th>Price</th><th>Destination</th><th></th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
        <td><?= h($r['name']) ?></td>
        <td><?= h($r['cuisine']) ?></td>
        <td><?= h($r['priceRange']) ?></td>
        <td><?= h($r['city']) ?>, <?= h($r['country']) ?></td>
        <td>
            <a class="btn" href="item_form.php?type=restaurants&id=<?= (int)$r['restaurantID'] ?>">Edit</a>
            <form method="post" style="display:inline">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="item_id" value="<?= (int)$r['restaurantID'] ?>">
                <button type="submit" class="btn btn-danger" data-confirm="Delete?">Del</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
<?php endif; ?>

</table></div>
<?php endif; ?>

<script src="<?= h($base) ?>js/validation.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
