<?php
/**
 * Customer payment link
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/customer-pensopay-payment-link.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @version 4.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php /* translators: %s: Customer first name */ ?>
	<p><?php printf( esc_html__( 'Hi %s,', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ), esc_html( $order->get_billing_first_name() ) ); ?></p>

	<p><?php echo esc_html__( 'Thank you for your order.', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ) . "\n\n"; ?></p>

	<p><?php esc_html_e( 'Click the button below to pay and complete your order.', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ); ?></p>

<?php
$ppv2_button_color = sanitize_hex_color( get_option( 'woocommerce_email_base_color' ) ) ?: '#7f54b3';
$ppv2_text_color   = wc_light_or_dark( $ppv2_button_color, '#202020', '#ffffff' );
?>
	<table align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 auto 24px;">
		<tr>
			<td align="center" bgcolor="<?php echo esc_attr( $ppv2_button_color ); ?>" style="border-radius: 4px; background-color: <?php echo esc_attr( $ppv2_button_color ); ?>;">
				<a href="<?php echo esc_url( $payment_link ); ?>" target="_blank" rel="noopener noreferrer" style="display: inline-block; padding: 10px 22px; font-size: 14px; font-weight: bold; line-height: 1.2; text-decoration: none; border-radius: 4px; color: <?php echo esc_attr( $ppv2_text_color ); ?>; background-color: <?php echo esc_attr( $ppv2_button_color ); ?>;"><?php esc_html_e( 'Pay for your order', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ); ?></a>
			</td>
		</tr>
	</table>
<?php

/*
 * @hooked WC_Emails::order_details() Shows the order details table.
 * @hooked WC_Structured_Data::generate_order_data() Generates structured data.
 * @hooked WC_Structured_Data::output_structured_data() Outputs structured data.
 * @since 2.5.0
 */
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

/*
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

/*
 * @hooked WC_Emails::customer_details() Shows customer details
 * @hooked WC_Emails::email_address() Shows email address
 */
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

?>
	<p>
		<?php esc_html_e( 'Thanks for shopping with us.', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ); ?>
	</p>
<?php

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );
