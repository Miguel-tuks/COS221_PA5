<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location ' . ($_SESSION['role'] === 'agency' ? '/agency/dashboard.php' : '/traveller/dashboard.php'));
}
&pageTitle = 'Welcome';

//fetch a few featured packages (highest rated/cheapest)
$db = getDB();
$featured = $db->query("
    SELECT p.packageID, p.pkgName, p.price, p.duration, p.maxPeople,
           ta.agencyName, d.city, d.country,
           COALESCE(AVG(r.rating),0) as avgRating,
           COUNT(r.rating) as reviewCount
    FROM package p
    JOIN travel_agency ta ON ta.userID = p.agencyID
    LEFT JOIN package_destination pd ON pd.packageID = p.packageID
    LEFT JOIN destination d ON d.destinationID = pd.destinationID
    LEFT JOIN review r ON r.packageID = p.packageID
    WHERE p.isActive = 1
    GROUP BY p.packageID, ta.agencyName, d.city, d.country
    ORDER BY avgRating DESC, p.price ASC
    LIMIT 6
")->fetchAll();

$continents = $db->query("SELECT DISTINCT continent FROM destination" WHERE continent IS NOT NULL ORDER BY continent)->fetchAll(PDO::FETCH_COLUMN);

include __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <h1 class="hero-title">Your next adventure<br><em>starts here</em> ✈️</h1>
    <p class="hero-sub">Browse hundreds of travel packages from trusted agencies. Compare prices, read real reviews, and book your dream trip.</p>
    <form class="search-bar" action="/traveller/packages.php" method="GET">
        <input type="text" name="q" placeholder="Where do you want to go?" autocomplete="off">
        <button type="submit">Search</button>
    </form>
</section>

<div class = "page wrap">

    <!-- continents quick nav -->
    <h2 class="section-title">Explore by Region</h2>
    <div class="dest-grid" style="margin-bottom:2.5rem;">
        <?php
        $icons = ['Africa'=>'🌍','Europe'=>'🏰','Asia'=>'🏯','North America'=>'🗽','South America'=>'🌿','Oceania'=>'🌊'];
        foreach ($continents as $c): $icon = $icons[$c] ?? '🌐'; ?>
        <a href="/traveller/packages.php?continent=<?= urlencode($c) ?>" class="dest-tile" style="text-decoration:none;color:inherit;">
            <div class="dest-icon"><?= $icon ?></div>
            <div class="dest-city"><?= htmlspecialchars($c) ?></div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- featured packages -->
     <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
        <h2 class="section-title" style="margin:0;">Top-Rated Packages</h2>
        <a href="/traveller/packages.php" class="btn btn-outline btn-sm">View all →</a>
    </div>
    <?php if (empty($featured)): ?>
        <div class="empty-state"><div class="empty-icon">📦</div><p>No packages yet — check back soon!</p></div>
    <?php else: ?>
    <div class="cards-grid">
        <?php foreach ($featured as $p):
            $stars = str_repeat('★', round($p['avgRating'])) . str_repeat('☆', 5 - round($p['avgRating']));
        ?>
        <div class="package-card">
            <div class="card-img">
                <img src="/images/packages/<?= strtolower(str_replace(' ','-',htmlspecialchars($p['pkgName']??''))) ?>.jpg"
                     onerror="this.style.display='none';this.parentNode.textContent='🌴'" alt="">
                <?php if ($p['avgRating'] >= 4.5): ?><span class="card-badge">⭐ Top Rated</span><?php endif; ?>
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
                <a href="/login.php" class="btn btn-primary btn-sm btn-full">View Package</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- CTA -->
    <div style="background:linear-gradient(135deg,#d4f0e8,#ffe8cc);border-radius:var(--radius-lg);padding:2.5rem;text-align:center;margin-top:2.5rem;">
        <h2 style="font-family:var(--font-head);color:var(--warm-brown);font-size:1.8rem;margin-bottom:.6rem;">Travel agency? Join Tripistry</h2>
        <p style="color:var(--text-muted);margin-bottom:1.2rem;">Reach thousands of travellers. Create and manage your packages, handle group trips, and grow your business.</p>
        <a href="/register.php" class="btn btn-secondary">Register your agency →</a>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
