<?php

declare(strict_types=1);

$availableLangs = ['en', 'ja', 'vi'];

if (isset($_GET['lang']) && in_array($_GET['lang'], $availableLangs, true)) {
    $_SESSION['lang'] = $_GET['lang'];
    setcookie('lang', $_GET['lang'], time() + 31536000, '/');
}

if (isset($_SESSION['lang']) && in_array($_SESSION['lang'], $availableLangs, true)) {
    $lang = $_SESSION['lang'];
} elseif (isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], $availableLangs, true)) {
    $lang = $_COOKIE['lang'];
    $_SESSION['lang'] = $lang;
} else {
    $lang = 'en';
}

$translations = [
    'en' => [
        'app_title' => 'Memo App',
        'hello' => 'Hello',
        'logout' => 'Logout',
        'lang_label' => 'Language',
        'reminder_notification_title' => 'Memo reminder',
        'reminder_alert_prefix' => 'Reminder:',
        'reschedule_error' => 'Failed to reschedule reminder',

        'login' => 'Login',
        'register' => 'Register',
        'username' => 'Username',
        'password' => 'Password',
        'create_account' => 'Create account',
        'have_account' => 'Already have an account? Login',
        'no_account' => 'No account yet? Register',
        'err_fill_all' => 'Please fill in all fields.',
        'err_user_exists' => 'Username already exists.',
        'err_wrong_login' => 'Wrong username or password.',
        'err_invalid_username' => 'Username must be 3-32 characters: letters, numbers, or underscore.',

        'edit_memo' => 'Edit memo',
        'add_memo' => 'Add new memo',
        'title' => 'Title',
        'content' => 'Content',
        'save' => 'Save',
        'update' => 'Update',
        'cancel' => 'Cancel',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'sure' => 'Sure?',
        'no_memos' => 'No memos yet.',

        'remind_label' => 'Remind me at (optional)',
        'repeat_scheduler' => 'Flexible repeat scheduler',
        'repeat_pattern' => 'Repeat pattern',
        'repeat_every' => 'Repeat every',
        'choose_weekdays' => 'Choose weekdays',
        'choose_monthdays' => 'Choose dates in the month',

        'repeat_none' => 'Do not repeat',
        'repeat_daily' => 'Every day',
        'repeat_weekly' => 'Every week',
        'repeat_monthly' => 'Every month',
        'repeat_yearly' => 'Every year',
        'repeat_interval' => 'Every few minutes / hours / days',
        'repeat_weekly_days' => 'Pick weekdays',
        'repeat_monthly_dates' => 'Pick month dates',

        'unit_minute' => 'minutes',
        'unit_hour' => 'hours',
        'unit_day' => 'days',
        'unit_week' => 'weeks',
        'unit_month' => 'months',
        'unit_year' => 'years',

        'wd_sun' => 'Sun',
        'wd_mon' => 'Mon',
        'wd_tue' => 'Tue',
        'wd_wed' => 'Wed',
        'wd_thu' => 'Thu',
        'wd_fri' => 'Fri',
        'wd_sat' => 'Sat',

        'hint_none' => 'One alert only.',
        'hint_daily' => 'Repeats every day at the same time.',
        'hint_weekly' => 'Repeats every week on the same weekday and time.',
        'hint_monthly' => 'Repeats every month on the same date and time.',
        'hint_yearly' => 'Repeats every year on the same date and time.',
        'hint_interval' => 'Pick an exact gap such as every 15 minutes or every 2 hours.',
        'hint_weekly_days' => 'Pick one or more weekdays. The time stays the same as the reminder above.',
        'hint_monthly_dates' => 'Pick one or more dates in the month. The time stays the same as the reminder above.',
        'hint_reminder_first' => 'Set the reminder time first. The repeat setting will be saved and start from that point.',

        'lbl_every' => 'Every',
        'lbl_every_day' => 'Every day',
        'lbl_every_week' => 'Every week',
        'lbl_every_month' => 'Every month',
        'lbl_every_year' => 'Every year',
        'lbl_weekly_pattern' => 'Weekly pattern',
        'lbl_monthly_on' => 'Monthly on',
        'lbl_monthly_pattern' => 'Monthly pattern',
        'auth_tagline' => 'Capture quick thoughts and keep every reminder in one calm workspace.',
        'auth_hint' => 'Sign in to manage personal memos, repeat schedules, and reminder timing without the clutter.',
        'feature_secure' => 'Private account access with CSRF protection',
        'feature_repeat' => 'Flexible repeat scheduling for recurring reminders',
        'feature_local' => 'Local SQLite storage that stays lightweight',
        'workspace_heading' => 'Your memo workspace',
        'workspace_subtitle' => 'A focused desk for notes, schedules, and follow-ups that need your attention.',
        'memo_count_label' => 'Memos',
        'scheduled_count_label' => 'Scheduled',
        'recurring_count_label' => 'Recurring',
        'memo_list_title' => 'Saved memos',
        'memo_list_subtitle' => 'Review details, adjust reminder timing, or jump back into an edit without losing context.',
        'enable_notifications' => 'Enable notifications',
        'notifications_default' => 'Turn on browser notifications to receive reminder popups while this page stays open.',
        'notifications_denied' => 'Browser notifications are blocked. In-page reminder toasts will still appear here.',
        'notifications_unavailable' => 'This browser cannot show desktop notifications. In-page reminder toasts will still work.',
        'empty_content' => 'No extra details for this memo yet.',
    ],
    'ja' => [
        'app_title' => 'メモアプリ',
        'hello' => 'こんにちは',
        'logout' => 'ログアウト',
        'lang_label' => '言語',
        'reminder_notification_title' => 'メモのリマインダー',
        'reminder_alert_prefix' => 'リマインダー:',
        'reschedule_error' => 'リマインダーの再設定に失敗しました',

        'login' => 'ログイン',
        'register' => '登録',
        'username' => 'ユーザー名',
        'password' => 'パスワード',
        'create_account' => 'アカウント作成',
        'have_account' => 'すでにアカウントをお持ちですか？ ログイン',
        'no_account' => 'アカウントがありませんか？ 登録',
        'err_fill_all' => 'すべての項目を入力してください。',
        'err_user_exists' => 'このユーザー名はすでに使われています。',
        'err_wrong_login' => 'ユーザー名またはパスワードが違います。',
        'err_invalid_username' => 'ユーザー名は3〜32文字の英数字またはアンダースコアにしてください。',

        'edit_memo' => 'メモを編集',
        'add_memo' => '新しいメモを追加',
        'title' => 'タイトル',
        'content' => '内容',
        'save' => '保存',
        'update' => '更新',
        'cancel' => 'キャンセル',
        'edit' => '編集',
        'delete' => '削除',
        'sure' => '本当に？',
        'no_memos' => 'まだメモがありません。',

        'remind_label' => 'リマインド時刻（任意）',
        'repeat_scheduler' => '柔軟な繰り返し設定',
        'repeat_pattern' => '繰り返しパターン',
        'repeat_every' => '繰り返し間隔',
        'choose_weekdays' => '曜日を選択',
        'choose_monthdays' => '月の日付を選択',

        'repeat_none' => '繰り返さない',
        'repeat_daily' => '毎日',
        'repeat_weekly' => '毎週',
        'repeat_monthly' => '毎月',
        'repeat_yearly' => '毎年',
        'repeat_interval' => '数分／数時間／数日ごと',
        'repeat_weekly_days' => '曜日を選ぶ',
        'repeat_monthly_dates' => '月の日付を選ぶ',

        'unit_minute' => '分',
        'unit_hour' => '時間',
        'unit_day' => '日',
        'unit_week' => '週',
        'unit_month' => 'か月',
        'unit_year' => '年',

        'wd_sun' => '日',
        'wd_mon' => '月',
        'wd_tue' => '火',
        'wd_wed' => '水',
        'wd_thu' => '木',
        'wd_fri' => '金',
        'wd_sat' => '土',

        'hint_none' => '1回だけ通知します。',
        'hint_daily' => '毎日同じ時刻に繰り返します。',
        'hint_weekly' => '毎週同じ曜日・同じ時刻に繰り返します。',
        'hint_monthly' => '毎月同じ日・同じ時刻に繰り返します。',
        'hint_yearly' => '毎年同じ日・同じ時刻に繰り返します。',
        'hint_interval' => '15分ごとや2時間ごとなど、正確な間隔を選べます。',
        'hint_weekly_days' => '1つ以上の曜日を選びます。時刻は上のリマインダーと同じです。',
        'hint_monthly_dates' => '1つ以上の日付を選びます。時刻は上のリマインダーと同じです。',
        'hint_reminder_first' => '先にリマインド時刻を設定してください。繰り返し設定はその時点から始まります。',

        'lbl_every' => '毎',
        'lbl_every_day' => '毎日',
        'lbl_every_week' => '毎週',
        'lbl_every_month' => '毎月',
        'lbl_every_year' => '毎年',
        'lbl_weekly_pattern' => '週単位の繰り返し',
        'lbl_monthly_on' => '毎月',
        'lbl_monthly_pattern' => '月単位の繰り返し',
    ],
    'vi' => [
        'app_title' => 'Ứng dụng ghi chú',
        'hello' => 'Xin chào',
        'logout' => 'Đăng xuất',
        'lang_label' => 'Ngôn ngữ',
        'reminder_notification_title' => 'Nhắc nhở ghi chú',
        'reminder_alert_prefix' => 'Nhắc nhở:',
        'reschedule_error' => 'Không thể đặt lại lịch nhắc',

        'login' => 'Đăng nhập',
        'register' => 'Đăng ký',
        'username' => 'Tên đăng nhập',
        'password' => 'Mật khẩu',
        'create_account' => 'Tạo tài khoản',
        'have_account' => 'Đã có tài khoản? Đăng nhập',
        'no_account' => 'Chưa có tài khoản? Đăng ký',
        'err_fill_all' => 'Vui lòng điền đầy đủ thông tin.',
        'err_user_exists' => 'Tên đăng nhập đã tồn tại.',
        'err_wrong_login' => 'Sai tên đăng nhập hoặc mật khẩu.',
        'err_invalid_username' => 'Tên đăng nhập phải dài 3-32 ký tự: chữ, số hoặc dấu gạch dưới.',

        'edit_memo' => 'Sửa ghi chú',
        'add_memo' => 'Thêm ghi chú mới',
        'title' => 'Tiêu đề',
        'content' => 'Nội dung',
        'save' => 'Lưu',
        'update' => 'Cập nhật',
        'cancel' => 'Hủy',
        'edit' => 'Sửa',
        'delete' => 'Xóa',
        'sure' => 'Chắc chắn?',
        'no_memos' => 'Chưa có ghi chú nào.',

        'remind_label' => 'Nhắc tôi lúc (tùy chọn)',
        'repeat_scheduler' => 'Bộ lặp lịch nhắc linh hoạt',
        'repeat_pattern' => 'Kiểu lặp lại',
        'repeat_every' => 'Lặp lại mỗi',
        'choose_weekdays' => 'Chọn các thứ trong tuần',
        'choose_monthdays' => 'Chọn ngày trong tháng',

        'repeat_none' => 'Không lặp lại',
        'repeat_daily' => 'Mỗi ngày',
        'repeat_weekly' => 'Mỗi tuần',
        'repeat_monthly' => 'Mỗi tháng',
        'repeat_yearly' => 'Mỗi năm',
        'repeat_interval' => 'Mỗi vài phút / giờ / ngày',
        'repeat_weekly_days' => 'Chọn thứ trong tuần',
        'repeat_monthly_dates' => 'Chọn ngày trong tháng',

        'unit_minute' => 'phút',
        'unit_hour' => 'giờ',
        'unit_day' => 'ngày',
        'unit_week' => 'tuần',
        'unit_month' => 'tháng',
        'unit_year' => 'năm',

        'wd_sun' => 'CN',
        'wd_mon' => 'T2',
        'wd_tue' => 'T3',
        'wd_wed' => 'T4',
        'wd_thu' => 'T5',
        'wd_fri' => 'T6',
        'wd_sat' => 'T7',

        'hint_none' => 'Chỉ nhắc một lần.',
        'hint_daily' => 'Lặp lại mỗi ngày vào cùng giờ.',
        'hint_weekly' => 'Lặp lại mỗi tuần vào cùng thứ và cùng giờ.',
        'hint_monthly' => 'Lặp lại mỗi tháng vào cùng ngày và cùng giờ.',
        'hint_yearly' => 'Lặp lại mỗi năm vào cùng ngày và cùng giờ.',
        'hint_interval' => 'Chọn khoảng chính xác như mỗi 15 phút hoặc mỗi 2 giờ.',
        'hint_weekly_days' => 'Chọn một hoặc nhiều thứ trong tuần. Giờ sẽ giữ giống thời gian nhắc ở trên.',
        'hint_monthly_dates' => 'Chọn một hoặc nhiều ngày trong tháng. Giờ sẽ giữ giống thời gian nhắc ở trên.',
        'hint_reminder_first' => 'Hãy đặt thời gian nhắc trước. Thiết lập lặp sẽ được lưu và bắt đầu từ thời điểm đó.',

        'lbl_every' => 'Mỗi',
        'lbl_every_day' => 'Mỗi ngày',
        'lbl_every_week' => 'Mỗi tuần',
        'lbl_every_month' => 'Mỗi tháng',
        'lbl_every_year' => 'Mỗi năm',
        'lbl_weekly_pattern' => 'Lặp theo tuần',
        'lbl_monthly_on' => 'Hàng tháng vào',
        'lbl_monthly_pattern' => 'Lặp theo tháng',
    ],
];

