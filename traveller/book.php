<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('Traveller');
$pageTitle = 'Book package';
$pdo = getDB();
$uid = currentUserId();
$pid = (int)($_GET['id'] ?? $_POST['package_id'] ?? 0);
$errors = [];
$stmt = $pdo->prepare(
    "SELECT p.*, ta.agencyName,
            (SELECT image FROM PACKAGE_IMAGE WHERE packageID = p.packageID LIMIT 1) AS image
     FROM PACKAGE p JOIN TRAVEL_AGENCY ta ON ta.userID = p.agencyID
     WHERE p.packageID = :p"
);
$stmt->execute([':p' => $pid]);
$pkg = $stmt->fetch();
if (!$pkg) {
    setFlash('error', 'Package not found.');
    header('Location: packages.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM BOOKING WHERE travellerID = :t AND packageID = :p');
$stmt->execute([':t' => $uid, ':p' => $pid]);
if ($stmt->fetch()) {
    setFlash('info', 'You have already booked this package.');
    header('Location: bookings.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numPax = (int)($_POST['num_pax'] ?? 1);

    if ($numPax < 1) {
        $errors['num_pax'] = 'At least 1 traveller is required.';
    } elseif ($numPax > (int)$pkg['maxPeople']) {
        $errors['num_pax'] = 'This package allows a maximum of ' . (int)$pkg['maxPeople'] . ' people.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            "INSERT INTO BOOKING (travellerID, packageID, bookingDate, status, numPax)
             VALUES (:t, :p, CURDATE(), 'confirmed', :n)"
        );
        $stmt->execute([':t' => $uid, ':p' => $pid, ':n' => $numPax]);

        setFlash('success', 'Booking confirmed! Have a great trip.');
        header('Location: bookings.php');
        exit;
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="form-card">
    <h1>Book this package</h1>

    <div style="display:flex;gap:12px;align-items:center;margin-bottom:20px">
        <img src="<?= h($pkg['image'] ?: 'https://placehold.co/120x80?text=Tripistry') ?>"
             style="width:120px;height:80px;object-fit:cover;border-radius:6px">
        <div>
            <strong><?= h($pkg['pkgName']) ?></strong><br>
            <span class="meta">by <?= h($pkg['agencyName']) ?> · <?= (int)$pkg['duration'] ?> days</span>
        </div>
    </div>

    <form method="post" data-validate-form>
        <input type="hidden" name="package_id" value="<?= (int)$pid ?>">

        <div class="field <?= isset($errors['num_pax']) ? 'has-error' : '' ?>">
            <label>Number of travellers</label>
            <input type="number" name="num_pax" min="1" max="<?= (int)$pkg['maxPeople'] ?>"
                   value="<?= h($_POST['num_pax'] ?? 1) ?>" data-validate="required|number">
            <div class="error"><?= h($errors['num_pax'] ?? '') ?></div>
            <small class="meta">Max <?= (int)$pkg['maxPeople'] ?> per booking</small>
        </div>

        <p>Total:
            <strong>R<?= number_format($pkg['price'] * (int)($_POST['num_pax'] ?? 1), 2) ?></strong>
            (R<?= number_format($pkg['price'], 2) ?> × pax)
        </p>

        <button type="submit" class="btn btn-primary">Confirm booking</button>
        <a class="btn btn-secondary" href="package_detail.php?id=<?= (int)$pid ?>">Cancel</a>
    </form>
</div>

<script src="<?= h($base) ?>js/validation.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
