<?php
/**
 * Helper Functions
 *
 * @package     EDD\EmailReports\Functions
 * @since       1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parse the template tags manually until parse_tags is supported
 * outside of payment emails.
 *
 * @link   https://github.com/easydigitaldownloads/Easy-Digital-Downloads/blob/eb3e7f7cacec154be1970507877d355195e09d97/includes/emails/class-edd-emails.php#L190-L201
 *
 * @param  string     $message The message to parse tags for.
 * @param  EDD_Emails $klass   The instantiated EDD_Emails class.
 *
 * @return string
 */
function edd_email_reports_render_email( $message, $klass ) {
	if ( 'report' === $klass->get_template() ) {
		$message = edd_do_email_tags( $message, 0 );
	}

	return $message;
}

/**
 * Add support for the EDD Email Reports tags.
 *
 * @param  array $tags The existing email tags.
 *
 * @return array The tags with support for email reports.
 */
function edd_email_reports_add_email_tags( $tags ) {
	return array_merge(
		$tags,
		array(
			array(
				'tag'         => 'email_report_currency',
				'description' => __( 'Adds the currency setting for the store.', 'edd-email-reports' ),
				'function'    => 'edd_email_reports_currency',
			),
			array(
				'tag'         => 'email_report_letters_of_days_in_week',
				'description' => __( 'Adds the total amount earned for the day.', 'edd-email-reports' ),
				'function'    => 'edd_email_reports_letters_of_days_in_week',
			),
			array(
				'tag'         => 'email_report_daily_total',
				'description' => __( 'Adds the total amount earned for the day.', 'edd-email-reports' ),
				'function'    => 'edd_email_reports_daily_total',
			),
			array(
				'tag'         => 'email_report_daily_transactions',
				'description' => __( 'Adds the number of transactions for the day.', 'edd-email-reports' ),
				'function'    => 'edd_email_reports_daily_transactions',
			),
			array(
				'tag'         => 'email_report_weekly_best_selling_downloads',
				'description' => __( 'Adds the total amount earned for the week.', 'edd-email-reports' ),
				'function'    => 'edd_email_reports_weekly_best_selling_downloads',
			),
			array(
				'tag'         => 'email_report_weekly_total',
				'description' => __( 'Adds the total amount earned for the week.', 'edd-email-reports' ),
				'function'    => 'edd_email_reports_weekly_total',
			),
			array(
				'tag'         => 'email_report_monthly_total',
				'description' => __( 'Adds the total amount earned for the monthly.', 'edd-email-reports' ),
				'function'    => 'edd_email_reports_monthly_total',
			),
			array(
				'tag'         => 'email_report_rolling_weekly_total',
				'description' => __( 'Adds the total amount earned for past seven days.', 'edd-email-reports' ),
				'function'    => 'edd_email_reports_rolling_weekly_total',
			),
			array(
				'tag'         => 'email_report_rolling_monthly_total',
				'description' => __( 'Adds the total amount earned for past thirty days.', 'edd-email-reports' ),
				'function'    => 'edd_email_reports_rolling_monthly_total',
			),
			array(
				'tag'         => 'email_report_cold_selling_downloads',
				'description' => __( 'Displays the least selling downloads and their last sale date.', 'edd-email-reports' ),
				'function'    => 'edd_email_reports_cold_selling_downloads',
			),
		)
	);
}

/**
 * Fetch six downloads sorted by the furthest
 * last sale date.
 *
 * @return string
 */
