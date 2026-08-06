export interface Client {
  id: string;
  name: string;
  notes?: string;
  is_active: boolean;
}

export interface Field {
  id: string;
  name: string;
  client_id: string;
  location_id: string;
  is_active: boolean;
}

export interface Project {
  id: string;
  name: string;
  client_id: string;
  field_id: string;
  is_active: boolean;
}

export interface Location {
  id: string;
  name: string;
  region: string;
  country: string;
  is_active: boolean;
}

export interface Department {
  id: string;
  name: string;
  is_active: boolean;
}

export interface Position {
  id: string;
  name: string;
  department_id: string;
  is_active: boolean;
}

export interface DocumentType {
  id: string;
  name: string;
  requires_expiry: boolean;
  default_reminder_days: number;
  is_active: boolean;
}

export interface CertificateType {
  id: string;
  name: string;
  requires_expiry: boolean;
  default_reminder_days: number;
  is_active: boolean;
}

export interface ShiftType {
  id: string;
  name: string; // Day, Night, Rotating...
}

export interface RotationType {
  id: string;
  name: string; // 28/28, 21/21...
  cycle_days: number;
}

export interface LeaveType {
  id: string;
  name: string;
  is_paid: boolean;
}

export interface EvaluationTemplate {
  id: string;
  name: string;
  is_active: boolean;
}

export interface EvaluationTemplateSection {
  id: string;
  template_id: string;
  name: string;
  weight: number; // e.g. 0.40 for 40%
  sort_order: number;
}

export interface EvaluationTemplateItem {
  id: string;
  section_id: string;
  name_en: string;
  name_ar: string;
  sort_order: number;
}

export interface AllowanceType {
  id: string;
  name: string;
  default_amount: number;
  is_active: boolean;
}

export interface BonusType {
  id: string;
  name: string;
  is_active: boolean;
}

export interface EquipmentType {
  id: string;
  name: string;
  is_active: boolean;
}

// Master Records
export interface Employee {
  id: string;
  employee_code: string; // TAQA-WT-1001, etc.
  first_name: string;
  last_name: string;
  name_arabic?: string;
  contract_type: 'TAQA' | 'Consultant';
  department_id: string;
  position_id: string;
  date_of_birth?: string;
  gender?: string;
  marital_status?: string;
  family_members_count?: number;
  blood_group?: string;
  phone_primary: string;
  phone_secondary?: string;
  email: string;
  home_address?: string;
  photo_url?: string;
  passport_number?: string;
  passport_issue_date?: string;
  passport_expiry_date?: string;
  national_id_number?: string;
  driving_license_number?: string;
  driving_license_expiry_date?: string;
  daily_rate: number;
  currency?: 'USD' | 'LYD';
  status: 'active' | 'on_leave' | 'deployed' | 'inactive';
  hire_date: string;
  termination_date?: string;
  created_at: string;
  updated_at: string;
}

export interface EmergencyContact {
  id: string;
  employee_id: string;
  name: string;
  relationship: string;
  phone_1: string;
  phone_2?: string;
}

export interface Document {
  id: string;
  employee_id: string;
  document_type_id: string;
  issue_date?: string;
  expiry_date?: string;
  reminder_days: number;
  file_url?: string;
  file_name?: string;
  description?: string;
  notes?: string;
  status: 'valid' | 'expiring_soon' | 'expired' | 'missing';
  created_at: string;
  updated_at: string;
}

export interface Certificate {
  id: string;
  employee_id: string;
  certificate_type_id: string;
  training_provider?: string;
  certificate_number?: string;
  issue_date?: string;
  expiry_date?: string;
  file_url?: string;
  file_name?: string;
  notes?: string;
  status: 'valid' | 'expiring_soon' | 'expired' | 'missing';
  created_at: string;
  updated_at: string;
}

export interface PositionRequiredCertificate {
  position_id: string;
  certificate_type_id: string;
}

export interface Deployment {
  id: string;
  employee_id: string;
  client_id: string;
  field_id: string;
  project_id: string;
  position_id: string;
  start_date: string;
  end_date: string;
  shift_type_id: string;
  rotation_type_id: string;
  status: 'planned' | 'active' | 'completed' | 'cancelled';
  b2b_partner_employee_id?: string;
  rotation_duration_days?: number;
  created_at: string;
  updated_at: string;
}

export interface BackToBackPair {
  id: string;
  deployment_id: string;
  replacement_employee_id: string; // Employee who rotates with deployment employee
  handover_date: string;
  status: 'confirmed' | 'unassigned' | 'conflict';
}

export interface LeaveRecord {
  id: string;
  employee_id: string;
  leave_type_id: string;
  start_date: string;
  end_date: string;
  notes?: string;
}

export interface Evaluation {
  id: string;
  employee_id: string;
  template_id: string;
  deployment_id?: string;
  field_client_label?: string; // Client/Field e.g. "MobileEPF"
  job_type?: string;
  period_start: string;
  period_end: string;
  supervisor_name: string;
  would_rehire: boolean;
  rehire_justification?: string;
  supervisor_comments?: string;
  employee_comments?: string;
  overall_score: number; // calculated percent (e.g., 85.5)
  competency_score?: number;
  hse_score?: number;
  training_score?: number;
  attitude_score?: number;
  status: 'draft' | 'submitted';
  created_at: string;
}

export interface EvaluationScore {
  id: string;
  evaluation_id: string;
  template_item_id: string;
  score: number; // 1 to 5
}

export interface Allowance {
  id: string;
  employee_id: string;
  allowance_type_id: string;
  amount: number;
  period_month: string; // YYYY-MM
  notes?: string;
}

export interface Bonus {
  id: string;
  employee_id: string;
  bonus_type_id: string;
  amount: number;
  period_month: string; // YYYY-MM
  notes?: string;
}

export interface PayrollPeriod {
  id: string; // YYYY-MM
  period_month: string; // e.g. "2026-07"
  status: 'draft' | 'locked';
}

export interface PayrollEntry {
  id: string;
  payroll_period_id: string;
  employee_id: string;
  days_worked: number;
  field_days: number;
  day_shifts: number;
  night_shifts: number;
  daily_rate: number;
  base_amount: number; // days_worked * daily_rate
  total_allowances: number;
  total_bonuses: number;
  manual_adjustment: number;
  adjustment_reason?: string;
  total_amount: number; // base_amount + allowances + bonuses + adjustments
}

export interface EquipmentAssignment {
  id: string;
  employee_id: string;
  equipment_type_id: string;
  issued_date: string;
  returned_date?: string;
  notes?: string;
}

export interface EmployeeNote {
  id: string;
  employee_id: string;
  body: string;
  created_at: string;
}

export interface EmployeeAttachment {
  id: string;
  employee_id: string;
  file_url: string;
  label: string;
  created_at: string;
}

export interface ActivityLog {
  id: string;
  entity_type: string; // 'employee' | 'document' | 'deployment' | etc.
  entity_id: string;
  action: string;
  description: string;
  created_at: string;
}

export interface AIRecommendation {
  id: string;
  employee_id?: string;
  category: string; // 'expiring_cert' | 'missing_doc' | 'overdue_appraisal' | 'unassigned_b2b' | 'scheduling_conflict'
  reason: string;
  impact: string;
  suggested_action: string;
  status: 'new' | 'applied' | 'ignored';
  created_at: string;
  resolved_at?: string;
}
