<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAgency();

$db = getDB();
$uid = (int)$_SESSION['userID'];

$agency = $db->prepare('SELECT * FROM travel_agency WHERE userID = ?');
$agency->execute([$uid]);
$me = $agency->fetch();

//stats
$pkgCount = $db->prepare ('SELECT COUNT(*) FROM package WHERE agencyID = ? AND isActive = 1');
$pkgCount->execute([$uid]);
$totalPackages = $pkgCount->fetchColumn();

$bookCount = $db->prepare('SELECT COUNT(*) FROM booking JOIN package p ON p.packageID = b.packageID WHERE p.agencyID = ?');
$bookCount->execute([$uid]);
$totalBookings = $bookCount->fetchColumn();

$revStat = $db->prepare('SELECT COALESCE(AVG(r.rating), 0), COUNT(r.rating) FROM review r JOIN package p ON p.packageID = r.packageID WHERE p.agencyID = ?');
$revStat->execute([$uid]);
$totalGroups = $groupCount->fetchColumn();

//recent booking for this agency
$recentBookings = $db->prepare("
    SELECT b.bookingDate, b.status, b.numPax, b.travellerID,
           p.pkgName, p.price,
           t.firstName, t.lastName
    FROM booking b
    JOIN package p ON p.packageID=b.packageID
    JOIN traveller t ON t.userID=b.travellerID
    WHERE p.agencyID=?
    ORDER BY b.bookingDate DESC LIMIT 8
");
$recentBookings->execute([$uid]);
$recentBookings = $recentBookings->fetchAll();

$pageTitle = 'Agency Dashboard';
include __DIR__ . '/../includes/header.php';

?>

<div class="page-wrap">
    <h1 class="section-title">Welcome back, <?= htmlspecialchars($me['agencyName']) ?> 🏢</h1>
    <p class="section-subtitle">Here's an overview of your agency's performance.</p>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon">📦</div>
            <div class="stat-num"><?= $totalPackages ?></div>
            <div class="stat-label">Active Packages</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🧳</div>
            <div class="stat-num"><?= $totalBookings ?></div>
            <div class="stat-label">Total Bookings</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⭐</div>
            <div class="stat-num"><?= round($avgRating,1) ?></div>
            <div class="stat-label">Avg Rating (<?= $totalReviews ?> reviews)</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-num"><?= $totalGroups ?></div>
            <div class="stat-label">Group Trips</div>
        </div>
    </div>

    <!--quick actions-->
    <div style="display:flex;gap:.8rem;flex-wrap:wrap;margin-bottom:2rem;">
        <a href="/agency/package_form.php" class="btn btn-primary">+ New Package</a>
        <a href="/agency/packages.php" class="btn btn-outline">📦 Manage Packages</a>
        <a href="/agency/group_trips.php" class="btn btn-ghost">👥 Group Trips</a>
        <a href="/agency/manage_items.php" class="btn btn-ghost">🗂 Manage Items</a>
        <a href="/agency/reviews.php" class="btn btn-ghost">⭐ Reviews</a>
    </div>

    <!-- recent bookings -->
    <div class="section-block">
        <h3>Recent Bookings</h3>
        <?php if (empty($recentBookings)): ?>
        <div class="empty-state"><div class="empty-icon">📭</div><p>No bookings yet.</p></div>
        <?php else: ?>
        <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Traveller</th><th>Package</th><th>Date</th><th>Pax</th><th>Revenue</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($recentBookings as $b):
                $statusClass = ['Confirmed'=>'badge-green','Pending'=>'badge-orange','Cancelled'=>'badge-red','Completed'=>'badge-teal'][$b['status']] ?? 'badge-grey';
            ?>
            <tr>
                <td><?= htmlspecialchars($b['firstName'].' '.$b['lastName']) ?></td>
                <td><?= htmlspecialchars($b['pkgName']) ?></td>
                <td><?= htmlspecialchars($b['bookingDate']) ?></td>
                <td><?= $b['numPax'] ?></td>
                <td><strong>R <?= number_format($b['price']*$b['numPax'],2) ?></strong></td>
                <td><span class="badge <?= $statusClass ?>"><?= $b['status'] ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
