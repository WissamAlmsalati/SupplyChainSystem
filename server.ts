import express from 'express';
import path from 'path';
import fs from 'fs';
import { createServer as createViteServer } from 'vite';
import { GoogleGenAI } from '@google/genai';

const app = express();
const PORT = 3000;
const DB_PATH = path.join(process.cwd(), 'data', 'db.json');
const UPLOADS_DIR = path.join(process.cwd(), 'public', 'uploads');

// Ensure database file and uploads directory exist
if (!fs.existsSync(path.dirname(DB_PATH))) {
  fs.mkdirSync(path.dirname(DB_PATH), { recursive: true });
}
if (!fs.existsSync(UPLOADS_DIR)) {
  fs.mkdirSync(UPLOADS_DIR, { recursive: true });
}

// Request size limit for potential document uploads (base64)
app.use(express.json({ limit: '10mb' }));
app.use(express.urlencoded({ limit: '10mb', extended: true }));

// Helper functions for file-backed JSON database
function readDB(): any {
  try {
    if (!fs.existsSync(DB_PATH)) {
      return {};
    }
    const raw = fs.readFileSync(DB_PATH, 'utf-8');
    return JSON.parse(raw);
  } catch (error) {
    console.error('Error reading DB:', error);
    return {};
  }
}

function writeDB(data: any): void {
  try {
    fs.writeFileSync(DB_PATH, JSON.stringify(data, null, 2), 'utf-8');
  } catch (error) {
    console.error('Error writing DB:', error);
  }
}

// Global Activity Logging helper
function logActivity(entityType: string, entityId: string, action: string, description: string) {
  const db = readDB();
  const logs = db.activity_log || [];
  const newLog = {
    id: `ac-${Date.now()}`,
    entity_type: entityType,
    entity_id: entityId,
    action,
    description,
    created_at: new Date().toISOString()
  };
  db.activity_log = [newLog, ...logs].slice(0, 100); // keep last 100 logs
  writeDB(db);
}

// Expiry status calculation utility
function getExpiryStatus(expiryDateStr: string | undefined, reminderDays: number): 'valid' | 'expiring_soon' | 'expired' {
  if (!expiryDateStr) return 'valid';
  const expiry = new Date(expiryDateStr);
  const now = new Date();
  expiry.setHours(0, 0, 0, 0);
  now.setHours(0, 0, 0, 0);

  const diffTime = expiry.getTime() - now.getTime();
  const daysRemaining = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

  if (daysRemaining < 0) return 'expired';
  if (daysRemaining <= reminderDays) return 'expiring_soon';
  return 'valid';
}

