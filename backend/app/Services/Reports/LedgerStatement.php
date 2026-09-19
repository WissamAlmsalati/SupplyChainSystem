<?php

namespace App\Services\Reports;

use App\Support\BusinessTime;
use Illuminate\Database\Eloquent\Builder;

// Statement over a running-balance ledger (custody entries, wallet transactions):
// opening balance, the period's rows, totals per type, closing balance.
class LedgerStatement
{
    /**
     * @param  Builder  $ledger  query scoped to one account, ordered later by id
     * @param  array<string, string>  $labels  type value => Arabic label
     */
    public function build(Builder $ledger, Period $period, array $labels, string $currentBalance): array
    {
        $before = (clone $ledger)->where('created_at', '<', $period->from)->orderByDesc('id')->first();
        $opening = $before ? (float) $before->balance_after : 0.0;

        $rows = (clone $ledger)->whereBetween('created_at', [$period->from, $period->to])->orderBy('id')->get();
        $last = $rows->last();
        $closing = $last ? (float) $last->balance_after : $opening;

        $totals = $rows->groupBy(fn ($r) => $r->type instanceof \BackedEnum ? $r->type->value : $r->type)
            ->map(fn ($g, $type) => [
                'type' => $type,
                'label' => $labels[$type] ?? $type,
                'count' => $g->count(),
                'amount' => round((float) $g->sum('amount'), 2),
            ])->values();

        return [
            'period' => $period->toArray(),
            'opening_balance' => round($opening, 2),
            'closing_balance' => round($closing, 2),
            'current_balance' => round((float) $currentBalance, 2),
            'credits' => round((float) $rows->where('amount', '>', 0)->sum('amount'), 2),
            'debits' => round(abs((float) $rows->where('amount', '<', 0)->sum('amount')), 2),
            'totals' => $totals,
            'entries' => $rows->map(fn ($r) => [
                'id' => $r->id,
                'date' => BusinessTime::format($r->created_at, 'Y-m-d H:i:s'),
                'type' => $r->type instanceof \BackedEnum ? $r->type->value : $r->type,
                'label' => $labels[$r->type instanceof \BackedEnum ? $r->type->value : $r->type] ?? $r->type,
                'amount' => round((float) $r->amount, 2),
                'balance_after' => round((float) $r->balance_after, 2),
                'note' => $r->note,
                'by' => $r->createdBy?->name,
                'reference' => $r->reference_type ? class_basename($r->reference_type).' #'.$r->reference_id : null,
            ])->values(),
        ];
    }
}
