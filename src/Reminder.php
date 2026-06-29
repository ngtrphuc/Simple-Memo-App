<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use Exception;

/**
 * Pure reminder and repeat-scheduling logic.
 *
 * This class contains no I/O, no session access, and no translation calls.
 * Every method is deterministic given its inputs, which makes the whole
 * repeat scheduler unit-testable in isolation. Display labels live in
 * {@see RepeatLabel} because they depend on the active language.
 *
 * Repeat modes:
 *   none | daily | weekly | monthly | yearly | interval | weekly_days | monthly_dates
 *
 * Config shape (after normalization):
 *   interval      => ['value' => int, 'unit' => string]
 *   weekly_days   => ['weekdays' => int[]]   (0=Sun .. 6=Sat)
 *   monthly_dates => ['monthdays' => int[]]   (1 .. 31)
 */
final class Reminder
{
    /** @var list<string> */
    public const array REPEAT_MODES = [
        'none',
        'daily',
        'weekly',
        'monthly',
        'yearly',
        'interval',
        'weekly_days',
        'monthly_dates',
    ];

    /** @var list<string> */
    public const array INTERVAL_UNITS = ['minute', 'hour', 'day', 'week', 'month', 'year'];

    public static function normalizeRepeatMode(string $value): string
    {
        return in_array($value, self::REPEAT_MODES, true) ? $value : 'none';
    }

    public static function normalizeIntervalUnit(string $value): string
    {
        return in_array($value, self::INTERVAL_UNITS, true) ? $value : 'hour';
    }

    /**
     * Safely coerce an arbitrary value (often from decoded JSON or form input) to int.
     *
     * @param  mixed  $value
     */
    private static function toInt($value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Safely coerce an arbitrary value to string.
     *
     * @param  mixed  $value
     */
    private static function toString($value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    public static function parseReminderDate(?string $value): ?DateTimeImmutable
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
        } catch (Exception) {
            return null;
        }
    }

    public static function formatReminderForInput(?string $value): string
    {
        $date = self::parseReminderDate($value);

        return $date instanceof DateTimeImmutable ? $date->format('Y-m-d\TH:i') : '';
    }

    public static function formatReminderForDisplay(?string $value): string
    {
        $date = self::parseReminderDate($value);

        return $date instanceof DateTimeImmutable ? $date->format('Y-m-d H:i') : '';
    }

    /**
     * @return array<string, mixed>
     */
    public static function decodeRepeatConfig(?string $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return [];
        }

