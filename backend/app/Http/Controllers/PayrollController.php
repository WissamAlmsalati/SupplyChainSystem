<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    public function getPeriod($period)
    {
        $payrollPeriod = DB::table('payroll_periods')->where('id', $period)->first();
        if (!$payrollPeriod) {
            $payrollPeriod = [
                'id' => $period,
                'period_month' => $period,
                'status' => 'draft',
                'created_at' => now(),
                'updated_at' => now()
            ];
            DB::table('payroll_periods')->insert($payrollPeriod);
        }

        $employees = DB::table('employees')->where('status', '!=', 'inactive')->get();
        $existingEntries = DB::table('payroll_entries')->where('payroll_period_id', $period)->get()->keyBy('employee_id');

        $entries = [];
        foreach ($employees as $emp) {
            if (isset($existingEntries[$emp->id])) {
                $entries[] = $existingEntries[$emp->id];
            } else {
                $newEntry = [
                    'id' => "pe-{$period}-{$emp->id}",
                    'payroll_period_id' => $period,
                    'employee_id' => $emp->id,
                    'days_worked' => 22,
                    'field_days' => 15,
                    'day_shifts' => 10,
                    'night_shifts' => 5,
                    'daily_rate' => $emp->daily_rate,
                    'base_amount' => 22 * $emp->daily_rate,
                    'total_allowances' => 0,
                    'total_bonuses' => 0,
                    'manual_adjustment' => 0,
                    'adjustment_reason' => null,
                    'total_amount' => 22 * $emp->daily_rate,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                DB::table('payroll_entries')->insert($newEntry);
                $entries[] = (object)$newEntry;
            }
        }

        return response()->json([
            'period' => DB::table('payroll_periods')->where('id', $period)->first(),
            'entries' => $entries
        ]);
    }

    public function updateEntry(Request $request, $period, $entryId)
    {
        $data = $request->all();
        $data['updated_at'] = now();

        DB::table('payroll_entries')->where('id', $entryId)->update($data);

        return response()->json(DB::table('payroll_entries')->where('id', $entryId)->first());
    }

    public function lockPeriod($period)
    {
        DB::table('payroll_periods')->where('id', $period)->update([
            'status' => 'locked',
            'updated_at' => now()
        ]);

        return response()->json(['success' => true]);
    }

    public function storeAllowance(Request $request)
    {
        $data = $request->all();
        $id = $data['id'] ?? ('all-' . time());
        $data['id'] = $id;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        DB::table('allowances')->insert($data);

        return response()->json(DB::table('allowances')->where('id', $id)->first(), 201);
    }

    public function storeBonus(Request $request)
    {
        $data = $request->all();
        $id = $data['id'] ?? ('bon-' . time());
        $data['id'] = $id;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        DB::table('bonuses')->insert($data);

        return response()->json(DB::table('bonuses')->where('id', $id)->first(), 201);
    }
}
