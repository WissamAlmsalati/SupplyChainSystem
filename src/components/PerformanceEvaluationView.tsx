import React, { useState } from 'react';
import {
  TrendingUp,
  Award,
  ShieldCheck,
  CheckCircle,
  ThumbsUp,
  AlertTriangle,
  User,
  Plus,
  X
} from 'lucide-react';
import { Employee, Evaluation } from '../types';
import { usePagination } from '../hooks/usePagination';
import Pagination from './Pagination';

interface PerformanceEvaluationViewProps {
  employees: Employee[];
  evaluations: Evaluation[];
  onAddEvaluation: (evalData: any) => Promise<any>;
  openNewAppraisalOnInit?: boolean;
}

export default function PerformanceEvaluationView({
  employees,
  evaluations,
  onAddEvaluation,
  openNewAppraisalOnInit = false
}: PerformanceEvaluationViewProps) {
  const [showAddModal, setShowAddModal] = useState(openNewAppraisalOnInit);

  const [evalForm, setEvalForm] = useState({
    employee_id: employees[0]?.id || '',
    supervisor_name: '',
    period_start: new Date().toISOString().split('T')[0],
    period_end: new Date().toISOString().split('T')[0],
    competency_score: 80,
    hse_score: 80,
    training_score: 80,
    attitude_score: 80,
    supervisor_comments: '',
    would_rehire: true
  });

  const handleScoreChange = (paramName: string, val: number) => {
    setEvalForm(prev => ({
      ...prev,
      [paramName]: val
    }));
  };

  const handleFormSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    await onAddEvaluation(evalForm);
    setShowAddModal(false);
    setEvalForm({
      employee_id: employees[0]?.id || '',
      supervisor_name: '',
      period_start: new Date().toISOString().split('T')[0],
      period_end: new Date().toISOString().split('T')[0],
      competency_score: 80,
      hse_score: 80,
      training_score: 80,
      attitude_score: 80,
      supervisor_comments: '',
      would_rehire: true
    });
  };

  // Leaders calculations
  const leaderboard = employees
    .map(emp => {
      const empEvals = evaluations.filter(e => e.employee_id === emp.id);
      if (empEvals.length === 0) return { emp, avg: 0 };
      const sum = empEvals.reduce((acc, curr) => acc + curr.overall_score, 0);
      return { emp, avg: Math.round(sum / empEvals.length) };
    })
    .filter(item => item.avg > 0)
    .sort((a, b) => b.avg - a.avg);

  const { page, setPage, paginatedItems, totalPages, pageSize, totalItems } = usePagination(evaluations, 10);

  return (
    <div className="p-8 space-y-8 max-w-7xl mx-auto" id="evaluations-desk-wrapper">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 className="text-xl font-bold text-white tracking-tight uppercase">Performance & Grading Desk</h2>
          <p className="text-sm text-slate-400 mt-1">Digitize crew appraisal, track competence logs, and monitor safety adherence</p>
        </div>

        <button
          onClick={() => setShowAddModal(true)}
          className="bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold px-4 py-2.5 rounded-lg text-sm flex items-center gap-2 cursor-pointer self-start md:self-auto"
        >
          <Plus className="w-4 h-4" /> File Crew Appraisal
        </button>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        {/* Appraisal History Feed list */}
        <div className="lg:col-span-2 space-y-6">
          <h3 className="font-bold text-slate-200 text-sm uppercase tracking-wider">Filed Appraisals Feed</h3>
          
          <div className="space-y-4">
            {paginatedItems.map((item) => {
              const emp = employees.find(e => e.id === item.employee_id);
              return (
                <div key={item.id} className="bg-[#0e1626] border border-slate-800 p-6 rounded-xl space-y-4 shadow-lg">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      <div className="w-10 h-10 bg-slate-800 rounded-lg flex items-center justify-center font-bold text-xs text-slate-300">
                        {emp ? `${emp.first_name[0]}${emp.last_name[0]}` : 'EM'}
                      </div>
                      <div>
                        <h4 className="font-bold text-slate-200 text-sm font-sans">
                          {emp ? `${emp.first_name} ${emp.last_name}` : 'Archive Record'}
                        </h4>
                        <span className="text-[10px] text-slate-500 font-mono">Appraisal Period: {item.period_start} to {item.period_end}</span>
                      </div>
                    </div>

                    <div className="text-right">
                      <span className="text-2xl font-bold font-mono text-emerald-400 block">{item.overall_score}%</span>
                      <span className="text-[9px] text-slate-500 uppercase tracking-wider block font-bold">Overall Score</span>
                    </div>
                  </div>

                  {/* Criteria split indicators */}
                  <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                    {[
                      { label: 'Technical Competence', score: item.competency_score, color: 'text-blue-400' },
                      { label: 'Safety & HSE Adherence', score: item.hse_score, color: 'text-emerald-400' },
                      { label: 'Training & Aptitude', score: item.training_score, color: 'text-cyan-400' },
                      { label: 'Professional Attitude', score: item.attitude_score, color: 'text-pink-400' }
                    ].map((crit, idx) => (
                      <div key={idx} className="bg-slate-900/40 p-3 rounded-lg border border-slate-850">
                        <span className="block text-[10px] text-slate-500 uppercase tracking-wider font-semibold leading-none">{crit.label}</span>
                        <span className={`block font-bold font-mono text-sm mt-1.5 ${crit.color}`}>{crit.score}%</span>
                      </div>
                    ))}
                  </div>

                  {item.supervisor_comments && (
                    <div className="p-3 bg-black/25 rounded-lg border-l-2 border-emerald-500/55 text-xs text-slate-300 italic leading-relaxed">
                      "{item.supervisor_comments}"
                    </div>
                  )}

                  <div className="flex justify-between items-center text-xs text-slate-500 pt-1 border-t border-slate-850/40">
                    <span>Supervisor: <strong className="text-slate-400 font-medium">{item.supervisor_name}</strong></span>
                    <span className={`font-bold text-[9px] uppercase px-2 py-0.5 rounded ${
                      item.would_rehire ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400'
                    }`}>
                      {item.would_rehire ? 'Recommended for Rehire' : 'Rehire Exception'}
                    </span>
                  </div>
                </div>
              );
            })}

            {evaluations.length === 0 && (
              <p className="text-sm text-slate-500 py-12 text-center bg-[#0e1626] border border-slate-800 rounded-xl">
                No performance evaluations filed yet. Click "File Crew Appraisal" above.
              </p>
            )}

            <Pagination
              page={page}
              totalPages={totalPages}
              totalItems={totalItems}
              pageSize={pageSize}
              onPageChange={setPage}
            />
          </div>
        </div>

        {/* Competency Leaderboard Sidebar */}
        <div className="space-y-6">
          <div className="bg-[#0e1626] border border-slate-800 p-6 rounded-xl shadow-lg space-y-4">
            <h3 className="font-bold text-slate-200 text-sm uppercase tracking-wider border-b border-slate-800 pb-2">Crew Leaderboard</h3>
            <p className="text-xs text-slate-400 leading-relaxed font-sans">
              Highest-rated crew members ranked by overall historic appraisal averages.
            </p>

            <div className="space-y-3">
              {leaderboard.slice(0, 5).map((item, index) => (
                <div key={item.emp.id} className="p-3 bg-slate-900/30 border border-slate-850 rounded-lg flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    <span className="font-mono font-bold text-xs text-emerald-400 w-4">#{index + 1}</span>
                    <div>
                      <h4 className="font-bold text-xs text-slate-200">{item.emp.first_name} {item.emp.last_name}</h4>
                      <span className="text-[9px] text-slate-500 font-mono uppercase">{item.emp.employee_code}</span>
                    </div>
                  </div>
                  <span className="text-xs font-bold font-mono text-emerald-400 bg-emerald-500/10 px-2 py-1 rounded">
                    {item.avg}%
                  </span>
                </div>
              ))}

              {leaderboard.length === 0 && (
                <p className="text-center text-xs text-slate-500 py-6">No rated crew members yet.</p>
              )}
            </div>
          </div>
        </div>

      </div>

      {/* FILE NEW APPRAISAL MODAL POP */}
      {showAddModal && (
        <div className="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 z-50">
          <div className="bg-[#0d1424] border border-slate-800 rounded-xl w-full max-w-lg shadow-2xl">
            <div className="p-5 border-b border-slate-800 flex justify-between items-center bg-[#0a0f1d]">
              <h3 className="font-bold text-slate-100 text-sm">File Digital Performance Appraisal</h3>
              <button onClick={() => setShowAddModal(false)} className="text-slate-400 hover:text-white cursor-pointer">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleFormSubmit} className="p-5 space-y-4 text-xs text-slate-300">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block mb-1 text-slate-400">Select Employee Profile *</label>
                  <select
                    value={evalForm.employee_id}
                    onChange={(e) => setEvalForm({ ...evalForm, employee_id: e.target.value })}
                    className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                  >
                    {employees.map(emp => (
                      <option key={emp.id} value={emp.id}>{emp.first_name} {emp.last_name}</option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block mb-1 text-slate-400">Supervisor Name *</label>
                  <input
                    type="text"
                    required
                    value={evalForm.supervisor_name}
                    onChange={(e) => setEvalForm({ ...evalForm, supervisor_name: e.target.value })}
                    className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block mb-1 text-slate-400">Appraisal Start Date *</label>
                  <input
                    type="date"
                    required
                    value={evalForm.period_start}
                    onChange={(e) => setEvalForm({ ...evalForm, period_start: e.target.value })}
                    className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                  />
                </div>
                <div>
                  <label className="block mb-1 text-slate-400">Appraisal End Date *</label>
                  <input
                    type="date"
                    required
                    value={evalForm.period_end}
                    onChange={(e) => setEvalForm({ ...evalForm, period_end: e.target.value })}
                    className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                  />
                </div>
              </div>

              {/* Slider scales */}
              <div className="space-y-3.5 border-t border-slate-800/60 pt-3">
                <h4 className="font-bold text-[10px] text-slate-400 uppercase tracking-wider mb-2">Grading Metric Parameters</h4>
                
                {[
                  { key: 'competency_score', label: 'Technical Competence & Skill' },
                  { key: 'hse_score', label: 'Safety Commitment / HSE Compliance' },
                  { key: 'training_score', label: 'Training Adaptability & Aptitude' },
                  { key: 'attitude_score', label: 'Professional Attendance & Attitude' }
                ].map((param) => {
                  const val = (evalForm as any)[param.key];
                  return (
                    <div key={param.key} className="space-y-1">
                      <div className="flex justify-between items-center">
                        <span className="text-slate-300">{param.label}</span>
                        <span className="font-mono font-bold text-emerald-400">{val}%</span>
                      </div>
                      <input
                        type="range"
                        min="20"
                        max="100"
                        step="5"
                        value={val}
                        onChange={(e) => handleScoreChange(param.key, Number(e.target.value))}
                        className="w-full accent-emerald-500 cursor-pointer h-1.5 bg-slate-800 rounded-lg"
                      />
                    </div>
                  );
                })}
              </div>

              <div>
                <label className="block mb-1 text-slate-400">Supervisor Comments</label>
                <textarea
                  value={evalForm.supervisor_comments}
                  onChange={(e) => setEvalForm({ ...evalForm, supervisor_comments: e.target.value })}
                  className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white h-16 resize-none"
                  placeholder="Record summary comments..."
                ></textarea>
              </div>

              <div className="flex items-center gap-2">
                <input
                  type="checkbox"
                  id="would_rehire"
                  checked={evalForm.would_rehire}
                  onChange={(e) => setEvalForm({ ...evalForm, would_rehire: e.target.checked })}
                  className="accent-emerald-500 rounded border-slate-800"
                />
                <label htmlFor="would_rehire" className="text-slate-300 font-semibold cursor-pointer">
                  Recommend this employee for rehire & rotation extensions
                </label>
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
                  File Appraisal
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

    </div>
  );
}
