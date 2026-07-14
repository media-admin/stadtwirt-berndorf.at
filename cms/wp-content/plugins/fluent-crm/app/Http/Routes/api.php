<?php

/**
 * @var $router FluentCrm\Framework\Http\Router
 */

use FluentCrm\App\Http\Controllers\CampaignAnalyticsController;
use FluentCrm\App\Http\Controllers\CsvController;
use FluentCrm\App\Http\Controllers\CustomContactFieldsController;
use FluentCrm\App\Http\Controllers\DashboardController;
use FluentCrm\App\Http\Controllers\GlobalLabelController;
use FluentCrm\App\Http\Controllers\ImporterController;
use FluentCrm\App\Http\Controllers\PurchaseHistoryController;
use FluentCrm\App\Http\Controllers\SetupController;
use FluentCrm\App\Http\Controllers\SubscriberController;
use FluentCrm\App\Http\Controllers\CompanyController;
use FluentCrm\App\Http\Controllers\TagsController;
use FluentCrm\App\Http\Controllers\ListsController;
use FluentCrm\App\Http\Controllers\CampaignController;
use FluentCrm\App\Http\Controllers\FunnelController;
use FluentCrm\App\Http\Controllers\ReportingController;
use FluentCrm\App\Http\Controllers\SettingsController;
use FluentCrm\App\Http\Controllers\TemplateController;
use FluentCrm\App\Http\Controllers\WebhookBounceController;
use FluentCrm\App\Http\Controllers\WebhookController;
use FluentCrm\App\Http\Controllers\UsersController;
use FluentCrm\App\Http\Controllers\FormsController;
use FluentCrm\App\Http\Controllers\DocsController;
use FluentCrm\App\Http\Controllers\OptionsController;
use FluentCrm\App\Http\Controllers\SystemLogController;
use FluentCrm\App\Http\Controllers\MigratorController;
use FluentCrm\App\Modules\AbandonCart\AbandonCartController;
use FluentCrm\App\Modules\AbandonCart\SettingsController as AbandonCartSettingsController;
use FluentCrm\App\Http\Controllers\AiController;
use FluentCrm\App\Http\Controllers\EmailPatternController;
use FluentCrm\App\Http\Controllers\MCPSettingsController;

/*
 * /tags endpoints
 */
$router->prefix('tags')->withPolicy('TagPolicy')->group(function ($router) {

    $router->get('/', [TagsController::class, 'index']);
    $router->post('/', [TagsController::class, 'create']);

    $router->get('{id}', [TagsController::class, 'find'])->int('id');
    $router->put('{id}', [TagsController::class, 'store'])->int('id');
    $router->delete('{id}', [TagsController::class, 'remove'])->int('id');
    $router->post('do-bulk-action', [TagsController::class, 'handleBulkAction']);

    $router->post('/bulk', [TagsController::class, 'storeBulk']);

});

/*
 * /lists endpoints
 */
$router->prefix('lists')->withPolicy('ListPolicy')->group(function ($router) {

    $router->get('/', [ListsController::class, 'index']);
    $router->post('/', [ListsController::class, 'create']);

    $router->get('{id}', [ListsController::class, 'find'])->int('id');
    $router->put('{id}', [ListsController::class, 'update'])->int('id');
    $router->delete('/{id}', [ListsController::class, 'remove'])->int('id');
    $router->post('do-bulk-action', [ListsController::class, 'handleBulkAction']);

    $router->post('/bulk', [ListsController::class, 'storeBulk']);

});

/*
 * Global search: contacts + email campaigns + automations in one call.
 * Each Permission is checked in the controller. 
 */
$router->get('global-search', [OptionsController::class, 'search']);

/*
 * /subscribers endpoints
 */
