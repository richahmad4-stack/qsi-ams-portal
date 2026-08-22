const AMS = {
  VERSION: '1.2.0-sheets',
  TIMEZONE: 'Asia/Riyadh',
  MODULES: ['dashboard', 'clients', 'standards', 'personnel', 'application_reviews', 'proposals', 'contracts', 'audit_programs', 'auditor_appointments', 'audit_plans', 'reports', 'ncrs', 'capas', 'technical_reviews', 'certification_decisions', 'certificates', 'document_templates', 'finance', 'users', 'audit_trail', 'settings'],
  ACTIONS: ['view', 'create', 'edit', 'delete', 'approve', 'reject', 'download', 'print'],
  TABLES: {
    tenants: ['id', 'name', 'legal_name', 'code', 'timezone', 'currency', 'status', 'created_at', 'updated_at'],
    roles: ['id', 'tenant_id', 'code', 'name', 'description', 'system_role', 'created_at', 'updated_at'],
    permissions: ['id', 'module', 'action', 'description', 'created_at'],
    role_permissions: ['id', 'role_code', 'module', 'action', 'created_at'],
    users: ['id', 'tenant_id', 'full_name', 'email', 'phone', 'status', 'primary_role_code', 'last_login_at', 'created_at', 'updated_at'],
    standards: ['id', 'code', 'name', 'version', 'scheme_type', 'active', 'created_at', 'updated_at'],
    clients: ['id', 'tenant_id', 'company', 'legal_name', 'client_code', 'address', 'city', 'country', 'contact_name', 'contact_email', 'contact_phone', 'scope', 'employee_count', 'number_of_sites', 'risk_category', 'status', 'current_stage', 'certificate_number', 'certificate_expiry_date', 'created_at', 'updated_at'],
    client_standards: ['id', 'client_id', 'standard_id', 'status', 'created_at'],
    certification_applications: ['id', 'tenant_id', 'client_id', 'application_number', 'received_date', 'application_payload', 'status', 'submitted_by', 'submitted_at', 'created_at', 'updated_at'],
    application_reviews: ['id', 'tenant_id', 'client_id', 'reviewer_user_id', 'review_date', 'review_payload', 'calculated_days', 'decision', 'status', 'created_at', 'updated_at'],
    proposals: ['id', 'tenant_id', 'client_id', 'proposal_number', 'proposal_date', 'valid_until', 'currency', 'subtotal', 'vat', 'grand_total', 'status', 'payload', 'created_at', 'updated_at'],
    contracts: ['id', 'tenant_id', 'client_id', 'proposal_id', 'contract_number', 'signed_date', 'status', 'payload', 'created_at', 'updated_at'],
    audit_programs: ['id', 'tenant_id', 'client_id', 'program_number', 'cycle_type', 'start_date', 'expiry_date', 'status', 'payload', 'created_at', 'updated_at'],
    audit_events: ['id', 'tenant_id', 'audit_program_id', 'client_id', 'audit_number', 'event_type', 'planned_start_date', 'planned_end_date', 'actual_start_date', 'actual_end_date', 'status', 'payload', 'created_at', 'updated_at'],
    auditor_appointments: ['id', 'tenant_id', 'audit_event_id', 'personnel_id', 'user_id', 'role_in_audit', 'appointment_status', 'conflict_checked', 'payload', 'created_at'],
    audit_plans: ['id', 'tenant_id', 'audit_event_id', 'objective', 'criteria', 'scope', 'status', 'approved_by', 'approved_at', 'created_at', 'updated_at'],
    audit_plan_items: ['id', 'audit_plan_id', 'audit_date', 'start_time', 'end_time', 'process_area', 'clause_reference', 'auditor_name', 'method', 'created_at'],
    integrated_audit_requirements: ['id', 'requirement_code', 'title', 'audit_question', 'evidence_expectation', 'category', 'active'],
    audit_requirement_responses: ['id', 'tenant_id', 'audit_event_id', 'audit_requirement_id', 'conformity_status', 'objective_evidence', 'finding_text', 'auditor_confirmed', 'confirmed_by_user_id', 'confirmed_at', 'created_at', 'updated_at'],
    ncrs: ['id', 'tenant_id', 'audit_event_id', 'audit_requirement_response_id', 'ncr_number', 'clause_reference', 'severity', 'statement', 'correction', 'root_cause', 'corrective_action', 'due_date', 'status', 'closed_by', 'closed_at', 'created_at', 'updated_at'],
    capas: ['id', 'tenant_id', 'ncr_id', 'audit_event_id', 'action_plan', 'evidence_summary', 'effectiveness_review', 'status', 'due_date', 'closed_by', 'closed_at', 'created_at', 'updated_at'],
    technical_reviews: ['id', 'tenant_id', 'client_id', 'audit_event_id', 'reviewer_user_id', 'review_date', 'checklist_payload', 'review_notes', 'recommendation', 'status', 'created_at', 'updated_at'],
    certification_decisions: ['id', 'tenant_id', 'client_id', 'technical_review_id', 'decision_user_id', 'decision_date', 'decision', 'decision_notes', 'gm_approval', 'status', 'created_at', 'updated_at'],
    certificates: ['id', 'tenant_id', 'client_id', 'certification_decision_id', 'certificate_number', 'standard_code', 'scope', 'issue_date', 'expiry_date', 'status', 'verification_token', 'verification_status', 'created_at', 'updated_at'],
    invoices: ['id', 'tenant_id', 'client_id', 'invoice_number', 'invoice_date', 'due_date', 'subtotal', 'vat', 'total', 'status', 'payload', 'created_at', 'updated_at'],
    payments: ['id', 'tenant_id', 'invoice_id', 'payment_date', 'amount', 'method', 'reference', 'status', 'created_at'],
    document_templates: ['id', 'tenant_id', 'template_key', 'title', 'document_type', 'body_html', 'revision', 'active', 'created_at', 'updated_at'],
    generated_documents: ['id', 'tenant_id', 'client_id', 'source_table', 'source_id', 'template_key', 'title', 'drive_file_id', 'pdf_file_id', 'version_no', 'hash_value', 'generated_by', 'generated_at'],
    audit_logs: ['id', 'tenant_id', 'user_id', 'action', 'entity_table', 'entity_id', 'before_json', 'after_json', 'created_at']
  },
  ROLES: [
    ['super_admin', 'Super User', 'Full tenant owner access to every module and action.'],
    ['administrator', 'Administrator', 'Day-to-day certification operations administration.'],
    ['quality_manager', 'Quality Manager', 'Quality system oversight and audit trail review.'],
    ['technical_manager', 'Technical Manager', 'Application review, competence, and technical controls.'],
    ['proposal_officer', 'Proposal Officer', 'Commercial proposals and contracts.'],
    ['lead_auditor', 'Lead Auditor', 'Audit planning, execution, report, NCR, and CAPA.'],
    ['auditor', 'Auditor', 'Assigned audit execution and findings.'],
    ['technical_reviewer', 'Technical Reviewer', 'Independent technical review.'],
    ['certification_decision_maker', 'Certification Decision Maker', 'Certification decision authority.'],
    ['finance', 'Finance', 'Invoices, payments, and commercial summaries.'],
    ['viewer', 'Viewer', 'Read-only access.']
  ],
  STANDARDS: [['ISO 9001:2015', 'ISO 9001', '2015', 'management_system'], ['ISO 14001:2015', 'ISO 14001', '2015', 'management_system'], ['ISO 45001:2018', 'ISO 45001', '2018', 'management_system'], ['ISO 22000:2018', 'ISO 22000', '2018', 'food_safety'], ['HACCP', 'HACCP', '', 'food_safety']],
  REQUIREMENTS: [
    ['QSI-COM-04.03', 'Management system scope', 'Does the documented scope accurately cover sites, activities, products, services, boundaries and justified applicability decisions?', 'Scope statement, certificate comparison, process map, applicability rationale.', 'integrated_management'],
    ['QSI-COM-05.01', 'Leadership and accountability', 'Does top management demonstrate accountability for the management system and certification scope?', 'Policy, objectives, responsibilities, interview evidence, review records.', 'integrated_management'],
    ['QSI-COM-06.01', 'Risk and opportunity planning', 'Are risks and opportunities identified, planned, implemented and reviewed?', 'Risk register, action plans, effectiveness review, changes.', 'integrated_management'],
    ['QSI-COM-07.02', 'Competence', 'Are personnel competent for assigned process and system responsibilities?', 'Competence matrix, training, evaluation, interviews.', 'integrated_management'],
    ['QSI-COM-08.01', 'Operational control', 'Are operational processes planned, controlled, monitored and updated?', 'Procedures, process controls, monitoring records, production/service records.', 'integrated_management'],
    ['QSI-COM-09.02', 'Internal audit', 'Does the internal audit programme cover relevant processes, sites and requirements using competent and impartial auditors?', 'Programme, plans, checklists, reports, auditor competence, follow-up.', 'integrated_management'],
    ['QSI-COM-09.03', 'Management review', 'Does management review consider required inputs and produce decisions/actions?', 'Review minutes, inputs, actions, outputs, improvement decisions.', 'integrated_management'],
    ['QSI-COM-10.02', 'Nonconformity and corrective action', 'Are nonconformities corrected, analyzed, addressed and verified for effectiveness?', 'NCR/CAPA log, root cause, actions, evidence, effectiveness review.', 'integrated_management'],
    ['QSI-FSMS-08.53', 'Validation of control measures', 'Are food-safety control measures validated before implementation and after relevant changes?', 'Validation basis, regulatory criteria, studies, trials, revalidation.', 'food_safety'],
    ['QSI-FSMS-08.90', 'Control of food-safety nonconformity', 'Are affected products evaluated and controlled with corrections and withdrawal/recall readiness?', 'Product disposition, correction/CAPA, recall test, notifications.', 'food_safety'],
    ['QSI-HACCP-P03', 'Validated critical limits', 'Are measurable critical limits established and supported by scientific, regulatory or technical basis?', 'Critical limits, validation studies, legal basis, approval records.', 'haccp'],
    ['QSI-HACCP-P06', 'HACCP validation and verification', 'Is the HACCP plan validated, verified, reviewed and updated after relevant changes?', 'HACCP review, verification records, internal audit, test results.', 'haccp']
  ]
};

