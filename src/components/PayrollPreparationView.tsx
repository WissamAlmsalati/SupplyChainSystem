import React, { useState, useEffect } from 'react';
import {
  Coins,
  Search,
  Filter,
  CheckCircle,
  FileSpreadsheet,
  Plus,
  X,
  Edit,
  DollarSign
} from 'lucide-react';
import { Employee, Deployment } from '../types';
import { usePagination } from '../hooks/usePagination';
import Pagination from './Pagination';

interface PayrollPreparationViewProps {
  employees: Employee[];
  deployments: Deployment[];
}

export default function PayrollPreparationView({ employees, deployments }: PayrollPreparationViewProps) {
  const [searchQuery, setSearchQuery] = useState('');
  const [monthFilter, setMonthFilter] = useState('2026-07');
  const [selectedAdjustEmp, setSelectedAdjustEmp] = useState<Employee | null>(null);
  const formatEmployeeValue = (val: number, currencyParam?: 'USD' | 'LYD') => {
    const cur = currencyParam || 'USD';
    if (cur === 'LYD') {
      return `${val.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} LYD`;
    }
    return `$${val.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 })} USD`;
  };

  const formatAggregate = (usdVal: number, lydVal: number) => {
    const parts = [];
    if (usdVal > 0 || lydVal === 0) {
      parts.push(`$${usdVal.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 })} USD`);
    }
    if (lydVal > 0) {
      parts.push(`${lydVal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} LYD`);
    }
    return parts.join(' / ');
  };

  // Allowances & Adjustments State Store mapped to employee IDs
  const [allowances, setAllowances] = useState<Record<string, { allowance: number; bonus: number; adjustment: number; reason: string }>>({
    'emp-1': { allowance: 400, bonus: 250, adjustment: -100, reason: 'Safety training fee rebate' },
    'emp-2': { allowance: 350, bonus: 500, adjustment: 0, reason: 'Field extension bonus' }
  });

  const [adjustForm, setAdjustForm] = useState({
    allowance: 0,
    bonus: 0,
    adjustment: 0,
    reason: ''
  });

  const [downloadSuccessAlert, setDownloadSuccessAlert] = useState(false);

  // Compute Days worked and Gross Payroll
  const payrollRecords = employees
    .filter(emp => {
      const fullName = `${emp.first_name} ${emp.last_name}`.toLowerCase();
      const code = emp.employee_code.toLowerCase();
      return fullName.includes(searchQuery.toLowerCase()) || code.includes(searchQuery.toLowerCase());
    })
    .map(emp => {
      // Find deployments in July 2026
      // For simplicity, let's assume if they are active, they work 28 days this month
      // if on leave, they work 14 days, and if standby/active, they work 20 days.
      let daysWorked = 20;
      if (emp.status === 'deployed') daysWorked = 28;
      if (emp.status === 'on_leave') daysWorked = 10;

      const baseSalary = daysWorked * emp.daily_rate;
      const extras = allowances[emp.id] || { allowance: 0, bonus: 0, adjustment: 0, reason: '' };

      const totalPayroll = baseSalary + extras.allowance + extras.bonus + extras.adjustment;

      return {
        emp,
        daysWorked,
        baseSalary,
        allowance: extras.allowance,
        bonus: extras.bonus,
        adjustment: extras.adjustment,
        reason: extras.reason,
        totalPayroll
      };
    });

  const { page, setPage, paginatedItems, totalPages, pageSize, totalItems } = usePagination(payrollRecords, 10);

  useEffect(() => {
    setPage(1);
  }, [searchQuery, monthFilter]);

  // Calculate high-level payroll aggregate statistics separated by USD and LYD
  const totalBaseUSD = payrollRecords.filter(r => (r.emp.currency || 'USD') === 'USD').reduce((acc, curr) => acc + curr.baseSalary, 0);
  const totalBaseLYD = payrollRecords.filter(r => (r.emp.currency || 'USD') === 'LYD').reduce((acc, curr) => acc + curr.baseSalary, 0);

  const totalAllowancesUSD = payrollRecords.filter(r => (r.emp.currency || 'USD') === 'USD').reduce((acc, curr) => acc + curr.allowance, 0);
  const totalAllowancesLYD = payrollRecords.filter(r => (r.emp.currency || 'USD') === 'LYD').reduce((acc, curr) => acc + curr.allowance, 0);

  const totalBonusesUSD = payrollRecords.filter(r => (r.emp.currency || 'USD') === 'USD').reduce((acc, curr) => acc + curr.bonus, 0);
  const totalBonusesLYD = payrollRecords.filter(r => (r.emp.currency || 'USD') === 'LYD').reduce((acc, curr) => acc + curr.bonus, 0);

  const totalAdjustmentsUSD = payrollRecords.filter(r => (r.emp.currency || 'USD') === 'USD').reduce((acc, curr) => acc + curr.adjustment, 0);
  const totalAdjustmentsLYD = payrollRecords.filter(r => (r.emp.currency || 'USD') === 'LYD').reduce((acc, curr) => acc + curr.adjustment, 0);

  const totalNetUSD = payrollRecords.filter(r => (r.emp.currency || 'USD') === 'USD').reduce((acc, curr) => acc + curr.totalPayroll, 0);
  const totalNetLYD = payrollRecords.filter(r => (r.emp.currency || 'USD') === 'LYD').reduce((acc, curr) => acc + curr.totalPayroll, 0);

  const handleAdjustSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedAdjustEmp) return;

    setAllowances(prev => ({
      ...prev,
      [selectedAdjustEmp.id]: {
        allowance: Number(adjustForm.allowance),
        bonus: Number(adjustForm.bonus),
        adjustment: Number(adjustForm.adjustment),
        reason: adjustForm.reason
      }
    }));

    setSelectedAdjustEmp(null);
  };

  const csvEscape = (val: string | number) => {
    const str = String(val ?? '');
    if (str.includes(',') || str.includes('"') || str.includes('\n')) {
      return `"${str.replace(/"/g, '""')}"`;
    }
    return str;
  };

  const handleExport = () => {
    const headers = [
      'Employee Code', 'Name', 'Currency', 'Days Worked', 'Daily Rate',
      'Base Salary', 'Allowance', 'Bonus', 'Adjustment', 'Reason', 'Net Compensation'
    ];
    const rows = payrollRecords.map(r => [
      r.emp.employee_code,
      `${r.emp.first_name} ${r.emp.last_name}`,
      r.emp.currency || 'USD',
      r.daysWorked,
      r.emp.daily_rate,
      r.baseSalary,
      r.allowance,
      r.bonus,
      r.adjustment,
      r.reason || '',
      r.totalPayroll
    ]);

    const csv = [headers, ...rows]
      .map(row => row.map(csvEscape).join(','))
      .join('\n');

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `TAQA_Payroll_${monthFilter}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);

    setDownloadSuccessAlert(true);
    setTimeout(() => setDownloadSuccessAlert(false), 4000);
  };

  return (
    <div className="p-8 space-y-8 max-w-7xl mx-auto" id="payroll-desk-wrapper">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 className="text-xl font-bold text-white tracking-tight uppercase">Payroll Preparation Sheet</h2>
          <p className="text-sm text-slate-400 mt-1">Pre-computed allowances, work logs, and gross deductions for monthly accounting</p>
        </div>

        <button
          onClick={handleExport}
          className="bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold px-4 py-2.5 rounded-lg text-sm flex items-center gap-2 cursor-pointer self-start md:self-auto"
        >
          <FileSpreadsheet className="w-4 h-4" /> Export CSV Ledger
        </button>
      </div>

      {/* Aggregate Cost widgets */}
      <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
        {[
          { label: 'Calculated Base Outlay', formattedValue: formatAggregate(totalBaseUSD, totalBaseLYD), color: 'text-slate-300' },
          { label: 'Allowance Outlay', formattedValue: formatAggregate(totalAllowancesUSD, totalAllowancesLYD), color: 'text-blue-400' },
          { label: 'Discretionary Bonuses', formattedValue: formatAggregate(totalBonusesUSD, totalBonusesLYD), color: 'text-cyan-400' },
          { label: 'Manual Deductions', formattedValue: formatAggregate(totalAdjustmentsUSD, totalAdjustmentsLYD), color: 'text-rose-400' },
          { label: 'Total Net Payroll Cost', formattedValue: formatAggregate(totalNetUSD, totalNetLYD), color: 'text-emerald-400', isAccent: true }
        ].map((item, idx) => (
          <div key={idx} className={`bg-[#0e1626] border border-slate-800 p-5 rounded-xl shadow-md ${
            item.isAccent ? 'relative overflow-hidden ring-1 ring-emerald-500/20' : ''
          }`}>
            <span className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">{item.label}</span>
            <span className={`block text-[11px] font-bold font-mono mt-3 ${item.color}`}>
              {item.formattedValue}
            </span>
          </div>
        ))}
      </div>

      {/* Main Payroll sheet table */}
      <div className="bg-[#0e1626] border border-slate-800 rounded-xl p-6 shadow-xl space-y-6">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-800 pb-4">
          <h3 className="font-bold text-slate-200 text-sm uppercase tracking-wider">Payroll Computation Ledger</h3>
          
          <div className="flex flex-wrap items-center gap-3">
            <div className="relative">
              <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-500" />
              <input
                type="text"
                placeholder="Search ledger..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="bg-[#111827] border border-slate-850 rounded-lg pl-8 pr-3 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500"
              />
            </div>

            <select
              value={monthFilter}
              onChange={(e) => setMonthFilter(e.target.value)}
              className="bg-[#111827] border border-slate-850 rounded-lg py-1.5 px-2 text-xs text-slate-300"
            >
              <option value="2026-07">July 2026</option>
              <option value="2026-08">August 2026</option>
            </select>
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs border-collapse">
            <thead>
              <tr className="border-b border-slate-800 text-slate-400 font-bold uppercase tracking-wider">
                <th className="py-3 px-2">Workforce Member</th>
                <th className="py-3 px-2">Shift Days worked</th>
                {/* TARGETED COLUMN HEADER */}
                <th className="py-3 px-2 font-mono text-emerald-400 font-bold tracking-wide border-b-2 border-emerald-500/35 bg-emerald-500/5 transition-all duration-300 rounded-t-md">
                  Daily Rate
                </th>
                <th className="py-3 px-2 font-mono">Gross Base</th>
                <th className="py-3 px-2 font-mono">Allowances</th>
                <th className="py-3 px-2 font-mono">Bonuses</th>
                <th className="py-3 px-2 font-mono">Adjustments</th>
                <th className="py-3 px-2 font-mono text-right">Net Compensation</th>
                <th className="py-3 px-2 text-right">Adjustment</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800/40">
              {paginatedItems.map(({ emp, daysWorked, baseSalary, allowance, bonus, adjustment, reason, totalPayroll }) => (
                <tr key={emp.id} className="hover:bg-slate-800/10 transition-colors">
                  <td className="py-3.5 px-2">
                    <span className="font-bold text-slate-200 block">{emp.first_name} {emp.last_name}</span>
                    <span className="text-[10px] text-slate-500 font-mono mt-0.5">{emp.employee_code}</span>
                  </td>
                  <td className="py-3.5 px-2 font-semibold text-slate-300">
                    {daysWorked} Days
                  </td>
                  <td className="py-3.5 px-2 font-mono text-slate-200 bg-emerald-500/5 font-semibold">
                    {formatEmployeeValue(emp.daily_rate, emp.currency)}
                  </td>
                  <td className="py-3.5 px-2 font-mono text-slate-300">
                    {formatEmployeeValue(baseSalary, emp.currency)}
                  </td>
                  <td className="py-3.5 px-2 font-mono text-blue-400">
                    +{formatEmployeeValue(allowance, emp.currency)}
                  </td>
                  <td className="py-3.5 px-2 font-mono text-cyan-400">
                    +{formatEmployeeValue(bonus, emp.currency)}
                  </td>
                  <td className="py-3.5 px-2 font-mono text-rose-400">
                    {adjustment < 0 ? '-' : '+'}{formatEmployeeValue(Math.abs(adjustment), emp.currency)}
                  </td>
                  <td className="py-3.5 px-2 font-mono font-bold text-emerald-400 text-right text-sm">
                    {formatEmployeeValue(totalPayroll, emp.currency)}
                  </td>
                  <td className="py-3.5 px-2 text-right">
                    <button
                      onClick={() => {
                        setSelectedAdjustEmp(emp);
                        const extras = allowances[emp.id] || { allowance: 0, bonus: 0, adjustment: 0, reason: '' };
                        setAdjustForm(extras);
                      }}
                      className="text-slate-400 hover:text-emerald-400 p-1 bg-slate-900 rounded border border-slate-800 hover:border-emerald-500/20 transition-all cursor-pointer"
                      title="Adjust figures"
                    >
                      <Edit className="w-3.5 h-3.5" />
                    </button>
                  </td>
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

      {/* EXCEL EXPORT CONSOLE SIMULATED DOWNLOAD NOTIFICATION */}
      {downloadSuccessAlert && (
        <div className="fixed bottom-6 right-6 bg-[#0e1626] border border-emerald-500/30 text-slate-100 p-4 rounded-xl shadow-2xl flex items-center gap-3 max-w-sm z-50 animate-bounce">
          <CheckCircle className="w-6 h-6 text-emerald-400 shrink-0" />
          <div>
            <span className="font-bold text-xs uppercase text-emerald-400 tracking-wider">Payroll exported</span>
            <p className="text-[11px] text-slate-400 mt-1">Exported ledger for {monthFilter} successfully saved as `TAQA_Payroll_{monthFilter}.csv`.</p>
          </div>
        </div>
      )}

      {/* ADJUST PAYROLL VALUES POP */}
      {selectedAdjustEmp && (
        <div className="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 z-50">
          <div className="bg-[#0d1424] border border-slate-800 rounded-xl w-full max-w-sm shadow-2xl">
            <div className="p-5 border-b border-slate-800 flex justify-between items-center bg-[#0a0f1d]">
              <h3 className="font-bold text-slate-100 text-sm">Adjust Payroll Figures</h3>
              <button onClick={() => setSelectedAdjustEmp(null)} className="text-slate-400 hover:text-white cursor-pointer">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleAdjustSubmit} className="p-5 space-y-4 text-xs text-slate-300">
              <div className="bg-slate-900/40 p-3 rounded-lg mb-2">
                <span className="text-slate-500 text-[10px] uppercase">Employee</span>
                <span className="block font-bold text-slate-200 text-sm">{selectedAdjustEmp.first_name} {selectedAdjustEmp.last_name}</span>
                <span className="block text-[10px] text-slate-500 font-mono mt-0.5">{selectedAdjustEmp.employee_code}</span>
              </div>

              <div>
                <label className="block mb-1 text-slate-400">Regular Field Allowance ({selectedAdjustEmp.currency || 'USD'})</label>
                <input
                  type="number"
                  value={adjustForm.allowance}
                  onChange={(e) => setAdjustForm({ ...adjustForm, allowance: Number(e.target.value) })}
                  className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white font-mono"
                />
              </div>

              <div>
                <label className="block mb-1 text-slate-400">Discretionary Bonus ({selectedAdjustEmp.currency || 'USD'})</label>
                <input
                  type="number"
                  value={adjustForm.bonus}
                  onChange={(e) => setAdjustForm({ ...adjustForm, bonus: Number(e.target.value) })}
                  className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white font-mono"
                />
              </div>

              <div>
                <label className="block mb-1 text-slate-400">Manual Deductions / Fine adjustment ({selectedAdjustEmp.currency || 'USD'})</label>
                <input
                  type="number"
                  value={adjustForm.adjustment}
                  onChange={(e) => setAdjustForm({ ...adjustForm, adjustment: Number(e.target.value) })}
                  className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white font-mono"
                />
              </div>

              <div>
                <label className="block mb-1 text-slate-400">Audit / Adjustments reasoning</label>
                <input
                  type="text"
                  value={adjustForm.reason}
                  onChange={(e) => setAdjustForm({ ...adjustForm, reason: e.target.value })}
                  className="w-full bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-white"
                  placeholder="Reason for adjustment..."
                />
              </div>

              <div className="pt-4 flex justify-end gap-2 border-t border-slate-800">
                <button
                  type="button"
                  onClick={() => setSelectedAdjustEmp(null)}
                  className="bg-slate-800 hover:bg-slate-700 text-slate-200 px-4 py-2 rounded-lg cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold px-6 py-2 rounded-lg cursor-pointer"
                >
                  Save Adjustments
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

    </div>
  );
}
