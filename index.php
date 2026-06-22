<?php
// connect sqlite
$db = new PDO('sqlite:' . __DIR__ . '/memo.sqlite');

// create table if not exists
$db->exec("CREATE TABLE IF NOT EXISTS memos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    content TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
)");

// handle form on submit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];

    if ($action == 'add') {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        if ($title != '') {
            $stmt = $db->prepare("INSERT INTO memos (title, content) VALUES (?, ?)");
            $stmt->execute([$title, $content]);
        }
    }

    if ($action == 'edit') {
        $id = $_POST['id'];
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        if ($title != '') {
            $stmt = $db->prepare("UPDATE memos SET title = ?, content = ? WHERE id = ?");
            $stmt->execute([$title, $content, $id]);
        }
    }

    if ($action == 'delete') {
        $id = $_POST['id'];
        $stmt = $db->prepare("DELETE FROM memos WHERE id = ?");
        $stmt->execute([$id]);
    }

    // reload page so the form is not sent twice
    header('Location: index.php');
    exit;
}

// get all memos
$memos = $db->query("SELECT * FROM memos ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// if editing, load that memo
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM memos WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
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
            max-width: 600px;
            margin: 0 auto;
        }

        h1 {
            margin-top: 0;
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

        .btn:hover {
            background: #2559bd;
        }

        .btn-gray {
            background: #e0e0e0;
            color: #333;
        }

        .btn-gray:hover {
            background: #d2d2d2;
        }

        .date {
            color: #999;
            font-size: 12px;
            margin: 8px 0 12px;
        }

        a.link {
            color: #2d6cdf;
            text-decoration: none;
            font-size: 14px;
            margin-right: 10px;
        }

        a.link:hover {
            text-decoration: underline;
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }
    </style>
</head>
<body>
<div class="container">

    <h1>Memo App</h1>

    <!-- add / edit form -->
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

    <!-- memo list -->
    <?php if (count($memos) == 0) { ?>
        <div class="box">No memos yet.</div>
    <?php } ?>

    <?php foreach ($memos as $memo) { ?>
        <div class="box">
            <h3><?php echo htmlspecialchars($memo['title']); ?></h3>
            <p><?php echo nl2br(htmlspecialchars($memo['content'])); ?></p>
            <div class="date"><?php echo $memo['created_at']; ?></div>

            <div class="actions">
                <a class="link" href="index.php?edit=<?php echo $memo['id']; ?>">Edit</a>
                <form method="post" onsubmit="return confirm('Delete this memo?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo $memo['id']; ?>">
                    <button type="submit" class="btn-gray">Delete</button>
                </form>
            </div>
        </div>
    <?php } ?>

</div>
</body>
</html>