<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('Traveller');
$pdo = getDB();
$uid = currentUserId();
$pid = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT p.*, ta.agencyName, ta.description AS agencyDesc, ta.website AS agencyWebsite
     FROM PACKAGE p
     JOIN TRAVEL_AGENCY ta ON ta.userID = p.agencyID
     WHERE p.packageID = :p"
);
$stmt->execute([':p' => $pid]);
$pkg = $stmt->fetch();
if (!$pkg) {
    setFlash('error', 'Package not found.');
    header('Location: packages.php');
    exit;
}

$pageTitle = $pkg['pkgName'];

$stmt = $pdo->prepare('SELECT image FROM PACKAGE_IMAGE WHERE packageID = :p');
$stmt->execute([':p' => $pid]);
$images = $stmt->fetchAll(PDO::FETCH_COLUMN);
$mainImage = $images[0] ?? 'https://placehold.co/1200x400?text=Tripistry';

$stmt = $pdo->prepare(
    "SELECT d.* FROM PACKAGE_DESTINATION pd
     JOIN DESTINATION d ON d.destinationID = pd.destinationID
     WHERE pd.packageID = :p"
);
$stmt->execute([':p' => $pid]);
$destinations = $stmt->fetchAll();
$destIds = array_column($destinations, 'destinationID');

$stmt = $pdo->prepare(
    "SELECT f.*, d.city, d.country FROM PACKAGE_FLIGHT pf
     JOIN FLIGHT      f ON f.flightID      = pf.flightID
     JOIN DESTINATION d ON d.destinationID = f.destinationID
     WHERE pf.packageID = :p"
);
$stmt->execute([':p' => $pid]);
$flights = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT a.*, d.city, d.country FROM PACKAGE_ACCOMMODATION pa
     JOIN ACCOMMODATION a ON a.accommodationID = pa.accommodationID
     JOIN DESTINATION   d ON d.destinationID   = a.destinationID
     WHERE pa.packageID = :p"
);
$stmt->execute([':p' => $pid]);
$accommodations = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT a.* FROM PACKAGE_ATTRACTION pat
     JOIN TOURIST_ATTRACTION a ON a.attractionID = pat.attractionID
     WHERE pat.packageID = :p"
);
$stmt->execute([':p' => $pid]);
$attractions = $stmt->fetchAll();

