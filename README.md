# Config File Editor for WordPress

**Contributors:** Anton Bil
**Tags:** config, editor, child theme, json, ini, settings, development
**Requires at least:** 5.0
**Tested up to:** 6.8
**Stable tag:** 1.0.0
**License:** GPLv2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

A WordPress plugin to easily edit registered configuration files, typically located within the active child theme's directory or other plugin-defined locations. It provides a simple interface in the WordPress admin area to modify these files directly.

## Description

The Config File Editor plugin allows developers and site administrators to register specific configuration files (e.g., `.json`, `.ini`, `.php` arrays) that can then be edited through a dedicated settings page in the WordPress dashboard.

This is particularly useful for:

*   Managing theme-specific settings stored in JSON or INI files within a child theme.
*   Allowing quick modifications to configuration arrays defined by themes or plugins.
*   Streamlining the process of updating settings without needing FTP/SSH access for simple changes.

Developers can use a WordPress filter (`ceds_register_config_files`) to make their own configuration files available for editing through this plugin's interface.

## Features

*   **Admin Interface:** A dedicated page under "Settings" to select and edit registered config files.
*   **File Registration:** Uses a WordPress filter `ceds_register_config_files` for developers to easily add their own files to the editor.
*   **Basic Editor:** Provides a textarea for editing file content.
*   **File Validation (Basic):** Includes checks for safe paths.
*   **Nonce Security:** Uses WordPress nonces for secure form submissions.

## Installation

1.  **Download:**
    *   Download the `config-editor-plugin.zip` file from the [GitHub repository Releases page](https://github.com/antonbil/config-editor/releases).
    *   OR: Clone the repository: `git clone https://github.com/antonbil/config-editor.git`
2.  **Upload to WordPress:**
    *   Log in to your WordPress admin panel.
    *   Navigate to `Plugins` > `Add New`.
    *   Click `Upload Plugin` and choose the downloaded `config-editor-plugin.zip` file.
    *   Click `Install Now`.
    *   OR: If you cloned, zip the plugin directory contents or upload the directory via FTP to `wp-content/plugins/`.
3.  **Activate:**
    *   Activate the plugin through the 'Plugins' menu in WordPress.
4.  **Access:**
    *   Once activated, you can find the editor page under `Settings > Config Editor` (of waar je het menu-item ook hebt geplaatst).

## Usage

### For Site Administrators / Users

1.  Navigate to `Settings > Config Editor` in your WordPress admin area.
2.  If configuration files have been registered (e.g., by your theme or another plugin), you will see a dropdown menu to select a file.
3.  Choose a file from the list. Its content will be loaded into the editor.
4.  Make your changes in the textarea.
5.  Click "Save Changes" to save the modifications to the file.

### For Developers: Registering Config Files

To make a configuration file editable via this plugin, you need to use the `ceds_register_config_files` filter. Add code similar to the following in your theme's `functions.php` or in another plugin:
```
php add_filter( 'ceds_register_config_ files' ,  'my_theme_register_config_ files_ for_ editor'  );
function my_theme_register_config_ files_ for_ editor(  $files ) { // Example 1: JSON file in the child theme directory $child_theme_path = get_stylesheet_directory( ) ;  $my_settings_file_path = $child_theme_path . '/config/my-settings. json' ;
if ( file_exists( $my_settings_file_path ) ) {
    $files['my_theme_settings'] = array(
        'path'  => $my_settings_file_path,
        'label' => __('My Theme Specific Settings (JSON)', 'my-theme-textdomain'),
        'notes' => __('Edit carefully. This file controls various theme aspects.', 'my-theme-textdomain'),
    );
}
// Example 2: An INI file
// $my_ini_file_path = $child_theme_path . '/config/options.ini';
// if ( file_exists( $my_ini_file_path ) ) {
//     $files['my_theme_ini_options'] = array(
//         'path'  => $my_ini_file_path,
//         'label' => __('Theme INI Options', 'my-theme-textdomain'),
//     );
// }
// Ensure you return the $files array
return $files;
}
```

**Important considerations for developers:**

*   **File Paths:** Ensure the paths you provide are absolute server paths.
*   **Security:** Only register files that are intended to be user-editable. Be mindful of the security implications. This plugin implements basic path safety checks, but the responsibility for what is registered lies with the developer hooking into the filter.
*   **File Types:** While the editor is a simple textarea, it's best suited for text-based configuration formats like JSON, INI, or simple PHP arrays.


## Changelog

### 1.0.0 - 2025-08-15
*   Initial release.
*   Feature: Edit registered configuration files.
*   Feature: Filter `ceds_register_config_files` for developers.

## Frequently Asked Questions (FAQ)

*   **Q: What types of files can I edit?**
    A: The plugin provides a plain text editor, so it's best for text-based configuration files like JSON, INI, or simple PHP files (e.g., returning an array). It does not have specialized editors for complex file types.

*   **Q: Where are the files saved?**
    A: Files are saved back to their original location on the server, as specified when they were registered.

*   **Q: Is it secure?**
    A: The plugin uses WordPress nonces for form submissions and includes basic path safety checks. However, the overall security also depends on server permissions and which files developers choose to register. Only register files that are safe to be edited from the WordPress admin.

## Contributing

Contributions are welcome! Please feel free to fork the repository, make changes, and submit a pull request.

1.  Fork the Project
2.  Create your Feature Branch (`git checkout -b feature/AmazingFeature`)
3.  Commit your Changes (`git commit -m 'Add some AmazingFeature'`)
4.  Push to the Branch (`git push origin feature/AmazingFeature`)
5.  Open a Pull Request

## Support

If you encounter any issues or have a feature request, please open an issue on the [GitHub repository issue tracker](https://github.com/antonbil/config-editor/issues).
