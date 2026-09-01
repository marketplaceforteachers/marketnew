<?php
// First-run setup wizard. Delete or rename this file after installing for a little extra safety
// (it re-checks for an existing config.php on every run, so it's not exploitable once installed).

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

/**
 * Executes a .sql file statement-by-statement (not relying on driver multi-statement support,
 * which isn't guaranteed on shared hosts). Safe here because none of our SQL files embed a
 * semicolon inside a quoted string value.
 */
function run_sql_file(PDO $pdo, string $path): void
{
    $sql = file_get_contents($path);
    // Strip full-line comments so they don't confuse the splitter.
    $sql = preg_replace('/^--.*$/m', '', $sql);
    foreach (preg_split('/;\s*\n/', $sql) as $statement) {
        $statement = trim($statement);
        if ($statement === '') {
            continue;
        }
        $pdo->exec($statement);
    }
}

$error = null;
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim($_POST['db_host'] ?? 'localhost');
    $port = trim($_POST['db_port'] ?? '3306');
    $name = trim($_POST['db_name'] ?? '');
    $user = trim($_POST['db_user'] ?? '');
    $pass = $_POST['db_pass'] ?? '';
    $appUrl = rtrim(trim($_POST['app_url'] ?? ''), '/');

    if (!$name || !$user) {
        $error = 'Database name and username are required.';
    } else {
        try {
            // Connect without a database first so we can CREATE DATABASE IF NOT EXISTS.
            $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $name) . '` DEFAULT CHARACTER SET utf8mb4');
            $pdo->exec('USE `' . str_replace('`', '', $name) . '`');

            run_sql_file($pdo, __DIR__ . '/db/schema.sql');
            run_sql_file($pdo, __DIR__ . '/db/seed.sql');

            $secret = bin2hex(random_bytes(32));
            $configContents = "<?php\n"
                . "// Refuse to run if loaded directly over HTTP instead of via includes/db.php.\n"
                . "if (!defined('MFT_APP')) { http_response_code(403); exit('Direct access not permitted.'); }\n\n"
                . "define('DB_HOST', " . var_export($host, true) . ");\n"
                . "define('DB_PORT', " . var_export($port, true) . ");\n"
                . "define('DB_NAME', " . var_export($name, true) . ");\n"
                . "define('DB_USER', " . var_export($user, true) . ");\n"
                . "define('DB_PASS', " . var_export($pass, true) . ");\n"
                . "define('APP_SECRET', " . var_export($secret, true) . ");\n"
                . "define('APP_URL', " . var_export($appUrl ?: 'http://localhost', true) . ");\n";

            if (!is_writable(__DIR__)) {
                throw new Exception('This directory is not writable — set folder permissions so PHP can create config.php, or upload config.php yourself using config.php.example as a template.');
            }
            file_put_contents(__DIR__ . '/config.php', $configContents);

            $done = true;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install — MarketplaceForTeachers.com</title>
<link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body style="background:var(--royal-900);min-height:100vh;">
<div class="container-sm py-10">
  <div class="card card-pad" style="max-width:480px;margin:2rem auto;">
    <?php if ($done): ?>
      <h1 class="text-xl">Installed!</h1>
      <p class="text-sm mt-2">Your database is set up and seeded with demo data.</p>
      <div class="flash flash-success mt-4">
        <strong>Admin login:</strong> admin@example.com / AdminPass123!<br>
        <strong>Teacher login:</strong> teacher@example.com / Password123!
      </div>
      <p class="text-xs text-muted mt-3">For security, delete or rename <code>install.php</code> now that setup is complete.</p>
      <a href="/index.php" class="btn btn-primary mt-4 w-full text-center" style="justify-content:center;">Go to the site</a>
    <?php else: ?>
      <h1 class="text-xl">Set up MarketplaceForTeachers.com</h1>
      <p class="text-sm text-muted mt-2">Enter your MySQL database connection details. We'll create the tables and seed demo data automatically.</p>
      <?php if ($error): ?><div class="flash flash-error mt-3"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="post" class="mt-4">
        <div class="field"><label>Database Host</label><input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required></div>
        <div class="field"><label>Database Port</label><input type="text" name="db_port" value="<?= htmlspecialchars($_POST['db_port'] ?? '3306') ?>" required></div>
        <div class="field"><label>Database Name</label><input type="text" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" required></div>
        <div class="field"><label>Database Username</label><input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required></div>
        <div class="field"><label>Database Password</label><input type="password" name="db_pass"></div>
        <div class="field"><label>Site URL</label><input type="text" name="app_url" placeholder="https://yourdomain.com" value="<?= htmlspecialchars($_POST['app_url'] ?? '') ?>"></div>
        <button class="btn btn-primary w-full" style="justify-content:center;">Install</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