// Automated DB Scanner / Diagnosis Engine
function runDatabaseDiagnostics(db: any): any {
  const recommendations: any[] = [];
  const employees = db.employees || [];
  const docs = db.documents || [];
  const certs = db.certificates || [];
  const deployments = db.deployments || [];
  const leave = db.leave_records || [];
  const b2b = db.back_to_back_pairs || [];
  const positions = db.positions || [];
  const posRequiredCerts = db.position_required_certificates || [];
  const certTypes = db.certificate_types || [];
  const docTypes = db.document_types || [];

  const now = new Date();
  now.setHours(0, 0, 0, 0);

  // 1. Check Passport & Driver License Expiry (from Employee Master)
  employees.forEach((emp: any) => {
    // Passport Expiry Check
    if (emp.passport_expiry_date) {
      const expDate = new Date(emp.passport_expiry_date);
      const diffDays = Math.ceil((expDate.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
      if (diffDays < 0) {
        recommendations.push({
          id: `rec-p-exp-${emp.id}`,
          employee_id: emp.id,
          category: 'expiring_cert',
          reason: `Passport for ${emp.first_name} ${emp.last_name} expired on ${emp.passport_expiry_date}.`,
          impact: 'HIGH: Employee cannot travel internationally or access oilfield sites requiring passport identification.',
          suggested_action: 'Initiate passport renewal sequence with HR government relations officer.',
          status: 'new',
          created_at: new Date().toISOString()
        });
      } else if (diffDays <= 90) {
        recommendations.push({
          id: `rec-p-warn-${emp.id}`,
          employee_id: emp.id,
          category: 'expiring_cert',
          reason: `Passport for ${emp.first_name} ${emp.last_name} expires in ${diffDays} days (${emp.passport_expiry_date}).`,
          impact: 'MEDIUM: Upcoming deployment eligibility may be compromised due to 6-month passport validity rules.',
          suggested_action: 'Alert employee to schedule passport renewal appointment.',
          status: 'new',
          created_at: new Date().toISOString()
        });
      }
    }

    // Driving License Expiry Check
    if (emp.driving_license_expiry_date) {
      const expDate = new Date(emp.driving_license_expiry_date);
      const diffDays = Math.ceil((expDate.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
      if (diffDays < 0) {
        recommendations.push({
          id: `rec-dl-exp-${emp.id}`,
          employee_id: emp.id,
          category: 'expiring_cert',
          reason: `Driving license for ${emp.first_name} ${emp.last_name} expired on ${emp.driving_license_expiry_date}.`,
          impact: 'MEDIUM: Operator is legally prohibited from operating heavy vehicles or site transit.',
          suggested_action: 'Ensure vehicle keys are withheld and schedule driving license renewal.',
          status: 'new',
          created_at: new Date().toISOString()
        });
      }
    }

    // 2. Position Required Certificates - Missing compliance check
    const empPosition = emp.position_id;
    const requiredCerts = posRequiredCerts.filter((pr: any) => pr.position_id === empPosition);
    
    requiredCerts.forEach((req: any) => {
      const activeCert = certs.find((c: any) => c.employee_id === emp.id && c.certificate_type_id === req.certificate_type_id);
      const certType = certTypes.find((ct: any) => ct.id === req.certificate_type_id);
      const certTypeName = certType ? certType.name : 'Required Certificate';

      if (!activeCert) {
        recommendations.push({
          id: `rec-miss-cert-${emp.id}-${req.certificate_type_id}`,
          employee_id: emp.id,
          category: 'missing_doc',
          reason: `${emp.first_name} ${emp.last_name} is missing the required certificate "${certTypeName}" for position.`,
          impact: 'CRITICAL COMPLIANCE GAP: Employee does not meet occupational competence standards for active deployment.',
          suggested_action: `Enroll employee in the next available "${certTypeName}" training session.`,
          status: 'new',
          created_at: new Date().toISOString()
        });
      } else if (activeCert.status === 'expired') {
        recommendations.push({
          id: `rec-exp-cert-${emp.id}-${req.certificate_type_id}`,
          employee_id: emp.id,
          category: 'expiring_cert',
          reason: `Required certificate "${certTypeName}" for ${emp.first_name} has expired.`,
          impact: 'HIGH COMPLIANCE GAP: Active or planned deployments violate safety regulations and site standards.',
          suggested_action: `Book urgent recertification courses for "${certTypeName}".`,
          status: 'new',
          created_at: new Date().toISOString()
        });
      }
    });
  });

  // 3. Document types marked required vs what employees have
  const requiredDocs = docTypes.filter((dt: any) => dt.is_active && dt.name === 'Passport'); // or Passport/National ID
  requiredDocs.forEach((dt: any) => {
    employees.forEach((emp: any) => {
      const empDoc = docs.find((d: any) => d.employee_id === emp.id && d.document_type_id === dt.id);
      if (!empDoc) {
        recommendations.push({
          id: `rec-miss-doc-${emp.id}-${dt.id}`,
          employee_id: emp.id,
          category: 'missing_doc',
          reason: `${emp.first_name} ${emp.last_name} does not have a "${dt.name}" registered in the document store.`,
          impact: 'HR compliance audit warning. Missing essential personal identification record.',
          suggested_action: `Contact employee to obtain and upload scan of "${dt.name}".`,
          status: 'new',
          created_at: new Date().toISOString()
        });
      }
    });
  });

  // 4. Operations Check: Deployment Handover and B2B pairs
  deployments.forEach((dep: any) => {
    if (dep.status === 'active') {
      const b2bMatch = b2b.find((pair: any) => pair.deployment_id === dep.id);
      if (!b2bMatch || !b2bMatch.replacement_employee_id) {
        const emp = employees.find((e: any) => e.id === dep.employee_id);
        const empName = emp ? `${emp.first_name} ${emp.last_name}` : 'Employee';
        recommendations.push({
          id: `rec-nob2b-${dep.id}`,
          employee_id: dep.employee_id,
          category: 'unassigned_b2b',
          reason: `Active deployment for ${empName} (ends ${dep.end_date}) has no designated back-to-back replacement.`,
          impact: 'OPERATIONAL RISK: Handover schedule is vulnerable. Potential rig-floor fatigue or continuous overtime penalty.',
          suggested_action: 'Identify available crew member and assign as Back-to-Back replacement.',
          status: 'new',
          created_at: new Date().toISOString()
        });
      }
    }
  });

  // Blend existing ignored / resolved status if matching ID exists
  const existingRecs = db.ai_recommendations || [];
  const updatedRecommendations = recommendations.map((newRec: any) => {
    const matched = existingRecs.find((er: any) => er.id === newRec.id);
    if (matched) {
      return { ...newRec, status: matched.status, resolved_at: matched.resolved_at };
    }
    return newRec;
  });

  db.ai_recommendations = updatedRecommendations;
  return db;
}

// -------------------------------------------------------------
// REST API ENDPOINTS
// -------------------------------------------------------------

// 1. Bootstrap Endpoint
app.get('/api/bootstrap', (req, res) => {
  const db = readDB();
  res.json({
    clients: db.clients || [],
    fields: db.fields || [],
    projects: db.projects || [],
    locations: db.locations || [],
    departments: db.departments || [],
    positions: db.positions || [],
    document_types: db.document_types || [],
    certificate_types: db.certificate_types || [],
    shift_types: db.shift_types || [],
    rotation_types: db.rotation_types || [],
    leave_types: db.leave_types || [],
    evaluation_templates: db.evaluation_templates || [],
    evaluation_template_sections: db.evaluation_template_sections || [],
    evaluation_template_items: db.evaluation_template_items || [],
    allowance_types: db.allowance_types || [],
    bonus_types: db.bonus_types || [],
    equipment_types: db.equipment_types || [],
    employees: db.employees || [],
    emergency_contacts: db.emergency_contacts || [],
    documents: db.documents || [],
    certificates: db.certificates || [],
    position_required_certificates: db.position_required_certificates || [],
    deployments: db.deployments || [],
    back_to_back_pairs: db.back_to_back_pairs || [],
    leave_records: db.leave_records || [],
    evaluations: db.evaluations || [],
    evaluation_scores: db.evaluation_scores || [],
    allowances: db.allowances || [],
    bonuses: db.bonuses || [],
    payroll_periods: db.payroll_periods || [],
    payroll_entries: db.payroll_entries || [],
    equipment_assignments: db.equipment_assignments || [],
    notes: db.notes || [],
    attachments: db.attachments || [],
    activity_log: db.activity_log || [],
    ai_recommendations: db.ai_recommendations || []
  });
});

// 2. Settings CRUD Endpoint Generator
app.post('/api/settings/:table', (req, res) => {
  const { table } = req.params;
  const db = readDB();
  if (!db[table]) db[table] = [];

  const newItem = {
    id: `${table.substring(0, 2)}-${Date.now()}`,
    ...req.body
  };

  db[table].push(newItem);
  writeDB(db);
  logActivity('settings', newItem.id, 'CREATE', `Added new item in ${table}: ${newItem.name || newItem.id}`);
  res.status(201).json(newItem);
});

app.put('/api/settings/:table/:id', (req, res) => {
  const { table, id } = req.params;
  const db = readDB();
  if (!db[table]) return res.status(404).json({ error: 'Table not found' });

  const index = db[table].findIndex((item: any) => item.id === id);
  if (index === -1) return res.status(404).json({ error: 'Item not found' });

  db[table][index] = { ...db[table][index], ...req.body };
  writeDB(db);
  logActivity('settings', id, 'UPDATE', `Updated item in ${table}: ${db[table][index].name || id}`);
  res.json(db[table][index]);
});

app.delete('/api/settings/:table/:id', (req, res) => {
  const { table, id } = req.params;
  const db = readDB();
  if (!db[table]) return res.status(404).json({ error: 'Table not found' });

  const index = db[table].findIndex((item: any) => item.id === id);
  if (index === -1) return res.status(404).json({ error: 'Item not found' });

  const deletedItem = db[table].splice(index, 1)[0];
  writeDB(db);
  logActivity('settings', id, 'DELETE', `Deleted item from ${table}: ${deletedItem.name || id}`);
  res.json({ success: true, deleted: deletedItem });
});

// 3. Employee Master CRUD
app.post('/api/employees', (req, res) => {
  const db = readDB();
  const employees = db.employees || [];
  
  // Calculate next code TAQA-WT-100X
  const lastNum = employees.reduce((max: number, emp: any) => {
    const match = emp.employee_code.match(/TAQA-WT-(\d+)/);
    return match ? Math.max(max, parseInt(match[1])) : max;
  }, 1000);
  
  const employee_code = `TAQA-WT-${lastNum + 1}`;
  
  const newEmp = {
    id: `emp-${Date.now()}`,
    employee_code,
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString(),
    photo_url: '',
    status: 'active',
    ...req.body
  };

  db.employees = [...employees, newEmp];
  writeDB(db);
  
  logActivity('employee', newEmp.id, 'CREATE', `Created employee profile for ${newEmp.first_name} ${newEmp.last_name} (${employee_code}).`);
  
  // Trigger on-demand compliance scan
  const rescanned = runDatabaseDiagnostics(db);
  writeDB(rescanned);

  res.status(201).json(newEmp);
});

app.put('/api/employees/:id', (req, res) => {
  const { id } = req.params;
  const db = readDB();
  const employees = db.employees || [];

  const index = employees.findIndex((item: any) => item.id === id);
  if (index === -1) return res.status(404).json({ error: 'Employee not found' });

  let photo_url = employees[index].photo_url || '';
  const { photo_base64, photo_name, ...otherBody } = req.body;

  if (photo_base64 && photo_name) {
    try {
      const cleanBase64 = photo_base64.replace(/^data:.*?;base64,/, '');
      const uniqueName = `profile_${Date.now()}_${photo_name}`;
      const filePath = path.join(UPLOADS_DIR, uniqueName);
      fs.writeFileSync(filePath, cleanBase64, 'base64');
      photo_url = `/uploads/${uniqueName}`;
    } catch (e) {
      console.error('Profile photo upload write error:', e);
    }
  }

  const updatedEmp = {
    ...employees[index],
    ...otherBody,
    photo_url: (photo_base64 && photo_name) ? photo_url : (req.body.photo_url !== undefined ? req.body.photo_url : employees[index].photo_url),
    updated_at: new Date().toISOString()
  };

  employees[index] = updatedEmp;
  db.employees = employees;
  writeDB(db);

  logActivity('employee', id, 'UPDATE', `Updated employee profile for ${updatedEmp.first_name} ${updatedEmp.last_name}.`);
  
  // Run compliance diagnostics on save
  const rescanned = runDatabaseDiagnostics(db);
  writeDB(rescanned);

  res.json(updatedEmp);
});

app.delete('/api/employees/:id', (req, res) => {
  const { id } = req.params;
  const db = readDB();
  const employees = db.employees || [];

  const index = employees.findIndex((item: any) => item.id === id);
  if (index === -1) return res.status(404).json({ error: 'Employee not found' });

  const deleted = employees.splice(index, 1)[0];
  db.employees = employees;
  
  // Cascade delete files and child tables
  db.documents = (db.documents || []).filter((d: any) => d.employee_id !== id);
  db.certificates = (db.certificates || []).filter((c: any) => c.employee_id !== id);
  db.deployments = (db.deployments || []).filter((dp: any) => dp.employee_id !== id);
  db.leave_records = (db.leave_records || []).filter((l: any) => l.employee_id !== id);
  db.evaluations = (db.evaluations || []).filter((ev: any) => ev.employee_id !== id);
  db.notes = (db.notes || []).filter((n: any) => n.employee_id !== id);
  db.attachments = (db.attachments || []).filter((a: any) => a.employee_id !== id);
  db.equipment_assignments = (db.equipment_assignments || []).filter((ea: any) => ea.employee_id !== id);
  db.ai_recommendations = (db.ai_recommendations || []).filter((r: any) => r.employee_id !== id);

  writeDB(db);
  logActivity('employee', id, 'DELETE', `Archived & removed employee profile for ${deleted.first_name} ${deleted.last_name}.`);
  
  const rescanned = runDatabaseDiagnostics(db);
  writeDB(rescanned);

  res.json({ success: true, deleted });
});

// 4. Emergency Contacts
app.post('/api/emergency-contacts', (req, res) => {
  const db = readDB();
  const contacts = db.emergency_contacts || [];
  const newContact = {
    id: `ec-${Date.now()}`,
    ...req.body
  };
  db.emergency_contacts = [...contacts, newContact];
  writeDB(db);
  res.status(201).json(newContact);
});

// 5. Document Management with Expiry Engine & Simulated File Upload (base64)
app.post('/api/documents', (req, res) => {
  const db = readDB();
  const docs = db.documents || [];
  const { employee_id, document_type_id, issue_date, expiry_date, reminder_days, description, notes, file_base64, file_name } = req.body;

  let file_url = '';
  if (file_base64 && file_name) {
    try {
      const cleanBase64 = file_base64.replace(/^data:.*?;base64,/, '');
      const uniqueName = `${Date.now()}_${file_name}`;
      const filePath = path.join(UPLOADS_DIR, uniqueName);
      fs.writeFileSync(filePath, cleanBase64, 'base64');
      file_url = `/uploads/${uniqueName}`;
    } catch (e) {
      console.error('File write error:', e);
    }
  }

  const status = getExpiryStatus(expiry_date, Number(reminder_days || 90));

  const newDoc = {
    id: `doc-${Date.now()}`,
    employee_id,
    document_type_id,
    issue_date,
    expiry_date,
    reminder_days: Number(reminder_days || 90),
    file_url,
    file_name: file_name || 'document.pdf',
    description,
    notes,
    status,
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString()
  };

  db.documents = [...docs, newDoc];
  writeDB(db);

  const emp = (db.employees || []).find((e: any) => e.id === employee_id);
  const empName = emp ? `${emp.first_name} ${emp.last_name}` : 'Employee';
  const docType = (db.document_types || []).find((d: any) => d.id === document_type_id);
  const docTypeName = docType ? docType.name : 'Document';

  logActivity('document', newDoc.id, 'CREATE', `Uploaded compliance record "${docTypeName}" for ${empName}.`);
  
  // Recalculate diagnostics
  const rescanned = runDatabaseDiagnostics(db);
  writeDB(rescanned);

  res.status(201).json(newDoc);
});

app.delete('/api/documents/:id', (req, res) => {
  const { id } = req.params;
  const db = readDB();
  const docs = db.documents || [];

  const index = docs.findIndex((item: any) => item.id === id);
  if (index === -1) return res.status(404).json({ error: 'Document not found' });

  const deleted = docs.splice(index, 1)[0];
  db.documents = docs;
  writeDB(db);

  logActivity('document', id, 'DELETE', `Removed compliance document record.`);
  
  const rescanned = runDatabaseDiagnostics(db);
  writeDB(rescanned);

  res.json({ success: true });
});

// 6. Certificate Management with Expiry Engine & Simulated Upload
app.post('/api/certificates', (req, res) => {
  const db = readDB();
  const certs = db.certificates || [];
  const { employee_id, certificate_type_id, training_provider, certificate_number, issue_date, expiry_date, notes, file_base64, file_name } = req.body;

  let file_url = '';
  if (file_base64 && file_name) {
    try {
      const cleanBase64 = file_base64.replace(/^data:.*?;base64,/, '');
      const uniqueName = `${Date.now()}_${file_name}`;
      const filePath = path.join(UPLOADS_DIR, uniqueName);
      fs.writeFileSync(filePath, cleanBase64, 'base64');
      file_url = `/uploads/${uniqueName}`;
    } catch (e) {
      console.error('File write error:', e);
    }
  }

  // Calculate reminder days from type
  const certType = (db.certificate_types || []).find((ct: any) => ct.id === certificate_type_id);
  const reminder_days = certType ? certType.default_reminder_days : 90;
  const status = getExpiryStatus(expiry_date, reminder_days);

  const newCert = {
    id: `ce-${Date.now()}`,
    employee_id,
    certificate_type_id,
    training_provider,
    certificate_number,
    issue_date,
    expiry_date,
    file_url,
    file_name: file_name || 'certificate.pdf',
    notes,
    status,
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString()
  };

  db.certificates = [...certs, newCert];
  writeDB(db);

  const emp = (db.employees || []).find((e: any) => e.id === employee_id);
  const empName = emp ? `${emp.first_name} ${emp.last_name}` : 'Employee';
  const certTypeName = certType ? certType.name : 'Certificate';

  logActivity('certificate', newCert.id, 'CREATE', `Registered competency credential "${certTypeName}" for ${empName}.`);
  
  const rescanned = runDatabaseDiagnostics(db);
  writeDB(rescanned);

  res.status(201).json(newCert);
});

app.delete('/api/certificates/:id', (req, res) => {
  const { id } = req.params;
  const db = readDB();
  const certs = db.certificates || [];

  const index = certs.findIndex((item: any) => item.id === id);
  if (index === -1) return res.status(404).json({ error: 'Certificate not found' });

  const deleted = certs.splice(index, 1)[0];
  db.certificates = certs;
  writeDB(db);

  logActivity('certificate', id, 'DELETE', `Removed safety competency credential record.`);
  
  const rescanned = runDatabaseDiagnostics(db);
  writeDB(rescanned);

  res.json({ success: true });
});

// Position Certificate Matrix bindings
app.post('/api/position-required-certificates', (req, res) => {
  const db = readDB();
  const list = db.position_required_certificates || [];
  const { position_id, certificate_type_id } = req.body;

  // Check unique
  const exists = list.some((item: any) => item.position_id === position_id && item.certificate_type_id === certificate_type_id);
  if (exists) return res.status(400).json({ error: 'Requirement already exists' });

  db.position_required_certificates = [...list, { position_id, certificate_type_id }];
  writeDB(db);

  const rescanned = runDatabaseDiagnostics(db);
  writeDB(rescanned);

  res.status(201).json({ success: true });
});

app.delete('/api/position-required-certificates', (req, res) => {
  const db = readDB();
  const list = db.position_required_certificates || [];
  const { position_id, certificate_type_id } = req.body;

  db.position_required_certificates = list.filter((item: any) => !(item.position_id === position_id && item.certificate_type_id === certificate_type_id));
  writeDB(db);

  const rescanned = runDatabaseDiagnostics(db);
  writeDB(rescanned);

  res.json({ success: true });
});

// 7. Operations Deployment & Crew Board
app.post('/api/deployments', (req, res) => {
  const db = readDB();
  const deployments = db.deployments || [];
  const b2bList = db.back_to_back_pairs || [];
  const employees = db.employees || [];

  const newDep = {
    id: `dep-${Date.now()}`,
    status: 'planned',
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString(),
    ...req.body
  };

  db.deployments = [...deployments, newDep];

  // Auto-establish a Back-to-Back record
  const newB2b = {
    id: `b2b-${Date.now()}`,
    deployment_id: newDep.id,
    replacement_employee_id: req.body.replacement_employee_id || '',
    handover_date: newDep.end_date,
    status: req.body.replacement_employee_id ? 'confirmed' : 'unassigned'
  };
  db.back_to_back_pairs = [...b2bList, newB2b];

  // Update employee master status dynamically to "deployed" if active
  const empIndex = employees.findIndex((e: any) => e.id === newDep.employee_id);
  if (empIndex !== -1 && newDep.status === 'active') {
    employees[empIndex].status = 'deployed';
  }
  db.employees = employees;

  writeDB(db);

  const emp = employees.find((e: any) => e.id === newDep.employee_id);
  const empName = emp ? `${emp.first_name} ${emp.last_name}` : 'Employee';
  const client = (db.clients || []).find((c: any) => c.id === newDep.client_id);
  const clientName = client ? client.name : 'Oilfield Client';

  logActivity('deployment', newDep.id, 'CREATE', `Scheduled deployment for ${empName} on ${clientName} rotation.`);

  const rescanned = runDatabaseDiagnostics(db);
  writeDB(rescanned);

  res.status(201).json(newDep);
});

app.put('/api/deployments/:id', (req, res) => {
  const { id } = req.params;
  const db = readDB();
  const deployments = db.deployments || [];
  const employees = db.employees || [];

  const index = deployments.findIndex((item: any) => item.id === id);
  if (index === -1) return res.status(404).json({ error: 'Deployment not found' });

  const oldDep = deployments[index];
  const updatedDep = {
    ...oldDep,
    ...req.body,
    updated_at: new Date().toISOString()
  };

  deployments[index] = updatedDep;
  db.deployments = deployments;

  // Handle dynamic status cascades
  const empIndex = employees.findIndex((e: any) => e.id === updatedDep.employee_id);
  if (empIndex !== -1) {
    if (updatedDep.status === 'active') {
      employees[empIndex].status = 'deployed';
    } else if (updatedDep.status === 'completed' || updatedDep.status === 'cancelled') {
      employees[empIndex].status = 'active';
    }
  }
  db.employees = employees;

  // Handle B2b status sync
  if (db.back_to_back_pairs) {
    const bMatch = db.back_to_back_pairs.findIndex((p: any) => p.deployment_id === id);
    if (bMatch !== -1) {
      db.back_to_back_pairs[bMatch].handover_date = updatedDep.end_date;
    }
  }

  writeDB(db);

  const emp = employees.find((e: any) => e.id === updatedDep.employee_id);
  const empName = emp ? `${emp.first_name} ${emp.last_name}` : 'Employee';
  logActivity('deployment', id, 'UPDATE', `Updated operational deployment status to "${updatedDep.status}" for ${empName}.`);

  const rescanned = runDatabaseDiagnostics(db);
  writeDB(rescanned);

  res.json(updatedDep);
});

app.delete('/api/deployments/:id', (req, res) => {
  const { id } = req.params;
  const db = readDB();
  const deployments = db.deployments || [];

  const index = deployments.findIndex((item: any) => item.id === id);
  if (index === -1) return res.status(404).json({ error: 'Deployment not found' });

  const deleted = deployments.splice(index, 1)[0];
  db.deployments = deployments;

  // Cleanup matching B2b pairings
  db.back_to_back_pairs = (db.back_to_back_pairs || []).filter((p: any) => p.deployment_id !== id);

  writeDB(db);
  logActivity('deployment', id, 'DELETE', `Removed scheduling deployment record.`);

  const rescanned = runDatabaseDiagnostics(db);
  writeDB(rescanned);

  res.json({ success: true });
});

// Back-to-Back handovers updates
app.put('/api/b2b/:id', (req, res) => {
  const { id } = req.params;
  const db = readDB();
  const pairs = db.back_to_back_pairs || [];

  const index = pairs.findIndex((p: any) => p.id === id);
  if (index === -1) return res.status(404).json({ error: 'B2B pair not found' });

  pairs[index] = { ...pairs[index], ...req.body };
  db.back_to_back_pairs = pairs;
  writeDB(db);

  logActivity('deployment', pairs[index].deployment_id, 'UPDATE', `Updated rotation Back-to-Back assignment.`);

  const rescanned = runDatabaseDiagnostics(db);
  writeDB(rescanned);

  res.json(pairs[index]);
});

// 8. Leave Records CRUD
app.post('/api/leave', (req, res) => {
  const db = readDB();
  const leave = db.leave_records || [];
  const employees = db.employees || [];
  const { employee_id, leave_type_id, start_date, end_date, notes } = req.body;

  const newLeave = {
    id: `le-${Date.now()}`,
    employee_id,
    leave_type_id,
    start_date,
    end_date,
    notes
  };

  db.leave_records = [...leave, newLeave];

  // Set employee status to on_leave
  const empIndex = employees.findIndex((e: any) => e.id === employee_id);
  if (empIndex !== -1) {
    employees[empIndex].status = 'on_leave';
  }
  db.employees = employees;

  writeDB(db);

  const emp = employees.find((e: any) => e.id === employee_id);
  const empName = emp ? `${emp.first_name} ${emp.last_name}` : 'Employee';
  logActivity('employee', employee_id, 'LEAVE', `Placed ${empName} on leave from ${start_date} to ${end_date}.`);

  const rescanned = runDatabaseDiagnostics(db);
  writeDB(rescanned);

  res.status(201).json(newLeave);
});

app.delete('/api/leave/:id', (req, res) => {
  const { id } = req.params;
  const db = readDB();
  const leave = db.leave_records || [];
  const employees = db.employees || [];

  const index = leave.findIndex((item: any) => item.id === id);
  if (index === -1) return res.status(404).json({ error: 'Leave record not found' });

  const oldL = leave.splice(index, 1)[0];
  db.leave_records = leave;

  // Restore status to active
  const empIndex = employees.findIndex((e: any) => e.id === oldL.employee_id);
  if (empIndex !== -1) {
    employees[empIndex].status = 'active';
  }
  db.employees = employees;

  writeDB(db);
  logActivity('employee', oldL.employee_id, 'LEAVE', `Cancelled leave assignment for employee.`);

  const rescanned = runDatabaseDiagnostics(db);
  writeDB(rescanned);

  res.json({ success: true });
});

// -------------------------------------------------------------
// 9. PERFORMANCE EVALUATION (DIGITIZES FARES BEN FRAGE APPRAISAL FORM)
// -------------------------------------------------------------
app.post('/api/evaluations', (req, res) => {
  const db = readDB();
  const evaluations = db.evaluations || [];
  const scores = db.evaluation_scores || [];

  const {
    employee_id, template_id, deployment_id, field_client_label, job_type,
    period_start, period_end, supervisor_name, would_rehire, rehire_justification,
    supervisor_comments, employee_comments, status, item_scores
  } = req.body;

  // Calculation of appraisal score based on template and weights
  // Competence Section weight: 40%, HSE: 30%, Training: 10%, Attitude: 20%
  const sections = db.evaluation_template_sections || [];
  const items = db.evaluation_template_items || [];

  let overall_score = 0;

  if (item_scores && typeof item_scores === 'object') {
    // Group scores by section
    const scoresBySection: Record<string, number[]> = {};
    items.forEach((item: any) => {
      const scoreValue = Number(item_scores[item.id] || 3); // default to midpoint 3
      if (!scoresBySection[item.section_id]) {
        scoresBySection[item.section_id] = [];
      }
      scoresBySection[item.section_id].push(scoreValue);
    });

    // Compute weighted score
    let weightedSum = 0;
    sections.forEach((sect: any) => {
      const sectScores = scoresBySection[sect.id] || [];
      if (sectScores.length > 0) {
        const avg = sectScores.reduce((a, b) => a + b, 0) / sectScores.length; // average 1-5
        const percentage = (avg / 5) * 100; // 0-100%
        weightedSum += percentage * Number(sect.weight);
      }
    });
    overall_score = Number(weightedSum.toFixed(1));
  } else {
    overall_score = 60.0; // placeholder midpoint
  }

  const newEvalId = `ev-${Date.now()}`;
  const newEval = {
    id: newEvalId,
    employee_id,
    template_id,
    deployment_id,
    field_client_label,
    job_type,
    period_start,
    period_end,
    supervisor_name,
    would_rehire: !!would_rehire,
    rehire_justification,
    supervisor_comments,
    employee_comments,
    overall_score,
    status: status || 'draft',
    created_at: new Date().toISOString()
  };

  db.evaluations = [...evaluations, newEval];

  // Save each individual item score
  if (item_scores && typeof item_scores === 'object') {
    Object.entries(item_scores).forEach(([itemId, val]) => {
      scores.push({
        id: `evs-${Date.now()}-${Math.random().toString(36).substring(2, 6)}`,
        evaluation_id: newEvalId,
        template_item_id: itemId,
        score: Number(val)
      });
    });
  }
  db.evaluation_scores = scores;

  writeDB(db);

  const emp = (db.employees || []).find((e: any) => e.id === employee_id);
  const empName = emp ? `${emp.first_name} ${emp.last_name}` : 'Employee';
  logActivity('employee', employee_id, 'APPRAISAL', `Submitted high-fidelity field appraisal score ${overall_score}% for ${empName}.`);

  const rescanned = runDatabaseDiagnostics(db);
  writeDB(rescanned);

  res.status(201).json(newEval);
});

app.delete('/api/evaluations/:id', (req, res) => {
  const { id } = req.params;
  const db = readDB();
  db.evaluations = (db.evaluations || []).filter((e: any) => e.id !== id);
  db.evaluation_scores = (db.evaluation_scores || []).filter((s: any) => s.evaluation_id !== id);
  writeDB(db);
  res.json({ success: true });
});

// -------------------------------------------------------------
// 10. PAYROLL PREPARATION AND CALCULATIONS
// -------------------------------------------------------------
app.get('/api/payroll/:period', (req, res) => {
  const { period } = req.params; // format: YYYY-MM e.g. "2026-07"
  const db = readDB();
  
  let payrollPeriod = (db.payroll_periods || []).find((p: any) => p.period_month === period);
  if (!payrollPeriod) {
    payrollPeriod = { id: period, period_month: period, status: 'draft' };
    db.payroll_periods = [...(db.payroll_periods || []), payrollPeriod];
    writeDB(db);
  }

  const entries = db.payroll_entries || [];
  const periodEntries = entries.filter((e: any) => e.payroll_period_id === period);

  if (periodEntries.length > 0) {
    return res.json({ period: payrollPeriod, entries: periodEntries });
  }

  // Calculate fresh entries from master data & active deployments
  const employees = db.employees || [];
  const deployments = db.deployments || [];
  const allowances = db.allowances || [];
  const bonuses = db.bonuses || [];

  const calculatedEntries = employees.map((emp: any) => {
    // 1. Calculate active deployment days worked in this month
    const empDeployments = deployments.filter((d: any) => d.employee_id === emp.id && d.status === 'active');
    
    let days_worked = 0;
    let field_days = 0;
    let day_shifts = 0;
    let night_shifts = 0;

    const [year, month] = period.split('-').map(Number);
    const startOfMonth = new Date(year, month - 1, 1);
    const endOfMonth = new Date(year, month, 0);

    empDeployments.forEach((dep: any) => {
      const depStart = new Date(dep.start_date);
      const depEnd = new Date(dep.end_date);

      const overlapStart = depStart < startOfMonth ? startOfMonth : depStart;
      const overlapEnd = depEnd > endOfMonth ? endOfMonth : depEnd;

      if (overlapStart <= overlapEnd) {
        const diffTime = overlapEnd.getTime() - overlapStart.getTime();
        const overlapDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
        days_worked += overlapDays;
        field_days += overlapDays;

        // Simulate shift division
        if (dep.shift_type_id === 'sh-1') {
          day_shifts += overlapDays;
        } else if (dep.shift_type_id === 'sh-2') {
          night_shifts += overlapDays;
        } else {
          day_shifts += Math.floor(overlapDays / 2);
          night_shifts += Math.ceil(overlapDays / 2);
        }
      }
    });

    // 2. Fetch Allowances & Bonuses for this month
    const empAllowances = allowances
      .filter((a: any) => a.employee_id === emp.id && a.period_month === period)
      .reduce((sum: number, a: any) => sum + Number(a.amount || 0), 0);

    const empBonuses = bonuses
      .filter((b: any) => b.employee_id === emp.id && b.period_month === period)
      .reduce((sum: number, b: any) => sum + Number(b.amount || 0), 0);

    const base_amount = days_worked * Number(emp.daily_rate || 500);
    const total_amount = base_amount + empAllowances + empBonuses;

    return {
      id: `py-entry-${emp.id}-${period}`,
      payroll_period_id: period,
      employee_id: emp.id,
      days_worked,
      field_days,
      day_shifts,
      night_shifts,
      daily_rate: emp.daily_rate,
      base_amount,
      total_allowances: empAllowances,
      total_bonuses: empBonuses,
      manual_adjustment: 0,
      adjustment_reason: '',
      total_amount
    };
  });

  db.payroll_entries = [...entries, ...calculatedEntries];
  writeDB(db);

  res.json({ period: payrollPeriod, entries: calculatedEntries });
});

app.put('/api/payroll/:period/:entryId', (req, res) => {
  const { period, entryId } = req.params;
  const db = readDB();
  const entries = db.payroll_entries || [];

  const index = entries.findIndex((e: any) => e.id === entryId && e.payroll_period_id === period);
  if (index === -1) return res.status(404).json({ error: 'Payroll entry not found' });

  const entry = entries[index];
  const manual_adjustment = Number(req.body.manual_adjustment || 0);
  const adjustment_reason = req.body.adjustment_reason || '';

  const total_amount = entry.base_amount + entry.total_allowances + entry.total_bonuses + manual_adjustment;

  entries[index] = {
    ...entry,
    manual_adjustment,
    adjustment_reason,
    total_amount
  };

  db.payroll_entries = entries;
  writeDB(db);

  res.json(entries[index]);
});

app.post('/api/payroll/:period/lock', (req, res) => {
  const { period } = req.params;
  const db = readDB();
  const periods = db.payroll_periods || [];

  const index = periods.findIndex((p: any) => p.period_month === period);
  if (index !== -1) {
    periods[index].status = 'locked';
    db.payroll_periods = periods;
    writeDB(db);
    logActivity('payroll', period, 'LOCK', `Locked payroll prep journal for the period of ${period}.`);
  }
  res.json({ success: true });
});

// Allowances and bonuses CRUD
app.post('/api/allowances', (req, res) => {
  const db = readDB();
  const list = db.allowances || [];
  const newItem = { id: `al-${Date.now()}`, ...req.body };
  db.allowances = [...list, newItem];
  writeDB(db);
  res.status(201).json(newItem);
});

app.post('/api/bonuses', (req, res) => {
  const db = readDB();
  const list = db.bonuses || [];
  const newItem = { id: `bo-${Date.now()}`, ...req.body };
  db.bonuses = [...list, newItem];
  writeDB(db);
  res.status(201).json(newItem);
});

// 11. Equipment Assignment
app.post('/api/equipment', (req, res) => {
  const db = readDB();
  const list = db.equipment_assignments || [];
  const newItem = { id: `eqa-${Date.now()}`, ...req.body };
  db.equipment_assignments = [...list, newItem];
  writeDB(db);
  res.status(201).json(newItem);
});

app.put('/api/equipment/:id', (req, res) => {
  const { id } = req.params;
  const db = readDB();
  const list = db.equipment_assignments || [];
  const index = list.findIndex((item: any) => item.id === id);
  if (index === -1) return res.status(404).json({ error: 'Record not found' });

  list[index] = { ...list[index], ...req.body };
  db.equipment_assignments = list;
  writeDB(db);
  res.json(list[index]);
});

app.delete('/api/equipment/:id', (req, res) => {
  const { id } = req.params;
  const db = readDB();
  const list = db.equipment_assignments || [];
  db.equipment_assignments = list.filter((item: any) => item.id !== id);
  writeDB(db);
  res.json({ success: true });
});

// 12. Notes & Attachments
app.post('/api/notes', (req, res) => {
  const db = readDB();
  const notes = db.notes || [];
  const newNote = {
    id: `no-${Date.now()}`,
    body: req.body.body,
    employee_id: req.body.employee_id,
    created_at: new Date().toISOString()
  };
  db.notes = [...notes, newNote];
  writeDB(db);
  res.status(201).json(newNote);
});

app.delete('/api/notes/:id', (req, res) => {
  const { id } = req.params;
  const db = readDB();
  db.notes = (db.notes || []).filter((n: any) => n.id !== id);
  writeDB(db);
  res.json({ success: true });
});

app.post('/api/attachments', (req, res) => {
  const db = readDB();
  const attachments = db.attachments || [];
  const { employee_id, label, file_base64, file_name } = req.body;

  let file_url = '';
  if (file_base64 && file_name) {
    try {
      const cleanBase64 = file_base64.replace(/^data:.*?;base64,/, '');
      const uniqueName = `${Date.now()}_${file_name}`;
      const filePath = path.join(UPLOADS_DIR, uniqueName);
      fs.writeFileSync(filePath, cleanBase64, 'base64');
      file_url = `/uploads/${uniqueName}`;
    } catch (e) {
      console.error('Attachment upload failed:', e);
    }
  }

  const newAttachment = {
    id: `at-${Date.now()}`,
    employee_id,
    file_url,
    label: label || file_name || 'Attachment',
    created_at: new Date().toISOString()
  };

  db.attachments = [...attachments, newAttachment];
  writeDB(db);
  res.status(201).json(newAttachment);
});

app.delete('/api/attachments/:id', (req, res) => {
  const { id } = req.params;
  const db = readDB();
  db.attachments = (db.attachments || []).filter((a: any) => a.id !== id);
  writeDB(db);
  res.json({ success: true });
});

// 13. AI Scanner Diagnostic Endpoint
app.post('/api/ai/scan', (req, res) => {
  const db = readDB();
  const rescanned = runDatabaseDiagnostics(db);
  writeDB(rescanned);
  res.json({ success: true, recommendations: rescanned.ai_recommendations });
});

app.post('/api/ai/recommendations/:id/status', (req, res) => {
  const { id } = req.params;
  const { status } = req.body;
  const db = readDB();
  const recs = db.ai_recommendations || [];

  const index = recs.findIndex((r: any) => r.id === id);
  if (index !== -1) {
    recs[index].status = status;
    if (status === 'applied') {
      recs[index].resolved_at = new Date().toISOString();
    }
    db.ai_recommendations = recs;
    writeDB(db);
  }
  res.json({ success: true, recommendation: recs[index] });
});

// 14. AI Operations Assistant chat with Gemini Model (using @google/genai)
app.post('/api/ai/chat', async (req, res) => {
  const { message } = req.body;
  if (!message) return res.status(400).json({ error: 'Message is required' });

  const apiKey = process.env.GEMINI_API_KEY;
  if (!apiKey) {
    return res.json({
      text: "### ⚠️ Google GenAI API Key is Unconfigured\nTo activate the smart operations assistant, please go to **Settings > Secrets** in the AI Studio side panel and supply your `GEMINI_API_KEY`."
    });
  }

  try {
    const db = readDB();
    
    // Construct simplified summary of DB for grounding context
    const summaryContext = {
      total_employees: (db.employees || []).length,
      employees: (db.employees || []).map((e: any) => ({
        id: e.id,
        code: e.employee_code,
        name: `${e.first_name} ${e.last_name}`,
        position: (db.positions || []).find((p: any) => p.id === e.position_id)?.name || 'Unknown',
        status: e.status
      })),
      active_deployments: (db.deployments || [])
        .filter((d: any) => d.status === 'active')
        .map((d: any) => {
          const empName = (db.employees || []).find((e: any) => e.id === d.employee_id);
          const cName = (db.clients || []).find((c: any) => c.id === d.client_id)?.name;
          const fName = (db.fields || []).find((f: any) => f.id === d.field_id)?.name;
          return {
            employee: empName ? `${empName.first_name} ${empName.last_name}` : 'Unknown',
            client: cName,
            field: fName,
            start: d.start_date,
            end: d.end_date
          };
        }),
      compliance_warnings: (db.ai_recommendations || []).filter((r: any) => r.status === 'new').map((r: any) => r.reason),
      unassigned_handovers: (db.back_to_back_pairs || []).filter((p: any) => p.status === 'unassigned').length
    };

    // Lazy initialization of Gemini client per system rules
    const ai = new GoogleGenAI({
      apiKey,
      httpOptions: {
        headers: {
          'User-Agent': 'aistudio-build',
        }
      }
    });

    const systemPrompt = `You are the TAQA AI Operations Advisor, an intelligent assistant built into the TAQA Workforce Operations Management System.
Your job is to provide precise, professional, and practical operations advice grounded in the provided database state.
Address the user as a colleague. Highlight compliance issues, missing documents, active scheduling, and suggest solutions.
You can format your answers using rich markdown, tables, and lists. Keep descriptions realistic and professional. No AI-slop keywords.

Grounding Database Context:
${JSON.stringify(summaryContext, null, 2)}`;

    const response = await ai.models.generateContent({
      model: "gemini-3.5-flash",
      contents: message,
      config: {
        systemInstruction: systemPrompt,
        temperature: 0.7
      }
    });

    res.json({ text: response.text });
  } catch (error: any) {
    console.error('Gemini chat API error:', error);
    res.status(500).json({ error: 'AI consultation failed. Please check logs.' });
  }
});

// Serving file uploads
app.use('/uploads', express.static(UPLOADS_DIR));

// -------------------------------------------------------------
// VITE OR PRODUCTION BUILD ROUTING
// -------------------------------------------------------------
async function startServer() {
  if (process.env.NODE_ENV !== 'production') {
    // In dev mode, let Vite serve assets and inject HMR fallback
    const vite = await createViteServer({
      server: { middlewareMode: true },
      appType: 'spa',
    });
    app.use(vite.middlewares);
  } else {
    // In production, serve index.html and compiled client bundle
    const distPath = path.join(process.cwd(), 'dist');
    app.use(express.static(distPath));
    app.get('*', (req, res) => {
      res.sendFile(path.join(distPath, 'index.html'));
    });
  }

  app.listen(PORT, '0.0.0.0', () => {
    console.log(`TAQA Backend server running on port ${PORT}`);
  });
}

startServer();
