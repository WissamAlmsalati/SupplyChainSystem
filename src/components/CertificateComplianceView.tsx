import React, { useState, useEffect } from 'react';
import {
  Award,
  Search,
  Filter,
  CheckCircle,
  AlertTriangle,
  Clock,
  X,
  PlusCircle,
  ShieldAlert,
  Calendar
} from 'lucide-react';
import { Employee, Certificate, CertificateType } from '../types';
import { usePagination } from '../hooks/usePagination';
import Pagination from './Pagination';

interface CertificateComplianceViewProps {
  employees: Employee[];
  certificates: Certificate[];
  certTypes: CertificateType[];
  onAddCertificate: (certData: any) => Promise<any>;
  onDeleteCertificate: (id: string) => Promise<any>;
}

export default function CertificateComplianceView({
  employees,
  certificates,
  certTypes,
  onAddCertificate,
  onDeleteCertificate
}: CertificateComplianceViewProps) {
  const [searchQuery, setSearchQuery] = useState('');
  const [positionFilter, setPositionFilter] = useState('');
  const [showAddModal, setShowAddModal] = useState(false);
  const [selectedCellInfo, setSelectedCellInfo] = useState<{
    employeeName: string;
    certName: string;
    cert?: Certificate;
  } | null>(null);

  const [certForm, setCertForm] = useState({
    employee_id: employees[0]?.id || '',
    certificate_type_id: certTypes[0]?.id || '',
    training_provider: '',
    certificate_number: '',
    issue_date: '',
    expiry_date: '',
    notes: '',
    file_base64: '',
    file_name: ''
  });

  // Unique list of positions currently held by workforce
  const positionsList = Array.from(new Set(employees.map(e => e.status))).filter(Boolean);

  // Filtered employees for matrix rows
  const filteredEmployees = employees.filter((emp) => {
    const fullName = `${emp.first_name} ${emp.last_name}`.toLowerCase();
    const code = emp.employee_code.toLowerCase();
    const matchesSearch = fullName.includes(searchQuery.toLowerCase()) || code.includes(searchQuery.toLowerCase());
    return matchesSearch;
  });

  const { page, setPage, paginatedItems, totalPages, pageSize, totalItems } = usePagination(filteredEmployees, 10);

  useEffect(() => {
    setPage(1);
  }, [searchQuery, positionFilter]);

  // Calculate certificate status helper
  const getEmployeeCertStatus = (employeeId: string, certTypeId: string) => {
    const match = certificates.find(c => c.employee_id === employeeId && c.certificate_type_id === certTypeId);
    if (!match) return { label: 'missing', color: 'bg-slate-900 border border-slate-800 text-slate-500', icon: '—' };
    if (match.status === 'valid') return { label: 'valid', color: 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20', icon: '✓', data: match };
    if (match.status === 'expiring_soon') return { label: 'expiring', color: 'bg-amber-500/10 text-amber-400 border border-amber-500/20', icon: '⚠', data: match };
    return { label: 'expired', color: 'bg-rose-500/10 text-rose-400 border border-rose-500/20', icon: '✗', data: match };
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = () => {
        setCertForm(prev => ({
          ...prev,
          file_base64: reader.result as string,
          file_name: file.name
        }));
      };
      reader.readAsDataURL(file);
    }
  };

  const handleRegisterSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    await onAddCertificate(certForm);
    setShowAddModal(false);
    setCertForm({
      employee_id: employees[0]?.id || '',
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

  // Compute stats
  const totalCerts = certificates.length;
  const expiredCount = certificates.filter(c => c.status === 'expired').length;
  const expiringCount = certificates.filter(c => c.status === 'expiring_soon').length;

  return (
    <div className="p-8 space-y-8 max-w-7xl mx-auto" id="credential-matrix-wrapper">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 className="text-xl font-bold text-white tracking-tight uppercase">Credential Compliance Matrix</h2>
          <p className="text-sm text-slate-400 mt-1">Cross-reference safety training, BOSIET, and Well Control statuses</p>
        </div>

        <button
          onClick={() => setShowAddModal(true)}
          className="bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold px-4 py-2.5 rounded-lg text-sm flex items-center gap-2 cursor-pointer self-start md:self-auto"
        >
          <PlusCircle className="w-4 h-4" /> Register New Certificate
        </button>
      </div>

      {/* Analytics Row */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div className="bg-[#0e1626] border border-slate-800 p-5 rounded-xl flex items-center gap-4">
          <div className="p-3 bg-emerald-500/10 rounded-lg">
            <CheckCircle className="w-5 h-5 text-emerald-400" />
          </div>
          <div>
            <span className="block text-xs font-semibold text-slate-400 uppercase tracking-wider">Registered Certificates</span>
            <span className="text-2xl font-bold font-mono text-white">{totalCerts}</span>
          </div>
        </div>

        <div className="bg-[#0e1626] border border-slate-800 p-5 rounded-xl flex items-center gap-4">
          <div className="p-3 bg-amber-500/10 rounded-lg">
            <Clock className="w-5 h-5 text-amber-400" />
          </div>
          <div>
            <span className="block text-xs font-semibold text-slate-400 uppercase tracking-wider">Expiring ≤ 90 Days</span>
            <span className="text-2xl font-bold font-mono text-amber-400">{expiringCount}</span>
          </div>
        </div>

        <div className="bg-[#0e1626] border border-slate-800 p-5 rounded-xl flex items-center gap-4">
          <div className="p-3 bg-rose-500/10 rounded-lg">
            <ShieldAlert className="w-5 h-5 text-rose-400" />
          </div>
          <div>
            <span className="block text-xs font-semibold text-slate-400 uppercase tracking-wider">Expired / Deficient</span>
            <span className="text-2xl font-bold font-mono text-rose-400">{expiredCount}</span>
          </div>
        </div>
      </div>

      {/* Interactive Matrix Board */}
      <div className="bg-[#0e1626] border border-slate-800 rounded-xl p-6 shadow-xl space-y-6">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-800 pb-4">
          <h3 className="font-bold text-slate-200 text-sm uppercase tracking-wider">Matrix Overview</h3>
          
          {/* Quick Filters */}
          <div className="relative w-full md:w-72">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" />
            <input
              type="text"
              placeholder="Search crew member by name..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-full bg-[#111827] border border-slate-850 rounded-lg pl-9 pr-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500"
            />
          </div>
        </div>

        {/* Outer scrolling matrix container */}
        <div className="overflow-x-auto border border-slate-800 rounded-xl bg-slate-900/10">
          <table className="w-full border-collapse text-left text-xs min-w-[800px]">
            <thead>
              <tr className="bg-[#0b101c] border-b border-slate-800 text-slate-400 font-bold uppercase tracking-wider">
                <th className="py-4 px-4 sticky left-0 bg-[#0b101c] z-10 w-64 border-r border-slate-800/80">
                  Crew Member Master
                </th>
                {certTypes.map((type) => (
                  <th key={type.id} className="py-4 px-4 text-center text-[10px] tracking-wide border-r border-slate-800/30">
                    {type.name}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800/50">
              {paginatedItems.map((emp) => (
                <tr key={emp.id} className="hover:bg-slate-800/10 transition-colors">
                  {/* Fixed Left Employee Column */}
                  <td className="py-3.5 px-4 font-semibold text-slate-200 sticky left-0 bg-[#0e1626] z-10 border-r border-slate-800/80 flex flex-col">
                    <span className="font-bold text-slate-100">{emp.first_name} {emp.last_name}</span>
                    <span className="text-[10px] text-slate-500 font-mono mt-0.5">{emp.employee_code}</span>
                  </td>

                  {/* Cert types status cells */}
                  {certTypes.map((type) => {
                    const status = getEmployeeCertStatus(emp.id, type.id);
                    return (
                      <td key={type.id} className="py-3.5 px-2 text-center border-r border-slate-800/30">
                        <button
                          onClick={() => {
                            setSelectedCellInfo({
                              employeeName: `${emp.first_name} ${emp.last_name}`,
                              certName: type.name,
                              cert: status.data
                            });
                          }}
                          className={`w-10 h-8 rounded-lg mx-auto flex items-center justify-center font-bold text-xs transition-all hover:scale-105 cursor-pointer ${status.color}`}
                        >
                          {status.icon}
                        </button>
                      </td>
                    );
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <Pagination
          page={page}
          totalPages={totalPages}
          totalItems={totalItems}
          pageSize={pageSize}
          onPageChange={setPage}
        />
      </div>

      {/* MATRIX CELL DETAIL POPOVER CARD */}
      {selectedCellInfo && (
        <div className="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 z-50">
          <div className="bg-[#0e1626] border border-slate-800 p-6 rounded-xl w-full max-w-sm shadow-2xl relative">
            <button
              onClick={() => setSelectedCellInfo(null)}
              className="absolute top-4 right-4 text-slate-400 hover:text-white cursor-pointer"
            >
              <X className="w-5 h-5" />
            </button>

            <div className="space-y-4">
              <div className="flex items-center gap-2">
                <Award className="w-5 h-5 text-emerald-400 animate-pulse" />
                <h4 className="font-bold text-sm text-slate-200 uppercase tracking-wider">Credential Inspection</h4>
              </div>

              <div className="space-y-3 text-xs border-t border-slate-800 pt-3">
                <div className="flex justify-between">
                  <span className="text-slate-500">Employee:</span>
                  <span className="font-bold text-slate-200">{selectedCellInfo.employeeName}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-slate-500">Certificate:</span>
                  <span className="font-semibold text-slate-300">{selectedCellInfo.certName}</span>
                </div>

                {selectedCellInfo.cert ? (
                  <div className="space-y-2 bg-slate-900/60 p-3.5 rounded-lg border border-slate-800 mt-2">
                    <div className="flex justify-between">
                      <span className="text-slate-500">Cert Number:</span>
                      <span className="font-mono text-slate-300">{selectedCellInfo.cert.certificate_number || 'N/A'}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-slate-500">Provider:</span>
                      <span className="text-slate-300">{selectedCellInfo.cert.training_provider || 'TAQA'}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-slate-500">Issued On:</span>
                      <span className="font-mono text-slate-300">{selectedCellInfo.cert.issue_date || 'N/A'}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-slate-500">Expires On:</span>
                      <span className="font-mono text-rose-400 font-bold">{selectedCellInfo.cert.expiry_date || 'N/A'}</span>
                    </div>
                    <div className="flex justify-between pt-1 border-t border-slate-850/50 mt-1">
                      <span className="text-slate-500">Audit Status:</span>
                      <span className={`font-mono font-bold uppercase text-[9px] px-2 py-0.5 rounded ${
                        selectedCellInfo.cert.status === 'valid' ? 'bg-emerald-500/10 text-emerald-400' :
                        selectedCellInfo.cert.status === 'expiring_soon' ? 'bg-amber-500/10 text-amber-400' :
                        'bg-rose-500/10 text-rose-400'
                      }`}>
                        {selectedCellInfo.cert.status.replace('_', ' ')}
                      </span>
                    </div>
                  </div>
                ) : (
                  <div className="p-4 bg-rose-500/5 border border-rose-500/25 rounded-xl text-center text-rose-400 font-semibold text-xs leading-relaxed mt-2">
                    Deficiency Alert: Employee is missing this mandatory credential! Please schedule training immediately.
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* REGISTER NEW CERTIFICATE MODAL POP */}
      {showAddModal && (
        <div className="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 z-50">
          <div className="bg-[#0d1424] border border-slate-800 rounded-xl w-full max-w-md shadow-2xl">
            <div className="p-5 border-b border-slate-800 flex justify-between items-center bg-[#0a0f1d]">
              <h3 className="font-bold text-slate-100 text-sm">Register Competency Credential</h3>
              <button onClick={() => setShowAddModal(false)} className="text-slate-400 hover:text-white cursor-pointer">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleRegisterSubmit} className="p-5 space-y-4 text-xs text-slate-300">
              <div>
                <label className="block mb-1 text-slate-400">Employee Profile *</label>
                <select
                  value={certForm.employee_id}
                  onChange={(e) => setCertForm({ ...certForm, employee_id: e.target.value })}
                  className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                >
                  {employees.map(emp => (
                    <option key={emp.id} value={emp.id}>{emp.first_name} {emp.last_name} ({emp.employee_code})</option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block mb-1 text-slate-400">Certificate Category *</label>
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
                <label className="block mb-1 text-slate-400">Certificate File Scan *</label>
                <input
                  type="file"
                  required
                  onChange={handleFileChange}
                  className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white text-xs file:mr-4 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-bold file:bg-slate-850 file:text-slate-300 file:hover:bg-slate-800"
                />
              </div>

              <div className="pt-4 flex justify-end gap-2 border-t border-slate-800">
                <button
                  type="button"
                  onClick={() => setShowAddModal(false)}
                  className="bg-slate-800 hover:bg-slate-700 text-slate-200 px-4 py-2 rounded-lg cursor-pointer"
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

    </div>
  );
}
