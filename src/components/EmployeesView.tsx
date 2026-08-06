import React, { useState, useRef, useEffect } from 'react';
import {
  Search,
  Filter,
  Plus,
  User,
  Phone,
  Mail,
  MapPin,
  Calendar,
  Briefcase,
  FileText,
  Award,
  History,
  TrendingUp,
  FileUp,
  Heart,
  HardDrive,
  Trash2,
  Edit2,
  X,
  PlusCircle,
  AlertTriangle,
  ChevronRight,
  Pin,
  Check
} from 'lucide-react';
import {
  Employee,
  Department,
  Position,
  EmergencyContact,
  Document,
  DocumentType,
  Certificate,
  CertificateType,
  Deployment,
  LeaveRecord,
  LeaveType,
  Evaluation,
  EquipmentAssignment,
  EquipmentType,
  EmployeeNote,
  EmployeeAttachment,
  Client,
  Field,
  Project
} from '../types';
import { usePagination } from '../hooks/usePagination';
import Pagination from './Pagination';

interface EmployeesViewProps {
  employees: Employee[];
  departments: Department[];
  positions: Position[];
  emergencyContacts: EmergencyContact[];
  documents: Document[];
  docTypes: DocumentType[];
  certificates: Certificate[];
  certTypes: CertificateType[];
  deployments: Deployment[];
  leaveRecords: LeaveRecord[];
  leaveTypes: LeaveType[];
  evaluations: Evaluation[];
  equipmentAssignments: EquipmentAssignment[];
  equipmentTypes: EquipmentType[];
  notes: EmployeeNote[];
  attachments: EmployeeAttachment[];
  clients: Client[];
  fields: Field[];
  projects: Project[];
  
  onAddEmployee: (empData: any) => Promise<any>;
  onEditEmployee: (id: string, empData: any) => Promise<any>;
  onDeleteEmployee: (id: string) => Promise<any>;
  onAddDocument: (docData: any) => Promise<any>;
  onDeleteDocument: (id: string) => Promise<any>;
  onAddCertificate: (certData: any) => Promise<any>;
  onDeleteCertificate: (id: string) => Promise<any>;
  onAddNote: (noteData: any) => Promise<any>;
  onDeleteNote: (id: string) => Promise<any>;
  onAddAttachment: (attachData: any) => Promise<any>;
  onDeleteAttachment: (id: string) => Promise<any>;
  onAddEquipment: (eqData: any) => Promise<any>;
  onReturnEquipment: (id: string) => Promise<any>;

  initialActionState?: any;
}

