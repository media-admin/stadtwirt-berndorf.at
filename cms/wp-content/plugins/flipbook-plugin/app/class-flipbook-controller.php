<?php 

require_once 'class-flipbook-model.php';
require_once 'class-flipbook-view.php';

class FlipBook_Controller {

	private $view, $model;

	function __construct() {

		$this->model = new FlipBook_Model($this);	
		$this->view = new FlipBook_View($this);
	}

	function add_new() {
		
		$this->view->print_add_new();
	}
	
	function show_books() {
		
		$this->view->print_books();
	}
	
	function view_book()
	{
		$this->view->view_book();
	}
	
	function install_book($zipfile, $id) {
		
		return $this->model->install_book($zipfile, $id);
	}
	
	function install_book_from_folder($install_folder, $id) {
		
		return $this->model->install_book_from_folder($install_folder, $id);
	}
	
	function generate_body_code($id) {
		
		return $this->model->generate_body_code($id);
	}
	
	function generate_body_code_Preview($id) {
		
		return $this->model->generate_body_code_Preview($id);
	}
	
	function get_list_data() {
		
		return $this->model->get_list_data();
	}
	
	function delete_book($id)
	{
		return $this->model->delete_book($id);
	}
	
	function activation_handler()
	{
		if ( !$this->model->is_db_table_exists() )
			$this->model->install_book_from_folder( FLIPBOOK_PATH, -1);
	}
}