$router->prefix('subscribers')->withPolicy('SubscriberPolicy')->group(function ($router) {

    $router->get('/', [SubscriberController::class, 'index']);
    $router->post('/', [SubscriberController::class, 'store']);
    $router->put('subscribers-property', [SubscriberController::class, 'updateProperty']);
    $router->delete('/', [SubscriberController::class, 'deleteSubscribers']);
    $router->post('sync-segments', [SubscriberController::class, 'tagger']);
    $router->post('do-bulk-action', [SubscriberController::class, 'handleBulkActions']);
    $router->get('prev-next-ids', [SubscriberController::class, 'getPrevNextIds']);

    $router->get('{id}', [SubscriberController::class, 'show'])->int('id');
    $router->delete('{id}', [SubscriberController::class, 'deleteSubscriber'])->int('id');

    $router->put('{id}', [SubscriberController::class, 'updateSubscriber'])->int('id');
    $router->get('{id}/emails', [SubscriberController::class, 'emails'])->int('id');
    $router->get('{id}/emails/template-mock', [SubscriberController::class, 'getTemplateMock'])->int('id');
    $router->post('{id}/emails/send', [SubscriberController::class, 'sendCustomEmail'])->int('id');
    $router->delete('{id}/emails', [SubscriberController::class, 'deleteEmails'])->int('id');
    $router->get('{id}/purchase-history', [PurchaseHistoryController::class, 'getOrders'])->int('id');
    $router->get('{id}/form-submissions', [SubscriberController::class, 'getFormSubmissions'])->int('id');
    $router->get('{id}/support-tickets', [SubscriberController::class, 'getSupportTickets'])->int('id');
    $router->post('{id}/send-double-optin', [SubscriberController::class, 'sendDoubleOptinEmail'])->int('id');

    $router->get('{id}/notes', [SubscriberController::class, 'getNotes'])->int('id');
    $router->post('{id}/notes', [SubscriberController::class, 'addNote'])->int('id');
    $router->put('{id}/notes/{note_id}', [SubscriberController::class, 'updateNote'])->int('id')->int('note_id');
    $router->delete('{id}/notes/{note_id}', [SubscriberController::class, 'deleteNote'])->int('id')->int('note_id');
    $router->post('{id}/notes/bulk-delete', [SubscriberController::class, 'bulkDeleteNotes'])->int('id');
    $router->get('{id}/external_view', [SubscriberController::class, 'getExternalView'])->int('id');
    $router->post('{id}/external_view', [SubscriberController::class, 'saveExternalViewData'])->int('id');
    $router->get('{id}/info-widgets', [SubscriberController::class, 'getInfoWidgets'])->int('id');
    $router->get('{id}/dynamic-item-view', [SubscriberController::class, 'getDynamicItemView'])->int('id');

    $router->get('search-contacts', [SubscriberController::class, 'searchContacts']);

    $router->get('{id}/tracking-events', [SubscriberController::class, 'getTrackingEvents'])->int('id');
    $router->post('track-event', [SubscriberController::class, 'trackEvent']);

    $router->get('{id}/url-metrics', [SubscriberController::class, 'getUrlMetrics'])->int('id');

    $router->post('bulk-add-update', [SubscriberController::class, 'bulkAddUpdate']);

});

