<?php
require 'db.php';

function getRepeatModeOptions()
{
    return [
        'none' => 'Do not repeat',
        'daily' => 'Every day',
        'weekly' => 'Every week',
        'monthly' => 'Every month',
        'yearly' => 'Every year',
        'interval' => 'Every few minutes / hours / days',
        'weekly_days' => 'Pick weekdays',
        'monthly_dates' => 'Pick month dates',
    ];
}

function getIntervalUnitOptions()
{
    return [
        'minute' => 'minutes',
        'hour' => 'hours',
        'day' => 'days',
        'week' => 'weeks',
        'month' => 'months',
        'year' => 'years',
    ];
}

function getWeekdayLabels()
{
    return [
        0 => 'Sun',
        1 => 'Mon',
        2 => 'Tue',
        3 => 'Wed',
        4 => 'Thu',
        5 => 'Fri',
        6 => 'Sat',
    ];
}

function getRepeatHintMap()
{
    return [
        'none' => 'One alert only.',
        'daily' => 'Repeats every day at the same time.',
        'weekly' => 'Repeats every week on the same weekday and time.',
        'monthly' => 'Repeats every month on the same date and time.',
        'yearly' => 'Repeats every year on the same date and time.',
        'interval' => 'Choose an exact interval such as every 15 minutes or every 2 hours.',
        'weekly_days' => 'Pick one or more weekdays. The time stays the same as the reminder time above.',
        'monthly_dates' => 'Pick one or more dates in the month. The time stays the same as the reminder time above.',
    ];
}

function normalizeRepeatMode($value)
{
    $allowed = array_keys(getRepeatModeOptions());

    return in_array($value, $allowed, true) ? $value : 'none';
}

function parseReminderDate($value)
{
    if (! is_string($value) || trim($value) === '') {
        return null;
    }

    $formats = ['Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i'];

    foreach ($formats as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value);
        if ($date instanceof DateTimeImmutable) {
            return $date;
        }
    }

    try {
        return new DateTimeImmutable($value);
    } catch (Exception $e) {
        return null;
    }
}

function formatReminderForInput($value)
{
    $date = parseReminderDate($value);

    return $date ? $date->format('Y-m-d\TH:i') : '';
}

function formatReminderForDisplay($value)
{
    $date = parseReminderDate($value);

    return $date ? $date->format('Y-m-d H:i') : '';
}

function decodeRepeatConfig($value)
{
    if (! is_string($value) || trim($value) === '') {
        return [];
    }

    $decoded = json_decode($value, true);

    return is_array($decoded) ? $decoded : [];
}

function encodeRepeatConfig(array $config)
{
    return $config ? json_encode($config, JSON_UNESCAPED_SLASHES) : '';
}

function normalizeIntervalUnit($value)
{
    $allowed = array_keys(getIntervalUnitOptions());

    return in_array($value, $allowed, true) ? $value : 'hour';
}

function normalizeNumericSelection($values, $min, $max)
{
    $normalized = [];

    foreach ((array) $values as $value) {
        $number = (int) $value;
        if ($number >= $min && $number <= $max) {
            $normalized[$number] = $number;
        }
    }

    $result = array_values($normalized);
    sort($result);

    return $result;
}