function doGet(e) {
  const params = e && e.parameter ? e.parameter : {};
  if (params.verify) return renderVerify(params.verify);
  const tpl = HtmlService.createTemplateFromFile('Index');
  tpl.bootstrap = JSON.stringify(getBootstrap_());
  return tpl.evaluate().setTitle('QSI AMS').setXFrameOptionsMode(HtmlService.XFrameOptionsMode.ALLOWALL);
}

function dispatch(action, payload) {
  if (action === 'seedBaseline' || action === 'setupNoPaidStorage') return seedBaseline_(bootstrapActor_());
  const user = requireUser_();
  const actions = {
    bootstrap: () => getBootstrap_(user),
    dashboard: () => dashboard_(user),
    seedDemoClient: () => seedDemoClient_(user),
    listClients: () => listClients_(user, payload || {}),
    saveClient: () => saveClient_(user, payload || {}),
    clientFile: () => clientFile_(user, Number(payload.clientId)),
    saveApplication: () => saveApplication_(user, payload || {}),
    saveApplicationReview: () => saveApplicationReview_(user, payload || {}),
    saveProposal: () => saveProposal_(user, payload || {}),
    saveContract: () => saveContract_(user, payload || {}),
    createAuditProgram: () => createAuditProgram_(user, payload || {}),
    saveAuditEvent: () => saveAuditEvent_(user, payload || {}),
    saveAppointment: () => saveAppointment_(user, payload || {}),
    saveAuditPlan: () => saveAuditPlan_(user, payload || {}),
    savePlanItem: () => savePlanItem_(user, payload || {}),
    saveRequirementResponse: () => saveRequirementResponse_(user, payload || {}),
    saveNcr: () => saveNcr_(user, payload || {}),
    closeNcr: () => closeRecord_(user, 'ncrs', payload || {}),
    saveCapa: () => saveCapa_(user, payload || {}),
    closeCapa: () => closeRecord_(user, 'capas', payload || {}),
    completeAuditEvent: () => completeAuditEvent_(user, payload || {}),
    saveTechnicalReview: () => saveTechnicalReview_(user, payload || {}),
    saveDecision: () => saveDecision_(user, payload || {}),
    issueCertificate: () => issueCertificate_(user, payload || {}),
    generateDocument: () => generateDocument_(user, payload || {}),
    finance: () => finance_(user),
    saveInvoice: () => saveInvoice_(user, payload || {}),
    savePayment: () => savePayment_(user, payload || {}),
    adminData: () => adminData_(user),
    saveUser: () => saveUser_(user, payload || {}),
    auditTrail: () => auditTrail_(user, payload || {})
  };
  if (!actions[action]) throw new Error('Unknown action: ' + action);
  return actions[action]();
}

function setupNoPaidStorage() {
  return seedBaseline_(bootstrapActor_());
}

function props_() {
  return PropertiesService.getScriptProperties();
}

function store_() {
  if (globalThis.__AMS_STORE) return globalThis.__AMS_STORE;
  const scriptProps = props_();
  let id = scriptProps.getProperty('AMS_DATA_SPREADSHEET_ID');
  const created = !id;
  let ss = id ? SpreadsheetApp.openById(id) : SpreadsheetApp.create('QSI AMS Data');
  if (!id) scriptProps.setProperty('AMS_DATA_SPREADSHEET_ID', ss.getId());
  if (created && !globalThis.__AMS_SHEETS_READY) {
    ensureSheets_(ss);
    scriptProps.setProperty('AMS_SCHEMA_READY', '1');
    globalThis.__AMS_SHEETS_READY = true;
  }
  if (!created && !scriptProps.getProperty('AMS_SCHEMA_READY')) scriptProps.setProperty('AMS_SCHEMA_READY', '1');
  globalThis.__AMS_STORE = ss;
  return ss;
}

function ensureSheets_(ss) {
  Object.keys(AMS.TABLES).forEach(name => {
    let sh = ss.getSheetByName(name);
    if (!sh) sh = ss.insertSheet(name);
    const headers = AMS.TABLES[name];
    const existing = sh.getLastRow() ? sh.getRange(1, 1, 1, Math.max(sh.getLastColumn(), headers.length)).getValues()[0] : [];
    if (existing.slice(0, headers.length).join('|') !== headers.join('|')) {
      sh.clear();
      sh.getRange(1, 1, 1, headers.length).setValues([headers]);
      sh.setFrozenRows(1);
    }
  });
}

function sheet_(table) {
  const sh = store_().getSheetByName(table);
  if (!sh) throw new Error('Missing table: ' + table);
  return sh;
}

function all_(table) {
  const sh = sheet_(table);
  const values = sh.getDataRange().getValues();
  if (values.length < 2) return [];
  const headers = values[0];
  return values.slice(1).filter(row => row.some(cell => cell !== '')).map(row => {
    const obj = {};
    headers.forEach((h, i) => obj[h] = cellValue_(row[i]));
    return obj;
  });
}

function cellValue_(value) {
  if (value === undefined || value === null) return '';
  if (Object.prototype.toString.call(value) === '[object Date]') return Utilities.formatDate(value, AMS.TIMEZONE, 'yyyy-MM-dd HH:mm:ss');
  return value;
}

function insert_(table, data) {
  const sh = sheet_(table);
  const headers = AMS.TABLES[table];
  const id = nextId_(table);
  const row = Object.assign({ id: id, created_at: now_(), updated_at: now_() }, data);
  sh.appendRow(headers.map(h => row[h] === undefined || row[h] === null ? '' : row[h]));
  return id;
}

function insertMany_(table, rows) {
  if (!rows.length) return [];
  const lock = LockService.getScriptLock();
  lock.waitLock(30000);
  try {
    const sh = sheet_(table);
    const headers = AMS.TABLES[table];
    const key = 'NEXT_ID_' + table;
    const start = nextIdStart_(table, sh, key);
    const timestamp = now_();
    const records = rows.map((data, index) => Object.assign({ id: start + index, created_at: timestamp, updated_at: timestamp }, data));
    const values = records.map(row => headers.map(h => row[h] === undefined || row[h] === null ? '' : row[h]));
    sh.getRange(sh.getLastRow() + 1, 1, values.length, headers.length).setValues(values);
    props_().setProperty(key, String(start + rows.length));
    return records.map(row => row.id);
  } finally {
    lock.releaseLock();
  }
}

