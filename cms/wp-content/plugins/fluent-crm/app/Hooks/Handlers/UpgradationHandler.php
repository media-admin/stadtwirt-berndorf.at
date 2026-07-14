<?php

namespace FluentCrm\App\Hooks\Handlers;

class UpgradationHandler
{
    public static function maybeUpdateDbTables()
    {
        $currentDbVerson = get_option('_fluentcrm_db_version');
        if (!$currentDbVerson || version_compare($currentDbVerson, FLUENTCRM_DB_VERSION, '<')) {
            require_once(FLUENTCRM_PLUGIN_PATH . 'database/FluentCRMDBMigrator.php');

            // A migration just ran (and an index ALTER may have failed mid-flight).
            // Drop the cached index-health snapshot so the next health check hits
            // the live DB and the on-load self-heal reflects the true post-migration
            // state instead of a pre-migration "ok". Stored as an empty array (not
            // deleted) because getIndexHealth() treats an empty cache as stale.
            fluentcrm_update_option('_db_index_health', []);
        }
    }

    public static function updateTables()
    {
        // Run DB Migrations
        require_once(FLUENTCRM_PLUGIN_PATH . 'database/FluentCRMDBMigrator.php');
    }
}
