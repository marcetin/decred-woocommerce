<?php
/*
Decred Payments for WooCommerce
https://marcetin.com/
*/

//---------------------------------------------------------------------------
// Global definitions
if (!defined('WCDECRED_PLUGIN_NAME'))
  {
  define('WCDECRED_VERSION',           '1.0');

  //-----------------------------------------------
  define('WCDECRED_EDITION',           'Standard');    


  //-----------------------------------------------
  define('WCDECRED_SETTINGS_NAME',     'WCDECRED-Settings');
  define('WCDECRED_PLUGIN_NAME',       'Decred Payments for WooCommerce');   


  // i18n plugin domain for language files
  define('WCDECRED_I18N_DOMAIN',       'wcdecred');

  }
//---------------------------------------------------------------------------

//------------------------------------------
// Load wordpress for POSTback, WebHook and API pages that are called by external services directly.
if (defined('WCDECRED_MUST_LOAD_WP') && !defined('WP_USE_THEMES') && !defined('ABSPATH'))
   {
   $g_blog_dir = preg_replace ('|(/+[^/]+){4}$|', '', str_replace ('\\', '/', __FILE__)); // For love of the art of regex-ing
   define('WP_USE_THEMES', false);
   require_once ($g_blog_dir . '/wp-blog-header.php');

   // Force-elimination of header 404 for non-wordpress pages.
   header ("HTTP/1.1 200 OK");
   header ("Status: 200 OK");

   require_once ($g_blog_dir . '/wp-admin/includes/admin.php');
   }
//------------------------------------------

require_once (dirname(__FILE__) . '/includes/wcdecred_rpclib.php');
require_once (dirname(__FILE__) . '/wcdecred-cron.php');
require_once (dirname(__FILE__) . '/wcdecred-utils.php');
require_once (dirname(__FILE__) . '/wcdecred-admin.php');
require_once (dirname(__FILE__) . '/wcdecred-render-settings.php');
require_once (dirname(__FILE__) . '/wcdecred-gateway.php');

?>