function update_(table, id, patch) {
  const sh = sheet_(table);
  const headers = AMS.TABLES[table];
  const values = sh.getDataRange().getValues();
  const idCol = headers.indexOf('id');
  for (let r = 1; r < values.length; r++) {
    if (String(values[r][idCol]) === String(id)) {
      headers.forEach((h, c) => {
        if (Object.prototype.hasOwnProperty.call(patch, h)) values[r][c] = patch[h];
      });
      if (headers.indexOf('updated_at') !== -1) values[r][headers.indexOf('updated_at')] = now_();
      sh.getRange(r + 1, 1, 1, headers.length).setValues([values[r].slice(0, headers.length)]);
      return id;
    }
  }
  throw new Error('Record not found in ' + table + ': ' + id);
}

function upsertLatest_(table, keys, data) {
  const rows = all_(table).filter(row => keys.every(k => String(row[k]) === String(data[k])));
  if (rows.length) {
    const row = rows[rows.length - 1];
    update_(table, row.id, data);
    return Number(row.id);
  }
  return insert_(table, data);
}

function nextId_(table) {
  const lock = LockService.getScriptLock();
  lock.waitLock(30000);
  try {
    const key = 'NEXT_ID_' + table;
    const current = nextIdStart_(table, sheet_(table), key);
    props_().setProperty(key, String(current + 1));
    return current;
  } finally {
    lock.releaseLock();
  }
}

function nextIdStart_(table, sh, key) {
  const stored = Number(props_().getProperty(key) || '0');
  if (stored > 0) return stored;
  const headers = AMS.TABLES[table];
  const idCol = headers.indexOf('id') + 1;
  if (sh.getLastRow() < 2 || idCol < 1) return 1;
  const ids = sh.getRange(2, idCol, sh.getLastRow() - 1, 1).getValues().flat().map(Number).filter(Number.isFinite);
  return ids.length ? Math.max.apply(null, ids) + 1 : 1;
}

function oneById_(table, id) {
  return all_(table).find(row => String(row.id) === String(id)) || null;
}

function latest_(table, key, value) {
  const rows = all_(table).filter(r => String(r[key]) === String(value));
  return rows.length ? rows[rows.length - 1] : null;
}

function now_() {
  return Utilities.formatDate(new Date(), AMS.TIMEZONE, 'yyyy-MM-dd HH:mm:ss');
}

function today_() {
  return Utilities.formatDate(new Date(), AMS.TIMEZONE, 'yyyy-MM-dd');
}

function token_() {
  return Utilities.getUuid().replace(/-/g, '');
}

function json_(value) {
  return JSON.stringify(value || {});
}

function activeEmail_() {
  return String(Session.getActiveUser().getEmail() || props_().getProperty('AMS_DEV_USER_EMAIL') || '').toLowerCase();
}

function bootstrapActor_() {
  try {
    return requireUser_();
  } catch (err) {
    return { id: '', tenant_id: 1, email: activeEmail_(), full_name: 'Bootstrap Administrator', roles: ['super_admin'], permissions: fullPermissions_() };
  }
}

function requireUser_() {
  const email = activeEmail_();
  if (!email) throw new Error('No Google user email is available. Set AMS_DEV_USER_EMAIL for bootstrap.');
  const user = all_('users').find(u => String(u.email).toLowerCase() === email && u.status === 'active');
  if (!user) throw new Error('User is not registered in AMS: ' + email);
  user.id = Number(user.id);
  user.tenant_id = Number(user.tenant_id || 1);
  user.roles = [user.primary_role_code || 'viewer'];
  user.permissions = permissionsForRole_(user.primary_role_code || 'viewer');
  update_('users', user.id, { last_login_at: now_() });
  return user;
}

function fullPermissions_() {
  return AMS.MODULES.reduce((acc, module) => {
    acc[module] = AMS.ACTIONS.slice();
    return acc;
  }, {});
}

function permissionsForRole_(roleCode) {
  if (roleCode === 'super_admin') return fullPermissions_();
  const perms = {};
  all_('role_permissions').filter(rp => rp.role_code === roleCode).forEach(rp => {
    if (!perms[rp.module]) perms[rp.module] = [];
    perms[rp.module].push(rp.action);
  });
  return perms;
}

function requirePermission_(user, module, action) {
  if (!user.permissions[module] || user.permissions[module].indexOf(action) === -1) throw new Error('Permission denied: ' + module + ':' + action);
}

function audit_(user, action, table, id, beforeValue, afterValue) {
  insert_('audit_logs', { tenant_id: user ? user.tenant_id : 1, user_id: user ? user.id : '', action: action, entity_table: table, entity_id: id || '', before_json: json_(beforeValue), after_json: json_(afterValue), created_at: now_() });
}

function getBootstrap_(knownUser) {
  let user;
  try {
    user = knownUser || requireUser_();
  } catch (err) {
    user = { error: err.message, email: activeEmail_(), permissions: {}, roles: [] };
  }
  return { app: { version: AMS.VERSION, storage: 'Google Sheets', noPaid: true }, user: user, standards: all_('standards').filter(s => String(s.active) !== '0'), roleOptions: AMS.ROLES.map(r => ({ code: r[0], name: r[1] })) };
}

function seedBaseline_(user) {
  store_();
  seedTenant_();
  seedRoles_();
  seedPermissions_();
  seedStandards_();
  seedRequirements_();
  seedTemplates_();
  ensureRootFolder_();
  ensureBootstrapUser_(user);
  audit_(user, 'seed', 'baseline', '', null, { version: AMS.VERSION, storage: 'sheets' });
  return { message: 'No-paid AMS storage created and baseline seeded', spreadsheetId: props_().getProperty('AMS_DATA_SPREADSHEET_ID'), rootFolderId: props_().getProperty('AMS_ROOT_FOLDER_ID') };
}

function seedTenant_() {
  if (!all_('tenants').some(t => t.code === 'QSI')) insert_('tenants', { name: 'QSI', legal_name: 'QSI Certification Body', code: 'QSI', timezone: AMS.TIMEZONE, currency: 'SAR', status: 'active' });
}

function seedRoles_() {
  const existing = all_('roles').map(r => r.code);
  const rows = AMS.ROLES
    .filter(role => existing.indexOf(role[0]) === -1)
    .map(role => ({ tenant_id: 1, code: role[0], name: role[1], description: role[2], system_role: 1 }));
  insertMany_('roles', rows);
}

function seedPermissions_() {
  const existing = all_('permissions').map(p => p.module + ':' + p.action);
  const permissionRows = [];
  AMS.MODULES.forEach(module => AMS.ACTIONS.forEach(action => {
    const key = module + ':' + action;
    if (existing.indexOf(key) === -1) {
      permissionRows.push({ module: module, action: action, description: action + ' ' + module });
      existing.push(key);
    }
  }));
  insertMany_('permissions', permissionRows);

  const rpExisting = all_('role_permissions').map(rp => rp.role_code + ':' + rp.module + ':' + rp.action);
  const rolePermissionRows = [];
  const addPerms = (role, modules, actions) => {
    modules.forEach(module => actions.forEach(action => {
      const key = role + ':' + module + ':' + action;
      if (rpExisting.indexOf(key) === -1) {
        rolePermissionRows.push({ role_code: role, module: module, action: action });
        rpExisting.push(key);
      }
    }));
  };
  addPerms('super_admin', AMS.MODULES, AMS.ACTIONS);
  addPerms('administrator', AMS.MODULES, AMS.ACTIONS);
  addPerms('viewer', AMS.MODULES, ['view', 'download', 'print']);
  addPerms('quality_manager', ['dashboard', 'clients', 'standards', 'application_reviews', 'audit_programs', 'reports', 'ncrs', 'capas', 'technical_reviews', 'certification_decisions', 'certificates', 'document_templates', 'audit_trail'], AMS.ACTIONS);
  addPerms('technical_manager', ['dashboard', 'clients', 'standards', 'application_reviews', 'audit_programs', 'personnel', 'auditor_appointments', 'audit_plans', 'reports', 'technical_reviews', 'certification_decisions'], AMS.ACTIONS);
  addPerms('proposal_officer', ['dashboard', 'clients', 'application_reviews', 'proposals', 'contracts', 'audit_programs', 'finance', 'document_templates'], AMS.ACTIONS);
  addPerms('lead_auditor', ['dashboard', 'clients', 'audit_programs', 'auditor_appointments', 'audit_plans', 'reports', 'ncrs', 'capas'], AMS.ACTIONS);
  addPerms('auditor', ['dashboard', 'clients', 'audit_plans', 'reports', 'ncrs', 'capas'], AMS.ACTIONS);
  addPerms('technical_reviewer', ['dashboard', 'clients', 'reports', 'ncrs', 'capas', 'technical_reviews'], AMS.ACTIONS);
  addPerms('certification_decision_maker', ['dashboard', 'clients', 'technical_reviews', 'certification_decisions', 'certificates'], AMS.ACTIONS);
  addPerms('finance', ['dashboard', 'clients', 'proposals', 'contracts', 'finance'], AMS.ACTIONS);
  insertMany_('role_permissions', rolePermissionRows);
}

