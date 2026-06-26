<?php
require 'db.php';
require 'lang.php';

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
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $oldUsername = $username;

    if ($username === '' || $password === '') {
        $error = t('err_fill_all');
    } elseif ($action === 'register') {
        $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);

        if ($stmt->fetch()) {
            $error = t('err_user_exists');
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO users (username, password) VALUES (?, ?)');
            $stmt->execute([$username, $hash]);
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
    <title><?php echo $isRegister ? t('register') : t('login'); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f5f7;
            padding: 60px 15px;
        }

        .box {
            max-width: 340px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #e3e3e3;
            border-radius: 8px;
            padding: 20px;
        }

        h2 {
            margin-top: 0;
        }

        input {
            width: 100%;
            box-sizing: border-box;
            padding: 9px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button {
            width: 100%;
            padding: 10px;
            border: 0;
            border-radius: 5px;
            background: #2d6cdf;
            color: #fff;
            cursor: pointer;
        }

        .error {
            background: #fdecea;
            color: #b3261e;
            padding: 9px;
            border-radius: 5px;
            margin-bottom: 12px;
        }

        a {
            color: #2d6cdf;
            display: block;
            text-align: center;
            margin-top: 14px;
            text-decoration: none;
        }

        .lang-select {
            width: auto;
            display: inline-block;
            padding: 5px 8px;
            margin: 0;
            font-size: 13px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background: #fff;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="box">
        <div style="text-align:right;margin-bottom:12px;"><?php echo langSelect(); ?></div>
        <h2><?php echo $isRegister ? t('register') : t('login'); ?></h2>

        <?php if ($error) { ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php } ?>

        <form method="post">
            <input type="text" name="username" placeholder="<?php echo t('username'); ?>" value="<?php echo htmlspecialchars($oldUsername); ?>">
            <input type="password" name="password" placeholder="<?php echo t('password'); ?>">
            <button type="submit"><?php echo $isRegister ? t('create_account') : t('login'); ?></button>
        </form>

        <?php if ($isRegister) { ?>
            <a href="auth.php"><?php echo t('have_account'); ?></a>
        <?php } else { ?>
            <a href="auth.php?action=register"><?php echo t('no_account'); ?></a>
        <?php } ?>
    </div>
</body>
</html>
