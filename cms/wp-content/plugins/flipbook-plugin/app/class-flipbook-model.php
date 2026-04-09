<?php 
require_once 'functions.php';
class FlipBook_Model {
	private $controller;
	function __construct($controller) {
		$this->controller = $controller;
	}
	
	function get_upload_path() {
		$uploads = wp_upload_dir();
		return $uploads['basedir'] . '/flipbook/';
	}
	
	function get_upload_url() {
		$uploads = wp_upload_dir();
		return $uploads['baseurl'] . '/flipbook/';
	}
	
	function install_book_from_folder($install_folder, $id) {
		// write to db
		$ret = $this->add_book_to_db($install_folder . 'data/', $id);
		if ( is_wp_error($ret) )
			return $ret;
		
		// copy to uploads folder
		$upload_folder = $this->get_upload_path();
		if ( !is_dir( $upload_folder ) ){
			if ( 0 == wp_mkdir_p( $upload_folder ) )
				return new WP_Error('filesystem', __("Cannot create folder", "flipbook"));
		}
		
		$book_folder = $upload_folder . $ret['id'] . '/';
		if ( is_dir( $book_folder ) )
			flipbook_recurse_rmdir( $book_folder );
		if ( 0 == wp_mkdir_p( $book_folder ) )
			return new WP_Error('filesystem', __("Cannot create folder", "flipbook"));
		flipbook_recurse_copy($install_folder . 'data/', $book_folder);
		/*
		// copy sharedengine to upload folder
		$sharedengine_folder = $upload_folder . 'sharedengine/';
		if ( is_dir( $sharedengine_folder ) )
			flipbook_recurse_rmdir( $sharedengine_folder );
		if ( 0 == wp_mkdir_p( $sharedengine_folder ) )
			return new WP_Error('filesystem', __("Cannot create folder", "flipbook"));
		
		flipbook_recurse_copy($install_folder . 'sharedengine/', $sharedengine_folder);
		// modify id of slider
		$file = $slider_folder . 'js/config.js';
		$content = file_get_contents($file);
		$jsfolder = $this->get_upload_url() . $ret['id'] . '/';
		$content = str_replace("%DESTURL%", $jsfolder, $content);
		$content = str_replace("%SLIDERID%", $ret['id'], $content);
		file_put_contents($file, $content);
		
		$file1 = $slider_folder . 'js/jquery.hislider.js';
		$content1 = file_get_contents($file1);
		$jsfolder = $this->get_upload_url() . $ret['id'] . '/';
		$content1 = str_replace("%DESTURL%", $jsfolder, $content1);
		$content1 = str_replace("%SLIDERID%", $ret['id'], $content1);
		file_put_contents($file1, $content1);*/
		
		return array(
			"id" => $ret['id']);
	}
	
	function install_book($zipfile, $id){
		// unzip
		$unzip_folder = trailingslashit ( dirname( $zipfile ) );
		$ret = flipbook_unzip_file($zipfile, $unzip_folder);
		if ( is_wp_error($ret) )
			return $ret;
		unlink( $zipfile );
		$ret = $this->install_book_from_folder( $unzip_folder . "flipbook-plugin/", $id );
		if (!is_array($ret)) return $ret;
		if ( is_wp_error($ret) )
			return $ret;
		// check new version
		$ret["new"] = false;
		$file = $unzip_folder . "flipbook-plugin/" . "flipbook.php";
		$content = file_get_contents($file);
		$pattern = '/define\(\'FLIPBOOK_VERSION\', \'([0-9\.]+)\'\);/';
		if ( preg_match($pattern, $content, $matches) )
		{
			if ( floatval($matches[1]) > floatval(FLIPBOOK_VERSION) )
				$ret["new"] = true;
		}
		flipbook_recurse_rmdir( $unzip_folder . "flipbook-plugin/" );
		return $ret;
	}

	function add_book_to_db($folder, $id) {
		$this->create_db_table();
		return $this->insert_book_to_db_table($folder, $id);
	}

	function is_db_table_exists() {
		global $wpdb;
		$table_name = $wpdb->prefix . "flipbook";
		return ( $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name );
	}

	function create_db_table() {
		if ($this->is_db_table_exists()) return;
		global $wpdb;
		$table_name = $wpdb->prefix . "flipbook";
		$charset = '';
		if ( !empty($wpdb -> charset) ) 
			$charset = "DEFAULT CHARACTER SET $wpdb->charset";
		if ( !empty($wpdb -> collate) ) 
			$charset .= " COLLATE $wpdb->collate";
		$sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		authorid tinytext NOT NULL,
		name tinytext DEFAULT '' NOT NULL,
		images text DEFAULT '' NOT NULL,
		bookcode text DEFAULT '' NOT NULL,
		PRIMARY KEY  (id)
		) $charset;";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

