<?php
/**
 * Email Body
 *
 * @author 		Easy Digital Downloads
 * @package 	Easy Digital Downloads/Templates/Emails
 * @version     2.1
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

?>

<table style="text-align: center !important; width: 100%; table-layout: fixed;">
  <tbody>
    <tr>
      <td colspan="3" style="padding: 0 0 25px;">
          <h3 style="margin: 0;"><?php echo date('F j, Y'); ?></h3>
          <p style="margin: 0;"><?php printf( __('Happy %1$s!', 'edd-email-reports'), date( 'l', current_time('timestamp') ) ); ?></p>
      </td>
    </tr>

    <tr>
      <td colspan="3" style="padding: 16px;">
        <h1 style="font-size: 48px; line-height: 1em; margin: 0; color:#4EAD61;">
          <?php if (edd_get_option('currency_position') == 'before'): ?><span style="font-size: 20px; vertical-align: super;">{email_report_currency}</span><?php endif; ?>{email_report_daily_total}<?php if (edd_get_option('currency_position') == 'after'): ?><span style="font-size: 20px; vertical-align: super;">{email_report_currency}</span><?php endif; ?>
        </h1>
        <h2 style="margin: 8px 0; color: #222;">{email_report_daily_transactions} <?php _e('orders today', 'edd-email-reports'); ?></h2>
        <h3 style="margin: 0; color: #333;">{email_report_rolling_weekly_total} <?php _e('past seven days', 'edd-email-reports'); ?></h3>
      </td>
    </tr>

    <tr>
      <td style="padding: 30px; text-align: center;"><span style="display: block; font-weight: bold;color:#4EAD61;">{email_report_weekly_total}</span><small style="display: block;"><?php _e('this week', 'edd-email-reports'); ?></small></td>
      <td style="padding: 30px; text-align: center;"><span style="display: block; font-weight: bold;color:#4EAD61;">{email_report_monthly_total}</span><small style="display: block;"><?php _e('this month', 'edd-email-reports'); ?></small></td>
      <td style="padding: 30px; text-align: center;"><span style="display: block; font-weight: bold;color:#4EAD61;">{email_report_rolling_monthly_total}</span><small style="display: block;"><?php _e('past 30 days', 'edd-email-reports'); ?></small></td>
    </tr>

    <!-- daily amount sold chart, past thirty days -->

    <tr>
      <td colspan="3" style="text-align: left !important;">
        <h3 style="margin: 0; padding-left: 40px;"><?php _e('Best-selling downloads over the past week:', 'edd-email-reports'); ?></h3>
        {email_report_weekly_best_selling_downloads}
      </td>
    </tr>

  </tbody>
</table>