function normalizeRepeatPattern($mode, array $rawConfig, $remindAt)
{
    $mode = normalizeRepeatMode($mode);
    $reminder = parseReminderDate($remindAt);

    if (! $reminder || $mode === 'none') {
        return [
            'mode' => 'none',
            'config' => [],
        ];
    }

    if ($mode === 'interval') {
        $value = (int) ($rawConfig['interval_value'] ?? $rawConfig['value'] ?? 1);
        $value = max(1, min(999, $value));

        return [
            'mode' => 'interval',
            'config' => [
                'value' => $value,
                'unit' => normalizeIntervalUnit($rawConfig['interval_unit'] ?? $rawConfig['unit'] ?? 'hour'),
            ],
        ];
    }

    if ($mode === 'weekly_days') {
        $weekdays = normalizeNumericSelection($rawConfig['repeat_weekdays'] ?? $rawConfig['weekdays'] ?? [], 0, 6);
        if (! $weekdays) {
            $weekdays = [(int) $reminder->format('w')];
        }

        return [
            'mode' => 'weekly_days',
            'config' => ['weekdays' => $weekdays],
        ];
    }

    if ($mode === 'monthly_dates') {
        $monthdays = normalizeNumericSelection($rawConfig['repeat_monthdays'] ?? $rawConfig['monthdays'] ?? [], 1, 31);
        if (! $monthdays) {
            $monthdays = [(int) $reminder->format('j')];
        }

        return [
            'mode' => 'monthly_dates',
            'config' => ['monthdays' => $monthdays],
        ];
    }

    return [
        'mode' => $mode,
        'config' => [],
    ];
}

function normalizeStoredRepeatPattern($mode, $repeatConfig, $remindAt)
{
    return normalizeRepeatPattern($mode, decodeRepeatConfig($repeatConfig), $remindAt);
}

function pluralizeUnit($unit, $count)
{
    $labels = getIntervalUnitOptions();
    $label = $labels[$unit] ?? $unit;

    if ($count === 1 && substr($label, -1) === 's') {
        return substr($label, 0, -1);
    }

    return $label;
}

function getRepeatLabel($mode, array $config)
{
    if ($mode === 'none') {
        return '';
    }

    if ($mode === 'daily') {
        return 'Every day';
    }

    if ($mode === 'weekly') {
        return 'Every week';
    }

    if ($mode === 'monthly') {
        return 'Every month';
    }

    if ($mode === 'yearly') {
        return 'Every year';
    }

    if ($mode === 'interval') {
        $value = max(1, (int) ($config['value'] ?? 1));
        $unit = normalizeIntervalUnit($config['unit'] ?? 'hour');

        return 'Every '.$value.' '.pluralizeUnit($unit, $value);
    }

    if ($mode === 'weekly_days') {
        $labels = getWeekdayLabels();
        $parts = [];

        foreach ((array) ($config['weekdays'] ?? []) as $weekday) {
            if (isset($labels[$weekday])) {
                $parts[] = $labels[$weekday];
            }
        }

        return $parts ? 'Every '.implode(', ', $parts) : 'Weekly pattern';
    }

    if ($mode === 'monthly_dates') {
        $parts = normalizeNumericSelection($config['monthdays'] ?? [], 1, 31);

        return $parts ? 'Monthly on '.implode(', ', $parts) : 'Monthly pattern';
    }

    return '';
}

function getRepeatSummaryText($remindAt, $repeatLabel)
{
    $text = formatReminderForDisplay($remindAt);
    if ($repeatLabel !== '') {
        $text .= ' | '.$repeatLabel;
    }

    return $text;
}

function getNextIntervalDate(DateTimeImmutable $current, DateTimeImmutable $now, $value, $unit)
{
    $value = max(1, (int) $value);
    $unit = normalizeIntervalUnit($unit);

    if ($current > $now) {
        return $current;
    }

    if (in_array($unit, ['minute', 'hour', 'day', 'week'], true)) {
        $secondsMap = [
            'minute' => 60,
            'hour' => 3600,
            'day' => 86400,
            'week' => 604800,
        ];

        $stepSeconds = $secondsMap[$unit] * $value;
        $diffSeconds = max(0, $now->getTimestamp() - $current->getTimestamp());
        $steps = intdiv($diffSeconds, $stepSeconds) + 1;
        $totalUnits = $steps * $value;

        return $current->modify('+'.$totalUnits.' '.$unit.($totalUnits === 1 ? '' : 's'));
    }

    $candidate = $current;
    do {
        $candidate = $candidate->modify('+'.$value.' '.$unit.($value === 1 ? '' : 's'));
    } while ($candidate <= $now);

    return $candidate;
}