$router->prefix('campaigns')->withPolicy('CampaignPolicy')->group(function ($router) {

    $router->get('/', [CampaignController::class, 'campaigns']);
    $router->post('/', [CampaignController::class, 'create']);
    $router->post('/send-test-email', [CampaignController::class, 'sendTestEmail']);
    // Editor/draft preview iframe: renders a campaign payload or campaign_id without email-history metadata.
    $router->post('/email-preview-html', [CampaignController::class, 'getEmailPreviewBody']);
    // Sent/scheduled email history preview: used from contact profile, campaign emails, and all emails.
    $router->get('emails/{email_id}/preview', [CampaignController::class, 'previewEmail'])->int('email_id');

    $router->post('estimated-contacts', [CampaignController::class, 'getContactEstimation']);
    $router->post('update-single-campaign', [CampaignController::class, 'updateSingleCampaignSimulate']);

    $router->get('{id}', [CampaignController::class, 'campaign'])->int('id');
    $router->put('{id}', [CampaignController::class, 'update'])->int('id');
    $router->post('{id}/step', [CampaignController::class, 'updateStep'])->int('id');

    $router->post('{id}/pause', [CampaignController::class, 'pauseCampaign'])->int('id');
    $router->post('{id}/duplicate', [CampaignController::class, 'duplicateCampaign'])->int('id');
    $router->post('{id}/resume', [CampaignController::class, 'resumeCampaign'])->int('id');
    $router->put('{id}/title', [CampaignController::class, 'updateCampaignTitle'])->int('id');
    $router->delete('{id}', [CampaignController::class, 'delete'])->int('id');

    $router->post('do-bulk-action', [CampaignController::class, 'handleBulkAction'])->int('id');

    // todo: delete this endpoint '{id}/subscribe' in future since it is not in use anywhere. We will keep it for reference for now. We will remove in the immediate next version
    // $router->post('{id}/subscribe', [CampaignController::class, 'subscribe'])->int('id');
    $router->post('{id}/draft-recipients', [CampaignController::class, 'draftRecipients'])->int('id');
    $router->get('{id}/estimated-recipients-count', [CampaignController::class, 'recipientsCount'])->int('id');

    $router->get('{id}/emails', [CampaignController::class, 'campaignEmails'])->int('id');
    $router->delete('{id}/emails', [CampaignController::class, 'deleteCampaignEmails'])->int('id');
    $router->post('{id}/schedule', [CampaignController::class, 'schedule'])->int('id');
    $router->post('{id}/un-schedule', [CampaignController::class, 'unSchedule'])->int('id');
    $router->get('{id}/processing-stat', [CampaignController::class, 'processingStat'])->int('id');

    $router->get('{id}/share-url', [CampaignController::class, 'getShareUrl'])->int('id');


    $router->get('{id}/status', [CampaignController::class, 'getCampaignStatus'])->int('id');
    $router->get('{id}/overview_stats', [CampaignController::class, 'getOverviewStats'])->int('id');
    $router->get('{id}/link-report', [CampaignAnalyticsController::class, 'getLinksReport'])->int('id');
    $router->get('{id}/revenues', [CampaignAnalyticsController::class, 'getRevenueReport'])->int('id');
    $router->post('{id}/revenues/resync', [CampaignAnalyticsController::class, 'getRevenueReSyncReport'])->int('id');
    $router->get('{id}/unsubscribers', [CampaignAnalyticsController::class, 'getUnsubscribers'])->int('id');

    $router->get('{id}/contacts-by-segment', [CampaignAnalyticsController::class, 'getSegmentedContacts'])->int('id');

    $router->put('{id}/update-labels', [CampaignController::class, 'updateLabels'])->int('id');
});

$router->prefix('templates')->withPolicy('TemplatePolicy')->group(function ($router) {

    $router->get('/', [TemplateController::class, 'templates']);
    $router->get('/all', [TemplateController::class, 'allTemplates']);
    $router->get('/smartcodes', [TemplateController::class, 'getSmartCodes']);
    $router->post('/', [TemplateController::class, 'create']);

    $router->get('{id}', [TemplateController::class, 'template'])->int('id');
    $router->put('{id}', [TemplateController::class, 'update'])->int('id');
    $router->post('/duplicate/{id}', [TemplateController::class, 'duplicate'])->int('id');
    $router->delete('{id}', [TemplateController::class, 'delete'])->int('id');
    $router->post('do-bulk-action', [TemplateController::class, 'handleBulkAction']);

    $router->post('set-global-style', [TemplateController::class, 'setGlobalStyle']);
    $router->post('built-in-template', [TemplateController::class, 'getBuiltInTemplate']);
    $router->get('/built-in-templates', [TemplateController::class, 'getBuiltInTemplates']);

});

/*
 * Email Patterns Route
 */
$router->prefix('email-patterns')->withPolicy('EmailPatternPolicy')->group(function ($router) {
    $router->get('/', [EmailPatternController::class, 'index']);
    $router->post('/', [EmailPatternController::class, 'store']);
    $router->get('{id}', [EmailPatternController::class, 'show'])->int('id');
    $router->put('{id}', [EmailPatternController::class, 'update'])->int('id');
    $router->delete('{id}', [EmailPatternController::class, 'delete'])->int('id');
    $router->post('do-bulk-action', [EmailPatternController::class, 'handleBulkAction']);

    // wp_block-compatible endpoints for editor middleware interception
    $router->get('/wp-format', [EmailPatternController::class, 'indexWpFormat']);
    $router->post('/wp-format', [EmailPatternController::class, 'storeWpFormat']);

    // Pattern categories
    $router->get('/categories', [EmailPatternController::class, 'getCategories']);
    $router->post('/categories', [EmailPatternController::class, 'storeCategory']);
    $router->delete('/categories/{id}', [EmailPatternController::class, 'deleteCategory'])->int('id');
});

