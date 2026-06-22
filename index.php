<?php
// ket noi sqlite
$db = new PDO('sqlite:' . __DIR__ . '/memo.sqlite');

// tao bang neu chua co
$db->exec("CREATE TABLE IF NOT EXISTS memos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    content TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
)");

// xu ly form khi bam nut
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

    // load lai trang cho khoi gui form 2 lan
    header('Location: index.php');
    exit;
}

// lay danh sach memo
$memos = $db->query("SELECT * FROM memos ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// neu dang sua thi lay memo can sua
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM memos WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Memo App</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        input, textarea, button {
            font-size: 14px;
        }

        input, textarea {
            width: 300px;
            display: block;
            margin-bottom: 8px;
            padding: 5px;
        }

        textarea {
            height: 80px;
        }

        .memo {
            border: 1px solid #ccc;
            padding: 10px;
            margin-bottom: 10px;
        }

        .date {
            color: gray;
            font-size: 12px;
            margin-bottom: 8px;
        }

        .inline-form {
            display: inline;
        }
    </style>
</head>
<body>

<h1>Memo App</h1>

<form method="post">
    <?php if ($edit) { ?>
        <h3>Sua memo</h3>
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" value="<?php echo $edit['id']; ?>">
        <input type="text" name="title" value="<?php echo htmlspecialchars($edit['title']); ?>" placeholder="Tieu de">
        <textarea name="content" placeholder="Noi dung"><?php echo htmlspecialchars($edit['content']); ?></textarea>
        <button type="submit">Cap nhat</button>
        <a href="index.php">Huy</a>
    <?php } else { ?>
        <h3>Them memo moi</h3>
        <input type="hidden" name="action" value="add">
        <input type="text" name="title" placeholder="Tieu de">
        <textarea name="content" placeholder="Noi dung"></textarea>
        <button type="submit">Luu</button>
    <?php } ?>
</form>

<hr>

<?php if (count($memos) == 0) { ?>
    <p>Chua co memo nao.</p>
<?php } ?>

<?php foreach ($memos as $memo) { ?>
    <div class="memo">
        <h3><?php echo htmlspecialchars($memo['title']); ?></h3>
        <p><?php echo nl2br(htmlspecialchars($memo['content'])); ?></p>
        <div class="date"><?php echo $memo['created_at']; ?></div>

        <a href="index.php?edit=<?php echo $memo['id']; ?>">Sua</a>

        <form method="post" class="inline-form" onsubmit="return confirm('Xoa memo nay?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?php echo $memo['id']; ?>">
            <button type="submit">Xoa</button>
        </form>
    </div>
<?php } ?>

</body>
</html>
