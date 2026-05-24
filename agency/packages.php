<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAgency();

$db  = getDB();
$uid = (int)$_SESSION['userID'];

$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'delete') {
    $pid = (int)($_POST['packageID']??0);
 
    $check = $db->prepare("SELECT agencyID FROM package WHERE packageID=?");
    $check->execute([$pid]);
    $pkg = $check->fetch();
    if ($pkg && $pkg['agencyID'] == $uid) {
        $db->prepare("UPDATE package SET isActive=0 WHERE packageID=?")->execute([$pid]);
        $flash = ['type'=>'success','msg'=>'Package deactivated.'];
    } else {
        $flash = ['type'=>'error','msg'=>'Unauthorised.'];
    }
}

$packages = $db->prepare("
    SELECT p.*, d.city, d.country,
           COALESCE(AVG(r.rating),0) as avgRating, COUNT(DISTINCT r.travellerID) as reviewCount,
           COUNT(DISTINCT b.travellerID) as bookingCount
    FROM package p
    LEFT JOIN package_destination pd ON pd.packageID=p.packageID
    LEFT JOIN destination d ON d.destinationID=pd.destinationID
    LEFT JOIN review r ON r.packageID=p.packageID
    LEFT JOIN booking b ON b.packageID=p.packageID
    WHERE p.agencyID=?
    GROUP BY p.packageID, d.city, d.country
    ORDER BY p.packageID DESC
");
$packages->execute([$uid]);
$packages = $packages->fetchAll();

$pageTitle = 'My Packages';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-wrap">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 class="section-title" style="margin:0;">My Packages</h1>
            <p style="color:var(--text-muted);font-size:.9rem;"><?= count($packages) ?> package<?= count($packages)!==1?'s':'' ?></p>
        </div>
        <a href="/agency/package_form.php" class="btn btn-primary">+ Create Package</a>
    </div>

    <?php if (empty($packages)): ?>
    <div class="empty-state">
        <div class="empty-icon">📦</div>
        <p>You haven't created any packages yet.</p>
        <a href="/agency/package_form.php" class="btn btn-primary" style="margin-top:1rem;">Create Your First Package</a>
    </div>
    <?php else: ?>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Package</th><th>Destination</th><th>Price</th><th>Duration</th><th>Rating</th><th>Bookings</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($packages as $p): ?>
        <tr>
            <td><strong><?= htmlspecialchars($p['pkgName']) ?></strong></td>
            <td><?= htmlspecialchars($p['city']?$p['city'].', '.$p['country']:'-') ?></td>
            <td>R <?= number_format($p['price'],2) ?></td>
            <td><?= $p['duration'] ?> days</td>
            <td style="color:#f0a500;"><?= round($p['avgRating'],1) ?> ⭐ (<?= $p['reviewCount'] ?>)</td>
            <td><?= $p['bookingCount'] ?></td>
            <td><span class="badge <?= $p['isActive']?'badge-green':'badge-grey' ?>"><?= $p['isActive']?'Active':'Inactive' ?></span></td>
            <td>
                <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
                    <a href="/agency/package_form.php?id=<?= $p['packageID'] ?>" class="btn btn-outline btn-sm">Edit</a>
                    <?php if ($p['isActive']): ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="packageID" value="<?= $p['packageID'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm" data-confirm="Deactivate this package?">Deactivate</button>
                    </form>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
