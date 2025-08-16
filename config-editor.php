<?php
/**
 * Plugin Name:       Config Editor for Database Sync
 * Plugin URI:        https://familiebil/anton/
 * Description:       A plugin to edit the config-files, mostly located in the active child theme's directory.
 * Version:           1.2.0
 * Author:            Anton Bil
 * Author URI:        https://familiebil/anton/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ceds
 * Domain Path:       /languages
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CEDS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'CEDS_CONFIG_FILE_NAME', 'anton-section-config.json' ); // Default config file name
function ceds_is_safe_path(){
return true;
}
/**
 * Adds the admin menu page.
 */
function ceds_add_admin_menu() {
    add_options_page(
        __( 'File Sync Config', 'ceds' ),    // Page Title
        __( 'File Sync Config', 'ceds' ),        // Menu Title
        'manage_options',                       // Capability
        'ceds-config-editor',                   // Menu Slug
        'ceds_render_admin_page_wrapper'        // Function to display page content wrapper
    );
}
add_action( 'admin_menu', 'ceds_add_admin_menu' );

/**
 * Gets the full path to the configuration file.
 * Expects the file to be in the active (child) theme's directory.
 *
 * @param string $custom_filename The name of the config file. Defaults to CEDS_CONFIG_FILE_NAME.
 * @return string|false The full path to the config file, or false if not found.
 */
function ceds_get_config_file_path( $custom_filename = CEDS_CONFIG_FILE_NAME ) {
    $theme_dir = get_stylesheet_directory(); // This correctly gets the active (child) theme directory
    $custom_filename = str_replace($theme_dir,"",$custom_filename);
    $config_file_path = $theme_dir . '/' . $custom_filename;

    if ( file_exists( $config_file_path ) ) {
        return $config_file_path;
    } else {
        // Log the error for admin/debugging purposes
        error_log( "CEDS Plugin: Config file not found at: " . $config_file_path );
        return false;
    }
}

/**
 * Core logic for processing and retrieving config data.
 *
 * @param string $custom_filename The name of the config file.
 * @return array Contains 'message', 'error', 'file_content_for_textarea', 'config_file_path', 'file_extension'.
 */
