<?php
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if ($action === 'add' && $title !== '') {
        $stmt = $db->prepare("INSERT INTO memos (user_id, title, content, created_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $title, $content, date('Y-m-d H:i:s')]);
    }

    if ($action === 'edit' && $title !== '') {
        $stmt = $db->prepare("UPDATE memos SET title = ?, content = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$title, $content, $_POST['id'], $userId]);
    }

    if ($action === 'delete') {
        $stmt = $db->prepare("DELETE FROM memos WHERE id = ? AND user_id = ?");
        $stmt->execute([$_POST['id'], $userId]);
    }

    header('Location: index.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM memos WHERE user_id = ? ORDER BY id DESC");
$stmt->execute([$userId]);
$memos = $stmt->fetchAll();

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM memos WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['edit'], $userId]);
    $edit = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memo App</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f5f7;
            color: #333;
            margin: 0;
            padding: 30px 15px;
        }

        .container {
            max-width: 640px;
            margin: 0 auto;
        }

        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        h1 {
            margin: 0;
        }

        h3 {
            margin: 0 0 12px;
        }

        .box {
            background: #fff;
            border: 1px solid #e3e3e3;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
        }

        input, textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 9px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
        }

        textarea {
            height: 90px;
            resize: vertical;
        }

        button {
            padding: 8px 14px;
            border: 0;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
        }

        .btn {
            background: #2d6cdf;
            color: #fff;
        }

        .btn-gray {
            background: #e0e0e0;
            color: #333;
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .date {
            color: #999;
            font-size: 12px;
            margin: 8px 0 12px;
        }

        .link {
            color: #2d6cdf;
            text-decoration: none;
            margin-right: 10px;
        }

        .hello {
            font-size: 14px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="top">
        <h1>Memo App</h1>
        <div class="hello">
            Hello, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
            <a class="link" href="auth.php?action=logout">Logout</a>
        </div>
    </div>

    <div class="box">
        <form method="post">
            <?php if ($edit) { ?>
                <h3>Edit memo</h3>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?php echo $edit['id']; ?>">
                <input type="text" name="title" value="<?php echo htmlspecialchars($edit['title']); ?>" placeholder="Title">
                <textarea name="content" placeholder="Content"><?php echo htmlspecialchars($edit['content']); ?></textarea>
                <div class="actions">
                    <button type="submit" class="btn">Update</button>
                    <a class="link" href="index.php">Cancel</a>
                </div>
            <?php } else { ?>
                <h3>Add new memo</h3>
                <input type="hidden" name="action" value="add">
                <input type="text" name="title" placeholder="Title">
                <textarea name="content" placeholder="Content"></textarea>
                <button type="submit" class="btn">Save</button>
            <?php } ?>
        </form>
    </div>

    <?php if (!$memos) { ?>
        <div class="box">No memos yet.</div>
    <?php } ?>

    <?php foreach ($memos as $memo) { ?>
        <div class="box">
            <h3><?php echo htmlspecialchars($memo['title']); ?></h3>
            <p><?php echo nl2br(htmlspecialchars($memo['content'])); ?></p>
            <div class="date"><?php echo $memo['created_at']; ?></div>
            <div class="actions">
                <a class="link" href="index.php?edit=<?php echo $memo['id']; ?>">Edit</a>
                <form method="post">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo $memo['id']; ?>">
                    <button type="button" class="btn-gray" onclick="confirmDelete(this)">Delete</button>
                </form>
            </div>
        </div>
    <?php } ?>
</div>
<script>
function confirmDelete(button) {
    if (button.innerText === 'Delete') {
        button.innerText = 'Sure?';
    } else {
        button.form.submit();
    }
}
</script>
</body>
</html>
