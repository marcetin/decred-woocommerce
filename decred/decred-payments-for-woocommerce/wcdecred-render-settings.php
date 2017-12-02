<?php
/*
Decred Payments for WooCommerce
https://marcetin.com/
*/

// Include everything
include (dirname(__FILE__) . '/wcdecred-include-all.php');

//===========================================================================
function WCDECRED__render_general_settings_page ()   { WCDECRED__render_settings_page   ('general'); }
//function WCDECRED__render_advanced_settings_page ()  { WCDECRED__render_settings_page   ('advanced'); }
//===========================================================================

//===========================================================================
function WCDECRED__render_settings_page ($menu_page_name)
{
   if (isset ($_POST['button_update_wcdecred_settings']))
      {
      WCDECRED__update_settings ("", false);
echo <<<HHHH
<div align="center" style="background-color:#FFFFE0;padding:5px;font-size:120%;border: 1px solid #E6DB55;margin:5px;border-radius:3px;">
Settings updated!
</div>
HHHH;
      }
   else if (isset($_POST['button_reset_wcdecred_settings']))
      {
      WCDECRED__reset_all_settings (false);
echo <<<HHHH
<div align="center" style="background-color:#FFFFE0;padding:5px;font-size:120%;border: 1px solid #E6DB55;margin:5px;border-radius:3px;">
All settings reverted to all defaults
</div>
HHHH;
      }
   else if (isset($_POST['button_reset_partial_wcdecred_settings']))
      {
      WCDECRED__reset_partial_settings (false);
echo <<<HHHH
<div align="center" style="background-color:#FFFFE0;padding:5px;font-size:120%;border: 1px solid #E6DB55;margin:5px;border-radius:3px;">
Settings on this page reverted to defaults
</div>
HHHH;
      }
   else if (isset($_POST['validate_wcdecred-license']))
      {
      WCDECRED__update_settings ("", false);
      }

   // Output full admin settings HTML
   echo '<div class="wrap">';

   switch ($menu_page_name)
      {
      case 'general'     :
        echo     WCDECRED__GetPluginNameVersionEdition(true);
        WCDECRED__render_general_settings_page_html();
        break;

      case 'advanced'    :
        echo     WCDECRED__GetPluginNameVersionEdition(false);
        WCDECRED__render_advanced_settings_page_html();
        break;

      default            :
        break;
      }

   echo '</div>'; // wrap
}
//===========================================================================

//===========================================================================
function WCDECRED__render_general_settings_page_html ()
{
  $wcdecred_settings = WCDECRED__get_settings ();
  global $g_wcdecred__cron_script_url;

?>

    <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
      <p class="submit">
          <input type="submit" class="button-primary"    name="button_update_wcdecred_settings"        value="<?php _e('Save Changes') ?>"             />
          <input type="submit" class="button-secondary"  style="color:red;" name="button_reset_partial_wcdecred_settings" value="<?php _e('Reset settings') ?>" onClick="return confirm('Are you sure you want to reset settings on this page?');" />
      </p>
      <table class="form-table">


        <tr valign="top">
            <th scope="row">Delete all plugin-specific settings, database tables and data on uninstall:</th>
            <td>
              <input type="hidden" name="delete_db_tables_on_uninstall" value="0" /><input type="checkbox" name="delete_db_tables_on_uninstall" value="1" <?php if ($wcdecred_settings['delete_db_tables_on_uninstall']) echo 'checked="checked"'; ?> />
              <p class="description">If checked - all plugin-specific settings, database tables and data will be removed from Wordpress database upon plugin uninstall (but not upon deactivation or upgrade).</p>
            </td>
        </tr>
        
        <tr valign="top">
            <th scope="row">Funds received value expires in mins:</th>
            <td>
              <input type="hidden" name="funds_received_value_expires_in_mins" value="<?php if($wcdecred_settings['funds_received_value_expires_in_mins']!=''){echo $wcdecred_settings['funds_received_value_expires_in_mins'];}else{ echo 10; } ?>" /><input type="text" name="funds_received_value_expires_in_mins" value="<?php if($wcdecred_settings['funds_received_value_expires_in_mins']!=''){echo $wcdecred_settings['funds_received_value_expires_in_mins'];}else{ echo 10; } ?>" />
              <p class="description">received_funds_checked_at' is fresh (considered to be a valid value) if it was last checked within 'funds_received_value_expires_in_mins' minutes.</p>
            </td>
        </tr>
        
        <tr valign="top">
            <th scope="row">Assigned address expires in mins:</th>
            <td>
              <input type="hidden" name="assigned_address_expires_in_mins" value="<?php if($wcdecred_settings['assigned_address_expires_in_mins']!=''){echo $wcdecred_settings['assigned_address_expires_in_mins']; } else{ echo 120; }?>" /><input type="text" name="assigned_address_expires_in_mins" value="<?php if($wcdecred_settings['assigned_address_expires_in_mins']!=''){echo $wcdecred_settings['assigned_address_expires_in_mins']; } else{ echo 120; } ?>" />
              <p class="description">2 hours to pay for order and receive necessary number of confirmations.</p>
            </td>
        </tr>

        <tr valign="top">
            <th scope="row">Cron job type:</th>
            <td>
              <select name="enable_soft_cron_job" class="select ">
                <option <?php if ($wcdecred_settings['enable_soft_cron_job'] == '1') echo 'selected="selected"'; ?> value="1">Soft Cron (Wordpress-driven)</option>
                <option <?php if ($wcdecred_settings['enable_soft_cron_job'] != '1') echo 'selected="selected"'; ?> value="0">Hard Cron (Cpanel-driven)</option>
              </select>
              <p class="description">
                <?php if ($wcdecred_settings['enable_soft_cron_job'] != '1') echo '<p style="background-color:#FFC;color:#2A2;"><b>NOTE</b>: Hard Cron job is enabled: make sure to follow instructions below to enable hard cron job at your hosting panel.</p>'; ?>
                Cron job will take care of all regular decred payment processing tasks, like checking if payments are made and automatically completing the orders.<br />
                <b>Soft Cron</b>: - Wordpress-driven (runs on behalf of a random site visitor).
                <br />
                <b>Hard Cron</b>: - Cron job driven by the website hosting system/server (usually via CPanel). <br />
                When enabling Hard Cron job - make this script to run every 5 minutes at your hosting panel cron job scheduler:<br />
                <?php echo '<tt style="background-color:#FFA;color:#B00;padding:0px 6px;">wget -O /dev/null ' . $g_wcdecred__cron_script_url . '?hardcron=1</tt>'; ?>
                <br /><u>Note:</u> You will need to deactivate/reactivate plugin after changing this setting for it to have effect.<br />
                "Hard" cron jobs may not be properly supported by all hosting plans (many shared hosting plans has restrictions in place).
                <br />For secure, fast hosting service optimized for wordpress and 100% compatibility with WooCommerce and Decred payments we recommend <b><a href="http://hostrum.com/" target="_blank">Hostrum Hosting</a></b>.
              </p>
            </td>
        </tr>

      </table>

      <p class="submit">
          <input type="submit" class="button-primary"    name="button_update_wcdecred_settings"        value="<?php _e('Save Changes') ?>"             />
          <input type="submit" class="button-secondary"  style="color:red;" name="button_reset_partial_wcdecred_settings" value="<?php _e('Reset settings') ?>" onClick="return confirm('Are you sure you want to reset settings on this page?');" />
      </p>
    </form>
<?php
}