function edd_email_reports_cold_selling_downloads() {

	$args = array(
		'post_type'      => 'download',
		'post_status'    => 'publish',
		'posts_per_page' => - 1,
	);

	$downloads = get_posts( $args );

	$last_sale_dates = array();

	if ( ! empty( $downloads ) ) {

		foreach ( $downloads as $download ) {

			if ( function_exists( 'edd_get_orders' ) ) {
				$result = edd_get_orders( array(
					'type'       => 'sale',
					'status__in' => edd_get_gross_order_statuses(),
					'product_id' => $download->ID,
					'orderby'    => 'date_created',
					'order'      => 'DESC',
					'number'     => 1
				) );

				if ( ! empty( $result[0] ) ) {
					$last_sale_dates[ $download->post_title ] = $result[0]->date_created;
				}
			} else {
				/** @var EDD_Logging $edd_logs */
				global $edd_logs;

				$result = $edd_logs->get_connected_logs(
					array(
						'post_parent'    => $download->ID,
						'log_type'       => 'sale',
						'posts_per_page' => 1,
					)
				);

				if ( ! empty( $result ) ) {
					$last_sale_dates[ $download->post_title ] = $result[0]->post_date;
				}
			}
		}

		if ( ! empty( $last_sale_dates ) ) {

			uasort( $last_sale_dates, 'edd_email_reports_sort_cold_selling_downloads' );
			$last_sale_dates = array_slice( $last_sale_dates, 0, 6, true );

			$color_prefix = 99;

			ob_start();
			echo '<ul>';
			foreach ( $last_sale_dates as $download => $date ) :

				printf( '<li style="color: #%1$s0000; padding: 5px 0;"><span style="font-weight: bold;">%2$s</span> – Last sold <strong>%4$s ago</strong> on <strong>%3$s</strong></li>',
					$color_prefix,
					$download,
					date( 'F j, Y', strtotime( $date ) ),
					human_time_diff( strtotime( $date ) )
				);

				if ( $color_prefix > 11 ) {
					$color_prefix -= 11;
				}
			endforeach;
			echo '</ul>';

			return ob_get_clean();
		} else {
			return '<p>' . __( 'No sales found.', 'edd-email-reports' ) . '</p>';
		}

	} else {
		return '<p>' . __( 'No downloads found.', 'edd-email-reports' ) . '</p>';
	}
}

/**
 * Sort the passed array based on the furthest sale date.
 *
 * @param  int $a The A value for sorting.
 * @param  int $b the B value for sorting.
 *
 * @return [type]    [description]
 */
function edd_email_reports_sort_cold_selling_downloads( $a, $b ) {
	return strtotime( $a ) - strtotime( $b );
}

/**
 * Returns the earnings amount for the past 7 days, including today.
 *
 * @return string
 */
function edd_email_reports_rolling_weekly_total() {
	$stats = new EDD_Payment_Stats();

	return edd_currency_filter( edd_format_amount( $stats->get_earnings( 0, '6 days ago 00:00', 'now' ) ) );
}

/**
 * Get the monthly total for the email report.
 *
 * @return string A formatted currency amount.
 */
function edd_email_reports_rolling_monthly_total() {
	$stats = new EDD_Payment_Stats();

	return edd_currency_filter( edd_format_amount( $stats->get_earnings( 0, '30 days ago 00:00', 'now' ) ) );
}

/**
 * Helper function to return the letters for the days in the week.
 *
 * @return array Letters for the day in the week, to be used in PHP date functions.
 */
function edd_email_reports_letters_of_days_in_week() {
	$letters_of_days_in_week = array();

	$timestamp = time();

	for ( $i = 0; $i < 7; $i ++ ) {
		$letters_of_days_in_week[] = substr( date( 'D', $timestamp ), 0, 1 );
		$timestamp                 -= 24 * 3600;
	}

	return $letters_of_days_in_week;
}

/**
 * Returns the currency symbol for the current store
 *
 * @return string
 */
function edd_email_reports_currency() {
	return edd_currency_filter( '' );
}

/**
 * Returns the total earnings for today.
 *
 * @return string
 */
function edd_email_reports_daily_total() {
	$stats = new EDD_Payment_Stats();

	return edd_format_amount( $stats->get_earnings( 0, 'today', false ) );
}

/**
 * Returns the number of transactions for today.
 *
 * @return int
 */
function edd_email_reports_daily_transactions() {
	$stats = new EDD_Payment_Stats();

	return $stats->get_sales( false, 'today' );
}

/**
 * Gets the total earnings for the current week.
 *
 * @return string
 */
function edd_email_reports_weekly_total() {
	$stats = new EDD_Payment_Stats();

	return edd_currency_filter( edd_format_amount( $stats->get_earnings( 0, 'this_week' ) ) );
}

/**
 * Gets the total earnings for the current month
 *
 * @return string
 */