/*
 * Funnels Route
 */
$router->prefix('funnels')->withPolicy('FunnelPolicy')->group(function ($router) {

    $router->get('/', [FunnelController::class, 'funnels']);
    $router->post('/', [FunnelController::class, 'create']);
    $router->get('templates', [FunnelController::class, 'getTemplates']);
    $router->post('create-from-template', [FunnelController::class, 'createFromTemplate']);
    $router->post('import', [FunnelController::class, 'importFunnel']);

    $router->get('all-activities', [FunnelController::class, 'getAllActivities']);
    $router->post('remove-bulk-subscribers', [FunnelController::class, 'removeBulkSubscribers']);

    $router->get('triggers', [FunnelController::class, 'getTriggersRest']);

    $router->get('subscriber/{subscriber_id}/automations', [FunnelController::class, 'subscriberAutomations']);

    $router->post('funnel/save-funnel-sequences', [FunnelController::class, 'saveSequencesFallback']);
    $router->post('funnel/save-email-action-fallback', [FunnelController::class, 'saveEmailActionFallback']);

    $router->get('{id}', [FunnelController::class, 'getFunnel'])->int('id');
    $router->post('{id}/clone', [FunnelController::class, 'cloneFunnel'])->int('id');
    $router->put('{id}', [FunnelController::class, 'updateFunnelProperty'])->int('id');
    $router->put('{id}/change-trigger', [FunnelController::class, 'changeTrigger'])->int('id');
    $router->post('{id}/sequences', [FunnelController::class, 'saveSequences'])->int('id');
    $router->put('funnel/{id}/title', [FunnelController::class, 'updateFunnelTitle'])->int('id');

    $router->post('{id}/sequences/save-email-action', [FunnelController::class, 'saveEmailAction'])->int('id');

    $router->get('{id}/subscribers', [FunnelController::class, 'getSubscribers'])->int('id');
    $router->get('{id}/subscribers/{contact_id}', [FunnelController::class, 'getSubscriberReporting'])->int('id')->int('contact_id');

    $router->delete('{id}/subscribers', [FunnelController::class, 'deleteSubscribers'])->int('id');
    $router->delete('{id}', [FunnelController::class, 'delete'])->int('id');
    $router->get('{id}/report', [FunnelController::class, 'report'])->int('id');
    $router->post('do-bulk-action', [FunnelController::class, 'handleBulkAction']);


    $router->get('{id}/email_reports', [FunnelController::class, 'getEmailReports'])->int('id');
    $router->put('{id}/subscribers/{subscriber_id}/status', [FunnelController::class, 'updateSubscriptionStatus'])->int('id')->int('subscriber_id');
    $router->post('{id}/subscribers/{subscriber_id}/advance', [FunnelController::class, 'forceAdvanceSubscriber'])->int('id')->int('subscriber_id');

    $router->get('{id}/syncable-counts', [FunnelController::class, 'getSyncableContactCounts'])->int('id');
    $router->post('{id}/sync-new-steps', [FunnelController::class, 'syncNewSteps'])->int('id');

    $router->post('send-test-webhook', [FunnelController::class, 'sendTestWebhook']);

    $router->put('{id}/update-labels', [FunnelController::class, 'updateLabels'])->int('id');

});

/*
 * Reporting Route
 */
