<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Times are stored in UTC, and the business runs on Libyan time.
 *
 * "Today's sales", a report's first and last day, the day a filter means and
 * the hour printed on an invoice are all questions about the office's clock,
 * not the server's. Tripoli is two hours ahead of UTC, so answering them in UTC
 * puts everything sold between midnight and 2 am on the previous day and
 * prints invoice times that disagree with the hour inside the order number.
 *
 * Everything that turns an instant into a day, or a day into a range, goes
 * through here. JSON keeps sending UTC ISO timestamps; clients localise those.
 */
final class BusinessTime
{
    public static function tz(): string
    {
        return config('app.business_timezone', 'Africa/Tripoli');
    }

    /** The current moment on the office clock. For reading parts of it, never for binding into a query. */
    public static function now(): Carbon
    {
        return Carbon::now(self::tz());
    }

    /** A stored instant, shown on the office clock. */
    public static function local(?CarbonInterface $at): ?Carbon
    {
        return $at ? Carbon::instance($at)->setTimezone(self::tz()) : null;
    }

    public static function format(?CarbonInterface $at, string $format = 'Y-m-d H:i'): ?string
    {
        return self::local($at)?->format($format);
    }

    /**
     * The UTC instant at which a local period starts ('day', 'week', 'month').
     * Returned in UTC because the query builder binds a Carbon by formatting it
     * as it stands, without converting its timezone.
     */
    public static function startOf(string $unit, ?CarbonInterface $of = null): Carbon
    {
        return (self::local($of) ?? self::now())->startOf($unit)->utc();
    }

    public static function endOf(string $unit, ?CarbonInterface $of = null): Carbon
    {
        return (self::local($of) ?? self::now())->endOf($unit)->utc();
    }

    /** First instant (UTC) of a local calendar day given as Y-m-d. */
    public static function dayStart(string $ymd): Carbon
    {
        return self::day($ymd)->startOfDay()->utc();
    }

    /** Last instant (UTC) of a local calendar day given as Y-m-d. */
    public static function dayEnd(string $ymd): Carbon
    {
        return self::day($ymd)->endOfDay()->utc();
    }

    // A date typed into a filter; a bad one is the caller's 422, not a 500.
    private static function day(string $value): Carbon
    {
        try {
            return Carbon::parse($value, self::tz());
        } catch (\Throwable) {
            throw ValidationException::withMessages(['date' => 'صيغة التاريخ غير صحيحة']);
        }
    }

    /**
     * SQL that formats a stored UTC column as a local day or month, for GROUP BY.
     * $format uses the strftime letters both engines share (%Y %m %d).
     * The offset is the zone's current one; Libya keeps no daylight saving.
     */
    public static function sqlFormat(string $column, string $format): string
    {
        $minutes = (int) (self::now()->getOffset() / 60);

        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('{$format}', {$column}, '{$minutes} minutes')"
            : "DATE_FORMAT(DATE_ADD({$column}, INTERVAL {$minutes} MINUTE), '{$format}')";
    }
}
