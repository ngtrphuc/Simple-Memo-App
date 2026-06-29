<?php

declare(strict_types=1);

namespace Tests;

use App\Reminder;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReminderTest extends TestCase
{
    private function now(): DateTimeImmutable
    {
        // Fixed "now" so every test is deterministic.
        return new DateTimeImmutable('2026-06-29 12:00:00');
    }

    #[Test]
    public function parses_datetime_local_and_space_formats(): void
    {
        $this->assertNotNull(Reminder::parseReminderDate('2026-06-29T08:30'));
        $this->assertNotNull(Reminder::parseReminderDate('2026-06-29 08:30:00'));
        $this->assertNull(Reminder::parseReminderDate(''));
        $this->assertNull(Reminder::parseReminderDate(null));
        $this->assertNull(Reminder::parseReminderDate('not a date'));
    }

    #[Test]
    public function formats_for_input_and_display(): void
    {
        $this->assertSame('2026-06-29T08:30', Reminder::formatReminderForInput('2026-06-29 08:30:00'));
        $this->assertSame('2026-06-29 08:30', Reminder::formatReminderForDisplay('2026-06-29T08:30'));
        $this->assertSame('', Reminder::formatReminderForInput(''));
    }

    #[Test]
    public function non_repeating_returns_null(): void
    {
        $this->assertNull(Reminder::getNextReminderAt('2026-06-01T08:00', 'none', [], $this->now()));
    }

    #[Test]
    public function null_or_empty_reminder_returns_null(): void
    {
        $this->assertNull(Reminder::getNextReminderAt(null, 'daily', [], $this->now()));
        $this->assertNull(Reminder::getNextReminderAt('', 'daily', [], $this->now()));
    }

    #[Test]
    public function daily_skips_missed_cycles_in_one_pass(): void
    {
        // Reminder set 5 days ago at 08:00. With "now" at 2026-06-29 12:00,
        // the next daily occurrence must be 2026-06-30 08:00 (first slot strictly
        // after now), NOT 2026-06-24 08:00 or any intermediate missed day.
        $next = Reminder::getNextReminderAt('2026-06-24T08:00', 'daily', [], $this->now());

        $this->assertSame('2026-06-30T08:00', $next);
    }

    #[Test]
    public function daily_today_before_now_advances_to_tomorrow(): void
    {
        $next = Reminder::getNextReminderAt('2026-06-29T08:00', 'daily', [], $this->now());

        $this->assertSame('2026-06-30T08:00', $next);
    }

    #[Test]
    public function future_reminder_is_left_untouched(): void
    {
        // Already in the future relative to now -> returned as-is.
        $next = Reminder::getNextReminderAt('2026-07-05T09:00', 'daily', [], $this->now());

        $this->assertSame('2026-07-05T09:00', $next);
    }

    #[Test]
    public function interval_minutes_uses_integer_jump_not_loop(): void
    {
        // 15-minute interval, reminder 2 hours ago. 8 missed slots -> next is the 9th.
        // current 10:00, now 12:00 -> next strictly after now = 12:15.
        $next = Reminder::getNextReminderAt(
            '2026-06-29T10:00',
            'interval',
            ['value' => 15, 'unit' => 'minute'],
            $this->now()
        );

        $this->assertSame('2026-06-29T12:15', $next);
    }

    #[Test]
    public function interval_hours_lands_exactly_on_now_boundary_then_steps_past(): void
    {
        // 2-hour interval from 06:00. Steps: 08,10,12,... now is exactly 12:00,
        // and "next" must be strictly after now -> 14:00.
        $next = Reminder::getNextReminderAt(
            '2026-06-29T06:00',
            'interval',
            ['value' => 2, 'unit' => 'hour'],
            $this->now()
        );

        $this->assertSame('2026-06-29T14:00', $next);
    }

    #[Test]
    public function monthly_uses_calendar_loop(): void
    {
        // Anchor May 15 09:00, now Jun 29 12:00. +1 month loop: Jun 15 (<= now)
        // -> Jul 15 (> now). Monthly keeps the same day-of-month, so the next
        // occurrence is Jul 15, not the end of June.
        $next = Reminder::getNextReminderAt('2026-05-15T09:00', 'monthly', [], $this->now());

        $this->assertSame('2026-07-15T09:00', $next);
    }

    #[Test]
    public function yearly_advances_to_next_year(): void
    {
        // Anchor Mar 10 2025, now Jun 29 2026. +1 year: Mar 10 2026 (<= now, since
        // June is past March) -> Mar 10 2027 (> now).
        $next = Reminder::getNextReminderAt('2025-03-10T07:00', 'yearly', [], $this->now());

        $this->assertSame('2027-03-10T07:00', $next);
    }

    #[Test]
    public function weekly_days_finds_next_selected_weekday(): void
    {
        // now = Monday 2026-06-29 12:00. Reminder anchor in the past at 08:00.
        // Selected weekdays: Wed(3), Fri(5). Next strictly-after-now match at 08:00
        // should be Wednesday 2026-07-01 08:00.
        $next = Reminder::getNextReminderAt(
            '2026-06-22T08:00',
            'weekly_days',
            ['weekdays' => [3, 5]],
            $this->now()
        );

        $this->assertSame('2026-07-01T08:00', $next);
    }

    #[Test]
    public function monthly_dates_finds_next_selected_date(): void
    {
        // Selected month dates 1 and 15, anchor at 08:00 in the past.
        // now = 2026-06-29 -> next match is 2026-07-01 08:00.
        $next = Reminder::getNextReminderAt(
            '2026-06-01T08:00',
            'monthly_dates',
            ['monthdays' => [1, 15]],
            $this->now()
        );

        $this->assertSame('2026-07-01T08:00', $next);
    }

    #[Test]
    public function weekly_days_with_empty_selection_returns_null(): void
    {
        $next = Reminder::getNextReminderAt(
            '2026-06-01T08:00',
            'weekly_days',
            ['weekdays' => []],
            $this->now()
        );

        $this->assertNull($next);
    }

    #[Test]
    public function normalize_pattern_defaults_weekday_from_reminder(): void
    {
        // 2026-06-29 is a Monday (w=1). With no weekdays chosen, it should default
        // to the reminder's own weekday.
        $pattern = Reminder::normalizeRepeatPattern('weekly_days', ['repeat_weekdays' => []], '2026-06-29T08:00');

        $this->assertSame('weekly_days', $pattern['mode']);
        $this->assertSame([1], $pattern['config']['weekdays']);
    }

    #[Test]
    public function normalize_pattern_clamps_interval_value(): void
    {
        $pattern = Reminder::normalizeRepeatPattern(
            'interval',
            ['interval_value' => 99999, 'interval_unit' => 'banana'],
            '2026-06-29T08:00'
        );

        $this->assertSame(999, $pattern['config']['value']);
        $this->assertSame('hour', $pattern['config']['unit']); // invalid unit falls back
    }

    #[Test]
    public function normalize_pattern_without_reminder_is_none(): void
    {
        $pattern = Reminder::normalizeRepeatPattern('daily', [], '');

        $this->assertSame('none', $pattern['mode']);
        $this->assertSame([], $pattern['config']);
    }

    #[Test]
    public function encode_decode_config_round_trips(): void
    {
        $config = ['value' => 3, 'unit' => 'day'];
        $encoded = Reminder::encodeRepeatConfig($config);
        $this->assertSame($config, Reminder::decodeRepeatConfig($encoded));

        $this->assertSame('', Reminder::encodeRepeatConfig([]));
        $this->assertSame([], Reminder::decodeRepeatConfig(''));
        $this->assertSame([], Reminder::decodeRepeatConfig('not json'));
    }

    #[Test]
    public function numeric_selection_dedupes_sorts_and_bounds(): void
    {
        $this->assertSame([1, 3, 5], Reminder::normalizeNumericSelection(['5', 3, 1, 3, 99, -2], 1, 31));
        $this->assertSame([], Reminder::normalizeNumericSelection([40, 50], 1, 31));
    }

    #[Test]
    public function stored_pattern_decodes_json_config(): void
    {
        $pattern = Reminder::normalizeStoredRepeatPattern(
            'interval',
            '{"value":2,"unit":"hour"}',
            '2026-06-29T08:00'
        );

        $this->assertSame('interval', $pattern['mode']);
        $this->assertSame(2, $pattern['config']['value']);
        $this->assertSame('hour', $pattern['config']['unit']);
    }
}
