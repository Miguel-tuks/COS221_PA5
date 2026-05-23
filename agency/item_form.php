<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('Travel Agency');
$pdo  = getDB();
$type = $_GET['type'] ?? 'destinations';
$id   = (int)($_GET['id'] ?? 0);
$validTypes = ['destinations','flights','accommodations','attractions','restaurants'];
if (!in_array($type, $validTypes, true)) {
    header('Location: items.php');
    exit;
}

$isEdit = $id > 0;
$errors = [];
$v      = [];
$pageTitle = ($isEdit ? 'Edit ' : 'New ') . rtrim($type, 's');

if ($isEdit) {
    $map = [
        'destinations'   => ['DESTINATION',        'destinationID'],
        'flights'        => ['FLIGHT',             'flightID'],
        'accommodations' => ['ACCOMMODATION',      'accommodationID'],
        'attractions'    => ['TOURIST_ATTRACTION', 'attractionID'],
        'restaurants'    => ['RESTAURANT',         'restaurantID'],
    ];
    [$tbl, $idCol] = $map[$type];
    $stmt = $pdo->prepare("SELECT * FROM $tbl WHERE $idCol = :i");
    $stmt->execute([':i' => $id]);
    $v = $stmt->fetch();
    if (!$v) {
        setFlash('error', 'Item not found.');
        header('Location: items.php?type=' . urlencode($type));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $v = array_merge($v ?: [], $_POST);

    try {
        if ($type === 'destinations') {
            if (trim($_POST['city']    ?? '') === '') $errors['city']    = 'City required.';
            if (trim($_POST['country'] ?? '') === '') $errors['country'] = 'Country required.';
            if (!$errors) {
                if ($isEdit) {
                    $stmt = $pdo->prepare(
                        'UPDATE DESTINATION SET continent=:c1, country=:c2, city=:c3 WHERE destinationID=:i'
                    );
                    $stmt->execute([':c1'=>$_POST['continent'],':c2'=>$_POST['country'],':c3'=>$_POST['city'],':i'=>$id]);
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO DESTINATION (continent, country, city) VALUES (:c1,:c2,:c3)'
                    );
                    $stmt->execute([':c1'=>$_POST['continent'],':c2'=>$_POST['country'],':c3'=>$_POST['city']]);
                }
            }
        }
        elseif ($type === 'flights') {
            if (trim($_POST['flightNumber'] ?? '') === '') $errors['flightNumber'] = 'Required.';
            if (empty($_POST['destinationID']))            $errors['destinationID']= 'Pick a destination.';
            if (!$errors) {
                if ($isEdit) {
                    $stmt = $pdo->prepare(
                        'UPDATE FLIGHT SET flightNumber=:fn, departure=:dep, origin=:o,
                                           arrival=:arr, destinationID=:dID, airline=:al
                         WHERE flightID=:i'
                    );
                    $stmt->execute([
                        ':fn'=>$_POST['flightNumber'],
                        ':dep'=>$_POST['departure'] ?: null,
                        ':o'=>$_POST['origin'],
                        ':arr'=>$_POST['arrival'] ?: null,
                        ':dID'=>(int)$_POST['destinationID'],
                        ':al'=>$_POST['airline'],
                        ':i'=>$id
                    ]);
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO FLIGHT (flightNumber, departure, origin, arrival, destinationID, airline)
                         VALUES (:fn,:dep,:o,:arr,:dID,:al)'
                    );
                    $stmt->execute([
                        ':fn'=>$_POST['flightNumber'],
                        ':dep'=>$_POST['departure'] ?: null,
                        ':o'=>$_POST['origin'],
                        ':arr'=>$_POST['arrival'] ?: null,
                        ':dID'=>(int)$_POST['destinationID'],
                        ':al'=>$_POST['airline']
                    ]);
                }
            }
        }
        elseif ($type === 'accommodations') {
            if (trim($_POST['accName'] ?? '') === '')   $errors['accName']     = 'Name required.';
            if (empty($_POST['destinationID']))         $errors['destinationID']= 'Pick a destination.';
            if (!$errors) {
                if ($isEdit) {
                    $stmt = $pdo->prepare(
                        'UPDATE ACCOMMODATION SET accName=:n, type=:t, rating=:r,
                                                  address=:ad, destinationID=:d
                         WHERE accommodationID=:i'
                    );
                    $stmt->execute([
                        ':n'=>$_POST['accName'], ':t'=>$_POST['type'],
                        ':r'=>$_POST['rating'] !== '' ? $_POST['rating'] : null,
                        ':ad'=>$_POST['address'], ':d'=>(int)$_POST['destinationID'],
                        ':i'=>$id
                    ]);
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO ACCOMMODATION (accName, type, rating, address, destinationID)
                         VALUES (:n,:t,:r,:ad,:d)'
                    );
                    $stmt->execute([
                        ':n'=>$_POST['accName'], ':t'=>$_POST['type'],
                        ':r'=>$_POST['rating'] !== '' ? $_POST['rating'] : null,
                        ':ad'=>$_POST['address'], ':d'=>(int)$_POST['destinationID']
                    ]);
                }
            }
        }
        elseif ($type === 'attractions') {
            if (trim($_POST['name'] ?? '') === '') $errors['name'] = 'Name required.';
            if (!$errors) {
                if ($isEdit) {
                    $stmt = $pdo->prepare(
                        'UPDATE TOURIST_ATTRACTION SET name=:n, category=:c, entranceFee=:f
                         WHERE attractionID=:i'
                    );
                    $stmt->execute([
                        ':n'=>$_POST['name'], ':c'=>$_POST['category'],
                        ':f'=>$_POST['entranceFee'] !== '' ? $_POST['entranceFee'] : null,
                        ':i'=>$id
                    ]);
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO TOURIST_ATTRACTION (name, category, entranceFee)
                         VALUES (:n,:c,:f)'
                    );
                    $stmt->execute([
                        ':n'=>$_POST['name'], ':c'=>$_POST['category'],
                        ':f'=>$_POST['entranceFee'] !== '' ? $_POST['entranceFee'] : null
                    ]);
                }
            }
        }
        elseif ($type === 'restaurants') {
            if (trim($_POST['name'] ?? '') === '')   $errors['name']         = 'Name required.';
            if (empty($_POST['destinationID']))      $errors['destinationID']= 'Pick a destination.';
            if (!$errors) {
                if ($isEdit) {
                    $stmt = $pdo->prepare(
                        'UPDATE RESTAURANT SET priceRange=:pr, name=:n,
                                                cuisine=:c, destinationID=:d
                         WHERE restaurantID=:i'
                    );
                    $stmt->execute([
                        ':pr'=>$_POST['priceRange'], ':n'=>$_POST['name'],
                        ':c'=>$_POST['cuisine'], ':d'=>(int)$_POST['destinationID'],
                        ':i'=>$id
                    ]);
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO RESTAURANT (priceRange, name, cuisine, destinationID)
                         VALUES (:pr,:n,:c,:d)'
                    );
                    $stmt->execute([
                        ':pr'=>$_POST['priceRange'], ':n'=>$_POST['name'],
                        ':c'=>$_POST['cuisine'], ':d'=>(int)$_POST['destinationID']
                    ]);
                }
            }
        }

        if (!$errors) {
            setFlash('success', 'Saved.');
            header('Location: items.php?type=' . urlencode($type));
            exit;
        }
    } catch (Exception $e) {
        $errors['_'] = $e->getMessage();
    }
}

