<?php
/**
 * Admin page view for the Config Editor.
 *
 * Available variables from ceds_render_admin_page_wrapper():
 * @var string $message                   Success or info message.
 * @var string $error                     Error message.
 * @var string $file_content_for_textarea The file content to display in the textarea.
 * @var string|null $config_file_path_for_view The full path to the configuration file currently being edited (or null).
 * @var string $file_extension_for_view   The extension of the file being edited.
 * @var array  $registered_files_for_view An array of registered config files.
 * @var string|null $selected_file_key_for_view The key of the currently selected file from $registered_files_for_view.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap ceds-admin-wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <?php // Display messages and errors first
    if ( ! empty( $message ) ) : ?>
        <div id="message" class="notice notice-success is-dismissible ceds-message">
            <p><?php echo esc_html( $message ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( ! empty( $error ) ) : ?>
        <div id="setting-error-ceds_error" class="notice notice-error is-dismissible ceds-error">
            <p><strong><?php echo wp_kses_post( $error ); // wp_kses_post because $error might contain <code> tags ?></strong></p>
        </div>
    <?php endif; ?>

    <div class="ceds-file-selector-section">
        <h2><?php esc_html_e( 'Select Configuration File to Edit', 'ceds' ); ?></h2>

        <?php if ( ! empty( $registered_files_for_view ) ) : ?>
            <form method="get" action="">
                <?php // WordPress admin pages usually submit to options.php or admin-post.php, but for a simple GET to reload the page with a new file: ?>
                <input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); // Keep current admin page ?>">

                <label for="ceds_edit_file_dropdown" class="screen-reader-text"><?php esc_html_e( 'Select a file:', 'ceds' ); ?></label>
                <select name="ceds_edit_file" id="ceds_edit_file_dropdown">
                    <option value=""><?php esc_html_e( '-- Select a file --', 'ceds' ); ?></option>
                    <?php foreach ( $registered_files_for_view as $file_key => $file_details ) : ?>
                        <?php
                        $label = isset( $file_details['label'] ) ? $file_details['label'] : $file_key;
                        $path_display = isset( $file_details['path'] ) ? ' (' . esc_html( wp_basename( $file_details['path'] ) ) . ')' : '';
                        ?>
                        <option value="<?php echo esc_attr( $file_key ); ?>" <?php selected( $selected_file_key_for_view, $file_key ); ?>>
                            <?php echo esc_html( $label . $path_display ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button( __( 'Load File', 'ceds' ), 'secondary', 'ceds_load_file_submit', false ); // 'secondary' class, no name attribute to prevent POST confusion ?>
            </form>
            <?php if ( $selected_file_key_for_view && isset( $registered_files_for_view[$selected_file_key_for_view]['notes'] ) && !empty($registered_files_for_view[$selected_file_key_for_view]['notes']) ) : ?>
                <p class="description ceds-file-notes">
                    <strong><?php esc_html_e( 'Notes for this file:', 'ceds'); ?></strong>
                    <?php echo esc_html( $registered_files_for_view[$selected_file_key_for_view]['notes'] ); ?>
                </p>
            <?php endif; ?>
        <?php else : ?>
            <p><?php esc_html_e( 'No configuration files have been registered for editing. Developers can use the "ceds_register_config_files" filter to add them.', 'ceds' ); ?></p>
        <?php endif; ?>
    </div>

    <hr class="ceds-separator">

    <?php // Editor section - only show if a valid file is selected and its path is known
    if ( $config_file_path_for_view && $selected_file_key_for_view ) : ?>
        <div class="ceds-editor-section">
            <h3>
                <?php
                printf(
                    // translators: %s is the path to the config file.
                    esc_html__( 'Editing: %s', 'ceds' ),
                    '<code>' . esc_html( $config_file_path_for_view ) . '</code>'
                );
                ?>
            </h3>

            <form method="post" action="">
                <?php // Nonce should be specific to the file being saved to prevent replay attacks on other files if logic changes
                wp_nonce_field( 'ceds_save_config_action_' . $selected_file_key_for_view, 'ceds_save_config_nonce' ); ?>
                <input type="hidden" name="ceds_edited_file_key" value="<?php echo esc_attr( $selected_file_key_for_view ); // Send back which file was edited ?>">
                <input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); // Keep current admin page for POST redirect ?>">
                <input type="hidden" name="ceds_edit_file" value="<?php echo esc_attr( $selected_file_key_for_view ); // Keep the file selected after POST ?>">


                <table class="form-table ceds-editor-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <?php
                                $label_text = __( 'File Content', 'ceds' ); // Default label
                                if ( $file_extension_for_view === 'json') {
                                    $label_text = __( 'JSON Configuration', 'ceds' );
                                } elseif ( $file_extension_for_view === 'txt') {
                                    $label_text = __( 'Text Content', 'ceds' );
                                }
                                // Add more specific labels if needed
                                ?>
                                <label for="ceds_file_content"><?php echo esc_html( $label_text ); ?></label>
                            </th>
                            <td>
                                <textarea name="ceds_file_content" id="ceds_file_content" rows="20" cols="80" class="large-text code"><?php echo $file_content_for_textarea; // Already escaped for textarea in the controller/processing function ?></textarea>
                                <p class="description">
                                    <?php
                                    if ( $file_extension_for_view === 'json') {
                                        esc_html_e( 'Enter the full JSON configuration here. Be careful, invalid JSON can cause issues.', 'ceds' );
                                    } else {
                                        esc_html_e( 'Edit the content of the file. Ensure the format is correct for its intended use.', 'ceds' );
                                    }
                                    ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button( __( 'Save Changes', 'ceds' ) ); // Default text is 'Save Changes' ?>
            </form>
        </div>
    <?php elseif ( ! empty( $registered_files_for_view ) && ! $selected_file_key_for_view && empty($error) && empty($message) ) : ?>
        <p class="ceds-no-file-selected-message"><?php esc_html_e( 'Please select a file from the dropdown above to start editing.', 'ceds' ); ?></p>
    <?php endif; // End of editor section or "please select file" message ?>

</div> <?php // .wrap ?>
