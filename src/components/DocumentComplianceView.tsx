import React, { useState, useEffect } from 'react';
import {
  FileText,
  AlertTriangle,
  Search,
  CheckCircle,
  Clock,
  Filter,
  FileUp,
  X,
  Plus
} from 'lucide-react';
import { Employee, Document, DocumentType } from '../types';
import { usePagination } from '../hooks/usePagination';
import Pagination from './Pagination';

interface DocumentComplianceViewProps {
  employees: Employee[];
  documents: Document[];
  docTypes: DocumentType[];
  onAddDocument: (docData: any) => Promise<any>;
  onDeleteDocument: (id: string) => Promise<any>;
  openUploadDocOnInit?: boolean;
}

export default function DocumentComplianceView({
  employees,
  documents,
  docTypes,
  onAddDocument,
  onDeleteDocument,
  openUploadDocOnInit = false
}: DocumentComplianceViewProps) {
  const [searchQuery, setSearchQuery] = useState('');
  const [docFilter, setDocFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [showUploadModal, setShowUploadModal] = useState(openUploadDocOnInit);

  const [docForm, setDocForm] = useState({
    employee_id: employees[0]?.id || '',
    document_type_id: docTypes[0]?.id || '',
    issue_date: '',
    expiry_date: '',
    reminder_days: 90,
    description: '',
    notes: '',
    file_base64: '',
    file_name: ''
  });

  // Calculate high-level compliance metrics
  const totalAudited = documents.length;
  const expiredCount = documents.filter(d => d.status === 'expired').length;
  const expiringSoonCount = documents.filter(d => d.status === 'expiring_soon').length;
  const validCount = documents.filter(d => d.status === 'valid').length;

  // Find missing documents (e.g. employees who do not have a Passport registered)
  const passportTypeId = docTypes.find(t => t.name.toLowerCase().includes('passport'))?.id;
  const missingPassports = employees.filter(emp => {
    if (!passportTypeId) return false;
    return !documents.some(d => d.employee_id === emp.id && d.document_type_id === passportTypeId);
  });

  const filteredDocuments = documents.filter(doc => {
    const emp = employees.find(e => e.id === doc.employee_id);
    const fullName = emp ? `${emp.first_name} ${emp.last_name}`.toLowerCase() : '';
    const matchesSearch = fullName.includes(searchQuery.toLowerCase());
    const matchesStatus = statusFilter ? doc.status === statusFilter : true;
    return matchesSearch && matchesStatus;
  });

  const {
    page: docPage,
    setPage: setDocPage,
    paginatedItems: paginatedDocs,
    totalPages: docTotalPages,
    pageSize: docPageSize,
    totalItems: docTotalItems
  } = usePagination(filteredDocuments, 10);

  const {
    page: missingPage,
    setPage: setMissingPage,
    paginatedItems: paginatedMissing,
    totalPages: missingTotalPages,
    pageSize: missingPageSize,
    totalItems: missingTotalItems
  } = usePagination(missingPassports, 10);

  useEffect(() => {
    setDocPage(1);
    setMissingPage(1);
  }, [searchQuery, docFilter, statusFilter]);

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = () => {
        setDocForm(prev => ({
          ...prev,
          file_base64: reader.result as string,
          file_name: file.name
        }));
      };
      reader.readAsDataURL(file);
    }
  };

  const handleUploadSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    await onAddDocument(docForm);
    setShowUploadModal(false);
    setDocForm({
      employee_id: employees[0]?.id || '',
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

  return (
    <div className="p-8 space-y-8 max-w-7xl mx-auto" id="document-compliance-wrapper">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 className="text-xl font-bold text-white tracking-tight uppercase">Document Compliance Desk</h2>
          <p className="text-sm text-slate-400 mt-1">Audit status of primary passports, visas, and identification records</p>
        </div>

        <button
          onClick={() => setShowUploadModal(true)}
          className="bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold px-4 py-2.5 rounded-lg text-sm flex items-center gap-2 cursor-pointer self-start md:self-auto"
        >
          <FileUp className="w-4 h-4" /> Upload Document Scan
        </button>
      </div>

      {/* Compliance Metric widgets */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div className="bg-[#0e1626] border border-slate-800 p-5 rounded-xl flex items-center gap-4">
          <div className="p-3 rounded-lg bg-emerald-500/10">
            <CheckCircle className="w-6 h-6 text-emerald-400" />
          </div>
          <div>
            <span className="block text-xs font-semibold text-slate-400 uppercase tracking-wider">Valid Documents</span>
            <span className="text-2xl font-bold text-white font-mono">{validCount}</span>
          </div>
        </div>

        <div className="bg-[#0e1626] border border-slate-800 p-5 rounded-xl flex items-center gap-4">
          <div className="p-3 rounded-lg bg-amber-500/10">
            <Clock className="w-6 h-6 text-amber-400" />
          </div>
          <div>
            <span className="block text-xs font-semibold text-slate-400 uppercase tracking-wider">Expiring Soon</span>
            <span className="text-2xl font-bold text-amber-400 font-mono">{expiringSoonCount}</span>
          </div>
        </div>

        <div className="bg-[#0e1626] border border-slate-800 p-5 rounded-xl flex items-center gap-4">
          <div className="p-3 rounded-lg bg-rose-500/10">
            <AlertTriangle className="w-6 h-6 text-rose-400" />
          </div>
          <div>
            <span className="block text-xs font-semibold text-slate-400 uppercase tracking-wider">Expired Records</span>
            <span className="text-2xl font-bold text-rose-400 font-mono">{expiredCount}</span>
          </div>
        </div>

        <div className="bg-[#0e1626] border border-slate-800 p-5 rounded-xl flex items-center gap-4">
          <div className="p-3 rounded-lg bg-blue-500/10">
            <AlertTriangle className="w-6 h-6 text-blue-400" />
          </div>
          <div>
            <span className="block text-xs font-semibold text-slate-400 uppercase tracking-wider">Missing Passports</span>
            <span className="text-2xl font-bold text-blue-400 font-mono">{missingPassports.length}</span>
          </div>
        </div>
      </div>

      {/* Main compliance audit grids */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        {/* Document Master List */}
        <div className="lg:col-span-2 bg-[#0e1626] border border-slate-800 p-6 rounded-xl shadow-lg space-y-6">
          <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-800 pb-4">
            <h3 className="font-bold text-slate-200 text-sm uppercase tracking-wider">Audit Log</h3>
            
            {/* Quick search filters */}
            <div className="flex flex-wrap items-center gap-2">
              <div className="relative">
                <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-500" />
                <input
                  type="text"
                  placeholder="Search employee..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="bg-[#111827] border border-slate-850 rounded-lg pl-8 pr-3 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500"
                />
              </div>

              <select
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value)}
                className="bg-[#111827] border border-slate-850 rounded-lg py-1.5 px-2 text-xs text-slate-300"
              >
                <option value="">All Statuses</option>
                <option value="valid">Valid</option>
                <option value="expiring_soon">Expiring Soon</option>
                <option value="expired">Expired</option>
              </select>
            </div>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs border-collapse">
              <thead>
                <tr className="border-b border-slate-800 text-slate-400 font-bold uppercase tracking-wider">
                  <th className="py-3 px-2">Employee Master</th>
                  <th className="py-3 px-2">Document Category</th>
                  <th className="py-3 px-2">Issue / Expiry Date</th>
                  <th className="py-3 px-2">Status</th>
                  <th className="py-3 px-2 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/40">
                {paginatedDocs.map((doc) => {
                  const emp = employees.find(e => e.id === doc.employee_id);
                  const type = docTypes.find(t => t.id === doc.document_type_id)?.name || 'Document';
                  return (
                    <tr key={doc.id} className="hover:bg-slate-800/10 transition-colors">
                      <td className="py-3.5 px-2">
                        <div className="font-bold text-slate-200">
                          {emp ? `${emp.first_name} ${emp.last_name}` : 'Archive Record'}
                        </div>
                        <span className="text-[10px] text-slate-500 font-mono mt-0.5 block">{emp?.employee_code}</span>
                      </td>
                      <td className="py-3.5 px-2">
                        <span className="font-semibold text-slate-300">{type}</span>
                        <span className="block text-[10px] text-slate-500 mt-0.5">{doc.file_name}</span>
                      </td>
                      <td className="py-3.5 px-2">
                        <div className="font-mono text-slate-300">Exp: {doc.expiry_date || 'Permanent'}</div>
                        <span className="text-[9px] text-slate-500 mt-0.5 block">Issued: {doc.issue_date || 'N/A'}</span>
                      </td>
                      <td className="py-3.5 px-2">
                        <span className={`text-[9px] font-bold font-mono px-2 py-0.5 rounded ${
                          doc.status === 'valid' ? 'bg-emerald-500/10 text-emerald-400' :
                          doc.status === 'expiring_soon' ? 'bg-amber-500/10 text-amber-400' :
                          'bg-rose-500/10 text-rose-400'
                        }`}>
                          {doc.status.replace('_', ' ')}
                        </span>
                      </td>
                      <td className="py-3.5 px-2 text-right">
                        <button
                          onClick={() => onDeleteDocument(doc.id)}
                          className="text-slate-500 hover:text-rose-400 p-1.5 rounded cursor-pointer transition-colors"
                          title="Delete scan"
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          <Pagination
            page={docPage}
            totalPages={docTotalPages}
            totalItems={docTotalItems}
            pageSize={docPageSize}
            onPageChange={setDocPage}
          />
        </div>

        {/* Missing Required Documents Sidebar */}
        <div className="bg-[#0e1626] border border-slate-800 p-6 rounded-xl shadow-lg space-y-4">
          <h3 className="font-bold text-slate-200 text-sm uppercase tracking-wider border-b border-slate-800 pb-2">Missing Passports Audit</h3>
          <p className="text-xs text-slate-400 leading-relaxed">
            The following employees do not have a valid, registered passport file in the system. Ensure compliance before planning cross-border rig mobilizations.
          </p>

          <div className="space-y-3">
            {paginatedMissing.map((emp) => (
              <div key={emp.id} className="p-3 bg-[#111a2e] border border-slate-800 rounded-lg flex items-center justify-between">
                <div>
                  <h4 className="font-bold text-xs text-slate-200">{emp.first_name} {emp.last_name}</h4>
                  <span className="text-[9px] text-slate-500 font-mono">{emp.employee_code}</span>
                </div>
                <button
                  onClick={() => {
                    setDocForm(prev => ({
                      ...prev,
                      employee_id: emp.id,
                      document_type_id: passportTypeId || ''
                    }));
                    setShowUploadModal(true);
                  }}
                  className="bg-amber-500/10 hover:bg-amber-500 hover:text-slate-950 text-amber-400 text-[10px] font-bold px-2 py-1 rounded border border-amber-500/20 transition-all cursor-pointer"
                >
                  Resolve Gap
                </button>
              </div>
            ))}

            {missingPassports.length === 0 && (
              <div className="text-center py-6 text-slate-500 text-xs">
                All workforce members have registered passports!
              </div>
            )}
          </div>

          <Pagination
            page={missingPage}
            totalPages={missingTotalPages}
            totalItems={missingTotalItems}
            pageSize={missingPageSize}
            onPageChange={setMissingPage}
          />
        </div>

      </div>

      {/* Upload Document Modal Pop */}
      {showUploadModal && (
        <div className="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 z-50">
          <div className="bg-[#0d1424] border border-slate-800 rounded-xl w-full max-w-md shadow-2xl">
            <div className="p-5 border-b border-slate-800 flex justify-between items-center bg-[#0a0f1d]">
              <h3 className="font-bold text-slate-100 text-sm">Upload Compliance Document Record</h3>
              <button onClick={() => setShowUploadModal(false)} className="text-slate-400 hover:text-white cursor-pointer">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleUploadSubmit} className="p-5 space-y-4 text-xs text-slate-300">
              <div>
                <label className="block mb-1 text-slate-400">Employee Profile *</label>
                <select
                  value={docForm.employee_id}
                  onChange={(e) => setDocForm({ ...docForm, employee_id: e.target.value })}
                  className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                >
                  {employees.map(emp => (
                    <option key={emp.id} value={emp.id}>{emp.first_name} {emp.last_name} ({emp.employee_code})</option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block mb-1 text-slate-400">Document Type *</label>
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
                <label className="block mb-1 text-slate-400">Upload scan file (PDF/IMG) *</label>
                <input
                  type="file"
                  required
                  onChange={handleFileChange}
                  className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white text-xs file:mr-4 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-bold file:bg-slate-850 file:text-slate-300 file:hover:bg-slate-800"
                />
              </div>

              <div>
                <label className="block mb-1 text-slate-400">Audit Notes / Reference</label>
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
                  onClick={() => setShowUploadModal(false)}
                  className="bg-slate-800 hover:bg-slate-700 text-slate-200 px-4 py-2 rounded-lg cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold px-6 py-2 rounded-lg cursor-pointer"
                >
                  Register Scan Record
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

    </div>
  );
}

// Sub-component wrapper for Trash button matching import context
import { Trash } from 'lucide-react';
const Trash2 = Trash;