$T = $translations[$lang];

/**
 * @return array<string, array<string, string>>
 */
function allTranslations(): array
{
    global $translations;

    if (! is_array($translations)) {
        return [];
    }

    $result = [];

    foreach ($translations as $language => $entries) {
        if (! is_string($language) || ! is_array($entries)) {
            continue;
        }

        $normalizedEntries = [];
        foreach ($entries as $key => $value) {
            if (! is_string($key) || ! is_string($value)) {
                continue;
            }

            $normalizedEntries[$key] = $value;
        }

        $result[$language] = $normalizedEntries;
    }

    return $result;
}

function t(string $key): string
{
    $translations = allTranslations();
    $lang = currentLang();
    $activeTranslations = $translations[$lang] ?? $translations['en'] ?? [];

    return $activeTranslations[$key] ?? $translations['en'][$key] ?? $key;
}

function currentLang(): string
{
    global $lang;

    return is_string($lang) ? $lang : 'en';
}

function langSelect(): string
{
    $names = [
        'en' => 'English',
        'ja' => '日本語',
        'vi' => 'Tiếng Việt',
    ];

    $params = $_GET;
    unset($params['lang']);

    $html = '<select class="lang-select" aria-label="' . htmlspecialchars(t('lang_label')) . '" onchange="location.href=this.value">';

    foreach ($names as $code => $name) {
        $urlParams = $params;
        $urlParams['lang'] = $code;
        $selected = currentLang() === $code ? ' selected' : '';
        $url = '?' . http_build_query($urlParams);
        $html .= '<option value="' . htmlspecialchars($url) . '"' . $selected . '>' . htmlspecialchars($name) . '</option>';
    }

    $html .= '</select>';

    return $html;
}
