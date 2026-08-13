import React, { useState, useEffect } from 'react';
import {
  Settings,
  Plus,
  Trash2,
  Check,
  Edit2,
  Tag,
  Building2,
  Compass,
  Award,
  FileText,
  User,
  HardDrive
} from 'lucide-react';
import {
  Client,
  Field,
  Department,
  Position,
  CertificateType,
  DocumentType,
  LeaveType,
  EquipmentType
} from '../types';
import { usePagination } from '../hooks/usePagination';
import Pagination from './Pagination';

interface SettingsViewProps {
  clients: Client[];
  fields: Field[];
  departments: Department[];
  positions: Position[];
  certTypes: CertificateType[];
  docTypes: DocumentType[];
  leaveTypes: LeaveType[];
  equipmentTypes: EquipmentType[];

  onAddSetting: (table: string, data: any) => Promise<any>;
  onDeleteSetting: (table: string, id: string) => Promise<any>;
}

export default function SettingsView({
  clients,
  fields,
  departments,
  positions,
  certTypes,
  docTypes,
  leaveTypes,
  equipmentTypes,
  onAddSetting,
  onDeleteSetting
}: SettingsViewProps) {
  const [activeTable, setActiveTable] = useState('clients');
  const [newItemName, setNewItemName] = useState('');
  const [newItemParentId, setNewItemParentId] = useState('');

  const settingMenus = [
    { id: 'clients', label: 'Client Accounts', icon: Building2, data: clients },
    { id: 'fields', label: 'Oilfield Wellsites', icon: Compass, data: fields },
    { id: 'departments', label: 'Departments', icon: User, data: departments },
    { id: 'positions', label: 'Crew Positions', icon: Tag, data: positions },
    { id: 'certificate_types', label: 'Certificate Types', icon: Award, data: certTypes },
    { id: 'document_types', label: 'Document Classifications', icon: FileText, data: docTypes },
    { id: 'leave_types', label: 'Off-Rotation Leave Types', icon: Tag, data: leaveTypes },
    { id: 'equipment_types', label: 'Industrial Gear Types', icon: HardDrive, data: equipmentTypes }
  ];

  const currentMenu = settingMenus.find(m => m.id === activeTable) || settingMenus[0];
  const { page, setPage, paginatedItems, totalPages, pageSize, totalItems } = usePagination(currentMenu.data as any[], 10);

  useEffect(() => {
    setPage(1);
  }, [activeTable]);

  const handleAddSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newItemName.trim()) return;

    const payload: any = { name: newItemName };
    if (activeTable === 'positions') {
      if (!newItemParentId) return;
      payload.department_id = newItemParentId;
    }
    if (activeTable === 'fields') {
      if (!newItemParentId) return;
      payload.client_id = newItemParentId;
    }

    await onAddSetting(activeTable, payload);
    setNewItemName('');
    setNewItemParentId('');
  };

  const handleDeleteClick = async (id: string) => {
    if (confirm(`Are you sure you want to delete this setting item from the catalog? This could impact historical relations.`)) {
      await onDeleteSetting(activeTable, id);
    }
  };

  return (
    <div className="p-8 space-y-8 max-w-7xl mx-auto" id="settings-desk-wrapper">
      {/* Header */}
      <div>
        <h2 className="text-xl font-bold text-white tracking-tight uppercase">Global Settings Catalog</h2>
        <p className="text-sm text-slate-400 mt-1">Manage static lookup catalogs, oilfield configurations, and mandatory positions</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-4 gap-8">
        
        {/* Left column menu list */}
        <div className="bg-[#0e1626] border border-slate-800 p-4 rounded-xl shadow-lg h-fit space-y-1">
          <h3 className="font-bold text-slate-400 text-[10px] uppercase tracking-wider px-3 mb-3">Lookup Categories</h3>
          
          {settingMenus.map((menu) => {
            const Icon = menu.icon;
            const isActive = activeTable === menu.id;
            return (
              <button
                key={menu.id}
                onClick={() => {
                  setActiveTable(menu.id);
                  setNewItemName('');
                  setNewItemParentId('');
                }}
                className={`w-full flex items-center gap-3 px-3.5 py-3 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all ${
                  isActive
                    ? 'bg-emerald-500/10 text-emerald-400 border-l-4 border-emerald-500'
                    : 'text-slate-300 hover:bg-slate-800/40 hover:text-white'
                }`}
              >
                <Icon className={`w-4 h-4 ${isActive ? 'text-emerald-400' : 'text-slate-400'}`} />
                <span>{menu.label}</span>
              </button>
            );
          })}
        </div>

        {/* Right column CRUD visualizer */}
        <div className="md:col-span-3 bg-[#0e1626] border border-slate-800 p-6 rounded-xl shadow-lg space-y-6">
          <div className="border-b border-slate-800 pb-4">
            <h3 className="font-bold text-slate-100 text-sm uppercase tracking-wider">{currentMenu.label} Catalog</h3>
            <p className="text-xs text-slate-400 mt-1">Add, review, or deprecate lookup listings in active database tables.</p>
          </div>

          {/* Quick inline entry form */}
          <form onSubmit={handleAddSubmit} className="flex flex-col gap-3 max-w-md">
            <div className="flex gap-2">
              <input
                type="text"
                placeholder={`Enter new ${currentMenu.label.toLowerCase()}...`}
                value={newItemName}
                onChange={(e) => setNewItemName(e.target.value)}
                required
                className="bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500 flex-1"
              />
              <button
                type="submit"
                className="bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold px-4 py-2 rounded-lg text-xs flex items-center gap-1.5 cursor-pointer"
              >
                <Plus className="w-4 h-4" /> Add Item
              </button>
            </div>

            {activeTable === 'positions' && (
              <select
                value={newItemParentId}
                onChange={(e) => setNewItemParentId(e.target.value)}
                required
                className="bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500"
              >
                <option value="">Select department...</option>
                {departments.map((d) => (
                  <option key={d.id} value={d.id}>{d.name}</option>
                ))}
              </select>
            )}

            {activeTable === 'fields' && (
              <select
                value={newItemParentId}
                onChange={(e) => setNewItemParentId(e.target.value)}
                required
                className="bg-[#111827] border border-slate-800 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500"
              >
                <option value="">Select client...</option>
                {clients.map((c) => (
                  <option key={c.id} value={c.id}>{c.name}</option>
                ))}
              </select>
            )}
          </form>

          {/* Records table list */}
          <div className="overflow-x-auto max-h-[350px] overflow-y-auto border border-slate-800 rounded-xl">
            <table className="w-full text-left text-xs border-collapse">
              <thead>
                <tr className="bg-[#0b101c] border-b border-slate-800 text-slate-400 font-bold uppercase tracking-wider">
                  <th className="py-3 px-4">Item Name</th>
                  <th className="py-3 px-4">Internal Table Reference ID</th>
                  <th className="py-3 px-4 text-right">Delete</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/40">
                {paginatedItems.map((item: any) => (
                  <tr key={item.id} className="hover:bg-slate-800/10 transition-colors">
                    <td className="py-3 px-4 font-semibold text-slate-200">
                      {item.name}
                    </td>
                    <td className="py-3 px-4 font-mono text-slate-500">
                      {item.id}
                    </td>
                    <td className="py-3 px-4 text-right">
                      <button
                        onClick={() => handleDeleteClick(item.id)}
                        className="text-slate-500 hover:text-rose-400 p-1 cursor-pointer transition-colors"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </td>
                  </tr>
                ))}

                {currentMenu.data.length === 0 && (
                  <tr>
                    <td colSpan={3} className="py-6 text-center text-slate-500 text-xs italic">
                      This catalog table is currently empty. Add items using the box above.
                    </td>
                  </tr>
                )}
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

      </div>
    </div>
  );
}
