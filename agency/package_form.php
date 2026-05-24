<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('Travel Agency');

$pdo = getDB();
$uid = currentUserId();
$pid = (int)($_GET['id'] ?? 0);

$isEdit  = $pid > 0;
$errors  = [];
$values  = [
    'pkgName' => '', 'description' => '', 'price' => '',
    'duration' => '', 'maxPeople' => '', 'guide' => '', 'guideNum' => '',
    'isActive' => 1,
    'destinations' => [], 'flights' => [], 'accommodations' => [],
    'attractions' => [], 'images' => ''
];
$pageTitle = $isEdit ? 'Edit package' : 'New package';

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM PACKAGE WHERE packageID = :p AND agencyID = :a');
    $stmt->execute([':p' => $pid, ':a' => $uid]);
    $pkg = $stmt->fetch();
    if (!$pkg) {
        setFlash('error', 'Package not found.');
        header('Location: packages.php');
        exit;
    }
    $values['pkgName']     = $pkg['pkgName'];
    $values['description'] = $pkg['description'];
    $values['price']       = $pkg['price'];
    $values['duration']    = $pkg['duration'];
    $values['maxPeople']   = $pkg['maxPeople'];
    $values['guide']       = $pkg['guide'];
    $values['guideNum']    = $pkg['guideNum'];
    $values['isActive']    = (int)$pkg['isActive'];

    $stmt = $pdo->prepare('SELECT destinationID FROM PACKAGE_DESTINATION WHERE packageID = :p');
    $stmt->execute([':p' => $pid]);
    $values['destinations'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->prepare('SELECT flightID FROM PACKAGE_FLIGHT WHERE packageID = :p');
    $stmt->execute([':p' => $pid]);
    $values['flights'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->prepare('SELECT accommodationID FROM PACKAGE_ACCOMMODATION WHERE packageID = :p');
    $stmt->execute([':p' => $pid]);
    $values['accommodations'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->prepare('SELECT attractionID FROM PACKAGE_ATTRACTION WHERE packageID = :p');
    $stmt->execute([':p' => $pid]);
    $values['attractions'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->prepare('SELECT image FROM PACKAGE_IMAGE WHERE packageID = :p');
    $stmt->execute([':p' => $pid]);
    $values['images'] = implode("\n", $stmt->fetchAll(PDO::FETCH_COLUMN));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['pkgName']        = trim($_POST['pkgName']     ?? '');
    $values['description']    = trim($_POST['description'] ?? '');
    $values['price']          = trim($_POST['price']       ?? '');
    $values['duration']       = trim($_POST['duration']    ?? '');
    $values['maxPeople']      = trim($_POST['maxPeople']   ?? '');
    $values['guide']          = trim($_POST['guide']       ?? '');
    $values['guideNum']       = trim($_POST['guideNum']    ?? '');
    $values['isActive']       = isset($_POST['isActive']) ? 1 : 0;
    $values['destinations']   = array_map('intval', $_POST['destinations']   ?? []);
    $values['flights']        = array_map('intval', $_POST['flights']        ?? []);
    $values['accommodations'] = array_map('intval', $_POST['accommodations'] ?? []);
    $values['attractions']    = array_map('intval', $_POST['attractions']    ?? []);
    $values['images']         = $_POST['images'] ?? '';

    if ($values['pkgName']   === '')               $errors['pkgName']  = 'Package name is required.';
    if (!is_numeric($values['price']) || $values['price'] < 0)
                                                   $errors['price']    = 'Price must be a positive number.';
    if (!ctype_digit((string)$values['duration']) || (int)$values['duration'] < 1)
                                                   $errors['duration'] = 'Duration in days is required.';
    if (!ctype_digit((string)$values['maxPeople']) || (int)$values['maxPeople'] < 1)
                                                   $errors['maxPeople']= 'Max people is required.';
    if ($values['guide']     === '')               $errors['guide']    = 'Guide name is required.';
    if ($values['guideNum']  === '')               $errors['guideNum'] = 'Guide contact number is required.';
    if (!$values['destinations'])                  $errors['destinations'] = 'Pick at least one destination.';

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            if ($isEdit) {
                $stmt = $pdo->prepare(
                    "UPDATE PACKAGE
                     SET pkgName = :n, description = :d, price = :p,
                         duration = :du, maxPeople = :mp, guide = :g,
                         guideNum = :gn, isActive = :a
                     WHERE packageID = :id AND agencyID = :u"
                );
                $stmt->execute([
                    ':n' => $values['pkgName'], ':d' => $values['description'],
                    ':p' => $values['price'],   ':du'=> $values['duration'],
                    ':mp'=> $values['maxPeople'], ':g' => $values['guide'],
                    ':gn'=> $values['guideNum'],  ':a' => $values['isActive'],
                    ':id'=> $pid, ':u' => $uid
                ]);
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO PACKAGE
                       (price, isActive, description, pkgName, duration, maxPeople, guide, guideNum, agencyID)
                     VALUES (:p, :a, :d, :n, :du, :mp, :g, :gn, :u)"
                );
                $stmt->execute([
                    ':p' => $values['price'], ':a' => $values['isActive'],
                    ':d' => $values['description'], ':n' => $values['pkgName'],
                    ':du'=> $values['duration'], ':mp'=> $values['maxPeople'],
                    ':g' => $values['guide'], ':gn'=> $values['guideNum'],
                    ':u' => $uid
                ]);
                $pid = (int)$pdo->lastInsertId();
            }

            $tables = [
                'PACKAGE_DESTINATION'   => ['destinationID',   $values['destinations']],
                'PACKAGE_FLIGHT'        => ['flightID',        $values['flights']],
                'PACKAGE_ACCOMMODATION' => ['accommodationID', $values['accommodations']],
                'PACKAGE_ATTRACTION'    => ['attractionID',    $values['attractions']],
            ];
            foreach ($tables as $tbl => [$col, $list]) {
                $stmt = $pdo->prepare("DELETE FROM $tbl WHERE packageID = :p");
                $stmt->execute([':p' => $pid]);
                if ($list) {
                    $stmt = $pdo->prepare("INSERT INTO $tbl (packageID, $col) VALUES (:p, :v)");
                    foreach (array_unique($list) as $v) {
                        $stmt->execute([':p' => $pid, ':v' => (int)$v]);
                    }
                }
            }

            $stmt = $pdo->prepare('DELETE FROM PACKAGE_IMAGE WHERE packageID = :p');
            $stmt->execute([':p' => $pid]);
            $urls = preg_split('/\r?\n/', $values['images']);
            $seen = [];
            foreach ($urls as $url) {
                $url = trim($url);
                if ($url === '' || isset($seen[$url])) continue;
                if (strlen($url) > 500) continue;
                $seen[$url] = true;
                $stmt = $pdo->prepare(
                    'INSERT INTO PACKAGE_IMAGE (packageID, image) VALUES (:p, :i)'
                );
                $stmt->execute([':p' => $pid, ':i' => $url]);
            }

            $pdo->commit();
            setFlash('success', $isEdit ? 'Package updated.' : 'Package created.');
            header('Location: packages.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['pkgName'] = 'Error saving: ' . $e->getMessage();
        }
    }
}

$destinations   = $pdo->query('SELECT * FROM DESTINATION ORDER BY country, city')->fetchAll();
$flights        = $pdo->query(
    "SELECT f.*, d.city, d.country FROM FLIGHT f
     JOIN DESTINATION d ON d.destinationID = f.destinationID
     ORDER BY f.departure"
)->fetchAll();
$accommodations = $pdo->query(
    "SELECT a.*, d.city, d.country FROM ACCOMMODATION a
     JOIN DESTINATION d ON d.destinationID = a.destinationID
     ORDER BY a.accName"
)->fetchAll();
$attractions    = $pdo->query('SELECT * FROM TOURIST_ATTRACTION ORDER BY name')->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<div class="form-card form-wide">
    <h1><?= $isEdit ? 'Edit package' : 'Create a new package' ?></h1>

    <form method="post" data-validate-form>
        <div class="field <?= isset($errors['pkgName']) ? 'has-error' : '' ?>">
            <label>Name</label>
            <input type="text" name="pkgName" data-validate="required"
                   value="<?= h($values['pkgName']) ?>">
            <div class="error"><?= h($errors['pkgName'] ?? '') ?></div>
        </div>

        <div class="field">
            <label>Description</label>
            <textarea name="description" rows="4"><?= h($values['description']) ?></textarea>
        </div>

        <div class="row">
            <div class="field <?= isset($errors['price']) ? 'has-error' : '' ?>">
                <label>Price (R)</label>
                <input type="number" step="0.01" min="0" name="price"
                       value="<?= h($values['price']) ?>" data-validate="required|number">
                <div class="error"><?= h($errors['price'] ?? '') ?></div>
            </div>
            <div class="field <?= isset($errors['duration']) ? 'has-error' : '' ?>">
                <label>Duration (days)</label>
                <input type="number" min="1" name="duration"
                       value="<?= h($values['duration']) ?>" data-validate="required|number">
                <div class="error"><?= h($errors['duration'] ?? '') ?></div>
            </div>
        </div>

        <div class="row">
            <div class="field <?= isset($errors['maxPeople']) ? 'has-error' : '' ?>">
                <label>Max travellers (per booking)</label>
                <input type="number" min="1" name="maxPeople"
                       value="<?= h($values['maxPeople']) ?>" data-validate="required|number">
                <div class="error"><?= h($errors['maxPeople'] ?? '') ?></div>
            </div>
            <div class="field">
                <label>
                    <input type="checkbox" name="isActive" value="1"
                           <?= $values['isActive'] ? 'checked' : '' ?>> Active (visible to travellers)
                </label>
            </div>
        </div>

        <div class="row">
            <div class="field <?= isset($errors['guide']) ? 'has-error' : '' ?>">
                <label>Guide name</label>
                <input type="text" name="guide" maxlength="30"
                       value="<?= h($values['guide']) ?>" data-validate="required">
                <div class="error"><?= h($errors['guide'] ?? '') ?></div>
            </div>
            <div class="field <?= isset($errors['guideNum']) ? 'has-error' : '' ?>">
                <label>Guide contact number</label>
                <input type="text" name="guideNum" maxlength="20"
                       value="<?= h($values['guideNum']) ?>" data-validate="required">
                <div class="error"><?= h($errors['guideNum'] ?? '') ?></div>
            </div>
        </div>

        <div class="field">
            <label>Image URLs (one per line)</label>
            <textarea name="images" rows="3"
                      placeholder="https://example.com/photo1.jpg&#10;https://example.com/photo2.jpg"><?= h($values['images']) ?></textarea>
            <small class="meta">The first URL is used as the thumbnail.</small>
        </div>

        <div class="field <?= isset($errors['destinations']) ? 'has-error' : '' ?>">
            <label>Destinations <small>(pick at least one)</small></label>
            <div class="checkbox-list">
                <?php foreach ($destinations as $d): ?>
                <label>
                    <input type="checkbox" name="destinations[]"
                           value="<?= (int)$d['destinationID'] ?>"
                           <?= in_array((int)$d['destinationID'], array_map('intval', $values['destinations']), true) ? 'checked' : '' ?>>
                    <?= h(destLabel($d)) ?>
                </label>
                <?php endforeach; ?>
            </div>
            <div class="error"><?= h($errors['destinations'] ?? '') ?></div>
        </div>

        <div class="field">
            <label>Flights</label>
            <div class="checkbox-list">
                <?php foreach ($flights as $f): ?>
                <label>
                    <input type="checkbox" name="flights[]"
                           value="<?= (int)$f['flightID'] ?>"
                           <?= in_array((int)$f['flightID'], array_map('intval', $values['flights']), true) ? 'checked' : '' ?>>
                    <?= h($f['airline']) ?> <?= h($f['flightNumber']) ?>
                    — <?= h($f['origin']) ?> → <?= h($f['city']) ?>, <?= h($f['country']) ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="field">
            <label>Accommodations</label>
            <div class="checkbox-list">
                <?php foreach ($accommodations as $a): ?>
                <label>
                    <input type="checkbox" name="accommodations[]"
                           value="<?= (int)$a['accommodationID'] ?>"
                           <?= in_array((int)$a['accommodationID'], array_map('intval', $values['accommodations']), true) ? 'checked' : '' ?>>
                    <?= h($a['accName']) ?> (<?= h($a['type']) ?>)
                    — <?= h($a['city']) ?>, <?= h($a['country']) ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="field">
            <label>Tourist attractions</label>
            <div class="checkbox-list">
                <?php foreach ($attractions as $a): ?>
                <label>
                    <input type="checkbox" name="attractions[]"
                           value="<?= (int)$a['attractionID'] ?>"
                           <?= in_array((int)$a['attractionID'], array_map('intval', $values['attractions']), true) ? 'checked' : '' ?>>
                    <?= h($a['name']) ?> <?= $a['category'] ? '(' . h($a['category']) . ')' : '' ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="btn-row">
            <button type="submit" class="btn btn-primary">
                <?= $isEdit ? 'Save changes' : 'Create package' ?>
            </button>
            <a class="btn btn-secondary" href="packages.php">Cancel</a>
        </div>
    </form>
</div>

<script src="<?= h($base) ?>js/validation.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
