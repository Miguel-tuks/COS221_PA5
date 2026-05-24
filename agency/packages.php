<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('Travel Agency');

$pageTitle = 'My packages';
$pdo = getDB();
$uid = currentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $pid = (int)($_POST['package_id'] ?? 0);

    $stmt = $pdo->prepare(
        'SELECT 1 FROM PACKAGE WHERE packageID = :p AND agencyID = :a'
    );
    $stmt->execute([':p' => $pid, ':a' => $uid]);
    if (!$stmt->fetchColumn()) {
        setFlash('error', 'Package not found or you do not have permission to delete it.');
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM BOOKING WHERE packageID = :p');
            $stmt->execute([':p' => $pid]);
            if ($stmt->fetchColumn() > 0) {
                throw new RuntimeException('Cannot delete: this package has active bookings.');
            }
            $stmt = $pdo->prepare('DELETE FROM REVIEW WHERE packageID = :p');
            $stmt->execute([':p' => $pid]);
            $stmt = $pdo->prepare('DELETE FROM PACKAGE WHERE packageID = :p AND agencyID = :a');
            $stmt->execute([':p' => $pid, ':a' => $uid]);
            $pdo->commit();
            setFlash('success', 'Package deleted.');
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('error', $e->getMessage());
        }
    }
    header('Location: packages.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT p.*,
            (SELECT image FROM PACKAGE_IMAGE WHERE packageID = p.packageID LIMIT 1) AS image,
            (SELECT COUNT(*) FROM BOOKING WHERE packageID = p.packageID) AS bookings,
            COALESCE((SELECT AVG(rating) FROM REVIEW WHERE packageID = p.packageID), 0) AS avg_rating,
            (SELECT GROUP_CONCAT(DISTINCT CONCAT(d.city, ', ', d.country) SEPARATOR ' • ')
             FROM PACKAGE_DESTINATION pd JOIN DESTINATION d ON d.destinationID = pd.destinationID
             WHERE pd.packageID = p.packageID) AS destinations
     FROM PACKAGE p
     WHERE p.agencyID = :u
     ORDER BY p.packageID DESC"
);
$stmt->execute([':u' => $uid]);
$packages = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>My packages</h1>
    <a class="btn btn-primary" href="package_form.php">+ New package</a>
</div>

<?php if (!$packages): ?>
    <p>You haven't created any packages yet. <a href="package_form.php">Create your first one</a>.</p>
<?php else: ?>
<div class="table-wrap"><table class="data">
    <tr>
        <th></th><th>Name</th><th>Destinations</th><th>Price</th>
        <th>Duration</th><th>Active</th><th>Rating</th><th>Bookings</th><th></th>
    </tr>
    <?php foreach ($packages as $p): ?>
    <tr>
        <td>
            <img src="<?= h($p['image'] ?: 'https://placehold.co/80x60?text=...') ?>"
                 style="width:80px;height:60px;object-fit:cover;border-radius:4px">
        </td>
        <td><strong><?= h($p['pkgName']) ?></strong></td>
        <td><?= h($p['destinations']) ?></td>
        <td>R<?= number_format($p['price'], 2) ?></td>
        <td><?= (int)$p['duration'] ?> days</td>
        <td><?= $p['isActive'] ? '✓' : '—' ?></td>
        <td><?= renderStars($p['avg_rating']) ?></td>
        <td><?= (int)$p['bookings'] ?></td>
        <td>
            <a class="btn" href="package_form.php?id=<?= (int)$p['packageID'] ?>">Edit</a>
            <form method="post" style="display:inline">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="package_id" value="<?= (int)$p['packageID'] ?>">
                <button type="submit" class="btn btn-danger"
                        data-confirm="Delete this package? This cannot be undone.">Delete</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table></div>
<?php endif; ?>

<script src="<?= h($base) ?>js/validation.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
