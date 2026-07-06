<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('Dashboard\DashboardController');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override();
$routes->setAutoRoute(false);

$routes->get('/', static fn () => redirect()->to('/dashboard'));
$routes->get('certificates/verify/(:segment)', 'PublicCertificateController::verify/$1');

$routes->get('login', 'Auth\AuthController::login', ['as' => 'login']);
$routes->post('login', 'Auth\AuthController::authenticate', ['as' => 'login.post']);
$routes->post('logout', 'Auth\AuthController::logout', ['filter' => 'auth', 'as' => 'logout']);

$routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('account/password', 'Account\PasswordController::edit', ['as' => 'account.password']);
    $routes->post('account/password', 'Account\PasswordController::update', ['as' => 'account.password.update']);

    $routes->get('dashboard', 'Dashboard\DashboardController::index', [
        'filter' => 'permission:dashboard,view',
        'as' => 'dashboard',
    ]);
    $routes->get('dashboard/section/(:segment)', 'Dashboard\DashboardDetailController::show/$1', ['filter' => 'permission:dashboard,view']);
    $routes->get('finance', 'Finance\FinanceController::index', ['filter' => 'permission:finance,view']);
    $routes->get('automation/cycle-generator', 'Automation\CycleGeneratorController::index', ['filter' => 'permission:automation,view']);
    $routes->post('automation/cycle-generator/preview', 'Automation\CycleGeneratorController::preview', ['filter' => 'permission:automation,create']);
    $routes->post('automation/cycle-generator/generate', 'Automation\CycleGeneratorController::generate', ['filter' => 'permission:automation,create']);
    $routes->post('automation/cycle-generator/upload', 'Automation\CycleGeneratorController::upload', ['filter' => 'permission:automation,create']);
    $routes->get('automation/cycle-generator/template', 'Automation\CycleGeneratorController::template', ['filter' => 'permission:automation,view']);

    $routes->get('workflow/certification', 'Workflow\CertificationWorkflowController::index', ['filter' => 'permission:clients,view']);
    $routes->get('workflow/certification/(:num)', 'Workflow\CertificationWorkflowController::show/$1', ['filter' => 'permission:clients,view']);
    $routes->get('workflow/certification/(:num)/application', 'Workflow\CertificationApplicationController::edit/$1', ['filter' => 'permission:clients,edit']);
    $routes->post('workflow/certification/(:num)/application', 'Workflow\CertificationApplicationController::save/$1', ['filter' => 'permission:clients,edit']);
    $routes->get('workflow/certification/(:num)/review', 'Workflow\WorkflowActionController::review/$1', ['filter' => 'permission:application_reviews,edit']);
    $routes->post('workflow/certification/(:num)/review', 'Workflow\WorkflowActionController::saveReview/$1', ['filter' => 'permission:application_reviews,edit']);
    $routes->get('workflow/certification/(:num)/proposal', 'Workflow\WorkflowActionController::proposal/$1', ['filter' => 'permission:proposals,edit']);
    $routes->post('workflow/certification/(:num)/proposal', 'Workflow\WorkflowActionController::saveProposal/$1', ['filter' => 'permission:proposals,edit']);
    $routes->get('workflow/certification/(:num)/contract', 'Workflow\WorkflowActionController::contract/$1', ['filter' => 'permission:contracts,edit']);
    $routes->post('workflow/certification/(:num)/contract', 'Workflow\WorkflowActionController::saveContract/$1', ['filter' => 'permission:contracts,edit']);
    $routes->get('workflow/certification/(:num)/audit-program', 'Workflow\WorkflowActionController::auditProgram/$1', ['filter' => 'permission:audit_programs,edit']);
    $routes->post('workflow/certification/(:num)/audit-program', 'Workflow\WorkflowActionController::saveAuditProgram/$1', ['filter' => 'permission:audit_programs,edit']);
    $routes->get('workflow/certification/(:num)/appointments', 'Workflow\WorkflowActionController::appointments/$1', ['filter' => 'permission:auditor_appointments,edit']);
    $routes->post('workflow/certification/(:num)/appointments', 'Workflow\WorkflowActionController::saveAppointment/$1', ['filter' => 'permission:auditor_appointments,edit']);
    $routes->post('workflow/certification/(:num)/appointments/(:num)/delete', 'Workflow\WorkflowActionController::deleteAppointment/$1/$2', ['filter' => 'permission:auditor_appointments,delete']);
    $routes->get('workflow/certification/(:num)/audit-plan', 'Workflow\WorkflowActionController::auditPlan/$1', ['filter' => 'permission:audit_plans,edit']);
    $routes->post('workflow/certification/(:num)/audit-plan', 'Workflow\WorkflowActionController::saveAuditPlan/$1', ['filter' => 'permission:audit_plans,edit']);
    $routes->post('workflow/certification/(:num)/audit-plan/items', 'Workflow\WorkflowActionController::addAuditPlanItem/$1', ['filter' => 'permission:audit_plans,edit']);
    $routes->post('workflow/certification/(:num)/audit-plan/items/(:num)/delete', 'Workflow\WorkflowActionController::deleteAuditPlanItem/$1/$2', ['filter' => 'permission:audit_plans,delete']);
    $routes->get('workflow/certification/(:num)/audit-events/(:num)/execute', 'Workflow\WorkflowActionController::executeAudit/$1/$2', ['filter' => 'permission:reports,edit']);
    $routes->get('workflow/certification/(:num)/audit-events/(:num)/file', 'Workflow\WorkflowActionController::auditEventFile/$1/$2', ['filter' => 'permission:reports,view']);
    $routes->post('workflow/certification/(:num)/audit-events/(:num)/findings', 'Workflow\WorkflowActionController::saveFinding/$1/$2', ['filter' => 'permission:reports,edit']);
    $routes->post('workflow/certification/(:num)/audit-events/(:num)/clauses/(:num)/clause-pool', 'Workflow\WorkflowActionController::generateConformityDraft/$1/$2/$3', ['filter' => 'permission:reports,edit']);
    $routes->post('workflow/certification/(:num)/audit-events/(:num)/clauses/(:num)/ai-conformity', 'Workflow\WorkflowActionController::generateConformityDraft/$1/$2/$3', ['filter' => 'permission:reports,edit']);
    $routes->post('workflow/certification/(:num)/audit-events/(:num)/findings/(:num)/autosave', 'Workflow\WorkflowActionController::autosaveConformityNote/$1/$2/$3', ['filter' => 'permission:reports,edit']);
    $routes->post('workflow/certification/(:num)/audit-events/(:num)/findings/(:num)/confirm', 'Workflow\WorkflowActionController::confirmReportSection/$1/$2/$3', ['filter' => 'permission:reports,edit']);
    $routes->post('workflow/certification/(:num)/audit-events/(:num)/findings/(:num)/delete', 'Workflow\WorkflowActionController::deleteFinding/$1/$2/$3', ['filter' => 'permission:reports,delete']);
    $routes->post('workflow/certification/(:num)/audit-events/(:num)/ncrs', 'Workflow\WorkflowActionController::saveNcr/$1/$2', ['filter' => 'permission:ncrs,edit']);
    $routes->post('workflow/certification/(:num)/audit-events/(:num)/ncrs/(:num)/close', 'Workflow\WorkflowActionController::closeNcr/$1/$2/$3', ['filter' => 'permission:ncrs,edit']);
    $routes->post('workflow/certification/(:num)/audit-events/(:num)/capas', 'Workflow\WorkflowActionController::saveCapa/$1/$2', ['filter' => 'permission:capas,edit']);
    $routes->post('workflow/certification/(:num)/audit-events/(:num)/capas/(:num)/close', 'Workflow\WorkflowActionController::closeCapa/$1/$2/$3', ['filter' => 'permission:capas,edit']);
    $routes->post('workflow/certification/(:num)/audit-events/(:num)/complete', 'Workflow\WorkflowActionController::completeAuditEvent/$1/$2', ['filter' => 'permission:reports,edit']);
    $routes->get('workflow/certification/(:num)/technical-review', 'Workflow\WorkflowActionController::technicalReview/$1', ['filter' => 'permission:technical_reviews,edit']);
    $routes->post('workflow/certification/(:num)/technical-review', 'Workflow\WorkflowActionController::saveTechnicalReview/$1', ['filter' => 'permission:technical_reviews,edit']);
    $routes->get('workflow/certification/(:num)/decision', 'Workflow\WorkflowActionController::decision/$1', ['filter' => 'permission:certification_decisions,edit']);
    $routes->post('workflow/certification/(:num)/decision', 'Workflow\WorkflowActionController::saveDecision/$1', ['filter' => 'permission:certification_decisions,edit']);
    $routes->get('workflow/certification/(:num)/certificates', 'Workflow\WorkflowActionController::certificates/$1', ['filter' => 'permission:certificates,edit']);
    $routes->post('workflow/certification/(:num)/certificates', 'Workflow\WorkflowActionController::generateCertificates/$1', ['filter' => 'permission:certificates,edit']);
    $routes->get('workflow/certification/(:num)/feedback', 'Workflow\WorkflowActionController::feedback/$1', ['filter' => 'permission:clients,edit']);
    $routes->post('workflow/certification/(:num)/feedback', 'Workflow\WorkflowActionController::saveFeedback/$1', ['filter' => 'permission:clients,edit']);
    $routes->get('workflow/certification/(:num)/documents/(:segment)', 'Workflow\WorkflowDocumentController::clientDocument/$1/$2', ['filter' => 'permission:document_templates,download']);
    $routes->get('workflow/certification/(:num)/audit-events/(:num)/documents/(:segment)', 'Workflow\WorkflowDocumentController::eventDocument/$1/$2/$3', ['filter' => 'permission:document_templates,download']);
    $routes->get('workflow/certification/certificates/(:num)/pdf', 'Workflow\WorkflowDocumentController::certificate/$1', ['filter' => 'permission:certificates,download']);

    $routes->get('masters/clients', 'Masters\ClientController::index', ['filter' => 'permission:clients,view']);
    $routes->get('masters/clients/new', 'Masters\ClientController::new', ['filter' => 'permission:clients,create']);
    $routes->post('masters/clients', 'Masters\ClientController::create', ['filter' => 'permission:clients,create']);
    $routes->get('masters/clients/(:num)/edit', 'Masters\ClientController::edit/$1', ['filter' => 'permission:clients,edit']);
    $routes->get('masters/clients/(:num)', 'Masters\ClientController::show/$1', ['filter' => 'permission:clients,view']);
    $routes->post('masters/clients/(:num)', 'Masters\ClientController::update/$1', ['filter' => 'permission:clients,edit']);
    $routes->post('masters/clients/(:num)/delete', 'Masters\ClientController::delete/$1', ['filter' => 'permission:clients,delete']);
    $routes->post('masters/clients/(:num)/standards', 'Masters\ClientController::addStandard/$1', ['filter' => 'permission:clients,edit']);
    $routes->post('masters/clients/(:num)/standards/(:num)/delete', 'Masters\ClientController::deleteStandard/$1/$2', ['filter' => 'permission:clients,delete']);
    $routes->post('masters/clients/(:num)/sites', 'Masters\ClientController::addSite/$1', ['filter' => 'permission:clients,edit']);
    $routes->post('masters/clients/(:num)/sites/(:num)/delete', 'Masters\ClientController::deleteSite/$1/$2', ['filter' => 'permission:clients,delete']);
    $routes->post('masters/clients/(:num)/processes', 'Masters\ClientController::addProcess/$1', ['filter' => 'permission:clients,edit']);
    $routes->post('masters/clients/(:num)/processes/(:num)/delete', 'Masters\ClientController::deleteProcess/$1/$2', ['filter' => 'permission:clients,delete']);
    $routes->post('masters/clients/(:num)/attachments', 'Masters\ClientController::addAttachment/$1', ['filter' => 'permission:clients,edit']);
    $routes->post('masters/clients/(:num)/attachments/(:num)/delete', 'Masters\ClientController::deleteAttachment/$1/$2', ['filter' => 'permission:clients,delete']);

    $routes->get('masters/imports', 'Masters\LegacyImportController::index', ['filter' => 'permission:legacy_imports,view']);
    $routes->post('masters/imports', 'Masters\LegacyImportController::upload', ['filter' => 'permission:legacy_imports,create']);
    $routes->get('masters/imports/(:num)', 'Masters\LegacyImportController::show/$1', ['filter' => 'permission:legacy_imports,view']);
    $routes->post('masters/imports/(:num)/commit', 'Masters\LegacyImportController::commit/$1', ['filter' => 'permission:legacy_imports,approve']);
    $routes->post('masters/imports/(:num)/rollback', 'Masters\LegacyImportController::rollback/$1', ['filter' => 'permission:legacy_imports,reject']);

    $routes->get('masters/standards', 'Masters\StandardController::index', ['filter' => 'permission:standards,view']);
    $routes->get('masters/standards/new', 'Masters\StandardController::new', ['filter' => 'permission:standards,create']);
    $routes->post('masters/standards', 'Masters\StandardController::create', ['filter' => 'permission:standards,create']);
    $routes->get('masters/standards/(:num)/edit', 'Masters\StandardController::edit/$1', ['filter' => 'permission:standards,edit']);
    $routes->post('masters/standards/(:num)', 'Masters\StandardController::update/$1', ['filter' => 'permission:standards,edit']);
    $routes->post('masters/standards/(:num)/deactivate', 'Masters\StandardController::deactivate/$1', ['filter' => 'permission:standards,delete']);

    $routes->get('masters/references/(:segment)', 'Masters\ReferenceController::index/$1', ['filter' => 'permission:standards,view']);
    $routes->get('masters/references/(:segment)/new', 'Masters\ReferenceController::new/$1', ['filter' => 'permission:standards,create']);
    $routes->post('masters/references/(:segment)', 'Masters\ReferenceController::create/$1', ['filter' => 'permission:standards,create']);
    $routes->get('masters/references/(:segment)/(:num)/edit', 'Masters\ReferenceController::edit/$1/$2', ['filter' => 'permission:standards,edit']);
    $routes->post('masters/references/(:segment)/(:num)', 'Masters\ReferenceController::update/$1/$2', ['filter' => 'permission:standards,edit']);
    $routes->post('masters/references/(:segment)/(:num)/deactivate', 'Masters\ReferenceController::deactivate/$1/$2', ['filter' => 'permission:standards,delete']);

    $routes->get('masters/personnel', 'Masters\PersonnelController::index', ['filter' => 'permission:personnel,view']);
    $routes->get('masters/personnel/new', 'Masters\PersonnelController::new', ['filter' => 'permission:personnel,create']);
    $routes->post('masters/personnel', 'Masters\PersonnelController::create', ['filter' => 'permission:personnel,create']);
    $routes->get('masters/personnel/(:num)/edit', 'Masters\PersonnelController::edit/$1', ['filter' => 'permission:personnel,edit']);
    $routes->get('masters/personnel/(:num)', 'Masters\PersonnelController::show/$1', ['filter' => 'permission:personnel,view']);
    $routes->post('masters/personnel/(:num)', 'Masters\PersonnelController::update/$1', ['filter' => 'permission:personnel,edit']);
    $routes->post('masters/personnel/(:num)/delete', 'Masters\PersonnelController::delete/$1', ['filter' => 'permission:personnel,delete']);
    $routes->post('masters/personnel/(:num)/competencies', 'Masters\PersonnelController::addCompetency/$1', ['filter' => 'permission:competency_matrix,edit']);
    $routes->post('masters/personnel/(:num)/competencies/(:num)/delete', 'Masters\PersonnelController::deleteCompetency/$1/$2', ['filter' => 'permission:competency_matrix,delete']);

    $routes->get('masters/clauses', 'Masters\ClauseLibraryController::index', ['filter' => 'permission:clause_library,view']);
    $routes->get('masters/clauses/new', 'Masters\ClauseLibraryController::new', ['filter' => 'permission:clause_library,create']);
    $routes->post('masters/clauses', 'Masters\ClauseLibraryController::create', ['filter' => 'permission:clause_library,create']);
    $routes->get('masters/clauses/(:num)/edit', 'Masters\ClauseLibraryController::edit/$1', ['filter' => 'permission:clause_library,edit']);
    $routes->post('masters/clauses/(:num)', 'Masters\ClauseLibraryController::update/$1', ['filter' => 'permission:clause_library,edit']);
    $routes->post('masters/clauses/(:num)/deactivate', 'Masters\ClauseLibraryController::deactivate/$1', ['filter' => 'permission:clause_library,delete']);

    $routes->get('masters/clause-pool', 'Masters\ClauseContentPoolController::index', ['filter' => 'permission:clause_library,view']);
    $routes->get('masters/clause-pool/new', 'Masters\ClauseContentPoolController::new', ['filter' => 'permission:clause_library,create']);
    $routes->post('masters/clause-pool', 'Masters\ClauseContentPoolController::create', ['filter' => 'permission:clause_library,create']);
    $routes->get('masters/clause-pool/export', 'Masters\ClauseContentPoolController::export', ['filter' => 'permission:clause_library,view']);
    $routes->post('masters/clause-pool/import', 'Masters\ClauseContentPoolController::import', ['filter' => 'permission:clause_library,edit']);
    $routes->get('masters/clause-pool/(:num)/edit', 'Masters\ClauseContentPoolController::edit/$1', ['filter' => 'permission:clause_library,edit']);
    $routes->post('masters/clause-pool/(:num)', 'Masters\ClauseContentPoolController::update/$1', ['filter' => 'permission:clause_library,edit']);
    $routes->post('masters/clause-pool/(:num)/deactivate', 'Masters\ClauseContentPoolController::deactivate/$1', ['filter' => 'permission:clause_library,delete']);

    $routes->get('masters/templates', 'Masters\DocumentTemplateController::index', ['filter' => 'permission:document_templates,view']);
    $routes->get('masters/templates/(:num)/edit', 'Masters\DocumentTemplateController::edit/$1', ['filter' => 'permission:document_templates,edit']);
    $routes->post('masters/templates/(:num)', 'Masters\DocumentTemplateController::update/$1', ['filter' => 'permission:document_templates,edit']);
});