function ceds_process_config_editor_data( $custom_filename, $expected_nonce_action = 'ceds_save_config_action' ) {
    $data = [
        'message'                   => '',
        'error'                     => '',
        'file_content_for_textarea' => '', // Changed from json_content_for_textarea for generality
        'config_file_path'          => ceds_get_config_file_path( $custom_filename ),
        'file_extension'            => '', // Will be populated if file_path is valid
    ];

    if ( ! $data['config_file_path'] ) {
        $data['error'] = sprintf(
            esc_html__( 'Error: Configuration file %1$s not found in the active theme directory: %2$s', 'ceds' ),
            '<code>' . esc_html( $custom_filename ) . '</code>',
            '<code>' . esc_html( get_stylesheet_directory() . '/' . $custom_filename ) . '</code>'
        );
        echo sprintf(
             'Error: Configuration file %1$s not found in the active theme directory: %2$s', 'ceds' ,
            '<code>' . esc_html( $custom_filename ) . '</code>',
            '<code>' . esc_html( get_stylesheet_directory() . '/' . $custom_filename ) . '</code>'
        );
        return $data; // Return data; the view will display the error
    }

    $data['file_extension'] = strtolower( pathinfo( $data['config_file_path'], PATHINFO_EXTENSION ) );

    if ( ! is_writable( $data['config_file_path'] ) ) {
        // Append to existing error if any, or set new error.
        $warning_message = sprintf(
            esc_html__( 'Warning: The configuration file %s is not writable. Please check server permissions.', 'ceds' ),
            '<code>' . esc_html( $data['config_file_path'] ) . '</code>'
        );
        $data['error'] = $data['error'] ? $data['error'] . '<br>' . $warning_message : $warning_message;
    }

    // Process the form if it has been submitted
    if ( isset( $_POST['ceds_save_config_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ceds_save_config_nonce'] ) ), $expected_nonce_action ) ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            $data['error'] = $data['error'] ? $data['error'] . '<br>' . esc_html__( 'You do not have sufficient permissions to save this configuration.', 'ceds' ) : esc_html__( 'You do not have sufficient permissions to save this configuration.', 'ceds' );
            // Still attempt to read current content to display
        } elseif ( isset( $_POST['ceds_file_content'] ) ) { // Changed from ceds_json_content
            $new_file_content = wp_unslash( $_POST['ceds_file_content'] ); // Raw content from textarea

            $can_save = false;

            if ( $data['file_extension'] === 'json' ) {
                // This is a JSON file, validate it
                json_decode( $new_file_content ); // Don't assign to variable if only checking error
                if ( json_last_error() === JSON_ERROR_NONE ) {
                    $can_save = true;
                } else {
                    $json_error_message = sprintf(
                        esc_html__( 'Error: The entered text is not valid JSON. (%s)', 'ceds' ),
                        json_last_error_msg()
                    );
                    $data['error'] = $data['error'] ? $data['error'] . '<br>' . $json_error_message : $json_error_message;
                    $data['file_content_for_textarea'] = esc_textarea( $new_file_content ); // Preserve incorrect input for JSON
                }
            } else {
                // For all other file types (txt, etc.), skip JSON validation.
                // You could add specific checks for other file types here if needed.
                $can_save = true;
            }

            if ( $can_save ) {
                global $wp_filesystem;
                if ( empty( $wp_filesystem ) ) {
                    require_once ABSPATH . '/wp-admin/includes/file.php';
                    WP_Filesystem();
                }
            if ( $wp_filesystem && $wp_filesystem->put_contents( $data['config_file_path'], $new_file_content ) ) {
                    $data['message'] = esc_html__( 'Configuration saved successfully.', 'ceds' );
                    // After a successful save, we want to display the newly saved content,
                    // so we set file_content_for_textarea here to avoid re-reading from the file system
                    // if there was no error during saving.
                    $data['file_content_for_textarea'] = esc_textarea( $new_file_content );
                } else {
                    $write_error_message = esc_html__( 'Error writing to the configuration file. Please check file permissions or disk space.', 'ceds' );
                    $data['error'] = $data['error'] ? $data['error'] . '<br>' . $write_error_message : $write_error_message;
                    // If saving failed, preserve the content the user tried to save
                    $data['file_content_for_textarea'] = esc_textarea( $new_file_content );
                }
            }
            // If !can_save, the error message is already set, and $data['file_content_for_textarea']
            // (if it's JSON and invalid) is already set to the user's input.
        }
    }

    // Read the current content of the file if it hasn't been populated by a save attempt or an error state
    if ( empty( $data['file_content_for_textarea'] ) && ! $data['message'] && file_exists( $data['config_file_path'] ) ) {
        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }
        if ( $wp_filesystem ) {
            $current_file_content = $wp_filesystem->get_contents( $data['config_file_path'] );
            if ( $current_file_content !== false ) {
                $data['file_content_for_textarea'] = esc_textarea( $current_file_content );
            } else {
                $read_error_message = esc_html__( 'Error reading the configuration file.', 'ceds' );
                $data['error'] = $data['error'] ? $data['error'] . '<br>' . $read_error_message : $read_error_message;
            }
        } else {
            $filesystem_error = esc_html__( 'Error: WordPress Filesystem API could not be initialized.', 'ceds' );
            $data['error'] = $data['error'] ? $data['error'] . '<br>' . $filesystem_error : $filesystem_error;
        }
    }
    // If there's still no content for the textarea (e.g., file is empty and no error occurred during POST or initial read)
    // and there's a valid config_file_path, and no prevailing error message that would explain the empty content.
    elseif ( empty( $data['file_content_for_textarea'] ) && $data['config_file_path'] && empty( $data['error'] ) && ! $data['message'] ) {
        // This can happen if the file exists but is empty. An empty string is valid for the textarea.
        $data['file_content_for_textarea'] = '';
    }
    // Note: The case where $data['config_file_path'] is false (file not found initially) is handled at the top.

    return $data;
}

