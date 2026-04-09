<?php 

if( ! class_exists( 'WP_List_Table' ) )
{
	require_once( ABSPATH . 'wp-admin/includes/screen.php' );
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class FlipBook_List_Table extends WP_List_Table {

	private $view;
	public $list_data;
	
	public function __construct($view)
	{
		parent::__construct();
		$this->view = $view;
	}
	
	function get_columns()
	{
		$columns = array(
				'cb' => '<input type="checkbox" />',
				'id' => __('ID', 'flipbook'),
				'name' => __('Name', 'flipbook'),
				'shortcode' => __('Shortcode', 'flipbook'),
				'time' => __('Created', 'flipbook'),
				'author' => __('Author', 'flipbook')
		);
		return $columns;
	}
		
	function prepare_items() 
	{
		
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array($columns, $hidden, $sortable);
				
		usort( $this->list_data, array( &$this, 'usort_reorder' ) );
				
		$this->items = $this->list_data;
	}
	
	function get_sortable_columns() {
		
		$sortable_columns = array(
				'id'  => array('id',true),
				'name' => array('name',true),
				'time'   => array('time',true),
				'author'   => array('author',true)
		);
		
		return $sortable_columns;
	}
	
	function usort_reorder( $a, $b ) {
		
		$orderby = ( ! empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : 'id';
		
		$order = ( ! empty($_GET['order'] ) ) ? $_GET['order'] : 'asc';
		
		if ($orderby == 'id')
			$result = ( (int) $a[$orderby] - (int) $b[$orderby] );
		else
			$result = strcmp( $a[$orderby], $b[$orderby] );
		
		return ( $order === 'asc' ) ? $result : -$result;
	}
	
	function column_cb($item) {
		
		return sprintf('<input type="checkbox" name="bookid[]" value="%s" />', $item['id']);
	}
	
	function column_default( $item, $column_name ) 
	{
		switch( $column_name ) {
			case 'id':
				$actions = array(
					'delete' => sprintf('<a href="?page=%s&action=%s&bookid=%s">Delete</a>', $_REQUEST['page'], 'delete', $item['id']),
					'view' => sprintf('<a href="?page=%s&bookid=%s">View</a>', 'flipbook_view_book', $item['id']),
				);
				return sprintf('%1$s %2$s', $item['id'], $this->row_actions($actions) );
			case 'name':
			case 'time':
				return $item[ $column_name ];
			case 'author':
				return get_userdata( $item[$column_name] )->display_name;
			case 'shortcode':
				return esc_attr('[flipbook id="' . $item['id'] . '"]');
			default:
				return $item[ $column_name ];
		}
	}
	
	function get_bulk_actions() {
		
		$actions = array(
				'delete' => 'Delete'
		);
		return $actions;
	}

}