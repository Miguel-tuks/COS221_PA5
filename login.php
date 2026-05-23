<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . baseUrl() . (isTraveller() ? 'traveller/' : 'agency/') . 'dashboard.php');
    exit;
}

$pageTitle = 'Log in';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    if ($u === '' || $p === ''){
        $error = 'Please enter your username and password.';
    } elseif (!attemptLogin($u, $p)) {
        $error = 'Invalid username or password.';
    } else {
        header('Location: ' . baseUrl() . (isTraveller() ? 'traveller/' : 'agency/') . 'dashboard.php');
        exit;
    }
}

require __DIR__ . '/includes/header.php';
?>

<div class="form-card">
    <h1>Log in</h1>
    <?php if ($error): ?>
        <div class="flash flash-error"><?= h($error) ?></div>
    <?php endif; ?>
    <form method="post" data-validate-form>
        <div class="field">
            <label>Username</label>
            <input type="text" name="username" data-validate="required"
                   value="<?= h($_POST['username'] ?? '') ?>" autofocus>
            <div class="error"></div>
        </div>
        <div class="field">
            <label>Password</label>
            <input type="password" name="password" data-validate="required">
            <div class="error"></div>
        </div>
        <button type="submit" class="btn btn-primary">Log in</button>
    </form>
    <p style="margin-top:14px;font-size:14px">
        New here? <a href="register.php">Create an account</a>.
    </p>
</div>

<script src="<?= h($base) ?>js/validation.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>