<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BootstrapController extends Controller
{
    public function index()
    {
        return response()->json([
            'clients' => DB::table('clients')->get(),
            'fields' => DB::table('fields')->get(),
            'projects' => DB::table('projects')->get(),
            'locations' => DB::table('locations')->get(),
            'departments' => DB::table('departments')->get(),
            'positions' => DB::table('positions')->get(),
            'document_types' => DB::table('document_types')->get(),
            'certificate_types' => DB::table('certificate_types')->get(),
            'shift_types' => DB::table('shift_types')->get(),
            'rotation_types' => DB::table('rotation_types')->get(),
            'leave_types' => DB::table('leave_types')->get(),
            'evaluation_templates' => DB::table('evaluation_templates')->get(),
            'evaluation_template_sections' => DB::table('evaluation_template_sections')->get(),
            'evaluation_template_items' => DB::table('evaluation_template_items')->get(),
            'allowance_types' => DB::table('allowance_types')->get(),
            'bonus_types' => DB::table('bonus_types')->get(),
            'equipment_types' => DB::table('equipment_types')->get(),
            'employees' => DB::table('employees')->get(),
            'emergency_contacts' => DB::table('emergency_contacts')->get(),
            'documents' => DB::table('documents')->get(),
            'certificates' => DB::table('certificates')->get(),
            'position_required_certificates' => DB::table('position_required_certificates')->get(),
            'deployments' => DB::table('deployments')->get(),
            'back_to_back_pairs' => DB::table('back_to_back_pairs')->get(),
            'leave_records' => DB::table('leave_records')->get(),
            'evaluations' => DB::table('evaluations')->get(),
            'evaluation_scores' => DB::table('evaluation_scores')->get(),
            'payroll_periods' => DB::table('payroll_periods')->get(),
            'payroll_entries' => DB::table('payroll_entries')->get(),
            'allowances' => DB::table('allowances')->get(),
            'bonuses' => DB::table('bonuses')->get(),
            'equipment_assignments' => DB::table('equipment_assignments')->get(),
            'notes' => DB::table('employee_notes')->get(),
            'attachments' => DB::table('employee_attachments')->get(),
            'activity_log' => DB::table('activity_logs')->orderBy('created_at', 'desc')->get(),
            'ai_recommendations' => DB::table('ai_recommendations')->get(),
        ]);
    }
}