$destinations = [];
if (in_array($type, ['flights','accommodations','restaurants'], true)) {
    $destinations = $pdo->query('SELECT * FROM DESTINATION ORDER BY country, city')->fetchAll();
}

require __DIR__ . '/../includes/header.php';
?>

<div class="form-card">
    <h1><?= $isEdit ? 'Edit' : 'New' ?> <?= h(rtrim($type, 's')) ?></h1>
    <?php if (!empty($errors['_'])): ?>
        <div class="flash flash-error"><?= h($errors['_']) ?></div>
    <?php endif; ?>

    <form method="post" data-validate-form>

    <?php if ($type === 'destinations'): ?>
        <div class="field"><label>Continent</label>
            <input type="text" name="continent" value="<?= h($v['continent'] ?? '') ?>"></div>
        <div class="field <?= isset($errors['country']) ? 'has-error' : '' ?>"><label>Country</label>
            <input type="text" name="country" data-validate="required" value="<?= h($v['country'] ?? '') ?>">
            <div class="error"><?= h($errors['country'] ?? '') ?></div></div>
        <div class="field <?= isset($errors['city']) ? 'has-error' : '' ?>"><label>City</label>
            <input type="text" name="city" data-validate="required" value="<?= h($v['city'] ?? '') ?>">
            <div class="error"><?= h($errors['city'] ?? '') ?></div></div>

    <?php elseif ($type === 'flights'): ?>
        <div class="field"><label>Airline</label>
            <input type="text" name="airline" value="<?= h($v['airline'] ?? '') ?>"></div>
        <div class="field <?= isset($errors['flightNumber']) ? 'has-error' : '' ?>"><label>Flight number</label>
            <input type="text" name="flightNumber" data-validate="required" value="<?= h($v['flightNumber'] ?? '') ?>">
            <div class="error"><?= h($errors['flightNumber'] ?? '') ?></div></div>
        <div class="field"><label>Origin (departure city)</label>
            <input type="text" name="origin" value="<?= h($v['origin'] ?? '') ?>"></div>
        <div class="field <?= isset($errors['destinationID']) ? 'has-error' : '' ?>"><label>Destination</label>
            <select name="destinationID" data-validate="required">
                <option value="">-- Choose --</option>
                <?= options(array_map(fn($d) => [
                    'id'=>$d['destinationID'], 'label'=>destLabel($d)
                ], $destinations), 'id', 'label', $v['destinationID'] ?? null) ?>
            </select>
            <div class="error"><?= h($errors['destinationID'] ?? '') ?></div></div>
        <div class="row">
            <div class="field"><label>Departure (date/time)</label>
                <input type="datetime-local" name="departure"
                       value="<?= h($v['departure'] ? date('Y-m-d\TH:i', strtotime($v['departure'])) : '') ?>"></div>
            <div class="field"><label>Arrival (date/time)</label>
                <input type="datetime-local" name="arrival"
                       value="<?= h($v['arrival'] ? date('Y-m-d\TH:i', strtotime($v['arrival'])) : '') ?>"></div>
        </div>

    <?php elseif ($type === 'accommodations'): ?>
        <div class="field <?= isset($errors['accName']) ? 'has-error' : '' ?>"><label>Name</label>
            <input type="text" name="accName" data-validate="required" value="<?= h($v['accName'] ?? '') ?>">
            <div class="error"><?= h($errors['accName'] ?? '') ?></div></div>
        <div class="field"><label>Type</label>
            <input type="text" name="type" placeholder="Hotel / Lodge / Apartment..."
                   value="<?= h($v['type'] ?? '') ?>"></div>
        <div class="field"><label>Rating (0–5)</label>
            <input type="number" step="0.1" min="0" max="5" name="rating"
                   value="<?= h($v['rating'] ?? '') ?>"></div>
        <div class="field"><label>Address</label>
            <input type="text" name="address" value="<?= h($v['address'] ?? '') ?>"></div>
        <div class="field <?= isset($errors['destinationID']) ? 'has-error' : '' ?>"><label>Destination</label>
            <select name="destinationID" data-validate="required">
                <option value="">-- Choose --</option>
                <?= options(array_map(fn($d) => [
                    'id'=>$d['destinationID'], 'label'=>destLabel($d)
                ], $destinations), 'id', 'label', $v['destinationID'] ?? null) ?>
            </select>
            <div class="error"><?= h($errors['destinationID'] ?? '') ?></div></div>

    <?php elseif ($type === 'attractions'): ?>
        <div class="field <?= isset($errors['name']) ? 'has-error' : '' ?>"><label>Name</label>
            <input type="text" name="name" data-validate="required" value="<?= h($v['name'] ?? '') ?>">
            <div class="error"><?= h($errors['name'] ?? '') ?></div></div>
        <div class="field"><label>Category</label>
            <input type="text" name="category" placeholder="Nature / Culture / Historical..."
                   value="<?= h($v['category'] ?? '') ?>"></div>
        <div class="field"><label>Entrance fee (R)</label>
            <input type="number" step="0.01" min="0" name="entranceFee"
                   value="<?= h($v['entranceFee'] ?? '') ?>"></div>

    <?php else: ?>
        <div class="field <?= isset($errors['name']) ? 'has-error' : '' ?>"><label>Name</label>
            <input type="text" name="name" data-validate="required" value="<?= h($v['name'] ?? '') ?>">
            <div class="error"><?= h($errors['name'] ?? '') ?></div></div>
        <div class="field"><label>Cuisine</label>
            <input type="text" name="cuisine" value="<?= h($v['cuisine'] ?? '') ?>"></div>
        <div class="field"><label>Price range</label>
            <select name="priceRange">
                <?php foreach (['', '$', '$$', '$$$'] as $pr): ?>
                    <option value="<?= h($pr) ?>" <?= ($v['priceRange'] ?? '') === $pr ? 'selected' : '' ?>>
                        <?= h($pr === '' ? '— Any —' : $pr) ?>
                    </option>
                <?php endforeach; ?>
            </select></div>
        <div class="field <?= isset($errors['destinationID']) ? 'has-error' : '' ?>"><label>Destination</label>
            <select name="destinationID" data-validate="required">
                <option value="">-- Choose --</option>
                <?= options(array_map(fn($d) => [
                    'id'=>$d['destinationID'], 'label'=>destLabel($d)
                ], $destinations), 'id', 'label', $v['destinationID'] ?? null) ?>
            </select>
            <div class="error"><?= h($errors['destinationID'] ?? '') ?></div></div>
    <?php endif; ?>

        <div class="btn-row">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save changes' : 'Create' ?></button>
            <a class="btn btn-secondary" href="items.php?type=<?= h($type) ?>">Cancel</a>
        </div>
    </form>
</div>

<script src="<?= h($base) ?>js/validation.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
