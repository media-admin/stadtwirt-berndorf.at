<?php
/**
 * Template Part: Breadcrumbs
 *
 * Verwendung in Templates:
 *   get_template_part('template-parts/components/breadcrumbs');
 *
 * Mit Optionen via set_query_var:
 *   set_query_var('breadcrumbs_args', ['separator' => '/']);
 *   get_template_part('template-parts/components/breadcrumbs');
 *
 * Direkt mit eigener Wrapper-Klasse:
 *   get_template_part('template-parts/components/breadcrumbs');
 *   // → class="breadcrumbs" (Standard)
 *   // → class="breadcrumbs breadcrumbs--light" für dunkle Hintergründe
 *
 * @package custom-theme
 */

if ( ! defined('ABSPATH') ) exit;
if ( ! function_exists('medialab_breadcrumbs') ) return;

// Optionen aus set_query_var oder Standardwerte
$bc_args = get_query_var('breadcrumbs_args', []);

medialab_breadcrumbs( $bc_args );

// Query-Var zurücksetzen
set_query_var('breadcrumbs_args', []);
