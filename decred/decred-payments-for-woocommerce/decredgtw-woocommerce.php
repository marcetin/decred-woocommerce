<?php
/*


Plugin Name: Decred Payments for WooCommerce
Plugin URI: https://marcetin.com/
Description: Decred Payments for WooCommerce plugin allows you to accept payments with Decred tokens for physical and digital products at your WooCommerce-powered online store.
Version: 1.0
Author: marcetin
License: GNU General Public License 2.0 (GPL) http://www.gnu.org/licenses/gpl.html

*/


// Include everything
include (dirname(__FILE__) . '/wcdecred-include-all.php');

//---------------------------------------------------------------------------
// Add hooks and filters

// create custom plugin settings menu
add_action( 'admin_menu',                   'WCDECRED_create_menu' );

register_activation_hook(__FILE__,          'WCDECRED_activate');
register_deactivation_hook(__FILE__,        'WCDECRED_deactivate');
register_uninstall_hook(__FILE__,           'WCDECRED_uninstall');

add_filter ('cron_schedules',               'WCDECRED__add_custom_scheduled_intervals');
add_action ('WCDECRED_cron_action',             'WCDECRED_cron_job_worker');     // Multiple functions can be attached to 'WCDECRED_cron_action' action

WCDECRED_set_lang_file();
//---------------------------------------------------------------------------

//===========================================================================
// activating the default values
function WCDECRED_activate()
{
    global  $g_wcdecred__config_defaults;

    $wcdecred_default_options = $g_wcdecred__config_defaults;

    // This will overwrite default options with already existing options but leave new options (in case of upgrading to new version) untouched.
    $wcdecred_settings = WCDECRED__get_settings ();

    foreach ($wcdecred_settings as $key=>$value)
    	$wcdecred_default_options[$key] = $value;

    update_option (WCDECRED_SETTINGS_NAME, $wcdecred_default_options);

    // Re-get new settings.
    $wcdecred_settings = WCDECRED__get_settings ();

    // Create necessary database tables if not already exists...
    WCDECRED__create_database_tables ($wcdecred_settings);

    //----------------------------------
    // Setup cron jobs

    if ($wcdecred_settings['enable_soft_cron_job'] && !wp_next_scheduled('WCDECRED_cron_action'))
    {
    	$cron_job_schedule_name = strpos($_SERVER['HTTP_HOST'], 'ttt.com')===FALSE ? $wcdecred_settings['soft_cron_job_schedule_name'] : 'seconds_30';
    	wp_schedule_event(time(), $cron_job_schedule_name, 'WCDECRED_cron_action');
    }
    //----------------------------------

}
//---------------------------------------------------------------------------
// Cron Subfunctions
function WCDECRED__add_custom_scheduled_intervals ($schedules)
{
	$schedules['seconds_30']     = array('interval'=>30,     'display'=>__('Once every 30 seconds'));     // For testing only.
	$schedules['minutes_1']      = array('interval'=>1*60,   'display'=>__('Once every 1 minute'));
	$schedules['minutes_2.5']    = array('interval'=>2.5*60, 'display'=>__('Once every 2.5 minutes'));
	$schedules['minutes_5']      = array('interval'=>5*60,   'display'=>__('Once every 5 minutes'));

	return $schedules;
}
//---------------------------------------------------------------------------
//===========================================================================

//===========================================================================
// deactivating
function WCDECRED_deactivate ()
{
    // Do deactivation cleanup. Do not delete previous settings in case user will reactivate plugin again...

   //----------------------------------
   // Clear cron jobs
   wp_clear_scheduled_hook ('WCDECRED_cron_action');
   //----------------------------------
}
//===========================================================================

//===========================================================================
// uninstalling
function WCDECRED_uninstall ()
{
    $wcdecred_settings = WCDECRED__get_settings();

    if ($wcdecred_settings['delete_db_tables_on_uninstall'])
    {
        // delete all settings.
        delete_option(WCDECRED_SETTINGS_NAME);

        // delete all DB tables and data.
        WCDECRED__delete_database_tables ();
    }
}
//===========================================================================

//===========================================================================
function WCDECRED_create_menu()
{

    // create new top-level menu
    // http://www.fileformat.info/info/unicode/char/e3f/index.htm
    add_menu_page (
        __('Woo Decred', WCDECRED_I18N_DOMAIN),                    // Page title
        __('Decred', WCDECRED_I18N_DOMAIN),                        // Menu Title - lower corner of admin menu
        'administrator',                                        // Capability
        'wcdecred-settings',                                        // Handle - First submenu's handle must be equal to parent's handle to avoid duplicate menu entry.
        'WCDECRED__render_general_settings_page',                   // Function
        plugins_url('/images/decred_16x.svg', __FILE__)                // Icon URL
        );

    add_submenu_page (
        'wcdecred-settings',                                        // Parent
        __("WooCommerce Decred Payments Gateway", WCDECRED_I18N_DOMAIN),                   // Page title
        __("General Settings", WCDECRED_I18N_DOMAIN),               // Menu Title
        'administrator',                                        // Capability
        'wcdecred-settings',                                        // Handle - First submenu's handle must be equal to parent's handle to avoid duplicate menu entry.
        'WCDECRED__render_general_settings_page'                    // Function
        );
}
//===========================================================================

//===========================================================================
// load language files
function WCDECRED_set_lang_file()
{
    # set the language file
    $currentLocale = get_locale();
    if(!empty($currentLocale))
    {
        $moFile = dirname(__FILE__) . "/lang/" . $currentLocale . ".mo";
        if (@file_exists($moFile) && is_readable($moFile))
        {
            load_textdomain(WCDECRED_I18N_DOMAIN, $moFile);
        }

    }
}
//===========================================================================

