import React from 'react';
import {
  LayoutDashboard,
  Users,
  FileText,
  Award,
  CalendarDays,
  Shuffle,
  TrendingUp,
  Coins,
  Settings,
  Bot,
  AlertCircle
} from 'lucide-react';
import { AIRecommendation } from '../types';

interface SidebarProps {
  currentView: string;
  onViewChange: (view: string) => void;
  recommendations: AIRecommendation[];
}

export default function Sidebar({ currentView, onViewChange, recommendations }: SidebarProps) {
  const activeCount = recommendations.filter(r => r.status === 'new').length;

  const menuItems = [
    { id: 'dashboard', label: 'Dashboard', icon: LayoutDashboard },
    { id: 'employees', label: 'Employee Master', icon: Users },
    { id: 'documents', label: 'Document Compliance', icon: FileText },
    { id: 'certificates', label: 'Credential Matrix', icon: Award },
    { id: 'operations', label: 'Operations & Crew', icon: CalendarDays },
    { id: 'b2b', label: 'Back-to-Back Planning', icon: Shuffle },
    { id: 'evaluations', label: 'Appraisal & Grading', icon: TrendingUp },
    { id: 'payroll', label: 'Payroll Preparation', icon: Coins },
    { id: 'ai-assistant', label: 'AI Operations Desk', icon: Bot, badge: activeCount },
    { id: 'settings', label: 'Global Settings', icon: Settings },
  ];

  return (
    <div className="w-64 bg-[#0e1626] border-r border-slate-800 flex flex-col h-screen sticky top-0" id="app-sidebar">
      {/* Brand Header */}
      <div className="p-6 border-b border-slate-800 flex items-center gap-3">
        <div className="w-10 h-10 bg-emerald-500 rounded-lg flex items-center justify-center font-bold text-slate-900 tracking-wider text-xl shadow-md shadow-emerald-500/15">
          TQ
        </div>
        <div>
          <h1 className="font-bold text-slate-100 text-sm tracking-wide leading-none uppercase">TAQA</h1>
          <p className="text-xs text-slate-400 mt-1 uppercase font-semibold tracking-wider">Workforce Ops</p>
        </div>
      </div>

      {/* Navigation List */}
      <nav className="flex-1 px-4 py-6 space-y-1.5 overflow-y-auto">
        {menuItems.map((item) => {
          const Icon = item.icon;
          const isActive = currentView === item.id;
          return (
            <button
              key={item.id}
              onClick={() => onViewChange(item.id)}
              className={`w-full flex items-center justify-between px-4 py-3 rounded-lg text-sm font-medium transition-all ${
                isActive
                  ? 'bg-emerald-500/10 text-emerald-400 border-l-4 border-emerald-500'
                  : 'text-slate-300 hover:bg-slate-800/50 hover:text-white'
              }`}
            >
              <div className="flex items-center gap-3">
                <Icon className={`w-5 h-5 ${isActive ? 'text-emerald-400' : 'text-slate-400 group-hover:text-white'}`} />
                <span>{item.label}</span>
              </div>
              {item.badge !== undefined && item.badge > 0 && (
                <span className="bg-rose-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full flex items-center justify-center animate-pulse">
                  {item.badge}
                </span>
              )}
            </button>
          );
        })}
      </nav>

      {/* Footer System Status Info */}
      <div className="p-4 border-t border-slate-800 bg-[#0a0f1d] text-center">
        <div className="flex items-center justify-center gap-2 text-xs text-slate-400">
          <span className="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-ping"></span>
          <span className="font-semibold uppercase tracking-wider text-[10px]">Operations Online</span>
        </div>
        <p className="text-[10px] text-slate-500 mt-1.5 font-mono">UTC: 2026-07-06</p>
      </div>
    </div>
  );
}
