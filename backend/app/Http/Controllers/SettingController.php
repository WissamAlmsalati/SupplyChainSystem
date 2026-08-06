<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    protected $allowedTables = [
        'clients', 'fields', 'projects', 'locations',
        'departments', 'positions', 'document_types',
        'certificate_types', 'allowance_types', 'bonus_types', 'equipment_types',
        'leave_types', 'shift_types', 'rotation_types'
    ];

    public function store(Request $request, $table)
    {
        if (!in_array($table, $this->allowedTables)) {
            return response()->json(['error' => 'Invalid table'], 400);
        }

        $data = $request->all();
        $id = $data['id'] ?? (substr($table, 0, 2) . '-' . time());
        $data['id'] = $id;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        DB::table($table)->insert($data);

        return response()->json(DB::table($table)->where('id', $id)->first(), 201);
    }

    public function update(Request $request, $table, $id)
    {
        if (!in_array($table, $this->allowedTables)) {
            return response()->json(['error' => 'Invalid table'], 400);
        }

        $data = $request->all();
        $data['updated_at'] = now();

        DB::table($table)->where('id', $id)->update($data);

        return response()->json(DB::table($table)->where('id', $id)->first());
    }

    public function destroy($table, $id)
    {
        if (!in_array($table, $this->allowedTables)) {
            return response()->json(['error' => 'Invalid table'], 400);
        }

        DB::table($table)->where('id', $id)->delete();

        return response()->json(['success' => true]);
    }
}
