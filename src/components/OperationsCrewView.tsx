import React, { useState, useEffect } from 'react';
import {
  CalendarDays,
  Plus,
  Users,
  Search,
  Filter,
  CheckCircle,
  Clock,
  Briefcase,
  AlertTriangle,
  X
} from 'lucide-react';
import { Employee, Deployment, Client, Field, Project } from '../types';
import { usePagination } from '../hooks/usePagination';
import Pagination from './Pagination';

interface OperationsCrewViewProps {
  employees: Employee[];
  deployments: Deployment[];
  clients: Client[];
  fields: Field[];
  projects: Project[];
  onAddDeployment: (depData: any) => Promise<any>;
  onEndDeployment: (id: string, endDate: string) => Promise<any>;
  openNewDeploymentOnInit?: boolean;
}

export default function OperationsCrewView({
  employees,
  deployments,
  clients,
  fields,
  projects,
  onAddDeployment,
  onEndDeployment,
  openNewDeploymentOnInit = false
}: OperationsCrewViewProps) {
  const [searchQuery, setSearchQuery] = useState('');
  const [clientFilter, setClientFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('active'); // default to active deployments
  const [showAddModal, setShowAddModal] = useState(openNewDeploymentOnInit);

  const [depForm, setDepForm] = useState({
    employee_id: employees[0]?.id || '',
    client_id: clients[0]?.id || '',
    field_id: fields[0]?.id || '',
    project_id: projects[0]?.id || '',
    start_date: new Date().toISOString().split('T')[0],
    end_date: '',
    rotation_duration_days: 28,
    b2b_partner_employee_id: '',
    status: 'active',
    notes: ''
  });

  const filteredDeployments = deployments.filter(dep => {
    const emp = employees.find(e => e.id === dep.employee_id);
    const fullName = emp ? `${emp.first_name} ${emp.last_name}`.toLowerCase() : '';
    const matchesSearch = fullName.includes(searchQuery.toLowerCase());
    const matchesClient = clientFilter ? dep.client_id === clientFilter : true;
    const matchesStatus = dep.status === statusFilter;
    return matchesSearch && matchesClient && matchesStatus;
  });

  const { page, setPage, paginatedItems, totalPages, pageSize, totalItems } = usePagination(filteredDeployments, 10);

  useEffect(() => {
    setPage(1);
  }, [searchQuery, clientFilter, statusFilter]);

  const handleAddSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    await onAddDeployment(depForm);
    setShowAddModal(false);
    setDepForm({
      employee_id: employees[0]?.id || '',
      client_id: clients[0]?.id || '',
      field_id: fields[0]?.id || '',
      project_id: projects[0]?.id || '',
      start_date: new Date().toISOString().split('T')[0],
      end_date: '',
      rotation_duration_days: 28,
      b2b_partner_employee_id: '',
      status: 'active',
      notes: ''
    });
  };

  const handleEndClick = async (id: string) => {
    const todayStr = new Date().toISOString().split('T')[0];
    if (confirm(`Confirm immediate release of employee from this deployment rotation as of today (${todayStr})?`)) {
      await onEndDeployment(id, todayStr);
    }
  };

  // Helper calculation counts
  const activeCount = deployments.filter(d => d.status === 'active').length;
  const scheduledCount = deployments.filter(d => d.status === 'planned').length;

  return (
    <div className="p-8 space-y-8 max-w-7xl mx-auto" id="operations-crew-wrapper">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 className="text-xl font-bold text-white tracking-tight uppercase">Operations & Crewing Desk</h2>
          <p className="text-sm text-slate-400 mt-1">Deploy, track, and rotate workforce personnel across remote oilfields</p>
        </div>

        <button
          onClick={() => setShowAddModal(true)}
          className="bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold px-4 py-2.5 rounded-lg text-sm flex items-center gap-2 cursor-pointer self-start md:self-auto"
        >
          <Plus className="w-4 h-4" /> Initiate Deployment
        </button>
      </div>

      {/* Stats row */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div className="bg-[#0e1626] border border-slate-800 p-5 rounded-xl flex items-center gap-4">
          <div className="p-3 bg-emerald-500/10 rounded-lg">
            <CheckCircle className="w-5 h-5 text-emerald-400" />
          </div>
          <div>
            <span className="block text-xs font-semibold text-slate-400 uppercase tracking-wider">Active Deployments</span>
            <span className="text-2xl font-bold font-mono text-white">{activeCount}</span>
          </div>
        </div>

        <div className="bg-[#0e1626] border border-slate-800 p-5 rounded-xl flex items-center gap-4">
          <div className="p-3 bg-cyan-500/10 rounded-lg">
            <Clock className="w-5 h-5 text-cyan-400" />
          </div>
          <div>
            <span className="block text-xs font-semibold text-slate-400 uppercase tracking-wider">Scheduled Mobilizations</span>
            <span className="text-2xl font-bold font-mono text-cyan-400">{scheduledCount}</span>
          </div>
        </div>

        <div className="bg-[#0e1626] border border-slate-800 p-5 rounded-xl flex items-center gap-4">
          <div className="p-3 bg-blue-500/10 rounded-lg">
            <Users className="w-5 h-5 text-blue-400" />
          </div>
          <div>
            <span className="block text-xs font-semibold text-slate-400 uppercase tracking-wider">Standby Workforce</span>
            <span className="text-2xl font-bold font-mono text-blue-400">
              {employees.filter(e => e.status === 'active').length}
            </span>
          </div>
        </div>
      </div>

      {/* Main Grid: Deployment Logs Table */}
      <div className="bg-[#0e1626] border border-slate-800 rounded-xl p-6 shadow-xl space-y-6">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-800 pb-4">
          <h3 className="font-bold text-slate-200 text-sm uppercase tracking-wider">Crew Mobilization Log</h3>
          
          <div className="flex flex-wrap items-center gap-2">
            <div className="relative">
              <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-500" />
              <input
                type="text"
                placeholder="Search crew name..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="bg-[#111827] border border-slate-850 rounded-lg pl-8 pr-3 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500"
              />
            </div>

            <select
              value={clientFilter}
              onChange={(e) => setClientFilter(e.target.value)}
              className="bg-[#111827] border border-slate-850 rounded-lg py-1.5 px-2 text-xs text-slate-300"
            >
              <option value="">All Clients</option>
              {clients.map(c => (
                <option key={c.id} value={c.id}>{c.name}</option>
              ))}
            </select>

            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="bg-[#111827] border border-slate-850 rounded-lg py-1.5 px-2 text-xs text-slate-300"
            >
              <option value="active">Active Rotations</option>
              <option value="scheduled">Scheduled</option>
              <option value="completed">Completed</option>
            </select>
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs border-collapse">
            <thead>
              <tr className="border-b border-slate-800 text-slate-400 font-bold uppercase tracking-wider">
                <th className="py-3 px-2">Crew Member</th>
                <th className="py-3 px-2">Destination (Client / Field)</th>
                <th className="py-3 px-2">Shift Schedule</th>
                <th className="py-3 px-2">B2B Handover Partner</th>
                <th className="py-3 px-2">Operational Status</th>
                <th className="py-3 px-2 text-right">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800/40">
              {paginatedItems.map((dep) => {
                const emp = employees.find(e => e.id === dep.employee_id);
                const client = clients.find(c => c.id === dep.client_id)?.name || 'Client';
                const field = fields.find(f => f.id === dep.field_id)?.name || 'Field';
                const b2b = employees.find(e => e.id === dep.b2b_partner_employee_id);
                return (
                  <tr key={dep.id} className="hover:bg-slate-800/10 transition-colors">
                    <td className="py-3.5 px-2">
                      <span className="font-bold text-slate-200 block">{emp ? `${emp.first_name} ${emp.last_name}` : 'Unknown'}</span>
                      <span className="text-[10px] text-slate-500 font-mono mt-0.5">{emp?.employee_code}</span>
                    </td>
                    <td className="py-3.5 px-2">
                      <span className="font-semibold text-slate-300 block">{client}</span>
                      <span className="text-[10px] text-slate-500 font-mono block mt-0.5">{field}</span>
                    </td>
                    <td className="py-3.5 px-2">
                      <span className="text-slate-300 font-mono block">{dep.start_date} to {dep.end_date || 'Open-ended'}</span>
                      <span className="text-[10px] text-slate-500 mt-0.5 block">{dep.rotation_duration_days}-day rotation</span>
                    </td>
                    <td className="py-3.5 px-2">
                      {b2b ? (
                        <div>
                          <span className="text-slate-300 font-medium block">{b2b.first_name} {b2b.last_name}</span>
                          <span className="text-[9px] text-emerald-400 font-mono">B2B MATCHED</span>
                        </div>
                      ) : (
                        <span className="text-amber-400 font-bold text-[10px] bg-amber-500/5 px-2 py-0.5 rounded border border-amber-500/15">
                          NO B2B PARTNER
                        </span>
                      )}
                    </td>
                    <td className="py-3.5 px-2">
                      <span className={`text-[9px] font-bold font-mono px-2 py-0.5 rounded ${
                        dep.status === 'active' ? 'bg-emerald-500/10 text-emerald-400' :
                        dep.status === 'planned' ? 'bg-cyan-500/10 text-cyan-400' :
                        'bg-slate-500/10 text-slate-400'
                      }`}>
                        {dep.status.toUpperCase()}
                      </span>
                    </td>
                    <td className="py-3.5 px-2 text-right">
                      {dep.status === 'active' && (
                        <button
                          onClick={() => handleEndClick(dep.id)}
                          className="bg-rose-500/10 hover:bg-rose-500 hover:text-slate-950 text-rose-400 text-[10px] font-bold px-2 py-1 rounded border border-rose-500/20 transition-all cursor-pointer"
                        >
                          Release
                        </button>
                      )}
                    </td>
                  </tr>
                );
              })}
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

      {/* INITIATE DEPLOYMENT MODAL POP */}
      {showAddModal && (
        <div className="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 z-50">
          <div className="bg-[#0d1424] border border-slate-800 rounded-xl w-full max-w-md shadow-2xl">
            <div className="p-5 border-b border-slate-800 flex justify-between items-center bg-[#0a0f1d]">
              <h3 className="font-bold text-slate-100 text-sm">Initiate Rotation Assignment</h3>
              <button onClick={() => setShowAddModal(false)} className="text-slate-400 hover:text-white cursor-pointer">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleAddSubmit} className="p-5 space-y-4 text-xs text-slate-300">
              <div>
                <label className="block mb-1 text-slate-400">Select Employee Profile *</label>
                <select
                  value={depForm.employee_id}
                  onChange={(e) => setDepForm({ ...depForm, employee_id: e.target.value })}
                  className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                >
                  {employees.map(emp => (
                    <option key={emp.id} value={emp.id}>{emp.first_name} {emp.last_name} ({emp.employee_code})</option>
                  ))}
                </select>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block mb-1 text-slate-400">Client Account *</label>
                  <select
                    value={depForm.client_id}
                    onChange={(e) => setDepForm({ ...depForm, client_id: e.target.value })}
                    className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                  >
                    {clients.map(c => (
                      <option key={c.id} value={c.id}>{c.name}</option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block mb-1 text-slate-400">Oilfield / Wellsite *</label>
                  <select
                    value={depForm.field_id}
                    onChange={(e) => setDepForm({ ...depForm, field_id: e.target.value })}
                    className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                  >
                    {fields.map(f => (
                      <option key={f.id} value={f.id}>{f.name}</option>
                    ))}
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block mb-1 text-slate-400">Start Mobilization Date *</label>
                  <input
                    type="date"
                    required
                    value={depForm.start_date}
                    onChange={(e) => setDepForm({ ...depForm, start_date: e.target.value })}
                    className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                  />
                </div>
                <div>
                  <label className="block mb-1 text-slate-400">Rotation Cycle Duration (Days) *</label>
                  <input
                    type="number"
                    required
                    value={depForm.rotation_duration_days}
                    onChange={(e) => setDepForm({ ...depForm, rotation_duration_days: Number(e.target.value) })}
                    className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                  />
                </div>
              </div>

              <div>
                <label className="block mb-1 text-slate-400">Expected End Date</label>
                <input
                  type="date"
                  value={depForm.end_date}
                  onChange={(e) => setDepForm({ ...depForm, end_date: e.target.value })}
                  className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                />
              </div>

              <div>
                <label className="block mb-1 text-slate-400">Link Back-to-Back Handover Partner (Optional)</label>
                <select
                  value={depForm.b2b_partner_employee_id}
                  onChange={(e) => setDepForm({ ...depForm, b2b_partner_employee_id: e.target.value })}
                  className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                >
                  <option value="">No matched B2B partner</option>
                  {employees.map(emp => (
                    <option key={emp.id} value={emp.id}>{emp.first_name} {emp.last_name}</option>
                  ))}
                </select>
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
                  Initiate Rotation
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

    </div>
  );
}
