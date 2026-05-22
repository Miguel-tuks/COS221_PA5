<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('Travel Agency');

$pageTitle = 'Bookings';
$pdo = getDB();
$uid = currentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'status') {
    $tid    = (int)($_POST['traveller_id'] ?? 0);
    $pid    = (int)($_POST['package_id']   ?? 0);
    $status = $_POST['status'] ?? '';

    if (!in_array($status, ['pending','confirmed','cancelled'], true)) {
        setFlash('error', 'Invalid status.');
    } else {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM PACKAGE WHERE packageID = :p AND agencyID = :a'
        );
        $stmt->execute([':p' => $pid, ':a' => $uid]);
        if (!$stmt->fetchColumn()) {
            setFlash('error', 'Booking does not belong to your agency.');
        } else {
            $stmt = $pdo->prepare(
                'UPDATE BOOKING SET status = :s WHERE travellerID = :t AND packageID = :p'
            );
            $stmt->execute([':s' => $status, ':t' => $tid, ':p' => $pid]);
            setFlash('success', 'Booking status updated.');
        }
    }
    header('Location: bookings.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT b.*, p.pkgName, p.price, t.firstName, t.lastName, u.username
     FROM BOOKING   b
     JOIN PACKAGE   p ON p.packageID = b.packageID
     JOIN TRAVELLER t ON t.userID    = b.travellerID
     JOIN USER      u ON u.userID    = t.userID
     WHERE p.agencyID = :a
     ORDER BY b.bookingDate DESC"
);
$stmt->execute([':a' => $uid]);
$bookings = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<h1>Bookings</h1>

<?php if (!$bookings): ?>
    <p>No bookings on your packages yet.</p>
<?php else: ?>
<div class="table-wrap"><table class="data">
    <tr>
        <th>Date</th><th>Traveller</th><th>Package</th>
        <th>Pax</th><th>Total</th><th>Status</th><th>Update</th>
    </tr>
    <?php foreach ($bookings as $b): ?>
    <tr>
        <td><?= h(date('d M Y', strtotime($b['bookingDate']))) ?></td>
        <td>
            <?= h($b['firstName']) ?> <?= h($b['lastName']) ?>
            <br><small class="meta">@<?= h($b['username']) ?></small>
        </td>
        <td><?= h($b['pkgName']) ?></td>
        <td><?= (int)$b['numPax'] ?></td>
        <td>R<?= number_format($b['price'] * (int)$b['numPax'], 2) ?></td>
        <td><span class="status status-<?= h($b['status']) ?>"><?= h(ucfirst($b['status'])) ?></span></td>
        <td>
            <form method="post" style="display:flex;gap:6px;align-items:center">
                <input type="hidden" name="action"       value="status">
                <input type="hidden" name="traveller_id" value="<?= (int)$b['travellerID'] ?>">
                <input type="hidden" name="package_id"   value="<?= (int)$b['packageID'] ?>">
                <select name="status">
                    <?php foreach (['pending','confirmed','cancelled'] as $s): ?>
                        <option value="<?= $s ?>" <?= $b['status'] === $s ? 'selected' : '' ?>>
                            <?= ucfirst($s) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn">Save</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table></div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