function seedStandards_() {
  const existing = all_('standards').map(s => s.code);
  const rows = AMS.STANDARDS
    .filter(std => existing.indexOf(std[0]) === -1)
    .map(std => ({ code: std[0], name: std[1], version: std[2], scheme_type: std[3], active: 1 }));
  insertMany_('standards', rows);
}

function seedRequirements_() {
  const existing = all_('integrated_audit_requirements').map(r => r.requirement_code);
  const rows = AMS.REQUIREMENTS
    .filter(req => existing.indexOf(req[0]) === -1)
    .map(req => ({ requirement_code: req[0], title: req[1], audit_question: req[2], evidence_expectation: req[3], category: req[4], active: 1 }));
  insertMany_('integrated_audit_requirements', rows);
}

function seedTemplates_() {
  const existing = all_('document_templates').map(t => t.template_key);
  const rows = defaultTemplates_()
    .filter(t => existing.indexOf(t.key) === -1)
    .map(t => ({ tenant_id: 1, template_key: t.key, title: t.title, document_type: t.type, body_html: t.html, revision: '1', active: 1 }));
  insertMany_('document_templates', rows);
}

function ensureRootFolder_() {
  if (!props_().getProperty('AMS_ROOT_FOLDER_ID')) props_().setProperty('AMS_ROOT_FOLDER_ID', DriveApp.createFolder('QSI AMS Generated Documents').getId());
}

function ensureBootstrapUser_(user) {
  const email = String(user.email || activeEmail_()).toLowerCase();
  if (!email) return;
  const found = all_('users').find(u => String(u.email).toLowerCase() === email);
  if (found) update_('users', found.id, { status: 'active', primary_role_code: 'super_admin' });
  else insert_('users', { tenant_id: 1, full_name: user.full_name || 'Bootstrap Administrator', email: email, phone: '', status: 'active', primary_role_code: 'super_admin' });
}

function defaultTemplates_() {
  return [
    { key: 'application_review', title: 'Application Review', type: 'review', html: '<h1>Application Review</h1><p><b>Client:</b> {{client_name}}</p><p><b>Scope:</b> {{scope}}</p><p><b>Decision:</b> {{decision}}</p><p>{{notes}}</p>' },
    { key: 'proposal', title: 'Proposal', type: 'proposal', html: '<h1>Certification Proposal</h1><p>{{client_name}}</p><p>{{scope}}</p><p>Total: {{grand_total}}</p>' },
    { key: 'contract', title: 'Certification Agreement', type: 'contract', html: '<h1>Certification Agreement</h1><p>{{client_name}}</p><p>Contract: {{contract_number}}</p><p>{{scope}}</p>' },
    { key: 'audit_plan', title: 'Audit Plan', type: 'audit_plan', html: '<h1>Audit Plan</h1><p>{{client_name}}</p><p>{{scope}}</p><p>{{audit_timetable}}</p>' },
    { key: 'audit_report', title: 'Audit Report', type: 'report', html: '<h1>Audit Report</h1><p>{{client_name}}</p><p>{{scope}}</p><p>{{report_body}}</p>' },
    { key: 'ncr_capa', title: 'NCR / CAPA', type: 'ncr_capa', html: '<h1>NCR / CAPA</h1><p>{{client_name}}</p><p>{{ncr_summary}}</p><p>{{capa_summary}}</p>' },
    { key: 'technical_review', title: 'Technical Review', type: 'technical_review', html: '<h1>Technical Review</h1><p>{{client_name}}</p><p>{{recommendation}}</p><p>{{notes}}</p>' },
    { key: 'decision', title: 'Certification Decision', type: 'decision', html: '<h1>Certification Decision</h1><p>{{client_name}}</p><p>{{decision}}</p><p>{{notes}}</p>' },
    { key: 'certificate', title: 'Certificate', type: 'certificate', html: '<h1>Certificate</h1><p>This certifies that {{client_name}} has been assessed for {{standard}}.</p><p>{{scope}}</p><p>Certificate: {{certificate_number}}</p><p>Valid: {{issue_date}} to {{expiry_date}}</p><p>{{verification_url}}</p>' }
  ];
}

function tenantRows_(table, user) {
  return all_(table).filter(row => String(row.tenant_id || user.tenant_id) === String(user.tenant_id));
}

function dashboard_(user) {
  requirePermission_(user, 'dashboard', 'view');
  const clients = tenantRows_('clients', user);
  const certs = tenantRows_('certificates', user);
  const ncrs = tenantRows_('ncrs', user);
  const trs = tenantRows_('technical_reviews', user);
  const events = tenantRows_('audit_events', user).map(e => Object.assign({}, e, { company: (oneById_('clients', e.client_id) || {}).company || '' }));
  return {
    counts: { clients: clients.length, activeCertificates: certs.filter(c => c.status === 'active').length, openNcrs: ncrs.filter(n => n.status !== 'closed').length, pendingReviews: trs.filter(t => t.status !== 'completed').length },
    upcoming: events.filter(e => e.status !== 'completed').slice(0, 10),
    recent: tenantRows_('audit_logs', user).slice(-12).reverse().map(a => Object.assign({}, a, { full_name: (oneById_('users', a.user_id) || {}).full_name || '' }))
  };
}

function listClients_(user, payload) {
  requirePermission_(user, 'clients', 'view');
  const term = String(payload.search || '').toLowerCase();
  const seen = {};
  const rows = tenantRows_('clients', user).filter(c => !term || [c.company, c.client_code, c.contact_email].join(' ').toLowerCase().indexOf(term) !== -1);
  return rows.slice().reverse().filter(c => {
    const key = c.client_code || 'id:' + c.id;
    if (seen[key]) return false;
    seen[key] = true;
    return true;
  }).map(c => Object.assign({}, c, { standards: standardsForClient_(c.id).map(s => s.code).join(', ') })).slice(0, 200);
}

function standardsForClient_(clientId) {
  const links = all_('client_standards').filter(cs => String(cs.client_id) === String(clientId) && cs.status !== 'inactive').map(cs => String(cs.standard_id));
  return all_('standards').filter(s => links.indexOf(String(s.id)) !== -1);
}

function saveClient_(user, payload) {
  requirePermission_(user, 'clients', payload.id ? 'edit' : 'create');
  const data = { tenant_id: user.tenant_id, company: payload.company || '', legal_name: payload.legal_name || payload.company || '', client_code: payload.client_code || '', address: payload.address || '', city: payload.city || '', country: payload.country || 'Saudi Arabia', contact_name: payload.contact_name || '', contact_email: payload.contact_email || '', contact_phone: payload.contact_phone || '', scope: payload.scope || '', employee_count: payload.employee_count || '', number_of_sites: payload.number_of_sites || 1, risk_category: payload.risk_category || '', status: payload.status || 'prospect', current_stage: payload.current_stage || 'application' };
  const id = payload.id ? update_('clients', payload.id, data) : insert_('clients', data);
  saveClientStandards_(id, payload.standard_ids || []);
  audit_(user, 'save', 'clients', id, null, data);
  return clientFile_(user, id);
}

