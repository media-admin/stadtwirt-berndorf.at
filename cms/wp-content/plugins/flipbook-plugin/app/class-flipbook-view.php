<?php 

require_once 'class-flipbook-list-table.php';

class FlipBook_View {

	private $controller;
	private $list_table;
	
	function __construct($controller) {
		
		$this->controller = $controller;
	}
	
	function print_add_new() {
		
		if ( !empty($_POST) && (isset($_POST['flipbook-upload']) || isset($_POST['flipbook-upload-url']) || isset($_POST['flipbook-upload-folder'])) && wp_verify_nonce($_POST['flipbook-nonce'],'flipbook-add-new-add-new') )
		{	
			if ( isset($_POST['flipbook-upload']) )	
			{	
				if ( !isset($_FILES['flipbook-zip']) || ($_FILES["flipbook-zip"]["error"] > 0) )
				{
					if ($_FILES["flipbook-zip"]["error"] == 1)
						echo '<div class="error"><p>' . __('The uploaded file exceeds the upload_max_filesize directive in php.ini', 'flipbook')  . '</p></div>';
					else
						echo '<div class="error"><p>' . __('Please select a ZIP file created with Flip Book', 'flipbook')  . '</p></div>';
				}
				else
				{		
					$uploadfile = wp_handle_upload($_FILES['flipbook-zip'], array(
							'test_form' => false,
							'mimes' => array('zip' => 'application/zip')
					));
					
					if ( isset($uploadfile['error']) )
					{
						echo '<div class="error"><p>' . esc_attr($uploadfile['error'])  . '</p></div>';
					}
					else
					{
						$id = ( isset($_POST['flipbook-replace']) && ($_POST['flipbook-replace'] == 'yes') && isset($_POST['flipbook-replace-id']) ) ? ($_POST['flipbook-replace-id']) : -1;
						$ret = $this->controller->install_book($uploadfile['file'], $id);
						if ( is_wp_error($ret) )
						{
							echo '<div class="error"><p>' . $ret->get_error_message()  . '</p></div>';
						}
						else
						{		
							if ( $ret["new"] )
								echo '<div class="error"><p>This is a new version Flip Book WordPress Plugin. In addition to adding the Book here, you also need to update the Plugin itself. Fore more inforamtion, visit <a href="http://flipbuilder.com/how-to-update-flip-builder-wordpress-plugin/" target="_blank">How to update Flip Book WordPress Plugin</a>.</p></div>';
															
							echo '<div class="updated"><p>The book is successfully installed. &nbsp;&nbsp;<a href="?page=flipbook_view_book&bookid=' . $ret["id"] . '">View The Book</a></p></div>';
							echo '<div class="updated"><p> To embed the book into your page, use shortcode <strong>[flipbook id="' . $ret["id"] . '"]</strong></p></div>';
							echo '<div class="updated"><p> To embed the book into your template, use php code <strong>' . esc_attr('<?php echo do_shortcode(\'[flipbook id="' . $ret["id"] . '"]\'); ?>') . '</strong></p></div>';						
						}
					}
				}
			}
			else if ( isset($_POST['flipbook-upload-url']) )
			{				
				if ( !isset($_POST['flipbook-zip-url']) || strlen($_POST['flipbook-zip-url']) == 0)	
				{
					echo '<div class="error"><p>' . __('Please specify a URL of ZIP file created with Flip Book', 'flipbook')  . '</p></div>';
				}	
				else
				{
					$accepted_status_codes = array( 200, 301, 302 );
					$response = wp_remote_head( $_POST['flipbook-zip-url'] );
					if ( is_wp_error( $response ) || !in_array( wp_remote_retrieve_response_code( $response ), $accepted_status_codes ) ) {
						echo '<div class="error"><p>' . __('The specified URL does not exist', 'flipbook')  . '</p></div>';
					}
					else
					{				
						$uploadfile = fopen($_POST['flipbook-zip-url'], "r");
						if ( !$uploadfile )
						{
							echo '<div class="error"><p>' . __('The specified URL does not exist', 'flipbook')  . '</p></div>';
						}
						else
						{						
							$uploads = wp_upload_dir();
							$zipfile = $uploads['basedir'] . '/flipbook/flipbook-url-upload.zip';
							
							while ( !feof($uploadfile) )
							{
								file_put_contents($zipfile, fread($uploadfile, 4096), FILE_APPEND);
							}
							fclose($uploadfile);
							
							$id = ( isset($_POST['flipbook-replace']) && ($_POST['flipbook-replace'] == 'yes') && isset($_POST['flipbook-replace-id']) ) ? ($_POST['flipbook-replace-id']) : -1;
							$ret = $this->controller->install_book($zipfile, $id);
							if ( is_wp_error($ret) )
							{
								echo '<div class="error"><p>' . $ret->get_error_message()  . '</p></div>';
							}
							else
							{
								if ( $ret["new"] )
									echo '<div class="error"><p>This is a new version Flip Book WordPress Plugin. In addition to adding the book here, you also need to update the Plugin itself. Fore more inforamtion, visit <a href="http://flipbuilder.com/how-to-update-flip-builder-wordpress-plugin/" target="_blank">How to update Flip Book WordPress Plugin</a>.</p></div>';
									
								echo '<div class="updated"><p>The book is successfully installed. &nbsp;&nbsp;<a href="?page=flipbook_view_book&bookid=' . $ret["id"] . '">View The Book</a></p></div>';
								echo '<div class="updated"><p> To embed the book into your page, use shortcode <strong>[flipbook id="' . $ret["id"] . '"]</strong></p></div>';
								echo '<div class="updated"><p> To embed the book into your template, use php code <strong>' . esc_attr('<?php echo do_shortcode(\'[flipbook id="' . $ret["id"] . '"]\'); ?>') . '</strong></p></div>';
							}
						}
					}
				}
			}
			else if ( isset($_POST['flipbook-upload-folder']) )
			{				
				if ( !isset($_POST['flipbook-zip-folder']) || strlen(trim($_POST['flipbook-zip-folder'])) == 0)
				{
					echo '<div class="error"><p>' . __('Please specify a folder for the unzipped plugin files', 'flipbook')  . '</p></div>';
				}
				else
				{
					$install_folder = trim($_POST['flipbook-zip-folder']);
					$id = ( isset($_POST['flipbook-replace']) && ($_POST['flipbook-replace'] == 'yes') && isset($_POST['flipbook-replace-id']) ) ? ($_POST['flipbook-replace-id']) : -1;
					$ret = $this->controller->install_book_from_folder($install_folder, $id);
					if ( is_wp_error($ret) )
					{
						echo '<div class="error"><p>' . $ret->get_error_message()  . '</p></div>';
					}
					else
					{
						if ( $ret["new"] )
							echo '<div class="error"><p>This is a new version Flip Book WordPress Plugin. In addition to adding the book here, you also need to update the Plugin itself. Fore more inforamtion, visit <a href="http://flipbuilder.com/how-to-update-flip-builder-wordpress-plugin/" target="_blank">How to update Flip Book WordPress Plugin</a>.</p></div>';
							
						echo '<div class="updated"><p>The book is successfully installed. &nbsp;&nbsp;<a href="?page=flipbook_view_book&bookid=' . $ret["id"] . '">View The Book</a></p></div>';
						echo '<div class="updated"><p> To embed the book into your page, use shortcode <strong>[flipbook id="' . $ret["id"] . '"]</strong></p></div>';
						echo '<div class="updated"><p> To embed the book into your template, use php code <strong>' . esc_attr('<?php echo do_shortcode(\'[flipbook id="' . $ret["id"] . '"]\'); ?>') . '</strong></p></div>';
					}
				}
			}
		}
		?>
		<div class="wrap">
			<div id="icon-flipbook" class="icon32"><br /></div>
			
			<h2><?php _e( 'Add New Book', 'flipbook' ); ?> <a href="<?php echo admin_url('admin.php?page=flipbook_show_books'); ?>" class="add-new-h2">Show All Books</a></h2>
									
			<form id="form-flipbook-add-new" method="post" enctype="multipart/form-data"> 
				<ul>
					<li>
						<h3><?php _e( 'Upload the ZIP file created with <a href="http://flipbook.com" target="_blank">Flip Book</a>:', 'flipbook' ); ?></h3>
						<input type="file" id="flipbook-zip" name="flipbook-zip" />
						<input id="flipbook-upload" name="flipbook-upload" type="submit" class="button" value="<?php esc_attr_e('Install', 'flipbook') ?>" />
					</li> 
					<li>
						<h3><?php _e( 'Or install the ZIP file from URL:', 'flipbook' ); ?></h3>
						<p><?php _e( 'If the ZIP file is too large, you can upload the file to your web server via FTP, then install the ZIP file from URL.', 'flipbook' ); ?></p>
						<input type="text" id="flipbook-zip-url" name="flipbook-zip-url" size="60" />
						<input id="flipbook-upload-url" name="flipbook-upload-url" type="submit" class="button" value="<?php esc_attr_e('Install', 'flipbook') ?>" />
						<p><strong><?php _e( '* Installing may take a while depending on the size of your ZIP file. Please be patient.', 'flipbook' ); ?></strong></p>
					</li> 
					<li>
						<h3><?php _e( 'Or install the plugin from folder:', 'flipbook' ); ?></h3>
						<p><?php _e( 'You can unzip the plugin ZIP file at local hard disk, then upload the plugin folder to your WordPress uploads folder, and install from the folder.', 'flipbook' ); ?></p>
						
						<?php 
						$uploads = wp_upload_dir();
						$upload_folder = $uploads['basedir'] . "/flipbook-plugin/";
						?>
						<input type="text" id="flipbook-zip-folder" name="flipbook-zip-folder" size="60" value="<?php echo $upload_folder; ?>" />
						<input id="flipbook-upload-folder" name="flipbook-upload-folder" type="submit" class="button" value="<?php esc_attr_e('Install', 'flipbook') ?>" />
						<p><strong><?php _e( '* You can use this option if the above two options do not work on your WordPress website.', 'flipbook' ); ?></strong></p>
					</li>
					<li>
						<input type="checkbox" name="flipbook-replace" value="yes"><?php esc_attr_e('Replace an existing book', 'flipbook') ?>:&nbsp;
						<select name="flipbook-replace-id">
							<?php 
							global $wpdb;
							$table_name = $wpdb->prefix . "flipbook";
							$book_rows = $wpdb->get_results( "SELECT * FROM $table_name", ARRAY_A);						
							if ( $book_rows )
							{
								foreach ( $book_rows as $row )
									echo '<option value="' . $row['id'] . '">' . $row['id'] . ' : ' . $row['name'] . '</option>';
							}
							?>
						</select>
					</li>
	  			</ul>
	  			<?php wp_nonce_field('flipbook-add-new-add-new','flipbook-nonce'); ?>
	  		</form>              
		</div>
		<?php
	}
	