export default function EmployeesView({
  employees,
  departments,
  positions,
  emergencyContacts,
  documents,
  docTypes,
  certificates,
  certTypes,
  deployments,
  leaveRecords,
  leaveTypes,
  evaluations,
  equipmentAssignments,
  equipmentTypes,
  notes,
  attachments,
  clients,
  fields,
  projects,

  onAddEmployee,
  onEditEmployee,
  onDeleteEmployee,
  onAddDocument,
  onDeleteDocument,
  onAddCertificate,
  onDeleteCertificate,
  onAddNote,
  onDeleteNote,
  onAddAttachment,
  onDeleteAttachment,
  onAddEquipment,
  onReturnEquipment,

  initialActionState
}: EmployeesViewProps) {
  // -------------------------------------------------------------
  // STATE
  // -------------------------------------------------------------
  const [selectedEmployeeId, setSelectedEmployeeId] = useState<string | null>(
    initialActionState?.selectedEmployeeId || (employees.length > 0 ? employees[0].id : null)
  );
  const [searchQuery, setSearchQuery] = useState(initialActionState?.filterSearch || '');
  const [statusFilter, setStatusFilter] = useState('');
  const [contractFilter, setContractFilter] = useState('');
  const [deptFilter, setDeptFilter] = useState('');

  const formatEmployeeValue = (val: number, currencyParam?: 'USD' | 'LYD') => {
    const cur = currencyParam || 'USD';
    if (cur === 'LYD') {
      return `${val.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} LYD`;
    }
    return `$${val.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 })} USD`;
  };

  const formatEmployeeValueShort = (val: number, currencyParam?: 'USD' | 'LYD') => {
    const cur = currencyParam || 'USD';
    if (cur === 'LYD') {
      return `${val.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 })} LYD`;
    }
    return `$${val.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 })}/d`;
  };

  const fileInputRef = useRef<HTMLInputElement>(null);

  // Modals Toggles
  const [showAddEmpModal, setShowAddEmpModal] = useState(false);
  const [showEditEmpModal, setShowEditEmpModal] = useState(false);
  const [showAddDocModal, setShowAddDocModal] = useState(false);
  const [showAddCertModal, setShowAddCertModal] = useState(false);
  const [showAddEqModal, setShowAddEqModal] = useState(false);
  const [showAddAttachmentModal, setShowAddAttachmentModal] = useState(false);

  // Profile Tab Selection
  const [activeTab, setActiveTab] = useState('overview');

  // Form State
  const [empForm, setEmpForm] = useState({
    first_name: '',
    last_name: '',
    name_arabic: '',
    contract_type: 'TAQA',
    department_id: departments[0]?.id || '',
    position_id: positions[0]?.id || '',
    date_of_birth: '',
    gender: 'Male',
    marital_status: 'Single',
    family_members_count: 0,
    blood_group: 'O+',
    phone_primary: '',
    phone_secondary: '',
    email: '',
    home_address: '',
    passport_number: '',
    passport_issue_date: '',
    passport_expiry_date: '',
    national_id_number: '',
    driving_license_number: '',
    driving_license_expiry_date: '',
    daily_rate: 500,
    currency: 'USD' as 'USD' | 'LYD',
    hire_date: new Date().toISOString().split('T')[0]
  });

  const [docForm, setDocForm] = useState({
    document_type_id: docTypes[0]?.id || '',
    issue_date: '',
    expiry_date: '',
    reminder_days: 90,
    description: '',
    notes: '',
    file_base64: '',
    file_name: ''
  });

  const [certForm, setCertForm] = useState({
    certificate_type_id: certTypes[0]?.id || '',
    training_provider: '',
    certificate_number: '',
    issue_date: '',
    expiry_date: '',
    notes: '',
    file_base64: '',
    file_name: ''
  });

  const [eqForm, setEqForm] = useState({
    equipment_type_id: equipmentTypes[0]?.id || '',
    issued_date: new Date().toISOString().split('T')[0],
    notes: ''
  });

  const [attachForm, setAttachForm] = useState({
    label: '',
    file_base64: '',
    file_name: ''
  });

  const [noteText, setNoteText] = useState('');

  // -------------------------------------------------------------
  // CALCULATIONS & FILTERING
  // -------------------------------------------------------------
  const filteredEmployees = employees.filter((emp) => {
    const fullName = `${emp.first_name} ${emp.last_name}`.toLowerCase();
    const arabicName = (emp.name_arabic || '').toLowerCase();
    const code = emp.employee_code.toLowerCase();
    const matchesSearch =
      fullName.includes(searchQuery.toLowerCase()) ||
      arabicName.includes(searchQuery.toLowerCase()) ||
      code.includes(searchQuery.toLowerCase());

    const matchesStatus = statusFilter ? emp.status === statusFilter : true;
    const matchesContract = contractFilter ? emp.contract_type === contractFilter : true;
    const matchesDept = deptFilter ? emp.department_id === deptFilter : true;

    return matchesSearch && matchesStatus && matchesContract && matchesDept;
  });

  const {
    page: dirPage,
    setPage: setDirPage,
    paginatedItems: paginatedEmployees,
    totalPages: dirTotalPages,
    pageSize: dirPageSize,
    totalItems: dirTotalItems
  } = usePagination(filteredEmployees, 10);

  useEffect(() => {
    setDirPage(1);
  }, [searchQuery, statusFilter, contractFilter, deptFilter]);

  const currentEmployee = employees.find((e) => e.id === selectedEmployeeId);

  const [rateInput, setRateInput] = useState<string>('');

  useEffect(() => {
    if (currentEmployee) {
      setRateInput(currentEmployee.daily_rate.toString());
    }
  }, [currentEmployee?.id, currentEmployee?.daily_rate]);

  const handleRateInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setRateInput(e.target.value);
  };

  const handleRateInputSave = async () => {
    if (!currentEmployee) return;
    const numericVal = parseFloat(rateInput);
    if (isNaN(numericVal) || numericVal <= 0) return;

    await onEditEmployee(currentEmployee.id, {
      daily_rate: numericVal
    });
  };

  // Linked details of selected employee
  const empEmergencyContacts = emergencyContacts.filter((ec) => ec.employee_id === selectedEmployeeId);
  const empDocs = documents.filter((d) => d.employee_id === selectedEmployeeId);
  const empCerts = certificates.filter((c) => c.employee_id === selectedEmployeeId);
  const empDeployments = deployments.filter((d) => d.employee_id === selectedEmployeeId);
  const empLeaves = leaveRecords.filter((l) => l.employee_id === selectedEmployeeId);
  const empEvaluations = evaluations.filter((e) => e.employee_id === selectedEmployeeId);
  const empEq = equipmentAssignments.filter((eq) => eq.employee_id === selectedEmployeeId);
  const empNotes = notes.filter((n) => n.employee_id === selectedEmployeeId);
  const empAttachments = attachments.filter((a) => a.employee_id === selectedEmployeeId);

  const {
    page: notesPage,
    setPage: setNotesPage,
    paginatedItems: paginatedNotes,
    totalPages: notesTotalPages,
    pageSize: notesPageSize,
    totalItems: notesTotalItems
  } = usePagination(empNotes, 10);

  const {
    page: docsPage,
    setPage: setDocsPage,
    paginatedItems: paginatedDocs,
    totalPages: docsTotalPages,
    pageSize: docsPageSize,
    totalItems: docsTotalItems
  } = usePagination(empDocs, 10);

  const {
    page: certsPage,
    setPage: setCertsPage,
    paginatedItems: paginatedCerts,
    totalPages: certsTotalPages,
    pageSize: certsPageSize,
    totalItems: certsTotalItems
  } = usePagination(empCerts, 10);

  const {
    page: depsPage,
    setPage: setDepsPage,
    paginatedItems: paginatedDeployments,
    totalPages: depsTotalPages,
    pageSize: depsPageSize,
    totalItems: depsTotalItems
  } = usePagination(empDeployments, 10);

  const {
    page: evalsPage,
    setPage: setEvalsPage,
    paginatedItems: paginatedEvaluations,
    totalPages: evalsTotalPages,
    pageSize: evalsPageSize,
    totalItems: evalsTotalItems
  } = usePagination(empEvaluations, 10);

  const {
    page: eqPage,
    setPage: setEqPage,
    paginatedItems: paginatedEquipment,
    totalPages: eqTotalPages,
    pageSize: eqPageSize,
    totalItems: eqTotalItems
  } = usePagination(empEq, 10);

  useEffect(() => {
    setNotesPage(1);
    setDocsPage(1);
    setCertsPage(1);
    setDepsPage(1);
    setEvalsPage(1);
    setEqPage(1);
  }, [selectedEmployeeId, activeTab]);

  // -------------------------------------------------------------
  // FILE HANDLING UTILITIES
  // -------------------------------------------------------------
  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>, setter: (data: any) => void) => {
    const file = e.target.files?.[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = () => {
        setter((prev: any) => ({
          ...prev,
          file_base64: reader.result as string,
          file_name: file.name
        }));
      };
      reader.readAsDataURL(file);
    }
  };

  const handlePhotoUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file && currentEmployee) {
      const reader = new FileReader();
      reader.onload = async () => {
        const base64 = reader.result as string;
        await onEditEmployee(currentEmployee.id, {
          photo_base64: base64,
          photo_name: file.name
        });
      };
      reader.readAsDataURL(file);
    }
  };

  // -------------------------------------------------------------
  // HANDLERS
  // -------------------------------------------------------------
  const handleCreateEmployeeSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const newEmp = await onAddEmployee(empForm);
    if (newEmp) {
      setSelectedEmployeeId(newEmp.id);
      setShowAddEmpModal(false);
    }
  };

  const handleEditEmployeeSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedEmployeeId) return;
    const updated = await onEditEmployee(selectedEmployeeId, empForm);
    if (updated) {
      setShowEditEmpModal(false);
    }
  };

  const handleDeleteEmployeeClick = async () => {
    if (!selectedEmployeeId) return;
    if (confirm('Are you absolutely sure you want to permanently archive and delete this employee profile? All certificates, documents, and historical deployments will be archived.')) {
      await onDeleteEmployee(selectedEmployeeId);
      setSelectedEmployeeId(employees.length > 0 ? employees[0].id : null);
    }
  };

  const handleAddDocSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedEmployeeId) return;
    await onAddDocument({ ...docForm, employee_id: selectedEmployeeId });
    setShowAddDocModal(false);
    setDocForm({
      document_type_id: docTypes[0]?.id || '',
      issue_date: '',
      expiry_date: '',
      reminder_days: 90,
      description: '',
      notes: '',
      file_base64: '',
      file_name: ''
    });
  };

  const handleAddCertSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedEmployeeId) return;
    await onAddCertificate({ ...certForm, employee_id: selectedEmployeeId });
    setShowAddCertModal(false);
    setCertForm({
      certificate_type_id: certTypes[0]?.id || '',
      training_provider: '',
      certificate_number: '',
      issue_date: '',
      expiry_date: '',
      notes: '',
      file_base64: '',
      file_name: ''
    });
  };

  const handleAddEqSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedEmployeeId) return;
    await onAddEquipment({ ...eqForm, employee_id: selectedEmployeeId });
    setShowAddEqModal(false);
  };

  const handleAddAttachmentSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedEmployeeId) return;
    await onAddAttachment({ ...attachForm, employee_id: selectedEmployeeId });
    setShowAddAttachmentModal(false);
    setAttachForm({ label: '', file_base64: '', file_name: '' });
  };

  const handleNoteSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedEmployeeId || !noteText.trim()) return;
    await onAddNote({ employee_id: selectedEmployeeId, body: noteText });
    setNoteText('');
  };

  const triggerEditModal = () => {
    if (!currentEmployee) return;
    setEmpForm({
      first_name: currentEmployee.first_name,
      last_name: currentEmployee.last_name,
      name_arabic: currentEmployee.name_arabic || '',
      contract_type: currentEmployee.contract_type,
      department_id: currentEmployee.department_id,
      position_id: currentEmployee.position_id,
      date_of_birth: currentEmployee.date_of_birth || '',
      gender: currentEmployee.gender || 'Male',
      marital_status: currentEmployee.marital_status || 'Single',
      family_members_count: currentEmployee.family_members_count || 0,
      blood_group: currentEmployee.blood_group || 'O+',
      phone_primary: currentEmployee.phone_primary,
      phone_secondary: currentEmployee.phone_secondary || '',
      email: currentEmployee.email,
      home_address: currentEmployee.home_address || '',
      passport_number: currentEmployee.passport_number || '',
      passport_issue_date: currentEmployee.passport_issue_date || '',
      passport_expiry_date: currentEmployee.passport_expiry_date || '',
      national_id_number: currentEmployee.national_id_number || '',
      driving_license_number: currentEmployee.driving_license_number || '',
      driving_license_expiry_date: currentEmployee.driving_license_expiry_date || '',
      daily_rate: currentEmployee.daily_rate,
      currency: currentEmployee.currency || 'USD',
      hire_date: currentEmployee.hire_date
    });
    setShowEditEmpModal(true);
  };

  return (
    <div className="flex h-[calc(100vh-65px)] overflow-hidden" id="employees-view-wrapper">
      {/* 1. LEFT COLUMN: Employees Directory (Sidebar List) */}
      <div className="w-1/3 bg-[#0a0f1d] border-r border-slate-800 flex flex-col h-full shrink-0">
        {/* Directory Controls */}
        <div className="p-4 border-b border-slate-800 space-y-3 bg-[#0d1424]">
          <div className="flex items-center justify-between">
            <h3 className="font-bold text-slate-200 tracking-wider text-xs uppercase">Workforce Directory</h3>
            <button
              onClick={() => {
                setEmpForm({
                  first_name: '',
                  last_name: '',
                  name_arabic: '',
                  contract_type: 'TAQA',
                  department_id: departments[0]?.id || '',
                  position_id: positions[0]?.id || '',
                  date_of_birth: '',
                  gender: 'Male',
                  marital_status: 'Single',
                  family_members_count: 0,
                  blood_group: 'O+',
                  phone_primary: '',
                  phone_secondary: '',
                  email: '',
                  home_address: '',
                  passport_number: '',
                  passport_issue_date: '',
                  passport_expiry_date: '',
                  national_id_number: '',
                  driving_license_number: '',
                  driving_license_expiry_date: '',
                  daily_rate: 500,
                  currency: 'USD',
                  hire_date: new Date().toISOString().split('T')[0]
                });
                setShowAddEmpModal(true);
              }}
              className="bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold px-2.5 py-1.5 rounded-lg text-xs flex items-center gap-1.5 cursor-pointer"
            >
              <Plus className="w-4 h-4" /> Add Profile
            </button>
          </div>

          {/* Quick Filter Controls */}
          <div className="space-y-2">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" />
              <input
                type="text"
                placeholder="Search code, name..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="w-full bg-[#111827] border border-slate-850 rounded-lg pl-9 pr-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500"
              />
            </div>

            <div className="grid grid-cols-3 gap-1">
              <select
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value)}
                className="bg-[#111827] border border-slate-850 rounded py-1 px-1.5 text-[10px] text-slate-300"
              >
                <option value="">Status</option>
                <option value="active">Active</option>
                <option value="deployed">Deployed</option>
                <option value="on_leave">On Leave</option>
              </select>

              <select
                value={contractFilter}
                onChange={(e) => setContractFilter(e.target.value)}
                className="bg-[#111827] border border-slate-850 rounded py-1 px-1.5 text-[10px] text-slate-300"
              >
                <option value="">Contract</option>
                <option value="TAQA">TAQA</option>
                <option value="Consultant">Consultant</option>
              </select>

              <select
                value={deptFilter}
                onChange={(e) => setDeptFilter(e.target.value)}
                className="bg-[#111827] border border-slate-850 rounded py-1 px-1.5 text-[10px] text-slate-300"
              >
                <option value="">Dept</option>
                {departments.map(d => (
                  <option key={d.id} value={d.id}>{d.name}</option>
                ))}
              </select>
            </div>
          </div>
        </div>

        {/* Directory Scrolling List */}
        <div className="flex-1 overflow-y-auto divide-y divide-slate-800/50">
          {paginatedEmployees.map((emp) => {
            const isSelected = emp.id === selectedEmployeeId;
            const dept = departments.find((d) => d.id === emp.department_id)?.name || 'Operations';
            const pos = positions.find((p) => p.id === emp.position_id)?.name || 'Staff';

            return (
              <button
                key={emp.id}
                onClick={() => setSelectedEmployeeId(emp.id)}
                className={`w-full text-left p-4 flex items-center justify-between transition-colors ${
                  isSelected ? 'bg-slate-800/40 border-l-4 border-emerald-500' : 'hover:bg-slate-800/10'
                }`}
              >
                <div className="flex items-center gap-3">
                  {emp.photo_url ? (
                    <img
                      src={emp.photo_url}
                      alt={`${emp.first_name} ${emp.last_name}`}
                      className="w-9 h-9 rounded-lg object-cover border border-slate-700"
                      referrerPolicy="no-referrer"
                    />
                  ) : (
                    <div className="w-9 h-9 bg-slate-800 rounded-lg flex items-center justify-center font-bold text-slate-300 text-xs tracking-wider border border-slate-700 uppercase">
                      {emp.first_name[0]}{emp.last_name[0]}
                    </div>
                  )}
                  <div>
                    <div className="flex items-center gap-2">
                      <span className="font-bold text-slate-100 text-xs">{emp.first_name} {emp.last_name}</span>
                      <span className="font-mono text-[9px] text-slate-500">{emp.employee_code}</span>
                    </div>
                    <span className="text-[10px] text-slate-400 block mt-0.5">{pos} • {dept}</span>
                  </div>
                </div>

                <div className="flex flex-col items-end gap-1.5">
                  <span className={`text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded font-mono ${
                    emp.status === 'deployed' ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/20' :
                    emp.status === 'on_leave' ? 'bg-pink-500/15 text-pink-400 border border-pink-500/20' :
                    'bg-slate-500/15 text-slate-400 border border-slate-500/20'
                  }`}>
                    {emp.status}
                  </span>
                  <span className="text-[10px] text-slate-400 font-mono font-bold">{formatEmployeeValueShort(emp.daily_rate, emp.currency)}</span>
                </div>
              </button>
            );
          })}

          {filteredEmployees.length === 0 && (
            <div className="text-center py-12 text-slate-500 text-sm">
              No matching employees found.
            </div>
          )}
        </div>

        <div className="p-4 border-t border-slate-800 bg-[#0d1424]">
          <Pagination
            page={dirPage}
            totalPages={dirTotalPages}
            totalItems={dirTotalItems}
            pageSize={dirPageSize}
            onPageChange={setDirPage}
          />
        </div>
      </div>

      {/* 2. RIGHT COLUMN: Selected Employee Profile Details */}
      <div className="flex-1 overflow-y-auto bg-[#080d19] h-full flex flex-col">
        {currentEmployee ? (
          <div className="flex-1 flex flex-col">
            {/* Profile Overview Banner */}
            <div className="p-6 border-b border-slate-800 bg-[#0c1424] flex flex-col md:flex-row md:items-center justify-between gap-4">
              <div className="flex items-center gap-4">
                {/* Hidden input for photo change */}
                <input
                  type="file"
                  ref={fileInputRef}
                  onChange={handlePhotoUpload}
                  accept="image/*"
                  className="hidden"
                />
                <div
                  onClick={() => fileInputRef.current?.click()}
                  className="relative group w-16 h-16 cursor-pointer select-none"
                  title="Click to change profile picture"
                >
                  {currentEmployee.photo_url ? (
                    <img
                      src={currentEmployee.photo_url}
                      alt={`${currentEmployee.first_name} ${currentEmployee.last_name}`}
                      className="w-16 h-16 rounded-xl object-cover border-2 border-slate-700 transition-transform duration-250 group-hover:scale-[1.02]"
                      referrerPolicy="no-referrer"
                    />
                  ) : (
                    <div className="w-16 h-16 bg-slate-800 rounded-xl flex items-center justify-center border-2 border-slate-700 font-bold text-xl text-slate-100 uppercase transition-transform duration-250 group-hover:scale-[1.02]">
                      {currentEmployee.first_name[0]}{currentEmployee.last_name[0]}
                    </div>
                  )}
                  {/* Photo Edit Overlay */}
                  <div className="absolute inset-0 bg-slate-950/60 rounded-xl flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity animate-fade-in duration-200">
                    <Edit2 className="w-4 h-4 text-white" />
                  </div>
                  {/* Visual Pin Overlay pinned at the top-right */}
                  <div className="absolute -top-1.5 -right-1.5 bg-rose-500 text-white rounded-full p-1 border border-slate-900 shadow-md transform rotate-12 group-hover:scale-110 transition-transform duration-200">
                    <Pin className="w-3.5 h-3.5" />
                  </div>
                </div>
                <div>
                  <div className="flex items-center gap-3">
                    <h2 className="text-xl font-bold text-white font-sans">{currentEmployee.first_name} {currentEmployee.last_name}</h2>
                    {currentEmployee.name_arabic && (
                      <span className="text-slate-400 font-medium font-sans text-sm tracking-wide" dir="rtl">
                        {currentEmployee.name_arabic}
                      </span>
                    )}
                  </div>
                  <div className="flex items-center gap-3 mt-1 text-xs text-slate-400">
                    <span className="font-mono bg-slate-800 px-2 py-0.5 rounded text-slate-300">{currentEmployee.employee_code}</span>
                    <span>•</span>
                    <span className="uppercase tracking-wider font-semibold text-emerald-400">{currentEmployee.contract_type} CONTRACT</span>
                  </div>
                </div>
              </div>

              {/* Action Buttons */}
              <div className="flex items-center gap-2">
                <button
                  onClick={triggerEditModal}
                  className="bg-slate-800 hover:bg-slate-700 text-slate-100 px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 border border-slate-700 cursor-pointer"
                >
                  <Edit2 className="w-3.5 h-3.5" /> Edit Profile
                </button>
                <button
                  onClick={handleDeleteEmployeeClick}
                  className="bg-rose-500/10 hover:bg-rose-500 text-rose-400 hover:text-slate-950 px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 border border-rose-500/20 transition-all cursor-pointer"
                >
                  <Trash2 className="w-3.5 h-3.5" /> Archive Profile
                </button>
              </div>
            </div>

            {/* Profile Navigation Tabs */}
            <div className="bg-[#0b1120] border-b border-slate-800 px-6 flex items-center gap-6 overflow-x-auto shrink-0">
              {[
                { id: 'overview', label: 'Overview', icon: User },
                { id: 'personal', label: 'Personal & Contact', icon: Heart },
                { id: 'employment', label: 'Employment & Job', icon: Briefcase },
                { id: 'documents', label: 'Documents', icon: FileText },
                { id: 'certificates', label: 'Certificates', icon: Award },
                { id: 'deployments', label: 'Deployments', icon: History },
                { id: 'appraisals', label: 'Appraisal History', icon: TrendingUp },
                { id: 'equipment', label: 'Issued Equipment', icon: HardDrive }
              ].map((tab) => {
                const Icon = tab.icon;
                const isActive = activeTab === tab.id;
                return (
                  <button
                    key={tab.id}
                    onClick={() => setActiveTab(tab.id)}
                    className={`py-3.5 border-b-2 font-medium text-xs tracking-wider uppercase transition-colors shrink-0 cursor-pointer flex items-center gap-2 ${
                      isActive
                        ? 'border-emerald-500 text-emerald-400'
                        : 'border-transparent text-slate-400 hover:text-slate-200'
                    }`}
                  >
                    <Icon className="w-3.5 h-3.5" /> {tab.label}
                  </button>
                );
              })}
            </div>

            {/* Tab Contents */}
            <div className="p-6 flex-1 space-y-6">
              
              {/* TAB 1: OVERVIEW */}
              {activeTab === 'overview' && (
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6 animate-fade-in">
                  <div className="md:col-span-2 space-y-6">
                    {/* Notes Feed */}
                    <div className="bg-[#0e1626] border border-slate-800 p-5 rounded-xl space-y-4">
                      <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider">Internal Notes & History Logs</h3>
                      <form onSubmit={handleNoteSubmit} className="flex gap-2">
                        <input
                          type="text"
                          placeholder="Type an operational note for this employee..."
                          value={noteText}
                          onChange={(e) => setNoteText(e.target.value)}
                          className="flex-1 bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500"
                        />
                        <button
                          type="submit"
                          className="bg-[#1b253b] hover:bg-[#25324e] border border-slate-800 text-emerald-400 font-bold px-4 py-2 rounded-lg text-xs shrink-0 cursor-pointer"
                        >
                          Save Note
                        </button>
                      </form>

                      <div className="space-y-3 max-h-48 overflow-y-auto">
                        {paginatedNotes.map((note) => (
                          <div key={note.id} className="p-3 bg-slate-900/40 rounded-lg flex justify-between items-start border border-slate-800/40">
                            <div>
                              <p className="text-xs text-slate-200 leading-relaxed font-sans">{note.body}</p>
                              <span className="text-[9px] text-slate-500 font-mono block mt-1">
                                {new Date(note.created_at).toLocaleString()}
                              </span>
                            </div>
                            <button
                              onClick={() => onDeleteNote(note.id)}
                              className="text-slate-500 hover:text-rose-400 p-1 cursor-pointer"
                            >
                              <X className="w-3.5 h-3.5" />
                            </button>
                          </div>
                        ))}

                        {empNotes.length === 0 && (
                          <p className="text-xs text-slate-500 py-3 text-center">No notes entered yet.</p>
                        )}
                      </div>

                      <Pagination
                        page={notesPage}
                        totalPages={notesTotalPages}
                        totalItems={notesTotalItems}
                        pageSize={notesPageSize}
                        onPageChange={setNotesPage}
                      />
                    </div>

                    {/* Quick deployment summary */}
                    <div className="bg-[#0e1626] border border-slate-800 p-5 rounded-xl">
                      <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Active Operational Assignment</h3>
                      {empDeployments.filter(d => d.status === 'active').map((dep) => {
                        const client = clients.find(c => c.id === dep.client_id)?.name;
                        const field = fields.find(f => f.id === dep.field_id)?.name;
                        const proj = projects.find(p => p.id === dep.project_id)?.name;
                        return (
                          <div key={dep.id} className="p-4 bg-emerald-500/5 border border-emerald-500/20 rounded-xl flex items-center justify-between">
                            <div>
                              <h4 className="font-bold text-slate-200 text-sm font-sans">{client} • {field}</h4>
                              <p className="text-xs text-slate-400 mt-1">Project: {proj || 'Main Assignment'}</p>
                              <p className="text-xs font-mono text-slate-500 mt-1">Rotation Cycle: {dep.start_date} to {dep.end_date}</p>
                            </div>
                            <span className="bg-emerald-500/20 text-emerald-400 font-bold font-mono text-[10px] px-2 py-0.5 rounded uppercase tracking-wider">
                              ACTIVE IN FIELD
                            </span>
                          </div>
                        );
                      })}

                      {empDeployments.filter(d => d.status === 'active').length === 0 && (
                        <div className="text-center py-6 border border-dashed border-slate-800 rounded-xl">
                          <p className="text-xs text-slate-500 font-sans">No active deployed rotations found in schedules.</p>
                        </div>
                      )}
                    </div>
                  </div>

                  {/* Sidebar Stats block */}
                  <div className="space-y-6">
                    {/* Quick Compliance block */}
                    <div className="bg-[#0e1626] border border-slate-800 p-5 rounded-xl space-y-4">
                      <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider">Compliance Status</h3>
                      <div className="space-y-3">
                        <div className="flex justify-between items-center border-b border-slate-800/50 pb-2">
                          <span className="text-xs text-slate-300">Registered Passports</span>
                          <span className={`text-[10px] font-bold px-2 py-0.5 rounded ${
                            empDocs.some(d => d.status === 'expired') ? 'bg-rose-500/10 text-rose-400' : 'bg-emerald-500/10 text-emerald-400'
                          }`}>
                            {empDocs.some(d => d.status === 'expired') ? 'EXPIRED' : 'COMPLIANT'}
                          </span>
                        </div>

                        <div className="flex justify-between items-center border-b border-slate-800/50 pb-2">
                          <span className="text-xs text-slate-300">Active Certifications</span>
                          <span className="font-bold font-mono text-xs text-white">{empCerts.filter(c => c.status === 'valid').length} Valid</span>
                        </div>

                        <div className="flex justify-between items-center">
                          <span className="text-xs text-slate-300">Last Appraisal Grade</span>
                          <span className="font-bold font-mono text-xs text-emerald-400">
                            {empEvaluations.length > 0 ? `${empEvaluations[0].overall_score}%` : 'N/A'}
                          </span>
                        </div>
                      </div>
                    </div>

                    {/* Quick Emergency Contacts */}
                    <div className="bg-[#0e1626] border border-slate-800 p-5 rounded-xl space-y-4">
                      <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider">Emergency Contact</h3>
                      {empEmergencyContacts.map((contact) => (
                        <div key={contact.id} className="space-y-2">
                          <p className="text-xs font-bold text-slate-200">{contact.name} ({contact.relationship})</p>
                          <div className="flex items-center gap-1.5 text-xs text-slate-400">
                            <Phone className="w-3.5 h-3.5 text-slate-500" /> {contact.phone_1}
                          </div>
                        </div>
                      ))}

                      {empEmergencyContacts.length === 0 && (
                        <p className="text-xs text-slate-500 italic">No contacts listed.</p>
                      )}
                    </div>
                  </div>
                </div>
              )}

              {/* TAB 2: PERSONAL & CONTACT */}
              {activeTab === 'personal' && (
                <div className="bg-[#0e1626] border border-slate-800 p-6 rounded-xl space-y-6 animate-fade-in">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="space-y-4">
                      <h4 className="text-xs font-bold text-slate-400 uppercase tracking-wider border-b border-slate-800 pb-2">Contact Details</h4>
                      <div className="grid grid-cols-3 gap-2 text-xs">
                        <span className="text-slate-500">Email:</span>
                        <span className="col-span-2 text-slate-200 font-medium">{currentEmployee.email}</span>

                        <span className="text-slate-500">Phone:</span>
                        <span className="col-span-2 text-slate-200 font-medium">{currentEmployee.phone_primary}</span>

                        <span className="text-slate-500">Secondary:</span>
                        <span className="col-span-2 text-slate-200 font-medium">{currentEmployee.phone_secondary || 'N/A'}</span>

                        <span className="text-slate-500">Home Address:</span>
                        <span className="col-span-2 text-slate-200 font-medium leading-relaxed">{currentEmployee.home_address || 'N/A'}</span>
                      </div>
                    </div>

                    <div className="space-y-4">
                      <h4 className="text-xs font-bold text-slate-400 uppercase tracking-wider border-b border-slate-800 pb-2">Demographic Profile</h4>
                      <div className="grid grid-cols-3 gap-2 text-xs">
                        <span className="text-slate-500">Date of Birth:</span>
                        <span className="col-span-2 text-slate-200 font-medium">{currentEmployee.date_of_birth || 'N/A'}</span>

                        <span className="text-slate-500">Gender:</span>
                        <span className="col-span-2 text-slate-200 font-medium">{currentEmployee.gender}</span>

                        <span className="text-slate-500">Blood Group:</span>
                        <span className="col-span-2 text-rose-400 font-bold font-mono">{currentEmployee.blood_group || 'Unknown'}</span>

                        <span className="text-slate-500">Marital Status:</span>
                        <span className="col-span-2 text-slate-200 font-medium">{currentEmployee.marital_status}</span>

                        <span className="text-slate-500">Family Count:</span>
                        <span className="col-span-2 text-slate-200 font-mono font-bold">{currentEmployee.family_members_count || 0} Members</span>
                      </div>
                    </div>
                  </div>
                </div>
              )}

              {/* TAB 3: EMPLOYMENT & JOB */}
              {activeTab === 'employment' && (
                <div className="bg-[#0e1626] border border-slate-800 p-6 rounded-xl space-y-6 animate-fade-in">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="space-y-4">
                      <h4 className="text-xs font-bold text-slate-400 uppercase tracking-wider border-b border-slate-800 pb-2">Employment Parameters</h4>
                      <div className="grid grid-cols-3 gap-2 text-xs">
                        <span className="text-slate-500">Department:</span>
                        <span className="col-span-2 text-slate-200 font-bold">
                          {departments.find((d) => d.id === currentEmployee.department_id)?.name || 'Unknown'}
                        </span>

                        <span className="text-slate-500">Position Role:</span>
                        <span className="col-span-2 text-emerald-400 font-bold">
                          {positions.find((p) => p.id === currentEmployee.position_id)?.name || 'Unknown'}
                        </span>

                        <span className="text-slate-500">Hire Date:</span>
                        <span className="col-span-2 text-slate-200 font-mono">{currentEmployee.hire_date}</span>

                        <span className="text-slate-500">Contracting Mode:</span>
                        <span className="col-span-2 text-slate-200 font-medium">{currentEmployee.contract_type}</span>
                      </div>
                    </div>

                    <div className="space-y-4">
                      <h4 className="text-xs font-bold text-slate-400 uppercase tracking-wider border-b border-slate-800 pb-2 flex items-center justify-between">
                        <span>Financial Accounting</span>
                        <span className="flex items-center bg-[#111827] border border-slate-850 rounded p-0.5 scale-[0.9] origin-right normal-case tracking-normal">
                          <button
                            type="button"
                            onClick={() => onEditEmployee(currentEmployee.id, { currency: 'USD' })}
                            className={`px-2 py-0.5 text-[9px] font-bold rounded transition-all cursor-pointer ${
                              (currentEmployee.currency || 'USD') === 'USD'
                                ? 'bg-emerald-500 text-slate-950 shadow'
                                : 'text-slate-400 hover:text-slate-200'
                            }`}
                          >
                            USD
                          </button>
                          <button
                            type="button"
                            onClick={() => onEditEmployee(currentEmployee.id, { currency: 'LYD' })}
                            className={`px-2 py-0.5 text-[9px] font-bold rounded transition-all cursor-pointer ${
                              (currentEmployee.currency || 'USD') === 'LYD'
                                ? 'bg-emerald-500 text-slate-950 shadow'
                                : 'text-slate-400 hover:text-slate-200'
                            }`}
                          >
                            LYD
                          </button>
                        </span>
                      </h4>
                      <div className="grid grid-cols-3 gap-y-3 gap-x-2 items-center text-xs">
                        <span className="text-slate-500 font-semibold">Daily Salary Rate:</span>
                        <div className="col-span-2 flex items-center gap-2">
                          <div className="relative flex-1">
                            <input
                              type="number"
                              step="any"
                              value={rateInput}
                              onChange={handleRateInputChange}
                              onBlur={handleRateInputSave}
                              onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                  handleRateInputSave();
                                  e.currentTarget.blur();
                                }
                              }}
                              className="w-full bg-[#111827] border border-slate-800 focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/30 rounded-lg pl-3 pr-12 py-1.5 text-white font-mono font-bold text-sm transition-all focus:outline-none"
                              placeholder="Rate"
                            />
                            <span className="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-mono text-slate-400 font-bold">
                              {currentEmployee.currency || 'USD'}
                            </span>
                          </div>
                          <button
                            type="button"
                            onClick={handleRateInputSave}
                            className="bg-emerald-500/10 hover:bg-emerald-500/20 active:bg-emerald-500/35 border border-emerald-500/30 text-emerald-400 p-1.5 rounded-lg transition-all cursor-pointer"
                            title="Save Rate"
                          >
                            <Check className="w-4 h-4" />
                          </button>
                        </div>

                        <span className="text-slate-500 font-medium text-slate-400">Est. Monthly Base:</span>
                        <span className="col-span-2 text-emerald-400 font-mono font-bold text-sm">
                          {formatEmployeeValue(currentEmployee.daily_rate * 28, currentEmployee.currency || 'USD')} <span className="text-[10px] text-slate-500 font-normal">(28 Days)</span>
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              )}

              {/* TAB 4: DOCUMENTS */}
              {activeTab === 'documents' && (
                <div className="space-y-6 animate-fade-in">
                  {/* Personal Government ID block */}
                  <div className="bg-[#0e1626] border border-slate-800 p-5 rounded-xl">
                    <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 border-b border-slate-800 pb-2">Primary Government Credentials</h3>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                      <div className="bg-slate-900/40 p-4 rounded-lg border border-slate-800/65">
                        <span className="block text-slate-500 uppercase tracking-wider text-[10px] font-bold">Passport Number</span>
                        <span className="block font-bold text-slate-200 mt-1 font-mono text-sm">{currentEmployee.passport_number || 'N/A'}</span>
                        <span className="block text-[10px] text-slate-500 mt-2">Expiry: {currentEmployee.passport_expiry_date || 'N/A'}</span>
                      </div>

                      <div className="bg-slate-900/40 p-4 rounded-lg border border-slate-800/65">
                        <span className="block text-slate-500 uppercase tracking-wider text-[10px] font-bold">National ID / Iqama</span>
                        <span className="block font-bold text-slate-200 mt-1 font-mono text-sm">{currentEmployee.national_id_number || 'N/A'}</span>
                      </div>

                      <div className="bg-slate-900/40 p-4 rounded-lg border border-slate-800/65">
                        <span className="block text-slate-500 uppercase tracking-wider text-[10px] font-bold">Driving License</span>
                        <span className="block font-bold text-slate-200 mt-1 font-mono text-sm">{currentEmployee.driving_license_number || 'N/A'}</span>
                        <span className="block text-[10px] text-slate-500 mt-2">Expiry: {currentEmployee.driving_license_expiry_date || 'N/A'}</span>
                      </div>
                    </div>
                  </div>

                  {/* Uploaded Documents scan list */}
                  <div className="bg-[#0e1626] border border-slate-800 p-5 rounded-xl">
                    <div className="flex items-center justify-between mb-4">
                      <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider">Compliance Documents & Scans</h3>
                      <button
                        onClick={() => setShowAddDocModal(true)}
                        className="bg-slate-800 hover:bg-slate-700 text-emerald-400 font-bold px-2.5 py-1.5 rounded text-xs flex items-center gap-1.5 cursor-pointer"
                      >
                        <FileUp className="w-4 h-4" /> Upload Document
                      </button>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      {paginatedDocs.map((doc) => {
                        const type = docTypes.find(d => d.id === doc.document_type_id)?.name || 'Document';
                        return (
                          <div key={doc.id} className="p-4 bg-slate-900/40 border border-slate-800 rounded-xl flex items-center justify-between">
                            <div className="flex items-center gap-3">
                              <div className="w-9 h-9 bg-slate-850 rounded-lg flex items-center justify-center">
                                <FileText className="w-5 h-5 text-slate-400" />
                              </div>
                              <div>
                                <span className="block font-bold text-xs text-slate-200">{type}</span>
                                <span className="block text-[10px] text-slate-500 mt-0.5 font-mono">{doc.file_name}</span>
                                {doc.expiry_date && (
                                  <span className="block text-[10px] text-slate-400 mt-1">Expires: {doc.expiry_date}</span>
                                )}
                              </div>
                            </div>

                            <div className="flex items-center gap-2">
                              <span className={`text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded ${
                                doc.status === 'valid' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400'
                              }`}>
                                {doc.status}
                              </span>
                              <button
                                onClick={() => onDeleteDocument(doc.id)}
                                className="text-slate-500 hover:text-rose-400 p-1.5 cursor-pointer"
                              >
                                <Trash2 className="w-4 h-4" />
                              </button>
                            </div>
                          </div>
                        );
                      })}

                      {empDocs.length === 0 && (
                        <div className="md:col-span-2 text-center py-8 text-slate-500 text-xs">
                          No documents uploaded yet. Click Upload Document above.
                        </div>
                      )}
                    </div>

                    <Pagination
                      page={docsPage}
                      totalPages={docsTotalPages}
                      totalItems={docsTotalItems}
                      pageSize={docsPageSize}
                      onPageChange={setDocsPage}
                    />
                  </div>
                </div>
              )}

              {/* TAB 5: CERTIFICATES */}
              {activeTab === 'certificates' && (
                <div className="bg-[#0e1626] border border-slate-800 p-5 rounded-xl space-y-6 animate-fade-in">
                  <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider">Active Credentials & Certifications</h3>
                    <button
                      onClick={() => setShowAddCertModal(true)}
                      className="bg-slate-850 hover:bg-slate-800 text-emerald-400 font-bold px-2.5 py-1.5 rounded text-xs flex items-center gap-1.5 cursor-pointer"
                    >
                      <PlusCircle className="w-4 h-4" /> Register Certificate
                    </button>
                  </div>

                  <div className="space-y-4">
                    {paginatedCerts.map((cert) => {
                      const type = certTypes.find(ct => ct.id === cert.certificate_type_id)?.name || 'Certificate';
                      return (
                        <div key={cert.id} className="p-4 bg-slate-900/30 border border-slate-800 rounded-xl flex items-center justify-between">
                          <div>
                            <div className="flex items-center gap-3">
                              <h4 className="font-bold text-sm text-slate-200">{type}</h4>
                              <span className="font-mono text-[10px] text-slate-500">{cert.certificate_number || 'No Num'}</span>
                            </div>
                            <p className="text-xs text-slate-400 mt-1">Provider: {cert.training_provider || 'TAQA Training Academy'}</p>
                            <p className="text-[10px] font-mono text-slate-500 mt-1">Validity: {cert.issue_date || 'N/A'} to {cert.expiry_date || 'N/A'}</p>
                          </div>

                          <div className="flex items-center gap-4">
                            <span className={`text-[10px] font-bold font-mono px-2 py-0.5 rounded ${
                              cert.status === 'valid' ? 'bg-emerald-500/10 text-emerald-400' :
                              cert.status === 'expiring_soon' ? 'bg-amber-500/10 text-amber-400' :
                              'bg-rose-500/10 text-rose-400'
                            }`}>
                              {cert.status.replace('_', ' ')}
                            </span>
                            <button
                              onClick={() => onDeleteCertificate(cert.id)}
                              className="text-slate-500 hover:text-rose-400 cursor-pointer"
                            >
                              <Trash2 className="w-4 h-4" />
                            </button>
                          </div>
                        </div>
                      );
                    })}

                    {empCerts.length === 0 && (
                      <p className="text-xs text-slate-500 py-6 text-center">No certifications registered.</p>
                    )}
                  </div>

                  <Pagination
                    page={certsPage}
                    totalPages={certsTotalPages}
                    totalItems={certsTotalItems}
                    pageSize={certsPageSize}
                    onPageChange={setCertsPage}
                  />
                </div>
              )}

              {/* TAB 6: DEPLOYMENTS */}
              {activeTab === 'deployments' && (
                <div className="bg-[#0e1626] border border-slate-800 p-5 rounded-xl space-y-6 animate-fade-in">
                  <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 border-b border-slate-800 pb-2">Scheduling & Rotation History</h3>
                  <div className="relative border-l border-slate-800 pl-4 space-y-6">
                    {paginatedDeployments.map((dep) => {
                      const client = clients.find(c => c.id === dep.client_id)?.name;
                      const field = fields.find(f => f.id === dep.field_id)?.name;
                      return (
                        <div key={dep.id} className="relative group">
                          <div className="absolute -left-[21px] top-1.5 w-2.5 h-2.5 bg-slate-800 group-hover:bg-emerald-500 rounded-full border border-slate-900"></div>
                          <div>
                            <div className="flex items-center justify-between gap-2">
                              <span className="text-xs font-bold text-slate-200">{client} • {field}</span>
                              <span className={`text-[9px] font-bold font-mono uppercase px-2 py-0.5 rounded ${
                                dep.status === 'active' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-slate-500/10 text-slate-400'
                              }`}>{dep.status}</span>
                            </div>
                            <span className="text-[10px] font-mono text-slate-500 mt-1 block">Rotations: {dep.start_date} to {dep.end_date}</span>
                          </div>
                        </div>
                      );
                    })}

                    {empDeployments.length === 0 && (
                      <p className="text-xs text-slate-500 py-4 text-center">No assignment rotations found.</p>
                    )}
                  </div>

                  <Pagination
                    page={depsPage}
                    totalPages={depsTotalPages}
                    totalItems={depsTotalItems}
                    pageSize={depsPageSize}
                    onPageChange={setDepsPage}
                  />
                </div>
              )}

              {/* TAB 7: APPRAISAL HISTORY */}
              {activeTab === 'appraisals' && (
                <div className="bg-[#0e1626] border border-slate-800 p-5 rounded-xl space-y-6 animate-fade-in">
                  <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 border-b border-slate-800 pb-2">Historic Appraisal Records</h3>
                  <div className="space-y-4">
                    {paginatedEvaluations.map((evalRecord) => (
                      <div key={evalRecord.id} className="p-4 bg-slate-900/30 border border-slate-800 rounded-xl space-y-2">
                        <div className="flex items-center justify-between">
                          <div>
                            <span className="text-xs text-slate-400">Supervisor: {evalRecord.supervisor_name}</span>
                            <span className="block text-[10px] text-slate-500 font-mono mt-0.5">Period: {evalRecord.period_start} to {evalRecord.period_end}</span>
                          </div>
                          <div className="text-right">
                            <span className="text-lg font-bold font-mono text-emerald-400">{evalRecord.overall_score}%</span>
                            <span className="block text-[9px] text-slate-500 uppercase tracking-wider mt-0.5">Overall Grade</span>
                          </div>
                        </div>

                        {evalRecord.supervisor_comments && (
                          <p className="text-xs text-slate-300 italic bg-black/20 p-2.5 rounded border-l-2 border-slate-700 leading-relaxed mt-2">
                            "{evalRecord.supervisor_comments}"
                          </p>
                        )}

                        <div className="pt-2 flex items-center gap-3 text-[10px]">
                          <span className={`px-2 py-0.5 rounded font-bold ${
                            evalRecord.would_rehire ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400'
                          }`}>
                            {evalRecord.would_rehire ? 'Recommended for Rehire' : 'Rehire Warning'}
                          </span>
                        </div>
                      </div>
                    ))}

                    {empEvaluations.length === 0 && (
                      <p className="text-xs text-slate-500 py-6 text-center">No digital appraisals filed.</p>
                    )}
                  </div>

                  <Pagination
                    page={evalsPage}
                    totalPages={evalsTotalPages}
                    totalItems={evalsTotalItems}
                    pageSize={evalsPageSize}
                    onPageChange={setEvalsPage}
                  />
                </div>
              )}

              {/* TAB 8: EQUIPMENT */}
              {activeTab === 'equipment' && (
                <div className="bg-[#0e1626] border border-slate-800 p-5 rounded-xl space-y-6 animate-fade-in">
                  <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider">Issued Industrial Gear</h3>
                    <button
                      onClick={() => setShowAddEqModal(true)}
                      className="bg-slate-850 hover:bg-slate-800 text-emerald-400 font-bold px-2.5 py-1.5 rounded text-xs flex items-center gap-1.5 cursor-pointer"
                    >
                      <PlusCircle className="w-4 h-4" /> Issue Equipment
                    </button>
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {paginatedEquipment.map((item) => {
                      const type = equipmentTypes.find(eq => eq.id === item.equipment_type_id)?.name || 'Gear';
                      return (
                        <div key={item.id} className="p-4 bg-slate-900/30 border border-slate-800 rounded-xl flex items-center justify-between">
                          <div>
                            <h4 className="font-bold text-xs text-slate-200">{type}</h4>
                            <span className="block text-[10px] text-slate-500 mt-1 font-mono">Issued: {item.issued_date}</span>
                            {item.notes && <p className="text-[10px] text-slate-400 mt-1 italic">"{item.notes}"</p>}
                          </div>
                          
                          {!item.returned_date ? (
                            <button
                              onClick={() => onReturnEquipment(item.id)}
                              className="text-xs font-semibold text-rose-400 hover:text-rose-300 border border-rose-500/20 hover:border-rose-400/40 bg-rose-500/5 px-2 py-1 rounded cursor-pointer"
                            >
                              Return Gear
                            </button>
                          ) : (
                            <span className="text-[10px] text-slate-500 uppercase font-bold">Returned ({item.returned_date})</span>
                          )}
                        </div>
                      );
                    })}

                    {empEq.length === 0 && (
                      <p className="md:col-span-2 text-xs text-slate-500 text-center py-6">No equipment issued.</p>
                    )}
                  </div>

                  <Pagination
                    page={eqPage}
                    totalPages={eqTotalPages}
                    totalItems={eqTotalItems}
                    pageSize={eqPageSize}
                    onPageChange={setEqPage}
                  />
                </div>
              )}

            </div>
          </div>
        ) : (
          <div className="flex-1 flex flex-col items-center justify-center text-slate-500">
            <User className="w-12 h-12 text-slate-600 mb-2" />
            <p className="text-sm font-sans">Select an employee from the directory to review profiles</p>
          </div>
        )}
      </div>

      {/* -------------------------------------------------------------
          MODALS SECTION (MODALS POPUPS)
         ------------------------------------------------------------- */}
      
      {/* 1. ADD EMPLOYEE PROFILE MODAL */}
      {showAddEmpModal && (
        <div className="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 z-50">
          <div className="bg-[#0d1424] border border-slate-800 rounded-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-2xl">
            <div className="p-6 border-b border-slate-800 flex justify-between items-center bg-[#0a0f1d]">
              <h3 className="font-bold text-slate-100 text-base font-sans">Establish New Employee Profile</h3>
              <button onClick={() => setShowAddEmpModal(false)} className="text-slate-400 hover:text-white cursor-pointer">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleCreateEmployeeSubmit} className="p-6 space-y-4 text-xs text-slate-300">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block mb-1 text-slate-400">First Name *</label>
                  <input
                    type="text"
                    required
                    value={empForm.first_name}
                    onChange={(e) => setEmpForm({ ...empForm, first_name: e.target.value })}
                    className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                  />
                </div>
                <div>
                  <label className="block mb-1 text-slate-400">Last Name *</label>
                  <input
                    type="text"
                    required
                    value={empForm.last_name}
                    onChange={(e) => setEmpForm({ ...empForm, last_name: e.target.value })}
                    className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block mb-1 text-slate-400">Name in Arabic</label>
                  <input
                    type="text"
                    value={empForm.name_arabic}
                    onChange={(e) => setEmpForm({ ...empForm, name_arabic: e.target.value })}
                    className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white text-right"
                    dir="rtl"
                  />
                </div>
                <div className="grid grid-cols-3 gap-2">
                  <div className="col-span-2">
                    <label className="block mb-1 text-slate-400">Daily Salary Rate *</label>
                    <input
                      type="number"
                      required
                      value={empForm.daily_rate}
                      onChange={(e) => setEmpForm({ ...empForm, daily_rate: Number(e.target.value) })}
                      className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white font-mono"
                    />
                  </div>
                  <div>
                    <label className="block mb-1 text-slate-400">Currency *</label>
                    <select
                      value={empForm.currency || 'USD'}
                      onChange={(e) => setEmpForm({ ...empForm, currency: e.target.value as any })}
                      className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white font-semibold"
                    >
                      <option value="USD">USD ($)</option>
                      <option value="LYD">LYD (د.ل)</option>
                    </select>
                  </div>
                </div>
              </div>

              <div className="grid grid-cols-3 gap-4">
                <div>
                  <label className="block mb-1 text-slate-400">Contract Type *</label>
                  <select
                    value={empForm.contract_type}
                    onChange={(e) => setEmpForm({ ...empForm, contract_type: e.target.value as any })}
                    className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white font-semibold"
                  >
                    <option value="TAQA">TAQA Employee</option>
                    <option value="Consultant">External Consultant</option>
                  </select>
                </div>

                <div>
                  <label className="block mb-1 text-slate-400">Department *</label>
                  <select
                    value={empForm.department_id}
                    onChange={(e) => setEmpForm({ ...empForm, department_id: e.target.value })}
                    className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                  >
                    {departments.map(d => (
                      <option key={d.id} value={d.id}>{d.name}</option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block mb-1 text-slate-400">Position Role *</label>
                  <select
                    value={empForm.position_id}
                    onChange={(e) => setEmpForm({ ...empForm, position_id: e.target.value })}
                    className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                  >
                    {positions.map(p => (
                      <option key={p.id} value={p.id}>{p.name}</option>
                    ))}
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-3 gap-4">
                <div>
                  <label className="block mb-1 text-slate-400">Email Address *</label>
                  <input
                    type="email"
                    required
                    value={empForm.email}
                    onChange={(e) => setEmpForm({ ...empForm, email: e.target.value })}
                    className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                  />
                </div>
                <div>
                  <label className="block mb-1 text-slate-400">Primary Phone *</label>
                  <input
                    type="text"
                    required
                    value={empForm.phone_primary}
                    onChange={(e) => setEmpForm({ ...empForm, phone_primary: e.target.value })}
                    className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                  />
                </div>
                <div>
                  <label className="block mb-1 text-slate-400">Secondary Phone</label>
                  <input
                    type="text"
                    value={empForm.phone_secondary}
                    onChange={(e) => setEmpForm({ ...empForm, phone_secondary: e.target.value })}
                    className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block mb-1 text-slate-400">Passport Number</label>
                  <input
                    type="text"
                    value={empForm.passport_number}
                    onChange={(e) => setEmpForm({ ...empForm, passport_number: e.target.value })}
                    className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white font-mono"
                  />
                </div>
                <div>
                  <label className="block mb-1 text-slate-400">Passport Expiry Date</label>
                  <input
                    type="date"
                    value={empForm.passport_expiry_date}
                    onChange={(e) => setEmpForm({ ...empForm, passport_expiry_date: e.target.value })}
                    className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                  />
                </div>
              </div>

              <div>
                <label className="block mb-1 text-slate-400">Home Residential Address</label>
                <input
                  type="text"
                  value={empForm.home_address}
                  onChange={(e) => setEmpForm({ ...empForm, home_address: e.target.value })}
                  className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                />
              </div>

              <div className="pt-4 flex justify-end gap-2 border-t border-slate-800">
                <button
                  type="button"
                  onClick={() => setShowAddEmpModal(false)}
                  className="bg-slate-800 hover:bg-slate-700 text-slate-200 px-4 py-2 rounded-lg cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold px-6 py-2 rounded-lg cursor-pointer"
                >
                  Confirm Profile
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* 2. EDIT PROFILE MODAL */}
      {showEditEmpModal && (
        <div className="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 z-50">
          <div className="bg-[#0d1424] border border-slate-800 rounded-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-2xl">
            <div className="p-6 border-b border-slate-800 flex justify-between items-center bg-[#0a0f1d]">
              <h3 className="font-bold text-slate-100 text-base font-sans">Modify Employee Details</h3>
              <button onClick={() => setShowEditEmpModal(false)} className="text-slate-400 hover:text-white cursor-pointer">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleEditEmployeeSubmit} className="p-6 space-y-4 text-xs text-slate-300">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block mb-1 text-slate-400">First Name</label>
                  <input
                    type="text"
                    required
                    value={empForm.first_name}
                    onChange={(e) => setEmpForm({ ...empForm, first_name: e.target.value })}
                    className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                  />
                </div>
                <div>
                  <label className="block mb-1 text-slate-400">Last Name</label>
                  <input
                    type="text"
                    required
                    value={empForm.last_name}
                    onChange={(e) => setEmpForm({ ...empForm, last_name: e.target.value })}
                    className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div className="grid grid-cols-3 gap-2">
                  <div className="col-span-2">
                    <label className="block mb-1 text-slate-400">Daily Salary Rate</label>
                    <input
                      type="number"
                      required
                      value={empForm.daily_rate}
                      onChange={(e) => setEmpForm({ ...empForm, daily_rate: Number(e.target.value) })}
                      className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white font-mono"
                    />
                  </div>
                  <div>
                    <label className="block mb-1 text-slate-400">Currency</label>
                    <select
                      value={empForm.currency || 'USD'}
                      onChange={(e) => setEmpForm({ ...empForm, currency: e.target.value as any })}
                      className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white font-semibold"
                    >
                      <option value="USD">USD ($)</option>
                      <option value="LYD">LYD (د.ل)</option>
                    </select>
                  </div>
                </div>
                <div>
                  <label className="block mb-1 text-slate-400">Arabic Name</label>
                  <input
                    type="text"
                    value={empForm.name_arabic}
                    onChange={(e) => setEmpForm({ ...empForm, name_arabic: e.target.value })}
                    className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white text-right"
                    dir="rtl"
                  />
                </div>
              </div>

              <div className="pt-4 flex justify-end gap-2 border-t border-slate-800">
                <button
                  type="button"
                  onClick={() => setShowEditEmpModal(false)}
                  className="bg-slate-800 hover:bg-slate-700 text-slate-200 px-4 py-2 rounded-lg cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold px-6 py-2 rounded-lg cursor-pointer"
                >
                  Apply Modifications
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* 3. UPLOAD DOCUMENT MODAL */}
      {showAddDocModal && (
        <div className="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 z-50">
          <div className="bg-[#0d1424] border border-slate-800 rounded-xl w-full max-w-md shadow-2xl">
            <div className="p-5 border-b border-slate-800 flex justify-between items-center bg-[#0a0f1d]">
              <h3 className="font-bold text-slate-100 text-sm">Upload Document Record</h3>
              <button onClick={() => setShowAddDocModal(false)} className="text-slate-400 hover:text-white cursor-pointer">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleAddDocSubmit} className="p-5 space-y-4 text-xs text-slate-300">
              <div>
                <label className="block mb-1 text-slate-400">Document Classification *</label>
                <select
                  value={docForm.document_type_id}
                  onChange={(e) => setDocForm({ ...docForm, document_type_id: e.target.value })}
                  className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                >
                  {docTypes.map(d => (
                    <option key={d.id} value={d.id}>{d.name}</option>
                  ))}
                </select>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block mb-1 text-slate-400">Issue Date</label>
                  <input
                    type="date"
                    value={docForm.issue_date}
                    onChange={(e) => setDocForm({ ...docForm, issue_date: e.target.value })}
                    className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                  />
                </div>
                <div>
                  <label className="block mb-1 text-slate-400">Expiry Date</label>
                  <input
                    type="date"
                    value={docForm.expiry_date}
                    onChange={(e) => setDocForm({ ...docForm, expiry_date: e.target.value })}
                    className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                  />
                </div>
              </div>

              <div>
                <label className="block mb-1 text-slate-400">Upload Scan Copy (PDF or Image) *</label>
                <input
                  type="file"
                  required
                  onChange={(e) => handleFileChange(e, setDocForm)}
                  className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white text-xs file:mr-4 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-bold file:bg-slate-850 file:text-slate-300 file:hover:bg-slate-800"
                />
              </div>

              <div>
                <label className="block mb-1 text-slate-400">Brief Description</label>
                <input
                  type="text"
                  value={docForm.description}
                  onChange={(e) => setDocForm({ ...docForm, description: e.target.value })}
                  className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                />
              </div>

              <div className="pt-4 flex justify-end gap-2 border-t border-slate-800">
                <button
                  type="button"
                  onClick={() => setShowAddDocModal(false)}
                  className="bg-slate-800 hover:bg-slate-700 text-slate-250 px-4 py-2 rounded-lg cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold px-6 py-2 rounded-lg cursor-pointer"
                >
                  Confirm Upload
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* 4. REGISTER CERTIFICATE MODAL */}
      {showAddCertModal && (
        <div className="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 z-50">
          <div className="bg-[#0d1424] border border-slate-800 rounded-xl w-full max-w-md shadow-2xl">
            <div className="p-5 border-b border-slate-800 flex justify-between items-center bg-[#0a0f1d]">
              <h3 className="font-bold text-slate-100 text-sm">Register Competency Credential</h3>
              <button onClick={() => setShowAddCertModal(false)} className="text-slate-400 hover:text-white cursor-pointer">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleAddCertSubmit} className="p-5 space-y-4 text-xs text-slate-300">
              <div>
                <label className="block mb-1 text-slate-400">Certificate Type *</label>
                <select
                  value={certForm.certificate_type_id}
                  onChange={(e) => setCertForm({ ...certForm, certificate_type_id: e.target.value })}
                  className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                >
                  {certTypes.map(c => (
                    <option key={c.id} value={c.id}>{c.name}</option>
                  ))}
                </select>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block mb-1 text-slate-400">Certificate Number</label>
                  <input
                    type="text"
                    value={certForm.certificate_number}
                    onChange={(e) => setCertForm({ ...certForm, certificate_number: e.target.value })}
                    className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white font-mono"
                  />
                </div>
                <div>
                  <label className="block mb-1 text-slate-400">Training Provider</label>
                  <input
                    type="text"
                    value={certForm.training_provider}
                    onChange={(e) => setCertForm({ ...certForm, training_provider: e.target.value })}
                    className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block mb-1 text-slate-400">Issue Date</label>
                  <input
                    type="date"
                    value={certForm.issue_date}
                    onChange={(e) => setCertForm({ ...certForm, issue_date: e.target.value })}
                    className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                  />
                </div>
                <div>
                  <label className="block mb-1 text-slate-400">Expiry Date</label>
                  <input
                    type="date"
                    value={certForm.expiry_date}
                    onChange={(e) => setCertForm({ ...certForm, expiry_date: e.target.value })}
                    className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                  />
                </div>
              </div>

              <div>
                <label className="block mb-1 text-slate-400">Certificate File *</label>
                <input
                  type="file"
                  required
                  onChange={(e) => handleFileChange(e, setCertForm)}
                  className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white text-xs file:mr-4 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-bold file:bg-slate-850 file:text-slate-300 file:hover:bg-slate-800"
                />
              </div>

              <div className="pt-4 flex justify-end gap-2 border-t border-slate-800">
                <button
                  type="button"
                  onClick={() => setShowAddCertModal(false)}
                  className="bg-slate-800 hover:bg-slate-700 text-slate-250 px-4 py-2 rounded-lg cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold px-6 py-2 rounded-lg cursor-pointer"
                >
                  Register Credential
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* 5. ISSUE EQUIPMENT MODAL */}
      {showAddEqModal && (
        <div className="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 z-50">
          <div className="bg-[#0d1424] border border-slate-800 rounded-xl w-full max-w-sm shadow-2xl">
            <div className="p-5 border-b border-slate-800 flex justify-between items-center bg-[#0a0f1d]">
              <h3 className="font-bold text-slate-100 text-sm">Issue Gear Assignment</h3>
              <button onClick={() => setShowAddEqModal(false)} className="text-slate-400 hover:text-white cursor-pointer">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleAddEqSubmit} className="p-5 space-y-4 text-xs text-slate-300">
              <div>
                <label className="block mb-1 text-slate-400">Select Equipment Type *</label>
                <select
                  value={eqForm.equipment_type_id}
                  onChange={(e) => setEqForm({ ...eqForm, equipment_type_id: e.target.value })}
                  className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                >
                  {equipmentTypes.map(eq => (
                    <option key={eq.id} value={eq.id}>{eq.name}</option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block mb-1 text-slate-400">Issue Date *</label>
                <input
                  type="date"
                  required
                  value={eqForm.issued_date}
                  onChange={(e) => setEqForm({ ...eqForm, issued_date: e.target.value })}
                  className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                />
              </div>

              <div>
                <label className="block mb-1 text-slate-400">Calibration Notes / Reference</label>
                <input
                  type="text"
                  value={eqForm.notes}
                  onChange={(e) => setEqForm({ ...eqForm, notes: e.target.value })}
                  className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                />
              </div>

              <div className="pt-4 flex justify-end gap-2 border-t border-slate-800">
                <button
                  type="button"
                  onClick={() => setShowAddEqModal(false)}
                  className="bg-slate-800 hover:bg-slate-700 text-slate-250 px-4 py-2 rounded-lg cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold px-6 py-2 rounded-lg cursor-pointer"
                >
                  Issue Equipment
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

    </div>
  );
}
