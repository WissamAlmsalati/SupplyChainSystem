import React, { useState } from 'react';
import {
  Users,
  Calendar,
  AlertTriangle,
  Sparkles,
  Search,
  Plus,
  TrendingUp,
  Clock,
  ArrowRight
} from 'lucide-react';
import {
  ResponsiveContainer,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  Tooltip,
  PieChart,
  Pie,
  Cell,
  Legend
} from 'recharts';
import {
  Employee,
  Deployment,
  Document,
  Certificate,
  ActivityLog,
  AIRecommendation,
  Client,
  Field,
  CertificateType,
  DocumentType
} from '../types';

interface DashboardViewProps {
  employees: Employee[];
  deployments: Deployment[];
  documents: Document[];
  certificates: Certificate[];
  activityLogs: ActivityLog[];
  recommendations: AIRecommendation[];
  clients: Client[];
  fields: Field[];
  certTypes: CertificateType[];
  docTypes: DocumentType[];
  onNavigate: (view: string, actionState?: any) => void;
}

export default function DashboardView({
  employees,
  deployments,
  documents,
  certificates,
  activityLogs,
  recommendations,
  clients,
  fields,
  certTypes,
  docTypes,
  onNavigate
}: DashboardViewProps) {
  const [searchQuery, setSearchQuery] = useState('');

  // -------------------------------------------------------------
  // CALCULATE METRICS
  // -------------------------------------------------------------
  const totalEmployees = employees.length;
  const inFieldCount = employees.filter(e => e.status === 'deployed').length;
  const availableCount = employees.filter(e => e.status === 'active').length;
  const onLeaveCount = employees.filter(e => e.status === 'on_leave').length;

  // Returning in 7 days (e.g. deployments ending or leaves ending soon)
  const now = new Date();
  const next7Days = new Date();
  next7Days.setDate(now.getDate() + 7);

  const returningSoonCount = deployments.filter(d => {
    if (d.status !== 'active') return false;
    const end = new Date(d.end_date);
    return end >= now && end <= next7Days;
  }).length;

  // -------------------------------------------------------------
  // CHARTS DATA PREPARATION
  // -------------------------------------------------------------
  // 1. Crew Distribution by Client
  const clientDistribution = clients.map(client => {
    const count = deployments.filter(d => d.client_id === client.id && d.status === 'active').length;
    return { name: client.name, count };
  }).filter(c => c.count > 0);

  // 2. Expiring soon & Expired Compliance count
  const expiringDocs = documents.filter(d => d.status === 'expiring_soon' || d.status === 'expired');
  const expiringCerts = certificates.filter(c => c.status === 'expiring_soon' || c.status === 'expired');

  // Chart colors
  const COLORS = ['#10b981', '#06b6d4', '#f59e0b', '#ec4899', '#8b5cf6'];

  // -------------------------------------------------------------
  // GLOBAL SEARCH TYPEAHEAD IMPLEMENTATION
  // -------------------------------------------------------------
  const handleGlobalSearch = (e: React.FormEvent) => {
    e.preventDefault();
    if (!searchQuery.trim()) return;

    // Direct search matching
    const normalizedQuery = searchQuery.toLowerCase();
    const matchedEmp = employees.find(
      emp =>
        emp.first_name.toLowerCase().includes(normalizedQuery) ||
        emp.last_name.toLowerCase().includes(normalizedQuery) ||
        emp.employee_code.toLowerCase().includes(normalizedQuery)
    );

    if (matchedEmp) {
      onNavigate('employees', { selectedEmployeeId: matchedEmp.id });
    } else {
      // General fallthrough to employee view with search text
      onNavigate('employees', { filterSearch: searchQuery });
    }
  };

  return (
    <div className="p-8 space-y-8 max-w-7xl mx-auto" id="dashboard-view-wrapper">
      {/* Search and Welcome Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 className="text-2xl font-bold tracking-tight text-white font-sans">Operations Command Center</h2>
          <p className="text-sm text-slate-400 mt-1">Live workforce synchronization and compliance diagnostics</p>
        </div>

        {/* Global Quick Search */}
        <form onSubmit={handleGlobalSearch} className="flex items-center gap-3 w-full md:w-auto">
          <div className="relative w-full md:w-80">
            <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4.5 h-4.5 text-slate-400" />
            <input
              type="text"
              placeholder="Search employee, ID, position..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-full bg-[#111827] border border-slate-800 rounded-lg pl-10 pr-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 transition-colors font-sans"
            />
          </div>
          <button
            type="submit"
            className="bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-semibold px-4 py-2.5 rounded-lg text-sm transition-colors flex items-center gap-2 shrink-0 cursor-pointer"
          >
            Search
          </button>
        </form>
      </div>

      {/* KPI Stats Grid */}
      <div className="grid grid-cols-2 md:grid-cols-5 gap-4" id="kpi-stats-grid">
        {[
          { label: 'Total Workforce', value: totalEmployees, icon: Users, color: 'text-blue-400', bg: 'bg-blue-500/10' },
          { label: 'Deployed in Field', value: inFieldCount, icon: Calendar, color: 'text-emerald-400', bg: 'bg-emerald-500/10' },
          { label: 'Available Standby', value: availableCount, icon: Clock, color: 'text-cyan-400', bg: 'bg-cyan-500/10' },
          { label: 'On Off-Rotation / Leave', value: onLeaveCount, icon: AlertTriangle, color: 'text-pink-400', bg: 'bg-pink-500/10' },
          { label: 'Returning ≤ 7 Days', value: returningSoonCount, icon: Clock, color: 'text-amber-400', bg: 'bg-amber-500/10' }
        ].map((kpi, idx) => {
          const Icon = kpi.icon;
          return (
            <div key={idx} className="bg-[#0e1626] border border-slate-800/80 p-5 rounded-xl shadow-md flex flex-col justify-between">
              <div className="flex items-center justify-between">
                <span className="text-xs font-semibold text-slate-400 uppercase tracking-wider">{kpi.label}</span>
                <div className={`p-2 rounded-lg ${kpi.bg}`}>
                  <Icon className={`w-5 h-5 ${kpi.color}`} />
                </div>
              </div>
              <div className="mt-4">
                <span className="text-3xl font-bold text-white font-mono">{kpi.value}</span>
              </div>
            </div>
          );
        })}
      </div>

      {/* Main Grid: Charts & Compliance Warnings */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Left Column: Analytics Charts */}
        <div className="lg:col-span-2 space-y-8">
          {/* Active Field Distribution Chart */}
          <div className="bg-[#0e1626] border border-slate-800 p-6 rounded-xl shadow-lg">
            <h3 className="text-base font-bold text-slate-200 mb-6 uppercase tracking-wider flex items-center gap-2">
              <TrendingUp className="w-5 h-5 text-emerald-500" /> Active Crew Distribution
            </h3>
            
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
              <div className="h-64">
                {clientDistribution.length > 0 ? (
                  <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                      <Pie
                        data={clientDistribution}
                        cx="50%"
                        cy="50%"
                        innerRadius={60}
                        outerRadius={80}
                        paddingAngle={5}
                        dataKey="count"
                      >
                        {clientDistribution.map((entry, index) => (
                          <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                        ))}
                      </Pie>
                      <Tooltip 
                        contentStyle={{ backgroundColor: '#0f172a', borderColor: '#334155', borderRadius: '8px' }}
                        itemStyle={{ color: '#fff' }}
                      />
                    </PieChart>
                  </ResponsiveContainer>
                ) : (
                  <div className="flex items-center justify-center h-full text-slate-500 text-sm font-sans">
                    No active deployments to chart
                  </div>
                )}
              </div>

              {/* Legends list */}
              <div className="space-y-4">
                <h4 className="text-xs font-bold text-slate-400 uppercase tracking-wider">Client Allocation</h4>
                {clientDistribution.map((entry, index) => (
                  <div key={index} className="flex items-center justify-between border-b border-slate-800/40 pb-2">
                    <div className="flex items-center gap-2.5">
                      <div className="w-3 h-3 rounded-full" style={{ backgroundColor: COLORS[index % COLORS.length] }}></div>
                      <span className="text-sm font-medium text-slate-200">{entry.name}</span>
                    </div>
                    <span className="text-sm font-bold font-mono text-white">{entry.count} Crew</span>
                  </div>
                ))}
              </div>
            </div>
          </div>

          {/* Quick Actions Board */}
          <div className="bg-[#0e1626] border border-slate-800 p-6 rounded-xl shadow-lg">
            <h3 className="text-base font-bold text-slate-200 mb-4 uppercase tracking-wider">Quick Actions</h3>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <button
                onClick={() => onNavigate('operations', { openNewDeployment: true })}
                className="bg-[#111a2e] border border-slate-800 hover:border-emerald-500/40 p-4 rounded-xl text-left transition-all group cursor-pointer"
              >
                <div className="w-10 h-10 bg-emerald-500/10 rounded-lg flex items-center justify-center mb-3">
                  <Plus className="w-5 h-5 text-emerald-400 group-hover:scale-110 transition-transform" />
                </div>
                <span className="block font-bold text-sm text-slate-200">New Deployment</span>
                <span className="block text-xs text-slate-500 mt-1">Deploy employee on client rig rotation</span>
              </button>

              <button
                onClick={() => onNavigate('evaluations', { openNewAppraisal: true })}
                className="bg-[#111a2e] border border-slate-800 hover:border-cyan-500/40 p-4 rounded-xl text-left transition-all group cursor-pointer"
              >
                <div className="w-10 h-10 bg-cyan-500/10 rounded-lg flex items-center justify-center mb-3">
                  <Plus className="w-5 h-5 text-cyan-400 group-hover:scale-110 transition-transform" />
                </div>
                <span className="block font-bold text-sm text-slate-200">New Appraisal</span>
                <span className="block text-xs text-slate-500 mt-1">Digitize appraisal and record safety ratings</span>
              </button>

              <button
                onClick={() => onNavigate('documents', { openUploadDoc: true })}
                className="bg-[#111a2e] border border-slate-800 hover:border-amber-500/40 p-4 rounded-xl text-left transition-all group cursor-pointer"
              >
                <div className="w-10 h-10 bg-amber-500/10 rounded-lg flex items-center justify-center mb-3">
                  <Plus className="w-5 h-5 text-amber-400 group-hover:scale-110 transition-transform" />
                </div>
                <span className="block font-bold text-sm text-slate-200">Upload Credentials</span>
                <span className="block text-xs text-slate-500 mt-1">Upload personal documents or driving license</span>
              </button>
            </div>
          </div>
        </div>

        {/* Right Column: AI Insights & Activity logs */}
        <div className="space-y-8">
          {/* AI Operational Recommendations panel */}
          <div className="bg-[#0e1626] border border-slate-800 p-6 rounded-xl shadow-lg relative overflow-hidden">
            {/* Top decorative gradient */}
            <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-500 via-cyan-500 to-rose-500"></div>
            
            <div className="flex items-center justify-between mb-6">
              <h3 className="text-base font-bold text-slate-200 uppercase tracking-wider flex items-center gap-2">
                <Sparkles className="w-5 h-5 text-emerald-400 animate-pulse" /> AI Operations Desk
              </h3>
              <button
                onClick={() => onNavigate('ai-assistant')}
                className="text-xs font-semibold text-emerald-400 hover:text-emerald-300 flex items-center gap-1 cursor-pointer"
              >
                View All <ArrowRight className="w-3.5 h-3.5" />
              </button>
            </div>

            <div className="space-y-4">
              {recommendations.filter(r => r.status === 'new').slice(0, 3).map((rec) => {
                const isCritical = rec.reason.toLowerCase().includes('expired') || rec.reason.toLowerCase().includes('missing');
                return (
                  <div key={rec.id} className="p-4 bg-[#111a2e] border border-slate-800/80 rounded-xl space-y-2">
                    <div className="flex items-center justify-between">
                      <span className={`text-[10px] font-bold px-2 py-0.5 rounded uppercase tracking-wider ${
                        isCritical ? 'bg-rose-500/15 text-rose-400 border border-rose-500/20' : 'bg-amber-500/15 text-amber-400 border border-amber-500/20'
                      }`}>
                        {rec.category.replace('_', ' ')}
                      </span>
                      <span className="text-[10px] text-slate-500 font-mono">ALERT</span>
                    </div>
                    <p className="text-xs font-medium text-slate-200 leading-relaxed font-sans">{rec.reason}</p>
                    <p className="text-[11px] text-slate-400 italic bg-black/20 p-2 rounded leading-relaxed border-l-2 border-emerald-500/40">
                      Recommendation: {rec.suggested_action}
                    </p>
                    <div className="pt-1 flex justify-end">
                      <button
                        onClick={() => onNavigate('ai-assistant', { highlightRecId: rec.id })}
                        className="text-xs font-bold text-emerald-400 hover:text-emerald-300 flex items-center gap-1 cursor-pointer"
                      >
                        Action Desk <ArrowRight className="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </div>
                );
              })}

              {recommendations.filter(r => r.status === 'new').length === 0 && (
                <div className="text-center py-8">
                  <p className="text-sm text-slate-500 font-sans">All compliant! Zero operational anomalies detected.</p>
                </div>
              )}
            </div>
          </div>

          {/* Recent Operations Activity Feed */}
          <div className="bg-[#0e1626] border border-slate-800 p-6 rounded-xl shadow-lg">
            <h3 className="text-base font-bold text-slate-200 mb-6 uppercase tracking-wider flex items-center gap-2">
              <Clock className="w-5 h-5 text-slate-400" /> Recent Activities
            </h3>

            <div className="relative border-l border-slate-800 pl-4 space-y-6">
              {activityLogs.slice(0, 5).map((log) => {
                const date = new Date(log.created_at);
                const timeStr = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                return (
                  <div key={log.id} className="relative group">
                    {/* Circle icon on line */}
                    <div className="absolute -left-[21px] top-1.5 w-2.5 h-2.5 bg-slate-800 group-hover:bg-emerald-500 rounded-full border border-slate-900 transition-colors"></div>
                    <div>
                      <div className="flex items-center justify-between gap-2">
                        <span className="text-xs font-bold text-slate-300 font-sans">{log.description}</span>
                        <span className="text-[10px] font-mono text-slate-500 shrink-0">{timeStr}</span>
                      </div>
                      <span className="text-[10px] font-mono text-slate-500 uppercase tracking-wider block mt-0.5">{log.action} • {log.entity_type}</span>
                    </div>
                  </div>
                );
              })}

              {activityLogs.length === 0 && (
                <div className="text-center py-6 text-slate-500 text-sm font-sans">
                  No logged operation activities today.
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Expiry Critical Boards Section */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {/* Ranked Expiring Documents Table */}
        <div className="bg-[#0e1626] border border-slate-800 p-6 rounded-xl shadow-lg">
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-base font-bold text-slate-200 uppercase tracking-wider">Expiring Passports & IDs</h3>
            <button
              onClick={() => onNavigate('documents')}
              className="text-xs font-bold text-emerald-400 hover:text-emerald-300 cursor-pointer"
            >
              Verify All
            </button>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm border-collapse">
              <thead>
                <tr className="border-b border-slate-800 text-xs font-bold text-slate-400 uppercase tracking-wider">
                  <th className="py-3 px-2">Employee</th>
                  <th className="py-3 px-2">Document</th>
                  <th className="py-3 px-2">Expiry</th>
                  <th className="py-3 px-2 text-right">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/40">
                {expiringDocs.slice(0, 5).map((doc) => {
                  const emp = employees.find(e => e.id === doc.employee_id);
                  const type = docTypes.find(t => t.id === doc.document_type_id);
                  return (
                    <tr key={doc.id} className="hover:bg-slate-800/20 transition-colors">
                      <td className="py-3.5 px-2 font-medium text-slate-200">
                        {emp ? `${emp.first_name} ${emp.last_name}` : 'Unknown'}
                      </td>
                      <td className="py-3.5 px-2 text-slate-400 text-xs">
                        {type ? type.name : 'Document'}
                      </td>
                      <td className="py-3.5 px-2 font-mono text-xs text-slate-300">
                        {doc.expiry_date || 'N/A'}
                      </td>
                      <td className="py-3.5 px-2 text-right">
                        <span className={`text-[10px] font-bold px-2 py-0.5 rounded font-mono ${
                          doc.status === 'expired' ? 'bg-rose-500/15 text-rose-400' : 'bg-amber-500/15 text-amber-400'
                        }`}>
                          {doc.status.replace('_', ' ')}
                        </span>
                      </td>
                    </tr>
                  );
                })}

                {expiringDocs.length === 0 && (
                  <tr>
                    <td colSpan={4} className="py-6 text-center text-slate-500 text-sm font-sans">
                      All passports and IDs are fully compliant!
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>

        {/* Ranked Expiring Certificates Table */}
        <div className="bg-[#0e1626] border border-slate-800 p-6 rounded-xl shadow-lg">
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-base font-bold text-slate-200 uppercase tracking-wider">Expiring Safety Credentials</h3>
            <button
              onClick={() => onNavigate('certificates')}
              className="text-xs font-bold text-emerald-400 hover:text-emerald-300 cursor-pointer"
            >
              Verify Matrix
            </button>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm border-collapse">
              <thead>
                <tr className="border-b border-slate-800 text-xs font-bold text-slate-400 uppercase tracking-wider">
                  <th className="py-3 px-2">Employee</th>
                  <th className="py-3 px-2">Certificate</th>
                  <th className="py-3 px-2">Expiry</th>
                  <th className="py-3 px-2 text-right">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/40">
                {expiringCerts.slice(0, 5).map((cert) => {
                  const emp = employees.find(e => e.id === cert.employee_id);
                  const type = certTypes.find(t => t.id === cert.certificate_type_id);
                  return (
                    <tr key={cert.id} className="hover:bg-slate-800/20 transition-colors">
                      <td className="py-3.5 px-2 font-medium text-slate-200">
                        {emp ? `${emp.first_name} ${emp.last_name}` : 'Unknown'}
                      </td>
                      <td className="py-3.5 px-2 text-slate-400 text-xs">
                        {type ? type.name : 'Certificate'}
                      </td>
                      <td className="py-3.5 px-2 font-mono text-xs text-slate-300">
                        {cert.expiry_date || 'N/A'}
                      </td>
                      <td className="py-3.5 px-2 text-right">
                        <span className={`text-[10px] font-bold px-2 py-0.5 rounded font-mono ${
                          cert.status === 'expired' ? 'bg-rose-500/15 text-rose-400' : 'bg-amber-500/15 text-amber-400'
                        }`}>
                          {cert.status.replace('_', ' ')}
                        </span>
                      </td>
                    </tr>
                  );
                })}

                {expiringCerts.length === 0 && (
                  <tr>
                    <td colSpan={4} className="py-6 text-center text-slate-500 text-sm font-sans">
                      All safety and BOSIET certifications are active!
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}
