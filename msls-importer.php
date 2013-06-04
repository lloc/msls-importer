<?php

/*
Plugin Name: Msls Importer
Plugin URI: http://lloc.de/msls
Description: Imports and exports the stored options and relations of the Multisite Language Switcher.
Version: 0.1
Author: Dennis Ploetner
Author URI: http://lloc.de/
*/

/*
 Copyright 2013  Dennis Ploetner  (email : re@lloc.de)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ( !is_admin() )
	return;

function msls_importer_init() {
	ob_start();
	load_plugin_textdomain( 'msls-importer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'msls_importer_init' );

function msls_importer_menu() {
	add_management_page( 'Msls Importer', 'Msls Importer', 'manage_options', 'msls-importer', 'msls_importer_dialog' );
}
add_action('admin_menu', 'msls_importer_menu' );
	
function msls_importer_dialog() {
	if ( !isset( $_POST['export'] ) ) {
		echo '<div class="wrap">';

		echo '<div class="icon32" id="icon-tools"><br></div>';
		echo '<h2>', __( 'Msls Importer', 'msls_importer' ), '</h2>';

		echo '<h3>', __( 'Export', 'msls_importer' ), '</h3>';
		echo '<p>', __( 'This backup file contains all configution and settings for the Multisite Language Switcher of your whole network.<br/><br/>Note that it do <b>NOT</b> contain posts, pages, or any other data.', 'msls_importer' ), '</p>';
		echo '<form method="post"><p class="submit">';
		wp_nonce_field( 'msls-importer-export' );
		echo '<input type="submit" name="export" value="', __( 'Download backup file', 'msls_importer' ), '"/>';
		echo '</p></form>';

		echo '<h3>', __( 'Import', 'msls_importer' ), '</h3>';
		if ( isset( $_FILES['import'] ) && check_admin_referer( 'msls-importer-import' ) ) {
			if ( $_FILES['import']['error'] > 0 ) {
				wp_die( __( 'Ooops! Error happens...', 'msls_importer' ) );
			}
			else {
				$file_ext  = pathinfo( $_FILES['import']['name'], PATHINFO_EXTENSION );
				if ( 'json' == strtolower( $file_ext ) ) {
					global $wpdb;
					$options = json_decode( file_get_contents( $_FILES['import']['tmp_name'] ), true );
					$blogs   = MslsBlogCollection::instance();
					foreach ( $blogs->get_objects() as $blog ) {
						$language = $blog->get_language();
						if ( isset( $options[$language] ) ) {
							if ( $blog->userblog_id != $blogs->get_current_blog_id() )
								switch_to_blog( $blog->userblog_id );
							foreach ( $options[$language] as $key => $value )
								update_option( $key, $value );
							if ( $blog->userblog_id != $blogs->get_current_blog_id() )
								restore_current_blog();
						}
					}
					echo '<div class="updated"><p>', __( 'All options are restored successfully.', 'msls_importer' ), '</p></div>';
				}
				else {
					echo '<div class="error"><p>', __( 'Invalid file or file size too big.', 'msls_importer' ), '</p></div>';
				}
			}
		}
		echo '<p>', __( 'Click Browse button and choose a json file that you backup before.', 'msls_importer' ), '</p>';
		echo '<p>', __( 'Press Restore button, WordPress do the rest for you.', 'msls_import' ), '</p>  ';
		echo '<form method="post" enctype="multipart/form-data"><p class="submit">';
		wp_nonce_field( 'msls-importer-import' );
		echo '<input type="file" name="import" /><br/>'; 
		echo '<input type="submit" name="submit" value="', __( 'Restore from backup file' ), '" />';
		echo '</p></form>';
		echo '</div>';
	}
	elseif ( check_admin_referer( 'msls-importer-export' ) ) {
		global $wpdb;
		$options = array();
		$blogs   = MslsBlogCollection::instance();
		foreach ( $blogs->get_objects() as $blog ) {
			$language = $blog->get_language();
			if ( $blog->userblog_id != $blogs->get_current_blog_id() )
				switch_to_blog( $blog->userblog_id );
			$result  = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'msls%'" );
			if ( is_array( $result ) ) {
				foreach ( $result as $row )
					$options[$language][$row->option_name] = maybe_unserialize( $row->option_value );
			}
			if ( $blog->userblog_id != $blogs->get_current_blog_id() )
				restore_current_blog();
		}
		$json_name = sanitize_file_name( esc_attr( get_site_option( 'site_name' ) ) . '-' .  date( 'm-d-Y' ) . '.json' );
		$json_file = json_encode( $options );
	
		ob_clean();
		echo $json_file;
		header( 'Content-Type: text/json; charset=' . get_option( 'blog_charset' ) );
		header( 'Content-Disposition: attachment; filename=' . $json_name );
		exit();
	}
}

function msls_importer_notice() {
	if ( !is_plugin_active( 'multisite-language-switcher/MultisiteLanguageSwitcher.php' ) )
		echo '<div class="updated"><p>Msls Importer will not work like expected because its functionality depends on the <strong>Multisite Language Switcher</strong>.</p></div>';
}
add_action( 'admin_notices', 'msls_importer_notice' );