function getNextWeeklyDayDate(DateTimeImmutable $current, DateTimeImmutable $now, array $weekdays)
{
    $weekdays = normalizeNumericSelection($weekdays, 0, 6);

    if (! $weekdays) {
        return null;
    }

    $candidate = $current;

    for ($i = 0; $i < 370; $i++) {
        if ($candidate > $now && in_array((int) $candidate->format('w'), $weekdays, true)) {
            return $candidate;
        }

        $candidate = $candidate->modify('+1 day');
    }

    return null;
}

function getNextMonthlyDate(DateTimeImmutable $current, DateTimeImmutable $now, array $monthdays)
{
    $monthdays = normalizeNumericSelection($monthdays, 1, 31);

    if (! $monthdays) {
        return null;
    }

    $candidate = $current;

    for ($i = 0; $i < 800; $i++) {
        if ($candidate > $now && in_array((int) $candidate->format('j'), $monthdays, true)) {
            return $candidate;
        }

        $candidate = $candidate->modify('+1 day');
    }

    return null;
}

function getNextReminderAt($value, $repeatMode, array $repeatConfig = [], ?DateTimeImmutable $now = null)
{
    $current = parseReminderDate($value);

    if (! $current) {
        return null;
    }

    $now = $now ?: new DateTimeImmutable('now');
    $repeatMode = normalizeRepeatMode($repeatMode);

    if ($repeatMode === 'none') {
        return null;
    }

    if ($repeatMode === 'daily') {
        return getNextIntervalDate($current, $now, 1, 'day')->format('Y-m-d\TH:i');
    }

    if ($repeatMode === 'weekly') {
        return getNextIntervalDate($current, $now, 1, 'week')->format('Y-m-d\TH:i');
    }

    if ($repeatMode === 'monthly') {
        return getNextIntervalDate($current, $now, 1, 'month')->format('Y-m-d\TH:i');
    }

    if ($repeatMode === 'yearly') {
        return getNextIntervalDate($current, $now, 1, 'year')->format('Y-m-d\TH:i');
    }

    if ($repeatMode === 'interval') {
        $next = getNextIntervalDate($current, $now, $repeatConfig['value'] ?? 1, $repeatConfig['unit'] ?? 'hour');

        return $next ? $next->format('Y-m-d\TH:i') : null;
    }

    if ($repeatMode === 'weekly_days') {
        $next = getNextWeeklyDayDate($current, $now, $repeatConfig['weekdays'] ?? []);

        return $next ? $next->format('Y-m-d\TH:i') : null;
    }

    if ($repeatMode === 'monthly_dates') {
        $next = getNextMonthlyDate($current, $now, $repeatConfig['monthdays'] ?? []);

        return $next ? $next->format('Y-m-d\TH:i') : null;
    }

    return null;
}

