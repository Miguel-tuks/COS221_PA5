<?php

require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . baseUrl() . 'index.php');
    exit;
}

$pageTitle = 'Sign up';
$errors    = [];
$role      = $_POST['role'] ?? 'Traveller';   
$pdo       = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']         ?? '');
    $pwd      = $_POST['password']               ?? '';
    $pwd2     = $_POST['password_confirm']       ?? '';
    $phone    = trim($_POST['phone']             ?? '');

    if ($username === '')               $errors['username'] = 'Username is required.';
    if (strlen($username) > 50)         $errors['username'] = 'Username too long (max 50).';
    if (!isStrongEnough($pwd))          $errors['password'] = 'Min 8 chars, with a letter and a digit.';
    if ($pwd !== $pwd2)                 $errors['password_confirm'] = 'Passwords do not match.';
    if (!in_array($role, ['Traveller','Travel Agency'], true)) {
        $errors['role'] = 'Pick a role.';
    }

    if ($role === 'Traveller') {
        $first = trim($_POST['first_name'] ?? '');
        $last  = trim($_POST['last_name']  ?? '');
        if ($first === '') $errors['first_name'] = 'First name is required.';
        if ($last  === '') $errors['last_name']  = 'Last name is required.';
    } else {
        $agencyName = trim($_POST['agency_name'] ?? '');
        $license    = trim($_POST['license_no']  ?? '');
        $website    = trim($_POST['website']     ?? '');
        $aDesc      = trim($_POST['description'] ?? '');
        if ($agencyName === '') $errors['agency_name'] = 'Agency name is required.';
        if ($license    === '') $errors['license_no']  = 'License number is required.';
    }

    if (!$errors) {
        $check = $pdo->prepare('SELECT 1 FROM USER WHERE username = :u');
        $check->execute([':u' => $username]);
        if ($check->fetch()) {
            $errors['username'] = 'That username is already taken.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $hash = password_hash($pwd, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare(
                'INSERT INTO USER (username, title, password) VALUES (:u, :t, :p)'
            );
            $stmt->execute([':u' => $username, ':t' => $role, ':p' => $hash]);
            $uid = (int)$pdo->lastInsertId();

            if ($role === 'Traveller') {
                $stmt = $pdo->prepare(
                    'INSERT INTO TRAVELLER (userID, firstName, lastName) VALUES (:u, :f, :l)'
                );
                $stmt->execute([':u' => $uid, ':f' => $first, ':l' => $last]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO TRAVEL_AGENCY (userID, agencyName, website, licenseNo, description)
                     VALUES (:u, :n, :w, :l, :d)'
                );
                $stmt->execute([
                    ':u' => $uid, ':n' => $agencyName,
                    ':w' => $website !== '' ? $website : null,
                    ':l' => $license,
                    ':d' => $aDesc   !== '' ? $aDesc   : null
                ]);
            }

            if ($phone !== '') {
                $stmt = $pdo->prepare(
                    'INSERT INTO USER_PHONE (userID, phone) VALUES (:u, :p)'
                );
                $stmt->execute([':u' => $uid, ':p' => $phone]);
            }

            $pdo->commit();

            attemptLogin($username, $pwd);
            setFlash('success', 'Welcome to Tripistry!');
            header('Location: ' . baseUrl() . (isTraveller() ? 'traveller/' : 'agency/') . 'dashboard.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            if (strpos($e->getMessage(), 'licenseNo') !== false) {
                $errors['license_no'] = 'That license number is already registered.';
            } else {
                $errors['username'] = 'Registration failed. Please try again.';
            }
        }
    }
}

require __DIR__ . '/includes/header.php';
?>

<div class="form-card">
    <h1>Create your account</h1>

    <form method="post" data-validate-form>
        <div class="field">
            <label>I am a…</label>
            <select name="role" onchange="toggleRole(this.value)">
                <option value="Traveller"     <?= $role === 'Traveller'     ? 'selected' : '' ?>>Traveller</option>
                <option value="Travel Agency" <?= $role === 'Travel Agency' ? 'selected' : '' ?>>Travel Agency</option>
            </select>
        </div>

        <div class="field <?= isset($errors['username']) ? 'has-error' : '' ?>">
            <label>Username</label>
            <input type="text" name="username" data-validate="required"
                   value="<?= h($_POST['username'] ?? '') ?>">
            <div class="error"><?= h($errors['username'] ?? '') ?></div>
        </div>
        <div class="field <?= isset($errors['password']) ? 'has-error' : '' ?>">
            <label>Password</label>
            <input type="password" name="password" data-validate="required|password">
            <div class="error"><?= h($errors['password'] ?? '') ?></div>
        </div>
        <div class="field <?= isset($errors['password_confirm']) ? 'has-error' : '' ?>">
            <label>Confirm password</label>
            <input type="password" name="password_confirm" data-validate="required|match:password">
            <div class="error"><?= h($errors['password_confirm'] ?? '') ?></div>
        </div>
        <div class="field">
            <label>Phone (optional)</label>
            <input type="text" name="phone" value="<?= h($_POST['phone'] ?? '') ?>">
        </div>

        <div id="traveller-fields" style="<?= $role === 'Traveller' ? '' : 'display:none' ?>">
            <div class="row">
                <div class="field <?= isset($errors['first_name']) ? 'has-error' : '' ?>">
                    <label>First name</label>
                    <input type="text" name="first_name" value="<?= h($_POST['first_name'] ?? '') ?>">
                    <div class="error"><?= h($errors['first_name'] ?? '') ?></div>
                </div>
                <div class="field <?= isset($errors['last_name']) ? 'has-error' : '' ?>">
                    <label>Last name</label>
                    <input type="text" name="last_name" value="<?= h($_POST['last_name'] ?? '') ?>">
                    <div class="error"><?= h($errors['last_name'] ?? '') ?></div>
                </div>
            </div>
        </div>

        <div id="agency-fields" style="<?= $role === 'Travel Agency' ? '' : 'display:none' ?>">
            <div class="field <?= isset($errors['agency_name']) ? 'has-error' : '' ?>">
                <label>Agency name</label>
                <input type="text" name="agency_name" value="<?= h($_POST['agency_name'] ?? '') ?>">
                <div class="error"><?= h($errors['agency_name'] ?? '') ?></div>
            </div>
            <div class="field <?= isset($errors['license_no']) ? 'has-error' : '' ?>">
                <label>License number</label>
                <input type="text" name="license_no" value="<?= h($_POST['license_no'] ?? '') ?>">
                <div class="error"><?= h($errors['license_no'] ?? '') ?></div>
            </div>
            <div class="field">
                <label>Website</label>
                <input type="text" name="website" placeholder="https://..."
                       value="<?= h($_POST['website'] ?? '') ?>">
            </div>
            <div class="field">
                <label>Description</label>
                <textarea name="description" rows="3"><?= h($_POST['description'] ?? '') ?></textarea>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Create account</button>
    </form>

    <p style="margin-top:14px;font-size:14px">
        Already have an account? <a href="login.php">Log in</a>.
    </p>
</div>

<script>
function toggleRole(role) {
    document.getElementById('traveller-fields').style.display = role === 'Traveller'     ? '' : 'none';
    document.getElementById('agency-fields').style.display   = role === 'Travel Agency' ? '' : 'none';
}
</script>
<script src="<?= h($base) ?>js/validation.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