$router->prefix('reports')->withPolicy('ReportPolicy')->group(function ($router) {

    $router->get('dashboard-stats', [DashboardController::class, 'getStats']);
    $router->get('subscribers', [ReportingController::class, 'getContactGrowth']);
    $router->get('email-sents', [ReportingController::class, 'getEmailSentStats']);
    $router->get('email-opens', [ReportingController::class, 'getEmailOpenStats']);
    $router->get('email-clicks', [ReportingController::class, 'getEmailClickStats']);
    $router->get('email-unsubs', [ReportingController::class, 'getEmailUnsubStats']);
    $router->get('email-performance', [ReportingController::class, 'getEmailPerformance']);

    $router->get('options', [OptionsController::class, 'index']);
    $router->get('ajax-options', [OptionsController::class, 'getAjaxOptions']);
    $router->get('taxonomy-terms', [OptionsController::class, 'getTaxonomyTerms']);
    $router->get('cascade_selections', [OptionsController::class, 'getCascadeSelections']);

    $router->get('emails', [ReportingController::class, 'getEmails']);
    $router->delete('emails', [ReportingController::class, 'deleteEmails']);

    $router->get('advanced-providers', [ReportingController::class, 'getAdvancedReportProviders']);

    $router->get('contacts-by-status', [ReportingController::class, 'getContactsByStatus']);
    $router->get('contacts-by-tags', [ReportingController::class, 'getContactsByTags']);
    $router->get('contacts-by-lists', [ReportingController::class, 'getContactsByLists']);
    $router->get('contacts-by-country', [ReportingController::class, 'getContactsByCountry']);
    $router->get('recent-tags', [ReportingController::class, 'getRecentTags']);
    $router->get('campaigns-list', [ReportingController::class, 'getCampaignsList']);
    $router->get('campaign-options', [ReportingController::class, 'getCampaignOptions']);
    $router->get('automations', [ReportingController::class, 'getAutomationReports']);
    $router->get('automations/{id}/steps', [ReportingController::class, 'getAutomationStepReport']);

    $router->get('ping', [ReportingController::class, 'ping']);

});

$router->prefix('setting')->withPolicy('SettingsPolicy')->group(function ($router) {

    $router->get('/', [SettingsController::class, 'get']);
    $router->put('/', [SettingsController::class, 'save']);
    $router->post('complete-installation', [SetupController::class, 'CompleteWizard']);
    $router->get('double-optin', [SettingsController::class, 'getDoubleOptinSettings']);
    $router->put('double-optin', [SettingsController::class, 'saveDoubleOptinSettings']);

    $router->post('install-fluentform', [SetupController::class, 'handleFluentFormInstall']);
    $router->post('install-fluentsmtp', [SetupController::class, 'handleFluentSmtpInstall']);
    $router->post('install-fluent-support', [SetupController::class, 'handleFluentSupportInstall']);
    $router->post('install-fluent-boards', [SetupController::class, 'handleFluentBoardsInstall']);
    $router->post('install-fluent-community', [SetupController::class, 'handleFluentCommunityInstall']);
    $router->post('install-fluent-cart', [SetupController::class, 'handleFluentCartInstall']);
    $router->post('install-fluent-booking', [SetupController::class, 'handleFluentBookingInstall']);

    $router->get('bounce_configs', [SettingsController::class, 'getBounceConfigs']);

    $router->get('auto_subscribe_settings', [SettingsController::class, 'getAutoSubscribeSettings']);
    $router->post('auto_subscribe_settings', [SettingsController::class, 'saveAutoSubscribeSettings']);

    $router->get('test', [SettingsController::class, 'TestRequestResolver']);
    $router->put('test', [SettingsController::class, 'TestRequestResolver']);
    $router->post('test', [SettingsController::class, 'TestRequestResolver']);
    $router->delete('test', [SettingsController::class, 'TestRequestResolver']);

    $router->post('reset_db', [SettingsController::class, 'resetDB']);
    $router->get('old_logs', [SettingsController::class, 'getOldLogDetails']);
    $router->delete('old_logs', [SettingsController::class, 'removeOldLogs']);

    $router->get('cron_status', [SettingsController::class, 'getCronStatus']);
    $router->post('run_cron', [SettingsController::class, 'runCron']);

    $router->get('db-index-health', [SettingsController::class, 'getDbIndexHealth']);
    $router->post('db-index-health/repair', [SettingsController::class, 'repairDbIndexes']);

    $router->get('rest-keys', [SettingsController::class, 'getRestKeys']);
    $router->post('rest-keys', [SettingsController::class, 'createRestKey']);
    $router->delete('rest-keys', [SettingsController::class, 'deleteRestKey']);


    $router->get('integrations', [SettingsController::class, 'getIntegrations']);
    $router->post('integrations', [SettingsController::class, 'saveIntegration']);

    $router->get('compliance', [SettingsController::class, 'getComplianceSettings']);
    $router->post('compliance', [SettingsController::class, 'updateComplianceSettings']);

    $router->get('experiments', [SettingsController::class, 'getExperimentalSettings']);
    $router->post('experiments', [SettingsController::class, 'updateExperimentalSettings']);
    $router->get('experiments/campaigns', [SettingsController::class, 'getCampaigns']);

    $router->get('system-logs', [SystemLogController::class, 'index']);
    $router->get('system-logs/export', [SystemLogController::class, 'export']);
    $router->delete('system-logs/reset', [SystemLogController::class, 'deleteAll']);

    // will be added in future
    // $router->get('activity-logs', [ActivityLogController::class, 'index']);
    // $router->get('activity-logs/reset', [ActivityLogController::class, 'deleteAll']);

    $router->get('abandon-cart', [AbandonCartSettingsController::class, 'getSettings']);
    $router->post('abandon-cart', [AbandonCartSettingsController::class, 'saveSettings']);

});

