<?php
/**
 * Plugin Name:     Easy Digital Downloads - Email Reports
 * Plugin URI:      http://easydigitaldownloads.com/extension/email-reports
 * Description:     Sends a beautiful, comprehensive sales performance report once a day to the store admin.
 * Version:         1.0.2
 * Author:          Dave Kiss
 * Author URI:      http://davekiss.com
 * Text Domain:     edd-email-reports
 *
 * @package         EDD\EmailReports
 * @author          Dave Kiss
 * @copyright       Copyright (c) 2014
 */


// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;

if( !class_exists( 'EDD_Email_Reports' ) ) {

    /**
     * Main EDD_Email_Reports class
     *
     * @since       1.0.0
     */
    class EDD_Email_Reports {

        /**
         * @var         EDD_Email_Reports $instance The one true EDD_Email_Reports
         * @since       1.0.0
         */
        private static $instance;

        /**
         * Get active instance
         *
         * @access      public
         * @since       1.0.0
         * @return      object self::$instance The one true EDD_Email_Reports
         */
        public static function instance() {
          if( !self::$instance ) {
              self::$instance = new EDD_Email_Reports();
              self::$instance->setup_constants();
              self::$instance->includes();
              self::$instance->load_textdomain();
              self::$instance->hooks();
          }

          return self::$instance;
        }

        /**
         * Setup plugin constants
         *
         * @access      private
         * @since       1.0.0
         * @return      void
         */
        private function setup_constants() {
          // Plugin version
          define( 'EDD_EMAIL_REPORTS_VER', '1.0.2' );

          // Plugin path
          define( 'EDD_EMAIL_REPORTS_DIR', plugin_dir_path( __FILE__ ) );

          // Plugin URL
          define( 'EDD_EMAIL_REPORTS_URL', plugin_dir_url( __FILE__ ) );
        }

        /**
         * Include necessary files
         *
         * @access      private
         * @since       1.0.0
         * @return      void
         */
        private function includes() {
          // Include scripts
          require_once EDD_EMAIL_REPORTS_DIR . 'includes/scripts.php';
          require_once EDD_EMAIL_REPORTS_DIR . 'includes/functions.php';
        }

        /**
         * Run action and filter hooks
         *
         * @access      private
         * @since       1.0.0
         * @return      void
         */
        private function hooks() {
          // Register settings
          add_filter( 'edd_settings_emails', array( $this, 'settings' ), 1 );
          add_action( 'edd_email_reports_settings', array($this, 'edd_email_reports_add_email_report_preview') );

          // Render the email report preview
          add_action( 'template_redirect', array($this, 'edd_email_reports_display_email_report_preview') );
          add_filter( 'edd_template_paths', array($this, 'add_template_paths') );
          add_filter( 'edd_email_templates', array($this, 'add_email_report_template') );
          add_filter( 'edd_email_content_type', array($this, 'change_email_content_type'), 10, 2 );

          add_filter( 'edd_email_tags', 'edd_email_reports_add_email_tags');
          add_filter( 'edd_email_message', 'edd_email_reports_render_email', 10, 2);

          add_filter( 'edd_settings_sanitize', array($this, 'sanitize_settings'), 10, 2 );

          // Schedule cron event for daily email
          add_action( 'wp', array( $this, 'schedule_daily_email' ) );

          // Remove from cron if plugin is deactivated
          register_deactivation_hook( __FILE__, array( $this, 'unschedule_daily_email' ) );

          // Send the daily email when the cron event triggers the store action
          add_action('edd_email_reports_daily_email', 'edd_email_reports_send_daily_email' );

          // Handle licensing
          if ( class_exists( 'EDD_License' ) ) {
              $license = new EDD_License( __FILE__, 'EDD Email Reports', EDD_EMAIL_REPORTS_VER, 'Dave Kiss' );
          }
        }

        /**
         * Sanitize the values for the edd_email_reports settings
         *
         * @param  [type] $value [description]
         * @param  [type] $key   [description]
         * @return [type]        [description]
         */
        public function sanitize_settings($value, $key) {
          if ($key == 'edd_email_reports_daily_email_delivery_time') {
            global $edd_options;

            if ($edd_options['edd_email_reports_daily_email_delivery_time'] != $value) {
              wp_clear_scheduled_hook( 'edd_email_reports_daily_email' );
            }

            return intval($value);
          }
          return $value;
        }

        /**
         * Unschedule the cronjob for the daily email if plugin deactivated.
         *
         * @return [type] [description]
         */
        public function unschedule_daily_email() {
          return wp_clear_scheduled_hook( 'edd_email_reports_daily_email' );
        }

        /**
         * Schedule the daily email report in cron.
         *
         * Pass the selected setting in the EDD settings panel, but default to 18:00 local time
         *
         * @return [type] [description]
         */
        public function schedule_daily_email() {
          if ( ! wp_next_scheduled( 'edd_email_reports_daily_email' ) && ! defined('EDD_DISABLE_EMAIL_REPORTS') ) {

          $timezone_string  = ! empty( get_option('timezone_string') ) ? get_option('timezone_string') : 'UTC';
          $target_time_zone = new DateTimeZone( $timezone_string );
          $date_time = new DateTime('now', $target_time_zone);

            wp_schedule_event(
              strtotime( edd_get_option( 'edd_email_reports_daily_email_delivery_time', 1800 ) . 'GMT' . $date_time->format('P'), current_time('timestamp') ),
              'daily',
              'edd_email_reports_daily_email'
            );
          }
        }

        /**
         * Internationalization
         *
         * @access      public
         * @since       1.0.0
         * @return      void
         */
        public function load_textdomain() {
          // Set filter for language directory
          $lang_dir = EDD_EMAIL_REPORTS_DIR . '/languages/';
          $lang_dir = apply_filters( 'edd_email_reports_languages_directory', $lang_dir );

          // Traditional WordPress plugin locale filter
          $locale = apply_filters( 'plugin_locale', get_locale(), 'edd-email-reports' );
          $mofile = sprintf( '%1$s-%2$s.mo', 'edd-email-reports', $locale );

          // Setup paths to current locale file
          $mofile_local   = $lang_dir . $mofile;
          $mofile_global  = WP_LANG_DIR . '/edd-email-reports/' . $mofile;

          if( file_exists( $mofile_global ) ) {
            // Look in global /wp-content/languages/edd-email-reports/ folder
            load_textdomain( 'edd-email-reports', $mofile_global );
          } elseif( file_exists( $mofile_local ) ) {
            // Look in local /wp-content/plugins/edd-email-reports/languages/ folder
            load_textdomain( 'edd-email-reports', $mofile_local );
          } else {
            // Load the default language files
            load_plugin_textdomain( 'edd-email-reports', false, $lang_dir );
          }
        }

        /**
         * Add the custom template path for the email reporting templates.
         *
         * @param array $file_paths priority-based paths to check for templates
         */
        public function add_template_paths($file_paths) {
          $file_paths[20] = trailingslashit( plugin_dir_path(__FILE__) ) . 'templates/';
          return $file_paths;
        }

        /**
         * [add_email_report_template description]
         * @param [type] $templates [description]
         */
        public function add_email_report_template($templates) {
          $templates['report'] = __( 'Email Report Template', 'edd' );
          return $templates;
        }

        /**
         * [change_email_content_type description]
         * @return [type] [description]
         */
        public function change_email_content_type($content_type, $klass) {
          return 'text/html';
        }

        /**
         * Add settings
         *
         * @access      public
         * @since       1.0.0
         * @param       array $settings The existing EDD settings array
         * @return      array The modified EDD settings array
         */
        public function settings( $settings ) {
            $new_settings = array(
              array(
                  'id'    => 'edd_email_reports_settings',
                  'name'  => '<strong>' . __( 'Email Reports Settings', 'edd-email-reports' ) . '</strong>',
                  'desc'  => __( 'Configure EDD Email Reports Settings', 'edd-email-reports' ),
                  'type'  => 'header',
              ),
              array(
                'id' => 'edd_email_reports_daily_email_delivery_time',
                'name' => __( 'Daily Email Delivery Time', 'edd' ),
                'desc' => __( 'Select when you would like to receive your daily email report.', 'edd' ),
                'type' => 'select',
                'options' => array(
                  '1300' => __( '1:00 PM', 'edd' ),
                  '1400' => __( '2:00 PM', 'edd' ),
                  '1500' => __( '3:00 PM', 'edd' ),
                  '1600' => __( '4:00 PM', 'edd' ),
                  '1700' => __( '5:00 PM', 'edd' ),
                  '1800' => __( '6:00 PM', 'edd' ),
                  '1900' => __( '7:00 PM', 'edd' ),
                  '2000' => __( '8:00 PM', 'edd' ),
                  '2100' => __( '9:00 PM', 'edd' ),
                  '2200' => __( '10:00 PM', 'edd' ),
                  '2300' => __( '11:00 PM', 'edd' ),
                )
              ),
              array(
                  'id' => 'email_reports_settings',
                  'name' => '',
                  'desc' => '',
                  'type' => 'hook'
              ),
            );

            return array_merge( $settings, $new_settings );
        }

        /**
         * [edd_email_reports_add_email_report_preview description]
         * @return [type] [description]
         */
        public function edd_email_reports_add_email_report_preview() {
          ob_start();
          ?>
          <a href="<?php echo esc_url( add_query_arg( array( 'edd_action' => 'preview_email_report' ), home_url() ) ); ?>" class="button-secondary" target="_blank" title="<?php _e( 'Preview Email Report', 'edd' ); ?> "><?php _e( 'Preview Email Report', 'edd' ); ?></a>
          <?php
          echo ob_get_clean();
        }

        /**
         * Displays the email preview
         *
         * @since 2.1
         * @return void
         */
        public function edd_email_reports_display_email_report_preview() {

          if( empty( $_GET['edd_action'] ) ) {
            return;
          }

          if( 'preview_email_report' !== $_GET['edd_action'] ) {
            return;
          }

          if( ! current_user_can( 'manage_shop_settings' ) ) {
            return;
          }

          // $message will be rendered during edd_email_message filter
          $message = '';

          // Swip out the email template before we send the email.
          add_action( 'edd_email_header', 'edd_email_reports_change_email_template' );

          EDD()->emails->html = TRUE;
          EDD()->emails->heading = sprintf( __('Daily Sales Report – %1$s', 'edd-email-reports'), get_bloginfo('name') );

          echo EDD()->emails->build_email( $message );

          exit;

        }

    }
}

/**
 * The main function responsible for returning the one true EDD_Email_Reports
 * instance to functions everywhere
 *
 * @since       1.0.0
 * @return      \EDD_Email_Reports The one true EDD_Email_Reports
 *
 * @todo        Inclusion of the activation code below isn't mandatory, but
 *              can prevent any number of errors, including fatal errors, in
 *              situations where your extension is activated but EDD is not
 *              present.
 */
function EDD_Email_Reports_load() {
    if( ! class_exists( 'Easy_Digital_Downloads' ) ) {
        if( ! class_exists( 'EDD_Extension_Activation' ) ) {
            require_once 'includes/class.extension-activation.php';
        }

        $activation = new EDD_Extension_Activation( plugin_dir_path( __FILE__ ), basename( __FILE__ ) );
        $activation = $activation->run();
    } else {
        return EDD_Email_Reports::instance();
    }
}
add_action( 'plugins_loaded', 'EDD_Email_Reports_load' );
