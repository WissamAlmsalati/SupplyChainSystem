<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Clients
        Schema::create('clients', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Locations
        Schema::create('locations', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('region')->nullable();
            $table->string('country')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Fields
        Schema::create('fields', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('client_id');
            $table->string('location_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 4. Projects
        Schema::create('projects', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('client_id');
            $table->string('field_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 5. Departments
        Schema::create('departments', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 6. Positions
        Schema::create('positions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('department_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 7. Document Types
        Schema::create('document_types', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->boolean('requires_expiry')->default(true);
            $table->integer('default_reminder_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 8. Certificate Types
        Schema::create('certificate_types', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->boolean('requires_expiry')->default(true);
            $table->integer('default_reminder_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 9. Shift Types
        Schema::create('shift_types', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        // 10. Rotation Types
        Schema::create('rotation_types', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('cycle_days')->default(28);
            $table->timestamps();
        });

        // 11. Leave Types
        Schema::create('leave_types', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->boolean('is_paid')->default(true);
            $table->timestamps();
        });

        // 12. Evaluation Templates & Structure
        Schema::create('evaluation_templates', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('evaluation_template_sections', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('template_id');
            $table->string('name');
            $table->decimal('weight', 5, 2)->default(0.25);
            $table->integer('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::create('evaluation_template_items', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('section_id');
            $table->string('name_en');
            $table->string('name_ar')->nullable();
            $table->integer('sort_order')->default(1);
            $table->timestamps();
        });

        // 13. Financial Types
        Schema::create('allowance_types', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->decimal('default_amount', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bonus_types', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('equipment_types', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 14. Employees Master Table
        Schema::create('employees', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('employee_code')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('name_arabic')->nullable();
            $table->string('contract_type')->default('TAQA');
            $table->string('department_id')->nullable();
            $table->string('position_id')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->string('marital_status')->nullable();
            $table->integer('family_members_count')->nullable();
            $table->string('blood_group')->nullable();
            $table->string('phone_primary')->nullable();
            $table->string('phone_secondary')->nullable();
            $table->string('email')->nullable();
            $table->text('home_address')->nullable();
            $table->string('photo_url')->nullable();
            $table->string('passport_number')->nullable();
            $table->date('passport_issue_date')->nullable();
            $table->date('passport_expiry_date')->nullable();
            $table->string('national_id_number')->nullable();
            $table->string('driving_license_number')->nullable();
            $table->date('driving_license_expiry_date')->nullable();
            $table->decimal('daily_rate', 10, 2)->default(0);
            $table->string('currency')->default('USD');
            $table->string('status')->default('active');
            $table->date('hire_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->timestamps();
        });

        // 15. Emergency Contacts
        Schema::create('emergency_contacts', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('employee_id');
            $table->string('name');
            $table->string('relationship');
            $table->string('phone_1');
            $table->string('phone_2')->nullable();
            $table->timestamps();
        });

        // 16. Documents & Certificates
        Schema::create('documents', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('employee_id');
            $table->string('document_type_id');
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->integer('reminder_days')->default(30);
            $table->string('file_url')->nullable();
            $table->string('file_name')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('valid');
            $table->timestamps();
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('employee_id');
            $table->string('certificate_type_id');
            $table->string('training_provider')->nullable();
            $table->string('certificate_number')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('file_url')->nullable();
            $table->string('file_name')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('valid');
            $table->timestamps();
        });

        Schema::create('position_required_certificates', function (Blueprint $table) {
            $table->id();
            $table->string('position_id');
            $table->string('certificate_type_id');
            $table->timestamps();
        });

        // 17. Operations & Deployments
        Schema::create('deployments', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('employee_id');
            $table->string('client_id');
            $table->string('field_id');
            $table->string('project_id');
            $table->string('position_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('shift_type_id')->nullable();
            $table->string('rotation_type_id')->nullable();
            $table->string('status')->default('planned');
            $table->string('b2b_partner_employee_id')->nullable();
            $table->integer('rotation_duration_days')->nullable();
            $table->timestamps();
        });

        Schema::create('back_to_back_pairs', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('deployment_id');
            $table->string('replacement_employee_id');
            $table->date('handover_date')->nullable();
            $table->string('status')->default('unassigned');
            $table->timestamps();
        });

        Schema::create('leave_records', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('employee_id');
            $table->string('leave_type_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 18. Evaluations
        Schema::create('evaluations', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('employee_id');
            $table->string('template_id');
            $table->string('deployment_id')->nullable();
            $table->string('field_client_label')->nullable();
            $table->string('job_type')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('supervisor_name')->nullable();
            $table->boolean('would_rehire')->default(true);
            $table->text('rehire_justification')->nullable();
            $table->text('supervisor_comments')->nullable();
            $table->text('employee_comments')->nullable();
            $table->decimal('overall_score', 5, 2)->default(0);
            $table->decimal('competency_score', 5, 2)->nullable();
            $table->decimal('hse_score', 5, 2)->nullable();
            $table->decimal('training_score', 5, 2)->nullable();
            $table->decimal('attitude_score', 5, 2)->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('evaluation_scores', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('evaluation_id');
            $table->string('template_item_id');
            $table->integer('score')->default(5);
            $table->timestamps();
        });

        // 19. Payroll
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->string('id')->primary(); // e.g. "2026-07"
            $table->string('period_month');
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('payroll_entries', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('payroll_period_id');
            $table->string('employee_id');
            $table->integer('days_worked')->default(0);
            $table->integer('field_days')->default(0);
            $table->integer('day_shifts')->default(0);
            $table->integer('night_shifts')->default(0);
            $table->decimal('daily_rate', 10, 2)->default(0);
            $table->decimal('base_amount', 10, 2)->default(0);
            $table->decimal('total_allowances', 10, 2)->default(0);
            $table->decimal('total_bonuses', 10, 2)->default(0);
            $table->decimal('manual_adjustment', 10, 2)->default(0);
            $table->text('adjustment_reason')->nullable();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('allowances', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('employee_id');
            $table->string('allowance_type_id');
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('period_month');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('bonuses', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('employee_id');
            $table->string('bonus_type_id');
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('period_month');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 20. Equipment & Auxiliary
        Schema::create('equipment_assignments', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('employee_id');
            $table->string('equipment_type_id');
            $table->date('issued_date');
            $table->date('returned_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_notes', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('employee_id');
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('employee_attachments', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('employee_id');
            $table->string('file_url');
            $table->string('label');
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('entity_type');
            $table->string('entity_id');
            $table->string('action');
            $table->text('description');
            $table->timestamps();
        });

        Schema::create('ai_recommendations', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('employee_id')->nullable();
            $table->string('category');
            $table->text('reason');
            $table->text('impact');
            $table->text('suggested_action');
            $table->string('status')->default('new');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_recommendations');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('employee_attachments');
        Schema::dropIfExists('employee_notes');
        Schema::dropIfExists('equipment_assignments');
        Schema::dropIfExists('bonuses');
        Schema::dropIfExists('allowances');
        Schema::dropIfExists('payroll_entries');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('evaluation_scores');
        Schema::dropIfExists('evaluations');
        Schema::dropIfExists('leave_records');
        Schema::dropIfExists('back_to_back_pairs');
        Schema::dropIfExists('deployments');
        Schema::dropIfExists('position_required_certificates');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('emergency_contacts');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('equipment_types');
        Schema::dropIfExists('bonus_types');
        Schema::dropIfExists('allowance_types');
        Schema::dropIfExists('evaluation_template_items');
        Schema::dropIfExists('evaluation_template_sections');
        Schema::dropIfExists('evaluation_templates');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('rotation_types');
        Schema::dropIfExists('shift_types');
        Schema::dropIfExists('certificate_types');
        Schema::dropIfExists('document_types');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('fields');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('clients');
    }
};
