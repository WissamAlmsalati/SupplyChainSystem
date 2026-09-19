<?php

namespace App\Services\Reports;

use App\Support\BusinessTime;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

// Date range shared by every report: ?from=Y-m-d&to=Y-m-d (inclusive days).
final class Period
{
    public function __construct(public readonly Carbon $from, public readonly Carbon $to) {}

    // Defaults to the last 30 days; ?all=1 means "since the beginning".
    public static function fromRequest(Request $request, ?string $default = '-30 days'): self
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'all' => ['nullable', Rule::in(['0', '1', 'true', 'false'])],
        ]);

        // The days are the office's days (see BusinessTime); the bounds are
        // handed on as UTC instants because that is what the columns hold.
        $tz = BusinessTime::tz();
        $to = isset($data['to']) ? Carbon::createFromFormat('Y-m-d', $data['to'], $tz) : BusinessTime::now();
        $from = isset($data['from'])
            ? Carbon::createFromFormat('Y-m-d', $data['from'], $tz)
            : ($request->boolean('all') ? Carbon::create(2000, 1, 1, 0, 0, 0, $tz) : $to->copy()->modify($default));

        return new self($from->startOfDay()->utc(), $to->endOfDay()->utc());
    }

    public function label(): string
    {
        return BusinessTime::format($this->from, 'Y-m-d').' → '.BusinessTime::format($this->to, 'Y-m-d');
    }

    public function slug(): string
    {
        return BusinessTime::format($this->from, 'Ymd').'-'.BusinessTime::format($this->to, 'Ymd');
    }

    public function toArray(): array
    {
        return ['from' => BusinessTime::format($this->from, 'Y-m-d'), 'to' => BusinessTime::format($this->to, 'Y-m-d')];
    }
}
