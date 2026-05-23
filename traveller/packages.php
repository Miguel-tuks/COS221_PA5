<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireTraveller();

$db = getDB();

// Filter parameters (sanitised)
$q         = trim($_GET['q'] ?? '');
$continent = trim($_GET['continent'] ?? '');
$country   = trim($_GET['country'] ?? '');
$minPrice  = isset($_GET['minPrice']) && is_numeric($_GET['minPrice']) ? (float)$_GET['minPrice'] : null;
$maxPrice  = isset($_GET['maxPrice']) && is_numeric($_GET['maxPrice']) ? (float)$_GET['maxPrice'] : null;
$minDur    = isset($_GET['minDur']) && is_numeric($_GET['minDur']) ? (int)$_GET['minDur'] : null;
$maxDur    = isset($_GET['maxDur']) && is_numeric($_GET['maxDur']) ? (int)$_GET['maxDur'] : null;
$sortBy    = in_array($_GET['sort']??'', ['price_asc','price_desc','rating','duration_asc']) ? $_GET['sort'] : 'rating';
$page      = max(1, (int)($_GET['page']??1));
$perPage   = 9;
$offset    = ($page-1)*$perPage;

// Build WHERE clauses
$where = ['p.isActive = 1'];
$params = [];

if ($q) {
    $where[] = "(p.pkgName LIKE ? OR d.city LIKE ? OR d.country LIKE ? OR ta.agencyName LIKE ?)";
    $params = array_merge($params, ["%$q%","%$q%","%$q%","%$q%"]);
}
if ($continent) { $where[] = "d.continent = ?"; $params[] = $continent; }
if ($country)   { $where[] = "d.country = ?";   $params[] = $country; }
if ($minPrice !== null) { $where[] = "p.price >= ?"; $params[] = $minPrice; }
if ($maxPrice !== null) { $where[] = "p.price <= ?"; $params[] = $maxPrice; }
if ($minDur !== null)   { $where[] = "p.duration >= ?"; $params[] = $minDur; }
if ($maxDur !== null)   { $where[] = "p.duration <= ?"; $params[] = $maxDur; }

$whereSQL = implode(' AND ', $where);
$orderSQL = match($sortBy) {
    'price_asc'    => 'p.price ASC',
    'price_desc'   => 'p.price DESC',
    'duration_asc' => 'p.duration ASC',
    default        => 'avgRating DESC',
};

$baseSQL = "
    FROM package p
    JOIN travel_agency ta ON ta.userID = p.agencyID
    LEFT JOIN package_destination pd ON pd.packageID = p.packageID
    LEFT JOIN destination d ON d.destinationID = pd.destinationID
    LEFT JOIN review r ON r.packageID = p.packageID
    WHERE $whereSQL
    GROUP BY p.packageID, ta.agencyName, d.city, d.country, d.continent
";

$countStmt = $db->prepare("SELECT COUNT(*) FROM (SELECT p.packageID $baseSQL) sub");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = ceil($total / $perPage);