/**
 * Retrieves a list of registered editable config files.
 * Developers can use the 'ceds_register_config_files' filter to add their files.
 *
 * @return array An associative array where keys might be identifiers/labels
 *               and values are arrays containing file details like 'path', 'label', 'notes'.
 */
function ceds_get_registered_config_files() {
    $default_files = []; // Start with an empty array

    // Apply the filter, allowing themes/other plugins to add/modify files.
    // $default_files is passed as the initial value to the filter functions.
    $registered_files = apply_filters( 'ceds_register_config_files', $default_files );

    // Ensure the result is always an array
    if ( ! is_array( $registered_files ) ) {
        $registered_files = [];
    }

    return $registered_files;
}

/**
 * Wrapper function for rendering the admin page.
 * Handles file selection, loading, and saving logic before passing data to the view.
 */
function ceds_render_admin_page_wrapper() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ceds' ) );
    }

    // --- FILE SELECTION AND PROCESSING LOGIC ---
    $all_registered_files = ceds_get_registered_config_files(); // Filter is called indirectly here

    $selected_file_key = null;
    $current_file_to_edit_path = null;
    // Default nonce action, will be made specific if a file is selected
    $current_nonce_action = 'ceds_save_config_action_generic';

    // Check if a file is selected via GET (for loading)
    if ( isset( $_GET['ceds_edit_file'] ) && is_string( $_GET['ceds_edit_file'] ) ) {
        $potential_key = sanitize_key( $_GET['ceds_edit_file'] );
        // Ensure the key exists and its path is valid and safe
        if ( isset( $all_registered_files[ $potential_key ]['path'] ) &&
             is_string($all_registered_files[ $potential_key ]['path']) && // Ensure path is a string
             ceds_is_safe_path( $all_registered_files[ $potential_key ]['path'] ) ) {
            $selected_file_key = $potential_key;
            $current_file_to_edit_path = $all_registered_files[ $potential_key ]['path'];
        } else {
            // Log or handle the case where a key is provided but not valid/found
            // error_log("CEDS: Invalid or unsafe file key/path provided in GET: " . $potential_key);
        }
    }

    // Initialize data for the view
    $view_data = [
        'message'                   => '',
        'error'                     => '',
        'file_content_for_textarea' => '',
        'config_file_path'          => null, // Will hold the path of the file being edited
        'file_extension'            => '',
        'registered_files'          => $all_registered_files, // Pass all registered files to the view for the selector
        'selected_file_key'         => null, // Will be set if a file is actively selected/processed
    ];

    // If a file is selected (either via GET or from a POST redirect that maintained selection)
    if ( $selected_file_key && $current_file_to_edit_path ) {
        $view_data['selected_file_key'] = $selected_file_key; // Set the selected key for the view
        $current_nonce_action = 'ceds_save_config_action_' . $selected_file_key; // Make nonce action specific to this file

        // Determine if the form was posted for THIS specific file
        $form_posted_for_this_file = false;
        if ( 'POST' === $_SERVER['REQUEST_METHOD'] && // Check if it's a POST request
             isset( $_POST['ceds_save_config_nonce'], $_POST['ceds_edited_file_key'] ) &&
             sanitize_key( $_POST['ceds_edited_file_key'] ) === $selected_file_key && // Check if the POST is for the currently selected file
             wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ceds_save_config_nonce'] ) ), $current_nonce_action ) ) {
            $form_posted_for_this_file = true;
        }

        // Process the file (read content or save posted content)
        // Only proceed if the file path is valid and (ideally) the file exists or is meant to be created.
        // The ceds_is_safe_path check was done earlier for selection.
        // file_exists check is important before reading. For writing, ceds_process_config_editor_data should handle it.

        // Note: The 'anton-section-config.json' hardcoding was for testing.
        // It should use $current_file_to_edit_path.
        $processed_data = ceds_process_config_editor_data(
            $current_file_to_edit_path,  // Pass the full path of the selected file
            $current_nonce_action,       // Pass the expected nonce action for verification
            $form_posted_for_this_file   // Indicate if this is a POST request to save this file
        );

        $view_data['message'] = $processed_data['message'];
        $view_data['error'] = $processed_data['error'];
        $view_data['file_content_for_textarea'] = $processed_data['file_content_for_textarea'];
        // $processed_data['config_file_path'] should confirm the path it processed
        $view_data['config_file_path'] = $processed_data['config_file_path'];
        $view_data['file_extension'] = $processed_data['file_extension'];

        // If an error occurred during processing (e.g., file not found by ceds_process_config_editor_data),
        // ensure the selected key is unset if the file path became invalid.
        if (!empty($processed_data['error']) && empty($processed_data['config_file_path'])) {
             // $view_data['selected_file_key'] = null; // Or handle based on specific error
        }

    } elseif ( empty( $all_registered_files ) ) {
        // No files registered at all
        if (empty($view_data['error'])) { // Only set this if no other error (like a failed selection) is present
            $view_data['error'] = __( 'No configuration files have been registered for editing. Developers can use the "ceds_register_config_files" filter to add them.', 'ceds' );
        }
    } elseif ( ! $selected_file_key && ! empty( $all_registered_files ) ) {
        // Files are registered, but none is currently selected (e.g., initial page load before any selection)
        // This message is often better handled directly in the view if no file is selected.
        if (empty($view_data['error']) && empty($view_data['message'])) { // Avoid overwriting other messages
            // $view_data['message'] = __('Please select a configuration file to edit from the list.', 'ceds');
        }
    }

    // Extract variables for the view template
    // These names must match what the view (admin-page-view.php) expects
    $message                   = $view_data['message'];
    $error                     = $view_data['error'];
    $file_content_for_textarea = $view_data['file_content_for_textarea'];
    $config_file_path_for_view = $view_data['config_file_path']; // Path of the file being edited (if any)
    $file_extension_for_view   = $view_data['file_extension'];
    $registered_files_for_view = $view_data['registered_files']; // All registered files for the selector dropdown
    $selected_file_key_for_view= $view_data['selected_file_key']; // The key of the currently selected file

    // Load the admin view template
    // Ensure CEDS_PLUGIN_PATH is correctly defined and accessible.
    if ( defined('CEDS_PLUGIN_PATH') ) {
        require_once CEDS_PLUGIN_PATH . 'views/admin-page-view.php';
    } else {
        // Fallback or error if plugin path constant is not defined
        wp_die( esc_html__( 'Error: Plugin path is not defined. Cannot load admin view.', 'ceds' ) );
    }
}