if (! isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

$userId = $_SESSION['user_id'];
$repeatModeOptions = getRepeatModeOptions();
$intervalUnitOptions = getIntervalUnitOptions();
$weekdayLabels = getWeekdayLabels();
$repeatHints = getRepeatHintMap();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $remind = formatReminderForInput(trim($_POST['remind_at'] ?? ''));
    $repeatPattern = normalizeRepeatPattern(
        $_POST['repeat_mode'] ?? 'none',
        [
            'interval_value' => $_POST['repeat_interval_value'] ?? 1,
            'interval_unit' => $_POST['repeat_interval_unit'] ?? 'hour',
            'repeat_weekdays' => $_POST['repeat_weekdays'] ?? [],
            'repeat_monthdays' => $_POST['repeat_monthdays'] ?? [],
        ],
        $remind
    );
    $repeatMode = $repeatPattern['mode'];
    $repeatConfig = encodeRepeatConfig($repeatPattern['config']);

    if ($action === 'reschedule') {
        header('Content-Type: application/json');

        $stmt = $db->prepare('SELECT remind_at, repeat_mode, repeat_config FROM memos WHERE id = ? AND user_id = ?');
        $stmt->execute([$_POST['id'] ?? 0, $userId]);
        $memo = $stmt->fetch();

        if (! $memo) {
            echo json_encode(['success' => false]);
            exit;
        }

        $currentRemindAt = formatReminderForInput($memo['remind_at'] ?? '');
        $storedPattern = normalizeStoredRepeatPattern($memo['repeat_mode'] ?? 'none', $memo['repeat_config'] ?? '', $currentRemindAt);
        $currentRepeatMode = $storedPattern['mode'];
        $currentRepeatConfig = $storedPattern['config'];
        $currentRepeatLabel = getRepeatLabel($currentRepeatMode, $currentRepeatConfig);
        $currentReminderTime = parseReminderDate($currentRemindAt);
        $now = new DateTimeImmutable('now');

        if ($currentRemindAt === '' || $currentRepeatMode === 'none' || ! $currentReminderTime || $currentReminderTime > $now) {
            echo json_encode([
                'success' => true,
                'remind_at' => $currentRemindAt,
                'repeat_mode' => $currentRepeatMode,
                'repeat_label' => $currentRepeatLabel,
            ]);
            exit;
        }

        $nextRemindAt = getNextReminderAt($currentRemindAt, $currentRepeatMode, $currentRepeatConfig, $now);

        if (! $nextRemindAt || $nextRemindAt === $currentRemindAt) {
            echo json_encode([
                'success' => true,
                'remind_at' => $currentRemindAt,
                'repeat_mode' => $currentRepeatMode,
                'repeat_label' => $currentRepeatLabel,
            ]);
            exit;
        }

        $stmt = $db->prepare('UPDATE memos SET remind_at = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$nextRemindAt, $_POST['id'] ?? 0, $userId]);

        echo json_encode([
            'success' => true,
            'remind_at' => $nextRemindAt,
            'repeat_mode' => $currentRepeatMode,
            'repeat_label' => $currentRepeatLabel,
        ]);
        exit;
    }

    if ($action === 'add' && $title !== '') {
        $stmt = $db->prepare('INSERT INTO memos (user_id, title, content, remind_at, repeat_mode, repeat_config, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $title, $content, $remind, $repeatMode, $repeatConfig, date('Y-m-d H:i:s')]);
    }

    if ($action === 'edit' && $title !== '') {
        $stmt = $db->prepare('UPDATE memos SET title = ?, content = ?, remind_at = ?, repeat_mode = ?, repeat_config = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$title, $content, $remind, $repeatMode, $repeatConfig, $_POST['id'], $userId]);
    }

    if ($action === 'delete') {
        $stmt = $db->prepare('DELETE FROM memos WHERE id = ? AND user_id = ?');
        $stmt->execute([$_POST['id'], $userId]);
    }

    header('Location: index.php');
    exit;
}

$stmt = $db->prepare('SELECT * FROM memos WHERE user_id = ? ORDER BY id DESC');
$stmt->execute([$userId]);
$memos = $stmt->fetchAll();

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare('SELECT * FROM memos WHERE id = ? AND user_id = ?');
    $stmt->execute([$_GET['edit'], $userId]);
    $edit = $stmt->fetch();
}

$editPattern = $edit ? normalizeStoredRepeatPattern($edit['repeat_mode'] ?? 'none', $edit['repeat_config'] ?? '', $edit['remind_at'] ?? '') : ['mode' => 'none', 'config' => []];
$editIntervalValue = (int) ($editPattern['config']['value'] ?? 1);
$editIntervalUnit = normalizeIntervalUnit($editPattern['config']['unit'] ?? 'hour');
$editWeekdays = normalizeNumericSelection($editPattern['config']['weekdays'] ?? [], 0, 6);
$editMonthdays = normalizeNumericSelection($editPattern['config']['monthdays'] ?? [], 1, 31);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memo App</title>
    <style>
        :root {
            --bg: #eef2f6;
            --panel: #ffffff;
            --border: #d9e1ea;
            --text: #203040;
            --muted: #607182;
            --primary: #1d5fd1;
            --primary-soft: #eaf2ff;
            --accent: #c96a16;
            --accent-soft: #fff3e5;
            --shadow: 0 16px 40px rgba(31, 52, 73, 0.08);
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: "Segoe UI", "Trebuchet MS", sans-serif;
            background:
                radial-gradient(circle at top left, rgba(29, 95, 209, 0.12), transparent 32%),
                linear-gradient(180deg, #f7f9fc 0%, var(--bg) 100%);
            color: var(--text);
            margin: 0;
            padding: 30px 15px;
        }

        .container {
            max-width: 760px;
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
            font-size: 32px;
            letter-spacing: 0.02em;
        }

        h3 {
            margin: 0 0 12px;
        }

        .box {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 18px;
            margin-bottom: 16px;
            box-shadow: var(--shadow);
        }

        input, textarea, select {
            width: 100%;
            padding: 10px 12px;
            margin-bottom: 10px;
            border: 1px solid #cbd4de;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            background: #fff;
            color: var(--text);
        }

        input:focus, textarea:focus, select:focus {
            outline: 2px solid rgba(29, 95, 209, 0.16);
            border-color: var(--primary);
        }

        textarea {
            height: 90px;
            resize: vertical;
        }

        button {
            padding: 10px 16px;
            border: 0;
            border-radius: 10px;
            font-size: 14px;
            cursor: pointer;
            transition: transform 0.16s ease, opacity 0.16s ease;
        }

        button:hover {
            transform: translateY(-1px);
        }

        .btn {
            background: var(--primary);
            color: #fff;
        }

        .btn-gray {
            background: #e7edf3;
            color: var(--text);
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .date {
            color: #8191a1;
            font-size: 12px;
            margin: 8px 0 12px;
        }

        .link {
            color: var(--primary);
            text-decoration: none;
            margin-right: 10px;
            font-weight: 600;
        }

        .hello {
            font-size: 14px;
        }

        .lbl {
            display: block;
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 6px;
            font-weight: 600;
        }

        .remind {
            color: var(--accent);
            font-size: 13px;
            margin: 8px 0;
            padding: 10px 12px;
            border-radius: 12px;
            background: var(--accent-soft);
            border: 1px solid rgba(201, 106, 22, 0.18);
        }

        .repeat-card {
            margin-bottom: 14px;
            padding: 14px;
            border-radius: 14px;
            border: 1px solid #d8e2ef;
            background: linear-gradient(180deg, #fbfdff 0%, #f3f7fb 100%);
        }

        .repeat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 10px;
            margin-bottom: 10px;
        }

        .repeat-panel {
            display: none;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #d0d9e2;
        }

        .repeat-help {
            margin: 0;
            font-size: 12px;
            color: var(--muted);
            line-height: 1.45;
        }

        .stack-note {
            display: inline-block;
            margin-bottom: 10px;
            padding: 6px 10px;
            border-radius: 999px;
            background: var(--primary-soft);
            color: var(--primary);
            font-size: 12px;
            font-weight: 600;
        }

        .interval-row {
            display: grid;
            grid-template-columns: minmax(110px, 150px) 1fr;
            gap: 10px;
        }

        .chip-grid {
            display: grid;
            gap: 8px;
        }

        .weekday-grid {
            grid-template-columns: repeat(7, minmax(0, 1fr));
        }

        .monthday-grid {
            grid-template-columns: repeat(auto-fit, minmax(54px, 1fr));
            max-height: 188px;
            overflow: auto;
            padding-right: 2px;
        }

        .chip {
            position: relative;
        }

        .chip input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .chip span {
            display: block;
            padding: 10px 8px;
            border-radius: 12px;
            text-align: center;
            border: 1px solid #ccd7e3;
            background: #fff;
            color: var(--text);
            font-size: 13px;
            cursor: pointer;
            transition: all 0.18s ease;
        }

        .chip input:checked + span {
            border-color: var(--primary);
            background: var(--primary-soft);
            color: var(--primary);
            font-weight: 700;
            transform: translateY(-1px);
        }

        .chip input:focus + span {
            outline: 2px solid rgba(29, 95, 209, 0.16);
        }

        .empty-state {
            color: var(--muted);
        }

        @media (max-width: 680px) {
            body {
                padding: 18px 12px;
            }

            .top {
                flex-direction: column;
                align-items: flex-start;
            }

            .weekday-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }

            .interval-row {
                grid-template-columns: 1fr;
            }
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
        <form method="post" data-repeat-form>
            <?php if ($edit) { ?>
                <h3>Edit memo</h3>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?php echo $edit['id']; ?>">
                <input type="text" name="title" value="<?php echo htmlspecialchars($edit['title']); ?>" placeholder="Title">
                <textarea name="content" placeholder="Content"><?php echo htmlspecialchars($edit['content']); ?></textarea>
                <label class="lbl">Remind me at (optional)</label>
                <input type="datetime-local" name="remind_at" value="<?php echo htmlspecialchars(formatReminderForInput($edit['remind_at'] ?? '')); ?>">
                <div class="repeat-card">
                    <span class="stack-note">Flexible repeat scheduler</span>
                    <label class="lbl">Repeat pattern</label>
                    <select name="repeat_mode" data-repeat-mode>
                        <?php foreach ($repeatModeOptions as $value => $label) { ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $editPattern['mode'] === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php } ?>
                    </select>
                    <p class="repeat-help" data-repeat-hint><?php echo htmlspecialchars($repeatHints[$editPattern['mode']] ?? $repeatHints['none']); ?></p>

                    <div class="repeat-panel" data-repeat-panel="interval">
                        <label class="lbl">Repeat every</label>
                        <div class="interval-row">
                            <input type="number" min="1" max="999" name="repeat_interval_value" value="<?php echo $editIntervalValue; ?>" data-repeat-input>
                            <select name="repeat_interval_unit" data-repeat-input>
                                <?php foreach ($intervalUnitOptions as $value => $label) { ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $editIntervalUnit === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class="repeat-panel" data-repeat-panel="weekly_days">
                        <label class="lbl">Choose weekdays</label>
                        <div class="chip-grid weekday-grid">
                            <?php foreach ($weekdayLabels as $weekdayValue => $weekdayLabel) { ?>
                                <label class="chip">
                                    <input type="checkbox" name="repeat_weekdays[]" value="<?php echo $weekdayValue; ?>" <?php echo in_array($weekdayValue, $editWeekdays, true) ? 'checked' : ''; ?> data-repeat-input>
                                    <span><?php echo htmlspecialchars($weekdayLabel); ?></span>
                                </label>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="repeat-panel" data-repeat-panel="monthly_dates">
                        <label class="lbl">Choose dates in the month</label>
                        <div class="chip-grid monthday-grid">
                            <?php for ($day = 1; $day <= 31; $day++) { ?>
                                <label class="chip">
                                    <input type="checkbox" name="repeat_monthdays[]" value="<?php echo $day; ?>" <?php echo in_array($day, $editMonthdays, true) ? 'checked' : ''; ?> data-repeat-input>
                                    <span><?php echo $day; ?></span>
                                </label>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <div class="actions">
                    <button type="submit" class="btn">Update</button>
                    <a class="link" href="index.php">Cancel</a>
                </div>
            <?php } else { ?>
                <h3>Add new memo</h3>
                <input type="hidden" name="action" value="add">
                <input type="text" name="title" placeholder="Title">
                <textarea name="content" placeholder="Content"></textarea>
                <label class="lbl">Remind me at (optional)</label>
                <input type="datetime-local" name="remind_at">
                <div class="repeat-card">
                    <span class="stack-note">Flexible repeat scheduler</span>
                    <label class="lbl">Repeat pattern</label>
                    <select name="repeat_mode" data-repeat-mode>
                        <?php foreach ($repeatModeOptions as $value => $label) { ?>
                            <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option>
                        <?php } ?>
                    </select>
                    <p class="repeat-help" data-repeat-hint><?php echo htmlspecialchars($repeatHints['none']); ?></p>

                    <div class="repeat-panel" data-repeat-panel="interval">
                        <label class="lbl">Repeat every</label>
                        <div class="interval-row">
                            <input type="number" min="1" max="999" name="repeat_interval_value" value="1" data-repeat-input>
                            <select name="repeat_interval_unit" data-repeat-input>
                                <?php foreach ($intervalUnitOptions as $value => $label) { ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $value === 'hour' ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class="repeat-panel" data-repeat-panel="weekly_days">
                        <label class="lbl">Choose weekdays</label>
                        <div class="chip-grid weekday-grid">
                            <?php foreach ($weekdayLabels as $weekdayValue => $weekdayLabel) { ?>
                                <label class="chip">
                                    <input type="checkbox" name="repeat_weekdays[]" value="<?php echo $weekdayValue; ?>" data-repeat-input>
                                    <span><?php echo htmlspecialchars($weekdayLabel); ?></span>
                                </label>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="repeat-panel" data-repeat-panel="monthly_dates">
                        <label class="lbl">Choose dates in the month</label>
                        <div class="chip-grid monthday-grid">
                            <?php for ($day = 1; $day <= 31; $day++) { ?>
                                <label class="chip">
                                    <input type="checkbox" name="repeat_monthdays[]" value="<?php echo $day; ?>" data-repeat-input>
                                    <span><?php echo $day; ?></span>
                                </label>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn">Save</button>
            <?php } ?>
        </form>
    </div>

    <?php if (! $memos) { ?>
        <div class="box empty-state">No memos yet.</div>
    <?php } ?>

    <?php foreach ($memos as $memo) { ?>
        <?php
        $memoRemindAt = formatReminderForInput($memo['remind_at'] ?? '');
        $memoPattern = normalizeStoredRepeatPattern($memo['repeat_mode'] ?? 'none', $memo['repeat_config'] ?? '', $memoRemindAt);
        $memoRepeatMode = $memoPattern['mode'];
        $memoRepeatLabel = getRepeatLabel($memoRepeatMode, $memoPattern['config']);
        ?>
        <div class="box">
            <h3><?php echo htmlspecialchars($memo['title']); ?></h3>
            <p><?php echo nl2br(htmlspecialchars($memo['content'])); ?></p>
            <?php if ($memoRemindAt !== '') { ?>
                <div
                    class="remind"
                    data-id="<?php echo (int) $memo['id']; ?>"
                    data-remind="<?php echo htmlspecialchars($memoRemindAt); ?>"
                    data-repeat-mode="<?php echo htmlspecialchars($memoRepeatMode); ?>"
                    data-repeat-label="<?php echo htmlspecialchars($memoRepeatLabel); ?>"
                    data-title="<?php echo htmlspecialchars($memo['title']); ?>"
                >
                    &#9200; <?php echo htmlspecialchars(getRepeatSummaryText($memoRemindAt, $memoRepeatLabel)); ?>
                </div>
            <?php } ?>
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

if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission();
}

var alreadyNotified = [];
var repeatHints = {
    none: 'One alert only.',
    daily: 'Repeats every day at the same time.',
    weekly: 'Repeats every week on the same weekday and time.',
    monthly: 'Repeats every month on the same date and time.',
    yearly: 'Repeats every year on the same date and time.',
    interval: 'Choose an exact interval such as every 15 minutes or every 2 hours.',
    weekly_days: 'Pick one or more weekdays. The time stays the same as the reminder time above.',
    monthly_dates: 'Pick one or more dates in the month. The time stays the same as the reminder time above.'
};

function formatReminderText(remindAt, repeatLabel) {
    return '\u23F0 ' + remindAt.replace('T', ' ') + (repeatLabel ? ' | ' + repeatLabel : '');
}

function updateReminderElement(item, remindAt, repeatMode, repeatLabel) {
    item.setAttribute('data-remind', remindAt);
    item.setAttribute('data-repeat-mode', repeatMode);
    item.setAttribute('data-repeat-label', repeatLabel || '');
    item.textContent = formatReminderText(remindAt, repeatLabel || '');
}

function rescheduleReminder(item) {
    var memoId = item.getAttribute('data-id');
    var repeatMode = item.getAttribute('data-repeat-mode') || 'none';

    if (!memoId || repeatMode === 'none') {
        return;
    }

    fetch('index.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: new URLSearchParams({
            action: 'reschedule',
            id: memoId
        })
    })
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            if (!data || !data.success || !data.remind_at) {
                return;
            }

            updateReminderElement(item, data.remind_at, data.repeat_mode || repeatMode, data.repeat_label || '');
        })
        .catch(function (error) {
            console.error('Failed to reschedule reminder', error);
        });
}

function toggleRepeatOptions(form) {
    var modeInput = form.querySelector('[data-repeat-mode]');
    var remindInput = form.querySelector('[name="remind_at"]');
    var hint = form.querySelector('[data-repeat-hint]');
    var mode = modeInput ? modeInput.value : 'none';
    var hasReminder = remindInput && remindInput.value !== '';

    form.querySelectorAll('[data-repeat-panel]').forEach(function (panel) {
        var isActive = panel.getAttribute('data-repeat-panel') === mode;
        panel.style.display = isActive ? 'block' : 'none';

        panel.querySelectorAll('[data-repeat-input]').forEach(function (input) {
            input.disabled = !isActive;
        });
    });

    if (hint) {
        if (!hasReminder && mode !== 'none') {
            hint.textContent = 'Set a reminder time first. The repeat settings will be saved and start from that time.';
        } else {
            hint.textContent = repeatHints[mode] || repeatHints.none;
        }
    }
}

document.querySelectorAll('[data-repeat-form]').forEach(function (form) {
    var modeInput = form.querySelector('[data-repeat-mode]');
    var remindInput = form.querySelector('[name="remind_at"]');

    if (modeInput) {
        modeInput.addEventListener('change', function () {
            toggleRepeatOptions(form);
        });
    }

    if (remindInput) {
        remindInput.addEventListener('input', function () {
            toggleRepeatOptions(form);
        });
    }

    toggleRepeatOptions(form);
});

function checkReminders() {
    var now = new Date();
    var items = document.querySelectorAll('.remind');

    items.forEach(function (item) {
        var memoId = item.getAttribute('data-id');
        var remindAt = item.getAttribute('data-remind');
        var repeatMode = item.getAttribute('data-repeat-mode') || 'none';
        var title = item.getAttribute('data-title');

        if (!remindAt) {
            return;
        }

        var remindTime = new Date(remindAt);
        var notifyKey = memoId + ':' + remindAt;

        if (now >= remindTime && alreadyNotified.indexOf(notifyKey) === -1) {
            alreadyNotified.push(notifyKey);

            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification('Memo reminder', { body: title });
            } else {
                alert('Reminder: ' + title);
            }

            if (repeatMode !== 'none') {
                rescheduleReminder(item);
            }
        }
    });
}

setInterval(checkReminders, 10000);
checkReminders();
</script>
</body>
</html>
