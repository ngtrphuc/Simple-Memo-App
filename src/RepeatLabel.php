<?php

declare(strict_types=1);

namespace App;

/**
 * Human-readable labels for repeat patterns.
 *
 * Split out from {@see Reminder} because labels depend on the active language.
 * Instead of calling the global t() directly, every method takes a translator
 * callable (typically `t(...)`), which keeps this class testable with a stub
 * translator and avoids coupling domain code to the i18n bootstrap.
 *
 * @phpstan-type Translator callable(string): string
 */
final class RepeatLabel
{
    /**
     * Map repeat-mode keys to their dropdown labels.
     *
     * @param  callable(string): string  $t
     * @return array<string, string>
     */
    public static function modeOptions(callable $t): array
    {
        return [
            'none' => $t('repeat_none'),
            'daily' => $t('repeat_daily'),
            'weekly' => $t('repeat_weekly'),
            'monthly' => $t('repeat_monthly'),
            'yearly' => $t('repeat_yearly'),
            'interval' => $t('repeat_interval'),
            'weekly_days' => $t('repeat_weekly_days'),
            'monthly_dates' => $t('repeat_monthly_dates'),
        ];
    }

    /**
     * @param  callable(string): string  $t
     * @return array<string, string>
     */
    public static function intervalUnitOptions(callable $t): array
    {
        return [
            'minute' => $t('unit_minute'),
            'hour' => $t('unit_hour'),
            'day' => $t('unit_day'),
            'week' => $t('unit_week'),
            'month' => $t('unit_month'),
            'year' => $t('unit_year'),
        ];
    }

    /**
     * @param  callable(string): string  $t
     * @return array<int, string>
     */
    public static function weekdayLabels(callable $t): array
    {
        return [
            0 => $t('wd_sun'),
            1 => $t('wd_mon'),
            2 => $t('wd_tue'),
            3 => $t('wd_wed'),
            4 => $t('wd_thu'),
            5 => $t('wd_fri'),
            6 => $t('wd_sat'),
        ];
    }

    /**
     * @param  callable(string): string  $t
     * @return array<string, string>
     */
    public static function hintMap(callable $t): array
    {
        return [
            'none' => $t('hint_none'),
            'daily' => $t('hint_daily'),
            'weekly' => $t('hint_weekly'),
            'monthly' => $t('hint_monthly'),
            'yearly' => $t('hint_yearly'),
            'interval' => $t('hint_interval'),
            'weekly_days' => $t('hint_weekly_days'),
            'monthly_dates' => $t('hint_monthly_dates'),
        ];
    }

    /**
     * Singularize a unit label when count is exactly 1 (English-style trailing 's').
     * Languages without plural 's' are unaffected because the label simply lacks it.
     *
     * @param  callable(string): string  $t
     */
    public static function pluralizeUnit(callable $t, string $unit, int $count): string
    {
        $label = self::intervalUnitOptions($t)[$unit] ?? $unit;

        if ($count === 1 && str_ends_with($label, 's')) {
            return substr($label, 0, -1);
        }

        return $label;
    }

    /**
     * Build the short label describing a repeat rule (e.g. "Every 2 hours",
     * "Every Mon, Wed", "Monthly on 1, 15"). Returns '' for non-repeating.
     *
     * @param  callable(string): string  $t
     * @param  array<string, mixed>  $config
     */
    public static function describe(callable $t, string $mode, array $config): string
    {
        if ($mode === 'none') {
            return '';
        }

        if ($mode === 'daily') {
            return $t('lbl_every_day');
        }

        if ($mode === 'weekly') {
            return $t('lbl_every_week');
        }

        if ($mode === 'monthly') {
            return $t('lbl_every_month');
        }

        if ($mode === 'yearly') {
            return $t('lbl_every_year');
        }

        if ($mode === 'interval') {
            $rawValue = $config['value'] ?? 1;
            $value = max(1, is_numeric($rawValue) ? (int) $rawValue : 1);
            $rawUnit = $config['unit'] ?? 'hour';
            $unit = Reminder::normalizeIntervalUnit(is_scalar($rawUnit) ? (string) $rawUnit : 'hour');

            return $t('lbl_every').' '.$value.' '.self::pluralizeUnit($t, $unit, $value);
        }

        if ($mode === 'weekly_days') {
            $labels = self::weekdayLabels($t);
            $parts = [];

            foreach (Reminder::normalizeNumericSelection($config['weekdays'] ?? [], 0, 6) as $weekday) {
                if (isset($labels[$weekday])) {
                    $parts[] = $labels[$weekday];
                }
            }

            return $parts !== [] ? $t('lbl_every').' '.implode(', ', $parts) : $t('lbl_weekly_pattern');
        }

        if ($mode === 'monthly_dates') {
            $parts = Reminder::normalizeNumericSelection($config['monthdays'] ?? [], 1, 31);

            return $parts !== [] ? $t('lbl_monthly_on').' '.implode(', ', array_map(strval(...), $parts)) : $t('lbl_monthly_pattern');
        }

        return '';
    }

    /**
     * Combine the formatted reminder time with its repeat label for list display.
     *
     * @param  callable(string): string  $t
     */
    public static function summaryText(callable $t, string $remindAt, string $repeatLabel): string
    {
        $text = Reminder::formatReminderForDisplay($remindAt);

        if ($repeatLabel !== '') {
            $text .= ' | '.$repeatLabel;
        }

        return $text;
    }
}
