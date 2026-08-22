const AMS = {
  VERSION: '1.0.0-appscript',
  TENANT_CODE: 'QSI',
  TIMEZONE: 'Asia/Riyadh',
  MODULES: [
    'dashboard', 'clients', 'standards', 'personnel', 'application_reviews',
    'proposals', 'contracts', 'audit_programs', 'auditor_appointments',
    'audit_plans', 'reports', 'ncrs', 'capas', 'technical_reviews',
    'certification_decisions', 'certificates', 'document_templates',
    'finance', 'users', 'audit_trail', 'settings'
  ],
  ACTIONS: ['view', 'create', 'edit', 'delete', 'approve', 'reject', 'download', 'print'],
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
  STANDARDS: [
    ['ISO 9001:2015', 'ISO 9001', '2015', 'management_system'],
    ['ISO 14001:2015', 'ISO 14001', '2015', 'management_system'],
    ['ISO 45001:2018', 'ISO 45001', '2018', 'management_system'],
    ['ISO 22000:2018', 'ISO 22000', '2018', 'food_safety'],
    ['HACCP', 'HACCP', '', 'food_safety']
  ],
  WORKFLOW_STAGES: [
    'application', 'application_review', 'proposal', 'contract', 'audit_program',
    'appointments', 'audit_plan', 'stage1', 'stage2', 'ncr_capa',
    'technical_review', 'decision', 'certificate', 'surveillance_1',
    'surveillance_2', 'recertification', 'feedback'
  ],
  REQUIREMENTS: [
    ['QSI-COM-04.03', 'Management system scope', 'Does the documented scope accurately cover sites, activities, products, services, boundaries and justified applicability decisions?', 'Scope statement, certificate comparison, process map, applicability rationale.', 'integrated_management'],
    ['QSI-COM-05.01', 'Leadership and accountability', 'Does top management demonstrate accountability for the management system and certification scope?', 'Policy, objectives, responsibilities, interview evidence, review records.', 'integrated_management'],
    ['QSI-COM-06.01', 'Risk and opportunity planning', 'Are risks and opportunities identified, planned, implemented and reviewed?', 'Risk register, action plans, effectiveness review, changes.', 'integrated_management'],
    ['QSI-COM-07.02', 'Competence', 'Are personnel competent for assigned process and system responsibilities?', 'Competence matrix, training, evaluation, interviews.', 'integrated_management'],
    ['QSI-COM-08.01', 'Operational control', 'Are operational processes planned, controlled, monitored and updated?', 'Procedures, process controls, monitoring records, production/service records.', 'integrated_management'],
    ['QSI-COM-09.01', 'Monitoring and measurement', 'Does the organization monitor, measure, analyze and evaluate performance?', 'KPIs, monitoring plan, calibration where relevant, trend review.', 'integrated_management'],
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
  if (params.verify) {
    return renderVerify(params.verify);
  }
  const tpl = HtmlService.createTemplateFromFile('Index');
  tpl.bootstrap = JSON.stringify(getBootstrap_());
  return tpl.evaluate()
    .setTitle('QSI AMS')
    .setXFrameOptionsMode(HtmlService.XFrameOptionsMode.ALLOWALL);
}

function doPost(e) {
  try {
    const payload = e && e.postData && e.postData.contents ? JSON.parse(e.postData.contents) : {};
    const result = dispatch(payload.action, payload.payload || {});
    return ContentService.createTextOutput(JSON.stringify({ ok: true, result: result }))
      .setMimeType(ContentService.MimeType.JSON);
  } catch (err) {
    return ContentService.createTextOutput(JSON.stringify({ ok: false, error: String(err.message || err) }))
      .setMimeType(ContentService.MimeType.JSON);
  }
}

function dispatch(action, payload) {
  if (action === 'seedBaseline') {
    return seedBaseline_(bootstrapActor_());
  }
  const user = requireUser_();
  const actions = {
    bootstrap: () => getBootstrap_(user),
    dashboard: () => dashboard_(user),
    listClients: () => listClients_(user, payload),
    saveClient: () => saveClient_(user, payload),
    clientFile: () => clientFile_(user, payload.clientId),
    saveApplication: () => saveApplication_(user, payload),
    saveApplicationReview: () => saveApplicationReview_(user, payload),
    saveProposal: () => saveProposal_(user, payload),
    saveContract: () => saveContract_(user, payload),
    createAuditProgram: () => createAuditProgram_(user, payload),
    saveAuditEvent: () => saveAuditEvent_(user, payload),
    saveAppointment: () => saveAppointment_(user, payload),
    saveAuditPlan: () => saveAuditPlan_(user, payload),
    savePlanItem: () => savePlanItem_(user, payload),
    saveRequirementResponse: () => saveRequirementResponse_(user, payload),
    saveNcr: () => saveNcr_(user, payload),
    closeNcr: () => closeNcr_(user, payload),
    saveCapa: () => saveCapa_(user, payload),
    closeCapa: () => closeCapa_(user, payload),
    completeAuditEvent: () => completeAuditEvent_(user, payload),
    saveTechnicalReview: () => saveTechnicalReview_(user, payload),
    saveDecision: () => saveDecision_(user, payload),
    issueCertificate: () => issueCertificate_(user, payload),
    generateDocument: () => generateDocument_(user, payload),
    finance: () => finance_(user),
    saveInvoice: () => saveInvoice_(user, payload),
    savePayment: () => savePayment_(user, payload),
    adminData: () => adminData_(user),
    saveUser: () => saveUser_(user, payload),
    auditTrail: () => auditTrail_(user, payload)
  };
  if (!actions[action]) {
    throw new Error('Unknown action: ' + action);
  }
  return actions[action]();
}

function include(filename) {
  return HtmlService.createHtmlOutputFromFile(filename).getContent();
}

function getProps_() {
  return PropertiesService.getScriptProperties();
}

function db_() {
  const props = getProps_();
  const instance = props.getProperty('CLOUD_SQL_CONNECTION_NAME');
  const name = props.getProperty('DB_NAME') || 'qsi_ams';
  const user = props.getProperty('DB_USER');
  const password = props.getProperty('DB_PASSWORD');
  if (!instance || !user || !password) {
    throw new Error('Database properties are incomplete. Set CLOUD_SQL_CONNECTION_NAME, DB_NAME, DB_USER and DB_PASSWORD.');
  }
  const url = 'jdbc:google:mysql://' + instance + '/' + name + '?useUnicode=true&characterEncoding=UTF-8';
  return Jdbc.getCloudSqlConnection(url, user, password);
}

function query_(sql, params) {
  const conn = db_();
  try {
    const stmt = conn.prepareStatement(sql);
    bind_(stmt, params || []);
    const rs = stmt.executeQuery();
    const rows = rows_(rs);
    rs.close();
    stmt.close();
    return rows;
  } finally {
    conn.close();
  }
}

function execute_(sql, params) {
  const conn = db_();
  try {
    const stmt = conn.prepareStatement(sql, Jdbc.Statement.RETURN_GENERATED_KEYS);
    bind_(stmt, params || []);
    const count = stmt.executeUpdate();
    let id = null;
    const keys = stmt.getGeneratedKeys();
    if (keys && keys.next()) {
      id = Number(keys.getLong(1));
    }
    if (keys) keys.close();
    stmt.close();
    return { affected: count, id: id };
  } finally {
    conn.close();
  }
}

function executeMany_(items) {
  const conn = db_();
  try {
    conn.setAutoCommit(false);
    const out = [];
    items.forEach(function(item) {
      const stmt = conn.prepareStatement(item.sql, Jdbc.Statement.RETURN_GENERATED_KEYS);
      bind_(stmt, item.params || []);
      const count = stmt.executeUpdate();
      let id = null;
      const keys = stmt.getGeneratedKeys();
      if (keys && keys.next()) id = Number(keys.getLong(1));
      if (keys) keys.close();
      stmt.close();
      out.push({ affected: count, id: id });
    });
    conn.commit();
    return out;
  } catch (err) {
    conn.rollback();
    throw err;
  } finally {
    conn.close();
  }
}

function bind_(stmt, params) {
  params.forEach(function(value, idx) {
    const pos = idx + 1;
    if (value === null || value === undefined) stmt.setNull(pos, Jdbc.Types.VARCHAR);
    else if (typeof value === 'number') stmt.setDouble(pos, value);
    else if (typeof value === 'boolean') stmt.setInt(pos, value ? 1 : 0);
    else stmt.setString(pos, String(value));
  });
}

function rows_(rs) {
  const meta = rs.getMetaData();
  const count = meta.getColumnCount();
  const rows = [];
  while (rs.next()) {
    const row = {};
    for (let i = 1; i <= count; i++) {
      row[meta.getColumnLabel(i)] = rs.getString(i);
    }
    rows.push(row);
  }
  return rows;
}

function one_(sql, params) {
  const rows = query_(sql, params);
  return rows.length ? rows[0] : null;
}

function json_(value) {
  return JSON.stringify(value || {});
}

function parseJson_(value, fallback) {
  if (!value) return fallback || {};
  try {
    return JSON.parse(value);
  } catch (err) {
    return fallback || {};
  }
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

function activeEmail_() {
  const email = Session.getActiveUser().getEmail();
  return (email || getProps_().getProperty('AMS_DEV_USER_EMAIL') || '').toLowerCase();
}

function requireUser_() {
  const email = activeEmail_();
  if (!email) {
    throw new Error('No Google user email is available. Deploy for domain users or set AMS_DEV_USER_EMAIL for bootstrap.');
  }
  const user = one_(
    "SELECT u.*, t.code AS tenant_code, t.name AS tenant_name FROM users u JOIN tenants t ON t.id = u.tenant_id WHERE LOWER(u.email) = ? AND u.status = 'active' LIMIT 1",
    [email]
  );
  if (!user) {
    throw new Error('User is not registered in AMS: ' + email);
  }
  user.id = Number(user.id);
  user.tenant_id = Number(user.tenant_id);
  user.roles = query_(
    'SELECT r.code, r.name FROM user_role_assignments ura JOIN roles r ON r.id = ura.role_id WHERE ura.user_id = ?',
    [user.id]
  ).map(function(r) { return r.code; });
  if (user.roles.indexOf(user.primary_role_code) === -1) {
    user.roles.push(user.primary_role_code);
  }
  user.permissions = permissionsForUser_(user);
  execute_('UPDATE users SET last_login_at = ? WHERE id = ?', [now_(), user.id]);
  return user;
}

function permissionsForUser_(user) {
  if (user.roles.indexOf('super_admin') !== -1) {
    return AMS.MODULES.reduce(function(acc, module) {
      acc[module] = AMS.ACTIONS.slice();
      return acc;
    }, {});
  }
  const rows = query_(
    'SELECT DISTINCT p.module, p.action FROM user_role_assignments ura JOIN role_permissions rp ON rp.role_id = ura.role_id JOIN permissions p ON p.id = rp.permission_id WHERE ura.user_id = ?',
    [user.id]
  );
  const permissions = {};
  rows.forEach(function(row) {
    if (!permissions[row.module]) permissions[row.module] = [];
    permissions[row.module].push(row.action);
  });
  return permissions;
}

function can_(user, module, action) {
  return Boolean(user.permissions[module] && user.permissions[module].indexOf(action) !== -1);
}

function requirePermission_(user, module, action) {
  if (!can_(user, module, action)) {
    throw new Error('Permission denied: ' + module + ':' + action);
  }
}

function audit_(user, action, table, id, beforeValue, afterValue) {
  execute_(
    'INSERT INTO audit_logs (tenant_id, user_id, action, entity_table, entity_id, before_json, after_json, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
    [user ? user.tenant_id : null, user ? user.id : null, action, table, id || null, json_(beforeValue), json_(afterValue), now_()]
  );
}

function bootstrapActor_() {
  try {
    return requireUser_();
  } catch (err) {
    const countRow = one_('SELECT COUNT(*) AS c FROM users', []);
    if (Number(countRow.c || 0) > 0) {
      throw err;
    }
    return {
      id: null,
      tenant_id: 1,
      email: activeEmail_(),
      full_name: 'Bootstrap Administrator',
      roles: ['super_admin'],
      permissions: AMS.MODULES.reduce(function(acc, module) {
        acc[module] = AMS.ACTIONS.slice();
        return acc;
      }, {})
    };
  }
}

function getBootstrap_(knownUser) {
  let user = null;
  try {
    user = knownUser || requireUser_();
  } catch (err) {
    user = { error: err.message, email: activeEmail_(), permissions: {}, roles: [] };
  }
  return {
    app: { version: AMS.VERSION, tenantCode: AMS.TENANT_CODE, stages: AMS.WORKFLOW_STAGES },
    user: user,
    standards: safeQuery_('SELECT id, code, name, version, scheme_type FROM standards WHERE active = 1 ORDER BY code', []),
    roleOptions: AMS.ROLES.map(function(r) { return { code: r[0], name: r[1] }; })
  };
}

function safeQuery_(sql, params) {
  try {
    return query_(sql, params);
  } catch (err) {
    return [];
  }
}

function seedBaseline_(user) {
  requirePermission_(user, 'settings', 'edit');
  const items = [];
  items.push({
    sql: "INSERT IGNORE INTO tenants (id, name, legal_name, code, timezone, currency, status) VALUES (1, 'QSI', 'QSI Certification Body', 'QSI', 'Asia/Riyadh', 'SAR', 'active')",
    params: []
  });
  AMS.ROLES.forEach(function(role) {
    items.push({
      sql: 'INSERT IGNORE INTO roles (tenant_id, code, name, description, system_role) VALUES (1, ?, ?, ?, 1)',
      params: role
    });
  });
  AMS.MODULES.forEach(function(module) {
    AMS.ACTIONS.forEach(function(action) {
      items.push({
        sql: 'INSERT IGNORE INTO permissions (module, action, description) VALUES (?, ?, ?)',
        params: [module, action, action + ' ' + module]
      });
    });
  });
  AMS.STANDARDS.forEach(function(std) {
    items.push({
      sql: 'INSERT IGNORE INTO standards (code, name, version, scheme_type, active) VALUES (?, ?, ?, ?, 1)',
      params: std
    });
  });
  AMS.REQUIREMENTS.forEach(function(req) {
    items.push({
      sql: 'INSERT IGNORE INTO integrated_audit_requirements (requirement_code, title, audit_question, evidence_expectation, category, active) VALUES (?, ?, ?, ?, ?, 1)',
      params: req
    });
  });
  defaultTemplates_().forEach(function(tpl) {
    items.push({
      sql: 'INSERT IGNORE INTO document_templates (tenant_id, template_key, title, document_type, body_html, revision, active) VALUES (1, ?, ?, ?, ?, ?, 1)',
      params: [tpl.key, tpl.title, tpl.type, tpl.html, '1']
    });
  });
  executeMany_(items);
  seedRolePermissions_();
  ensureBootstrapUser_(user);
  audit_(user, 'seed', 'baseline', null, null, { version: AMS.VERSION });
  return { message: 'Baseline seeded', count: items.length };
}

function ensureBootstrapUser_(user) {
  const email = String(user.email || activeEmail_()).toLowerCase();
  if (!email) return;
  let existing = one_('SELECT id FROM users WHERE tenant_id = 1 AND LOWER(email) = ?', [email]);
  if (!existing) {
    const id = execute_(
      'INSERT INTO users (tenant_id, full_name, email, status, primary_role_code) VALUES (1, ?, ?, "active", "super_admin")',
      [user.full_name || 'Bootstrap Administrator', email]
    ).id;
    existing = { id: id };
  }
  const role = one_('SELECT id FROM roles WHERE tenant_id = 1 AND code = "super_admin"', []);
  if (role && existing.id) {
    execute_('INSERT IGNORE INTO user_role_assignments (user_id, role_id) VALUES (?, ?)', [existing.id, role.id]);
  }
}

function seedRolePermissions_() {
  const roleRows = query_('SELECT id, code FROM roles WHERE tenant_id = 1', []);
  const roles = {};
  roleRows.forEach(function(r) { roles[r.code] = Number(r.id); });
  if (roles.super_admin) {
    execute_('INSERT IGNORE INTO role_permissions (role_id, permission_id) SELECT ?, id FROM permissions', [roles.super_admin]);
  }
  if (roles.viewer) {
    execute_("INSERT IGNORE INTO role_permissions (role_id, permission_id) SELECT ?, id FROM permissions WHERE action IN ('view', 'download', 'print')", [roles.viewer]);
  }
  const rules = {
    administrator: AMS.MODULES,
    quality_manager: ['dashboard', 'clients', 'standards', 'application_reviews', 'audit_programs', 'reports', 'ncrs', 'capas', 'technical_reviews', 'certification_decisions', 'certificates', 'document_templates', 'audit_trail'],
    technical_manager: ['dashboard', 'clients', 'standards', 'application_reviews', 'audit_programs', 'personnel', 'auditor_appointments', 'audit_plans', 'reports', 'technical_reviews', 'certification_decisions'],
    proposal_officer: ['dashboard', 'clients', 'application_reviews', 'proposals', 'contracts', 'audit_programs', 'finance', 'document_templates'],
    lead_auditor: ['dashboard', 'clients', 'audit_programs', 'auditor_appointments', 'audit_plans', 'reports', 'ncrs', 'capas'],
    auditor: ['dashboard', 'clients', 'audit_plans', 'reports', 'ncrs', 'capas'],
    technical_reviewer: ['dashboard', 'clients', 'reports', 'ncrs', 'capas', 'technical_reviews'],
    certification_decision_maker: ['dashboard', 'clients', 'technical_reviews', 'certification_decisions', 'certificates'],
    finance: ['dashboard', 'clients', 'proposals', 'contracts', 'finance']
  };
  Object.keys(rules).forEach(function(roleCode) {
    if (!roles[roleCode]) return;
    rules[roleCode].forEach(function(module) {
      execute_('INSERT IGNORE INTO role_permissions (role_id, permission_id) SELECT ?, id FROM permissions WHERE module = ?', [roles[roleCode], module]);
    });
  });
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

function dashboard_(user) {
  requirePermission_(user, 'dashboard', 'view');
  return {
    counts: {
      clients: one_("SELECT COUNT(*) AS c FROM clients WHERE tenant_id = ?", [user.tenant_id]).c,
      activeCertificates: one_("SELECT COUNT(*) AS c FROM certificates WHERE tenant_id = ? AND status = 'active'", [user.tenant_id]).c,
      openNcrs: one_("SELECT COUNT(*) AS c FROM ncrs WHERE tenant_id = ? AND status <> 'closed'", [user.tenant_id]).c,
      pendingReviews: one_("SELECT COUNT(*) AS c FROM technical_reviews WHERE tenant_id = ? AND status <> 'completed'", [user.tenant_id]).c
    },
    upcoming: query_(
      "SELECT e.id, e.audit_number, e.event_type, e.planned_start_date, e.status, c.company FROM audit_events e JOIN clients c ON c.id = e.client_id WHERE e.tenant_id = ? AND e.status <> 'completed' ORDER BY e.planned_start_date IS NULL, e.planned_start_date LIMIT 10",
      [user.tenant_id]
    ),
    recent: query_(
      'SELECT a.created_at, a.action, a.entity_table, a.entity_id, u.full_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE a.tenant_id = ? ORDER BY a.id DESC LIMIT 12',
      [user.tenant_id]
    )
  };
}

function listClients_(user, payload) {
  requirePermission_(user, 'clients', 'view');
  const term = '%' + String(payload && payload.search ? payload.search : '') + '%';
  return query_(
    'SELECT c.*, GROUP_CONCAT(s.code ORDER BY s.code SEPARATOR ", ") AS standards FROM clients c LEFT JOIN client_standards cs ON cs.client_id = c.id LEFT JOIN standards s ON s.id = cs.standard_id WHERE c.tenant_id = ? AND (c.company LIKE ? OR c.client_code LIKE ? OR c.contact_email LIKE ?) GROUP BY c.id ORDER BY c.updated_at DESC, c.id DESC LIMIT 200',
    [user.tenant_id, term, term, term]
  );
}

function saveClient_(user, payload) {
  requirePermission_(user, 'clients', payload.id ? 'edit' : 'create');
  const data = payload || {};
  const before = data.id ? one_('SELECT * FROM clients WHERE id = ? AND tenant_id = ?', [data.id, user.tenant_id]) : null;
  if (data.id && !before) throw new Error('Client not found');
  const params = [
    data.company || '',
    data.legal_name || data.company || '',
    data.client_code || '',
    data.address || '',
    data.city || '',
    data.country || 'Saudi Arabia',
    data.contact_name || '',
    data.contact_email || '',
    data.contact_phone || '',
    data.scope || '',
    Number(data.employee_count || 0),
    Number(data.number_of_sites || 1),
    data.risk_category || '',
    data.status || 'prospect',
    data.current_stage || 'application'
  ];
  let id = Number(data.id || 0);
  if (id) {
    execute_('UPDATE clients SET company=?, legal_name=?, client_code=?, address=?, city=?, country=?, contact_name=?, contact_email=?, contact_phone=?, scope=?, employee_count=?, number_of_sites=?, risk_category=?, status=?, current_stage=?, updated_at=NOW() WHERE id=? AND tenant_id=?', params.concat([id, user.tenant_id]));
  } else {
    const result = execute_('INSERT INTO clients (tenant_id, company, legal_name, client_code, address, city, country, contact_name, contact_email, contact_phone, scope, employee_count, number_of_sites, risk_category, status, current_stage) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [user.tenant_id].concat(params));
    id = result.id;
  }
  saveClientStandards_(id, data.standard_ids || []);
  const after = one_('SELECT * FROM clients WHERE id = ?', [id]);
  audit_(user, id ? 'save' : 'create', 'clients', id, before, after);
  return clientFile_(user, id);
}

function saveClientStandards_(clientId, standardIds) {
  execute_('DELETE FROM client_standards WHERE client_id = ?', [clientId]);
  (standardIds || []).forEach(function(sid) {
    if (sid) execute_('INSERT IGNORE INTO client_standards (client_id, standard_id, status) VALUES (?, ?, "active")', [clientId, Number(sid)]);
  });
}

function clientFile_(user, clientId) {
  requirePermission_(user, 'clients', 'view');
  const client = one_('SELECT * FROM clients WHERE id = ? AND tenant_id = ?', [clientId, user.tenant_id]);
  if (!client) throw new Error('Client not found');
  return {
    client: client,
    standards: query_('SELECT s.* FROM client_standards cs JOIN standards s ON s.id = cs.standard_id WHERE cs.client_id = ? ORDER BY s.code', [clientId]),
    application: one_('SELECT * FROM certification_applications WHERE client_id = ? ORDER BY id DESC LIMIT 1', [clientId]),
    review: one_('SELECT * FROM application_reviews WHERE client_id = ? ORDER BY id DESC LIMIT 1', [clientId]),
    proposal: one_('SELECT * FROM proposals WHERE client_id = ? ORDER BY id DESC LIMIT 1', [clientId]),
    contract: one_('SELECT * FROM contracts WHERE client_id = ? ORDER BY id DESC LIMIT 1', [clientId]),
    programs: query_('SELECT * FROM audit_programs WHERE client_id = ? ORDER BY id DESC', [clientId]),
    events: query_('SELECT e.* FROM audit_events e WHERE e.client_id = ? ORDER BY e.planned_start_date IS NULL, e.planned_start_date, e.id', [clientId]),
    plans: query_('SELECT p.* FROM audit_plans p JOIN audit_events e ON e.id = p.audit_event_id WHERE e.client_id = ? ORDER BY p.id DESC', [clientId]),
    requirements: query_('SELECT * FROM integrated_audit_requirements WHERE active = 1 ORDER BY requirement_code', []),
    responses: query_('SELECT r.*, q.requirement_code, q.title FROM audit_requirement_responses r JOIN integrated_audit_requirements q ON q.id = r.audit_requirement_id JOIN audit_events e ON e.id = r.audit_event_id WHERE e.client_id = ? ORDER BY r.id DESC', [clientId]),
    ncrs: query_('SELECT n.* FROM ncrs n JOIN audit_events e ON e.id = n.audit_event_id WHERE e.client_id = ? ORDER BY n.id DESC', [clientId]),
    capas: query_('SELECT c.* FROM capas c JOIN audit_events e ON e.id = c.audit_event_id WHERE e.client_id = ? ORDER BY c.id DESC', [clientId]),
    technicalReview: one_('SELECT * FROM technical_reviews WHERE client_id = ? ORDER BY id DESC LIMIT 1', [clientId]),
    decision: one_('SELECT * FROM certification_decisions WHERE client_id = ? ORDER BY id DESC LIMIT 1', [clientId]),
    certificates: query_('SELECT * FROM certificates WHERE client_id = ? ORDER BY id DESC', [clientId]),
    documents: query_('SELECT * FROM generated_documents WHERE client_id = ? ORDER BY id DESC', [clientId]),
    invoices: query_('SELECT * FROM invoices WHERE client_id = ? ORDER BY id DESC', [clientId])
  };
}

function stage_(user, clientId, stage) {
  execute_('UPDATE clients SET current_stage = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?', [stage, clientId, user.tenant_id]);
}

function saveApplication_(user, payload) {
  requirePermission_(user, 'clients', 'edit');
  const id = upsertJsonRecord_('certification_applications', user, payload.clientId, {
    application_number: payload.application_number || ('APP-' + payload.clientId),
    received_date: payload.received_date || today_(),
    application_payload: json_(payload.application || {}),
    status: payload.status || 'submitted',
    submitted_by: user.id,
    submitted_at: now_()
  });
  stage_(user, payload.clientId, 'application_review');
  audit_(user, 'save_application', 'certification_applications', id, null, payload);
  return clientFile_(user, payload.clientId);
}

function saveApplicationReview_(user, payload) {
  requirePermission_(user, 'application_reviews', 'edit');
  const id = upsertJsonRecord_('application_reviews', user, payload.clientId, {
    reviewer_user_id: user.id,
    review_date: payload.review_date || today_(),
    review_payload: json_(payload.review || {}),
    calculated_days: Number(payload.calculated_days || 0),
    decision: payload.decision || 'accepted',
    status: payload.status || 'completed'
  });
  stage_(user, payload.clientId, 'proposal');
  audit_(user, 'save_application_review', 'application_reviews', id, null, payload);
  return clientFile_(user, payload.clientId);
}

function saveProposal_(user, payload) {
  requirePermission_(user, 'proposals', 'edit');
  const subtotal = Number(payload.subtotal || 0);
  const vat = Number(payload.vat || subtotal * 0.15);
  const id = upsertJsonRecord_('proposals', user, payload.clientId, {
    proposal_number: payload.proposal_number || ('PROP-' + payload.clientId + '-' + new Date().getFullYear()),
    proposal_date: payload.proposal_date || today_(),
    valid_until: payload.valid_until || '',
    subtotal: subtotal,
    vat: vat,
    grand_total: Number(payload.grand_total || subtotal + vat),
    status: payload.status || 'issued',
    payload: json_(payload)
  });
  stage_(user, payload.clientId, 'contract');
  audit_(user, 'save_proposal', 'proposals', id, null, payload);
  return clientFile_(user, payload.clientId);
}

function saveContract_(user, payload) {
  requirePermission_(user, 'contracts', 'edit');
  const id = upsertJsonRecord_('contracts', user, payload.clientId, {
    proposal_id: payload.proposal_id || null,
    contract_number: payload.contract_number || ('CON-' + payload.clientId + '-' + new Date().getFullYear()),
    signed_date: payload.signed_date || today_(),
    status: payload.status || 'signed',
    payload: json_(payload)
  });
  stage_(user, payload.clientId, 'audit_program');
  audit_(user, 'save_contract', 'contracts', id, null, payload);
  return clientFile_(user, payload.clientId);
}

function upsertJsonRecord_(table, user, clientId, fields) {
  const existing = one_('SELECT id FROM ' + table + ' WHERE client_id = ? AND tenant_id = ? ORDER BY id DESC LIMIT 1', [clientId, user.tenant_id]);
  const names = Object.keys(fields);
  if (existing) {
    const setSql = names.map(function(name) { return name + ' = ?'; }).join(', ');
    execute_('UPDATE ' + table + ' SET ' + setSql + ', updated_at = NOW() WHERE id = ?', names.map(function(n) { return fields[n]; }).concat([existing.id]));
    return Number(existing.id);
  }
  return execute_('INSERT INTO ' + table + ' (tenant_id, client_id, ' + names.join(', ') + ') VALUES (?, ?, ' + names.map(function() { return '?'; }).join(', ') + ')', [user.tenant_id, clientId].concat(names.map(function(n) { return fields[n]; }))).id;
}

function createAuditProgram_(user, payload) {
  requirePermission_(user, 'audit_programs', 'create');
    const result = execute_(
    'INSERT INTO audit_programs (tenant_id, client_id, program_number, cycle_type, start_date, expiry_date, status, payload) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
    [user.tenant_id, payload.clientId, payload.program_number || ('PRG-' + payload.clientId), payload.cycle_type || 'initial', payload.start_date || today_(), payload.expiry_date || '', payload.status || 'planned', json_(payload)]
  );
  stage_(user, payload.clientId, 'appointments');
  audit_(user, 'create', 'audit_programs', result.id, null, payload);
  return clientFile_(user, payload.clientId);
}

function saveAuditEvent_(user, payload) {
  requirePermission_(user, 'audit_programs', 'edit');
  const existing = payload.id ? one_('SELECT * FROM audit_events WHERE id = ? AND tenant_id = ?', [payload.id, user.tenant_id]) : null;
  let id = Number(payload.id || 0);
  if (id && existing) {
    execute_('UPDATE audit_events SET event_type=?, planned_start_date=?, planned_end_date=?, actual_start_date=?, actual_end_date=?, status=?, payload=?, updated_at=NOW() WHERE id=?', [payload.event_type, payload.planned_start_date || null, payload.planned_end_date || null, payload.actual_start_date || null, payload.actual_end_date || null, payload.status || 'planned', json_(payload), id]);
  } else {
    const res = execute_('INSERT INTO audit_events (tenant_id, audit_program_id, client_id, audit_number, event_type, planned_start_date, planned_end_date, status, payload) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [user.tenant_id, payload.audit_program_id, payload.clientId, payload.audit_number || ('AUD-' + payload.clientId + '-' + token_().substring(0, 6)), payload.event_type || 'stage2', payload.planned_start_date || null, payload.planned_end_date || null, payload.status || 'planned', json_(payload)]);
    id = res.id;
  }
  audit_(user, 'save', 'audit_events', id, existing, payload);
  return clientFile_(user, payload.clientId);
}

function saveAppointment_(user, payload) {
  requirePermission_(user, 'auditor_appointments', 'edit');
  const res = execute_('INSERT INTO auditor_appointments (tenant_id, audit_event_id, personnel_id, user_id, role_in_audit, appointment_status, conflict_checked, payload) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [user.tenant_id, payload.audit_event_id, payload.personnel_id || null, payload.user_id || null, payload.role_in_audit || 'auditor', payload.appointment_status || 'appointed', payload.conflict_checked ? 1 : 0, json_(payload)]);
  audit_(user, 'save', 'auditor_appointments', res.id, null, payload);
  return clientFile_(user, payload.clientId);
}

function saveAuditPlan_(user, payload) {
  requirePermission_(user, 'audit_plans', 'edit');
  const existing = one_('SELECT id FROM audit_plans WHERE audit_event_id = ? AND tenant_id = ? ORDER BY id DESC LIMIT 1', [payload.audit_event_id, user.tenant_id]);
  let id = existing ? Number(existing.id) : 0;
  if (id) {
    execute_('UPDATE audit_plans SET objective=?, criteria=?, scope=?, status=?, approved_by=?, approved_at=?, updated_at=NOW() WHERE id=?', [payload.objective || '', payload.criteria || '', payload.scope || '', payload.status || 'draft', payload.status === 'approved' ? user.id : null, payload.status === 'approved' ? now_() : null, id]);
  } else {
    id = execute_('INSERT INTO audit_plans (tenant_id, audit_event_id, objective, criteria, scope, status) VALUES (?, ?, ?, ?, ?, ?)', [user.tenant_id, payload.audit_event_id, payload.objective || '', payload.criteria || '', payload.scope || '', payload.status || 'draft']).id;
  }
  audit_(user, 'save', 'audit_plans', id, null, payload);
  return clientFile_(user, payload.clientId);
}

function savePlanItem_(user, payload) {
  requirePermission_(user, 'audit_plans', 'edit');
  const id = execute_('INSERT INTO audit_plan_items (audit_plan_id, audit_date, start_time, end_time, process_area, clause_reference, auditor_name, method) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [payload.audit_plan_id, payload.audit_date || '', payload.start_time || '', payload.end_time || '', payload.process_area || '', payload.clause_reference || '', payload.auditor_name || '', payload.method || 'onsite']).id;
  audit_(user, 'save', 'audit_plan_items', id, null, payload);
  return clientFile_(user, payload.clientId);
}

function saveRequirementResponse_(user, payload) {
  requirePermission_(user, 'reports', 'edit');
  const existing = one_('SELECT id FROM audit_requirement_responses WHERE audit_event_id = ? AND audit_requirement_id = ?', [payload.audit_event_id, payload.audit_requirement_id]);
  let id = existing ? Number(existing.id) : 0;
  const confirmed = payload.auditor_confirmed ? 1 : 0;
  if (id) {
    execute_('UPDATE audit_requirement_responses SET conformity_status=?, objective_evidence=?, finding_text=?, auditor_confirmed=?, confirmed_by_user_id=?, confirmed_at=?, updated_at=NOW() WHERE id=?', [payload.conformity_status || 'conforming', payload.objective_evidence || '', payload.finding_text || '', confirmed, confirmed ? user.id : null, confirmed ? now_() : null, id]);
  } else {
    id = execute_('INSERT INTO audit_requirement_responses (tenant_id, audit_event_id, audit_requirement_id, conformity_status, objective_evidence, finding_text, auditor_confirmed, confirmed_by_user_id, confirmed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [user.tenant_id, payload.audit_event_id, payload.audit_requirement_id, payload.conformity_status || 'conforming', payload.objective_evidence || '', payload.finding_text || '', confirmed, confirmed ? user.id : null, confirmed ? now_() : null]).id;
  }
  audit_(user, 'save', 'audit_requirement_responses', id, null, payload);
  return clientFile_(user, payload.clientId);
}

function saveNcr_(user, payload) {
  requirePermission_(user, 'ncrs', 'edit');
  const id = execute_('INSERT INTO ncrs (tenant_id, audit_event_id, audit_requirement_response_id, ncr_number, clause_reference, severity, statement, correction, root_cause, corrective_action, due_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [user.tenant_id, payload.audit_event_id, payload.audit_requirement_response_id || null, payload.ncr_number || ('NCR-' + token_().substring(0, 8)), payload.clause_reference || '', payload.severity || 'minor', payload.statement || '', payload.correction || '', payload.root_cause || '', payload.corrective_action || '', payload.due_date || '', payload.status || 'open']).id;
  audit_(user, 'save', 'ncrs', id, null, payload);
  return clientFile_(user, payload.clientId);
}

function closeNcr_(user, payload) {
  requirePermission_(user, 'ncrs', 'edit');
  execute_('UPDATE ncrs SET status = "closed", closed_by = ?, closed_at = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?', [user.id, now_(), payload.id, user.tenant_id]);
  audit_(user, 'close', 'ncrs', payload.id, null, payload);
  return clientFile_(user, payload.clientId);
}

function saveCapa_(user, payload) {
  requirePermission_(user, 'capas', 'edit');
  const id = execute_('INSERT INTO capas (tenant_id, ncr_id, audit_event_id, action_plan, evidence_summary, effectiveness_review, due_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [user.tenant_id, payload.ncr_id || null, payload.audit_event_id, payload.action_plan || '', payload.evidence_summary || '', payload.effectiveness_review || '', payload.due_date || '', payload.status || 'open']).id;
  audit_(user, 'save', 'capas', id, null, payload);
  return clientFile_(user, payload.clientId);
}

function closeCapa_(user, payload) {
  requirePermission_(user, 'capas', 'edit');
  execute_('UPDATE capas SET status = "closed", closed_by = ?, closed_at = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?', [user.id, now_(), payload.id, user.tenant_id]);
  audit_(user, 'close', 'capas', payload.id, null, payload);
  return clientFile_(user, payload.clientId);
}

function completeAuditEvent_(user, payload) {
  requirePermission_(user, 'reports', 'edit');
  execute_('UPDATE audit_events SET status = "completed", actual_end_date = COALESCE(actual_end_date, ?), updated_at = NOW() WHERE id = ? AND tenant_id = ?', [today_(), payload.audit_event_id, user.tenant_id]);
  stage_(user, payload.clientId, 'technical_review');
  audit_(user, 'complete', 'audit_events', payload.audit_event_id, null, payload);
  return clientFile_(user, payload.clientId);
}

function saveTechnicalReview_(user, payload) {
  requirePermission_(user, 'technical_reviews', 'edit');
  const id = upsertJsonRecord_('technical_reviews', user, payload.clientId, {
    audit_event_id: payload.audit_event_id || null,
    reviewer_user_id: user.id,
    review_date: payload.review_date || today_(),
    checklist_payload: json_(payload.checklist || {}),
    review_notes: payload.review_notes || '',
    recommendation: payload.recommendation || 'recommended',
    status: payload.status || 'completed'
  });
  stage_(user, payload.clientId, 'decision');
  audit_(user, 'save', 'technical_reviews', id, null, payload);
  return clientFile_(user, payload.clientId);
}

function saveDecision_(user, payload) {
  requirePermission_(user, 'certification_decisions', 'edit');
  const id = upsertJsonRecord_('certification_decisions', user, payload.clientId, {
    technical_review_id: payload.technical_review_id || null,
    decision_user_id: user.id,
    decision_date: payload.decision_date || today_(),
    decision: payload.decision || 'grant_certification',
    decision_notes: payload.decision_notes || '',
    gm_approval: payload.gm_approval || '',
    status: payload.status || 'approved'
  });
  stage_(user, payload.clientId, 'certificate');
  audit_(user, 'save', 'certification_decisions', id, null, payload);
  return clientFile_(user, payload.clientId);
}

function issueCertificate_(user, payload) {
  requirePermission_(user, 'certificates', 'create');
  const certNo = payload.certificate_number || ('QSI-' + new Date().getFullYear() + '-' + payload.clientId);
  const verificationToken = token_();
  const id = execute_('INSERT INTO certificates (tenant_id, client_id, certification_decision_id, certificate_number, standard_code, scope, issue_date, expiry_date, status, verification_token, verification_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [user.tenant_id, payload.clientId, payload.certification_decision_id || null, certNo, payload.standard_code || '', payload.scope || '', payload.issue_date || today_(), payload.expiry_date || '', payload.status || 'active', verificationToken, 'valid']).id;
  execute_('UPDATE clients SET status = "certified", certificate_number = ?, certificate_expiry_date = ?, current_stage = "surveillance_1", updated_at = NOW() WHERE id = ? AND tenant_id = ?', [certNo, payload.expiry_date || '', payload.clientId, user.tenant_id]);
  audit_(user, 'issue', 'certificates', id, null, payload);
  return clientFile_(user, payload.clientId);
}

function generateDocument_(user, payload) {
  requirePermission_(user, 'document_templates', 'download');
  const client = one_('SELECT * FROM clients WHERE id = ? AND tenant_id = ?', [payload.clientId, user.tenant_id]);
  if (!client) throw new Error('Client not found');
  const template = one_('SELECT * FROM document_templates WHERE tenant_id = ? AND template_key = ? AND active = 1', [user.tenant_id, payload.template_key]);
  if (!template) throw new Error('Template not found: ' + payload.template_key);
  const rootId = getProps_().getProperty('AMS_ROOT_FOLDER_ID');
  if (!rootId) throw new Error('Set AMS_ROOT_FOLDER_ID');
  const folder = clientFolder_(DriveApp.getFolderById(rootId), client);
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
  const recordId = execute_('INSERT INTO generated_documents (tenant_id, client_id, source_table, source_id, template_key, title, drive_file_id, pdf_file_id, hash_value, generated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [user.tenant_id, client.id, payload.source_table || '', payload.source_id || null, payload.template_key, title, file.getId(), pdf.getId(), sha256Hex_(merged), user.id]).id;
  audit_(user, 'generate', 'generated_documents', recordId, null, { template: payload.template_key, file: file.getId(), pdf: pdf.getId() });
  return { documentUrl: file.getUrl(), pdfUrl: pdf.getUrl(), id: recordId };
}

function clientFolder_(root, client) {
  const name = (client.client_code || client.id) + ' - ' + client.company;
  const folders = root.getFoldersByName(name);
  if (folders.hasNext()) return folders.next();
  return root.createFolder(name);
}

function documentContext_(client, payload) {
  const base = {
    client_name: client.company,
    scope: client.scope || '',
    standard: payload.standard_code || '',
    certificate_number: payload.certificate_number || client.certificate_number || '',
    issue_date: payload.issue_date || today_(),
    expiry_date: payload.expiry_date || client.certificate_expiry_date || '',
    verification_url: getProps_().getProperty('AMS_PUBLIC_BASE_URL') ? getProps_().getProperty('AMS_PUBLIC_BASE_URL') + '?verify=' + (payload.verification_token || '') : '',
    decision: payload.decision || '',
    notes: payload.notes || '',
    recommendation: payload.recommendation || '',
    grand_total: payload.grand_total || '',
    audit_timetable: payload.audit_timetable || '',
    report_body: payload.report_body || '',
    ncr_summary: payload.ncr_summary || '',
    capa_summary: payload.capa_summary || '',
    contract_number: payload.contract_number || ''
  };
  return Object.assign(base, payload.context || {});
}

function mergeTemplate_(html, context) {
  return String(html || '').replace(/\{\{([a-zA-Z0-9_]+)\}\}/g, function(_, key) {
    return context[key] === undefined || context[key] === null ? '' : String(context[key]);
  });
}

function htmlToParagraphs_(body, html) {
  const text = String(html).replace(/<br\s*\/?>/gi, '\n').replace(/<\/p>/gi, '\n').replace(/<[^>]+>/g, '');
  text.split(/\n+/).forEach(function(line) {
    if (line.trim()) body.appendParagraph(line.trim());
  });
}

function renderVerify(token) {
  const cert = one_('SELECT cert.*, c.company FROM certificates cert JOIN clients c ON c.id = cert.client_id WHERE cert.verification_token = ? LIMIT 1', [token]);
  const status = cert && cert.status === 'active' && cert.verification_status === 'valid' ? 'Valid certificate' : 'Certificate not valid';
  const body = '<!doctype html><html><head><base target="_top"><style>body{font-family:Arial,sans-serif;margin:40px;color:#1f2937}.box{max-width:760px;border:1px solid #d1d5db;padding:24px;border-radius:8px}dt{font-weight:bold;margin-top:14px}</style></head><body><div class="box"><h1>' + status + '</h1>' + (cert ? '<dl><dt>Client</dt><dd>' + escapeHtml_(cert.company) + '</dd><dt>Certificate</dt><dd>' + escapeHtml_(cert.certificate_number) + '</dd><dt>Standard</dt><dd>' + escapeHtml_(cert.standard_code) + '</dd><dt>Scope</dt><dd>' + escapeHtml_(cert.scope) + '</dd><dt>Issue date</dt><dd>' + escapeHtml_(cert.issue_date) + '</dd><dt>Expiry date</dt><dd>' + escapeHtml_(cert.expiry_date) + '</dd><dt>Status</dt><dd>' + escapeHtml_(cert.status) + '</dd></dl>' : '<p>No active certificate matched this verification token.</p>') + '</div></body></html>';
  return HtmlService.createHtmlOutput(body).setTitle('Certificate Verification');
}

function escapeHtml_(value) {
  return String(value || '').replace(/[&<>"']/g, function(c) {
    return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
  });
}

function sha256Hex_(value) {
  return Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, value)
    .map(function(byte) {
      const unsigned = byte < 0 ? byte + 256 : byte;
      return ('0' + unsigned.toString(16)).slice(-2);
    })
    .join('');
}

function finance_(user) {
  requirePermission_(user, 'finance', 'view');
  return {
    invoices: query_('SELECT i.*, c.company FROM invoices i JOIN clients c ON c.id = i.client_id WHERE i.tenant_id = ? ORDER BY i.id DESC LIMIT 200', [user.tenant_id]),
    payments: query_('SELECT p.*, i.invoice_number, c.company FROM payments p JOIN invoices i ON i.id = p.invoice_id JOIN clients c ON c.id = i.client_id WHERE p.tenant_id = ? ORDER BY p.id DESC LIMIT 200', [user.tenant_id])
  };
}

function saveInvoice_(user, payload) {
  requirePermission_(user, 'finance', 'edit');
  const subtotal = Number(payload.subtotal || 0);
  const vat = Number(payload.vat || subtotal * 0.15);
  const id = execute_('INSERT INTO invoices (tenant_id, client_id, invoice_number, invoice_date, due_date, subtotal, vat, total, status, payload) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [user.tenant_id, payload.clientId, payload.invoice_number || ('INV-' + payload.clientId + '-' + token_().substring(0, 5)), payload.invoice_date || today_(), payload.due_date || null, subtotal, vat, Number(payload.total || subtotal + vat), payload.status || 'issued', json_(payload)]).id;
  audit_(user, 'save', 'invoices', id, null, payload);
  return finance_(user);
}

function savePayment_(user, payload) {
  requirePermission_(user, 'finance', 'edit');
  const id = execute_('INSERT INTO payments (tenant_id, invoice_id, payment_date, amount, method, reference, status) VALUES (?, ?, ?, ?, ?, ?, ?)', [user.tenant_id, payload.invoice_id, payload.payment_date || today_(), Number(payload.amount || 0), payload.method || '', payload.reference || '', payload.status || 'posted']).id;
  audit_(user, 'save', 'payments', id, null, payload);
  return finance_(user);
}

function adminData_(user) {
  requirePermission_(user, 'users', 'view');
  return {
    users: query_('SELECT * FROM users WHERE tenant_id = ? ORDER BY full_name', [user.tenant_id]),
    roles: query_('SELECT * FROM roles WHERE tenant_id = ? ORDER BY name', [user.tenant_id])
  };
}

function saveUser_(user, payload) {
  requirePermission_(user, 'users', payload.id ? 'edit' : 'create');
  let id = Number(payload.id || 0);
  if (id) {
    execute_('UPDATE users SET full_name=?, email=?, phone=?, status=?, primary_role_code=?, updated_at=NOW() WHERE id=? AND tenant_id=?', [payload.full_name || '', String(payload.email || '').toLowerCase(), payload.phone || '', payload.status || 'active', payload.primary_role_code || 'viewer', id, user.tenant_id]);
  } else {
    id = execute_('INSERT INTO users (tenant_id, full_name, email, phone, status, primary_role_code) VALUES (?, ?, ?, ?, ?, ?)', [user.tenant_id, payload.full_name || '', String(payload.email || '').toLowerCase(), payload.phone || '', payload.status || 'active', payload.primary_role_code || 'viewer']).id;
  }
  execute_('DELETE FROM user_role_assignments WHERE user_id = ?', [id]);
  const role = one_('SELECT id FROM roles WHERE tenant_id = ? AND code = ?', [user.tenant_id, payload.primary_role_code || 'viewer']);
  if (role) execute_('INSERT IGNORE INTO user_role_assignments (user_id, role_id) VALUES (?, ?)', [id, role.id]);
  audit_(user, 'save', 'users', id, null, payload);
  return adminData_(user);
}

function auditTrail_(user, payload) {
  requirePermission_(user, 'audit_trail', 'view');
  return query_('SELECT a.*, u.full_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE a.tenant_id = ? ORDER BY a.id DESC LIMIT ?', [user.tenant_id, Number(payload.limit || 200)]);
}
