<?php

declare(strict_types=1);

use App\Csrf;
use App\MemoRepository;
use App\Reminder;
use App\RepeatLabel;

require __DIR__.'/db.php';
$db = memoAppDatabase();
require __DIR__.'/lang.php';

function requestStringValue(mixed $value): string
{
    return is_string($value) ? trim($value) : '';
}

function requestIntValue(mixed $value): int
{
    if (is_int($value)) {
        return $value;
    }

    if (is_string($value) && is_numeric($value)) {
        return (int) $value;
    }

    return 0;
}

/**
 * @return array<int, mixed>
 */
function requestArrayValue(mixed $value): array
{
    return is_array($value) ? array_values($value) : [];
}

function scalarStringValue(mixed $value, string $default = ''): string
{
    return is_scalar($value) ? (string) $value : $default;
}

if (! isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

$userId = requestIntValue($_SESSION['user_id']);

if ($userId <= 0) {
    header('Location: auth.php');
    exit;
}

$sessionUsername = $_SESSION['username'] ?? '';
$sessionUsername = is_string($sessionUsername) ? $sessionUsername : '';
$memos = new MemoRepository($db);

// Translator passed to label helpers so domain code stays decoupled from i18n.
$tr = t(...);

$repeatModeOptions = RepeatLabel::modeOptions($tr);
$intervalUnitOptions = RepeatLabel::intervalUnitOptions($tr);
$weekdayLabels = RepeatLabel::weekdayLabels($tr);
$repeatHints = RepeatLabel::hintMap($tr);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();

    $action = $_POST['action'] ?? '';
    $action = is_string($action) ? $action : '';
    $title = requestStringValue($_POST['title'] ?? null);
    $content = requestStringValue($_POST['content'] ?? null);
    $remind = Reminder::formatReminderForInput(requestStringValue($_POST['remind_at'] ?? null));

    $repeatPattern = Reminder::normalizeRepeatPattern(
        requestStringValue($_POST['repeat_mode'] ?? 'none'),
        [
            'interval_value' => $_POST['repeat_interval_value'] ?? 1,
            'interval_unit' => $_POST['repeat_interval_unit'] ?? 'hour',
            'repeat_weekdays' => requestArrayValue($_POST['repeat_weekdays'] ?? []),
            'repeat_monthdays' => requestArrayValue($_POST['repeat_monthdays'] ?? []),
        ],
        $remind
    );
    $repeatMode = $repeatPattern['mode'];
    $repeatConfig = Reminder::encodeRepeatConfig($repeatPattern['config']);

    if ($action === 'reschedule') {
        header('Content-Type: application/json');

        $memo = $memos->find(requestIntValue($_POST['id'] ?? 0), $userId);

        if ($memo === null) {
            echo json_encode(['success' => false]);
            exit;
        }

        $currentRemindAt = Reminder::formatReminderForInput($memo['remind_at']);
        $storedPattern = Reminder::normalizeStoredRepeatPattern(
            $memo['repeat_mode'],
            $memo['repeat_config'],
            $currentRemindAt
        );
        $currentRepeatMode = $storedPattern['mode'];
        $currentRepeatConfig = $storedPattern['config'];
        $currentRepeatLabel = RepeatLabel::describe($tr, $currentRepeatMode, $currentRepeatConfig);
        $currentReminderTime = Reminder::parseReminderDate($currentRemindAt);
        $now = new DateTimeImmutable('now');

        if ($currentRemindAt === '' || $currentRepeatMode === 'none' || ! $currentReminderTime instanceof DateTimeImmutable || $currentReminderTime > $now) {
            echo json_encode([
                'success' => true,
                'remind_at' => $currentRemindAt,
                'repeat_mode' => $currentRepeatMode,
                'repeat_label' => $currentRepeatLabel,
            ]);
            exit;
        }

        $nextRemindAt = Reminder::getNextReminderAt($currentRemindAt, $currentRepeatMode, $currentRepeatConfig, $now);

        if ($nextRemindAt === null || $nextRemindAt === $currentRemindAt) {
            echo json_encode([
                'success' => true,
                'remind_at' => $currentRemindAt,
                'repeat_mode' => $currentRepeatMode,
                'repeat_label' => $currentRepeatLabel,
            ]);
            exit;
        }

        $memos->updateRemindAt(requestIntValue($_POST['id'] ?? 0), $userId, $nextRemindAt);

        echo json_encode([
            'success' => true,
            'remind_at' => $nextRemindAt,
            'repeat_mode' => $currentRepeatMode,
            'repeat_label' => $currentRepeatLabel,
        ]);
        exit;
    }

    if ($action === 'add' && $title !== '') {
        $memos->create($userId, $title, $content, $remind, $repeatMode, $repeatConfig);
    }

    if ($action === 'edit' && $title !== '') {
        $memos->update(requestIntValue($_POST['id'] ?? 0), $userId, $title, $content, $remind, $repeatMode, $repeatConfig);
    }

    if ($action === 'delete') {
        $memos->delete(requestIntValue($_POST['id'] ?? 0), $userId);
    }

    header('Location: index.php');
    exit;
}

