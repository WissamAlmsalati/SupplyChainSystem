import React, { useState } from 'react';
import {
  Shuffle,
  Users,
  AlertTriangle,
  CheckCircle,
  HelpCircle,
  ArrowLeftRight,
  Plus,
  X
} from 'lucide-react';
import { Employee, Deployment, Client, Field } from '../types';
import { usePagination } from '../hooks/usePagination';
import Pagination from './Pagination';

interface BackToBackPlanningViewProps {
  employees: Employee[];
  deployments: Deployment[];
  clients: Client[];
  fields: Field[];
  onLinkB2B: (employeeId: string, partnerId: string) => Promise<any>;
}

export default function BackToBackPlanningView({
  employees,
  deployments,
  clients,
  fields,
  onLinkB2B
}: BackToBackPlanningViewProps) {
  const [showPairModal, setShowPairModal] = useState(false);
  const [empFormId, setEmpFormId] = useState(employees[0]?.id || '');
  const [partnerFormId, setPartnerFormId] = useState(employees[1]?.id || '');

  // Find deployed employees and check B2B status
  const activeDeployments = deployments.filter(d => d.status === 'active');

  const b2bRelationsList = activeDeployments.map(dep => {
    const emp = employees.find(e => e.id === dep.employee_id);
    const partner = employees.find(e => e.id === dep.b2b_partner_employee_id);
    const clientName = clients.find(c => c.id === dep.client_id)?.name || 'Client';
    const fieldName = fields.find(f => f.id === dep.field_id)?.name || 'Field';

    // Conflict calculations
    let conflict: string | null = null;
    let conflictType: 'warning' | 'critical' | null = null;

    if (!dep.b2b_partner_employee_id) {
      conflict = 'No back-to-back partner assigned. Deployed rig status is vulnerable.';
      conflictType = 'warning';
    } else {
      // Find partner's current deployment status
      const partnerDep = deployments.find(d => d.employee_id === dep.b2b_partner_employee_id && d.status === 'active');
      if (partnerDep) {
        // Overlap check
        conflict = 'Schedule overlap! Both back-to-back partners are currently active in field.';
        conflictType = 'critical';
      }
    }

    return {
      deploymentId: dep.id,
      emp,
      partner,
      clientName,
      fieldName,
      startDate: dep.start_date,
      endDate: dep.end_date,
      conflict,
      conflictType
    };
  });

  const { page, setPage, paginatedItems, totalPages, pageSize, totalItems } = usePagination(b2bRelationsList, 10);

  const handlePairSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (empFormId === partnerFormId) {
      alert('An employee cannot be their own back-to-back partner!');
      return;
    }
    await onLinkB2B(empFormId, partnerFormId);
    setShowPairModal(false);
  };

  return (
    <div className="p-8 space-y-8 max-w-7xl mx-auto" id="b2b-planning-wrapper">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 className="text-xl font-bold text-white tracking-tight uppercase">Back-to-Back Planning Desk</h2>
          <p className="text-sm text-slate-400 mt-1">Schedules, relief pairs, and crew-change handover alignments</p>
        </div>

        <button
          onClick={() => setShowPairModal(true)}
          className="bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold px-4 py-2.5 rounded-lg text-sm flex items-center gap-2 cursor-pointer self-start md:self-auto"
        >
          <Shuffle className="w-4 h-4" /> Align Relief Pair
        </button>
      </div>

      {/* Visual Relief Pairs Board */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        {/* Left Column: Handover Alignment List */}
        <div className="space-y-4">
          <h3 className="font-bold text-slate-200 text-sm uppercase tracking-wider">Relief Board</h3>

          {paginatedItems.map((rel) => {
            if (!rel.emp) return null;
            return (
              <div key={rel.deploymentId} className="bg-[#0e1626] border border-slate-800 p-5 rounded-xl space-y-4 shadow-lg relative overflow-hidden">
                {rel.conflictType && (
                  <div className={`absolute top-0 left-0 right-0 h-1 ${
                    rel.conflictType === 'critical' ? 'bg-rose-500' : 'bg-amber-500'
                  }`}></div>
                )}

                <div className="flex items-center justify-between">
                  <div>
                    <span className="text-[10px] text-slate-500 font-mono block">RIG ASSIGNMENT</span>
                    <span className="font-bold text-slate-200 text-xs uppercase">{rel.clientName} • {rel.fieldName}</span>
                  </div>

                  <span className="text-[9px] font-mono text-slate-500 uppercase">Handover Desk</span>
                </div>

                {/* Reliever Pair split view */}
                <div className="grid grid-cols-7 gap-1 items-center bg-black/25 p-3.5 rounded-xl border border-slate-800/60">
                  <div className="col-span-3 text-center">
                    <span className="block text-[10px] text-emerald-400 font-mono font-bold uppercase tracking-wider">ON SHIFT (In Field)</span>
                    <span className="block font-bold text-sm text-slate-100 mt-1">{rel.emp.first_name} {rel.emp.last_name}</span>
                    <span className="block text-[9px] text-slate-500 font-mono mt-0.5">{rel.emp.employee_code}</span>
                  </div>

                  <div className="col-span-1 flex items-center justify-center">
                    <ArrowLeftRight className="w-5 h-5 text-slate-600 animate-pulse" />
                  </div>

                  <div className="col-span-3 text-center">
                    <span className="block text-[10px] text-slate-500 font-mono font-bold uppercase tracking-wider">OFF SHIFT (Relief)</span>
                    {rel.partner ? (
                      <>
                        <span className="block font-bold text-sm text-slate-100 mt-1">{rel.partner.first_name} {rel.partner.last_name}</span>
                        <span className="block text-[9px] text-slate-500 font-mono mt-0.5">{rel.partner.employee_code}</span>
                      </>
                    ) : (
                      <span className="block text-amber-400 font-bold text-xs mt-2 italic">UNASSIGNED</span>
                    )}
                  </div>
                </div>

                {/* Handover Conflicts and warnings alerts */}
                {rel.conflict && (
                  <div className={`p-3 rounded-lg flex items-start gap-3 border ${
                    rel.conflictType === 'critical'
                      ? 'bg-rose-500/5 text-rose-400 border-rose-500/20'
                      : 'bg-amber-500/5 text-amber-400 border-amber-500/20'
                  }`}>
                    <AlertTriangle className="w-5 h-5 shrink-0 mt-0.5" />
                    <div>
                      <span className="font-bold text-[11px] uppercase tracking-wider">Handover Exception:</span>
                      <p className="text-[11px] leading-relaxed mt-0.5">{rel.conflict}</p>
                    </div>
                  </div>
                )}
              </div>
            );
          })}

          <Pagination
            page={page}
            totalPages={totalPages}
            totalItems={totalItems}
            pageSize={pageSize}
            onPageChange={setPage}
          />
        </div>

        {/* Right Column: Handover Planning Matrix Context Guide */}
        <div className="bg-[#0e1626] border border-slate-800 p-6 rounded-xl shadow-lg space-y-6 self-start">
          <h3 className="font-bold text-slate-200 text-sm uppercase tracking-wider border-b border-slate-800 pb-3">Operational Handover Protocol</h3>
          <p className="text-xs text-slate-400 leading-relaxed font-sans">
            A secure crew rotation depends entirely on reliable Back-to-Back (B2B) handovers. Perfect handovers ensure zero overlaps in cost-accounting, and zero gaps in safety-critical operations.
          </p>

          <div className="space-y-4">
            <h4 className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Exception Rules Directory</h4>
            <div className="space-y-3">
              <div className="flex gap-3 items-start text-xs">
                <div className="w-2 h-2 rounded-full bg-rose-500 shrink-0 mt-1.5"></div>
                <div>
                  <span className="font-bold text-slate-200">Rotation Schedule Overlaps (Critical)</span>
                  <p className="text-slate-500 text-[11px] mt-0.5">Triggered if both relievers are deployed on fields simultaneously. Prevents duplicate billing and logistics bottlenecks.</p>
                </div>
              </div>

              <div className="flex gap-3 items-start text-xs">
                <div className="w-2 h-2 rounded-full bg-amber-500 shrink-0 mt-1.5"></div>
                <div>
                  <span className="font-bold text-slate-200">Missing Partner Alerts (Warning)</span>
                  <p className="text-slate-500 text-[11px] mt-0.5">Triggered if an active rig role lacks a registered reliever partner. Threatens continuity of the shift change.</p>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>

      {/* RELIEF PAIR MODAL POP */}
      {showPairModal && (
        <div className="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 z-50">
          <div className="bg-[#0d1424] border border-slate-800 rounded-xl w-full max-w-sm shadow-2xl">
            <div className="p-5 border-b border-slate-800 flex justify-between items-center bg-[#0a0f1d]">
              <h3 className="font-bold text-slate-100 text-sm">Align Relief Pair</h3>
              <button onClick={() => setShowPairModal(false)} className="text-slate-400 hover:text-white cursor-pointer">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handlePairSubmit} className="p-5 space-y-4 text-xs text-slate-300">
              <div>
                <label className="block mb-1 text-slate-400">Select Employee Profile *</label>
                <select
                  value={empFormId}
                  onChange={(e) => setEmpFormId(e.target.value)}
                  className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                >
                  {employees.map(emp => (
                    <option key={emp.id} value={emp.id}>{emp.first_name} {emp.last_name}</option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block mb-1 text-slate-400">Select relief Back-to-Back partner *</label>
                <select
                  value={partnerFormId}
                  onChange={(e) => setPartnerFormId(e.target.value)}
                  className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                >
                  {employees.map(emp => (
                    <option key={emp.id} value={emp.id}>{emp.first_name} {emp.last_name}</option>
                  ))}
                </select>
              </div>

              <div className="pt-4 flex justify-end gap-2 border-t border-slate-800">
                <button
                  type="button"
                  onClick={() => setShowPairModal(false)}
                  className="bg-slate-800 hover:bg-slate-700 text-slate-200 px-4 py-2 rounded-lg cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold px-6 py-2 rounded-lg cursor-pointer"
                >
                  Save relief pairing
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

    </div>
  );
}
