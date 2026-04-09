<?php
/*
Plugin Name: Flip Book
Plugin URI: http://flipbuilder.com
Description: Flip Book WordPress Plugin
Version: 2.0
Author: flipbuilder.com
Author URI: http://flipbuilder.com
License: Copyright 2015 flipbuilder.com, All Rights Reserved
*/

define('FLIPBOOK_VERSION', '2.0');

define('FLIPBOOK_URL', plugin_dir_url( __FILE__ ));

define('FLIPBOOK_PATH', plugin_dir_path( __FILE__ ));

require_once 'app/class-flipbook-controller.php';

class FlipBook_Plugin

{
	public $flipbook_controller;
	
	function __construct() {
		
		$this->init();
	}
	
	public function init()
	{
		
		$this->flipbook_controller = new FlipBook_Controller();

		register_activation_hook( __FILE__, array($this, 'activation_handler') );
		
		add_action( 'admin_menu', array($this, 'register_menu') );
		
		add_shortcode( 'flipbook', array($this, 'shortcode_handler') );
		
		add_action( 'init', array($this, 'register_script') );
		add_action( 'wp_enqueue_scripts', array($this, 'enqueue_script') );
		add_action( 'admin_enqueue_scripts', array($this, 'enqueue_admin_script') );
	}
	
	function activation_handler()
	{
		$this->flipbook_controller->activation_handler();
	}
	
	function enqueue_admin_script($hook)

	{
		//if ($hook == 'admin_page_flipbook_view_slider')

			//wp_enqueue_script('flipbook-script');

	}
	
	function enqueue_script()
	{
		//wp_enqueue_script('flipbook-script');
	}
	
	function register_script()
	{
		//$uploads = wp_upload_dir();
		//$script_url = $uploads['baseurl'] . '/amazingslider/sharedengine/amazingslider.js';
	
		// wp_register_script('flipbook-script', $script_url, array('jquery'), FLIPBOOK_VERSION, false);
		
		if ( is_admin() )

		{

			wp_register_style('flipbookr-style', FLIPBOOK_URL . 'flipbook.css');

			wp_enqueue_style('flipbook-style');

		}
	}
	
	function shortcode_handler($atts)
	{
		if ( !isset($atts['id']) )
			return __('Please specify a book id', 'flipbook');
		
		$id = $atts['id'];
		return $this->flipbook_controller->generate_body_code($id);
	}
	
	function register_menu()
	{
		add_menu_page( 
				__('Flip Book', 'flipbook'), 
				__('Flip Book', 'flipbook'), 
				'manage_options', 
				'flipbook_show_books', 
				array($this, 'show_books'),
				FLIPBOOK_URL . 'images/logo-16.png' );
				
		add_submenu_page(
				'flipbook_show_books', 
				__('Installed Books', 'flipbook'), 
				__('Installed Books', 'flipbook'), 
				'manage_options',
				'flipbook_show_books',
				array($this, 'show_books' ) );
		
		add_submenu_page(

				'flipbook_show_books',

				__('Add New book', 'flipbook'),

				__('Add New', 'flipbook'),

				'manage_options',

				'flipbook_add_new',

				array($this, 'add_new' ) );
		
		add_submenu_page(

				null,

				__('View Book', 'flipbook'),

				__('View Book', 'flipbook'),

				'manage_options',

				'flipbook_view_book',

				array($this, 'view_book' ) );
	}
	
	public function show_books()
	{
		$this->flipbook_controller->show_books();
	}
	
	public function add_new()
	{
		$this->flipbook_controller->add_new();
	}
	
	public function view_book()
	{
		$this->flipbook_controller->view_book();
	}
}

$flipbook_plugin = new FlipBook_Plugin();

/**
 * Global php function
 * @param unknown_type $id
 */
function flipbook($id) {
	
	echo $flipbook_plugin->flipbook_controller->generate_body_code($id);
}

/**
 * Uninstallation
 */
function flipbook_uninstall() {


	global $wpdb;
	

	$table_name = $wpdb->prefix . "flipbook";

	$wpdb->query("DROP TABLE IF EXISTS $table_name");

}

if ( function_exists('register_uninstall_hook') )
	register_uninstall_hook( __FILE__, 'flipbook_uninstall' );

