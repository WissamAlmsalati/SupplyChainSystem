<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->all();
        $id = $data['id'] ?? ('emp-' . time());
        $data['id'] = $id;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        // ponytail: auto-generate employee code when frontend doesn't send one,
        // mirroring the Express server.ts behaviour (TAQA-WT-1001+).
        if (empty($data['employee_code'])) {
            $lastNum = 1000;
            foreach (DB::table('employees')->pluck('employee_code') as $code) {
                if (preg_match('/TAQA-WT-(\d+)/', $code, $matches)) {
                    $lastNum = max($lastNum, (int) $matches[1]);
                }
            }
            $data['employee_code'] = 'TAQA-WT-' . ($lastNum + 1);
        }

        DB::table('employees')->insert($data);

        // Activity log
        DB::table('activity_logs')->insert([
            'id' => 'ac-' . time() . '-' . substr(uniqid(), -5),
            'entity_type' => 'employee',
            'entity_id' => $id,
            'action' => 'CREATE',
            'description' => "Employee " . ($data['first_name'] ?? '') . " " . ($data['last_name'] ?? '') . " created.",
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(DB::table('employees')->where('id', $id)->first(), 201);
    }

    public function update(Request $request, $id)
    {
        $data = $request->all();
        $data['updated_at'] = now();

        DB::table('employees')->where('id', $id)->update($data);

        DB::table('activity_logs')->insert([
            'id' => 'ac-' . time() . '-' . substr(uniqid(), -5),
            'entity_type' => 'employee',
            'entity_id' => $id,
            'action' => 'UPDATE',
            'description' => "Employee profile updated.",
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(DB::table('employees')->where('id', $id)->first());
    }

    public function destroy($id)
    {
        // ponytail: cascade delete all child records so the DB stays clean
        // when an employee is removed (matches the original Express behaviour).
        DB::table('emergency_contacts')->where('employee_id', $id)->delete();
        DB::table('documents')->where('employee_id', $id)->delete();
        DB::table('certificates')->where('employee_id', $id)->delete();
        DB::table('deployments')->where('employee_id', $id)->delete();
        DB::table('back_to_back_pairs')->whereIn('deployment_id', function ($query) use ($id) {
            $query->select('id')->from('deployments')->where('employee_id', $id);
        })->delete();
        DB::table('leave_records')->where('employee_id', $id)->delete();
        DB::table('evaluations')->where('employee_id', $id)->delete();
        DB::table('employee_notes')->where('employee_id', $id)->delete();
        DB::table('employee_attachments')->where('employee_id', $id)->delete();
        DB::table('equipment_assignments')->where('employee_id', $id)->delete();
        DB::table('ai_recommendations')->where('employee_id', $id)->delete();
        DB::table('payroll_entries')->where('employee_id', $id)->delete();

        DB::table('employees')->where('id', $id)->delete();

        return response()->json(['success' => true]);
    }
}
