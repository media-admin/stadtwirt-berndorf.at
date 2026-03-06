<?php
/**
 * Redirects Manager
 * 301/302/307 redirects with wildcard support, managed via admin UI.
 * Also logs 404 hits with counter for analysis.
 *
 * Features:
 * - Admin table: Source URL, Target URL, Type (301/302/307), Hits, Date
 * - Wildcard support: /old-path/* → /new-path/*
 * - 404 log with hit counter (auto-cleared after 90 days via WP-Cron)
 * - Import/Export via CSV
 *
 * @package Media Lab SEO Toolkit
 * @version 1.1.1
 * TODO: Implement redirect manager + 404 logger
 */
if (!defined('ABSPATH')) { exit; }
