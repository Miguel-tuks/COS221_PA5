<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireTraveller();

$db = getDB();
$uid = (int)$_SESSION['userID'];

//traveller info
$traveller = $db->prepare('SELECT firstName, lastName FROM traveller WHERE userID = ?');
$traveller->execute([$uid]);
$me = $traveller->fetch();

//my bookings count
$bookCount = $db->prepare('SELECT COUNT(*) FROM booking WHERE travellerID = ?');
$bookCount->execute([$uid]);
$myBookings = $bookCount->fetchColumn();

//my reviews count
$revCount = $db->prepare('SELECT COUNT(*) FROM review WHERE travellerID = ?');
$revCount->execute([$uid]);
$myReviews = $revCount->fetchColumn();

//featured / top rated packages
$featured = $db->query("
    SELECT p.packageID, p.pkgName, p.price, p.duration,
           ta.agencyName,
           d.city, d.country,
           COALESCE(AVG(r.rating),0) as avgRating,
           COUNT(DISTINCT r.travellerID) as reviewCount
    FROM package p
    JOIN travel_agency ta ON ta.userID = p.agencyID
    LEFT JOIN package_destination pd ON pd.packageID = p.packageID
    LEFT JOIN destination d ON d.destinationID = pd.destinationID
    LEFT JOIN review r ON r.packageID = p.packageID
    WHERE p.isActive = 1
    GROUP BY p.packageID, ta.agencyName, d.city, d.country
    ORDER BY avgRating DESC, p.price ASC
    LIMIT 4
")->fetchAll();

//recent bookings
$recent = $db->prepare("
    SELECT b.bookingDate, b.status, b.numPax,
           p.packageID, p.pkgName, p.price,
           ta.agencyName
    FROM booking b
    JOIN package p ON p.packageID = b.packageID
    JOIN travel_agency ta ON ta.userID = p.agencyID
    WHERE b.travellerID = ?
    ORDER BY b.bookingDate DESC
    LIMIT 5
");

$recent->execute([$uid]);
$recent = $recent->fetchAll();

$pageTitle = 'Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-wrap">
    <h1 class="section-title">Hey, <?= htmlspecialchars($me['firstName'] ?? 'Traveller') ?> 👋</h1>
    <p class="section-subtitle">Ready to plan your next adventure?</p>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon">🧳</div>
            <div class="stat-num"><?= $myBookings ?></div>
            <div class="stat-label">My Bookings</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⭐</div>
            <div class="stat-num"><?= $myReviews ?></div>
            <div class="stat-label">Reviews Left</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🌍</div>
            <div class="stat-num">50+</div>
            <div class="stat-label">Destinations</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🏢</div>
            <div class="stat-num">50+</div>
            <div class="stat-label">Travel Agencies</div>
        </div>
    </div>

    <!--quick actions-->
    <div style="display:flex;gap:.8rem;flex-wrap:wrap;margin-bottom:2rem;">
        <a href="/traveller/packages.php" class="btn btn-primary">🔍 Browse Packages</a>
        <a href="/traveller/compare.php" class="btn btn-outline">⚖️ Compare</a>
        <a href="/traveller/browse.php" class="btn btn-ghost">🗺 Explore Destinations</a>
        <a href="/traveller/my_bookings.php" class="btn btn-ghost">🧳 My Bookings</a>
    </div>

    <!-- recent bookings -->
    <?php if (!empty($recentBookings)): ?>
    <div class="section-block">
        <h3>Recent Bookings</h3>
        <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Package</th><th>Agency</th><th>Date</th><th>Pax</th><th>Total</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($recentBookings as $b):
                $statusClass = ['Confirmed'=>'badge-green','Pending'=>'badge-orange','Cancelled'=>'badge-red','Completed'=>'badge-teal'][$b['status']] ?? 'badge-grey';
            ?>
            <tr>
                <td><a href="/traveller/package_detail.php?id=<?= $b['packageID'] ?>"><?= htmlspecialchars($b['pkgName']) ?></a></td>
                <td><?= htmlspecialchars($b['agencyName']) ?></td>
                <td><?= htmlspecialchars($b['bookingDate']) ?></td>
                <td><?= $b['numPax'] ?></td>
                <td><strong>R <?= number_format($b['price'] * $b['numPax'], 2) ?></strong></td>
                <td><span class="badge <?= $statusClass ?>"><?= $b['status'] ?></span></td>
                <td><a href="/traveller/package_detail.php?id=<?= $b['packageID'] ?>" class="btn btn-ghost btn-sm">View</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <div style="margin-top:.8rem;"><a href="/traveller/my_bookings.php" class="btn btn-outline btn-sm">All bookings →</a></div>
    </div>
    <?php endif; ?>

    <!-- featured packages -->
    <h2 class="section-title" style="margin-top:1rem;">Top-Rated Packages</h2>
    <div class="cards-grid">
        <?php foreach ($featured as $p):
            $stars = str_repeat('★', round($p['avgRating'])) . str_repeat('☆', 5-round($p['avgRating']));
        ?>
        <div class="package-card">
            <div class="card-img">
                <img src="/images/packages/<?= strtolower(str_replace([' ','\''],'-',$p['pkgName'])) ?>.jpg"
                     onerror="this.style.display='none';this.parentNode.innerHTML='🌴'" alt="">
                <?php if ($p['avgRating']>=4.5): ?><span class="card-badge">⭐ Top Rated</span><?php endif; ?>
            </div>
            <div class="card-body">
                <div class="card-title"><?= htmlspecialchars($p['pkgName']) ?></div>
                <div class="card-agency">by <?= htmlspecialchars($p['agencyName']) ?></div>
                <div class="card-meta">
                    <?php if ($p['city']): ?><span class="meta-tag">📍 <?= htmlspecialchars($p['city']) ?></span><?php endif; ?>
                    <?php if ($p['duration']): ?><span class="meta-tag">🗓 <?= $p['duration'] ?> days</span><?php endif; ?>
                </div>
                <div style="font-size:.85rem;color:#f0a500;"><?= $stars ?> <span style="color:var(--text-muted);">(<?= $p['reviewCount'] ?>)</span></div>
                <div class="card-price">R <?= number_format($p['price'],2) ?> <span>/ person</span></div>
            </div>
            <div class="card-actions">
                <a href="/traveller/package_detail.php?id=<?= $p['packageID'] ?>" class="btn btn-primary btn-sm btn-full">View Details</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