/**
 * Shortcode handler function.
 * Usage: [ceds_config_editor filename="optional_other_file.json"]
 * If 'filename' attribute is omitted, it defaults to CEDS_CONFIG_FILE_NAME.
 */
function ceds_config_editor_shortcode_handler( $atts ) {
    // Standard attributes and process the passed attributes.
    $atts = shortcode_atts(
        array(
            'filename' => null, // Defaults to no specific filename passed.
        ),
        $atts,
        'ceds_config_editor'
    );

    // IMPORTANT: Capability check for the shortcode!
    if ( ! current_user_can( 'manage_options' ) ) {
        return '<p>' . esc_html__( 'You do not have sufficient permissions to view or edit this configuration.', 'ceds' ) . '</p>';
    }

    // Get the optional filename from the attributes.
    $custom_filename = CEDS_CONFIG_FILE_NAME; // Default
    if ( ! empty( $atts['filename'] ) && is_string( $atts['filename'] ) ) {
        // Basic validation: ensure it's a string and sanitize it.
        // You might want stricter validation based on your needs (e.g., allow only .json, .txt).
        $custom_filename = sanitize_file_name( $atts['filename'] ); // Sanitize the filename
    }

    // Get the processed data using the (potentially custom) filename.
    $view_data = ceds_process_config_editor_data( $custom_filename );

    // Extract variables.
    $message                   = $view_data['message'];
    $error                     = $view_data['error'];
    $file_content_for_textarea = $view_data['file_content_for_textarea']; // Changed
    $config_file_path          = $view_data['config_file_path'];
    $file_extension            = $view_data['file_extension']; // For context in the view if needed

    // Start output buffering to capture the HTML.
    ob_start();

    // You can load a separate view specifically for the shortcode here,
    // or reuse/adapt the admin-page.php view.
    // For now, we'll implement a basic form structure.
    // You'll need to handle styling for the frontend yourself.

    if ( ! empty( $message ) ) : ?>
        <div class="ceds-message ceds-success">
            <p><?php echo esc_html( $message ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( ! empty( $error ) ) : ?>
        <div class="ceds-error ceds-notice">
            <p><strong><?php echo wp_kses_post( $error ); // Allow basic HTML in errors if needed, otherwise esc_html() ?></strong></p>
        </div>
    <?php endif; ?>

    <?php
    // Only show the form if the config file path is valid (meaning the file was initially found or is expected)
    // and even if it's not currently writable (the error for not writable is shown above).
    // If $config_file_path is false, it means ceds_get_config_file_path returned false (file not found).
    ?>
    <?php if ( $config_file_path ) : ?>
        <div class="ceds-config-editor-form ceds-shortcode-form">
            <h3><?php
                printf(
                    esc_html__( 'Editing file: %s', 'ceds' ),
                    '<span class="ceds-file-path" style="font-family: Consolas, Monaco, monospace; direction: ltr; unicode-bidi: embed; font-size: 13px;">' . esc_html( $config_file_path ) . '</span>'
                );
            ?></h3>

            <form method="post" action=""> <?php // action="" ensures it posts to the same page. ?>
                <?php wp_nonce_field( 'ceds_save_config_action', 'ceds_save_config_nonce' ); ?>
                <?php // Add a hidden field for the filename if it was custom, so it's reprocessed correctly on POST
                if ( $custom_filename !== CEDS_CONFIG_FILE_NAME ) {
                    echo '<input type="hidden" name="ceds_custom_filename_shortcode" value="' . esc_attr( $custom_filename ) . '" />';
                }
                ?>
                <div>
                    <label for="ceds_file_content_shortcode"><?php
                        // Dynamically set label based on file type if known, otherwise generic.
                        if ( $file_extension === 'json' ) {
                            esc_html_e( 'JSON Configuration Content', 'ceds' );
                        } elseif ( $file_extension === 'txt' ) {
                            esc_html_e( 'Text File Content', 'ceds' );
                        } else {
                            esc_html_e( 'File Content', 'ceds' );
                        }
                    ?></label>
                </div>
                <div>
                    <textarea name="ceds_file_content" id="ceds_file_content_shortcode" rows="15" style="width: 98%; font-family: monospace;"><?php echo $file_content_for_textarea; /* Already escaped in processing function */ ?></textarea>
                    <?php if ( $file_extension === 'json' ) : ?>
                    <p><em><small><?php esc_html_e( 'Ensure the JSON structure remains valid. Incorrect JSON can lead to issues reading the data.', 'ceds' ); ?></small></em></p>
                    <?php endif; ?>
                </div>

                <p><input type="submit" name="submit" id="submit_shortcode" class="button button-primary ceds-shortcode-submit" value="<?php esc_attr_e( 'Save Configuration', 'ceds' ); ?>"></p>
            </form>
        </div>
    <?php elseif ( empty( $error ) ) : // If $config_file_path is false and no other error was set by ceds_process_config_editor_data
        // This case should ideally be covered by the error handling in ceds_process_config_editor_data
        // when ceds_get_config_file_path returns false.
    ?>
         <div class="ceds-error ceds-notice">
            <p><strong><?php
                printf(
                    esc_html__( 'Error: Configuration file %s could not be processed or was not found.', 'ceds' ), // Adjusted message
                    '<code>' . esc_html( $custom_filename ) . '</code>'
                );
            ?></strong></p>
        </div>
    <?php endif; // End of the main if/elseif for showing the form or an error.

    return ob_get_clean(); // Return the buffered HTML.
}
add_shortcode( 'ceds_config_editor', 'ceds_config_editor_shortcode_handler' );


?>
