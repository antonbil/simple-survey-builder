<?php
/**
 * Plugin Name:       Simple Survey Builder
 * Plugin URI:        https://example.com/plugins/simple-survey-builder/ (Jouw website of de plek waar men info kan vinden)
 * Description:       A simple plugin to create and manage surveys on your WordPress site.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Anton Bil
 * Author URI:        https://jouwwebsite.com (Jouw website)
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       simple-survey-builder
 * Domain Path:       /languages
 */

// Voorkom directe toegang tot het bestand
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// global kss_registered_cpt_slugs
global $kss_registered_cpt_slugs;
$kss_registered_cpt_slugs = array();
function ssb_load_textdomain() {
        load_plugin_textdomain( 'simple-survey-builder', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }
    add_action( 'plugins_loaded', 'ssb_load_textdomain' );
/**
 * Loads survey configurations from a JSON file and sets the global
 * variables $kss_post_type_definition and $kss_survey_questions_config.
 *
 * @param string $slug_to_load The identifier of the survey configuration to load.
 * @return bool True if loading was successful, False otherwise.
 */
function define_survey_variables( $slug_to_load = 'survey_entry' ) { // $slug_to_load for future flexibility
    global $kss_post_type_definition, $kss_survey_questions_config;
    $text_domain = 'simple-survey-builder'; // Your plugin's text domain

    $json_file_path = plugin_dir_path( __FILE__ ) . 'config/survey_configurations.json';

    if ( ! file_exists( $json_file_path ) ) {
        // translators: %s is the file path.
        error_log( sprintf( esc_html__( 'KSS Survey Error: JSON configuration file not found at %s', $text_domain ), $json_file_path ) );
        // Optional: set defaults or return an error status
        $kss_post_type_definition = null;
        $kss_survey_questions_config = null;
        return false;
    }

    $json_content = file_get_contents( $json_file_path );
    if ( $json_content === false ) {
        error_log( esc_html__( 'KSS Survey Error: Could not read JSON configuration file.', $text_domain ) );
        $kss_post_type_definition = null;
        $kss_survey_questions_config = null;
        return false;
    }

    // Decode JSON to a PHP array. The 'true' parameter ensures associative arrays.
    $all_configurations = json_decode( $json_content, true );

    if ( json_last_error() !== JSON_ERROR_NONE ) {
        // translators: %s is the JSON error message.
        error_log( sprintf( esc_html__( 'KSS Survey Error: Error decoding JSON: %s', $text_domain ), json_last_error_msg() ) );
        $kss_post_type_definition = null;
        $kss_survey_questions_config = null;
        return false;
    }

    if ( empty( $all_configurations ) || ! is_array( $all_configurations ) ) {
        error_log( esc_html__( 'KSS Survey Error: JSON configuration is empty or not a valid array after decoding.', $text_domain ) );
        $kss_post_type_definition = null;
        $kss_survey_questions_config = null;
        return false;
    }

    // --- For now: Grab the configuration that matches $slug_to_load ---
    $target_configuration = null;
    foreach ($all_configurations as $config_item) {
        if (isset($config_item['slug_identifier']) && $config_item['slug_identifier'] === $slug_to_load) {
            $target_configuration = $config_item;
            break;
        }
    }

    // If, after searching, we still don't have a target configuration:
    if ( $target_configuration === null ) {
        if (!empty($all_configurations[0])) { // Fallback: try the very first one if the specific one wasn't found
            $target_configuration = $all_configurations[0];
            // translators: %s is the slug that was not found.
            error_log( sprintf( esc_html__( 'KSS Survey Warning: Specific slug "%s" not found in JSON, falling back to the first configuration.', $text_domain ), $slug_to_load ) );
        } else {
            error_log( esc_html__( 'KSS Survey Error: No configurations found in JSON or the first element is empty.', $text_domain ) );
            $kss_post_type_definition = null;
            $kss_survey_questions_config = null;
            return false;
        }
    }

    // Assign the data to the global variables
    if ( isset( $target_configuration['post_type_definition'] ) ) {
        $kss_post_type_definition = $target_configuration['post_type_definition'];
    } else {
        // translators: %s is the slug of the configuration being processed.
        error_log( sprintf( esc_html__( 'KSS Survey Error: "post_type_definition" not found in the selected JSON configuration for slug: %s', $text_domain ), $slug_to_load ) );
        $kss_post_type_definition = null; // Ensure a clean state on error
    }

    if ( isset( $target_configuration['survey_questions_config'] ) ) {
        $kss_survey_questions_config = $target_configuration['survey_questions_config'];
    } else {
        // translators: %s is the slug of the configuration being processed.
        error_log( sprintf( esc_html__( 'KSS Survey Error: "survey_questions_config" not found in the selected JSON configuration for slug: %s', $text_domain ), $slug_to_load ) );
        $kss_survey_questions_config = null; // Ensure a clean state on error
    }

    // The add_action for CPT registration should still be on the 'init' hook,
    // but the *definition* of the CPT (via $kss_post_type_definition)
    // must already be available when that hook runs.
    // It's better to keep add_action('init', ...) outside this function
    // and call this function earlier (e.g., on 'plugins_loaded' or very early 'init').
    // IMPORTANT: This add_action call here means kss_register_survey_entry_cpt() will be added
    // to the 'init' hook *every time* define_survey_variables() is called. This can lead to
    // the CPT registration function being hooked multiple times if define_survey_variables()
    // is called more than once before 'init'.
    // It's generally better to hook functions to 'init' only once, typically in the main plugin file
    // or an includes file that runs once.
    // Consider moving this add_action out of this function.
    // For now, it will use the $kss_post_type_definition set by the *last call* to define_survey_variables()
    // before the 'init' action fires.
    add_action( 'init', 'kss_register_survey_entry_cpt' );

    if ($kss_post_type_definition === null || $kss_survey_questions_config === null) {
        return false; // Something went wrong with the assignment.
    }

    return true; // Successfully loaded and assigned
}

//define_survey_variables('survey_entry');
/**
 * Register the shortcode [site_survey].
 */
function kss_register_site_survey_shortcode() {
    add_shortcode( 'site_survey', 'kss_render_site_survey_form' );
}
add_action( 'init', 'kss_register_site_survey_shortcode' );

/**
 * Generate and return the HTML for the survey form.
 */
function kss_render_site_survey_form( $atts ) {
    // --- CSS Enqueue ---
    // Unique handle for your stylesheet
    $style_handle = 'simple-survey-builder-style'; // Use the plugin-slug for uniqueness

    // ** NEW WAY TO GET THE URL **
    // plugins_url() returns the URL to the 'plugins' directory.
    // We then add the path within your plugin directory.
    // __FILE__ is a magic constant that contains the full path and filename
    // of the current PHP file. This is crucial to make it relative to your plugin.
    $style_url = plugins_url( 'css/simple-survey-builder.css', __FILE__ );

    // ** NEW WAY TO GET THE PATH FOR filemtime **
    // plugin_dir_path() returns the system path to the directory of the specified file.
    $css_file_path = plugin_dir_path( __FILE__ ) . 'css/simple-survey-builder.css';

    // Version number (optional, good for cache busting)
    $version = defined('WP_DEBUG') && WP_DEBUG && file_exists($css_file_path) ? filemtime( $css_file_path ) : '1.0.0';

    // Enqueue the stylesheet
    if ( ! wp_style_is( $style_handle, 'enqueued' ) && ! wp_style_is( $style_handle, 'done' ) ) {
        wp_enqueue_style( $style_handle, $style_url, array(), $version );
    }
    // --- End CSS Enqueue ---

    global $kss_post_type_definition; // Needed to get the CPT slug after loading

    $default_slug = 'default_survey_slug'; // Fallback slug if something goes wrong
    if ( is_array( $kss_post_type_definition ) && isset( $kss_post_type_definition['slug'] ) ) {
        $default_slug = $kss_post_type_definition['slug'];
    }

    // 1. Default attributes and merge with what's provided
    $atts = shortcode_atts( array(
        'slug'            => $default_slug,
        'redirect_page'   => '', // Optional redirect page slug or ID
        'title'           =>'', // Optional title on top of survey
    ), $atts, 'site_survey' ); // Use the actual shortcode tag 'site_survey'**

    // 2. Validate the provided slug
    if ( empty( $atts['slug'] ) ) {
        // translators: %s: is the shortcode name. Example: [cpt_list slug="your_slug"]
        return '<p class="kss-error">' . sprintf( esc_html__( 'Error: No slug provided for the %s shortcode. Use e.g., [%s slug="your_slug"].', 'simple-survey-builder' ), 'cpt_list', 'cpt_list' ) . '</p>';
    }
    $current_slug_identifier = sanitize_key( $atts['slug'] );

    // 3. Load the configuration variables based on the slug
    define_survey_variables( $current_slug_identifier ); // This function should ensure $kss_survey_questions_config and others are populated.
    
    // Default attributes (optional, for future expansion)
    // $attributes = shortcode_atts( array(
        // 'example_attribute' => 'default_value',
    // ), $atts );

    // Start output buffering to capture the HTML
    ob_start();
    // Check if the success query variable is present
    // Use filter_input for safer access to GET variables
    $survey_success = filter_input( INPUT_GET, 'survey_success', FILTER_SANITIZE_STRING ); // or FILTER_VALIDATE_BOOLEAN if you expect '1' or 'true'

    if ( $survey_success && ($survey_success === 'true' || $survey_success === '1') ) {
        // Display the success message
        ?>
        <div class="site-survey-success-message" style="padding: 15px; background-color: #e6ffed; border: 1px solid #b3ffc6; margin-bottom: 20px;">
            <h2><?php esc_html_e( 'Thank you for your feedback!', 'simple-survey-builder' ); ?></h2>
            <p><?php esc_html_e( 'Your response has been successfully received and saved.', 'simple-survey-builder' ); ?></p>
            <?php
                // Try to get the URL of the current page without the 'survey_success' query var
                $current_url_no_success = remove_query_arg('survey_success');
                // If you use a hidden field 'kss_current_page_url', you could also use that here
                // to ensure you're linking to the correct page.
            ?>
            <p>
                <a href="<?php echo esc_url( $current_url_no_success ); ?>"><?php esc_html_e( 'Fill out the survey again', 'simple-survey-builder' ); ?></a> (<?php esc_html_e( 'optional', 'simple-survey-builder'); ?>) <?php esc_html_e( 'or', 'simple-survey-builder' ); ?> <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'go to the homepage', 'simple-survey-builder' ); ?></a>.
            </p>
        </div>
        <?php
    } else {
        global $kss_survey_questions_config; // Get the global configuration
        // global $kss_post_type_definition; // Already declared above

        if ( empty( $kss_survey_questions_config ) ) {
            // This message is more for the site admin/developer during setup.
            echo '<p>' . esc_html__( 'Error: The survey questions configuration has not been loaded. Please check the survey slug and JSON configuration.', 'simple-survey-builder' ) . '</p>';
        } else {
            $title = 'Please fill out the survey below';
            if ( ! empty( $atts['title'] ) ) {
                $user_provided_title = $atts['title']; // Do not escape directly here!

                // Define allowed HTML for the title
                $allowed_title_html = array(
                    'br' => array(),      // Allow <br> tags
                    'strong' => array(),  // Allow <strong> tags
                    'em' => array(),      // Allow <em> tags
                    // Add more tags here if necessary and safe
                );

                // Filter the user-provided title with wp_kses
                $title = wp_kses( $user_provided_title, $allowed_title_html );
            }

            ?>
            <div class="site-survey-form-container kss-survey-form-container">
                <form id="site-survey-form" method="post" action="">
                    <?php wp_nonce_field( 'kss_submit_survey_action', 'kss_survey_nonce' ); ?>
                    <?php
                    if ( ! empty( $atts['redirect_page'] ) ) {?><input type="hidden" name="kss_redirect_page_slug" value=" <?php echo esc_attr( $atts['redirect_page'] ).'">';} ?>
                    <input type="hidden" name="kss_current_page_url" value="<?php echo esc_url( get_permalink( get_the_ID() ) ); ?>">
                    <input type="hidden" name="kss_submitted_survey_slug" value="<?php echo esc_attr( $current_slug_identifier ); ?>">

                    <h2><?php if ( ! empty( $atts['title'] ) ) {echo $title;}else esc_html_e( 'Please fill out the survey below', 'simple-survey-builder' ); ?></h2>

                    <?php
                    $current_legend_text = null; // Keep track of the current legend text
                    $required_fields = false;
                    foreach ( $kss_survey_questions_config as $field_key => $config ) :
                        $field_id_name = 'kss_' . $field_key; // Use the key from the config for name/id
                        $current_required = (isset($config['required']) && $config['required']);
                        if ($current_required){
                            $required_fields = true;
                        }

                        // Start a new fieldset if the legend changes
                        if ( isset( $config['legend'] ) && $config['legend'] !== $current_legend_text ) {
                            if ( $current_legend_text !== null ) {
                                echo '</fieldset>'; // Close previous fieldset
                            }
                            echo '<fieldset>';
                            // Legend text comes from JSON, assumed to be already in the desired language or managed there.
                            // If legends also need to be translatable from default English strings in PHP, this needs a different approach.
                            echo '<legend>' . esc_html( $config['legend'] ) . '</legend>';
                            $current_legend_text = $config['legend'];
                        } elseif ( !isset( $config['legend'] ) && $current_legend_text !== "no_legend_fieldset_opened" ) {
                        // If there is no legend, and we haven't opened a "no legend" fieldset yet
                            if ( $current_legend_text !== null && $current_legend_text !== "no_legend_fieldset_opened") {
                                echo '</fieldset>'; // Close previous specific legend fieldset
                            }
                            if ($current_legend_text !== "no_legend_fieldset_opened") {
                                echo '<fieldset>'; // Fieldset for questions without an explicit legend
                                // This internal marker "no_legend_fieldset_opened" is not a user-facing string, so no i18n needed.
                                $current_legend_text = "no_legend_fieldset_opened";
                            }
                        }
                    ?>
                        <div class="survey-question-item">
                            <?php // The form_label comes from the JSON config. Assumed to be in the desired language or managed via JSON structure. ?>
                            <label class="survey-question-label <?php echo $current_required ? 'required' : ''; ?>" for="<?php echo esc_attr( $field_id_name ); ?>"><?php echo esc_html( $config['form_label'] );?></label><br>

                            <?php if ( $config['form_type'] === 'select' && isset( $config['form_options'] ) ) : ?>
                                <select name="<?php echo esc_attr( $field_id_name ); ?>" id="<?php echo esc_attr( $field_id_name ); ?>" <?php echo $current_required ? 'required' : ''; ?>>
                                    <?php foreach ( $config['form_options'] as $value => $option_label ) : // Option labels also from JSON ?>
                                        <option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $option_label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ( $config['form_type'] === 'textarea' ) : ?>
                                <textarea name="<?php echo esc_attr( $field_id_name ); ?>"
                                          id="<?php echo esc_attr( $field_id_name ); ?>"
                                          rows="<?php echo isset( $config['rows'] ) ? esc_attr( $config['rows'] ) : '4'; ?>"
                                          placeholder="<?php echo isset( $config['placeholder'] ) ? esc_attr( $config['placeholder'] ) : ''; // Placeholder from JSON ?>"
                                          <?php echo (isset($config['required']) && $config['required']) ? 'required' : ''; ?>></textarea>
                            <?php
                            // ++++++++++ CODE FOR RADIO BUTTONS ++++++++++
                            elseif ( $config['form_type'] === 'radio' && isset( $config['form_options'] ) && is_array( $config['form_options'] ) ) :
                                // For radio buttons, the main label is for the group.
                                // The <label> above is for the entire question.
                                // We'll place individual labels per radio option below.
                            ?>
                                <fieldset class="survey-radio-group" id="fieldset-<?php echo esc_attr( $field_id_name ); ?>">
                                    <?php // Screen reader legend text from JSON config (form_label) ?>
                                    <legend class="screen-reader-text"><?php echo esc_html( $config['form_label'] ); ?></legend>
                                    <?php
                                    $is_first_radio = true;
                                    foreach ( $config['form_options'] as $value => $option_label ) : // Option labels from JSON
                                        $radio_id = esc_attr( $field_id_name . '_' . $value );
                                    ?>
                                        <span class="survey-radio-option">
                                            <input type="radio"
                                                   name="<?php echo esc_attr( $field_id_name ); ?>"
                                                   id="<?php echo $radio_id; ?>"
                                                   value="<?php echo esc_attr( $value ); ?>"
                                                   <?php echo (isset($config['required']) && $config['required'] && $is_first_radio) ? 'required' : ''; // Only make the first radio required for HTML5 validation ?>
                                            >
                                            <label for="<?php echo $radio_id; ?>"><?php echo esc_html( $option_label ); ?></label>
                                        </span>
                                    <?php
                                        $is_first_radio = false;
                                    endforeach;
                                    ?>
                                </fieldset>
                            <?php // ++++++++++ RADIO BUTTONS END HERE ++++++++++
                            // ++++++++++ NEW CODE FOR CHECKBOXES STARTS HERE ++++++++++
                            elseif ( $config['form_type'] === 'checkbox' && isset( $config['form_options'] ) && is_array( $config['form_options'] ) ) :
                                // Checkboxes are sent as an array of values if the 'name' ends with [].
                                $checkbox_group_name = esc_attr( $field_id_name ) . '[]';
                            ?>
                                <fieldset class="survey-checkbox-group" id="fieldset-<?php echo esc_attr( $field_id_name ); ?>">
                                     <?php // Screen reader legend text from JSON config (form_label, or question_display_label if that was a separate var) ?>
                                    <legend class="screen-reader-text"><?php echo esc_html( $config['form_label'] ); ?></legend>
                                    <?php foreach ( $config['form_options'] as $value => $option_label ) : // Option labels from JSON
                                        $checkbox_id = esc_attr( $field_id_name . '_' . $value );
                                    ?>
                                        <div class="survey-checkbox-option">
                                            <input type="checkbox"
                                                   name="<?php echo $checkbox_group_name; ?>"
                                                   id="<?php echo $checkbox_id; ?>"
                                                   value="<?php echo esc_attr( $value ); ?>"
                                                   <?php // 'required' for a group of checkboxes is more complex in pure HTML.
                                                         // Usually 'required' means at least one checkbox must be checked.
                                                         // This should primarily be validated in PHP.
                                                         // You can add JavaScript for client-side validation if desired.
                                                    ?>
                                            >
                                            <label for="<?php echo $checkbox_id; ?>"><?php echo esc_html( $option_label ); ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </fieldset>
                            <?php // ++++++++++ NEW CODE FOR CHECKBOXES ENDS HERE ++++++++++
                            else : ?>
                                <?php // translators: %s: is the form field type that is not configured. ?>
                                <p><em><?php printf( esc_html__( 'Form field type "%s" is not configured.', 'simple-survey-builder' ), esc_html( $config['form_type'] ) ); ?></em></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <?php if ( $current_legend_text !== null ) : // Close the very last fieldset ?>
                        </fieldset>
                    <?php endif; ?>
                    <?php
                    if ($required_fields){
                        // This text explains what the asterisk or 'required' class means.
                        echo '<div class="required-explanation">' . esc_html__( 'Required questions', 'simple-survey-builder' ) . '</div>';
                    }
                    ?>

                    <p>
                        <?php // The submit button text can also come from JSON if you want per-survey customization.
                              // For now, making it a general translatable string.
                        ?>
                        <input type="submit" name="kss_submit_survey" value="<?php echo esc_attr_x( 'Submit Feedback', 'verb: form submit button', 'simple-survey-builder' ); ?>">
                    </p>
                </form>
            </div>
            <?php
        } // End else (configuration loaded)
    } // End else (show form instead of success message)

    return ob_get_clean();
}

/**
 * Handle the submission of the survey form.
 */
function kss_handle_survey_submission() {
    // global $kss_current_slug; // This was commented out, might be from a previous approach.
    $text_domain = 'simple-survey-builder'; // Main text domain for the plugin.

    // Check if the form was submitted and the nonce is correct
    // The nonce verification should ideally happen right at the beginning,
    // before any other processing, using wp_verify_nonce().
    // For example:
    // if ( ! isset( $_POST['kss_survey_nonce'] ) || ! wp_verify_nonce( $_POST['kss_survey_nonce'], 'kss_survey_action_' . $some_identifier_from_form ) ) {
    //     wp_die( esc_html__( 'Security check failed. Please try again.', $text_domain ), esc_html__( 'Nonce Error', $text_domain ), array( 'response' => 403 ) );
    //     return;
    // }
    // The current code checks for nonce presence but not its validity against an action yet.

    if ( isset( $_POST['kss_submit_survey'] ) && isset( $_POST['kss_survey_nonce'] ) ) {
        // ** THE SLUG IS RECEIVED HERE **
        if ( ! isset( $_POST['kss_submitted_survey_slug'] ) || empty( $_POST['kss_submitted_survey_slug'] ) ) {
            // This error_log is for developers.
            error_log("KSS Survey Error: kss_submitted_survey_slug not received or empty in submission handler.");
            // This wp_die is user-facing.
            wp_die(
                esc_html__( 'Error: Critical survey information (slug) is missing. Cannot process submission.', $text_domain ),
                esc_html__( 'Configuration Error', $text_domain ),
                array( 'response' => 400, 'back_link' => true ) // Added back_link for usability
            );
            return; // Should not be reached if wp_die is called.
        }

        $submitted_slug = sanitize_text_field( $_POST['kss_submitted_survey_slug'] );
        // define_survey_variables() is expected to populate globals like $kss_survey_questions_config
        define_survey_variables( $submitted_slug );

        // ** DEBUGGING STEP: **
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            error_log("KSS Survey Debug: Received slug in submission handler: " . $submitted_slug);
        }

        global $kss_survey_questions_config; // Get the global questions configuration
        $submitted_data = array();
        $errors = array();
        // $kss_current_slug; // This seems like an unused variable or remnant.

        if ( empty( $kss_survey_questions_config ) ) {
            // This error is primarily for the user if something goes wrong.
            // A more robust error message might be needed for the user, or this could be logged
            // and a generic error shown to the user.
            $errors[] = esc_html__( 'Error: The survey questions configuration was not loaded for validation.', $text_domain );
            // Do not proceed if the configuration is missing
            // The error handling further down will catch this $errors entry.
        } else {
            // Loop through the configured questions to validate and retrieve data
            foreach ( $kss_survey_questions_config as $field_key => $config ) {
                $post_field_name = 'kss_' . $field_key; // The name of the field in the $_POST array

                // Get the submitted value (if present)
                if ( isset($config['form_type']) && $config['form_type'] === 'checkbox' ) {
                    // Checkbox values arrive as an array under $post_field_name[]
                    // So $_POST[$post_field_name] will be an array if anything is selected.
                    if ( isset( $_POST[ $post_field_name ] ) && is_array( $_POST[ $post_field_name ] ) ) {
                        $selected_checkbox_options = array_map('sanitize_text_field', $_POST[ $post_field_name ]); // Sanitize each value in the array

                        // Validation for required checkboxes (at least one must be selected)
                        if ( isset( $config['required'] ) && $config['required'] === true && empty( $selected_checkbox_options ) ) {
                            $error_label = isset($config['form_label']) ? $config['form_label'] : ucfirst(str_replace('_', ' ', $field_key));
                            // translators: %s is the field label.
                            $errors[] = sprintf( esc_html__( '%s is a required field (choose at least one option).', $text_domain ), esc_html( $error_label ) );
                            continue; // Skip to next field
                        }

                        // Convert selected options to a bitwise integer
                        $bitwise_value = 0;
                        // IMPORTANT: Ensure $config['form_options'] exists and has the keys we expect.
                        if (isset($config['form_options']) && is_array($config['form_options'])) {
                            $option_keys_in_order = array_keys($config['form_options']); // Get the keys in their defined order
                            foreach ( $selected_checkbox_options as $selected_option_value ) {
                                // Find the index (position) of the selected value in the original option list
                                $option_index = array_search($selected_option_value, $option_keys_in_order);
                                if ($option_index !== false) { // Ensure the submitted value is a valid option
                                    $bitwise_value |= (1 << $option_index);
                                } else {
                                     // Optional: log a warning if an invalid checkbox value was submitted
                                     if (defined('WP_DEBUG') && WP_DEBUG === true) {
                                        error_log("KSS Survey Warning: Invalid checkbox value '" . esc_html($selected_option_value) . "' submitted for field: " . esc_html($field_key));
                                     }
                                }
                            }
                        } else {
                             if (defined('WP_DEBUG') && WP_DEBUG === true) {
                                error_log("KSS Survey Warning: form_options missing in configuration for checkbox field: " . esc_html($field_key));
                             }
                        }
                        $submitted_value = $bitwise_value;

                    } elseif ( isset( $config['required'] ) && $config['required'] === true ) {
                        // No checkboxes selected, but it is required
                        $error_label = isset($config['form_label']) ? $config['form_label'] : ucfirst(str_replace('_', ' ', $field_key));
                        // translators: %s is the field label.
                        $errors[] = sprintf( esc_html__( '%s is a required field (choose at least one option).', $text_domain ), esc_html( $error_label ) );
                        continue; // Skip to next field
                    } else {
                        // Not required and nothing selected, store 0 (no bits set)
                        $submitted_value = 0;
                    }
                    // Sanitization for checkboxes: $submitted_value is already an integer, so no extra text sanitization needed.
                    // If you want additional validation (e.g., max number of selections), that should happen here.
                    $sanitized_value = intval($submitted_value); // Ensure it's an integer.

                // ---- End of adjustment for CHECKBOX type ----
                } else {
                     // For other field types
                    $submitted_value = isset( $_POST[ $post_field_name ] ) ? trim( $_POST[ $post_field_name ] ) : null;
                }

                // Validation for required fields (non-checkbox)
                // This block is only reached if not a checkbox, or if it's a checkbox and values were processed.
                // However, the required check for checkboxes was already done above.
                // This part should specifically handle non-checkbox required fields.
                if ( !(isset($config['form_type']) && $config['form_type'] === 'checkbox') && isset( $config['required'] ) && $config['required'] === true ) {
                    // Check if the value is empty. For '0' as a valid value, you might need to be more specific.
                    // For most select fields, an empty value will be ''.
                    if ( $submitted_value === null || $submitted_value === '' ) {
                        // Use form_label if it exists, otherwise the generic label
                        $error_label = isset($config['form_label']) ? $config['form_label'] : (isset($config['label']) ? $config['label'] : ucfirst(str_replace('_', ' ', $field_key)));
                        // translators: %s is the field label.
                        $errors[] = sprintf( esc_html__( '%s is a required field.', $text_domain ), esc_html( $error_label ) );
                        continue; // Go to the next field if this required field is empty
                    }
                }

                // If the field is not required and not filled in (and not a checkbox, which defaults to 0 if not set and not required),
                // we don't need to process it further.
                // Checkboxes are handled: if not required and not set, $sanitized_value becomes 0.
                if ( !(isset($config['form_type']) && $config['form_type'] === 'checkbox') && ($submitted_value === null || $submitted_value === '') && ( !isset($config['required']) || $config['required'] === false ) ) {
                    // Store an empty string or null, depending on your database preference for optional fields
                    // For post meta, an empty string is usually fine.
                    $submitted_data[ $field_key ] = '';
                    continue; // Skip to next field
                }

                // Sanitization based on form_type (or a new 'sanitize_type' field in the config)
                // For checkboxes, $sanitized_value is already set to an int.
                if ( !(isset($config['form_type']) && $config['form_type'] === 'checkbox') ) {
                    $sanitized_value = ''; // Initialize for non-checkbox types
                    $form_type = isset($config['form_type']) ? $config['form_type'] : 'text'; // Fallback to text

                    switch ( $form_type ) {
                        case 'select':
                        case 'radio': // Radio buttons usually also have discrete values
                        case 'text':
                            $sanitized_value = sanitize_text_field( $submitted_value );
                            break;
                        case 'email': // If you add email fields
                            $sanitized_value = sanitize_email( $submitted_value );
                            // You might also want to validate it with is_email() here and add to $errors if invalid.
                            break;
                        case 'number': // If you add number fields
                            // sanitize_text_field is okay, or intval()/floatval() if you expect specific number types.
                            // For example, if you expect an integer:
                            // $sanitized_value = intval( $submitted_value );
                            $sanitized_value = sanitize_text_field( $submitted_value ); // General purpose
                            break;
                        case 'textarea':
                            $sanitized_value = sanitize_textarea_field( $submitted_value );
                            break;
                        // Add more cases here if you use other field types
                        default:
                            $sanitized_value = sanitize_text_field( $submitted_value ); // Safe fallback
                    }
                }
                // For checkboxes, $sanitized_value was already set earlier.
                $submitted_data[ $field_key ] = $sanitized_value;
            } // End foreach loop through questions
        } // End else (config was loaded)

        // ---- Processing ----

        // If there are validation errors:
        if ( ! empty( $errors ) ) {
            // Build an error message
            $error_html = '<strong>' . esc_html__( 'Please correct the following errors:', $text_domain ) . '</strong><ul>';
            foreach ( $errors as $error ) {
                // $error is already escaped when added to the $errors array
                $error_html .= '<li>' . $error . '</li>';
            }
            $error_html .= '</ul> <p><a href="javascript:history.back()">' . esc_html__( 'Go back and try again.', $text_domain ) . '</a></p>';

            wp_die( $error_html, esc_html__( 'Validation Error', $text_domain ), array( 'response' => 400, 'back_link' => false ) ); // back_link false as we provide one
            // exit; // wp_die() includes an exit.
        }


        // --- TEMPORARY: Display the captured data for testing purposes ---
        // In a next step, we'll replace this with data storage.
        // $output_message = '<h2>' . esc_html__( 'Thank you for your feedback!', $text_domain ) . '</h2>';
        // $output_message .= '<p>' . esc_html__( 'The following information has been received (this is a temporary display for testing purposes):', $text_domain ) . '</p>';
        // $output_message .= '<pre>' . esc_html( print_r( $submitted_data, true ) ) . '</pre>';
        // $output_message .= '<p><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Back to homepage', $text_domain ) . '</a></p>'; // Or the page where the survey was

        // wp_die( $output_message, esc_html__( 'Feedback Received', $text_domain ), array( 'response' => 200, 'back_link' => true ) );
        // exit; // Important after wp_die to stop further execution

        // ---- Data Storage as Custom Post Type ----
        if ( empty( $errors ) ) { // Only proceed if there are no validation errors

            // Compile the post data
            // translators: %1$s is date, %2$s is time.
            $post_title = sprintf(
                esc_html__( 'Survey Submission - %1$s %2$s', $text_domain ),
                date_i18n( get_option( 'date_format', 'd-m-Y' ) ), // Use WordPress date format
                date_i18n( get_option( 'time_format', 'H:i:s' ) )  // Use WordPress time format
            );
            $post_content = ''; // We are not using the editor, so content can remain empty.
                                // Or you could create a summary here if you wish.

            global $kss_post_type_definition; // Make sure this is populated correctly by define_survey_variables()
            if (empty($kss_post_type_definition) || !isset($kss_post_type_definition['slug'])) {
                error_log("KSS Survey Error: \$kss_post_type_definition not properly set before trying to insert post.");
                wp_die(
                    esc_html__( 'A critical error occurred while trying to save your feedback due to missing configuration. Please contact the site administrator.', $text_domain ),
                    esc_html__( 'Save Error', $text_domain ),
                    array( 'response' => 500 )
                );
                return;
            }

            $new_survey_entry_args = array(
                'post_title'    => $post_title,
                'post_content'  => $post_content, // Empty, we use custom fields
                'post_status'   => 'publish',    // Set directly to 'published' (or 'pending' if you want review)
                'post_type'     => $kss_post_type_definition['slug'], // The slug of our CPT
                // 'post_author' => get_current_user_id(), // Optional: if you want to know who (if logged in) filled it out
            );

            // Add the new post (survey entry) to the database
            $post_id = wp_insert_post( $new_survey_entry_args, true ); // true to get WP_Error object on failure

            if ( is_wp_error( $post_id ) ) {
                // Error saving the post
                // Log the error for the admin, show a generic error to the user
                error_log( 'KSS Survey Error: Error saving survey entry: ' . $post_id->get_error_message() );
                wp_die(
                    esc_html__( 'An error occurred while processing your feedback. Please try again later.', $text_domain ),
                    esc_html__( 'Save Error', $text_domain ),
                    array( 'response' => 500, 'back_link' => true )
                );
                // exit; // wp_die() includes exit.
            } else {
                // Post successfully created, now save the individual answers as post meta (custom fields)
                foreach ( $submitted_data as $key => $value ) {
                    // Use the keys from $submitted_data directly as meta keys
                    // Example: 'overall_rating' becomes meta_key 'kss_overall_rating'
                    update_post_meta( $post_id, 'kss_' . $key, $value );
                    // Prefix 'kss_' to prevent conflicts with other meta keys and to group them.
                }

                // ---- Success Message and Redirect ----

                // Option 2: Show a message on the same page (via wp_die or by setting a query var) - we are using redirect with query var.
                // For now, a simple wp_die like before, but with a success message (this was the old approach)
                // $success_message = '<h2>' . esc_html__( 'Thank you for your feedback!', $text_domain ) . '</h2>';
                // $success_message .= '<p>' . esc_html__( 'Your response has been successfully received and saved.', $text_domain ) . '</p>';
                // $success_message .= '<p><a href="' . esc_url( wp_get_referer() ? wp_get_referer() : home_url('/') ) . '">' . esc_html__( 'Go back', $text_domain ) . '</a> ' .
                //                     esc_html__( 'or', $text_domain ) .
                //                     ' <a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'go to the homepage', $text_domain ) . '</a>.</p>';

                // wp_die( $success_message, esc_html__( 'Feedback Received', $text_domain ), array( 'response' => 200, 'back_link' => false ) ); // back_link false as we show custom links
                // exit; // Important after wp_die

                $redirect_url = '';
                //Check for the custom redirect page slug from the form**
                if ( ! empty( $_POST['kss_redirect_page_slug'] ) ) {
                    $page_slug_or_id = sanitize_text_field( $_POST['kss_redirect_page_slug'] );

                    $redirect_page = null;

                    if ( is_numeric( $page_slug_or_id ) ) { // Check if it's an ID
                        $redirect_page = get_post( intval( $page_slug_or_id ) );
                    } else { // Assume it's a slug
                        $redirect_page = get_page_by_path( $page_slug_or_id );
                    }

                    if ( $redirect_page ) {
                        $redirect_url = get_permalink( $redirect_page->ID );
                    }
                }

                // Fallback to _wp_http_referer if custom redirect is not set or not found
                if ( empty( $redirect_url ) &&  ! empty( $_POST['_wp_http_referer'] ) ) {
                    // _wp_http_referer is a hidden field that WordPress often adds.
                    // Remove any existing query vars to prevent them from being duplicated.
                    $redirect_url = remove_query_arg( array('survey_success', 'survey_error'), wp_unslash( $_POST['_wp_http_referer'] ) );
                } elseif(empty( $redirect_url )) {
                    // Fallback if _wp_http_referer is not available.
                    // Getting the current URL is tricky as we are in an 'init' hook action.
                    // You could pass the ID of the page where the shortcode is as a hidden field in the form,
                    // then construct the permalink from that ID.
                    // For now, a simple fallback to the home URL, which is not ideal.
                    // A better fallback might be to have a dedicated "thank you" page slug in your plugin settings.
                    // e.g., $thank_you_page_slug = get_option('kss_survey_thank_you_page_slug', 'survey-thank-you');
                    // $redirect_url = home_url('/' . $thank_you_page_slug . '/');
                    $redirect_url = home_url('/'); // IMPROVE THIS IF POSSIBLE
                }

                // Add the query variable for the success message
                $redirect_url = add_query_arg( 'survey_success', 'true', $redirect_url );

                // Remove any error message query var if it was there from a previous attempt
                $redirect_url = remove_query_arg( 'survey_error', $redirect_url );

                wp_safe_redirect( esc_url_raw( $redirect_url ) ); // Ensure URL is properly escaped for redirect
                exit; // Essential after wp_safe_redirect()
            }
        }
        // If there were $errors, they have already been handled with wp_die in the earlier block.
    } // End if (isset($_POST['kss_submit_survey']))
} // End function kss_handle_survey_submission
// Hook the function to 'init'.
// The 'init' hook is a good common hook which loads early, before headers are sent
add_action( 'init', 'kss_handle_survey_submission' );