$router->prefix('ai')->withPolicy('AiPolicy')->group(function ($router) {
    $router->get('settings', [AiController::class, 'getSettings']);
    $router->post('settings', [AiController::class, 'saveSettings']);
    $router->post('models', [AiController::class, 'getModels']);
    $router->post('test', [AiController::class, 'testConnection']);
    $router->post('generate', [AiController::class, 'generate']);
    $router->post('generate-email-body', [AiController::class, 'generateEmailBody']);
    $router->post('contact-summary', [AiController::class, 'contactSummary']);
});

/*
 * MCP settings endpoints — Settings → MCP admin page (MCP_PLAN.md § 13).
 */
$router->prefix('mcp')->withPolicy('SettingsPolicy')->group(function ($router) {
    $router->get('status', [MCPSettingsController::class, 'status']);
    $router->post('toggle', [MCPSettingsController::class, 'toggle']);
    $router->post('install-adapter', [MCPSettingsController::class, 'installAdapter']);
    $router->get('config-snippet', [MCPSettingsController::class, 'getConfigSnippet']);
});

$router->prefix('abandon-carts')->withPolicy('FunnelPolicy')->group(function ($router) {
    $router->get('/', [AbandonCartController::class, 'getCarts']);
    $router->post('bulk-delete', [AbandonCartController::class, 'handleBulkDeleteCart']);
    $router->get('report-summary', [AbandonCartController::class, 'getReportSummary']);
});

$router->prefix('custom-fields')->withPolicy('CustomFieldsPolicy')->group(function ($router) {
    $router->get('contacts', [CustomContactFieldsController::class, 'getGlobalFields']);
    $router->put('contacts', [CustomContactFieldsController::class, 'saveGlobalFields']);
    $router->put('contacts/update_group_name', [CustomContactFieldsController::class, 'updateGroupName']);
});

$router->prefix('labels')->withPolicy('CustomFieldsPolicy')->group(function ($router) {
    $router->get('/', [GlobalLabelController::class, 'getlabels']);
    $router->post('/', [GlobalLabelController::class, 'create']);
    $router->put('{id}', [GlobalLabelController::class, 'update'])->int('id');
    $router->delete('{id}', [GlobalLabelController::class, 'delete'])->int('id');
});

$router->prefix('webhooks')->withPolicy('WebhookPolicy')->group(function ($router) {
    $router->get('/', [WebhookController::class, 'index']);
    $router->post('/', [WebhookController::class, 'create']);
    $router->put('/{id}', [WebhookController::class, 'update'])->int('id');
    $router->delete('/{id}', [WebhookController::class, 'delete'])->int('id');
});

/*
 * Users
 */
$router->prefix('users')->withPolicy('UsersPolicy')->group(function ($router) {

    $router->get('/', [UsersController::class, 'index']);
    $router->get('/roles', [UsersController::class, 'roles']);

});

/*
 * Import
 */
$router->prefix('import')->withPolicy('ImportUserPolicy')->group(function ($router) {

    $router->post('csv-upload', [CsvController::class, 'upload']);
    $router->post('csv-import', [CsvController::class, 'import']);

    $router->post('users', [UsersController::class, 'import']);

    $router->get('drivers', [ImporterController::class, 'getDrivers']);
    $router->get('drivers/{driver}', [ImporterController::class, 'getDriver'])->alphaNumDash('driver');
    $router->post('drivers/{driver}', [ImporterController::class, 'importData'])->alphaNumDash('driver');

});


