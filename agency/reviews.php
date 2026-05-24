<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('Travel Agency');

$pageTitle = 'Reviews';
$pdo = getDB();
$uid = currentUserId();

$summary = $pdo->prepare(
    "SELECT p.packageID, p.pkgName,
            COUNT(r.rating)              AS num_reviews,
            COALESCE(AVG(r.rating), 0)   AS avg_rating
     FROM PACKAGE p
     LEFT JOIN REVIEW r ON r.packageID = p.packageID
     WHERE p.agencyID = :a
     GROUP BY p.packageID, p.pkgName
     ORDER BY avg_rating DESC, p.pkgName"
);
$summary->execute([':a' => $uid]);
$summary = $summary->fetchAll();

$overall = $pdo->prepare(
    "SELECT COUNT(r.rating) AS num, COALESCE(AVG(r.rating), 0) AS avg_rating
     FROM REVIEW r
     JOIN PACKAGE p ON p.packageID = r.packageID
     WHERE p.agencyID = :a"
);
$overall->execute([':a' => $uid]);
$overall = $overall->fetch();

$reviews = $pdo->prepare(
    "SELECT r.*, p.pkgName, t.firstName, t.lastName
     FROM REVIEW r
     JOIN PACKAGE   p ON p.packageID = r.packageID
     JOIN TRAVELLER t ON t.userID    = r.travellerID
     WHERE p.agencyID = :a
     ORDER BY r.reviewDate DESC"
);
$reviews->execute([':a' => $uid]);
$reviews = $reviews->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<h1>Reviews</h1>

<div class="stats">
    <div class="stat">
        <div class="stat-num"><?= (int)$overall['num'] ?></div>
        <div class="stat-lbl">Total reviews</div>
    </div>
    <div class="stat">
        <div class="stat-num"><?= number_format($overall['avg_rating'], 1) ?></div>
        <div class="stat-lbl">Avg rating</div>
    </div>
</div>

<div class="section">
    <h2>Per package</h2>
    <?php if (!$summary): ?>
        <p>No packages yet.</p>
    <?php else: ?>
    <div class="table-wrap"><table class="data">
        <tr><th>Package</th><th>Reviews</th><th>Avg rating</th></tr>
        <?php foreach ($summary as $s): ?>
        <tr>
            <td><?= h($s['pkgName']) ?></td>
            <td><?= (int)$s['num_reviews'] ?></td>
            <td><?= $s['num_reviews'] > 0 ? renderStars($s['avg_rating']) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
</div>

<div class="section">
    <h2>All reviews</h2>
    <?php if (!$reviews): ?>
        <p>No reviews yet.</p>
    <?php else: foreach ($reviews as $r): ?>
        <div class="review">
            <div class="meta">
                <strong><?= h($r['firstName']) ?> <?= h(substr($r['lastName'], 0, 1)) ?>.</strong>
                · <?= h($r['pkgName']) ?>
                · <?= h(date('d M Y', strtotime($r['reviewDate']))) ?>
            </div>
            <div><?= renderStars($r['rating']) ?></div>
            <?php if ($r['comment']): ?>
                <p><?= nl2br(h($r['comment'])) ?></p>
            <?php endif; ?>
        </div>
    <?php endforeach; endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