/**
 * Register the Custom Post Type for survey entries.
 * This function seems to be a more specific version or an older version
 * of what kss_register_cpts_from_json does. Ensure this is still needed
 * or if kss_register_cpts_from_json should handle this CPT registration.
 */
function kss_register_survey_entry_cpt() {
    global $kss_post_type_definition; // This global variable is expected to be populated, e.g., by define_survey_variables()

    // Check if the configuration exists and has all necessary keys
    if ( empty( $kss_post_type_definition ) ||
         !isset( $kss_post_type_definition['slug'] ) ||
         !isset( $kss_post_type_definition['text_domain'] ) || // This text_domain is for the CPT's own names
         !isset( $kss_post_type_definition['name_plural'] ) ||
         !isset( $kss_post_type_definition['name_singular'] ) ||
         !isset( $kss_post_type_definition['menu_name_main'] ) ) {

        // Log an error if the configuration is missing or incorrect.
        // Consider making this translatable if it were an admin notice.
        error_log('KSS Survey Plugin: CPT configuration for survey entry is missing or incorrect.');
        // Consider adding an admin_notice for better visibility to the site admin.
        // add_action( 'admin_notices', function() {
        //     echo '<div class="notice notice-error"><p>' .
        //          esc_html__( 'KSS Survey Plugin: Critical configuration for Survey Entry CPT is missing. The CPT cannot be registered.', 'simple-survey-builder' ) .
        //          '</p></div>';
        // });
        return;
    }

    // Retrieve values from $kss_post_type_definition
    // These values (name_plural, name_singular) are assumed to be English and will serve as msgids for their own $text_domain.
    $slug          = $kss_post_type_definition['slug'];
    $text_domain   = $kss_post_type_definition['text_domain']; // Text domain for the CPT's own descriptive names
    $name_plural   = $kss_post_type_definition['name_plural'];
    $name_singular = $kss_post_type_definition['name_singular'];
    $menu_name     = $kss_post_type_definition['menu_name_main'];

    // Define the main plugin text domain for generic labels, to ensure they use the plugin's .po file.
    $main_plugin_text_domain = 'simple-survey-builder';

    // Generate the labels array dynamically
    // The $text_domain variable (from $kss_post_type_definition) is used for the CPT's own names (e.g., "Event", "Events").
    // The $main_plugin_text_domain is used for generic WordPress UI strings (e.g., "Add New", "Edit Item").
    $labels = array(
        'name'                  => _x( $name_plural, 'Post type general name', $text_domain ),
        'singular_name'         => _x( $name_singular, 'Post type singular name', $text_domain ),
        'menu_name'             => _x( $menu_name, 'Admin Menu text', $text_domain ),
        'name_admin_bar'        => _x( $name_singular, 'Add New on Toolbar', $text_domain ),

        // Generic labels using the main plugin's text domain and English msgids
        // translators: %s: singular post type name (e.g., "Event")
        'add_new'               => sprintf( __( 'Add New %s', $main_plugin_text_domain ), $name_singular ),
        // translators: %s: singular post type name
        'add_new_item'          => sprintf( __( 'Add New %s', $main_plugin_text_domain ), $name_singular ),
        // translators: %s: singular post type name
        'new_item'              => sprintf( __( 'New %s', $main_plugin_text_domain ), $name_singular ),
        // translators: %s: singular post type name
        'edit_item'             => sprintf( __( 'Edit %s', $main_plugin_text_domain ), $name_singular ),
        // translators: %s: singular post type name
        'view_item'             => sprintf( __( 'View %s', $main_plugin_text_domain ), $name_singular ),
        // translators: %s: plural post type name (e.g., "Events")
        'all_items'             => sprintf( __( 'All %s', $main_plugin_text_domain ), $name_plural ),
        // translators: %s: plural post type name
        'search_items'          => sprintf( __( 'Search %s', $main_plugin_text_domain ), $name_plural ),
        // translators: %s: singular post type name
        'parent_item_colon'     => sprintf( __( 'Parent %s:', $main_plugin_text_domain ), $name_singular ),
        // translators: %s: plural post type name, lowercase (e.g., "events")
        'not_found'             => sprintf( __( 'No %s found.', $main_plugin_text_domain ), lcfirst($name_plural) ),
        // translators: %s: plural post type name, lowercase
        'not_found_in_trash'    => sprintf( __( 'No %s found in Trash.', $main_plugin_text_domain ), lcfirst($name_plural) ),

        // For the following, use _x with sprintf for placeholders, and use the CPT's $text_domain
        // if the string is highly specific to the CPT's nature, or $main_plugin_text_domain for generic phrases.
        // Assuming $name_singular is English from the config.
        // translators: %s: singular post type name. Context: Overrides the “Featured Image” phrase.
        'featured_image'        => sprintf( _x( 'Featured image for %s', 'Overrides the “Featured Image” phrase for this post type. Added in 4.3', $main_plugin_text_domain ), $name_singular ),
        // translators: Context: Overrides the “Set featured image” phrase.
        'set_featured_image'    => _x( 'Set featured image', 'Overrides the “Set featured image” phrase for this post type. Added in 4.3', $main_plugin_text_domain ),
        // translators: Context: Overrides the “Remove featured image” phrase.
        'remove_featured_image' => _x( 'Remove featured image', 'Overrides the “Remove featured image” phrase for this post type. Added in 4.3', $main_plugin_text_domain ),
        // translators: Context: Overrides the “Use as featured image” phrase.
        'use_featured_image'    => _x( 'Use as featured image', 'Overrides the “Use as featured image” phrase for this post type. Added in 4.3', $main_plugin_text_domain ),
        // translators: %s: singular post type name. Context: The post type archive label.
        'archives'              => sprintf( _x( '%s Archives', 'The post type archive label used in nav menus. Default “Post Archives”. Added in 4.4', $main_plugin_text_domain ), $name_singular ),
        // translators: %s: singular post type name, lowercase. Context: Overrides “Insert into post” phrase.
        'insert_into_item'      => sprintf( _x( 'Insert into %s', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post). Added in 4.4', $main_plugin_text_domain ), lcfirst($name_singular) ),
        // translators: %s: singular post type name, lowercase. Context: Overrides “Uploaded to this post” phrase.
        'uploaded_to_this_item' => sprintf( _x( 'Uploaded to this %s', 'Overrides the “Uploaded to this post” phrase (used when viewing media attached to a post). Added in 4.4', $main_plugin_text_domain ), lcfirst($name_singular) ),
        // translators: %s: plural post type name, lowercase. Context: Screen reader text for filter links.
        'filter_items_list'     => sprintf( _x( 'Filter %s list', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/”Filter pages list”. Added in 4.4', $main_plugin_text_domain ), lcfirst($name_plural) ),
        // translators: %s: plural post type name. Context: Screen reader text for pagination.
        'items_list_navigation' => sprintf( _x( '%s list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/”Pages list navigation”. Added in 4.4', $main_plugin_text_domain ), $name_plural ),
        // translators: %s: plural post type name. Context: Screen reader text for items list.
        'items_list'            => sprintf( _x( '%s list', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/”Pages list”. Added in 4.4', $main_plugin_text_domain ), $name_plural ),
        // translators: %s: singular post type name. WordPress 5.0+
        'item_updated'          => sprintf( __( '%s updated.', $main_plugin_text_domain ), $name_singular ),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => false, // Not publicly visible on the frontend (unless you want that later)
        'publicly_queryable' => false, // Not directly queryable via URL
        'show_ui'            => true,  // Show in the admin UI
        'show_in_menu'       => true,  // Show in the admin menu
        'query_var'          => false, // No query var needed if not publicly_queryable
        'rewrite'            => false, // No rewrite rules needed if not public
        'capability_type'    => 'post', // Use standard post capabilities (edit_post, delete_post, etc.)
                                      // Consider 'kss_survey_entry' or similar for custom capabilities if needed.
        'has_archive'        => false, // No archive page needed
        'hierarchical'       => false, // Not hierarchical (like pages)
        'menu_position'      => 25,    // Position in the admin menu (below Comments)
        'menu_icon'          => 'dashicons-feedback', // Icon for the menu
        'supports'           => array( 'title', 'custom-fields' ), // We use the title for a summary and custom fields for the data. No editor needed by default.
        'show_in_rest'       => false, // Default to false unless explicitly needed for Gutenberg or REST API consumers
        // 'delete_with_user' => false, // Optional: What happens to CPT entries when a user is deleted. Default is null (WordPress default behavior).
    );

    // Before registering, you could check if post_type_exists( $slug ) if this function
    // might be called multiple times or if another plugin/theme could define the same CPT.
    // However, if $kss_post_type_definition is meant to be the single source of truth for this CPT,
    // and this function is hooked correctly (e.g., to 'init' with a reasonable priority),
    // such a check might be redundant here if it's already handled by the loading mechanism of $kss_post_type_definition.
    // Check if this slug has already been registered by this plugin or if the CPT already exists in WP
    if (!kss_check_cpt_slug_is_registered( $slug, __FUNCTION__ ))
        register_post_type( $slug, $args );
}

/**
 * Checks if a Custom Post Type (CPT) slug has already been processed by this plugin
 * or if the CPT already exists in WordPress.
 *
 * This function helps prevent attempting to register the same CPT slug multiple times.
 * It uses a global array `$kss_registered_cpt_slugs` to keep track of slugs
 * processed by the KSS plugin itself during the current request.
 * It also checks `post_type_exists()` for CPTs registered by any means.
 *
 * @global array $kss_registered_cpt_slugs An array of CPT slugs already processed by this plugin.
 *
 * @param string $slug The CPT slug to check.
 * @param string $source_function_name Optional. The name of the function calling this check, for more precise logging.
 * @return bool True if the slug is already registered or processed, false otherwise.
 */
function kss_check_cpt_slug_is_registered( $slug, $source_function_name = 'unknown function' ) {
    global $kss_registered_cpt_slugs;
    $text_domain = 'simple-survey-builder'; // Define your plugin's text domain

    // Ensure $kss_registered_cpt_slugs is an array, even if not initialized elsewhere yet for some reason.
    if ( !is_array($kss_registered_cpt_slugs) ) {
        $kss_registered_cpt_slugs = array();
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            // This log helps if the global was not initialized as expected.
            error_log( esc_html__( 'KSS Survey Warning: Global $kss_registered_cpt_slugs was not initialized as an array. Initializing now.', $text_domain ) );
        }
    }

    if ( empty($slug) ) {
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            // translators: %s is the name of the function that called this check.
            error_log( sprintf( esc_html__( 'KSS Survey Debug: Empty slug provided to kss_check_cpt_slug_is_registered() from %s.', $text_domain ), $source_function_name ) );
        }
        return false; // Cannot check an empty slug; treat as not registered to allow potential error handling later.
    }

    $already_processed_by_kss = in_array( $slug, $kss_registered_cpt_slugs, true );
    $exists_in_wp = post_type_exists( $slug );

    if ( $already_processed_by_kss ) {
        if (defined('WP_DEBUG') && WP_DEBUG === true) { // Log only in debug mode to avoid spamming logs for normal skips.
            // translators: %1$s is the CPT slug, %2$s is the name of the function that called this check.
            error_log( sprintf(
                esc_html__( 'KSS Survey Info: CPT slug \'%1$s\' already processed by this plugin. Skipping registration attempt from %2$s.', $text_domain ),
                esc_html( $slug ), // Sanitize slug for logging
                esc_html( $source_function_name )
            ) );
        }
        return true; // Already processed by this plugin in the current request.
    }

    if ( $exists_in_wp ) {
        // This means WordPress knows this CPT, but it wasn't via $kss_registered_cpt_slugs this request.
        // It could be from another plugin, the theme, or an earlier, separate registration by KSS
        // if the $kss_registered_cpt_slugs array was reset or not persistent across certain calls.
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            // translators: %1$s is the CPT slug, %2$s is the name of the function that called this check.
            error_log( sprintf(
                esc_html__( 'KSS Survey Info: CPT slug \'%1$s\' already registered in WordPress (possibly by another plugin/theme, or a different part of KSS). Skipping registration attempt from %2$s.', $text_domain ),
                esc_html( $slug ),
                esc_html( $source_function_name )
            ) );
        }
        return true; // Exists in WordPress.
    }

    return false; // Not yet registered or processed.
}
/**
 * Register the shortcode [site_survey_results].
 */
function kss_register_site_survey_results_shortcode() {
    add_shortcode( 'site_survey_results', 'kss_render_site_survey_results' );
}
add_action( 'init', 'kss_register_site_survey_results_shortcode' );

if ( ! function_exists( 'kss_enqueue_scripts_for_results_shortcode' ) ) {
    function kss_enqueue_scripts_for_results_shortcode() {
        global $post;
        // Check if the current post content contains the shortcode
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'site_survey_results' ) ) { // Ensure this shortcode tag matches yours
            wp_enqueue_script('chartjs-cdn', 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js', array(), '3.9.1', true);
            // You could also enqueue your own JavaScript file here for Chart initialization
            // wp_enqueue_script('my-survey-results-charts', plugin_dir_url(__FILE__) . 'js/survey-results-charts.js', array('chartjs-cdn'), '1.0.0', true);
        }
    }
}
add_action( 'wp_enqueue_scripts', 'kss_enqueue_scripts_for_results_shortcode' );

/**
 * Generate and return the HTML for the survey results.
 */
function kss_render_site_survey_results( $atts ) {
    // Check if we are in the admin area (e.g., Gutenberg editor)
    if ( is_admin() || ( defined('REST_REQUEST') && REST_REQUEST && !empty($_REQUEST['context']) && $_REQUEST['context'] === 'edit' ) ) {
        // Specific check for Gutenberg editor context can also be:
        // if (defined('REST_REQUEST') && REST_REQUEST && !empty($_REQUEST['context']) && $_REQUEST['context'] === 'edit') {
        // But a general is_admin() check is often sufficient for shortcode previews.

        // You can return a simple placeholder here indicating what the shortcode does.
        // This prevents the full (potentially error-prone) logic of the shortcode from running in the editor.
        $output = '<div style="padding: 15px; border: 2px dashed #ccc; background-color: #f9f9f9; text-align: center;">';
        $output .= '<strong>' . esc_html__( 'Survey Results Overview', 'simple-survey-builder' ) . '</strong><br>';
        $output .= '<span style="font-size: 0.9em; color: #555;">(' . esc_html__( 'Results will be displayed here on the live page', 'simple-survey-builder' ) . ')</span>';
        $output .= '</div>';
        return $output;
    }

    global $kss_post_type_definition; // Needed to get the CPT slug after loading

    // 1. Default attributes and merge with what's provided
    // Ensure $kss_post_type_definition is an array and 'slug' key exists before accessing
    $default_slug = 'default_survey_slug'; // Fallback
    if ( is_array( $kss_post_type_definition ) && isset( $kss_post_type_definition['slug'] ) ) {
        $default_slug = $kss_post_type_definition['slug'];
    } else {
        // Optionally log an error or handle if definition is not set as expected
        // error_log('Simple Survey Builder: $kss_post_type_definition not properly set for results shortcode.');
    }

    $atts = shortcode_atts( array(
        'slug'      => $default_slug, // Slug for the survey configuration
    ), $atts, 'site_survey_results' ); // Use the actual shortcode tag, e.g., 'site_survey_results'

    // 2. Validate the provided slug
    if ( empty( $atts['slug'] ) ) {
        // translators: %s: is the shortcode name. Example: [site_survey_results slug="your_slug"]
        return '<p class="kss-error">' . sprintf( esc_html__( 'Error: No slug provided for the %s shortcode. Use e.g., [%s slug="your_slug"].', 'simple-survey-builder' ), 'site_survey_results', 'site_survey_results' ) . '</p>';
    }
    $current_slug_identifier = sanitize_key( $atts['slug'] );

    // 3. Load the configuration variables based on the slug
    define_survey_variables( $current_slug_identifier ); // This function should populate $kss_post_type_definition and $kss_survey_questions_config

    // Check again if $kss_post_type_definition or its slug is available after define_survey_variables
    // as it's used to fetch posts.
    if ( !is_array( $kss_post_type_definition ) || empty( $kss_post_type_definition['slug'] ) ) {
        return '<p class="kss-error">' . esc_html__( 'Error: Survey configuration could not be loaded for the provided slug.', 'simple-survey-builder' ) . '</p>';
    }

    // Get all survey entries
    $args = array(
        'post_type'      => $kss_post_type_definition['slug'],
        'posts_per_page' => -1, // Get all entries
        'post_status'    => 'publish',
    );
    $survey_entries = get_posts( $args );

    if ( empty( $survey_entries ) ) {
        return '<p>' . esc_html__( 'No survey results are available yet.', 'simple-survey-builder' ) . '</p>';
    }

    // Array to collect all answers per question
    $all_answers = array();
    $total_entries = count( $survey_entries );
    $charts_data_for_js = array(); // Initialize for JS data

    foreach ( $survey_entries as $entry ) {
        // Get all custom fields (answers) for this entry
        $entry_meta = get_post_meta( $entry->ID );

        foreach ( $entry_meta as $meta_key => $meta_value_array ) {
            // We are only interested in our kss_ prefixed meta keys
            if ( strpos( $meta_key, 'kss_' ) === 0 ) {
                $question_key = str_replace( 'kss_', '', $meta_key );
                // meta_value_array is an array, we take the first value (only value in our case)
                // Handle potential serialization if checkboxes are stored as serialized arrays
                $answer_value = $meta_value_array[0];
                if (is_serialized($answer_value)) {
                    $answer_value = unserialize($answer_value);
                }
                if (is_array($answer_value)) { // If it's an array (e.g., from checkboxes)
                    foreach($answer_value as $single_answer) {
                        $all_answers[ $question_key ][] = $single_answer;
                    }
                } else {
                    $all_answers[ $question_key ][] = $answer_value;
                }
            }
        }
    }

    // --- Data preparation and HTML output will happen here ---
    // We will call functions here later to process data and generate HTML

    ob_start();
    ?>
    <div class="site-survey-results-container">
        <?php // translators: %d: number of survey entries. ?>
        <h2><?php printf( esc_html__( 'Site Evaluation Results (%d entries)', 'simple-survey-builder' ), $total_entries ); ?></h2>

        <?php
        global $kss_survey_questions_config; // Ensure this is populated by define_survey_variables
        if ( empty( $kss_survey_questions_config ) ) {
        // This message is more for the site admin/developer if config is missing
            echo '<p>' . esc_html__( 'Error: The survey questions configuration is not available for displaying results.', 'simple-survey-builder' ) . '</p>';
        } else {
            foreach ( $kss_survey_questions_config as $q_key => $q_data ) {
                // Ensure $q_data has expected keys like 'label' and 'type' to avoid notices
                if ( !isset($q_data['label']) || !isset($q_data['type']) ) {
                    // error_log("Simple Survey Builder: Question data for key '{$q_key}' is incomplete.");
                    continue; // Skip this question if essential data is missing
                }
                try {
                    echo '<div class="survey-question-result">';
                    // The 'label' comes from JSON configuration.
                    echo '<h3>' . esc_html( $q_data['label'] ) . '</h3>';

                    if ( isset( $all_answers[ $q_key ] ) ) {
                        $current_question_answers = $all_answers[ $q_key ];

                        // Assuming 'bar_chart', 'radio', 'checkbox' types are intended for charts
                        // and 'text_list' for text answers.
                        // The 'type' in $q_data here refers to the *display type* for results,
                        // which might be different from the 'form_type' in the form rendering.
                        // You might need to adjust this logic based on how results 'type' is defined in your JSON.
                        if ( $q_data['type'] === 'bar_chart' || $q_data['type'] === 'radio' || $q_data['type'] === 'checkbox' ) {
                            // Ensure kss_prepare_data_for_bar_chart function is defined and handles data correctly
                            $chart_js_data = kss_prepare_data_for_bar_chart( $q_data, $current_question_answers, $total_entries );
                            if ( $chart_js_data ) {
                                // Use a unique key for the JS data array, consistent with the canvas ID
                                $chart_data_key = 'chart_' . sanitize_key( $q_key ); // Sanitize key for safety
                                $charts_data_for_js[ $chart_data_key ] = $chart_js_data;
                                // The canvas ID must match the key above
                                echo '<div class="chart-container" style="position: relative; height:300px; width:80vw; max-width:600px; margin-bottom: 20px;"><canvas id="' . esc_attr( $chart_data_key ) . '"></canvas></div>';
                            } else {
                                echo '<p>' . esc_html__( 'Insufficient data for a chart for this question.', 'simple-survey-builder' ) . '</p>';
                            }

                        } elseif ( $q_data['type'] === 'text_list' ) {
                            // Ensure kss_render_text_list_for_question function is defined
                            kss_render_text_list_for_question( $q_data, $current_question_answers );
                        } else {
                            // translators: %s is the unknown display type for a question.
                            echo '<p>' . sprintf( esc_html__( 'Unknown display type (%s) for this question.', 'simple-survey-builder' ), esc_html($q_data['type']) ) . '</p>';
                        }
                    } else {
                        echo '<p>' . esc_html__( 'No answers yet for this question.', 'simple-survey-builder' ) . '</p>';
                    }
                    echo '</div>';
                    echo '<hr>';
                } catch (Exception $e) {
                    // Log the error for debugging, don't show raw error to users
                    // error_log("Simple Survey Builder Error rendering result for question {$q_key}: " . $e->getMessage());
                    echo '<p>' . esc_html__( 'An error occurred while displaying results for this question.', 'simple-survey-builder' ) . '</p>';
                }
            } // end foreach $kss_survey_questions_config
        } // end else $kss_survey_questions_config not empty
        ?>
    </div>

    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            const chartsData = <?php echo empty($charts_data_for_js) ? '{}' : json_encode( $charts_data_for_js ); ?>;
            // console.log('Chart.js Data for Results:', chartsData); // Debugging

            for (const chartKey in chartsData) {
                if (chartsData.hasOwnProperty(chartKey)) {
                    const canvasId = chartKey; // The canvas ID is equal to the key in chartsData
                    const ctx = document.getElementById(canvasId);
                    
                    if (ctx) {
                        const chartConfigData = chartsData[chartKey];
                        console.log('Processing chart for results:', canvasId, chartConfigData); // Debugging
                        
                        new Chart(ctx, {
                            type: chartConfigData.type || 'bar', // Ensure chartConfigData provides 'type' (bar, pie etc)
                            data: {
                                labels: chartConfigData.labels,
                                datasets: [{
                                    // Make "Number of votes" translatable
                                    label: chartConfigData.datasetLabel || '<?php echo esc_js( __( 'Number of votes', 'simple-survey-builder' ) ); ?>',
                                    data: chartConfigData.data,
                                    backgroundColor: chartConfigData.backgroundColors || [ // Default colors
                                        'rgba(54, 162, 235, 0.6)', 'rgba(255, 99, 132, 0.6)',
                                        'rgba(75, 192, 192, 0.6)', 'rgba(255, 206, 86, 0.6)',
                                        'rgba(153, 102, 255, 0.6)', 'rgba(255, 159, 64, 0.6)'
                                    ],
                                    borderColor: chartConfigData.borderColors || [
                                        'rgba(54, 162, 235, 1)', 'rgba(255, 99, 132, 1)',
                                        'rgba(75, 192, 192, 1)', 'rgba(255, 206, 86, 1)',
                                        'rgba(153, 102, 255, 1)', 'rgba(255, 159, 64, 1)'
                                    ],
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false, // Important for div.chart-container sizing
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            // Ensure y-axis only shows whole numbers if it's about counts
                                            stepSize: 1, 
                                            precision: 0 
                                        }
                                    }
                                },
                                plugins: {
                                    legend: {
                                        display: chartConfigData.showLegend !== undefined ? chartConfigData.showLegend : false // Legend default off for bar charts
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                let label = context.dataset.label || '';
                                                if (label) {
                                                    label += ': ';
                                                }
                                                if (context.parsed.y !== null) {
                                                    label += context.parsed.y;
                                                    // Optional: add percentage
                                                    // const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                                    // const percentage = total > 0 ? ((context.parsed.y / total) * 100).toFixed(1) + '%' : '0%';
                                                    // label += ' (' + percentage + ')';
                                                }
                                                return label;
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    } else {
                        // console.error('Canvas element not found for chart:', canvasId); // Debugging
                    }
                }
            }
        });
    </script>
    <?php
    return ob_get_clean();
} // End kss_render_site_survey_results

/**
 * Helper function to prepare data for a bar chart.
 *
 * @param array $question_config Configuration for the question.
 * @param array $answers Array of answers for this question.
 * @param int   $total_entries Total number of survey entries (can be used for percentages if needed).
 * @return array|false Chart.js compatible data array or false on failure.
 */
function kss_prepare_data_for_bar_chart( $question_config, $answers, $total_entries ) {
    $labels = array();
    $data_counts = array();
    $answer_counts = array(); // Holds counts for each answer option

    // Determine how $answer_counts is calculated based on form_type
    if ( isset( $question_config['form_type'] ) && $question_config['form_type'] === 'checkbox' ) {
        // ---- Specific logic for Checkbox (bitwise) ----
        // $answers is an array of bitwise integers.
        // $question_config['form_options'] contains the definition of the options (value => label).
        // The 'key' (not 'value' in the typical sense for bitwise options here, but the option identifier like "technology")
        // from form_options becomes the key in $answer_counts.

        if ( empty( $question_config['form_options'] ) || ! is_array( $question_config['form_options'] ) ) {
            if (defined('WP_DEBUG') && WP_DEBUG === true) {
                // The label for the error log might come from a 'results_config' or directly from 'label'.
                // Adjust if $question_config structure is different for results label.
                $error_label = isset($question_config['label']) ? $question_config['label'] : (isset($question_config['results_config']['label']) ? $question_config['results_config']['label'] : 'Unknown question');
                error_log("kss_prepare_data_for_bar_chart: 'form_options' is missing or not an array for checkbox question: " . $error_label);
            }
            return false; // Cannot process without option definitions
        }

        // $option_keys_in_order example: ["technology", "environment", "art"]
        // These keys from form_options are used to map bits to meaningful labels.
        $option_keys_in_order = array_keys( $question_config['form_options'] );

        // Initialize counters for each checkbox option
        foreach ( $option_keys_in_order as $option_key ) {
            $answer_counts[ $option_key ] = 0;
        }

        // Decode each bitwise value and count the selections
        foreach ( $answers as $bitwise_value_from_answers ) {
            // Ensure $bitwise_value_from_answers is treated as an integer for bitwise operations
            $bitwise_value = intval( $bitwise_value_from_answers );

            // Safety check: Skip if it's not a valid integer after conversion,
            // or if the original value wasn't numeric (intval(non-numeric string) can be 0).
            // A more robust check might involve is_numeric() on $bitwise_value_from_answers before intval().
            if ( !is_numeric($bitwise_value_from_answers) && $bitwise_value === 0) { // intval of non-numeric string is 0
                 if (defined('WP_DEBUG') && WP_DEBUG === true) {
                    error_log("kss_prepare_data_for_bar_chart: Non-integer or non-numeric value found in answers for checkbox question: " . print_r($bitwise_value_from_answers, true));
                }
                continue;
            }

            foreach ( $option_keys_in_order as $index => $option_key ) {
                // Check if the bit at position $index is set
                // (1 << $index) creates a mask for the bit at the given index (0-based)
                if ( ( $bitwise_value & ( 1 << $index ) ) ) {
                    $answer_counts[ $option_key ]++;
                }
            }
        }
        // $answer_counts is now e.g.: ['technology' => 10, 'environment' => 5, 'art' => 12]

    } else {
        // ---- Standard logic for Select, Radio (and other direct value types) ----
        // Ensure all values in $answers are strings for array_count_values,
        // as keys of the counted array will be strings.
        $string_answers = array_map('strval', $answers);
        $answer_counts = array_count_values( $string_answers );
        // $answer_counts is e.g.: ['yes' => 20, 'no' => 10, 'maybe' => 5]
    }

    // Ensure all possible options (even those with 0 votes) are included in the chart
    // in the order defined in $question_config['options'] (or 'form_options' if that's where display labels are)
    // The key 'options' here should refer to the display options for the results chart.
    // This might be $question_config['form_options'] or a specific $question_config['results_options'].
    // Assuming $question_config['options'] holds the value => label mapping for chart display.
    $chart_options_source = null;
    if (isset($question_config['options']) && is_array($question_config['options'])) {
        $chart_options_source = $question_config['options'];
    } elseif (isset($question_config['form_options']) && is_array($question_config['form_options']) && $question_config['form_type'] !== 'checkbox') {
        // Fallback to form_options if 'options' isn't set, for non-checkbox types
        // where form_options keys might be 'yes', 'no' and values are "Yes", "No".
        $chart_options_source = $question_config['form_options'];
    }


    if ( $chart_options_source ) {
        foreach ( $chart_options_source as $value => $label ) {
            $labels[] = $label; // Use the full label for the chart axis
            // The key in $answer_counts will be the 'value' part of the option.
            // For checkboxes, $value is the option_key like "technology".
            // For radio/select, $value is the submitted value like "yes".
            $data_counts[] = isset( $answer_counts[ (string)$value ] ) ? $answer_counts[ (string)$value ] : 0;
        }
    } else {
        // Fallback if no specific options are defined for the chart (less ideal for bar charts,
        // as order might be inconsistent and labels might be raw values).
        // This is more suitable if the answers themselves are the categories.
        if (defined('WP_DEBUG') && WP_DEBUG === true && $question_config['form_type'] !== 'checkbox') { // Checkbox has its own handling for labels from form_options
            $error_label = isset($question_config['label']) ? $question_config['label'] : (isset($question_config['results_config']['label']) ? $question_config['results_config']['label'] : 'Unknown question');
            error_log("kss_prepare_data_for_bar_chart: No 'options' or 'form_options' found for chart labels for question: " . $error_label . ". Falling back to raw answer values.");
        }
        // For checkboxes, if 'options' or 'form_options' were missing, it would have returned false earlier.
        // This fallback is more for select/radio where options *could* be implicitly derived if not explicitly provided.
        if ($question_config['form_type'] !== 'checkbox') { // Avoid this for checkboxes as labels are already derived
            foreach ( $answer_counts as $answer_value => $count ) {
                $labels[] = $answer_value; // Use the answer value itself as the label
                $data_counts[] = $count;
            }
        }
    }
    
    if (empty($labels) || empty($data_counts)) {
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            $error_label = isset($question_config['label']) ? $question_config['label'] : (isset($question_config['results_config']['label']) ? $question_config['results_config']['label'] : 'Unknown question');
            error_log("kss_prepare_data_for_bar_chart: No labels or data counts to plot for question: " . $error_label);
        }
        return false; // No data to plot
    }

    // The datasetLabel will be used in the chart legend if the chart drawing script
    // doesn't override it. This label comes from the JSON configuration.
    // If this needs to be a translatable default string from PHP,
    // $question_config['label'] should be a key to look up in translations.
    $dataset_label_text = isset($question_config['label']) ? $question_config['label'] : 'Count'; // Fallback to 'Count'

    return array(
        'labels'       => $labels,
        'data'         => $data_counts,
        'datasetLabel' => $dataset_label_text,
        'type'         => 'bar' // Explicitly set chart type for consistency, can be overridden by $question_config if needed
        // You could also define specific colors per chart here if desired,
        // e.g., 'backgroundColors' => ['#ff0000', ...], 'borderColors' => [...]
    );
}

/**
 * Helper function to render a list of textual answers.
 *
 * @param array $question_config The configuration for the question (currently unused in this function but good for context).
 * @param array $answers         An array of answer strings.
 */
function kss_render_text_list_for_question( $question_config, $answers ) {
    $text_domain = 'simple-survey-builder'; // Your plugin's text domain

    if ( empty( $answers ) ) {
        echo '<p>' . esc_html__( 'No answers yet for this question.', $text_domain ) . '</p>';
        return;
    }

    echo '<ul class="survey-text-answers-list">';
    foreach ( $answers as $answer ) {
        $trimmed_answer = trim( $answer );
        if ( ! empty( $trimmed_answer ) ) { // Only display non-empty answers
            echo '<li>' . nl2br( esc_html( $trimmed_answer ) ) . '</li>';
        }
    }
    echo '</ul>';
}

// Einde kss_render_text_list_for_question

if ( ! function_exists( 'kss_render_announce_survey_cta' ) ) { // Check if the main rendering function exists
    /**
     * Register the shortcode [announce_survey].
     */
    function kss_register_announce_survey_shortcode() {
        add_shortcode( 'announce_survey', 'kss_render_announce_survey_cta' );
    }
    add_action( 'init', 'kss_register_announce_survey_shortcode' );

    /**
     * Generate and return the HTML, CSS, and JavaScript for the survey announcement CTA.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML, CSS, and JS for the CTA.
     */
    function kss_render_announce_survey_cta( $atts ) {
        // Default attributes (optional, for future expansion)
        $attributes = shortcode_atts( array(
            'survey_page_url' => '/koorkerk-evaluatie/', // Default URL to the survey page
            'cta_text'        => 'Help us improve! Fill out the short survey.', // Default CTA text
            'cookie_name'     => 'surveyCtaClosed',    // Name for localStorage item
        ), $atts, 'announce_survey' ); // Added shortcode tag for context

        // Ensure CSS and JS are not added multiple times if the shortcode is used more than once on a single page (unlikely, but good practice)
        // Or if it has already been added via another method.
        // A simple static flag can help here for the duration of a single page load.
        static $kss_cta_assets_rendered = false;

        $survey_page_url = esc_url( $attributes['survey_page_url'] );
        $cta_text        = esc_html( $attributes['cta_text'] );
        $cookie_name     = esc_attr( $attributes['cookie_name'] ); // Safe for use in a JS string

        ob_start(); // Start output buffering

        // HTML for the CTA
        ?>
        <div class="mobile-survey-cta" id="mobileSurveyCta-<?php echo esc_attr( $cookie_name ); // Make ID unique if cookie_name changes ?>">
            <a href="<?php echo $survey_page_url; ?>"><?php echo $cta_text; ?></a>
            <a href="#" class="close-cta" id="closeMobileSurveyCta-<?php echo esc_attr( $cookie_name ); ?>" aria-label="<?php esc_attr_e( 'Close notification', 'simple-survey-builder' ); ?>">&times;</a>
        </div>
        <?php

        // Only render CSS and JavaScript if they haven't been rendered yet on this page load.
        if ( ! $kss_cta_assets_rendered ) {
            ?>
            <style type="text/css">
                /* Hidden by default, only show on mobile */
                .mobile-survey-cta {
                    display: none; /* Hidden on desktop */
                    position: fixed;
                    bottom: 0; /* Stick to the bottom */
                    left: 0;
                    width: 100%;
                    background-color: #0073aa; /* WordPress blue, or your own theme color */
                    color: white;
                    padding: 10px;
                    text-align: center;
                    z-index: 1000; /* Ensure it's above other elements */
                    box-shadow: 0 -2px 5px rgba(0,0,0,0.1);
                }

                .mobile-survey-cta a {
                    color: white;
                    text-decoration: none;
                    font-weight: bold;
                }

                .mobile-survey-cta .close-cta {
                    position: absolute;
                    top: 5px;
                    right: 10px;
                    color: white;
                    font-size: 20px;
                    text-decoration: none;
                    line-height: 1;
                }

                /* Media Query to show it only on mobile screens */
                @media (max-width: 768px) { /* Adjust breakpoint if necessary */
                    .mobile-survey-cta {
                        display: block; /* Show on mobile */
                    }
                }
            </style>
            
            <script type="text/javascript">
            // Use an IIFE (Immediately Invoked Function Expression) to isolate scope
            (function() {
                // Make cookie_name and IDs dynamic based on shortcode attributes
                const ctaId = 'mobileSurveyCta-<?php echo $cookie_name; ?>';
                const closeButtonId = 'closeMobileSurveyCta-<?php echo $cookie_name; ?>';
                const storageKey = '<?php echo $cookie_name; // Use the sanitized cookie_name ?>'; 

                document.addEventListener('DOMContentLoaded', function() {
                    const mobileCta = document.getElementById(ctaId);
                    const closeButton = document.getElementById(closeButtonId);

                    if (!mobileCta || !closeButton) {
                        // console.warn('Survey CTA elements not found for IDs:', ctaId, closeButtonId);
                        return; // Elements not found, stop script for this instance
                    }
                    
                    // Check if the CTA was previously closed
                    if (localStorage.getItem(storageKey) === 'true') {
                        mobileCta.style.display = 'none';
                        // return; // You had this commented out, so I'm leaving it. If you want the CTA to truly stay gone, uncomment.
                    }

                    // Event listener for the close button
                    closeButton.addEventListener('click', function(e) {
                        e.preventDefault();
                        mobileCta.style.display = 'none';
                        try {
                            localStorage.setItem(storageKey, 'true');
                        } catch (err) {
                            // It's good practice to inform the user or developer if localStorage is not available,
                            // though for a non-critical feature like a CTA, a console warning is often sufficient.
                            console.warn('localStorage is not available for survey CTA state for key:', storageKey, err);
                        }
                    });
                });
            })();
            </script>
            <?php
            $kss_cta_assets_rendered = true; // Mark that assets have been rendered
        }

        return ob_get_clean(); // Stop output buffering and return the content
    }
} // End if function_exists check for kss_render_announce_survey_cta

/**
 * Shortcode function to display a list of entries for a specific survey configuration.
 *
 * @param array $atts Shortcode attributes.
 *                    'slug' => (required) The slug_identifier of the survey configuration.
 *                    'number' => (optional) Number of entries to show. Default 10.
 *                    'orderby' => (optional) Order by parameter. Default 'date'.
 *                    'order' => (optional) Order direction (ASC/DESC). Default 'DESC'.
 *                    'link_to' => (optional) Link behavior: 'admin_edit' or 'none'. Default 'admin_edit'.
 * @return string HTML output for the list.
 */
function kss_display_cpt_list_shortcode( $atts ) {
    global $kss_post_type_definition; // Used to get CPT slug after loading config
    $text_domain = 'simple-survey-builder';

    // 1. Define default attributes and merge with provided ones
    $parsed_atts = shortcode_atts( array(
        'slug'      => '',             // This is the slug_identifier for the survey configuration
        'number'    => 10,
        'orderby'   => 'date',
        'order'     => 'DESC',
        'link_to'   => 'admin_edit',
        // 'post_type' attribute is removed, as it's now derived from the 'slug' (slug_identifier)
    ), $atts, 'cpt_list' );

    // 2. Validate the provided survey configuration slug (slug_identifier)
    if ( empty( $parsed_atts['slug'] ) ) {
        // translators: %1$s is the shortcode name, %2$s is an example of correct usage.
        return '<p class="kss-error">' . sprintf(
            esc_html__( 'Error: No survey configuration slug provided for the %1$s shortcode. Use e.g., [%1$s slug="%2$s"].', $text_domain ),
            'cpt_list',
            'your_survey_slug' // Example survey configuration slug
        ) . '</p>';
    }
    $current_slug_identifier = sanitize_key( $parsed_atts['slug'] );

    // 3. Load configuration variables based on the survey configuration slug
    // This function sets up $kss_post_type_definition based on the $current_slug_identifier
    if ( ! define_survey_variables( $current_slug_identifier ) ) { // Check return value
        // define_survey_variables should ideally return false if config not found
        // translators: %s is the survey configuration slug that failed to load.
        return '<p class="kss-error">' . sprintf(
            esc_html__( 'Error: Survey configuration for slug "%s" could not be loaded for the cpt_list shortcode.', $text_domain ),
            esc_html( $current_slug_identifier )
        ) . '</p>';
    }

    // Ensure $kss_post_type_definition and the CPT slug are set after define_survey_variables()
    if ( empty( $kss_post_type_definition ) || empty( $kss_post_type_definition['slug'] ) ) {
        // This case might be redundant if define_survey_variables handles its own error reporting and returns false.
        // translators: %s is the survey configuration slug.
        return '<p class="kss-error">' . sprintf(
            esc_html__( 'Error: CPT slug not found in the configuration for survey slug "%s".', $text_domain ),
            esc_html( $current_slug_identifier )
        ) . '</p>';
    }

    // The actual CPT slug to query is now definitively from the loaded configuration
    $cpt_slug_to_query = $kss_post_type_definition['slug'];

    // 4. Prepare arguments for WP_Query
    $args = array(
        'post_type'      => sanitize_key( $cpt_slug_to_query ), // Use CPT slug from loaded config
        'posts_per_page' => intval( $parsed_atts['number'] ),
        'orderby'        => sanitize_key( $parsed_atts['orderby'] ),
        'order'          => in_array( strtoupper( $parsed_atts['order'] ), array('ASC', 'DESC') ) ? strtoupper($parsed_atts['order']) : 'DESC',
        'paged'          => get_query_var('cptpaged_'.sanitize_key($current_slug_identifier) ) ? get_query_var('cptpaged_'.sanitize_key($current_slug_identifier)) : 1, // Unique paged query_var
    );

    $query = new WP_Query( $args );
    $output = '';

    if ( $query->have_posts() ) {
        $output .= '<ul class="cpt-shortcode-list cpt-list-' . esc_attr($args['post_type']) . '">';
        while ( $query->have_posts() ) {
            $query->the_post();
            $post_id = get_the_ID();
            $title = get_the_title();

            $output .= '<li>';

            // Determine the link based on 'link_to' attribute
            if ( /*$atts['link_to'] === 'admin_edit' && */current_user_can( 'edit_post', $post_id ) ) { // Also check if user can edit
                $edit_link = get_edit_post_link( $post_id );
                if ( $edit_link ) {
                    // translators: %1$s is the post title, %2$s is the "(Edit)" label part.
                    $link_title = sprintf(esc_attr__('Edit %1$s', $text_domain), $title);
                    $edit_label = esc_html__('(Edit)', $text_domain);
                    $output .= '<h4><a href="' . esc_url( $edit_link ) . '" title="' . $link_title . '">' . esc_html( $title ) . ' ' . $edit_label . '</a></h4>';
                } else {
                    $output .= '<h4>' . esc_html( $title ) . ' <span class="kss-error-inline">(' . esc_html__('Cannot generate edit link', $text_domain) . ')</span></h4>';
                }
            } else {
                // If CPT is not public, a frontend permalink is not useful.
                // If it were public, you would use get_permalink() here.
                $output .= '<h4>' . esc_html( $title ) . '</h4>';
            }

            // Add more details here, like custom fields
            // Example for 'overall_rating' specific to the CPT identified by $kss_post_type_definition['slug']
            if ($args['post_type'] === $kss_post_type_definition['slug']) { // Ensure we are on the expected CPT for this specific meta
                $overall_rating = get_post_meta( $post_id, 'kss_overall_rating', true ); // Assuming 'kss_overall_rating' is the meta key
                if ( $overall_rating ) {
                    $output .= '<p><strong>' . esc_html__('Overall Satisfaction:', $text_domain) . '</strong> ' . esc_html( $overall_rating ) . ' / 5</p>';
                }
            } else {
                // More generic way to display a custom field (if you know the key)
                // $some_meta_key = 'your_generic_custom_field_key_for_this_cpt';
                // $some_meta_value = get_post_meta( $post_id, $some_meta_key, true );
                // if ($some_meta_value) {
                //     $output .= '<p>Extra info: ' . esc_html($some_meta_value) . '</p>';
                // }
            }
            // translators: %s is the post date.
            $output .= '<small>' . sprintf(esc_html__('Submitted on: %s', $text_domain), get_the_date()) . '</small>';
            $output .= '</li>';
        }
        $output .= '</ul>';

        // Pagination (simplified example for shortcode)
        if ($query->max_num_pages > 1) {
            $output .= '<div class="cpt-pagination">';
            // Simple previous/next links. More advanced pagination can be complex.
            // Ensure the 'cptpaged' query var is correctly handled for links.
            // The 'base' needs to correctly form the URL for subsequent pages.
            // It might need adjustment depending on whether permalinks are pretty or not.
            $current_page_url = remove_query_arg( 'cptpaged', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]" );
            $base_url = add_query_arg( 'cptpaged', '%#%', $current_page_url );


            $output .= paginate_links(array(
                'base'    => $base_url, // Use a unique query var for pagination
                'format'  => '', // Handled by 'base'
                'total'   => $query->max_num_pages,
                'current' => max(1, $args['paged']), // Use the paged arg passed to WP_Query
                'prev_text' => __('&laquo; Previous', $text_domain),
                'next_text' => __('Next &raquo;', $text_domain),
            ));
            $output .= '</div>';
        }

    } else {
        // translators: %s is the post type name.
        $output .= '<p>' . sprintf( esc_html__('No items found for %s.', $text_domain), esc_html($args['post_type']) ) . '</p>';
    }

    wp_reset_postdata();
    return $output;
}
add_shortcode( 'cpt_list', 'kss_display_cpt_list_shortcode' );

/**
 * If using pagination in the shortcode with a custom query var like 'cptpaged',
 * you need to inform WordPress about it.
 *
 * @param array $vars Array of query variables.
 * @return array Modified array of query variables.
 */
function kss_add_cpt_paged_query_var( $vars ) {
    $vars[] = 'cptpaged'; // Add the custom query variable for pagination
    return $vars;
}
add_filter( 'query_vars', 'kss_add_cpt_paged_query_var' );

//====================================================================

/**
 * Registers Custom Post Types dynamically based on a JSON configuration file.
 * This function is hooked to 'init'.
 */
add_action( 'init', 'kss_register_cpts_from_json', 0 ); // Priority 0 to run early

function kss_register_cpts_from_json() {
    // Define the path to your JSON configuration file
    // __FILE__ refers to the current file. If this is in your main plugin file, it's fine.
    // If it's in a subfolder (e.g., 'includes/cpt-loader.php'), adjust the path:
    // plugin_dir_path( dirname( __FILE__ ) ) . 'survey_configurations.json'; // For one directory up
    $json_file_path = plugin_dir_path( __FILE__ ) . 'config/survey_configurations.json';
    $main_plugin_text_domain = 'simple-survey-builder'; // Define your main plugin text domain once

    // 1. Check if the JSON file exists
    if ( ! file_exists( $json_file_path ) ) {
        // Log an error or show an admin notice if the file is critical.
        // For CPT registration, it is critical.
        if ( defined('WP_DEBUG') && WP_DEBUG === true ) {
            error_log( 'KSS Survey Error: JSON configuration file for CPTs not found at: ' . $json_file_path );
        }
        // Consider an admin notice for site administrators if the file is essential.
        add_action( 'admin_notices', function() use ($json_file_path, $main_plugin_text_domain) {
            // translators: %s is the filename of the missing configuration file.
            $message = sprintf(
                esc_html__( 'KSS Survey Plugin: Critical configuration file (%s) is missing. CPTs cannot be registered.', $main_plugin_text_domain ),
                esc_html( basename( $json_file_path ) )
            );
            echo '<div class="notice notice-error"><p>' . $message . '</p></div>';
        });
        return; // Stop further execution if the file does not exist.
    }

    // 2. Read the contents of the JSON file
    $json_content = file_get_contents( $json_file_path );
    if ( $json_content === false ) {
        if ( defined('WP_DEBUG') && WP_DEBUG === true ) {
            error_log( 'KSS Survey Error: Could not read JSON configuration file for CPT registration from: ' . $json_file_path );
        }
        add_action( 'admin_notices', function() use ($json_file_path, $main_plugin_text_domain) {
            // translators: %s is the filename of the unreadable configuration file.
            $message = sprintf(
                esc_html__( 'KSS Survey Plugin: Could not read configuration file (%s). CPTs may not be registered correctly.', $main_plugin_text_domain ),
                esc_html( basename( $json_file_path ) )
            );
            echo '<div class="notice notice-error"><p>' . $message . '</p></div>';
        });
        return; // Stop if reading fails
    }

    // 3. Decode the JSON string into a PHP array
    // The 'true' parameter ensures associative arrays.
    $all_configurations = json_decode( $json_content, true );

    // 4. Check for JSON decode errors
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        if ( defined('WP_DEBUG') && WP_DEBUG === true ) {
            error_log( 'KSS Survey Error: Error decoding JSON for CPT registration: ' . json_last_error_msg() );
        }
        add_action( 'admin_notices', function() use ($main_plugin_text_domain) {
            // translators: %s is the JSON error message.
            $message = sprintf(
                esc_html__( 'KSS Survey Plugin: Error decoding CPT configuration file. JSON error: %s.', $main_plugin_text_domain ),
                esc_html( json_last_error_msg() )
            );
            echo '<div class="notice notice-error"><p>' . $message . '</p></div>';
        });
        return; // Stop on JSON parse error
    }

    // 5. Check if the configuration data is an array and not empty
    if ( empty( $all_configurations ) || ! is_array( $all_configurations ) ) {
        if ( defined('WP_DEBUG') && WP_DEBUG === true ) {
            error_log( 'KSS Survey Error: JSON CPT configuration is empty or not a valid array after decoding.' );
        }
        add_action( 'admin_notices', function() use ($main_plugin_text_domain) {
            echo '<div class="notice notice-warning"><p>' . esc_html__( 'KSS Survey Plugin: CPT configuration is empty or invalid. No CPTs were registered from the configuration file.', $main_plugin_text_domain ) . '</p></div>';
        });
        return; // Stop if there's no (valid) data
    }

    // 6. Loop through each configuration and register the CPT
    foreach ( $all_configurations as $index => $config_item ) {
        // Check if the 'post_type_definition' key exists and is an array
        if ( !isset( $config_item['post_type_definition'] ) || !is_array( $config_item['post_type_definition'] ) ) {
            if ( defined('WP_DEBUG') && WP_DEBUG === true ) {
                error_log( 'KSS Survey Warning: post_type_definition is missing or not an array for configuration item ' . $index . ' in JSON.' );
            }
            continue; // Skip this item and go to the next
        }

        $cpt_def = $config_item['post_type_definition'];

        // Essential fields for registration (slug, name_plural, name_singular)
        if ( empty( $cpt_def['slug'] ) || empty( $cpt_def['name_plural'] ) || empty( $cpt_def['name_singular'] ) ) {
            if ( defined('WP_DEBUG') && WP_DEBUG === true ) {
                error_log( 'KSS Survey Warning: Essential CPT fields (slug, name_plural, name_singular) are missing in post_type_definition for configuration item ' . $index . ' in JSON.' );
            }
            continue; // Skip this item
        }

        $cpt_slug = sanitize_key( $cpt_def['slug'] ); // Ensure a valid CPT slug
        // Use the text_domain from JSON if provided, otherwise fallback to the main plugin's text domain.
        // This text_domain is primarily for the labels like 'name', 'singular_name' that are directly taken from JSON.
        $cpt_specific_text_domain = isset($cpt_def['text_domain']) && is_string($cpt_def['text_domain']) ? $cpt_def['text_domain'] : $main_plugin_text_domain;

        // Build the labels array dynamically based on the JSON definition.
        // These labels come from the JSON ($cpt_def) and are made translatable using _x().
        // It assumes $cpt_def['name_plural'], $cpt_def['name_singular'] etc. are English strings.
        $labels = array(
            'name'                  => _x( $cpt_def['name_plural'], 'Post type general name', $cpt_specific_text_domain ),
            'singular_name'         => _x( $cpt_def['name_singular'], 'Post type singular name', $cpt_specific_text_domain ),
            'menu_name'             => _x( isset($cpt_def['menu_name_main']) ? $cpt_def['menu_name_main'] : $cpt_def['name_plural'], 'Admin Menu text', $cpt_specific_text_domain ),
            'name_admin_bar'        => _x( isset($cpt_def['name_admin_bar']) ? $cpt_def['name_admin_bar'] : $cpt_def['name_singular'], 'Add New on Toolbar', $cpt_specific_text_domain ),
            
            // For the following labels, if a specific value is provided in JSON ($cpt_def['add_new'], etc.),
            // we assume that JSON value is already the desired (possibly pre-translated or non-standard) string and just esc_html it.
            // If not provided in JSON, we use a standard, translatable WordPress pattern using the main plugin's text domain.
            'add_new'               => isset($cpt_def['add_new']) ? esc_html($cpt_def['add_new']) : sprintf( __( 'Add New %s', $main_plugin_text_domain ), $cpt_def['name_singular'] ),
            'add_new_item'          => isset($cpt_def['add_new_item']) ? esc_html($cpt_def['add_new_item']) : sprintf( __( 'Add New %s', $main_plugin_text_domain ), $cpt_def['name_singular'] ),
            'new_item'              => isset($cpt_def['new_item']) ? esc_html($cpt_def['new_item']) : sprintf( __( 'New %s', $main_plugin_text_domain ), $cpt_def['name_singular'] ),
            'edit_item'             => isset($cpt_def['edit_item']) ? esc_html($cpt_def['edit_item']) : sprintf( __( 'Edit %s', $main_plugin_text_domain ), $cpt_def['name_singular'] ),
            'view_item'             => isset($cpt_def['view_item']) ? esc_html($cpt_def['view_item']) : sprintf( __( 'View %s', $main_plugin_text_domain ), $cpt_def['name_singular'] ),
            'all_items'             => isset($cpt_def['all_items']) ? esc_html($cpt_def['all_items']) : sprintf( __( 'All %s', $main_plugin_text_domain ), $cpt_def['name_plural'] ),
            'search_items'          => isset($cpt_def['search_items']) ? esc_html($cpt_def['search_items']) : sprintf( __( 'Search %s', $main_plugin_text_domain ), $cpt_def['name_plural'] ),
            'parent_item_colon'     => isset($cpt_def['parent_item_colon']) ? esc_html($cpt_def['parent_item_colon']) : sprintf( __( 'Parent %s:', $main_plugin_text_domain ), $cpt_def['name_singular'] ),
            // translators: %s is the plural name of the post type, lowercase.
            'not_found'             => isset($cpt_def['not_found']) ? esc_html($cpt_def['not_found']) : sprintf( __( 'No %s found.', $main_plugin_text_domain ), lcfirst($cpt_def['name_plural']) ),
            // translators: %s is the plural name of the post type, lowercase.
            'not_found_in_trash'    => isset($cpt_def['not_found_in_trash']) ? esc_html($cpt_def['not_found_in_trash']) : sprintf( __( 'No %s found in Trash.', $main_plugin_text_domain ), lcfirst($cpt_def['name_plural']) ),
            
            // For these _x labels, if the JSON provides them, use that (already English from JSON).
            // Otherwise, construct a default English string and make it translatable with context.
            // The $cpt_def['name_singular'] or $cpt_def['name_plural'] comes from JSON (assumed English).
            'featured_image'        => isset($cpt_def['featured_image']) ? esc_html($cpt_def['featured_image']) : _x( 'Featured image for this %s', 'Overrides the “Featured Image” phrase for this post type. Added in 4.3. %s is singular post type name.', $main_plugin_text_domain ),
            'set_featured_image'    => isset($cpt_def['set_featured_image']) ? esc_html($cpt_def['set_featured_image']) : _x( 'Set featured image', 'Overrides the “Set featured image” phrase for this post type. Added in 4.3', $main_plugin_text_domain ),
            'remove_featured_image' => isset($cpt_def['remove_featured_image']) ? esc_html($cpt_def['remove_featured_image']) : _x( 'Remove featured image', 'Overrides the “Remove featured image” phrase for this post type. Added in 4.3', $main_plugin_text_domain ),
            'use_featured_image'    => isset($cpt_def['use_featured_image']) ? esc_html($cpt_def['use_featured_image']) : _x( 'Use as featured image', 'Overrides the “Use as featured image” phrase for this post type. Added in 4.3', $main_plugin_text_domain ),
            // translators: %s is singular post type name from JSON.
            'archives'              => isset($cpt_def['archives']) ? esc_html($cpt_def['archives']) : sprintf( _x( '%s Archives', 'The post type archive label used in nav menus. Default “Post Archives”. Added in 4.4. %s is singular post type name.', $main_plugin_text_domain ), $cpt_def['name_singular'] ),
            // translators: %s is singular post type name from JSON, lowercase.
            'insert_into_item'      => isset($cpt_def['insert_into_item']) ? esc_html($cpt_def['insert_into_item']) : sprintf( _x( 'Insert into %s', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post). Added in 4.4. %s is singular post type name, lowercase.', $main_plugin_text_domain ), lcfirst($cpt_def['name_singular']) ),
            // translators: %s is singular post type name from JSON, lowercase.
            'uploaded_to_this_item' => isset($cpt_def['uploaded_to_this_item']) ? esc_html($cpt_def['uploaded_to_this_item']) : sprintf( _x( 'Uploaded to this %s', 'Overrides the “Uploaded to this post” phrase (used when viewing media attached to a post). Added in 4.4. %s is singular post type name, lowercase.', $main_plugin_text_domain ), lcfirst($cpt_def['name_singular']) ),
            // translators: %s is plural post type name from JSON, lowercase.
            'filter_items_list'     => isset($cpt_def['filter_items_list']) ? esc_html($cpt_def['filter_items_list']) : sprintf( _x( 'Filter %s list', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/”Filter pages list”. Added in 4.4. %s is plural post type name, lowercase.', $main_plugin_text_domain ), lcfirst($cpt_def['name_plural']) ),
            // translators: %s is plural post type name from JSON.
            'items_list_navigation' => isset($cpt_def['items_list_navigation']) ? esc_html($cpt_def['items_list_navigation']) : sprintf( _x( '%s list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/”Pages list navigation”. Added in 4.4. %s is plural post type name.', $main_plugin_text_domain ), $cpt_def['name_plural'] ),
            // translators: %s is plural post type name from JSON.
            'items_list'            => isset($cpt_def['items_list']) ? esc_html($cpt_def['items_list']) : sprintf( _x( '%s list', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/”Pages list”. Added in 4.4. %s is plural post type name.', $main_plugin_text_domain ), $cpt_def['name_plural'] ),
             // Add a new default label if not provided by JSON for 'item_updated'. Added in WP 5.0
            'item_updated'          => isset($cpt_def['item_updated']) ? esc_html($cpt_def['item_updated']) : sprintf( __( '%s updated.', $main_plugin_text_domain ), $cpt_def['name_singular'] ),

        );

        // Default CPT arguments, with overrides from JSON if present
        // You can add more $args here and set them conditionally based on $cpt_def
        $args = array(
            'labels'             => $labels,
            'public'             => isset($cpt_def['public']) ? (bool)$cpt_def['public'] : false, // Default: false
            'publicly_queryable' => isset($cpt_def['publicly_queryable']) ? (bool)$cpt_def['publicly_queryable'] : $args['public'] ?? false, // Default: value of 'public'
            'show_ui'            => isset($cpt_def['show_ui']) ? (bool)$cpt_def['show_ui'] : true, // Default: true (you want to see them in admin)
            'show_in_menu'       => isset($cpt_def['show_in_menu']) ? (is_string($cpt_def['show_in_menu']) ? $cpt_def['show_in_menu'] : (bool)$cpt_def['show_in_menu']) : true, // Default: true. Can also be a slug.
            'query_var'          => isset($cpt_def['query_var']) ? $cpt_def['query_var'] : true, // Default: true ( CPT slug ) // Usually boolean true or string
            'rewrite'            => isset($cpt_def['rewrite']) ? $cpt_def['rewrite'] : array( 'slug' => $cpt_slug ), // Default: true (uses CPT slug). Can be an array for more control.
            'capability_type'    => isset($cpt_def['capability_type']) ? $cpt_def['capability_type'] : 'post', // Default: 'post'
            'has_archive'        => isset($cpt_def['has_archive']) ? $cpt_def['has_archive'] : false, // Default: false. Can be string for slug.
            'hierarchical'       => isset($cpt_def['hierarchical']) ? (bool)$cpt_def['hierarchical'] : false, // Default: false
            'menu_position'      => isset($cpt_def['menu_position']) ? (int)$cpt_def['menu_position'] : null, // Default: null (WordPress determines)
            'menu_icon'          => isset($cpt_def['menu_icon']) ? $cpt_def['menu_icon'] : 'dashicons-feedback', // Default icon
            'supports'           => isset($cpt_def['supports']) && is_array($cpt_def['supports']) ? $cpt_def['supports'] : array( 'title', 'editor', 'custom-fields' ), // Default supports
            'show_in_rest'       => isset($cpt_def['show_in_rest']) ? (bool)$cpt_def['show_in_rest'] : false, // Default: false (for Gutenberg/REST API)
            // Add any other CPT arguments you want to be configurable here
        );
        
        // If 'public' is true, and 'publicly_queryable' is not explicitly set, WordPress defaults 'publicly_queryable' to the value of 'public'.
        // Same for 'show_ui' and 'show_in_nav_menus' (if not set, defaults to value of 'public').
        // 'show_in_rest' defaults to false unless 'public' is true and it's a new CPT.
        // It's good to be explicit for clarity if values differ from 'public'.

        // Before registering, check if the CPT doesn't already exist (can happen e.g., due to themes/other plugins)
        if ( ! post_type_exists( $cpt_slug ) ) {
            if (!kss_check_cpt_slug_is_registered( $cpt_slug, __FUNCTION__ )){
                register_post_type( $cpt_slug, $args );
                if ( defined('WP_DEBUG') && WP_DEBUG === true ) {
                    // error_log( 'KSS Survey Info: CPT "' . $cpt_slug . '" successfully registered.' ); // Example, non-translatable log
            }
        }
        } else {
            if ( defined('WP_DEBUG') && WP_DEBUG === true ) {
                error_log( 'KSS Survey Warning: CPT "' . $cpt_slug . '" already existed and was not re-registered by this plugin.' );  // Example, non-translatable log
            }
        }
    } // End foreach loop through configurations
} // End function kss_register_cpts_from_json

?>
