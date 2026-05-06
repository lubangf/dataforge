<?php

declare(strict_types=1);

session_start();

if (!empty($_SESSION['ops_auth']) && $_SESSION['ops_auth'] === true) {
  header('Location: /dataforge/operations');
    exit;
}

function dataforge_ops_env(string $name, string $default = ''): string
{
    $value = getenv($name);
    if ($value === false) {
        return $default;
    }

    return is_string($value) ? trim($value) : $default;
}

$expectedUser = dataforge_ops_env('DATAFORGE_OPS_USER', 'opsadmin');
$expectedPassword = dataforge_ops_env('DATAFORGE_OPS_PASSWORD', 'dataforge-ops');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (hash_equals($expectedUser, $username) && hash_equals($expectedPassword, $password)) {
    $_SESSION['ops_auth'] = true;
    $_SESSION['ops_user'] = $username;
    header('Location: /dataforge/operations');
        exit;
    }

  $error = 'Invalid credentials. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Operations Login | Data Forge</title>
  <link rel="icon" type="image/png" href="/dataforge/assets/img/dataforge-mark-orange.png" />
  <link rel="stylesheet" href="/dataforge/assets/css/styles.css?v=20260506-3" />
</head>
<body>
  <main class="container ops-main">
    <article class="card ops-login">
      <p class="kicker">Operations</p>
      <h1>Lead Console Login</h1>
      <p class="ops-note">Sign in to view captured inquiries from the website contact form.</p>

      <?php if ($error !== ''): ?>
        <p class="ops-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endif; ?>

      <form class="contact-form" method="post" action="/dataforge/operations/login.php">
        <label>
          Username
          <input type="text" name="username" autocomplete="username" required />
        </label>
        <label>
          Password
          <input type="password" name="password" autocomplete="current-password" required />
        </label>
        <button class="btn btn-primary" type="submit">Sign In</button>
      </form>
      <p class="ops-note">Set environment variables DATAFORGE_OPS_USER and DATAFORGE_OPS_PASSWORD in production.</p>
    </article>
  </main>
</body>
</html>
