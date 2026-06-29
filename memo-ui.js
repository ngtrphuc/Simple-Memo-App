(function () {
    var config = window.memoAppConfig || {};
    var csrfToken = config.csrfToken || '';
    var repeatHints = config.repeatHints || {};
    var text = config.text || {};
    var alreadyNotified = new Set();
    var deleteTimers = new WeakMap();
    var toastStack = document.querySelector('[data-toast-stack]');

    function showToast(message, type) {
        if (!toastStack || !message) {
            return;
        }

        var toast = document.createElement('div');
        toast.className = 'toast' + (type === 'error' ? ' toast--error' : '');
        toast.textContent = message;
        toastStack.appendChild(toast);

        window.setTimeout(function () {
            toast.remove();
        }, 4800);
    }

    function resetDeleteButton(button) {
        if (!button) {
            return;
        }

        button.dataset.armed = '0';
        button.textContent = button.dataset.originalLabel || text.deleteLabel || 'Delete';
    }

    window.confirmDelete = function (button) {
        if (!button) {
            return;
        }

        if (button.dataset.armed === '1') {
            if (button.form) {
                button.form.submit();
            }

            return;
        }

        button.dataset.armed = '1';
        button.textContent = text.confirmDelete || 'Sure?';

        if (deleteTimers.has(button)) {
            window.clearTimeout(deleteTimers.get(button));
        }

        deleteTimers.set(button, window.setTimeout(function () {
            resetDeleteButton(button);
        }, 4000));
    };

    function updateReminderElement(item, remindAt, repeatMode, repeatLabel) {
        item.setAttribute('data-remind', remindAt);
        item.setAttribute('data-repeat-mode', repeatMode);
        item.setAttribute('data-repeat-label', repeatLabel || '');
        item.textContent = '\u23F0 ' + remindAt.replace('T', ' ') + (repeatLabel ? ' | ' + repeatLabel : '');

        var badge = item.parentElement ? item.parentElement.querySelector('[data-repeat-badge]') : null;

        if (!badge) {
            return;
        }

        if (repeatLabel) {
            badge.textContent = repeatLabel;
            badge.hidden = false;
        } else {
            badge.hidden = true;
        }
    }

    function rescheduleReminder(item) {
        var memoId = item.getAttribute('data-id');
        var repeatMode = item.getAttribute('data-repeat-mode') || 'none';

        if (!memoId || !csrfToken || repeatMode === 'none') {
            return;
        }

        fetch('index.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: new URLSearchParams({
                action: 'reschedule',
                id: memoId,
                csrf_token: csrfToken,
            }),
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
                console.error(text.rescheduleError || 'Failed to reschedule reminder', error);
                showToast(text.rescheduleError || 'Failed to reschedule reminder', 'error');
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

        if (!hint) {
            return;
        }

        if (!hasReminder && mode !== 'none') {
            hint.textContent = text.setReminderFirst || '';
            return;
        }

        hint.textContent = repeatHints[mode] || repeatHints.none || '';
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

    function refreshNotificationPanel() {
        var panel = document.querySelector('[data-notification-panel]');
        var copy = document.querySelector('[data-notification-copy]');
        var button = document.querySelector('[data-enable-notifications]');

        if (!panel || !copy || !button) {
            return;
        }

        if (!('Notification' in window)) {
            panel.hidden = false;
            copy.textContent = text.notificationsUnavailable || '';
            button.hidden = true;
            return;
        }

        if (Notification.permission === 'granted') {
            panel.hidden = true;
            return;
        }

        panel.hidden = false;

        if (Notification.permission === 'denied') {
            copy.textContent = text.notificationsDenied || '';
            button.hidden = true;
            return;
        }

        copy.textContent = text.notificationsDefault || '';
        button.hidden = false;
    }

    var enableNotificationsButton = document.querySelector('[data-enable-notifications]');
    if (enableNotificationsButton && 'Notification' in window) {
        enableNotificationsButton.addEventListener('click', function () {
            Notification.requestPermission().finally(function () {
                refreshNotificationPanel();
            });
        });
    }

    function checkReminders() {
        var now = new Date();
        var items = document.querySelectorAll('.reminder-pill[data-remind]');

        items.forEach(function (item) {
            var memoId = item.getAttribute('data-id');
            var remindAt = item.getAttribute('data-remind');
            var repeatMode = item.getAttribute('data-repeat-mode') || 'none';
            var title = item.getAttribute('data-title') || text.notificationTitle || 'Reminder';

            if (!memoId || !remindAt) {
                return;
            }

            var remindTime = new Date(remindAt);
            var notifyKey = memoId + ':' + remindAt;

            if (Number.isNaN(remindTime.getTime()) || now < remindTime || alreadyNotified.has(notifyKey)) {
                return;
            }

            alreadyNotified.add(notifyKey);

            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification(text.notificationTitle || 'Memo reminder', {
                    body: title,
                });
            } else {
                showToast((text.alertPrefix || 'Reminder:') + ' ' + title);
            }

            if (repeatMode !== 'none') {
                rescheduleReminder(item);
            }
        });
    }

    refreshNotificationPanel();
    window.setInterval(checkReminders, 10000);
    checkReminders();
})();
