<?php

class Pensopay_Payments_V2_Emails_Manager extends Pensopay_Payments_V2_Abstract {
	protected static $_instance;

	/**
	 * Perform actions and filters
	 */
	public function hooks() {
		add_filter( 'woocommerce_email_classes', [ $this, 'emails' ], 10, 1 );
		add_action( 'pensopay_payments_order_action_payment_link_created', [
			$this,
			'send_customer_payment_link'
		], 1, 2 );
	}

	/**
	 * Add support for custom emails
	 */
	public function emails( $emails ) {
		require_once PPV2_PATH . 'includes/emails/types/payment-link.php';

		$emails['Pensopay_Payment_Link_Email'] = new Pensopay_Payment_Link_Email();

		return $emails;
	}

	/**
	 * Make sure the mailer is loaded in order to load e-mails.
	 */
	public function send_customer_payment_link( $payment_link, $order ) {
		/** @var Pensopay_Payment_Link_Email $mail */
		$mail = wc()->mailer()->emails['Pensopay_Payment_Link_Email'];

		if ( $mail ) {
			$mail->trigger( $payment_link, $order );
		}
	}
}