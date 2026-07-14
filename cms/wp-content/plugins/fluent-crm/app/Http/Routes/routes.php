<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/**
 * @var $router FluentCrm\Framework\Http\Router
 */

$router->namespace('FluentCrm\App\Http\Controllers')->group(function($router) {
    require_once __DIR__ . '/api.php';
});
