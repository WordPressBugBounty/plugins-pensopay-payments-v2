<?php

class Pensopay_Payment_Link_Email extends WC_Email {
	private $ppv2_template_path;

	public function __construct() {
		$this->customer_email = true;
		$this->id             = 'pensopay_payments_payment_link';
		$this->title          = __( 'Payment link created', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN );
		$this->description    = __( 'This e-mail is sent upon manual payment link creation by a shop admin.', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN );
		$this->template_html  = 'emails/customer-pensopay-payment-link.php';
		$this->template_plain = 'emails/plain/customer-pensopay-payment-link.php';
		$this->placeholders   = [
			'{site_title}'   => $this->get_blogname(),
			'{order_date}'   => '',
			'{order_number}' => '',
			'{payment_link}' => '',
		];

		$this->template_base      = PPV2_PATH . 'templates/woocommerce/';
		$this->ppv2_template_path = PPV2_PATH . 'templates/woocommerce/';
		// Triggers for this email.

		// Call parent constructor.
		parent::__construct();
	}

	/**
	 * Trigger the sending of this email.
	 *
	 * @param WC_Order $order
	 * @param null $payment_link
	 */
	public function trigger( $payment_link, WC_Order $order ) {
		$this->setup_locale();

		$this->object                         = $order;
		$this->placeholders['{order_date}']   = wc_format_datetime( $this->object->get_date_created() );
		$this->placeholders['{order_number}'] = $this->object->get_order_number();
		$this->placeholders['{payment_link}'] = $payment_link;
		$this->recipient                      = $this->object->get_billing_email();

		if ( $payment_link && $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	/**
	 * Get content html.
	 *
	 * @access public
	 * @return string
	 */
	public function get_content_html() {
		return wc_get_template_html( $this->template_html, [
			'order'         => $this->object,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => false,
			'plain_text'    => false,
			'email'         => $this,
			'payment_link'  => $this->placeholders['{payment_link}'],
		], '', $this->ppv2_template_path );
	}

	/**
	 * Get content plain.
	 *
	 * @access public
	 * @return string
	 */
	public function get_content_plain() {
		return wc_get_template_html( $this->template_plain, [
			'order'         => $this->object,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => false,
			'plain_text'    => true,
			'email'         => $this,
			'payment_link'  => $this->placeholders['{payment_link}'],
		], '', $this->ppv2_template_path );
	}

	/**
	 * Initialise settings form fields.
	 */
	public function init_form_fields(): void {
		$this->form_fields = [
			'enabled'    => [
				'title'   => __( 'Enable/Disable', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'default' => 'yes',
			],
			'subject'    => [
				'title'       => __( 'Subject', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'type'        => 'text',
				'desc_tip'    => true,
				/* translators: %s: list of placeholders */
				'description' => sprintf( __( 'Available placeholders: %s', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ), '<code>{site_title}, {order_date}, {order_number}</code>' ),
				'placeholder' => $this->get_default_subject(),
				'default'     => '',
			],
			'heading'    => [
				'title'       => __( 'Email heading', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'type'        => 'text',
				'desc_tip'    => true,
				/* translators: %s: list of placeholders */
				'description' => sprintf( __( 'Available placeholders: %s', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ), '<code>{site_title}, {order_date}, {order_number}</code>' ),
				'placeholder' => $this->get_default_heading(),
				'default'     => '',
			],
			'email_type' => [
				'title'       => __( 'Email type', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'type'        => 'select',
				'description' => __( 'Choose which format of email to send.', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ),
				'default'     => 'html',
				'class'       => 'email_type wc-enhanced-select',
				'options'     => $this->get_email_type_options(),
				'desc_tip'    => true,
			],
		];
	}

	/**
	 * Get email subject.
	 *
	 * @return string
	 * @since  3.1.0
	 */
	public function get_default_subject() {
		return __( 'Payment link for your order ({order_number})', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN );
	}

	/**
	 * Get email heading.
	 *
	 * @return string
	 * @since  3.1.0
	 */
	public function get_default_heading() {
		return __( 'This is your payment link', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN );
	}
}
