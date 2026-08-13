import React, { useState, useEffect } from 'react';
import { RefreshCw } from 'lucide-react';
import Sidebar from './components/Sidebar';
import DashboardView from './components/DashboardView';
import EmployeesView from './components/EmployeesView';
import DocumentComplianceView from './components/DocumentComplianceView';
import CertificateComplianceView from './components/CertificateComplianceView';
import OperationsCrewView from './components/OperationsCrewView';
import BackToBackPlanningView from './components/BackToBackPlanningView';
import PerformanceEvaluationView from './components/PerformanceEvaluationView';
import PayrollPreparationView from './components/PayrollPreparationView';
import SettingsView from './components/SettingsView';
import AIAssistantView from './components/AIAssistantView';

import {
  Employee,
  Client,
  Field,
  Project,
  Department,
  Position,
  Document,
  DocumentType,
  Certificate,
  CertificateType,
  Deployment,
  BackToBackPair,
  LeaveRecord,
  LeaveType,
  Evaluation,
  EquipmentAssignment,
  EquipmentType,
  EmployeeNote,
  EmployeeAttachment,
  ActivityLog,
  AIRecommendation,
  EmergencyContact
} from './types';

export default function App() {
  const [currentView, setCurrentView] = useState<string>('dashboard');
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [errorState, setErrorState] = useState<string | null>(null);

  // Universal Action state parameters passed between screens
  const [viewActionState, setViewActionState] = useState<any>(null);

  // -------------------------------------------------------------
  // MASTER STATES STORE
  // -------------------------------------------------------------
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [clients, setClients] = useState<Client[]>([]);
  const [fields, setFields] = useState<Field[]>([]);
  const [projects, setProjects] = useState<Project[]>([]);
  const [departments, setDepartments] = useState<Department[]>([]);
  const [positions, setPositions] = useState<Position[]>([]);
  const [docTypes, setDocTypes] = useState<DocumentType[]>([]);
  const [certTypes, setCertificateTypes] = useState<CertificateType[]>([]);
  const [leaveTypes, setLeaveTypes] = useState<LeaveType[]>([]);
  const [equipmentTypes, setEquipmentTypes] = useState<EquipmentType[]>([]);
  
  const [documents, setDocuments] = useState<Document[]>([]);
  const [certificates, setCertificates] = useState<Certificate[]>([]);
  const [deployments, setDeployments] = useState<Deployment[]>([]);
  const [backToBackPairs, setBackToBackPairs] = useState<BackToBackPair[]>([]);
  const [leaveRecords, setLeaveRecords] = useState<LeaveRecord[]>([]);
  const [evaluations, setEvaluations] = useState<Evaluation[]>([]);
  const [equipmentAssignments, setEquipmentAssignments] = useState<EquipmentAssignment[]>([]);
  const [notes, setNotes] = useState<EmployeeNote[]>([]);
  const [attachments, setAttachments] = useState<EmployeeAttachment[]>([]);
  const [emergencyContacts, setEmergencyContacts] = useState<EmergencyContact[]>([]);
  const [activityLogs, setActivityLogs] = useState<ActivityLog[]>([]);
  const [recommendations, setRecommendations] = useState<AIRecommendation[]>([]);

  // -------------------------------------------------------------
  // BOOTSTRAP DATA INITIALIZATION
  // -------------------------------------------------------------
  const bootstrapData = async () => {
    try {
      setIsLoading(true);
      const res = await fetch('/api/bootstrap');
      const data = await res.json();
      
      setEmployees(data.employees || []);
      setClients(data.clients || []);
      setFields(data.fields || []);
      setProjects(data.projects || []);
      setDepartments(data.departments || []);
      setPositions(data.positions || []);
      setDocTypes(data.document_types || []);
      setCertificateTypes(data.certificate_types || []);
      setLeaveTypes(data.leave_types || []);
      setEquipmentTypes(data.equipment_types || []);
      
      setDocuments(data.documents || []);
      setCertificates(data.certificates || []);
      setDeployments(data.deployments || []);
      setBackToBackPairs(data.back_to_back_pairs || []);
      setLeaveRecords(data.leave_records || []);
      setEvaluations(data.evaluations || []);
      setEquipmentAssignments(data.equipment_assignments || []);
      setNotes(data.notes || []);
      setAttachments(data.attachments || []);
      setEmergencyContacts(data.emergency_contacts || []);
      setActivityLogs(data.activity_log || []);
      setRecommendations(data.ai_recommendations || []);
      
      setErrorState(null);
    } catch (err) {
      console.error('Bootstrap failure:', err);
      setErrorState('Operational backend unreachable. Ensure port 3000 bindings are healthy.');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    bootstrapData();
  }, []);

  // -------------------------------------------------------------
  // CALLBACK HANDLERS Connected to backend API routes
  // -------------------------------------------------------------

  // View Navigation Helper with parameter parsing
  const handleNavigate = (view: string, actionState?: any) => {
    setViewActionState(actionState || null);
    setCurrentView(view);
  };

  // 1. Employee Profiles Master CRUD
  const handleAddEmployee = async (empData: any) => {
    const res = await fetch('/api/employees', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(empData)
    });
    const newItem = await res.json();
    await bootstrapData();
    return newItem;
  };

  const handleEditEmployee = async (id: string, empData: any) => {
    const res = await fetch(`/api/employees/${id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(empData)
    });
    const updated = await res.json();
    await bootstrapData();
    return updated;
  };

  const handleDeleteEmployee = async (id: string) => {
    await fetch(`/api/employees/${id}`, { method: 'DELETE' });
    await bootstrapData();
  };

  // 2. Personal Documents uploads
  const handleAddDocument = async (docData: any) => {
    const res = await fetch('/api/documents', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(docData)
    });
    const newItem = await res.json();
    await bootstrapData();
    return newItem;
  };

  const handleDeleteDocument = async (id: string) => {
    await fetch(`/api/documents/${id}`, { method: 'DELETE' });
    await bootstrapData();
  };

  // 3. Safety Certifications matrix registering
  const handleAddCertificate = async (certData: any) => {
    const res = await fetch('/api/certificates', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(certData)
    });
    const newItem = await res.json();
    await bootstrapData();
    return newItem;
  };

  const handleDeleteCertificate = async (id: string) => {
    await fetch(`/api/certificates/${id}`, { method: 'DELETE' });
    await bootstrapData();
  };

  // 4. Operations Deployments schedules
  const handleAddDeployment = async (depData: any) => {
    const res = await fetch('/api/deployments', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(depData)
    });
    const newItem = await res.json();
    await bootstrapData();
    return newItem;
  };

  const handleEndDeployment = async (id: string, endDate: string) => {
    const res = await fetch(`/api/deployments/${id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ end_date: endDate, status: 'completed' })
    });
    const updated = await res.json();
    await bootstrapData();
    return updated;
  };

  // 5. Back-to-Back links pair relief
  const handleLinkB2B = async (employeeId: string, partnerId: string) => {
    // Find active deployment for employeeId
    const activeDep = deployments.find(d => d.employee_id === employeeId && d.status === 'active');
    if (!activeDep) {
      alert('Relief linking can only be aligned to active deployed rotations!');
      return;
    }
    
    // Find B2b pair corresponding to activeDep
    const matchedB = backToBackPairs.find(p => p.deployment_id === activeDep.id);
    if (matchedB) {
      const res = await fetch(`/api/b2b/${matchedB.id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ replacement_employee_id: partnerId, status: 'confirmed' })
      });
      const updated = await res.json();
      await bootstrapData();
      return updated;
    } else {
      // Direct assignment fallback
      const res = await fetch(`/api/deployments/${activeDep.id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ b2b_partner_employee_id: partnerId })
      });
      const updated = await res.json();
      await bootstrapData();
      return updated;
    }
  };

  // 6. Field appraisals digital filing
  const handleAddEvaluation = async (evalData: any) => {
    const res = await fetch('/api/evaluations', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        ...evalData,
        template_id: 'ev-t-1',
        item_scores: {
          'evi-1': Math.round(evalData.competency_score / 20),
          'evi-2': Math.round(evalData.hse_score / 20),
          'evi-3': Math.round(evalData.training_score / 20),
          'evi-4': Math.round(evalData.attitude_score / 20)
        }
      })
    });
    const newItem = await res.json();
    await bootstrapData();
    return newItem;
  };

  // 7. Issued gear assignments
  const handleAddEquipment = async (eqData: any) => {
    const res = await fetch('/api/equipment', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(eqData)
    });
    const newItem = await res.json();
    await bootstrapData();
    return newItem;
  };

  const handleReturnEquipment = async (id: string) => {
    const todayStr = new Date().toISOString().split('T')[0];
    const res = await fetch(`/api/equipment/${id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ returned_date: todayStr })
    });
    const updated = await res.json();
    await bootstrapData();
    return updated;
  };

  // 8. Lookup settings catalogs CRUD
  const handleAddSetting = async (table: string, data: any) => {
    const res = await fetch(`/api/settings/${table}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    const newItem = await res.json();
    await bootstrapData();
    return newItem;
  };

  const handleDeleteSetting = async (table: string, id: string) => {
    await fetch(`/api/settings/${table}/${id}`, { method: 'DELETE' });
    await bootstrapData();
  };

  // 9. Employee Notes additions
  const handleAddNote = async (noteData: any) => {
    const res = await fetch('/api/notes', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(noteData)
    });
    const newItem = await res.json();
    await bootstrapData();
    return newItem;
  };

  const handleDeleteNote = async (id: string) => {
    await fetch(`/api/notes/${id}`, { method: 'DELETE' });
    await bootstrapData();
  };

  // 10. Attachments uploads
  const handleAddAttachment = async (attachData: any) => {
    const res = await fetch('/api/attachments', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(attachData)
    });
    const newItem = await res.json();
    await bootstrapData();
    return newItem;
  };

  const handleDeleteAttachment = async (id: string) => {
    await fetch(`/api/attachments/${id}`, { method: 'DELETE' });
    await bootstrapData();
  };

  // 11. AI Diagnostics Scan & apply anomalous exceptions
  const handleTriggerScan = async () => {
    await fetch('/api/ai/scan', { method: 'POST' });
    await bootstrapData();
  };

  const handleUpdateRecStatus = async (id: string, status: string) => {
    await fetch(`/api/ai/recommendations/${id}/status`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ status })
    });
    await bootstrapData();
  };

  return (
    <div className="flex h-screen overflow-hidden bg-[#070b14]" id="taqa-applet-root">
      {/* Sidebar Navigation Panel */}
      <Sidebar
        currentView={currentView}
        onViewChange={(view) => handleNavigate(view)}
        recommendations={recommendations}
      />

      {/* Main scrolling viewport container */}
      <div className="flex-1 flex flex-col h-full overflow-hidden">
        
        {/* Simple top brand and breadcrumb header */}
        <header className="h-16 border-b border-slate-800/60 bg-[#0c1424] px-8 flex items-center justify-between shrink-0">
          <div className="flex items-center gap-2 text-xs font-semibold text-slate-400 uppercase tracking-wider">
            <span>Operational Area</span>
            <span className="text-slate-600">/</span>
            <span className="text-slate-200">{currentView.replace('-', ' ')}</span>
          </div>
          
          <div className="flex items-center gap-4 text-xs font-mono text-slate-500">
            <span>ADMINISTRATOR CONSOLE</span>
            <span>•</span>
            <span className="text-emerald-500 font-bold">DATABASE CONNECTED</span>
          </div>
        </header>

        {/* Display Loading status if bootstrapping */}
        {isLoading ? (
          <div className="flex-1 flex flex-col items-center justify-center text-slate-400 gap-3">
            <RefreshCw className="w-8 h-8 text-emerald-500 animate-spin" />
            <p className="text-xs uppercase tracking-wider font-mono">Synchronizing live operations logs...</p>
          </div>
        ) : errorState ? (
          <div className="flex-1 flex flex-col items-center justify-center text-rose-400 p-6 text-center max-w-md mx-auto gap-3">
            <RefreshCw className="w-10 h-10 text-rose-500 animate-spin" />
            <h3 className="font-bold uppercase tracking-wider text-sm">System Alignment Exception</h3>
            <p className="text-xs text-slate-400 leading-relaxed">{errorState}</p>
          </div>
        ) : (
          <main className="flex-1 overflow-y-auto">
            
            {/* View switching logic routes */}
            {currentView === 'dashboard' && (
              <DashboardView
                employees={employees}
                deployments={deployments}
                documents={documents}
                certificates={certificates}
                activityLogs={activityLogs}
                recommendations={recommendations}
                clients={clients}
                fields={fields}
                certTypes={certTypes}
                docTypes={docTypes}
                onNavigate={handleNavigate}
              />
            )}

            {currentView === 'employees' && (
              <EmployeesView
                employees={employees}
                departments={departments}
                positions={positions}
                emergencyContacts={emergencyContacts}
                documents={documents}
                docTypes={docTypes}
                certificates={certificates}
                certTypes={certTypes}
                deployments={deployments}
                leaveRecords={leaveRecords}
                leaveTypes={leaveTypes}
                evaluations={evaluations}
                equipmentAssignments={equipmentAssignments}
                equipmentTypes={equipmentTypes}
                notes={notes}
                attachments={attachments}
                clients={clients}
                fields={fields}
                projects={projects}
                
                onAddEmployee={handleAddEmployee}
                onEditEmployee={handleEditEmployee}
                onDeleteEmployee={handleDeleteEmployee}
                onAddDocument={handleAddDocument}
                onDeleteDocument={handleDeleteDocument}
                onAddCertificate={handleAddCertificate}
                onDeleteCertificate={handleDeleteCertificate}
                onAddNote={handleAddNote}
                onDeleteNote={handleDeleteNote}
                onAddAttachment={handleAddAttachment}
                onDeleteAttachment={handleDeleteAttachment}
                onAddEquipment={handleAddEquipment}
                onReturnEquipment={handleReturnEquipment}

                initialActionState={viewActionState}
              />
            )}

            {currentView === 'documents' && (
              <DocumentComplianceView
                employees={employees}
                documents={documents}
                docTypes={docTypes}
                onAddDocument={handleAddDocument}
                onDeleteDocument={handleDeleteDocument}
                openUploadDocOnInit={viewActionState?.openUploadDoc}
              />
            )}

            {currentView === 'certificates' && (
              <CertificateComplianceView
                employees={employees}
                certificates={certificates}
                certTypes={certTypes}
                onAddCertificate={handleAddCertificate}
                onDeleteCertificate={handleDeleteCertificate}
              />
            )}

            {currentView === 'operations' && (
              <OperationsCrewView
                employees={employees}
                deployments={deployments}
                clients={clients}
                fields={fields}
                projects={projects}
                onAddDeployment={handleAddDeployment}
                onEndDeployment={handleEndDeployment}
                openNewDeploymentOnInit={viewActionState?.openNewDeployment}
              />
            )}

            {currentView === 'b2b' && (
              <BackToBackPlanningView
                employees={employees}
                deployments={deployments}
                clients={clients}
                fields={fields}
                onLinkB2B={handleLinkB2B}
              />
            )}

            {currentView === 'evaluations' && (
              <PerformanceEvaluationView
                employees={employees}
                evaluations={evaluations}
                onAddEvaluation={handleAddEvaluation}
                openNewAppraisalOnInit={viewActionState?.openNewAppraisal}
              />
            )}

            {currentView === 'payroll' && (
              <PayrollPreparationView
                employees={employees}
                deployments={deployments}
              />
            )}

            {currentView === 'ai-assistant' && (
              <AIAssistantView
                recommendations={recommendations}
                onTriggerScan={handleTriggerScan}
                onUpdateRecStatus={handleUpdateRecStatus}
                highlightRecId={viewActionState?.highlightRecId}
              />
            )}

            {currentView === 'settings' && (
              <SettingsView
                clients={clients}
                fields={fields}
                departments={departments}
                positions={positions}
                certTypes={certTypes}
                docTypes={docTypes}
                leaveTypes={leaveTypes}
                equipmentTypes={equipmentTypes}
                onAddSetting={handleAddSetting}
                onDeleteSetting={handleDeleteSetting}
              />
            )}

          </main>
        )}
      </div>
    </div>
  );
}
