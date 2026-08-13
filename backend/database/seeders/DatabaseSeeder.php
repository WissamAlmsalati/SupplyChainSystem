<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $possiblePaths = [
            database_path('seeders/db.json'),
            base_path('../data/db.json'),
            base_path('data/db.json'),
            dirname(base_path()) . '/data/db.json',
            '/var/www/html/../data/db.json'
        ];

        $dbPath = null;
        foreach ($possiblePaths as $p) {
            if (File::exists($p)) {
                $dbPath = $p;
                break;
            }
        }

        if (!$dbPath) {
            $this->command->warn("data/db.json not found in candidate paths, skipping seed.");
            return;
        }

        $this->command->info("Seeding from file: {$dbPath}");
        $data = json_decode(File::get($dbPath), true);
        if (!$data) return;

        // Key to table mapping
        $tableMapping = [
            'clients' => 'clients',
            'fields' => 'fields',
            'projects' => 'projects',
            'locations' => 'locations',
            'departments' => 'departments',
            'positions' => 'positions',
            'document_types' => 'document_types',
            'certificate_types' => 'certificate_types',
            'shift_types' => 'shift_types',
            'rotation_types' => 'rotation_types',
            'leave_types' => 'leave_types',
            'evaluation_templates' => 'evaluation_templates',
            'evaluation_template_sections' => 'evaluation_template_sections',
            'evaluation_template_items' => 'evaluation_template_items',
            'allowance_types' => 'allowance_types',
            'bonus_types' => 'bonus_types',
            'equipment_types' => 'equipment_types',
            'employees' => 'employees',
            'emergency_contacts' => 'emergency_contacts',
            'documents' => 'documents',
            'certificates' => 'certificates',
            'position_required_certificates' => 'position_required_certificates',
            'deployments' => 'deployments',
            'back_to_back_pairs' => 'back_to_back_pairs',
            'leave_records' => 'leave_records',
            'evaluations' => 'evaluations',
            'evaluation_scores' => 'evaluation_scores',
            'payroll_periods' => 'payroll_periods',
            'payroll_entries' => 'payroll_entries',
            'allowances' => 'allowances',
            'bonuses' => 'bonuses',
            'equipment_assignments' => 'equipment_assignments',
            'employee_notes' => 'employee_notes',
            'employee_attachments' => 'employee_attachments',
            'activity_log' => 'activity_logs',
            'ai_recommendations' => 'ai_recommendations',
        ];

        foreach ($tableMapping as $jsonKey => $tableName) {
            if (isset($data[$jsonKey]) && is_array($data[$jsonKey])) {
                foreach ($data[$jsonKey] as $row) {
                    $formattedRow = [];
                    foreach ($row as $col => $val) {
                        if (is_bool($val)) {
                            $formattedRow[$col] = $val ? 1 : 0;
                        } else {
                            $formattedRow[$col] = $val;
                        }
                    }
                    $formattedRow['created_at'] = now();
                    $formattedRow['updated_at'] = now();
                    
                    DB::table($tableName)->updateOrInsert(
                        isset($formattedRow['id']) ? ['id' => $formattedRow['id']] : $formattedRow,
                        $formattedRow
                    );
                }
            }
        }
    }
}
