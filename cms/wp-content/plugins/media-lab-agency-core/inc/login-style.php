<?php
/**
 * WP Login – Theme-Style
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function medialab_login_enqueue_styles(): void {
	$theme_css_path = get_stylesheet_directory() . '/assets/dist/css/style.css';

	if ( file_exists( $theme_css_path ) ) {
		wp_enqueue_style(
			'medialab-theme-vars',
			get_stylesheet_directory_uri() . '/assets/dist/css/style.css',
			array(),
			filemtime( $theme_css_path )
		);
	}

	wp_enqueue_style(
		'medialab-login-style',
		MEDIALAB_CORE_URL . 'assets/css/login-styles.css',
		array(),
		MEDIALAB_CORE_VERSION
	);

	// forgetmenot + submit in einen Flex-Wrapper zusammenführen
	wp_add_inline_script( 'jquery-core', "
		document.addEventListener('DOMContentLoaded', function () {
			var form       = document.getElementById('loginform');
			var forgetmenot = form ? form.querySelector('p.forgetmenot') : null;
			var submit      = form ? form.querySelector('p.submit') : null;

			if ( ! forgetmenot || ! submit ) return;

			var wrapper = document.createElement('div');
			wrapper.className = 'mlt-login-row';

			// Inhalte der beiden <p>-Elemente in den Wrapper verschieben
			wrapper.appendChild(forgetmenot.cloneNode(true));
			wrapper.appendChild(submit.cloneNode(true));

			// Wrapper nach dem submit-Element einfügen
			submit.parentNode.insertBefore(wrapper, submit.nextSibling);
		});
	", 'after' );
}
add_action( 'login_enqueue_scripts', 'medialab_login_enqueue_styles' );

add_filter( 'login_headerurl',  fn() => home_url( '/' ) );
add_filter( 'login_headertext', fn() => get_bloginfo( 'name' ) );