function seedDemoClient_(user) {
  requirePermission_(user, 'clients', 'create');
  const data = {
    tenant_id: user.tenant_id,
    company: 'Demo Food Factory Ltd',
    legal_name: 'Demo Food Factory Ltd',
    client_code: 'DEMO-001',
    address: 'Demo Industrial Area, Riyadh, Saudi Arabia',
    city: 'Riyadh',
    country: 'Saudi Arabia',
    contact_name: 'Demo Contact',
    contact_email: 'demo.client@example.com',
    contact_phone: '+966500000000',
    scope: 'Manufacturing and packing of chilled ready-to-eat food products for demo certification workflow testing.',
    employee_count: 75,
    number_of_sites: 1,
    risk_category: 'medium',
    status: 'certified',
    current_stage: 'surveillance_1',
    certificate_number: 'QSI-DEMO-2026-001',
    certificate_expiry_date: '2029-08-21'
  };
  const matches = all_('clients').filter(c => String(c.client_code) === data.client_code && String(c.tenant_id) === String(user.tenant_id));
  const existing = matches.length ? matches[matches.length - 1] : null;
  const id = existing ? update_('clients', existing.id, data) : insert_('clients', data);
  const standardIds = all_('standards').filter(s => ['ISO 22000:2018', 'HACCP'].indexOf(String(s.code)) !== -1).map(s => s.id);
  saveClientStandards_(id, standardIds);
  upsertLatest_('certification_applications', ['tenant_id', 'client_id'], { tenant_id: user.tenant_id, client_id: id, application_number: 'APP-DEMO-001', received_date: today_(), application_payload: json_({ haccp_plans: 2, sites: 1, scope_reviewed: true }), status: 'submitted', submitted_by: user.id, submitted_at: now_() });
  upsertLatest_('application_reviews', ['tenant_id', 'client_id'], { tenant_id: user.tenant_id, client_id: id, reviewer_user_id: user.id, review_date: today_(), review_payload: json_({ competence_available: true, audit_days: 3, decision_basis: 'Demo baseline' }), calculated_days: 3, decision: 'accepted', status: 'completed' });
  seedDemoCycle_(user, id, data);
  audit_(user, existing ? 'update_demo_client' : 'seed_demo_client', 'clients', id, existing || null, data);
  return clientFile_(user, id);
}

function seedDemoCycle_(user, clientId, client) {
  const proposalId = upsertLatest_('proposals', ['tenant_id', 'client_id'], {
    tenant_id: user.tenant_id, client_id: clientId, proposal_number: 'PROP-DEMO-001',
    proposal_date: today_(), valid_until: '2026-09-21', currency: 'SAR',
    subtotal: 18000, vat: 2700, grand_total: 20700, status: 'accepted',
    payload: json_({ audit_days: 3, standards: ['ISO 22000:2018', 'HACCP'] })
  });
  upsertLatest_('contracts', ['tenant_id', 'client_id'], {
    tenant_id: user.tenant_id, client_id: clientId, proposal_id: proposalId,
    contract_number: 'CON-DEMO-001', signed_date: today_(), status: 'signed',
    payload: json_({ signed_by: 'Demo Contact', service: 'Initial certification' })
  });
  const programId = upsertLatest_('audit_programs', ['tenant_id', 'client_id'], {
    tenant_id: user.tenant_id, client_id: clientId, program_number: 'PRG-DEMO-001',
    cycle_type: 'initial', start_date: today_(), expiry_date: '2029-08-21',
    status: 'active', payload: json_({ stage1: true, stage2: true, surveillance1: true, surveillance2: true })
  });
  const eventId = upsertLatest_('audit_events', ['tenant_id', 'client_id', 'event_type'], {
    tenant_id: user.tenant_id, audit_program_id: programId, client_id: clientId,
    audit_number: 'AUD-DEMO-001-S2', event_type: 'stage2',
    planned_start_date: today_(), planned_end_date: today_(),
    actual_start_date: today_(), actual_end_date: today_(), status: 'completed',
    payload: json_({ team_leader: user.full_name || 'Lead Auditor', method: 'onsite' })
  });
  upsertLatest_('auditor_appointments', ['tenant_id', 'audit_event_id', 'role_in_audit'], {
    tenant_id: user.tenant_id, audit_event_id: eventId, personnel_id: '',
    user_id: user.id, role_in_audit: 'lead_auditor', appointment_status: 'appointed',
    conflict_checked: 1, payload: json_({ impartiality: 'No conflict declared' })
  });
  const planId = upsertLatest_('audit_plans', ['tenant_id', 'audit_event_id'], {
    tenant_id: user.tenant_id, audit_event_id: eventId,
    objective: 'Confirm effective implementation of ISO 22000:2018 and HACCP requirements for the approved demo scope.',
    criteria: 'ISO 22000:2018, HACCP/Codex CXC 1-1969, QSI certification rules, client documented system.',
    scope: client.scope, status: 'approved', approved_by: user.id, approved_at: now_()
  });
  [
    ['09:00', '10:30', 'Opening meeting and scope confirmation', 'ISO 22000 4.3', 'onsite'],
    ['10:30', '12:30', 'Receiving, storage and prerequisite programmes', 'ISO 22000 8.2', 'onsite'],
    ['13:30', '15:30', 'HACCP plan validation and CCP monitoring', 'HACCP Principle 3/6', 'onsite'],
    ['15:30', '16:30', 'Closing meeting and finding agreement', 'ISO 22000 10.2', 'onsite']
  ].forEach(item => upsertLatest_('audit_plan_items', ['audit_plan_id', 'process_area'], {
    audit_plan_id: planId, audit_date: today_(), start_time: item[0], end_time: item[1],
    process_area: item[2], clause_reference: item[3], auditor_name: user.full_name || 'Lead Auditor',
    method: item[4]
  }));
  const reqs = all_('integrated_audit_requirements');
  const reqByCode = code => reqs.find(r => r.requirement_code === code) || reqs[0] || {};
  [
    ['QSI-COM-04.03', 'conforming', 'Scope covers chilled ready-to-eat manufacturing, packing, site boundary and outsourced transport controls.', 'Scope was confirmed against application and process map.', true],
    ['QSI-FSMS-08.53', 'conforming', 'Validation files are available for cooking, chilling and metal detection control measures.', 'Validation evidence was sampled and found adequate.', true],
    ['QSI-FSMS-08.90', 'minor_nc', 'Recall test exists but mock recall effectiveness timing was not trended in management review.', 'Minor NC raised for trend review gap.', true],
    ['QSI-HACCP-P06', 'conforming', 'HACCP plan review includes CCP monitoring checks and verification records.', 'Records were sampled for the last production month.', true]
  ].forEach(row => {
    const req = reqByCode(row[0]);
    if (!req.id) return;
    upsertLatest_('audit_requirement_responses', ['audit_event_id', 'audit_requirement_id'], {
      tenant_id: user.tenant_id, audit_event_id: eventId, audit_requirement_id: req.id,
      conformity_status: row[1], objective_evidence: row[2], finding_text: row[3],
      auditor_confirmed: row[4] ? 1 : 0, confirmed_by_user_id: user.id, confirmed_at: now_()
    });
  });
  const ncReq = reqByCode('QSI-FSMS-08.90');
  const ncResponse = latest_('audit_requirement_responses', 'audit_requirement_id', ncReq.id) || {};
  const ncrId = upsertLatest_('ncrs', ['tenant_id', 'ncr_number'], {
    tenant_id: user.tenant_id, audit_event_id: eventId, audit_requirement_response_id: ncResponse.id || '',
    ncr_number: 'NCR-DEMO-001', clause_reference: 'ISO 22000:2018 8.9',
    severity: 'minor', statement: 'Mock recall effectiveness timing is not trended in management review inputs.',
    correction: 'Update the recall test summary to include elapsed-time analysis.',
    root_cause: 'Management review input checklist did not explicitly request mock recall timing trends.',
    corrective_action: 'Revise management review input checklist and review the next mock recall timing trend.',
    due_date: '2026-09-21', status: 'open'
  });
  upsertLatest_('capas', ['tenant_id', 'ncr_id'], {
    tenant_id: user.tenant_id, ncr_id: ncrId, audit_event_id: eventId,
    action_plan: 'QA manager to revise the management review input checklist and include mock recall trend analysis.',
    evidence_summary: 'Draft checklist update and mock recall trend table prepared for next review cycle.',
    effectiveness_review: 'Effectiveness to be checked at surveillance 1.', status: 'in_progress', due_date: '2026-09-21'
  });
  const trId = upsertLatest_('technical_reviews', ['tenant_id', 'client_id'], {
    tenant_id: user.tenant_id, client_id: clientId, audit_event_id: eventId,
    reviewer_user_id: user.id, review_date: today_(),
    checklist_payload: json_({ impartiality: 'confirmed', report_complete: true, ncr_acceptable: true }),
    review_notes: 'Demo technical review confirms file completeness with one minor NC under corrective action.',
    recommendation: 'recommended', status: 'completed'
  });
  const decisionId = upsertLatest_('certification_decisions', ['tenant_id', 'client_id'], {
    tenant_id: user.tenant_id, client_id: clientId, technical_review_id: trId,
    decision_user_id: user.id, decision_date: today_(), decision: 'grant_certification',
    decision_notes: 'Certification granted for the demo scope subject to routine CAPA follow-up.',
    gm_approval: 'approved', status: 'approved'
  });
  upsertLatest_('certificates', ['tenant_id', 'client_id'], {
    tenant_id: user.tenant_id, client_id: clientId, certification_decision_id: decisionId,
    certificate_number: 'QSI-DEMO-2026-001', standard_code: 'ISO 22000:2018 + HACCP',
    scope: client.scope, issue_date: today_(), expiry_date: '2029-08-21',
    status: 'active', verification_token: 'demo' + String(clientId), verification_status: 'valid'
  });
  const invoiceId = upsertLatest_('invoices', ['tenant_id', 'invoice_number'], {
    tenant_id: user.tenant_id, client_id: clientId, invoice_number: 'INV-DEMO-001',
    invoice_date: today_(), due_date: '2026-09-21', subtotal: 18000, vat: 2700,
    total: 20700, status: 'issued', payload: json_({ linked_proposal: 'PROP-DEMO-001' })
  });
  upsertLatest_('payments', ['tenant_id', 'reference'], {
    tenant_id: user.tenant_id, invoice_id: invoiceId, payment_date: today_(),
    amount: 10000, method: 'bank_transfer', reference: 'PAY-DEMO-001', status: 'partial'
  });
}