	function insert_book_to_db_table($folder, $id) {
		global $wpdb, $user_ID;
		$table_name = $wpdb->prefix . "flipbook";
		$file = $folder . "index.html";
		if ( !is_file($file) )
			return new WP_Error('filesystem', __("Cannot find book file", "flipbook"));
		$time = current_time('mysql');
		$authorid = $user_ID;
		if ( ($id > 0) && $this->is_id_exist($id) ){
			$ret = $wpdb->query( $wpdb->prepare("UPDATE $table_name SET time=%s, authorid=%s WHERE id=%d",$time,$authorid,$id) );	
			if (!$ret)
				return new WP_Error('wp_db', __("Cannot insert book to database", "flipbook"));
		}else{
			$ret = $wpdb->query( $wpdb->prepare(
					"INSERT INTO $table_name (time, authorid, name, images, bookcode)
					VALUES (%s, %s, %s, %s, %s)",$time,$authorid,"","",""));
			if(!$ret)
				return new WP_Error('wp_db', __("Cannot insert book to database", "flipbook"));
			$id = $wpdb->insert_id;
		}
		$ret = $this->read_book($file, $id);
		if ( is_wp_error($ret) )
			return $ret;		
		$name = $ret['name'];
		$images = $ret['images'];
		$bookcode = $ret['bookcode'];
		$ret = $wpdb->query( $wpdb->prepare(
				"UPDATE $table_name SET name=%s,images=%s,bookcode=%s WHERE id=%d",$name,$images,$bookcode,$id));
		return array("id"=>$id);
	}

	function read_book($file, $id) {
		$dest_url = $this->get_upload_url() . $id . '/';
		$content = file_get_contents($file);
		$name = "";
		$pattern = '/<title>(.*)<\/title>/';
		if ( preg_match($pattern, $content, $matches) )
			$name = $matches[1];
		$bookcode = "";
		$pattern = '/<!--you can copy the below code to your htm page-----------------------------begin--->(.*)<!--you can copy the above code to your htm page-----------------------------end--->/s';
		if ( preg_match($pattern, $content, $matches) ){
			
			$embed_url = str_replace("HTTPS://", "//", $dest_url);
			$embed_url = str_replace("https://", "//", $embed_url);
			$embed_url = str_replace("HTTP://", "//", $embed_url);
			$embed_url = str_replace("http://", "//", $embed_url);
			
			$bookcode = str_replace("%DESTURL%", $embed_url, $matches[1]);
			$bookcode = str_replace("%SLIDERID%", $id, $bookcode);
		}
		
		$imagescode = $bookcode;
		$pattern = '/<ul class=[\"\']flipbook-books[\"\'].*<\/ul>/sU';
		if ( preg_match($pattern, $bookcode, $matches) ){
			$imagescode = $matches[0];
		}
		$images = "";
		$pattern = '/<img (data-)*src="([^"]+)"/';
		if ( preg_match_all($pattern, $imagescode, $matches) )
			$images = serialize($matches[2]);
		return array("name" => $name,"images" => $images,"bookcode" => $bookcode);
	}
	
	function is_id_exist($id){
		global $wpdb;
		$table_name = $wpdb->prefix . "flipbook";
		$book_row = $wpdb->get_row("SELECT * FROM $table_name WHERE id = $id");
		return ($book_row != null);
	}
	
	function generate_body_code($id){
		global $wpdb;
		$table_name = $wpdb->prefix . "flipbook";
		$ret = "";
		$book_row = $wpdb->get_row("SELECT * FROM $table_name WHERE id = $id");
		if ($book_row != null){
			$ret = $book_row->bookcode;
		}else{
			$ret = '<p>The specified book id does not exist.</p>';
		}
		return $ret;
	}
	
	function generate_body_code_Preview($id){
		global $wpdb;
		$table_name = $wpdb->prefix . "flipbook";
		$ret = "";
		$book_row = $wpdb->get_row("SELECT * FROM $table_name WHERE id = $id");
		if ($book_row != null){
			$str = $book_row->bookcode;
			$ret='<iframe  style="width:100%;height:500px" '.strstr($str, 'src=');
		}else{
			$ret = '<p>The specified book id does not exist.</p>';
		}
		return $ret;
	}
	
	function get_list_data(){
		global $wpdb;
		$table_name = $wpdb->prefix . "flipbook";
		$book_rows = $wpdb->get_results( "SELECT * FROM $table_name", ARRAY_A);
		$ret = array();
		if($book_rows){
			foreach($book_rows as $row){
				$ret[]=array("id"=>$row['id'],'name'=>$row['name'],'images'=>$row['images'],'time'=>$row['time'],'author'=>$row['authorid']);
			}
		}
		return $ret;
	}
	
	function delete_book($id){
		global $wpdb;
		$table_name = $wpdb->prefix . "flipbook";
		$ret = $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE id=%s",$id));
		return $ret;
	}
}