	function print_books() {
		
		?>
		<div class="wrap">
		<div id="icon-flipbook" class="icon32"><br /></div>
			
		<h2><?php _e( 'Installed Books', 'flipbook' ); ?> <a href="<?php echo admin_url('admin.php?page=flipbook_add_new'); ?>" class="add-new-h2">Add New</a> </h2>
		
		<?php $this->process_delete_action(); ?>
		
		<form id="book-list-table" method="post">
		<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
		<?php 
		
		if ( !is_object($this->list_table) )
			$this->list_table = new FlipBook_List_Table($this);
		$this->list_table->list_data = $this->controller->get_list_data();
		$this->list_table->prepare_items();
		$this->list_table->display();		
		?>								
        </form>
        
		</div>
		<?php
	}
	
	function process_delete_action()
	{
		$deleted = 0;
		
		if ( isset($_REQUEST['action']) && ($_REQUEST['action'] == 'delete') && isset( $_REQUEST['bookid'] ) )
		{
			if ( is_array( $_REQUEST['bookid'] ) )
			{
				foreach( $_REQUEST['bookid'] as $id)
				{
					$ret = $this->controller->delete_book($id);
					if ($ret > 0)
						$deleted += $ret;
				}
			}
			else
			{
				$deleted = $this->controller->delete_book( $_REQUEST['bookid'] );
			}
		}
	
		if ($deleted > 0)
		{
			echo '<div class="updated"><p>';
			printf( _n('%d book deleted.', '%d books deleted.', $deleted), $deleted );
			echo '</p></div>';
		}
		
	}
	
	function view_book()
	{
		if ( !isset( $_REQUEST['bookid'] ) )
			return;
		
		?>
		<div class="wrap">
		<div id="icon-flipbook" class="icon32"><br /></div>
					
		<h2><?php _e( 'View Book', 'flipbook' ); ?> <a href="<?php echo admin_url('admin.php?page=flipbook_show_books'); ?>" class="add-new-h2">Show All Books</a> <a href="<?php echo admin_url('admin.php?page=flipbook_add_new'); ?>" class="add-new-h2">Add New</a> </h2>
		
		<div class="updated"><p style="text-align:center;"> To embed the book into your page, use shortcode <strong><?php echo esc_attr('[flipbook id="' . $_REQUEST['bookid'] . '"]'); ?></strong></p></div>

		<div class="updated"><p style="text-align:center;"> To embed the book into your template, use php code <strong><?php echo esc_attr('<?php echo do_shortcode(\'[flipbook id="' . $_REQUEST['bookid'] . '"]\'); ?>'); ?></strong></p></div>
		
		<?php
		echo $this->controller->generate_body_code_Preview( $_REQUEST['bookid'] ); 
		?>	 

		</div>
		<?php
	}
		
}