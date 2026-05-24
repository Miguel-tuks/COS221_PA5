<?php

require_once __DIR__ . '/../includes/auth.php';
requireRole('Traveller');

$pageTitle = 'Packages';
$pdo = getDB();

$where  = ['p.isActive = 1'];
$params = [];

$keyword = trim($_GET['q'] ?? '');
if ($keyword !== '') {
    $where[] = '(p.pkgName LIKE :kw OR p.description LIKE :kw)';
    $params[':kw'] = '%' . $keyword . '%';
}

$destId = (int)($_GET['destination'] ?? 0);
if ($destId) {
    $where[] = 'EXISTS (SELECT 1 FROM PACKAGE_DESTINATION pd
                        WHERE pd.packageID = p.packageID AND pd.destinationID = :dest)';
    $params[':dest'] = $destId;
}

$minP = (float)($_GET['min_price'] ?? 0);
$maxP = (float)($_GET['max_price'] ?? 0);
if ($minP > 0) { $where[] = 'p.price >= :minp'; $params[':minp'] = $minP; }
if ($maxP > 0) { $where[] = 'p.price <= :maxp'; $params[':maxp'] = $maxP; }

$minDur = (int)($_GET['min_duration'] ?? 0);
if ($minDur > 0) { $where[] = 'p.duration >= :mdur'; $params[':mdur'] = $minDur; }

$groupOnly = !empty($_GET['group_only']);
if ($groupOnly) {
    $where[] = 'EXISTS (SELECT 1 FROM GROUP_TRIP gt WHERE gt.packageID = p.packageID)';
}

$sort = $_GET['sort'] ?? 'newest';
$orderBy = match ($sort) {
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'duration'   => 'p.duration ASC',
    'rating'     => 'avg_rating DESC',
    default      => 'p.packageID DESC',
};

$sql = "SELECT p.*, ta.agencyName,
               (SELECT image FROM PACKAGE_IMAGE WHERE packageID = p.packageID LIMIT 1) AS image,
               COALESCE((SELECT AVG(rating) FROM REVIEW WHERE packageID = p.packageID), 0) AS avg_rating,
               (SELECT GROUP_CONCAT(DISTINCT CONCAT(d.city, ', ', d.country) SEPARATOR ' • ')
                FROM PACKAGE_DESTINATION pd JOIN DESTINATION d ON d.destinationID = pd.destinationID
                WHERE pd.packageID = p.packageID) AS destinations
        FROM PACKAGE p
        JOIN TRAVEL_AGENCY ta ON ta.userID = p.agencyID
        WHERE " . implode(' AND ', $where) . "
        ORDER BY $orderBy";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$packages = $stmt->fetchAll();

$destinations = $pdo->query(
    'SELECT destinationID, city, country FROM DESTINATION ORDER BY country, city'
)->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<h1>Browse packages</h1>

<form method="get" class="toolbar">
    <div class="field">
        <label>Keyword</label>
        <input type="text" name="q" value="<?= h($keyword) ?>" placeholder="e.g. safari">
    </div>
    <div class="field">
        <label>Destination</label>
        <select name="destination">
            <option value="">Any</option>
            <?php foreach ($destinations as $d): ?>
                <option value="<?= (int)$d['destinationID'] ?>"
                    <?= $destId === (int)$d['destinationID'] ? 'selected' : '' ?>>
                    <?= h(destLabel($d)) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label>Min price</label>
        <input type="number" min="0" name="min_price" value="<?= h($_GET['min_price'] ?? '') ?>">
    </div>
    <div class="field">
        <label>Max price</label>
        <input type="number" min="0" name="max_price" value="<?= h($_GET['max_price'] ?? '') ?>">
    </div>
    <div class="field">
        <label>Min duration</label>
        <input type="number" min="0" name="min_duration" value="<?= h($_GET['min_duration'] ?? '') ?>">
    </div>
    <div class="field">
        <label>Sort by</label>
        <select name="sort">
            <option value="newest"     <?= $sort === 'newest'     ? 'selected' : '' ?>>Newest</option>
            <option value="price_asc"  <?= $sort === 'price_asc'  ? 'selected' : '' ?>>Price (low to high)</option>
            <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price (high to low)</option>
            <option value="duration"   <?= $sort === 'duration'   ? 'selected' : '' ?>>Duration</option>
            <option value="rating"     <?= $sort === 'rating'     ? 'selected' : '' ?>>Rating</option>
        </select>
    </div>
    <div class="field" style="flex:0">
        <label><input type="checkbox" name="group_only" value="1" <?= $groupOnly ? 'checked' : '' ?>> Group trips</label>
    </div>
    <button type="submit" class="btn btn-primary">Apply</button>
    <a class="btn btn-secondary" href="packages.php">Reset</a>
</form>

<p class="meta" style="margin-top:14px"><?= count($packages) ?> package(s) found.</p>

<?php if ($packages): ?>
<form method="get" action="compare.php">
    <div class="cards">
        <?php foreach ($packages as $p): ?>
        <div class="card">
            <img src="<?= h($p['image'] ?: 'https://placehold.co/600x400?text=Tripistry') ?>" alt="<?= h($p['pkgName']) ?>">
            <div class="card-body">
                <h3><?= h($p['pkgName']) ?></h3>
                <div class="meta"><?= h($p['destinations']) ?> · <?= (int)$p['duration'] ?> days</div>
                <div class="meta"><?= renderStars($p['avg_rating']) ?></div>
                <div class="meta">by <?= h($p['agencyName']) ?></div>
                <div class="price">R<?= number_format($p['price'], 2) ?></div>
                <div class="btn-row" style="margin-top:8px">
                    <a class="btn btn-primary" href="package_detail.php?id=<?= (int)$p['packageID'] ?>">View</a>
                    <label style="font-size:13px">
                        <input type="checkbox" name="ids[]" value="<?= (int)$p['packageID'] ?>"> Compare
                    </label>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div style="margin-top:20px">
        <button type="submit" class="btn btn-primary">Compare selected (up to 3)</button>
    </div>
</form>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
