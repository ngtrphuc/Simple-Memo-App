<?php

declare(strict_types=1);

require 'db.php';
require 'lang.php';

use App\Csrf;

$action = $_GET['action'] ?? 'login';
$oldUsername = '';

if ($action === 'logout') {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
    header('Location: auth.php');
    exit;
}

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $oldUsername = $username;

    if ($username === '' || $password === '') {
        $error = t('err_fill_all');
    } elseif (! preg_match('/^[a-zA-Z0-9_]{3,32}$/', $username)) {
        $error = t('err_invalid_username');
    } elseif ($action === 'register') {
        $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);

        if ($stmt->fetch()) {
            $error = t('err_user_exists');
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO users (username, password) VALUES (?, ?)');
            $stmt->execute([$username, $hash]);
            session_regenerate_id(true);
            $_SESSION['user_id'] = $db->lastInsertId();
            $_SESSION['username'] = $username;
            header('Location: index.php');
            exit;
        }
    } else {
        $stmt = $db->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        $storedPassword = $user['password'] ?? '';
        $isHash = $storedPassword && password_get_info($storedPassword)['algo'];
        $ok = false;

        if ($user) {
            if ($isHash) {
                $ok = password_verify($password, $storedPassword);
            } else {
                $ok = $password === $storedPassword;

                if ($ok) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
                    $stmt->execute([$hash, $user['id']]);
                }
            }
        }

        if ($ok) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header('Location: index.php');
            exit;
        }

        $error = t('err_wrong_login');
    }
}

$isRegister = $action === 'register';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(currentLang()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(t('app_title') . ' | ' . ($isRegister ? t('register') : t('login'))); ?></title>
    <link rel="stylesheet" href="app.css">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-brand card-panel">
            <div>
                <span class="eyebrow"><?php echo htmlspecialchars(t('app_title')); ?></span>
                <h1><?php echo htmlspecialchars(t('auth_tagline')); ?></h1>
                <p class="lead"><?php echo htmlspecialchars(t('auth_hint')); ?></p>
            </div>

            <ul class="feature-list">
                <li><?php echo htmlspecialchars(t('feature_secure')); ?></li>
                <li><?php echo htmlspecialchars(t('feature_repeat')); ?></li>
                <li><?php echo htmlspecialchars(t('feature_local')); ?></li>
            </ul>
        </section>

        <section class="auth-panel card-panel">
            <div class="auth-toolbar"><?php echo langSelect(); ?></div>
            <h2><?php echo htmlspecialchars($isRegister ? t('register') : t('login')); ?></h2>
            <p class="lead"><?php echo htmlspecialchars(t('auth_hint')); ?></p>

            <?php if ($error) { ?>
                <div class="error-banner"><?php echo htmlspecialchars($error); ?></div>
            <?php } ?>

            <form class="stack-form" method="post" novalidate>
                <?php echo Csrf::field(); ?>

                <label class="field" for="username">
                    <span class="field__label"><?php echo htmlspecialchars(t('username')); ?></span>
                    <input
                        id="username"
                        type="text"
                        name="username"
                        autocomplete="username"
                        placeholder="<?php echo htmlspecialchars(t('username')); ?>"
                        value="<?php echo htmlspecialchars($oldUsername); ?>"
                    >
                </label>

                <label class="field" for="password">
                    <span class="field__label"><?php echo htmlspecialchars(t('password')); ?></span>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        autocomplete="<?php echo $isRegister ? 'new-password' : 'current-password'; ?>"
                        placeholder="<?php echo htmlspecialchars(t('password')); ?>"
                    >
                </label>

                <button type="submit" class="btn btn-primary btn-block"><?php echo htmlspecialchars($isRegister ? t('create_account') : t('login')); ?></button>
            </form>

            <div class="auth-switch">
                <?php if ($isRegister) { ?>
                    <a class="text-link" href="auth.php"><?php echo htmlspecialchars(t('have_account')); ?></a>
                <?php } else { ?>
                    <a class="text-link" href="auth.php?action=register"><?php echo htmlspecialchars(t('no_account')); ?></a>
                <?php } ?>
            </div>
        </section>
    </main>
</body>
</html>
