<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('Travel Agency');

$pageTitle = 'Profile';
$pdo = getDB();
$uid = currentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_phone') {
    $phone = trim($_POST['phone'] ?? '');
    if ($phone === '' || strlen($phone) > 20) {
        setFlash('error', 'Enter a phone number (max 20 characters).');
    } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO USER_PHONE (userID, phone) VALUES (:u, :p)'
            );
            $stmt->execute([':u' => $uid, ':p' => $phone]);
            setFlash('success', 'Phone added.');
        } catch (PDOException $e) {
            setFlash('error', 'That number is already on your profile.');
        }
    }
    header('Location: profile.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove_phone') {
    $phone = trim($_POST['phone'] ?? '');
    $stmt = $pdo->prepare(
        'DELETE FROM USER_PHONE WHERE userID = :u AND phone = :p'
    );
    $stmt->execute([':u' => $uid, ':p' => $phone]);
    setFlash('success', 'Phone removed.');
    header('Location: profile.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $agencyName = trim($_POST['agency_name'] ?? '');
    $license    = trim($_POST['license_no']  ?? '');
    $website    = trim($_POST['website']     ?? '');
    $desc       = trim($_POST['description'] ?? '');

    $errors = [];
    if ($agencyName === '') $errors['agency_name'] = 'Agency name is required.';
    if ($license    === '') $errors['license_no']  = 'License number is required.';

    if (!$errors) {
        try {
            $stmt = $pdo->prepare(
                'UPDATE TRAVEL_AGENCY
                 SET agencyName = :n, website = :w, licenseNo = :l, description = :d
                 WHERE userID = :u'
            );
            $stmt->execute([
                ':n' => $agencyName,
                ':w' => $website !== '' ? $website : null,
                ':l' => $license,
                ':d' => $desc    !== '' ? $desc    : null,
                ':u' => $uid
            ]);
            setFlash('success', 'Profile saved.');
            header('Location: profile.php');
            exit;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'licenseNo') !== false) {
                $errors['license_no'] = 'License number already taken by another agency.';
            } else {
                $errors['_'] = $e->getMessage();
            }
        }
    }
}

$me = $pdo->prepare('SELECT * FROM TRAVEL_AGENCY WHERE userID = :u');
$me->execute([':u' => $uid]);
$me = $me->fetch();

$phones = $pdo->prepare('SELECT phone FROM USER_PHONE WHERE userID = :u ORDER BY phone');
$phones->execute([':u' => $uid]);
$phones = $phones->fetchAll(PDO::FETCH_COLUMN);

require __DIR__ . '/../includes/header.php';
?>

<div class="form-card form-wide">
    <h1>Agency profile</h1>

    <form method="post" data-validate-form>
        <input type="hidden" name="action" value="save">
        <?php if (!empty($errors['_'])): ?>
            <div class="flash flash-error"><?= h($errors['_']) ?></div>
        <?php endif; ?>

        <div class="field <?= isset($errors['agency_name']) ? 'has-error' : '' ?>">
            <label>Agency name</label>
            <input type="text" name="agency_name" data-validate="required"
                   value="<?= h($_POST['agency_name'] ?? $me['agencyName']) ?>">
            <div class="error"><?= h($errors['agency_name'] ?? '') ?></div>
        </div>

        <div class="field <?= isset($errors['license_no']) ? 'has-error' : '' ?>">
            <label>License number</label>
            <input type="text" name="license_no" data-validate="required"
                   value="<?= h($_POST['license_no'] ?? $me['licenseNo']) ?>">
            <div class="error"><?= h($errors['license_no'] ?? '') ?></div>
        </div>

        <div class="field">
            <label>Website</label>
            <input type="text" name="website" placeholder="https://..."
                   value="<?= h($_POST['website'] ?? $me['website']) ?>">
        </div>

        <div class="field">
            <label>Description</label>
            <textarea name="description" rows="4"><?= h($_POST['description'] ?? $me['description']) ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Save changes</button>
    </form>

    <hr>

    <h2>Phone numbers</h2>
    <p class="meta">Multiple numbers supported — useful for a switchboard, after-hours line, etc.</p>

    <div>
        <?php if (!$phones): ?>
            <p class="meta">No phone numbers yet.</p>
        <?php else: foreach ($phones as $p): ?>
            <span class="phone-tag">
                <?= h($p) ?>
                <form method="post">
                    <input type="hidden" name="action" value="remove_phone">
                    <input type="hidden" name="phone"  value="<?= h($p) ?>">
                    <button type="submit" data-confirm="Remove this number?">×</button>
                </form>
            </span>
        <?php endforeach; endif; ?>
    </div>

    <form method="post" style="margin-top:14px;display:flex;gap:8px;align-items:end" data-validate-form>
        <input type="hidden" name="action" value="add_phone">
        <div class="field" style="flex:1;margin:0">
            <label>Add a phone number</label>
            <input type="text" name="phone" data-validate="required" maxlength="20"
                   placeholder="+27 ...">
            <div class="error"></div>
        </div>
        <button type="submit" class="btn">Add</button>
    </form>
</div>

<script src="<?= h($base) ?>js/validation.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