        /** @var array<string, mixed> $result */
        $result = [];
        foreach ($decoded as $key => $item) {
            $result[(string) $key] = $item;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function encodeRepeatConfig(array $config): string
    {
        return $config === [] ? '' : (string) json_encode($config, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Keep only integers within [$min, $max], de-duplicated and sorted ascending.
     *
     * @param  mixed  $values
     * @return list<int>
     */
    public static function normalizeNumericSelection($values, int $min, int $max): array
    {
        $normalized = [];

        foreach ((array) $values as $value) {
            $number = self::toInt($value);
            if ($number >= $min && $number <= $max) {
                $normalized[$number] = $number;
            }
        }

        $result = array_values($normalized);
        sort($result);

        return $result;
    }

    /**
     * Normalize a raw repeat selection into a canonical {mode, config} pair.
     *
     * Accepts both the raw HTTP field names (interval_value, repeat_weekdays, ...)
     * and the already-stored config keys (value, weekdays, ...) so it can be reused
     * for both incoming form data and persisted rows.
     *
     * @param  array<string, mixed>  $rawConfig
     * @return array{mode: string, config: array<string, mixed>}
     */
    public static function normalizeRepeatPattern(string $mode, array $rawConfig, ?string $remindAt): array
    {
        $mode = self::normalizeRepeatMode($mode);
        $reminder = self::parseReminderDate($remindAt);

        if (! $reminder instanceof DateTimeImmutable || $mode === 'none') {
            return ['mode' => 'none', 'config' => []];
        }

        if ($mode === 'interval') {
            $value = self::toInt($rawConfig['interval_value'] ?? $rawConfig['value'] ?? 1);
            $value = max(1, min(999, $value));

            return [
                'mode' => 'interval',
                'config' => [
                    'value' => $value,
                    'unit' => self::normalizeIntervalUnit(self::toString($rawConfig['interval_unit'] ?? $rawConfig['unit'] ?? 'hour')),
                ],
            ];
        }

        if ($mode === 'weekly_days') {
            $weekdays = self::normalizeNumericSelection($rawConfig['repeat_weekdays'] ?? $rawConfig['weekdays'] ?? [], 0, 6);
            if ($weekdays === []) {
                $weekdays = [(int) $reminder->format('w')];
            }

            return ['mode' => 'weekly_days', 'config' => ['weekdays' => $weekdays]];
        }

        if ($mode === 'monthly_dates') {
            $monthdays = self::normalizeNumericSelection($rawConfig['repeat_monthdays'] ?? $rawConfig['monthdays'] ?? [], 1, 31);
            if ($monthdays === []) {
                $monthdays = [(int) $reminder->format('j')];
            }

            return ['mode' => 'monthly_dates', 'config' => ['monthdays' => $monthdays]];
        }

        return ['mode' => $mode, 'config' => []];
    }

    /**
     * @return array{mode: string, config: array<string, mixed>}
     */
    public static function normalizeStoredRepeatPattern(string $mode, ?string $repeatConfig, ?string $remindAt): array
    {
        return self::normalizeRepeatPattern($mode, self::decodeRepeatConfig($repeatConfig), $remindAt);
    }

    /**
     * Advance a fixed-interval reminder to the first occurrence strictly after $now.
     *
     * For second-based units (minute/hour/day/week) we jump directly using integer
     * division instead of looping one cycle at a time. Month/year are calendar units
     * with variable length, so they use a do...while loop. This is the missed-cycle
     * fix: a reminder left untriggered for a long time skips straight to the next
     * future slot in one pass.
     */
    public static function getNextIntervalDate(DateTimeImmutable $current, DateTimeImmutable $now, int $value, string $unit): DateTimeImmutable
    {
        $value = max(1, $value);
        $unit = self::normalizeIntervalUnit($unit);

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

    /**
     * @param  list<int>  $weekdays  0=Sun .. 6=Sat
     */
    public static function getNextWeeklyDayDate(DateTimeImmutable $current, DateTimeImmutable $now, array $weekdays): ?DateTimeImmutable
    {
        $weekdays = self::normalizeNumericSelection($weekdays, 0, 6);

        if ($weekdays === []) {
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

    /**
     * @param  list<int>  $monthdays  1 .. 31
     */
    public static function getNextMonthlyDate(DateTimeImmutable $current, DateTimeImmutable $now, array $monthdays): ?DateTimeImmutable
    {
        $monthdays = self::normalizeNumericSelection($monthdays, 1, 31);

        if ($monthdays === []) {
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

    /**
     * Compute the next reminder timestamp, or null if the reminder does not repeat
     * (or cannot be advanced). Output is formatted as Y-m-d\TH:i to match the
     * datetime-local input the rest of the app uses.
     *
     * @param  array<string, mixed>  $repeatConfig
     */
    public static function getNextReminderAt(?string $value, string $repeatMode, array $repeatConfig = [], ?DateTimeImmutable $now = null): ?string
    {
        $current = self::parseReminderDate($value);

        if (! $current instanceof DateTimeImmutable) {
            return null;
        }

        $now ??= new DateTimeImmutable('now');
        $repeatMode = self::normalizeRepeatMode($repeatMode);

        if ($repeatMode === 'none') {
            return null;
        }

        if ($repeatMode === 'daily') {
            return self::getNextIntervalDate($current, $now, 1, 'day')->format('Y-m-d\TH:i');
        }

        if ($repeatMode === 'weekly') {
            return self::getNextIntervalDate($current, $now, 1, 'week')->format('Y-m-d\TH:i');
        }

        if ($repeatMode === 'monthly') {
            return self::getNextIntervalDate($current, $now, 1, 'month')->format('Y-m-d\TH:i');
        }

        if ($repeatMode === 'yearly') {
            return self::getNextIntervalDate($current, $now, 1, 'year')->format('Y-m-d\TH:i');
        }

        if ($repeatMode === 'interval') {
            $next = self::getNextIntervalDate(
                $current,
                $now,
                self::toInt($repeatConfig['value'] ?? 1),
                self::toString($repeatConfig['unit'] ?? 'hour')
            );

            return $next->format('Y-m-d\TH:i');
        }

        if ($repeatMode === 'weekly_days') {
            $next = self::getNextWeeklyDayDate($current, $now, self::normalizeNumericSelection($repeatConfig['weekdays'] ?? [], 0, 6));

            return $next instanceof DateTimeImmutable ? $next->format('Y-m-d\TH:i') : null;
        }

        if ($repeatMode === 'monthly_dates') {
            $next = self::getNextMonthlyDate($current, $now, self::normalizeNumericSelection($repeatConfig['monthdays'] ?? [], 1, 31));

            return $next instanceof DateTimeImmutable ? $next->format('Y-m-d\TH:i') : null;
        }

        return null;
    }
}
