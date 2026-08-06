<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeploymentController extends Controller
{
    public function storeDeployment(Request $request)
    {
        $data = $request->all();
        $id = $data['id'] ?? ('dep-' . time() . '-' . substr(uniqid(), -5));
        $data['id'] = $id;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        // ponytail: the UI doesn't send position/shift/rotation/status, so fill
        // them from the employee record or sensible defaults.
        if (empty($data['position_id']) && !empty($data['employee_id'])) {
            $emp = DB::table('employees')->where('id', $data['employee_id'])->first();
            if ($emp) {
                $data['position_id'] = $emp->position_id;
            }
        }
        if (empty($data['shift_type_id'])) {
            $data['shift_type_id'] = null;
        }
        if (empty($data['rotation_type_id'])) {
            $data['rotation_type_id'] = null;
        }
        if (empty($data['status'])) {
            $data['status'] = 'planned';
        }
        if (empty($data['end_date'])) {
            $data['end_date'] = $data['start_date'];
        }
        // The deployments table has no notes column.
        unset($data['notes']);
        $data['b2b_partner_employee_id'] = !empty($data['b2b_partner_employee_id']) ? $data['b2b_partner_employee_id'] : '';

        DB::table('deployments')->insert($data);

        // ponytail: auto-create the Back-to-Back pairing record the UI expects.
        DB::table('back_to_back_pairs')->insert([
            'id' => 'b2b-' . time() . '-' . substr(uniqid(), -5),
            'deployment_id' => $id,
            'replacement_employee_id' => $data['b2b_partner_employee_id'],
            'handover_date' => $data['end_date'] ?? null,
            'status' => empty($data['b2b_partner_employee_id']) ? 'unassigned' : 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Mirror the Express server.ts behaviour: keep employee status in sync.
        $this->syncEmployeeStatus($data['employee_id'], $data['status']);

        return response()->json(DB::table('deployments')->where('id', $id)->first(), 201);
    }

    protected function syncEmployeeStatus(?string $employeeId, ?string $status): void
    {
        if (!$employeeId) {
            return;
        }
        if ($status === 'active') {
            DB::table('employees')->where('id', $employeeId)->update(['status' => 'deployed', 'updated_at' => now()]);
        } elseif (in_array($status, ['completed', 'cancelled'])) {
            DB::table('employees')->where('id', $employeeId)->update(['status' => 'active', 'updated_at' => now()]);
        }
    }

    public function updateDeployment(Request $request, $id)
    {
        $data = $request->all();
        $data['updated_at'] = now();

        $deployment = DB::table('deployments')->where('id', $id)->first();

        DB::table('deployments')->where('id', $id)->update($data);

        if (isset($data['status'])) {
            $employeeId = $data['employee_id'] ?? ($deployment->employee_id ?? null);
            $this->syncEmployeeStatus($employeeId, $data['status']);
        }

        return response()->json(DB::table('deployments')->where('id', $id)->first());
    }

    public function destroyDeployment($id)
    {
        DB::table('deployments')->where('id', $id)->delete();
        DB::table('back_to_back_pairs')->where('deployment_id', $id)->delete();

        return response()->json(['success' => true]);
    }

    public function updateB2B(Request $request, $id)
    {
        $data = $request->all();
        $data['updated_at'] = now();

        DB::table('back_to_back_pairs')->where('id', $id)->update($data);

        return response()->json(DB::table('back_to_back_pairs')->where('id', $id)->first());
    }

    public function storeLeave(Request $request)
    {
        $data = $request->all();
        $id = $data['id'] ?? ('lea-' . time());
        $data['id'] = $id;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        DB::table('leave_records')->insert($data);

        return response()->json(DB::table('leave_records')->where('id', $id)->first(), 201);
    }

    public function destroyLeave($id)
    {
        DB::table('leave_records')->where('id', $id)->delete();
        return response()->json(['success' => true]);
    }
}