$stmt = $db->prepare("
    SELECT p.packageID, p.pkgName, p.price, p.duration, p.maxPeople, p.description,
           ta.agencyName, d.city, d.country, d.continent,
           COALESCE(AVG(r.rating),0) as avgRating, COUNT(DISTINCT r.travellerID) as reviewCount
    $baseSQL
    ORDER BY $orderSQL
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$packages = $stmt->fetchAll();

// Dropdown options
$continents = $db->query("SELECT DISTINCT continent FROM destination WHERE continent IS NOT NULL ORDER BY continent")->fetchAll(PDO::FETCH_COLUMN);
$countries  = $db->query("SELECT DISTINCT country FROM destination ORDER BY country")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Browse Packages';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-wrap">
    <h1 class="section-title">Browse Packages</h1>
    <p class="section-subtitle"><?= $total ?> package<?= $total!==1?'s':'' ?> found</p>

    <!-- Filters -->
    <form method="GET" class="filter-bar">
        <div class="filter-group" style="flex:2;min-width:180px;">
            <label>Search</label>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Destination, package, agency…">
        </div>
        <div class="filter-group">
            <label>Continent</label>
            <select name="continent">
                <option value="">All</option>
                <?php foreach ($continents as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $continent===$c?'selected':'' ?>><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Country</label>
            <select name="country">
                <option value="">All</option>
                <?php foreach ($countries as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $country===$c?'selected':'' ?>><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Min Price (R)</label>
            <input type="number" name="minPrice" value="<?= htmlspecialchars($_GET['minPrice']??'') ?>" min="0" style="width:90px;">
        </div>
        <div class="filter-group">
            <label>Max Price (R)</label>
            <input type="number" name="maxPrice" value="<?= htmlspecialchars($_GET['maxPrice']??'') ?>" min="0" style="width:90px;">
        </div>
        <div class="filter-group">
            <label>Min Days</label>
            <input type="number" name="minDur" value="<?= htmlspecialchars($_GET['minDur']??'') ?>" min="1" style="width:70px;">
        </div>
        <div class="filter-group">
            <label>Max Days</label>
            <input type="number" name="maxDur" value="<?= htmlspecialchars($_GET['maxDur']??'') ?>" min="1" style="width:70px;">
        </div>
        <div class="filter-group">
            <label>Sort By</label>
            <select name="sort">
                <option value="rating"       <?= $sortBy==='rating'?'selected':'' ?>>Top Rated</option>
                <option value="price_asc"    <?= $sortBy==='price_asc'?'selected':'' ?>>Price ↑</option>
                <option value="price_desc"   <?= $sortBy==='price_desc'?'selected':'' ?>>Price ↓</option>
                <option value="duration_asc" <?= $sortBy==='duration_asc'?'selected':'' ?>>Duration ↑</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="/traveller/packages.php" class="btn btn-ghost">Reset</a>
    </form>

    <?php if (empty($packages)): ?>
        <div class="empty-state"><div class="empty-icon">🔭</div><p>No packages match your filters. Try broadening your search.</p></div>
    <?php else: ?>
    <div class="cards-grid">
        <?php foreach ($packages as $p):
            $stars = str_repeat('★', round($p['avgRating'])) . str_repeat('☆', 5-round($p['avgRating']));
        ?>
        <div class="package-card">
            <div class="card-img">
                <img src="/images/packages/<?= strtolower(str_replace([' ','\''],'-',$p['pkgName'])) ?>.jpg"
                     onerror="this.style.display='none';this.parentNode.innerHTML='🌴'" alt="">
                <?php if ((float)$p['avgRating'] >= 4.5): ?><span class="card-badge">⭐ Top Rated</span><?php endif; ?>
            </div>
            <div class="card-body">
                <div class="card-title"><?= htmlspecialchars($p['pkgName']) ?></div>
                <div class="card-agency">by <?= htmlspecialchars($p['agencyName']) ?></div>
                <div class="card-meta">
                    <?php if ($p['city']): ?><span class="meta-tag">📍 <?= htmlspecialchars($p['city'].', '.$p['country']) ?></span><?php endif; ?>
                    <?php if ($p['duration']): ?><span class="meta-tag">🗓 <?= $p['duration'] ?> days</span><?php endif; ?>
                    <?php if ($p['maxPeople']): ?><span class="meta-tag">👥 Max <?= $p['maxPeople'] ?></span><?php endif; ?>
                </div>
                <?php if ($p['description']): ?>
                <p style="font-size:.85rem;color:var(--text-muted);line-height:1.4;"><?= htmlspecialchars(substr($p['description'],0,90)) ?>…</p>
                <?php endif; ?>
                <div style="font-size:.85rem;color:#f0a500;"><?= $stars ?> <span style="color:var(--text-muted);">(<?= $p['reviewCount'] ?> review<?= $p['reviewCount']!=1?'s':'' ?>)</span></div>
                <div class="card-price">R <?= number_format($p['price'],2) ?> <span>/ person</span></div>
            </div>
            <div class="card-actions">
                <a href="/traveller/package_detail.php?id=<?= $p['packageID'] ?>" class="btn btn-primary btn-sm" style="flex:1;">View Details</a>
                <label style="display:flex;align-items:center;gap:.3rem;font-size:.82rem;cursor:pointer;">
                    <input type="checkbox" class="compare-check" value="<?= $p['packageID'] ?>" data-name="<?= htmlspecialchars($p['pkgName']) ?>"> Compare
                </label>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Compare bar -->
    <div id="compare-bar" style="display:none;position:fixed;bottom:0;left:0;right:0;background:var(--cream);border-top:2px solid var(--border);padding:.8rem 1.5rem;display:flex;align-items:center;gap:1rem;z-index:99;box-shadow:0 -4px 20px rgba(0,0,0,.1);">
        <strong>Comparing: </strong><span id="compare-names" style="color:var(--text-muted);flex:1;"></span>
        <a id="compare-link" href="#" class="btn btn-secondary">Compare Packages ⚖️</a>
        <button onclick="clearCompare()" class="btn btn-ghost btn-sm">Clear</button>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div class="pagination">
        <?php for ($i=1; $i<=$pages; $i++):
            $qp = array_merge($_GET, ['page'=>$i]);
        ?>
        <a href="?<?= http_build_query($qp) ?>" class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
const compareBar  = document.getElementById('compare-bar');
const compareNames= document.getElementById('compare-names');
const compareLink = document.getElementById('compare-link');

function updateCompareBar() {
    const checked = [...document.querySelectorAll('.compare-check:checked')];
    if (checked.length >= 2) {
        compareBar.style.display = 'flex';
        compareNames.textContent = checked.map(c=>c.dataset.name).join(', ');
        compareLink.href = '/traveller/compare.php?ids=' + checked.map(c=>c.value).join(',');
    } else {
        compareBar.style.display = 'none';
    }
}
function clearCompare() {
    document.querySelectorAll('.compare-check:checked').forEach(c=>c.checked=false);
    updateCompareBar();
}
document.querySelectorAll('.compare-check').forEach(cb=>{
    cb.addEventListener('change', ()=>{
        const checked = document.querySelectorAll('.compare-check:checked');
        if (checked.length > 3) { cb.checked=false; alert('Max 3 packages for comparison.'); }
        updateCompareBar();
    });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