function saveClientStandards_(clientId, standardIds) {
  const keep = (standardIds || []).map(String);
  all_('client_standards').filter(cs => String(cs.client_id) === String(clientId)).forEach(cs => {
    if (keep.indexOf(String(cs.standard_id)) === -1) update_('client_standards', cs.id, { status: 'inactive' });
  });
  keep.forEach(id => {
    const existing = all_('client_standards').find(cs => String(cs.client_id) === String(clientId) && String(cs.standard_id) === id);
    if (existing) update_('client_standards', existing.id, { status: 'active' });
    else insert_('client_standards', { client_id: clientId, standard_id: id, status: 'active' });
  });
}

function clientFile_(user, clientId) {
  requirePermission_(user, 'clients', 'view');
  const client = oneById_('clients', clientId);
  if (!client || String(client.tenant_id) !== String(user.tenant_id)) throw new Error('Client not found');
  const events = all_('audit_events').filter(e => String(e.client_id) === String(clientId));
  const eventIds = events.map(e => String(e.id));
  const plans = all_('audit_plans').filter(p => eventIds.indexOf(String(p.audit_event_id)) !== -1);
  const planIds = plans.map(p => String(p.id));
  const responses = all_('audit_requirement_responses').filter(r => eventIds.indexOf(String(r.audit_event_id)) !== -1).map(r => {
    const req = oneById_('integrated_audit_requirements', r.audit_requirement_id) || {};
    return Object.assign({}, r, { requirement_code: req.requirement_code || '', title: req.title || '' });
  });
  return {
    client: client,
    standards: standardsForClient_(clientId),
    application: latest_('certification_applications', 'client_id', clientId),
    review: latest_('application_reviews', 'client_id', clientId),
    proposal: latest_('proposals', 'client_id', clientId),
    contract: latest_('contracts', 'client_id', clientId),
    programs: all_('audit_programs').filter(r => String(r.client_id) === String(clientId)).reverse(),
    events: events,
    appointments: all_('auditor_appointments').filter(a => eventIds.indexOf(String(a.audit_event_id)) !== -1),
    plans: plans,
    planItems: all_('audit_plan_items').filter(i => planIds.indexOf(String(i.audit_plan_id)) !== -1),
    requirements: all_('integrated_audit_requirements').filter(r => String(r.active) !== '0'),
    responses: responses,
    ncrs: all_('ncrs').filter(n => eventIds.indexOf(String(n.audit_event_id)) !== -1).reverse(),
    capas: all_('capas').filter(c => eventIds.indexOf(String(c.audit_event_id)) !== -1).reverse(),
    technicalReview: latest_('technical_reviews', 'client_id', clientId),
    decision: latest_('certification_decisions', 'client_id', clientId),
    certificates: all_('certificates').filter(c => String(c.client_id) === String(clientId)).reverse(),
    documents: all_('generated_documents').filter(d => String(d.client_id) === String(clientId)).reverse(),
    invoices: all_('invoices').filter(i => String(i.client_id) === String(clientId)).reverse()
  };
}

function stage_(user, clientId, stage) {
  update_('clients', clientId, { current_stage: stage });
}

function saveApplication_(user, payload) {
  requirePermission_(user, 'clients', 'edit');
  const id = upsertLatest_('certification_applications', ['tenant_id', 'client_id'], { tenant_id: user.tenant_id, client_id: payload.clientId, application_number: payload.application_number || 'APP-' + payload.clientId, received_date: payload.received_date || today_(), application_payload: json_(payload.application || payload), status: payload.status || 'submitted', submitted_by: user.id, submitted_at: now_() });
  stage_(user, payload.clientId, 'application_review');
  audit_(user, 'save_application', 'certification_applications', id, null, payload);
  return clientFile_(user, payload.clientId);
}

function saveApplicationReview_(user, payload) {
  requirePermission_(user, 'application_reviews', 'edit');
  const id = upsertLatest_('application_reviews', ['tenant_id', 'client_id'], { tenant_id: user.tenant_id, client_id: payload.clientId, reviewer_user_id: user.id, review_date: payload.review_date || today_(), review_payload: json_(payload.review || payload), calculated_days: payload.calculated_days || '', decision: payload.decision || 'accepted', status: payload.status || 'completed' });
  stage_(user, payload.clientId, 'proposal');
  audit_(user, 'save_application_review', 'application_reviews', id, null, payload);
  return clientFile_(user, payload.clientId);
}

function saveProposal_(user, payload) {
  requirePermission_(user, 'proposals', 'edit');
  const subtotal = Number(payload.subtotal || 0);
  const vat = Number(payload.vat || subtotal * 0.15);
  const id = upsertLatest_('proposals', ['tenant_id', 'client_id'], { tenant_id: user.tenant_id, client_id: payload.clientId, proposal_number: payload.proposal_number || 'PROP-' + payload.clientId, proposal_date: payload.proposal_date || today_(), valid_until: payload.valid_until || '', currency: 'SAR', subtotal: subtotal, vat: vat, grand_total: Number(payload.grand_total || subtotal + vat), status: payload.status || 'issued', payload: json_(payload) });
  stage_(user, payload.clientId, 'contract');
  audit_(user, 'save_proposal', 'proposals', id, null, payload);
  return clientFile_(user, payload.clientId);
}

function saveContract_(user, payload) {
  requirePermission_(user, 'contracts', 'edit');
  const id = upsertLatest_('contracts', ['tenant_id', 'client_id'], { tenant_id: user.tenant_id, client_id: payload.clientId, proposal_id: payload.proposal_id || '', contract_number: payload.contract_number || 'CON-' + payload.clientId, signed_date: payload.signed_date || today_(), status: payload.status || 'signed', payload: json_(payload) });
  stage_(user, payload.clientId, 'audit_program');
  audit_(user, 'save_contract', 'contracts', id, null, payload);
  return clientFile_(user, payload.clientId);
}

