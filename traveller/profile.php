<?php

require_once __DIR__ . '/../includes/auth.php';
requireRole('Traveller');

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
    $first = trim($_POST['first_name'] ?? '');
    $last  = trim($_POST['last_name']  ?? '');

    $errors = [];
    if ($first === '') $errors['first_name'] = 'First name is required.';
    if ($last  === '') $errors['last_name']  = 'Last name is required.';

    if (!$errors) {
        $stmt = $pdo->prepare(
            'UPDATE TRAVELLER SET firstName = :f, lastName = :l WHERE userID = :u'
        );
        $stmt->execute([':f' => $first, ':l' => $last, ':u' => $uid]);
        setFlash('success', 'Profile saved.');
        header('Location: profile.php');
        exit;
    }
}

$me = $pdo->prepare('SELECT * FROM TRAVELLER WHERE userID = :u');
$me->execute([':u' => $uid]);
$me = $me->fetch();

$phones = $pdo->prepare('SELECT phone FROM USER_PHONE WHERE userID = :u ORDER BY phone');
$phones->execute([':u' => $uid]);
$phones = $phones->fetchAll(PDO::FETCH_COLUMN);

require __DIR__ . '/../includes/header.php';
?>

<div class="form-card">
    <h1>My profile</h1>

    <form method="post" data-validate-form>
        <input type="hidden" name="action" value="save">

        <div class="row">
            <div class="field <?= isset($errors['first_name']) ? 'has-error' : '' ?>">
                <label>First name</label>
                <input type="text" name="first_name" data-validate="required"
                       value="<?= h($_POST['first_name'] ?? $me['firstName']) ?>">
                <div class="error"><?= h($errors['first_name'] ?? '') ?></div>
            </div>
            <div class="field <?= isset($errors['last_name']) ? 'has-error' : '' ?>">
                <label>Last name</label>
                <input type="text" name="last_name" data-validate="required"
                       value="<?= h($_POST['last_name'] ?? $me['lastName']) ?>">
                <div class="error"><?= h($errors['last_name'] ?? '') ?></div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Save changes</button>
    </form>

    <hr>

    <h2>Phone numbers</h2>
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
