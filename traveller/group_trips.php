<?php

require_once __DIR__ . '/../includes/auth.php';
requireRole('Traveller');

$pageTitle = 'Group trips';
$pdo = getDB();
$uid = currentUserId();

if (isset($_GET['join'])) {
    $pid  = (int)($_GET['pkg']  ?? 0);
    $trip = (int)($_GET['trip'] ?? 0);

    $stmt = $pdo->prepare(
        "SELECT gt.*,
                (SELECT COUNT(*) FROM JOINS WHERE packageID = gt.packageID AND tripID = gt.tripID) AS members
         FROM GROUP_TRIP gt WHERE gt.packageID = :p AND gt.tripID = :t"
    );
    $stmt->execute([':p' => $pid, ':t' => $trip]);
    $gt = $stmt->fetch();

    if (!$gt) {
        setFlash('error', 'Trip not found.');
    } elseif ($gt['tripStatus'] !== 'open') {
        setFlash('error', 'This group trip is not open for joining.');
    } elseif ((int)$gt['members'] >= (int)$gt['maxMembers']) {
        setFlash('error', 'This group trip is full.');
    } else {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO JOINS (travellerID, packageID, tripID, joinDate)
                 VALUES (:u, :p, :t, CURDATE())"
            );
            $stmt->execute([':u' => $uid, ':p' => $pid, ':t' => $trip]);
            setFlash('success', 'You\'ve joined the group trip!');
        } catch (PDOException $e) {
            setFlash('info', 'You have already joined this trip.');
        }
    }
    header('Location: group_trips.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'leave') {
    $pid  = (int)($_POST['package_id'] ?? 0);
    $trip = (int)($_POST['trip_id']    ?? 0);
    $stmt = $pdo->prepare(
        'DELETE FROM JOINS WHERE travellerID = :u AND packageID = :p AND tripID = :t'
    );
    $stmt->execute([':u' => $uid, ':p' => $pid, ':t' => $trip]);
    setFlash('success', 'You\'ve left the group trip.');
    header('Location: group_trips.php');
    exit;
}

$open = $pdo->prepare(
    "SELECT gt.*, p.pkgName, p.price, ta.agencyName,
            (SELECT COUNT(*) FROM JOINS WHERE packageID = gt.packageID AND tripID = gt.tripID) AS members,
            (SELECT GROUP_CONCAT(DISTINCT CONCAT(d.city, ', ', d.country) SEPARATOR ' • ')
             FROM PACKAGE_DESTINATION pd JOIN DESTINATION d ON d.destinationID = pd.destinationID
             WHERE pd.packageID = gt.packageID) AS destinations
     FROM GROUP_TRIP gt
     JOIN PACKAGE       p  ON p.packageID = gt.packageID
     JOIN TRAVEL_AGENCY ta ON ta.userID   = gt.agencyID
     WHERE gt.tripStatus = 'open'
       AND NOT EXISTS (SELECT 1 FROM JOINS j
                       WHERE j.travellerID = :u AND j.packageID = gt.packageID AND j.tripID = gt.tripID)
     ORDER BY gt.departDate"
);
$open->execute([':u' => $uid]);
$open = $open->fetchAll();

$mine = $pdo->prepare(
    "SELECT gt.*, p.pkgName, ta.agencyName, j.joinDate,
            (SELECT COUNT(*) FROM JOINS WHERE packageID = gt.packageID AND tripID = gt.tripID) AS members
     FROM JOINS j
     JOIN GROUP_TRIP    gt ON gt.packageID = j.packageID AND gt.tripID = j.tripID
     JOIN PACKAGE       p  ON p.packageID  = gt.packageID
     JOIN TRAVEL_AGENCY ta ON ta.userID    = gt.agencyID
     WHERE j.travellerID = :u
     ORDER BY gt.departDate"
);
$mine->execute([':u' => $uid]);
$mine = $mine->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<h1>Group trips</h1>
<p class="meta">Join a scheduled group of travellers instead of booking on your own.</p>

<div class="section">
    <h2>Trips I've joined</h2>
    <?php if (!$mine): ?>
        <p>You haven't joined any group trips yet.</p>
    <?php else: ?>
    <div class="table-wrap"><table class="data">
        <tr><th>Package</th><th>Agency</th><th>Departs</th><th>Returns</th><th>Members</th><th>Joined</th><th></th></tr>
        <?php foreach ($mine as $t): ?>
        <tr>
            <td><?= h($t['pkgName']) ?> (trip #<?= (int)$t['tripID'] ?>)</td>
            <td><?= h($t['agencyName']) ?></td>
            <td><?= h(date('d M Y', strtotime($t['departDate']))) ?></td>
            <td><?= h(date('d M Y', strtotime($t['returnDate']))) ?></td>
            <td><?= (int)$t['members'] ?> / <?= (int)$t['maxMembers'] ?></td>
            <td><?= h(date('d M Y', strtotime($t['joinDate']))) ?></td>
            <td>
                <form method="post" style="display:inline">
                    <input type="hidden" name="action" value="leave">
                    <input type="hidden" name="package_id" value="<?= (int)$t['packageID'] ?>">
                    <input type="hidden" name="trip_id"    value="<?= (int)$t['tripID'] ?>">
                    <button type="submit" class="btn btn-danger"
                            data-confirm="Leave this group trip?">Leave</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
</div>

<div class="section">
    <h2>Open group trips</h2>
    <?php if (!$open): ?>
        <p>No open trips available right now.</p>
    <?php else: ?>
    <div class="table-wrap"><table class="data">
        <tr><th>Package</th><th>Destinations</th><th>Agency</th><th>Departs</th><th>Returns</th><th>Price</th><th>Members</th><th></th></tr>
        <?php foreach ($open as $t):
            $isFull = (int)$t['members'] >= (int)$t['maxMembers']; ?>
        <tr>
            <td><a href="package_detail.php?id=<?= (int)$t['packageID'] ?>"><?= h($t['pkgName']) ?></a></td>
            <td><?= h($t['destinations']) ?></td>
            <td><?= h($t['agencyName']) ?></td>
            <td><?= h(date('d M Y', strtotime($t['departDate']))) ?></td>
            <td><?= h(date('d M Y', strtotime($t['returnDate']))) ?></td>
            <td>R<?= number_format($t['price'], 2) ?></td>
            <td><?= (int)$t['members'] ?> / <?= (int)$t['maxMembers'] ?></td>
            <td>
                <?php if ($isFull): ?>
                    <em>Full</em>
                <?php else: ?>
                    <a class="btn btn-primary"
                       href="group_trips.php?join=1&pkg=<?= (int)$t['packageID'] ?>&trip=<?= (int)$t['tripID'] ?>">Join</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
</div>

<script src="<?= h($base) ?>js/validation.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