$restaurants = [];
if ($destIds) {
    $placeholders = implode(',', array_fill(0, count($destIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT r.*, d.city, d.country FROM RESTAURANT r
         JOIN DESTINATION d ON d.destinationID = r.destinationID
         WHERE r.destinationID IN ($placeholders)"
    );
    $stmt->execute($destIds);
    $restaurants = $stmt->fetchAll();
}

$stmt = $pdo->prepare(
    "SELECT r.*, t.firstName, t.lastName FROM REVIEW r
     JOIN TRAVELLER t ON t.userID = r.travellerID
     WHERE r.packageID = :p
     ORDER BY r.reviewDate DESC"
);
$stmt->execute([':p' => $pid]);
$reviews = $stmt->fetchAll();
$avgRating = 0;
if ($reviews) {
    $sum = 0;
    foreach ($reviews as $r) $sum += (int)$r['rating'];
    $avgRating = $sum / count($reviews);
}

$stmt = $pdo->prepare(
    'SELECT * FROM BOOKING WHERE travellerID = :t AND packageID = :p'
);
$stmt->execute([':t' => $uid, ':p' => $pid]);
$existingBooking = $stmt->fetch();

$stmt = $pdo->prepare(
    "SELECT gt.*, (SELECT COUNT(*) FROM JOINS WHERE packageID = gt.packageID AND tripID = gt.tripID) AS members
     FROM GROUP_TRIP gt
     WHERE gt.packageID = :p
     ORDER BY gt.departDate ASC"
);
$stmt->execute([':p' => $pid]);
$groupTrips = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<div class="detail-hero">
    <img src="<?= h($mainImage) ?>" alt="<?= h($pkg['pkgName']) ?>">
    <div class="overlay">
        <h1><?= h($pkg['pkgName']) ?></h1>
        <div><?= renderStars($avgRating) ?> · <?= count($reviews) ?> review(s)</div>
        <div class="price-big">R<?= number_format($pkg['price'], 2) ?></div>
    </div>
</div>

<div class="two-col" style="margin-top:24px">
    <div>
        <h2>About this package</h2>
        <p><?= nl2br(h($pkg['description'])) ?></p>
        <p class="meta">
            <strong><?= (int)$pkg['duration'] ?> days</strong> · Up to <?= (int)$pkg['maxPeople'] ?> travellers per booking ·
            Guided by <?= h($pkg['guide']) ?> (<?= h($pkg['guideNum']) ?>)
        </p>

        <?php if (count($images) > 1): ?>
        <h2>Gallery</h2>
        <div class="gallery">
            <?php foreach ($images as $img): ?>
                <img src="<?= h($img) ?>" alt="">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <h2>Destinations</h2>
        <ul>
            <?php foreach ($destinations as $d): ?>
                <li><?= h(destLabel($d)) ?><?= $d['continent'] ? ' (' . h($d['continent']) . ')' : '' ?></li>
            <?php endforeach; ?>
        </ul>

        <?php if ($flights): ?>
        <h2>Flights included</h2>
        <div class="table-wrap"><table class="data">
            <tr><th>Airline</th><th>Flight #</th><th>From</th><th>To</th><th>Departs</th><th>Arrives</th></tr>
            <?php foreach ($flights as $f): ?>
            <tr>
                <td><?= h($f['airline']) ?></td>
                <td><?= h($f['flightNumber']) ?></td>
                <td><?= h($f['origin']) ?></td>
                <td><?= h($f['city']) ?>, <?= h($f['country']) ?></td>
                <td><?= h(date('d M Y H:i', strtotime($f['departure']))) ?></td>
                <td><?= h(date('d M Y H:i', strtotime($f['arrival']))) ?></td>
            </tr>
            <?php endforeach; ?>
        </table></div>
        <?php endif; ?>

        <?php if ($accommodations): ?>
        <h2>Stays</h2>
        <div class="cards">
            <?php foreach ($accommodations as $a): ?>
            <div class="card">
                <div class="card-body">
                    <h3><?= h($a['accName']) ?></h3>
                    <div class="meta"><?= h($a['type']) ?> · <?= h($a['city']) ?>, <?= h($a['country']) ?></div>
                    <div class="meta"><?= renderStars($a['rating']) ?></div>
                    <?php if ($a['address']): ?><div class="meta"><?= h($a['address']) ?></div><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($attractions): ?>
        <h2>Attractions</h2>
        <ul>
            <?php foreach ($attractions as $a): ?>
                <li><strong><?= h($a['name']) ?></strong>
                    <?= $a['category'] ? '· ' . h($a['category']) : '' ?>
                    <?= $a['entranceFee'] > 0
                        ? '· entrance fee R' . number_format($a['entranceFee'], 2)
                        : '· free entry' ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <?php if ($restaurants): ?>
        <h2>Recommended restaurants</h2>
        <div class="table-wrap"><table class="data">
            <tr><th>Name</th><th>Cuisine</th><th>Price</th><th>Location</th></tr>
            <?php foreach ($restaurants as $r): ?>
            <tr>
                <td><?= h($r['name']) ?></td>
                <td><?= h($r['cuisine']) ?></td>
                <td><?= h($r['priceRange']) ?></td>
                <td><?= h($r['city']) ?>, <?= h($r['country']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table></div>
        <?php endif; ?>

        <?php if ($groupTrips): ?>
        <h2>Available group trips</h2>
        <p class="meta">Join a scheduled group instead of booking on your own.</p>
        <div class="table-wrap"><table class="data">
            <tr><th>Trip</th><th>Departs</th><th>Returns</th><th>Members</th><th>Status</th><th></th></tr>
            <?php foreach ($groupTrips as $gt):
                $isFull = (int)$gt['members'] >= (int)$gt['maxMembers'];
            ?>
            <tr>
                <td>#<?= (int)$gt['tripID'] ?></td>
                <td><?= h(date('d M Y', strtotime($gt['departDate']))) ?></td>
                <td><?= h(date('d M Y', strtotime($gt['returnDate']))) ?></td>
                <td><?= (int)$gt['members'] ?> / <?= (int)$gt['maxMembers'] ?></td>
                <td><span class="status status-<?= h($gt['tripStatus']) ?>"><?= h(ucfirst($gt['tripStatus'])) ?></span></td>
                <td>
                    <?php if ($isFull): ?>
                        <em>Full</em>
                    <?php elseif ($gt['tripStatus'] !== 'open'): ?>
                        <em>Closed</em>
                    <?php else: ?>
                        <a class="btn btn-primary"
                           href="group_trips.php?join=1&pkg=<?= (int)$gt['packageID'] ?>&trip=<?= (int)$gt['tripID'] ?>">Join</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table></div>
        <?php endif; ?>

        <h2>Reviews</h2>
        <?php if (!$reviews): ?>
            <p>No reviews yet.</p>
        <?php else: foreach ($reviews as $r): ?>
            <div class="review">
                <div class="meta">
                    <strong><?= h($r['firstName']) ?> <?= h(substr($r['lastName'],0,1)) ?>.</strong>
                    · <?= h(date('d M Y', strtotime($r['reviewDate']))) ?>
                </div>
                <div><?= renderStars($r['rating']) ?></div>
                <?php if ($r['comment']): ?><p><?= nl2br(h($r['comment'])) ?></p><?php endif; ?>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <aside>
        <div class="card-block">
            <h3 style="margin-top:0">Travel agency</h3>
            <p><strong><?= h($pkg['agencyName']) ?></strong></p>
            <?php if ($pkg['agencyDesc']): ?><p class="meta"><?= h($pkg['agencyDesc']) ?></p><?php endif; ?>
            <?php if ($pkg['agencyWebsite']): ?>
                <p><a href="<?= h($pkg['agencyWebsite']) ?>" target="_blank" rel="noopener">Visit website ↗</a></p>
            <?php endif; ?>
        </div>

        <div class="card-block">
            <h3 style="margin-top:0">Book this package</h3>
            <p class="price-big">R<?= number_format($pkg['price'], 2) ?> <small style="font-size:13px;color:#64748b">per person</small></p>
            <?php if ($existingBooking): ?>
                <p>You've already booked this package.
                   Status: <span class="status status-<?= h($existingBooking['status']) ?>">
                       <?= h(ucfirst($existingBooking['status'])) ?></span></p>
                <a class="btn btn-secondary" href="bookings.php">View my bookings</a>
            <?php else: ?>
                <a class="btn btn-primary" href="book.php?id=<?= (int)$pid ?>">Book now</a>
            <?php endif; ?>
        </div>
    </aside>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
