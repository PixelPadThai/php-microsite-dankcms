<?php
require_once __DIR__ . '/../config.php';
spl_autoload_register(function ($c) { $p = __DIR__ . '/../class/' . str_replace('\\', '/', $c) . '.php'; if (is_file($p)) require $p; });

Auth::startSession();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::checkCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid form token. Try again.';
    } elseif (Auth::login($_POST['password'] ?? '')) {
        header('Location: /dashboard/content');
        exit;
    } else {
        $error = 'Wrong password or too many attempts.';
    }
}
$csrf = Auth::csrfToken();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard login</title>
<link rel="stylesheet" href="/styles/site.css">
<link rel="stylesheet" href="/styles/dashboard.css">
</head>
<body class="dash-login">
<form method="POST" class="card dash-login__card">
  <h1 style="margin-bottom:var(--space-4)">Dashboard</h1>
  <?php if ($error): ?><div class="field-error u-mb-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <div class="field u-mb-4">
    <label class="field-label" for="pw">Password</label>
    <input class="field-input" id="pw" type="password" name="password" required autofocus>
  </div>
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
  <button class="btn btn-primary" style="width:100%">Sign in</button>
</form>
</body>
</html>