/*
 * Fluent Forms Wrapper
 */
$router->prefix('forms')->withPolicy('FormsPolicy')->group(function ($router) {
    $router->get('/', [FormsController::class, 'index']);
    $router->post('/', [FormsController::class, 'create']);
    $router->get('templates', [FormsController::class, 'getTemplates']);
    $router->get('{id}/entries', [FormsController::class, 'getEntries'])->int('id');
    $router->get('{form_id}/entries/{id}', [FormsController::class, 'getEntry'])->int('form_id')->int('id');
});


/*
 * Fluent Forms Wrapper
 */
$router->prefix('docs')->withPolicy('ReportPolicy')->group(function ($router) {
    $router->get('/', [DocsController::class, 'index']);
    $router->get('/{doc_id}', [DocsController::class, 'getDoc'])->int('doc_id');
    $router->get('/addons', [DocsController::class, 'getAddons']);
});

/*
 * Public EndPoints
 */
$router->prefix('public')->withPolicy('PublicPolicy')->group(function ($router) {

    $router->any('bounce_handler/{service_name}/handle/{security_code}', [WebhookBounceController::class, 'handleBounce'])
        ->alphaNumDash('service_name')
        ->alphaNumDash('security_code');

    $router->any('bounce_handler/{service_name}/{security_code}', [WebhookBounceController::class, 'handleBounce'])
        ->alphaNumDash('service_name')
        ->alphaNumDash('security_code');

});


$router->prefix('migrators')->withPolicy('SettingsPolicy')->group(function ($router) {
    $router->get('/', [MigratorController::class, 'getDrivers']);
    $router->post('/verify-cred', [MigratorController::class, 'verifyCredential']);
    $router->get('/list-tag-mappings', [MigratorController::class, 'getListTagMappings']);

    $router->post('/summary', [MigratorController::class, 'getImportSummary']);
    $router->post('/import', [MigratorController::class, 'handleImport']);
});

$router->prefix('companies')->withPolicy('CompanyPolicy')->group(function ($router) {
    $router->get('/', [CompanyController::class, 'index']);
    $router->post('/', [CompanyController::class, 'create']);
    $router->get('/{id}', [CompanyController::class, 'find'])->int('id');
    $router->put('/{id}', [CompanyController::class, 'update'])->int('id');
    $router->delete('/{id}', [CompanyController::class, 'delete'])->int('id');

    $router->get('/search', [CompanyController::class, 'searchCompanies']);
    $router->get('/search-unattached-contacts', [CompanyController::class, 'searchUnattachedContacts']);
    $router->put('companies-property', [CompanyController::class, 'updateProperty']);
    $router->post('attach-subscribers', [CompanyController::class, 'attachSubscribers']);
    $router->post('detach-subscribers', [CompanyController::class, 'detachSubscribers']);
    $router->post('do-bulk-action', [CompanyController::class, 'handleBulkActions']);

    $router->get('{id}/notes', [CompanyController::class, 'getNotes'])->int('id');
    $router->post('{id}/notes', [CompanyController::class, 'addNote'])->int('id');
    $router->put('{id}/notes/{note_id}', [CompanyController::class, 'updateNote'])->int('id')->int('note_id');
    $router->delete('{id}/notes/{note_id}', [CompanyController::class, 'deleteNote'])->int('id')->int('note_id');
    $router->post('{id}/notes/bulk-delete', [CompanyController::class, 'bulkDeleteNotes'])->int('id');

    $router->post('csv-import', [CsvController::class, 'importCompanies']);

    $router->get('custom-fields', [CompanyController::class, 'getCustomGlobalFields']);
    $router->put('custom-fields', [CompanyController::class, 'saveCustomGlobalFields']);
    $router->put('custom-fields/update_group_name', [CompanyController::class, 'updateCustomFieldGroupName']);

    $router->get('{id}/custom_tab_view', [CompanyController::class, 'getCompanyExternalView'])->int('id');
});
