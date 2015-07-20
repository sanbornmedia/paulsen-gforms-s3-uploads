<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * Dashboard. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              http://example.com
 * @since             1.0.0
 * @package           Paulsen_Gforms_S3_Uploads
 *
 * @wordpress-plugin
 * Plugin Name:       Paulsen Gravity Forms S3 Uplods
 * Description:       Move files uploaded through Gravity Forms to S3
 * Version:           1.1.0
 * Author:            Paulsen
 * Author URI:        http://www.paulsen.ag/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       paulsen-gforms-s3-uploads
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The code that runs during plugin activation.
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-paulsen-gforms-s3-uploads-activator.php';

/*
define( 'GFORM_S3_FORM_ID', 3 );

define( 'GFORM_S3_FIELD_ID', 2342342 );
*/

define( 'GFORM_S3_BUCKET', 'BUCKET-NAME-HERE' );

define( 'GFORM_S3_PATH_PREFIX', 'FOLDER-NAME-OR-EMPTY');

define( 'AWS_ACCESS_KEY_ID', 'AWS-ACCESS-KEY-HERE' );

define( 'AWS_SECRET_ACCESS_KEY', 'AWS-SECRET-HERE' );

/**
 * The code that runs during plugin deactivation.
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-paulsen-gforms-s3-uploads-deactivator.php';

/** This action is documented in includes/class-paulsen-gforms-s3-uploads-activator.php */
register_activation_hook( __FILE__, array( 'Paulsen_Gforms_S3_Uploads_Activator', 'activate' ) );

/** This action is documented in includes/class-paulsen-gforms-s3-uploads-deactivator.php */
register_deactivation_hook( __FILE__, array( 'Paulsen_Gforms_S3_Uploads_Deactivator', 'deactivate' ) );

/**
 * The core plugin class that is used to define internationalization,
 * dashboard-specific hooks, and public-facing site hooks.
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-paulsen-gforms-s3-uploads.php';

	// Include the SDK using the Composer autoloader
	require 'vendor/autoload.php';

	use Aws\S3\S3Client;

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_plugin_name() {

	$plugin = new Paulsen_Gforms_S3_Uploads();
	$plugin->run();	

	// Upload to S3 after submission
	add_action('gform_after_submission', 'process_attachments', 10, 2);
	function process_attachments($entry, $form) {
    	
    	
    	$upload_field_ids = array();
    	foreach ($form['fields'] as $form_field){
        	if($form_field->type == 'fileupload'){
            	array_push($upload_field_ids, $form_field->id);
        	}
    	}
    		
		//loop through upload field IDS and push contents to S3
        foreach ($upload_field_ids as $upload_field_id){
            
			
			if(!empty($entry[$upload_field_id])){
    			// Grab the field value
    			$field_value = $entry[$upload_field_id];
    
    			// If multi-uplaod is enabled for the field, the value will be a JSON string.
    			// Decode it so we can test if it's an array
    			$field_value_decoded = json_decode($field_value);
    			
    			// If we have an aray, loop through it and upload each file
    			// Else, just uplaod the one file.
    			if (is_array($field_value_decoded)) {
        			// empty array to store new urls
        			$new_file_url = array();
    				foreach ($field_value_decoded as $attachment_url) {
        				//push file to S3 and return new url
    					$multi_file_item_url = upload_to_s3($attachment_url, $form['id']);
    					
    					//push new url in the new url array
    					array_push($new_file_url, $multi_file_item_url);
    				}
    				//json encode the new url before updating DB
    				$new_file_url = json_encode($new_file_url);
    			} else {
    				$new_file_url = upload_to_s3($field_value, $form['id']);
    			}
    			
    			//gform_update_meta( $entry['id'], 'api_response', $new_file_url );
    			
    			global $wpdb;
    			
    			$lead_detail_table = RGFormsModel::get_lead_details_table_name();
    			
    			
    			
    			if (is_array($field_value_decoded)) {
        			$entry_detail_row = $wpdb->get_row("SELECT * FROM {$lead_detail_table} WHERE field_number = {$upload_field_id} AND lead_id = {$entry['id']} AND form_id = {$entry['form_id']}", ARRAY_A);
        			
        			$entry_detail_id = $entry_detail_row["id"];
        			
        			$lead_detail_long_table = RGFormsModel::get_lead_details_long_table_name();
        			
        			$wpdb->update(
        			    $lead_detail_long_table, 
                        array(
                            'value'         => $new_file_url
                        ),
                        array(
                            'lead_detail_id'  => $entry_detail_id
                        )
                    );
    			}
    			else {
        			
        			$wpdb->update(
        			    $lead_detail_table, 
                        array(
                            'value'         => $new_file_url
                        ),
                        array(
                            'field_number'  => $upload_field_id,
                            'lead_id'       => $entry['id'],
                            'form_id'       => $entry['form_id']
                        )
                    );
                }
			}
		}
	}

	function upload_to_s3($attachment_url, $form_id) {
		// Instantiate the S3 client with your AWS credentials
		$client = S3Client::factory(array(
		    'key'    => AWS_ACCESS_KEY_ID,
		    'secret' => AWS_SECRET_ACCESS_KEY,
		));
        
        $org_attachment_url = $attachment_url;
        
		$wp_upload_dir = wp_upload_dir();

		$upload_path = str_replace(home_url(), '', $wp_upload_dir['baseurl']);

		$upload_filename = str_replace($wp_upload_dir['baseurl'], '', $attachment_url);

		$attachment_url = $wp_upload_dir['basedir'] . $upload_filename;
		
		$file_extension = pathinfo($upload_filename, PATHINFO_EXTENSION);
		
		$newFileName = GFORM_S3_PATH_PREFIX . "gravity-form-id-" . $form_id . "/" . sha1($attachment_url) . "-" . microtime(true) . "." . $file_extension;

		// Upload an object by streaming the contents of a file
		// $pathToFile should be absolute path to a file on disk
		$result = $client->putObject(array(
		    'Bucket'     => GFORM_S3_BUCKET,
		    'Key'        => $newFileName,
		    'SourceFile' => $attachment_url,
		    'ACL'        => 'public-read'
		));
        
        if($result){
            if(file_exists($attachment_url)){
                //unlink($attachment_url);
                if (!unlink($attachment_url)){
                    error_log("Error deleting $attachment_url");
                }
                else {
                    error_log("Deleted $attachment_url");
                }
            }
            else {
                error_log("File $attachment_url does not exist.");
            }
            
            return "https://s3.amazonaws.com/". GFORM_S3_BUCKET . "/" . $newFileName;
        } 
        else {
            error_log("There was an error uploading the file to s3. Local WP file will be used.");
            return $org_attachment_url;
        }
	}

}
run_plugin_name();