function createAuditProgram_(user, payload) {
  requirePermission_(user, 'audit_programs', 'create');
  const id = insert_('audit_programs', { tenant_id: user.tenant_id, client_id: payload.clientId, program_number: payload.program_number || 'PRG-' + payload.clientId, cycle_type: payload.cycle_type || 'initial', start_date: payload.start_date || today_(), expiry_date: payload.expiry_date || '', status: payload.status || 'planned', payload: json_(payload) });
  stage_(user, payload.clientId, 'appointments');
  audit_(user, 'create', 'audit_programs', id, null, payload);
  return clientFile_(user, payload.clientId);
}

function saveAuditEvent_(user, payload) {
  requirePermission_(user, 'audit_programs', 'edit');
  const data = { tenant_id: user.tenant_id, audit_program_id: payload.audit_program_id, client_id: payload.clientId, audit_number: payload.audit_number || 'AUD-' + payload.clientId + '-' + token_().slice(0, 6), event_type: payload.event_type || 'stage2', planned_start_date: payload.planned_start_date || '', planned_end_date: payload.planned_end_date || '', actual_start_date: payload.actual_start_date || '', actual_end_date: payload.actual_end_date || '', status: payload.status || 'planned', payload: json_(payload) };
  const id = payload.id ? update_('audit_events', payload.id, data) : insert_('audit_events', data);
  audit_(user, 'save', 'audit_events', id, null, payload);
  return clientFile_(user, payload.clientId);
}

function saveAppointment_(user, payload) {
  requirePermission_(user, 'auditor_appointments', 'edit');
  const id = insert_('auditor_appointments', { tenant_id: user.tenant_id, audit_event_id: payload.audit_event_id, personnel_id: payload.personnel_id || '', user_id: payload.user_id || '', role_in_audit: payload.role_in_audit || 'auditor', appointment_status: payload.appointment_status || 'appointed', conflict_checked: payload.conflict_checked ? 1 : 0, payload: json_(payload) });
  audit_(user, 'save', 'auditor_appointments', id, null, payload);
  return clientFile_(user, payload.clientId);
}

function saveAuditPlan_(user, payload) {
  requirePermission_(user, 'audit_plans', 'edit');
  const id = upsertLatest_('audit_plans', ['tenant_id', 'audit_event_id'], { tenant_id: user.tenant_id, audit_event_id: payload.audit_event_id, objective: payload.objective || '', criteria: payload.criteria || '', scope: payload.scope || '', status: payload.status || 'draft', approved_by: payload.status === 'approved' ? user.id : '', approved_at: payload.status === 'approved' ? now_() : '' });
  audit_(user, 'save', 'audit_plans', id, null, payload);
  return clientFile_(user, payload.clientId);
}

function savePlanItem_(user, payload) {
  requirePermission_(user, 'audit_plans', 'edit');
  const id = insert_('audit_plan_items', { audit_plan_id: payload.audit_plan_id, audit_date: payload.audit_date || '', start_time: payload.start_time || '', end_time: payload.end_time || '', process_area: payload.process_area || '', clause_reference: payload.clause_reference || '', auditor_name: payload.auditor_name || '', method: payload.method || 'onsite' });
  audit_(user, 'save', 'audit_plan_items', id, null, payload);
  return clientFile_(user, payload.clientId);
}

function saveRequirementResponse_(user, payload) {
  requirePermission_(user, 'reports', 'edit');
  const data = { tenant_id: user.tenant_id, audit_event_id: payload.audit_event_id, audit_requirement_id: payload.audit_requirement_id, conformity_status: payload.conformity_status || 'conforming', objective_evidence: payload.objective_evidence || '', finding_text: payload.finding_text || '', auditor_confirmed: payload.auditor_confirmed ? 1 : 0, confirmed_by_user_id: payload.auditor_confirmed ? user.id : '', confirmed_at: payload.auditor_confirmed ? now_() : '' };
  const id = upsertLatest_('audit_requirement_responses', ['audit_event_id', 'audit_requirement_id'], data);
  audit_(user, 'save', 'audit_requirement_responses', id, null, payload);
  return clientFile_(user, payload.clientId);
}

function saveNcr_(user, payload) {
  requirePermission_(user, 'ncrs', 'edit');
  const id = insert_('ncrs', { tenant_id: user.tenant_id, audit_event_id: payload.audit_event_id, audit_requirement_response_id: payload.audit_requirement_response_id || '', ncr_number: payload.ncr_number || 'NCR-' + token_().slice(0, 8), clause_reference: payload.clause_reference || '', severity: payload.severity || 'minor', statement: payload.statement || '', correction: payload.correction || '', root_cause: payload.root_cause || '', corrective_action: payload.corrective_action || '', due_date: payload.due_date || '', status: payload.status || 'open' });
  audit_(user, 'save', 'ncrs', id, null, payload);
  return clientFile_(user, payload.clientId);
}

function saveCapa_(user, payload) {
  requirePermission_(user, 'capas', 'edit');
  const id = insert_('capas', { tenant_id: user.tenant_id, ncr_id: payload.ncr_id || '', audit_event_id: payload.audit_event_id, action_plan: payload.action_plan || '', evidence_summary: payload.evidence_summary || '', effectiveness_review: payload.effectiveness_review || '', due_date: payload.due_date || '', status: payload.status || 'open' });
  audit_(user, 'save', 'capas', id, null, payload);
  return clientFile_(user, payload.clientId);
}

function closeRecord_(user, table, payload) {
  requirePermission_(user, table === 'ncrs' ? 'ncrs' : 'capas', 'edit');
  update_(table, payload.id, { status: 'closed', closed_by: user.id, closed_at: now_() });
  audit_(user, 'close', table, payload.id, null, payload);
  return clientFile_(user, payload.clientId);
}

function completeAuditEvent_(user, payload) {
  requirePermission_(user, 'reports', 'edit');
  update_('audit_events', payload.audit_event_id, { status: 'completed', actual_end_date: today_() });
  stage_(user, payload.clientId, 'technical_review');
  audit_(user, 'complete', 'audit_events', payload.audit_event_id, null, payload);
  return clientFile_(user, payload.clientId);
}

function saveTechnicalReview_(user, payload) {
  requirePermission_(user, 'technical_reviews', 'edit');
  const id = upsertLatest_('technical_reviews', ['tenant_id', 'client_id'], { tenant_id: user.tenant_id, client_id: payload.clientId, audit_event_id: payload.audit_event_id || '', reviewer_user_id: user.id, review_date: payload.review_date || today_(), checklist_payload: json_(payload.checklist || payload), review_notes: payload.review_notes || '', recommendation: payload.recommendation || 'recommended', status: payload.status || 'completed' });
  stage_(user, payload.clientId, 'decision');
  audit_(user, 'save', 'technical_reviews', id, null, payload);
  return clientFile_(user, payload.clientId);
}

function saveDecision_(user, payload) {
  requirePermission_(user, 'certification_decisions', 'edit');
  const id = upsertLatest_('certification_decisions', ['tenant_id', 'client_id'], { tenant_id: user.tenant_id, client_id: payload.clientId, technical_review_id: payload.technical_review_id || '', decision_user_id: user.id, decision_date: payload.decision_date || today_(), decision: payload.decision || 'grant_certification', decision_notes: payload.decision_notes || '', gm_approval: payload.gm_approval || '', status: payload.status || 'approved' });
  stage_(user, payload.clientId, 'certificate');
  audit_(user, 'save', 'certification_decisions', id, null, payload);
  return clientFile_(user, payload.clientId);
}

function issueCertificate_(user, payload) {
  requirePermission_(user, 'certificates', 'create');
  const certNo = payload.certificate_number || 'QSI-' + new Date().getFullYear() + '-' + payload.clientId;
  const id = insert_('certificates', { tenant_id: user.tenant_id, client_id: payload.clientId, certification_decision_id: payload.certification_decision_id || '', certificate_number: certNo, standard_code: payload.standard_code || '', scope: payload.scope || '', issue_date: payload.issue_date || today_(), expiry_date: payload.expiry_date || '', status: payload.status || 'active', verification_token: token_(), verification_status: 'valid' });
  update_('clients', payload.clientId, { status: 'certified', certificate_number: certNo, certificate_expiry_date: payload.expiry_date || '', current_stage: 'surveillance_1' });
  audit_(user, 'issue', 'certificates', id, null, payload);
  return clientFile_(user, payload.clientId);
}

