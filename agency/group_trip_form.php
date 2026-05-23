<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('Travel Agency');

$pdo = getDB();
$uid = currentUserId();

$pid    = (int)($_GET['pkg']  ?? $_POST['package_id'] ?? 0);
$tripID = (int)($_GET['trip'] ?? $_POST['trip_id']    ?? 0);
$isEdit = $tripID > 0;
$errors = [];
$v      = [
    'packageID'  => $pid,
    'tripStatus' => 'open',
    'departDate' => '',
    'returnDate' => '',
    'maxMembers' => ''
];
$pageTitle = $isEdit ? 'Edit group trip' : 'New group trip';

if ($isEdit) {
    $stmt = $pdo->prepare(
        'SELECT * FROM GROUP_TRIP WHERE packageID = :p AND tripID = :t AND agencyID = :a'
    );
    $stmt->execute([':p' => $pid, ':t' => $tripID, ':a' => $uid]);
    $v = $stmt->fetch();
    if (!$v) {
        setFlash('error', 'Trip not found.');
        header('Location: group_trips.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $v['packageID']  = (int)($_POST['package_id'] ?? 0);
    $v['tripStatus'] = $_POST['tripStatus'] ?? 'open';
    $v['departDate'] = $_POST['departDate'] ?? '';
    $v['returnDate'] = $_POST['returnDate'] ?? '';
    $v['maxMembers'] = $_POST['maxMembers'] ?? '';

    $stmt = $pdo->prepare('SELECT 1 FROM PACKAGE WHERE packageID = :p AND agencyID = :a');
    $stmt->execute([':p' => $v['packageID'], ':a' => $uid]);
    if (!$stmt->fetchColumn()) {
        $errors['package_id'] = 'Pick one of your own packages.';
    }
    if (!in_array($v['tripStatus'], ['open','closed','full'], true)) {
        $errors['tripStatus'] = 'Invalid status.';
    }
    if (!$v['departDate']) $errors['departDate'] = 'Required.';
    if (!$v['returnDate']) $errors['returnDate'] = 'Required.';
    if ($v['departDate'] && $v['returnDate'] && $v['returnDate'] < $v['departDate']) {
        $errors['returnDate'] = 'Return must be on or after departure.';
    }
    if (!ctype_digit((string)$v['maxMembers']) || (int)$v['maxMembers'] < 1) {
        $errors['maxMembers'] = 'Must be at least 1.';
    }

    if (!$errors) {
        try {
            if ($isEdit) {
                $stmt = $pdo->prepare(
                    'UPDATE GROUP_TRIP
                     SET tripStatus = :s, departDate = :d, returnDate = :r, maxMembers = :m
                     WHERE packageID = :p AND tripID = :t AND agencyID = :a'
                );
                $stmt->execute([
                    ':s' => $v['tripStatus'], ':d' => $v['departDate'],
                    ':r' => $v['returnDate'], ':m' => $v['maxMembers'],
                    ':p' => $pid, ':t' => $tripID, ':a' => $uid
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'SELECT COALESCE(MAX(tripID), 0) + 1 FROM GROUP_TRIP WHERE packageID = :p'
                );
                $stmt->execute([':p' => $v['packageID']]);
                $newId = (int)$stmt->fetchColumn();

                $stmt = $pdo->prepare(
                    "INSERT INTO GROUP_TRIP
                       (packageID, tripID, tripStatus, departDate, returnDate, maxMembers, agencyID)
                     VALUES (:p, :t, :s, :d, :r, :m, :a)"
                );
                $stmt->execute([
                    ':p' => $v['packageID'], ':t' => $newId,
                    ':s' => $v['tripStatus'], ':d' => $v['departDate'],
                    ':r' => $v['returnDate'], ':m' => $v['maxMembers'],
                    ':a' => $uid
                ]);
            }
            setFlash('success', 'Group trip saved.');
            header('Location: group_trips.php');
            exit;
        } catch (Exception $e) {
            $errors['_'] = $e->getMessage();
        }
    }
}

$myPackages = $pdo->prepare('SELECT packageID, pkgName FROM PACKAGE WHERE agencyID = :a ORDER BY pkgName');
$myPackages->execute([':a' => $uid]);
$myPackages = $myPackages->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<div class="form-card">
    <h1><?= $isEdit ? 'Edit group trip' : 'Create a group trip' ?></h1>
    <?php if (!empty($errors['_'])): ?>
        <div class="flash flash-error"><?= h($errors['_']) ?></div>
    <?php endif; ?>

    <form method="post" data-validate-form>
        <input type="hidden" name="trip_id" value="<?= (int)$tripID ?>">

        <div class="field <?= isset($errors['package_id']) ? 'has-error' : '' ?>">
            <label>Package</label>
            <?php if ($isEdit): ?>
                <input type="hidden" name="package_id" value="<?= (int)$pid ?>">
                <input type="text" disabled value="<?= h($myPackages
                    ? (array_values(array_filter($myPackages, fn($p) => (int)$p['packageID'] === (int)$pid))[0]['pkgName'] ?? '')
                    : '') ?>">
            <?php else: ?>
                <select name="package_id" data-validate="required">
                    <option value="">-- Choose --</option>
                    <?= options($myPackages, 'packageID', 'pkgName', $v['packageID'] ?? null) ?>
                </select>
            <?php endif; ?>
            <div class="error"><?= h($errors['package_id'] ?? '') ?></div>
        </div>

        <div class="row">
            <div class="field <?= isset($errors['departDate']) ? 'has-error' : '' ?>">
                <label>Departure date</label>
                <input type="date" name="departDate" data-validate="required"
                       value="<?= h($v['departDate']) ?>">
                <div class="error"><?= h($errors['departDate'] ?? '') ?></div>
            </div>
            <div class="field <?= isset($errors['returnDate']) ? 'has-error' : '' ?>">
                <label>Return date</label>
                <input type="date" name="returnDate" data-validate="required"
                       value="<?= h($v['returnDate']) ?>">
                <div class="error"><?= h($errors['returnDate'] ?? '') ?></div>
            </div>
        </div>

        <div class="row">
            <div class="field <?= isset($errors['maxMembers']) ? 'has-error' : '' ?>">
                <label>Max members</label>
                <input type="number" name="maxMembers" min="1" data-validate="required|number"
                       value="<?= h($v['maxMembers']) ?>">
                <div class="error"><?= h($errors['maxMembers'] ?? '') ?></div>
            </div>
            <div class="field <?= isset($errors['tripStatus']) ? 'has-error' : '' ?>">
                <label>Status</label>
                <select name="tripStatus" data-validate="required">
                    <?php foreach (['open','closed','full'] as $s): ?>
                        <option value="<?= $s ?>"
                                <?= ($v['tripStatus'] ?? '') === $s ? 'selected' : '' ?>>
                            <?= ucfirst($s) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="error"><?= h($errors['tripStatus'] ?? '') ?></div>
            </div>
        </div>

        <div class="btn-row">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save changes' : 'Create trip' ?></button>
            <a class="btn btn-secondary" href="group_trips.php">Cancel</a>
        </div>
    </form>
</div>

<script src="<?= h($base) ?>js/validation.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