function edd_email_reports_monthly_total() {
	$stats = new EDD_Payment_Stats();

	return edd_currency_filter( edd_format_amount( $stats->get_earnings( 0, 'this_month' ) ) );
}

/**
 * Outputs a list of all products sold within the last 7 days,
 * ordered from best-selling to least-selling
 *
 * @return string
 */
function edd_email_reports_weekly_best_selling_downloads() {
	$p_query = new EDD_Payments_Query(
		array(
			'number'     => - 1,
			'start_date' => '6 days ago 00:00',
			'end_date'   => 'now',
			'status'     => 'publish', // EDD 3.0 will auto convert to 'complete'.
		)
	);

	$payments = $p_query->get_payments();
	$stats    = array();

	if ( ! empty( $payments ) ) {

		foreach ( $payments as $order ) {
			foreach ( $order->cart_details as $line_item ) {
				// Skip if this item was purchased as part of a bundle.
				if ( isset( $line_item['in_bundle'] ) ) {
					continue;
				}

				$earnings = isset( $stats[ $line_item['id'] ]['earnings'] ) ? $stats[ $line_item['id'] ]['earnings'] : 0;
				$sales    = isset( $stats[ $line_item['id'] ]['sales'] ) ? $stats[ $line_item['id'] ]['sales'] : 0;

				$stats[ $line_item['id'] ] = array(
					'name'     => $line_item['name'],
					'earnings' => $earnings + $line_item['price'],
					'sales'    => $sales + $line_item['quantity'],
				);
			}
		}

		usort( $stats, 'edd_email_reports_sort_best_selling_downloads' );
		$color = 111111;

		ob_start();
		echo '<ul>';
		foreach ( $stats as $list_item ) :

			printf( '<li style="color: #%1$s; padding: 5px 0;"><span style="font-weight: bold;">%2$s</span> – %3$s (%4$s %5$s)</li>',
				$color,
				$list_item['name'],
				edd_currency_filter( edd_format_amount( $list_item['earnings'] ) ),
				$list_item['sales'],
				_n( 'sale', 'sales', $list_item['sales'], 'edd-email-reports' )
			);

			if ( $color < 999999 ) {
				$color += 111111;
			}
		endforeach;
		echo '</ul>';

		return ob_get_clean();
	} else {
		return '<p>' . __( 'No sales found.' ) . '</p>';
	}
}

/**
 * Sorts the given stats based on the best-selling downloads first.
 *
 * @param  array $a The item being sorted.
 * @param  array $b The item being sorted against.
 *
 * @return [type]    [description]
 */
function edd_email_reports_sort_best_selling_downloads( $a, $b ) {
	return $a['earnings'] < $b['earnings'];
}

/**
 * Triggers the daily sales report email generation and sending.
 */
function edd_email_reports_send_daily_email() {

	// $message will be rendered during edd_email_message filter
	$message = '';

	// Swap out the email template before we send the email.
	add_action( 'edd_email_send_before', 'edd_email_reports_change_email_template' );

	EDD()->emails->html    = true;
	EDD()->emails->heading = sprintf( __( 'Daily Sales Report – %1$s', 'edd-email-reports' ), get_bloginfo( 'name' ) );
	EDD()->emails->send( edd_get_admin_notice_emails(), sprintf( __( 'Daily Sales Report for %1$s', 'edd-email-reports' ), get_bloginfo( 'name' ) ), $message );

	remove_action( 'edd_email_send_before', 'edd_email_reports_change_email_template' );
	remove_filter( 'edd_email_template', 'edd_email_reports_set_email_template' );
}

/**
 * Filter the email template to load the reporting template for this email.
 */
function edd_email_reports_change_email_template() {
	add_filter( 'edd_email_template', 'edd_email_reports_set_email_template' );
}

/**
 * Sets the suffix to use while looking for the email template to load.
 *
 * @param string $template_name The template name being used.
 *
 * @return string
 */
function edd_email_reports_set_email_template( $template_name ) {
	return 'report';
}

/**
 * Triggers the weekly sales report email generation and sending.
 */
function edd_email_reports_weekly_email() {
	return false;
}
