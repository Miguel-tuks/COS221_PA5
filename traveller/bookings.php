<?php

require_once __DIR__ . '/../includes/auth.php';
requireRole('Traveller');

$pageTitle = 'My bookings';
$pdo = getDB();
$uid = currentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'cancel' && isset($_POST['package_id'])) {
        $pid = (int)$_POST['package_id'];
        $stmt = $pdo->prepare(
            "UPDATE BOOKING SET status = 'cancelled'
             WHERE travellerID = :t AND packageID = :p"
        );
        $stmt->execute([':t' => $uid, ':p' => $pid]);
        setFlash('success', 'Booking cancelled.');
        header('Location: bookings.php');
        exit;
    }

    if ($_POST['action'] === 'review' && isset($_POST['package_id'])) {
        $pid     = (int)$_POST['package_id'];
        $rating  = (int)($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');

        $stmt = $pdo->prepare(
            'SELECT 1 FROM BOOKING WHERE travellerID = :t AND packageID = :p'
        );
        $stmt->execute([':t' => $uid, ':p' => $pid]);
        $hasBooked = (bool)$stmt->fetchColumn();

        if (!$hasBooked) {
            setFlash('error', 'You can only review packages you have booked.');
        } elseif ($rating < 1 || $rating > 5) {
            setFlash('error', 'Please pick a rating from 1 to 5.');
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO REVIEW (travellerID, packageID, rating, comment, reviewDate)
                 VALUES (:t, :p, :r, :c, CURDATE())
                 ON DUPLICATE KEY UPDATE rating = :r2, comment = :c2, reviewDate = CURDATE()"
            );
            $stmt->execute([
                ':t' => $uid, ':p' => $pid,
                ':r' => $rating, ':c' => $comment,
                ':r2' => $rating, ':c2' => $comment
            ]);
            setFlash('success', 'Thanks — your review has been saved.');
        }
        header('Location: bookings.php');
        exit;
    }
}

$stmt = $pdo->prepare(
    "SELECT b.*, p.pkgName, p.price, p.duration, ta.agencyName,
            (SELECT image FROM PACKAGE_IMAGE WHERE packageID = p.packageID LIMIT 1) AS image,
            (SELECT GROUP_CONCAT(DISTINCT CONCAT(d.city, ', ', d.country) SEPARATOR ' • ')
             FROM PACKAGE_DESTINATION pd JOIN DESTINATION d ON d.destinationID = pd.destinationID
             WHERE pd.packageID = p.packageID) AS destinations,
            r.rating AS my_rating, r.comment AS my_comment
     FROM BOOKING b
     JOIN PACKAGE       p  ON p.packageID = b.packageID
     JOIN TRAVEL_AGENCY ta ON ta.userID   = p.agencyID
     LEFT JOIN REVIEW   r  ON r.packageID = b.packageID AND r.travellerID = b.travellerID
     WHERE b.travellerID = :u
     ORDER BY b.bookingDate DESC"
);
$stmt->execute([':u' => $uid]);
$bookings = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<h1>My bookings</h1>

<?php if (!$bookings): ?>
    <p>You haven't booked anything yet. <a href="packages.php">Browse packages</a>.</p>
<?php else: ?>

    <?php foreach ($bookings as $b): ?>
    <div class="card-block">
        <div class="two-col">
            <div>
                <h2 style="margin-top:0"><?= h($b['pkgName']) ?></h2>
                <div class="meta"><?= h($b['destinations']) ?> · by <?= h($b['agencyName']) ?></div>
                <p>
                    Booked on <?= h(date('d M Y', strtotime($b['bookingDate']))) ?><br>
                    <?= (int)$b['numPax'] ?> traveller(s) ·
                    Total: <strong>R<?= number_format($b['price'] * (int)$b['numPax'], 2) ?></strong><br>
                    Status: <span class="status status-<?= h($b['status']) ?>"><?= h(ucfirst($b['status'])) ?></span>
                </p>

                <?php if ($b['status'] !== 'cancelled'): ?>
                <form method="post" style="display:inline">
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="package_id" value="<?= (int)$b['packageID'] ?>">
                    <button type="submit" class="btn btn-danger"
                            data-confirm="Cancel this booking?">Cancel booking</button>
                </form>
                <?php endif; ?>
                <a class="btn btn-secondary"
                   href="package_detail.php?id=<?= (int)$b['packageID'] ?>">View package</a>
            </div>

            <div>
                <h3>Leave a review</h3>
                <?php if ($b['my_rating']): ?>
                    <p class="meta">You already reviewed this package — feel free to update it.</p>
                <?php endif; ?>
                <form method="post" data-validate-form>
                    <input type="hidden" name="action" value="review">
                    <input type="hidden" name="package_id" value="<?= (int)$b['packageID'] ?>">

                    <div class="field">
                        <label>Rating</label>
                        <select name="rating" data-validate="required">
                            <option value="">Choose 1–5</option>
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <option value="<?= $i ?>"
                                    <?= ((int)$b['my_rating'] === $i) ? 'selected' : '' ?>>
                                    <?= str_repeat('★', $i) ?> (<?= $i ?>)
                                </option>
                            <?php endfor; ?>
                        </select>
                        <div class="error"></div>
                    </div>
                    <div class="field">
                        <label>Comment</label>
                        <textarea name="comment" rows="3"><?= h($b['my_comment'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <?= $b['my_rating'] ? 'Update review' : 'Submit review' ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

<?php endif; ?>

<script src="<?= h($base) ?>js/validation.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
