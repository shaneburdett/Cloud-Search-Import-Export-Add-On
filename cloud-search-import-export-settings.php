<?php
/**
 * Plugin Name: Cloud Search Import/Export Settings
 * Description: Simple Import/Export Plugin for Cloud Search Plugin Settings. 
 * Version: 1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// Verify Cloud Search Plugin is Active
function cldchimp_activation() {
	
	if ( ! is_plugin_active( 'cloud-search/cloud-search.php' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		die( __( 'The plugin has been disabled because it will not work without the Cloud Search Plugin. ') );
	}
}
register_activation_hook( __FILE__, 'cldchimp_activation' );

/**
 * Register the settings page
 */
function cldchimp_settings_menu() {
	add_options_page( __( 'Cloud Search Import/Export Settings' ), __( 'Cloud Search Import/Export Settings' ), 'manage_options', 'cldchimp_settings', 'cldchimp_settings_page' );
}
add_action( 'admin_menu', 'cldchimp_settings_menu' );

/**
 * Render the settings page
 */
function cldchimp_settings_page() {
    if ( ! is_plugin_active( 'cloud-search/cloud-search.php' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		die( __( 'The plugin has been disabled because it will not work without the Cloud Search Plugin. ') );
	}
	$options = ACS::get_instance()->get_settings(); ?>
	<div class="wrap">
		<h2>CURRENT CLOUD SEARCH PLUGIN SETTINGS:</h2>
		<table class="form-table">
			<tbody>
				<?php 
					if (isset($options) && $options !="") {
    					foreach ($options as $key => $value) {
    					    if (isset($value) && $value != "") {
    					        echo '<tr>';
    					        echo '<td>'. $key . ':</td>';
        					        if ($key == 'acs_schema_fields' || $key == 'acs_schema_types' || $key == 'acs_schema_taxonomies' ) {
                                        $fields_value = explode( ",", $value);
                                        $count = 0;
                                        echo '<td><tr>';
                                            foreach ( $fields_value as $site_field_value ) {
                                                echo '<td><b>' . $site_field_value . '</b></td>';
                                                $count ++;
                                                if ($count == 5) {
                                                    echo '</tr><tr>';
                                                    $count = 0;
                                                }
                                            }
                                        echo '</tr></td>';
                                    } else if ($value == 1){
                                        echo '<td><b>Yes</b></td>';
                                    } else {
        					            echo '<td><b>' . $value . '</b></td>';
        					        }
    					        echo '</tr>';
    					    }
    					}
					} else {
					    echo '<tr><td>No Settings Found!</td></tr>';
					}
					
				?>
			</tbody>
		</table>
		<hr>
		<div class="metabox-holder">
			<div class="postbox">
				<h3><span><?php _e( 'Export Settings' ); ?></span></h3>
				<div class="inside">
					<p><?php _e( 'Export the cloud search plugin settings for this site as a .json file. This allows you to easily import the configuration into another site.' ); ?></p>
					<form method="post">
						<p><input type="hidden" name="cldchimp_action" value="export_settings" /></p>
						<p>
							<?php wp_nonce_field( 'cldchimp_export_nonce', 'cldchimp_export_nonce' ); ?>
							<?php submit_button( __( 'Export' ), 'secondary', 'submit', false ); ?>
						</p>
					</form>
				</div><!-- .inside -->
			</div><!-- .postbox -->

			<div class="postbox">
				<h3><span><?php _e( 'Import Settings' ); ?></span></h3>
				<div class="inside">
					<p><?php _e( 'Import the cloud search plugin settings from a .json file. This file can be obtained by exporting the settings on another site using the form above.' ); ?></p>
					<form method="post" enctype="multipart/form-data">
						<p>
							<input type="file" name="import_file"/>
						</p>
						<p>
							<input type="hidden" name="cldchimp_action" value="import_settings" />
							<?php wp_nonce_field( 'cldchimp_import_nonce', 'cldchimp_import_nonce' ); ?>
							<?php submit_button( __( 'Import' ), 'secondary', 'submit', false ); ?>
						</p>
					</form>
				</div><!-- .inside -->
			</div><!-- .postbox -->
		</div><!-- .metabox-holder -->
	</div><!--end .wrap-->
	<?php
}

/**
 * Process a settings export that generates a .json file of the shop settings
 */
function cldchimp_process_settings_export() {

	if( empty( $_POST['cldchimp_action'] ) || 'export_settings' != $_POST['cldchimp_action'] )
		return;

	if( ! wp_verify_nonce( $_POST['cldchimp_export_nonce'], 'cldchimp_export_nonce' ) )
		return;

	if( ! current_user_can( 'manage_options' ) )
		return;

	$settings = ACS::get_instance()->get_settings();

	ignore_user_abort( true );

	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=cloud-search-settings-export-' . date( 'm-d-Y' ) . '.json' );
	header( "Expires: 0" );

	echo json_encode( $settings );
	exit;
}
add_action( 'admin_init', 'cldchimp_process_settings_export' );

/**
 * Process a settings import from a json file
 */
function cldchimp_process_settings_import() {

	if( empty( $_POST['cldchimp_action'] ) || 'import_settings' != $_POST['cldchimp_action'] )
		return;

	if( ! wp_verify_nonce( $_POST['cldchimp_import_nonce'], 'cldchimp_import_nonce' ) )
		return;

	if( ! current_user_can( 'manage_options' ) )
		return;

	$extension = end( explode( '.', $_FILES['import_file']['name'] ) );

	if( $extension != 'json' ) {
		wp_die( __( 'Please upload a valid .json file' ) );
	}

	$import_file = $_FILES['import_file']['tmp_name'];

	if( empty( $import_file ) ) {
		wp_die( __( 'Please upload a file to import' ) );
	}

	// Retrieve the settings from the file and convert the json object to an array.
	$newsettings = (array) json_decode( file_get_contents( $import_file ) );
	
	// Save settings action
	$settings = new stdClass();
	
	// Read post data
	$settings->acs_aws_access_key_id = ( !empty( $newsettings[ 'acs_aws_access_key_id' ] ) ) ? wp_kses_post( $newsettings[ 'acs_aws_access_key_id' ] ) : '';
	$settings->acs_aws_secret_access_key = ( !empty( $newsettings[ 'acs_aws_secret_access_key' ] ) ) ? wp_kses_post( $newsettings[ 'acs_aws_secret_access_key' ] ) : '';
	$settings->acs_aws_region = ( !empty( $newsettings[ 'acs_aws_region' ] ) ) ? wp_kses_post( $newsettings[ 'acs_aws_region' ] ) : '';
	$settings->acs_search_endpoint = ( !empty( $newsettings[ 'acs_search_endpoint' ] ) ) ? wp_kses_post( $newsettings[ 'acs_search_endpoint' ] ) : '';
	$settings->acs_search_domain_name = ( !empty( $newsettings[ 'acs_search_domain_name' ] ) ) ? wp_kses_post( $newsettings[ 'acs_search_domain_name' ] ) : '';
	$settings->acs_frontpage_content_box_type = ( !empty( $newsettings[ 'acs_frontpage_content_box_type' ] ) ) ? wp_kses_post( $newsettings[ 'acs_frontpage_content_box_type' ] ) : 'default';
	$settings->acs_frontpage_content_box_value = ( !empty( $newsettings[ 'acs_frontpage_content_box_value' ] ) ) ? wp_kses_post( $newsettings[ 'acs_frontpage_content_box_value' ] ) : '';
	$settings->acs_frontpage_use_plugin_search_page = ( !empty( $newsettings[ 'acs_frontpage_use_plugin_search_page' ] ) ) ? 1 : 0;
	$settings->acs_frontpage_use_jquery = ( !empty( $newsettings[ 'acs_frontpage_use_jquery' ] ) ) ? 1 : 0;
	$settings->acs_frontpage_show_filters = ( !empty( $newsettings[ 'acs_frontpage_show_filters' ] ) ) ? 1 : 0;
	$settings->acs_frontpage_custom_css = ( !empty( $newsettings[ 'acs_frontpage_custom_css' ] ) ) ? wp_kses_post( $newsettings[ 'acs_frontpage_custom_css' ] ) : '';
	$settings->acs_results_show_fields_sticky = ( !empty( $newsettings[ 'acs_results_show_fields_sticky' ] ) ) ? 1 : 0;
	$settings->acs_results_show_fields_formats = ( !empty( $newsettings[ 'acs_results_show_fields_formats' ] ) ) ? 1 : 0;
	$settings->acs_results_show_fields_categories = ( !empty( $newsettings[ 'acs_results_show_fields_categories' ] ) ) ? 1 : 0;
	$settings->acs_results_show_fields_tags = ( !empty( $newsettings[ 'acs_results_show_fields_tags' ] ) ) ? 1 : 0;
	$settings->acs_results_show_fields_comments = ( !empty( $newsettings[ 'acs_results_show_fields_comments' ] ) ) ? 1 : 0;
	$settings->acs_results_show_fields_content = ( !empty( $newsettings[ 'acs_results_show_fields_content' ] ) ) ? 1 : 0;
    $settings->acs_results_show_fields_excerpt = ( !empty( $newsettings[ 'acs_results_show_fields_excerpt' ] ) ) ? 1 : 0;
	$settings->acs_results_show_fields_custom = ( !empty( $newsettings[ 'acs_results_show_fields_custom' ] ) ) ? 1 : 0;
	$settings->acs_results_custom_field = ( !empty( $newsettings[ 'acs_results_custom_field' ] ) ) ? wp_kses_post( $newsettings[ 'acs_results_custom_field' ] ) : '';
	$settings->acs_results_show_fields_image = ( !empty( $newsettings[ 'acs_results_show_fields_image' ] ) ) ? 1 : 0;
	$settings->acs_results_format_image = ( !empty( $newsettings[ 'acs_results_format_image' ] ) ) ? wp_kses_post( $newsettings[ 'acs_results_format_image' ] ) : '';
	$settings->acs_results_show_fields_date = ( !empty( $newsettings[ 'acs_results_show_fields_date' ] ) ) ? 1 : 0;
	$settings->acs_results_format_date = ( !empty( $newsettings[ 'acs_results_format_date' ] ) ) ? wp_kses_post( $newsettings[ 'acs_results_format_date' ] ) : '';
	$settings->acs_results_show_fields_author = ( !empty( $newsettings[ 'acs_results_show_fields_author' ] ) ) ? 1 : 0;
	$settings->acs_results_format_author = ( !empty( $newsettings[ 'acs_results_format_author' ] ) ) ? wp_kses_post( $newsettings[ 'acs_results_format_author' ] ) : '';
	$settings->acs_results_no_results_msg = ( !empty( $newsettings[ 'acs_results_no_results_msg' ] ) ) ? stripslashes( wp_kses_post( $newsettings[ 'acs_results_no_results_msg' ] ) ) : __( 'No results', ACS::PREFIX );
	$settings->acs_results_no_results_box_value = ( !empty( $newsettings[ 'acs_results_no_results_box_value' ] ) ) ? wp_kses_post( $newsettings[ 'acs_results_no_results_box_value' ] ) : '';
	$settings->acs_results_load_more_msg = ( !empty( $newsettings[ 'acs_results_load_more_msg' ] ) ) ? stripslashes( wp_kses_post( $newsettings[ 'acs_results_load_more_msg' ] ) ) : __( 'Load more', ACS::PREFIX );
	$settings->acs_filter_sort_field = ( !empty( $newsettings[ 'acs_filter_sort_field' ] ) ) ? wp_kses_post( $newsettings[ 'acs_filter_sort_field' ] ) : ACS::SORT_FIELD_DEFAULT;
	$settings->acs_filter_sort_order = ( !empty( $newsettings[ 'acs_filter_sort_order' ] ) ) ? wp_kses_post( $newsettings[ 'acs_filter_sort_order' ] ) : ACS::SORT_ORDER_DEFAULT;
	$settings->acs_results_max_items = ( !empty( $newsettings[ 'acs_results_max_items' ] ) ) ? intval( wp_kses_post( $newsettings[ 'acs_results_max_items' ] ) ) : ACS::SEARCH_RETURN_FULL_ITEMS;
	$settings->acs_results_field_weights = ( !empty( $newsettings[ 'acs_results_field_weights' ] ) ) ? stripslashes( wp_kses_post( $newsettings[ 'acs_results_field_weights' ] ) ) : '';
    $settings->acs_filter_text_length = ( !empty( $newsettings[ 'acs_filter_text_length' ] ) ) ? intval( wp_kses_post( $newsettings[ 'acs_filter_text_length' ] ) ) : ACS::SEARCH_TEXT_LENGTH;
    $settings->acs_filter_text_length_type = ( !empty( $newsettings[ 'acs_filter_text_length_type' ] ) ) ? wp_kses_post( $newsettings[ 'acs_filter_text_length_type' ] ) : ACS::SEARCH_TEXT_LENGTH_TYPE;
    $settings->acs_schema_fields_prefix = ( !empty( $newsettings[ 'acs_schema_fields_prefix' ] ) ) ? wp_kses_post( $newsettings[ 'acs_schema_fields_prefix' ] ) : '';
	$settings->acs_schema_fields_separator = ( !empty( $newsettings[ 'acs_schema_fields_separator' ] ) ) ? wp_kses_post( $newsettings[ 'acs_schema_fields_separator' ] ) : ACS::FIELD_SEPARATOR_DEFAULT;
	$settings->acs_schema_fields_image_size = ( !empty( $newsettings[ 'acs_schema_fields_image_size' ] ) ) ? wp_kses_post( $newsettings[ 'acs_schema_fields_image_size' ] ) : '';
	$settings->acs_schema_fields_custom_image_id = ( !empty( $newsettings[ 'acs_schema_fields_custom_image_id' ] ) ) ? wp_kses_post( $newsettings[ 'acs_schema_fields_custom_image_id' ] ) : '';
    $settings->acs_schema_prevent_deletion =  ( !empty( $newsettings[ 'acs_schema_prevent_deletion' ] ) ) ? 1 : 0;
	$settings->acs_network_site_id = ( !empty( $newsettings[ 'acs_network_site_id' ] ) ) ? wp_kses_post( $newsettings[ 'acs_network_site_id' ] ) : '';
	$settings->acs_network_blog_id = ( !empty( $newsettings[ 'acs_network_blog_id' ] ) ) ? wp_kses_post( $newsettings[ 'acs_network_blog_id' ] ) : '';
	$settings->acs_highlight_type = ( !empty( $newsettings[ 'acs_highlight_type' ] ) ) ? wp_kses_post( $newsettings[ 'acs_highlight_type' ] ) : ACS::HIGHLIGHT_TYPE_DEFAULT;
    $settings->acs_highlight_titles = ( !empty( $newsettings[ 'acs_highlight_titles' ] ) ) ? 1 : 0;
    $settings->acs_highlight_color_text = ( !empty( $newsettings[ 'acs_highlight_color_text' ] ) ) ? wp_kses_post( $newsettings[ 'acs_highlight_color_text' ] ) : '';
    $settings->acs_highlight_color_background = ( !empty( $newsettings[ 'acs_highlight_color_background' ] ) ) ? wp_kses_post( $newsettings[ 'acs_highlight_color_background' ] ) : '';
    $settings->acs_highlight_style = ( !empty( $newsettings[ 'acs_highlight_style' ] ) ) ? stripslashes( wp_kses_post( $newsettings[ 'acs_highlight_style' ] ) ) : '';
    $settings->acs_highlight_class = ( !empty( $newsettings[ 'acs_highlight_class' ] ) ) ? stripslashes( wp_kses_post( $newsettings[ 'acs_highlight_class' ] ) ) : '';
    $settings->acs_suggest_active =  ( !empty( $newsettings[ 'acs_suggest_active' ] ) ) ? 1 : 0;
	$settings->acs_suggest_only_title =  ( !empty( $newsettings[ 'acs_suggest_only_title' ] ) ) ? 1 : 0;
    $settings->acs_suggest_selector = ( !empty( $newsettings[ 'acs_suggest_selector' ] ) ) ? stripslashes( wp_kses_post( $newsettings[ 'acs_suggest_selector' ] ) ) : ACS::SUGGEST_DEFAULT_SELECTOR;
    $settings->acs_suggest_trigger = ( !empty( $newsettings[ 'acs_suggest_trigger' ] ) ) ? intval( wp_kses_post( $newsettings[ 'acs_suggest_trigger' ] ) ) : ACS::SUGGEST_DEFAULT_TRIGGER;
    $settings->acs_suggest_results = ( !empty( $newsettings[ 'acs_suggest_results' ] ) ) ? intval( wp_kses_post( $newsettings[ 'acs_suggest_results' ] ) ) : ACS::SUGGEST_DEFAULT_RESULTS;
    $settings->acs_suggest_order = ( !empty( $newsettings[ 'acs_suggest_order' ] ) ) ? wp_kses_post( $newsettings[ 'acs_suggest_order' ] ) : ACS::SUGGEST_ORDER_TYPE_1;
    $settings->acs_suggest_click = ( !empty( $newsettings[ 'acs_suggest_click' ] ) ) ? wp_kses_post( $newsettings[ 'acs_suggest_click' ] ) : ACS::SUGGEST_CLICK_TYPE_1;
    $settings->acs_suggest_all_font_size = ( !empty( $newsettings[ 'acs_suggest_all_font_size' ] ) ) ? wp_kses_post( $newsettings[ 'acs_suggest_all_font_size' ] ) : ACS::SUGGEST_DEFAULT_ALL_FONT_SIZE;
    $settings->acs_suggest_all_color = ( !empty( $newsettings[ 'acs_suggest_all_color' ] ) ) ? wp_kses_post( $newsettings[ 'acs_suggest_all_color' ] ) : ACS::SUGGEST_DEFAULT_ALL_COLOR;
    $settings->acs_suggest_all_background = ( !empty( $newsettings[ 'acs_suggest_all_background' ] ) ) ? wp_kses_post( $newsettings[ 'acs_suggest_all_background' ] ) : ACS::SUGGEST_DEFAULT_ALL_BACKGROUND;
    $settings->acs_suggest_focused_font_size = ( !empty( $newsettings[ 'acs_suggest_focused_font_size' ] ) ) ? wp_kses_post( $newsettings[ 'acs_suggest_focused_font_size' ] ) : ACS::SUGGEST_DEFAULT_FOCUSED_FONT_SIZE;
    $settings->acs_suggest_focused_color = ( !empty( $newsettings[ 'acs_suggest_focused_color' ] ) ) ? wp_kses_post( $newsettings[ 'acs_suggest_focused_color' ] ) : ACS::SUGGEST_DEFAULT_FOCUSED_COLOR;
    $settings->acs_suggest_focused_background = ( !empty( $newsettings[ 'acs_suggest_focused_background' ] ) ) ? wp_kses_post( $newsettings[ 'acs_suggest_focused_background' ] ) : ACS::SUGGEST_DEFAULT_FOCUSED_BACKGROUND;
	$settings->acs_hide_section_help =  ( !empty( $newsettings[ 'acs_hide_section_help' ] ) ) ? 1 : 0;
	$settings->acs_hide_section_docs =  ( !empty( $newsettings[ 'acs_hide_section_docs' ] ) ) ? 1 : 0;

	// Remove '.php' occurrences from boxes value
	$settings->acs_frontpage_content_box_value = str_replace( '.php', '', $settings->acs_frontpage_content_box_value );
	$settings->acs_results_no_results_box_value = str_replace( '.php', '', $settings->acs_results_no_results_box_value );
	$settings->acs_frontpage_content_box_value = str_replace( '.PHP', '', $settings->acs_frontpage_content_box_value );
	$settings->acs_results_no_results_box_value = str_replace( '.PHP', '', $settings->acs_results_no_results_box_value );

	$settings->acs_schema_types =  ( !empty( $newsettings[ 'acs_schema_types' ] ) ) ? $newsettings[ 'acs_schema_types' ] : array();
	$settings->acs_schema_fields =  ( !empty( $newsettings[ 'acs_schema_fields' ] ) ) ? $newsettings[ 'acs_schema_fields' ] : array();
	$settings->acs_schema_taxonomies =  ( !empty( $newsettings[ 'acs_schema_taxonomies' ] ) ) ? $newsettings[ 'acs_schema_taxonomies' ] : array();

	// Save option on database
	update_option( ACS::OPTION_SETTINGS, $settings );

	// Reload settings option to refresh settings data after POST
	wp_safe_redirect( admin_url( 'options-general.php?page=cldchimp_settings' ) ); exit;

}
add_action( 'admin_init', 'cldchimp_process_settings_import' );