function generateDocument_(user, payload) {
  requirePermission_(user, 'document_templates', 'download');
  ensureRootFolder_();
  const client = oneById_('clients', payload.clientId);
  const template = all_('document_templates').find(t => t.template_key === payload.template_key && String(t.active) !== '0');
  if (!client || !template) throw new Error('Client or template not found');
  const folder = clientFolder_(DriveApp.getFolderById(props_().getProperty('AMS_ROOT_FOLDER_ID')), client);
  const merged = mergeTemplate_(template.body_html, documentContext_(client, payload));
  const title = template.title + ' - ' + client.company + ' - ' + today_();
  const doc = DocumentApp.create(title);
  const body = doc.getBody();
  body.clear();
  body.appendParagraph('QSI-CERT').setHeading(DocumentApp.ParagraphHeading.HEADING1);
  body.appendParagraph(template.title).setHeading(DocumentApp.ParagraphHeading.HEADING2);
  htmlToParagraphs_(body, merged);
  doc.saveAndClose();
  const file = DriveApp.getFileById(doc.getId());
  folder.addFile(file);
  DriveApp.getRootFolder().removeFile(file);
  const pdf = folder.createFile(file.getBlob().getAs(MimeType.PDF)).setName(title + '.pdf');
  const id = insert_('generated_documents', { tenant_id: user.tenant_id, client_id: client.id, source_table: payload.source_table || '', source_id: payload.source_id || '', template_key: payload.template_key, title: title, drive_file_id: file.getId(), pdf_file_id: pdf.getId(), version_no: 1, hash_value: sha256Hex_(merged), generated_by: user.id, generated_at: now_() });
  audit_(user, 'generate', 'generated_documents', id, null, { template: payload.template_key, file: file.getId(), pdf: pdf.getId() });
  return { documentUrl: file.getUrl(), pdfUrl: pdf.getUrl(), id: id };
}

function clientFolder_(root, client) {
  const name = (client.client_code || client.id) + ' - ' + client.company;
  const folders = root.getFoldersByName(name);
  return folders.hasNext() ? folders.next() : root.createFolder(name);
}

function documentContext_(client, payload) {
  return Object.assign({ client_name: client.company, scope: client.scope || '', standard: payload.standard_code || '', certificate_number: payload.certificate_number || client.certificate_number || '', issue_date: payload.issue_date || today_(), expiry_date: payload.expiry_date || client.certificate_expiry_date || '', verification_url: props_().getProperty('AMS_PUBLIC_BASE_URL') || '', decision: payload.decision || '', notes: payload.notes || '', recommendation: payload.recommendation || '', grand_total: payload.grand_total || '', audit_timetable: payload.audit_timetable || '', report_body: payload.report_body || '', ncr_summary: payload.ncr_summary || '', capa_summary: payload.capa_summary || '', contract_number: payload.contract_number || '' }, payload.context || {});
}

function mergeTemplate_(html, context) {
  return String(html || '').replace(/\{\{([a-zA-Z0-9_]+)\}\}/g, (_, key) => context[key] == null ? '' : String(context[key]));
}

function htmlToParagraphs_(body, html) {
  String(html).replace(/<br\s*\/?>/gi, '\n').replace(/<\/p>/gi, '\n').replace(/<[^>]+>/g, '').split(/\n+/).forEach(line => {
    if (line.trim()) body.appendParagraph(line.trim());
  });
}

function renderVerify(token) {
  const cert = all_('certificates').find(c => c.verification_token === token);
  const client = cert ? oneById_('clients', cert.client_id) : null;
  const valid = cert && cert.status === 'active' && cert.verification_status === 'valid';
  const body = '<!doctype html><html><head><base target="_top"><style>body{font-family:Arial,sans-serif;margin:40px;color:#1f2937}.box{max-width:760px;border:1px solid #d1d5db;padding:24px;border-radius:8px}dt{font-weight:bold;margin-top:14px}</style></head><body><div class="box"><h1>' + (valid ? 'Valid certificate' : 'Certificate not valid') + '</h1>' + (cert ? '<dl><dt>Client</dt><dd>' + escapeHtml_(client ? client.company : '') + '</dd><dt>Certificate</dt><dd>' + escapeHtml_(cert.certificate_number) + '</dd><dt>Standard</dt><dd>' + escapeHtml_(cert.standard_code) + '</dd><dt>Scope</dt><dd>' + escapeHtml_(cert.scope) + '</dd><dt>Issue date</dt><dd>' + escapeHtml_(cert.issue_date) + '</dd><dt>Expiry date</dt><dd>' + escapeHtml_(cert.expiry_date) + '</dd><dt>Status</dt><dd>' + escapeHtml_(cert.status) + '</dd></dl>' : '<p>No active certificate matched this verification token.</p>') + '</div></body></html>';
  return HtmlService.createHtmlOutput(body).setTitle('Certificate Verification');
}

function finance_(user) {
  requirePermission_(user, 'finance', 'view');
  return {
    invoices: tenantRows_('invoices', user).map(i => Object.assign({}, i, { company: (oneById_('clients', i.client_id) || {}).company || '' })).reverse(),
    payments: tenantRows_('payments', user).map(p => {
      const invoice = oneById_('invoices', p.invoice_id) || {};
      const client = oneById_('clients', invoice.client_id) || {};
      return Object.assign({}, p, { invoice_number: invoice.invoice_number || '', company: client.company || '' });
    }).reverse()
  };
}

function saveInvoice_(user, payload) {
  requirePermission_(user, 'finance', 'edit');
  const subtotal = Number(payload.subtotal || 0);
  const vat = Number(payload.vat || subtotal * 0.15);
  const id = insert_('invoices', { tenant_id: user.tenant_id, client_id: payload.clientId, invoice_number: payload.invoice_number || 'INV-' + payload.clientId + '-' + token_().slice(0, 5), invoice_date: payload.invoice_date || today_(), due_date: payload.due_date || '', subtotal: subtotal, vat: vat, total: Number(payload.total || subtotal + vat), status: payload.status || 'issued', payload: json_(payload) });
  audit_(user, 'save', 'invoices', id, null, payload);
  return finance_(user);
}

function savePayment_(user, payload) {
  requirePermission_(user, 'finance', 'edit');
  const id = insert_('payments', { tenant_id: user.tenant_id, invoice_id: payload.invoice_id, payment_date: payload.payment_date || today_(), amount: Number(payload.amount || 0), method: payload.method || '', reference: payload.reference || '', status: payload.status || 'posted' });
  audit_(user, 'save', 'payments', id, null, payload);
  return finance_(user);
}

function adminData_(user) {
  requirePermission_(user, 'users', 'view');
  return { users: tenantRows_('users', user), roles: tenantRows_('roles', user) };
}

function saveUser_(user, payload) {
  requirePermission_(user, 'users', payload.id ? 'edit' : 'create');
  const data = { tenant_id: user.tenant_id, full_name: payload.full_name || '', email: String(payload.email || '').toLowerCase(), phone: payload.phone || '', status: payload.status || 'active', primary_role_code: payload.primary_role_code || 'viewer' };
  const id = payload.id ? update_('users', payload.id, data) : insert_('users', data);
  audit_(user, 'save', 'users', id, null, payload);
  return adminData_(user);
}

function auditTrail_(user, payload) {
  requirePermission_(user, 'audit_trail', 'view');
  return tenantRows_('audit_logs', user).slice(-Number(payload.limit || 200)).reverse().map(a => Object.assign({}, a, { full_name: (oneById_('users', a.user_id) || {}).full_name || '' }));
}

function escapeHtml_(value) {
  return String(value || '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}

function sha256Hex_(value) {
  return Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, value).map(byte => {
    const unsigned = byte < 0 ? byte + 256 : byte;
    return ('0' + unsigned.toString(16)).slice(-2);
  }).join('');
}