$memoList = $memos->allForUser($userId);

$edit = null;
if (isset($_GET['edit'])) {
    $edit = $memos->find(requestIntValue($_GET['edit']), $userId);
}

$editPattern = $edit !== null
    ? Reminder::normalizeStoredRepeatPattern($edit['repeat_mode'], $edit['repeat_config'], $edit['remind_at'])
    : ['mode' => 'none', 'config' => []];
$editIntervalValue = requestIntValue($editPattern['config']['value'] ?? 1);
$editIntervalUnit = Reminder::normalizeIntervalUnit(scalarStringValue($editPattern['config']['unit'] ?? 'hour', 'hour'));
$editWeekdays = Reminder::normalizeNumericSelection($editPattern['config']['weekdays'] ?? [], 0, 6);
$editMonthdays = Reminder::normalizeNumericSelection($editPattern['config']['monthdays'] ?? [], 1, 31);
$memoCount = count($memoList);
$scheduledCount = 0;
$recurringCount = 0;

foreach ($memoList as $memo) {
    $hasReminder = Reminder::formatReminderForInput($memo['remind_at']) !== '';

    if ($hasReminder) {
        $scheduledCount++;
    }

    if ($memo['repeat_mode'] !== 'none') {
        $recurringCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(currentLang()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(t('app_title')); ?></title>
    <link rel="stylesheet" href="app.css">
</head>
<body class="workspace-page">
<div class="workspace-shell">
    <header class="workspace-header">
        <div>
            <span class="eyebrow"><?php echo htmlspecialchars(t('app_title')); ?></span>
            <h1><?php echo htmlspecialchars(t('workspace_heading')); ?></h1>
            <p class="lead"><?php echo htmlspecialchars(t('workspace_subtitle')); ?></p>
        </div>

        <div class="header-tools">
            <div class="user-chip">
                <?php echo htmlspecialchars(t('hello')); ?>,
                <strong><?php echo htmlspecialchars($sessionUsername); ?></strong>
            </div>
            <?php echo langSelect(); ?>
            <a class="btn btn-ghost btn-small" href="auth.php?action=logout"><?php echo htmlspecialchars(t('logout')); ?></a>
        </div>
    </header>

    <section class="stats-grid" aria-label="<?php echo htmlspecialchars(t('workspace_heading')); ?>">
        <div class="card-panel stat-card">
            <span class="eyebrow"><?php echo htmlspecialchars(t('memo_count_label')); ?></span>
            <span class="stat-value"><?php echo $memoCount; ?></span>
            <span class="stat-label"><?php echo htmlspecialchars(t('memo_list_title')); ?></span>
        </div>
        <div class="card-panel stat-card">
            <span class="eyebrow"><?php echo htmlspecialchars(t('scheduled_count_label')); ?></span>
            <span class="stat-value"><?php echo $scheduledCount; ?></span>
            <span class="stat-label"><?php echo htmlspecialchars(t('remind_label')); ?></span>
        </div>
        <div class="card-panel stat-card">
            <span class="eyebrow"><?php echo htmlspecialchars(t('recurring_count_label')); ?></span>
            <span class="stat-value"><?php echo $recurringCount; ?></span>
            <span class="stat-label"><?php echo htmlspecialchars(t('repeat_scheduler')); ?></span>
        </div>
    </section>

    <div class="workspace-grid">
        <aside class="composer-column">
            <section class="card-panel composer-panel">
                <div class="panel-head">
                    <div>
                        <h2><?php echo htmlspecialchars($edit !== null ? t('edit_memo') : t('add_memo')); ?></h2>
                        <p class="panel-subtitle"><?php echo htmlspecialchars(t('workspace_subtitle')); ?></p>
                    </div>

                    <?php if ($edit !== null) { ?>
                        <a class="btn btn-secondary btn-small" href="index.php"><?php echo htmlspecialchars(t('cancel')); ?></a>
                    <?php } ?>
                </div>

                <form class="stack-form" method="post" data-repeat-form novalidate>
                    <?php echo Csrf::field(); ?>
                    <?php if ($edit !== null) { ?>
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" value="<?php echo (int) $edit['id']; ?>">
                    <?php } else { ?>
                        <input type="hidden" name="action" value="add">
                    <?php } ?>

                    <label class="field" for="memo-title">
                        <span class="field__label"><?php echo htmlspecialchars(t('title')); ?></span>
                        <input
                            id="memo-title"
                            type="text"
                            name="title"
                            value="<?php echo htmlspecialchars((string) ($edit['title'] ?? '')); ?>"
                            placeholder="<?php echo htmlspecialchars(t('title')); ?>"
                        >
                    </label>

                    <label class="field" for="memo-content">
                        <span class="field__label"><?php echo htmlspecialchars(t('content')); ?></span>
                        <textarea id="memo-content" name="content" placeholder="<?php echo htmlspecialchars(t('content')); ?>"><?php echo htmlspecialchars((string) ($edit['content'] ?? '')); ?></textarea>
                    </label>

                    <label class="field" for="memo-remind">
                        <span class="field__label"><?php echo htmlspecialchars(t('remind_label')); ?></span>
                        <input
                            id="memo-remind"
                            type="datetime-local"
                            name="remind_at"
                            value="<?php echo htmlspecialchars(Reminder::formatReminderForInput($edit['remind_at'] ?? '')); ?>"
                        >
                    </label>

                    <div class="repeat-card">
                        <span class="stack-note"><?php echo htmlspecialchars(t('repeat_scheduler')); ?></span>

                        <label class="field" for="repeat-mode">
                            <span class="field__label"><?php echo htmlspecialchars(t('repeat_pattern')); ?></span>
                            <select id="repeat-mode" name="repeat_mode" data-repeat-mode>
                                <?php foreach ($repeatModeOptions as $value => $label) { ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $editPattern['mode'] === $value ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </label>

                        <p class="repeat-help" data-repeat-hint><?php echo htmlspecialchars($repeatHints[$editPattern['mode']] ?? $repeatHints['none']); ?></p>

                        <div class="repeat-panel" data-repeat-panel="interval">
                            <label class="field">
                                <span class="field__label"><?php echo htmlspecialchars(t('repeat_every')); ?></span>
                                <div class="interval-row">
                                    <input
                                        type="number"
                                        min="1"
                                        max="999"
                                        name="repeat_interval_value"
                                        value="<?php echo $edit !== null ? $editIntervalValue : 1; ?>"
                                        data-repeat-input
                                    >
                                    <select name="repeat_interval_unit" data-repeat-input>
                                        <?php foreach ($intervalUnitOptions as $value => $label) { ?>
                                            <?php $selectedUnit = $edit !== null ? $editIntervalUnit : 'hour'; ?>
                                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $selectedUnit === $value ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </label>
                        </div>

                        <div class="repeat-panel" data-repeat-panel="weekly_days">
                            <span class="field__label"><?php echo htmlspecialchars(t('choose_weekdays')); ?></span>
                            <div class="chip-grid weekday-grid">
                                <?php foreach ($weekdayLabels as $weekdayValue => $weekdayLabel) { ?>
                                    <label class="chip">
                                        <input
                                            type="checkbox"
                                            name="repeat_weekdays[]"
                                            value="<?php echo $weekdayValue; ?>"
                                            <?php echo in_array($weekdayValue, $editWeekdays, true) ? 'checked' : ''; ?>
                                            data-repeat-input
                                        >
                                        <span><?php echo htmlspecialchars($weekdayLabel); ?></span>
                                    </label>
                                <?php } ?>
                            </div>
                        </div>

                        <div class="repeat-panel" data-repeat-panel="monthly_dates">
                            <span class="field__label"><?php echo htmlspecialchars(t('choose_monthdays')); ?></span>
                            <div class="chip-grid monthday-grid">
                                <?php for ($day = 1; $day <= 31; $day++) { ?>
                                    <label class="chip">
                                        <input
                                            type="checkbox"
                                            name="repeat_monthdays[]"
                                            value="<?php echo $day; ?>"
                                            <?php echo in_array($day, $editMonthdays, true) ? 'checked' : ''; ?>
                                            data-repeat-input
                                        >
                                        <span><?php echo $day; ?></span>
                                    </label>
                                <?php } ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <?php echo htmlspecialchars($edit !== null ? t('update') : t('save')); ?>
                        </button>

                        <?php if ($edit !== null) { ?>
                            <a class="text-link" href="index.php"><?php echo htmlspecialchars(t('cancel')); ?></a>
                        <?php } ?>
                    </div>
                </form>
            </section>

            <section class="card-panel notice-panel" data-notification-panel hidden>
                <div class="panel-head">
                    <div>
                        <h2><?php echo htmlspecialchars(t('reminder_notification_title')); ?></h2>
                        <p class="panel-subtitle"><?php echo htmlspecialchars(t('repeat_scheduler')); ?></p>
                    </div>
                </div>
                <p class="notice-copy" data-notification-copy></p>
                <button type="button" class="btn btn-secondary" data-enable-notifications><?php echo htmlspecialchars(t('enable_notifications')); ?></button>
            </section>
        </aside>

        <main class="memo-column">
            <section class="card-panel list-panel">
                <div class="panel-head">
                    <div>
                        <h2><?php echo htmlspecialchars(t('memo_list_title')); ?></h2>
                        <p class="panel-subtitle"><?php echo htmlspecialchars(t('memo_list_subtitle')); ?></p>
                    </div>
                </div>

                <?php if ($memoList === []) { ?>
                    <div class="empty-state">
                        <strong><?php echo htmlspecialchars(t('no_memos')); ?></strong>
                        <p><?php echo htmlspecialchars(t('workspace_subtitle')); ?></p>
                    </div>
                <?php } else { ?>
                    <div class="memo-grid">
                        <?php foreach ($memoList as $memo) { ?>
                            <?php
                            $memoRemindAt = Reminder::formatReminderForInput($memo['remind_at']);
                            $memoPattern = Reminder::normalizeStoredRepeatPattern($memo['repeat_mode'], $memo['repeat_config'], $memoRemindAt);
                            $memoRepeatMode = $memoPattern['mode'];
                            $memoRepeatLabel = RepeatLabel::describe($tr, $memoRepeatMode, $memoPattern['config']);
                            $memoContent = trim($memo['content']);
                            ?>
                            <article class="memo-card">
                                <div class="memo-card__head">
                                    <div>
                                        <h3><?php echo htmlspecialchars($memo['title']); ?></h3>
                                        <p class="memo-card__body<?php echo $memoContent === '' ? ' memo-card__body--muted' : ''; ?>">
                                            <?php echo $memoContent === '' ? htmlspecialchars(t('empty_content')) : nl2br(htmlspecialchars($memoContent)); ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="memo-meta">
                                    <?php if ($memoRemindAt !== '') { ?>
                                        <span
                                            class="meta-pill reminder-pill"
                                            data-id="<?php echo $memo['id']; ?>"
                                            data-remind="<?php echo htmlspecialchars($memoRemindAt); ?>"
                                            data-repeat-mode="<?php echo htmlspecialchars($memoRepeatMode); ?>"
                                            data-repeat-label="<?php echo htmlspecialchars($memoRepeatLabel); ?>"
                                            data-title="<?php echo htmlspecialchars($memo['title']); ?>"
                                        >
                                            <?php echo htmlspecialchars(RepeatLabel::summaryText($tr, $memoRemindAt, $memoRepeatLabel)); ?>
                                        </span>
                                        <span class="meta-pill repeat-pill" data-repeat-badge <?php echo $memoRepeatMode === 'none' ? 'hidden' : ''; ?>>
                                            <?php echo htmlspecialchars($memoRepeatLabel); ?>
                                        </span>
                                    <?php } ?>

                                    <span class="meta-date"><?php echo htmlspecialchars($memo['created_at']); ?></span>
                                </div>

                                <div class="memo-actions">
                                    <a class="btn btn-ghost btn-small" href="index.php?edit=<?php echo $memo['id']; ?>"><?php echo htmlspecialchars(t('edit')); ?></a>

                                    <form class="inline-form" method="post">
                                        <?php echo Csrf::field(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $memo['id']; ?>">
                                        <button
                                            type="button"
                                            class="btn btn-danger btn-small"
                                            data-original-label="<?php echo htmlspecialchars(t('delete')); ?>"
                                            onclick="confirmDelete(this)"
                                        >
                                            <?php echo htmlspecialchars(t('delete')); ?>
                                        </button>
                                    </form>
                                </div>
                            </article>
                        <?php } ?>
                    </div>
                <?php } ?>
            </section>
        </main>
    </div>
</div>
<div class="toast-stack" data-toast-stack aria-live="polite" aria-atomic="true"></div>
<script>
window.memoAppConfig = <?php echo json_encode([
    'csrfToken' => App\Csrf::token(),
    'repeatHints' => $repeatHints,
    'text' => [
        'setReminderFirst' => t('hint_reminder_first'),
        'notificationTitle' => t('reminder_notification_title'),
        'alertPrefix' => t('reminder_alert_prefix'),
        'rescheduleError' => t('reschedule_error'),
        'notificationsDefault' => t('notifications_default'),
        'notificationsDenied' => t('notifications_denied'),
        'notificationsUnavailable' => t('notifications_unavailable'),
        'confirmDelete' => t('sure'),
        'deleteLabel' => t('delete'),
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="memo-ui.js"></script>
</body>
</html>
