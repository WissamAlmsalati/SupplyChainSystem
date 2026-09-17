<?php

namespace App\Services\Reports;

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

        $to = isset($data['to']) ? Carbon::createFromFormat('Y-m-d', $data['to']) : Carbon::today();
        $from = isset($data['from'])
            ? Carbon::createFromFormat('Y-m-d', $data['from'])
            : ($request->boolean('all') ? Carbon::create(2000, 1, 1) : $to->copy()->modify($default));

        return new self($from->startOfDay(), $to->endOfDay());
    }

    public function label(): string
    {
        return $this->from->format('Y-m-d').' → '.$this->to->format('Y-m-d');
    }

    public function slug(): string
    {
        return $this->from->format('Ymd').'-'.$this->to->format('Ymd');
    }

    public function toArray(): array
    {
        return ['from' => $this->from->toDateString(), 'to' => $this->to->toDateString()];
    }
}
