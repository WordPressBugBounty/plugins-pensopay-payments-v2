<?php if ( ! defined( 'ABSPATH' ) ) {
    exit;
} ?>
<?php
/**
 * @var Pensopay_Api_Payment $transaction
 */
?>
<?php if ( isset( $payment ) ) : ?>

    <p class="woocommerce-pensopay-{<?php echo esc_attr( $status ); ?>}">
        <?php esc_attr_e( 'Current payment state', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ); ?>:
    </p>

    <div class="tags">
        <span class="tag is-<?php echo esc_attr( $status ); ?>"> <?php echo esc_attr( $status ); ?> </span>
    </div>
    <br/>
    <?php if ( ! empty ( $transaction_id ) && ( $payment->can( 'capture' ) || $payment->can( 'cancel' ) ) ) : ?>
        <h4><strong><?php esc_attr_e( 'Actions', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ); ?></strong></h4>
        <ul class="order_action">
            <?php if ( $payment->can( 'capture' ) ) : ?>
                <li class="pensopay-full-width">
                    <a class="button button-primary" data-action="capture"
                       data-confirm="<?php esc_attr_e( 'You are about to CAPTURE this payment', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ); ?>">
                        <?php
                        /* translators:
                            %s: payment amount
                            %s: payment currency
                        */
                        printf( esc_attr__( 'Capture Full Amount (%1$s %2$s)', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ), esc_attr( Pensopay_Payments_V2_Helpers_Price::normalize_price( $payment->amount - $payment->captured ) ), esc_attr( $payment->currency ) );
                        ?>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ( $payment->can( 'capture' ) ) : ?>
                <li class="pensopay-balance original">
                    <span class="pensopay-balance__label"><?php esc_attr_e( 'Original balance', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ); ?>:</span>
                    <span class="pensopay-balance__amount"><span
                                class="pensopay-balance__currency"> <?php echo esc_attr( $payment->currency ); ?></span><?php echo esc_attr( number_format( Pensopay_Payments_V2_Helpers_Price::normalize_price( $payment->amount ), 2, ',', '' ) ); ?></span>
                </li>
            <?php endif; ?>
            <?php if ( $payment->can( 'capture' ) ) : ?>
                <li class="pensopay-balance">
                    <span class="pensopay-balance__label"><?php esc_attr_e( 'Remaining balance', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ); ?>:</span>
                    <span class="pensopay-balance__amount"><span
                                class="pensopay-balance__currency"> <?php echo esc_attr( $payment->currency ); ?></span><?php echo esc_attr( number_format( Pensopay_Payments_V2_Helpers_Price::normalize_price( $remaining ), 2, ',', '' ) ); ?></span>
                </li>
            <?php endif; ?>
            <?php if ( $payment->can( 'capture' ) ) : ?>
                <li class="pensopay-balance last">
                    <label class="pensopay-balance__label"
                           for="pensopay-balance__amount-field"><?php esc_attr_e( 'Capture amount', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ) ?>
                        :</label><span class="pensopay-balance__amount"><span
                                class="pensopay-balance__currency"><?php echo esc_attr( $payment->currency ) ?></span><input
                                id="pensopay-balance__amount-field" type="text"
                                value="<?php echo esc_attr( number_format( Pensopay_Payments_V2_Helpers_Price::normalize_price( $remaining ), 2, ',', '' ) ); ?>"/></span>
                </li>
            <?php endif; ?>


            <?php if ( $payment->can( 'capture' ) ) : ?>
                <li class="pp-full-width">
                    <a class="button" data-action="captureAmount"
                       data-confirm="<?php esc_attr_e( 'You are about to CAPTURE this payment', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ); ?>"><?php esc_attr_e( 'Capture Specified Amount', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ); ?></a>
                </li>
            <?php endif; ?>

            <?php if ( $payment->can( 'cancel' ) && ! $payment->is( [
                            'canceled',
                            'refunded'
                    ], $payment ) ) : ?>
                <li class="pp-full-width">
                    <a class="button" data-action="cancel"
                       data-confirm="<?php esc_attr_e( 'You are about to CANCEL this payment', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ); ?>"><?php esc_attr_e( 'Cancel', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ); ?></a>
                </li>
            <?php endif; ?>
        </ul>
    <?php endif; ?>
    <p>
        <?php if ( ! empty( $transaction_id ) ): ?>
            <small>
                <?php esc_attr_e( 'Transaction ID', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ); ?>:
                <strong><?php echo esc_attr( $transaction_id ); ?></strong>
                <?php if ( ! empty( $transaction_brand ) ): ?>
                    <img style="margin-bottom: -5px;"
                         src="<?php echo $transaction_brand_logo_url  ?>"
                         width="30"/>
                <?php endif ?>
            </small>
        <?php endif ?>

        <?php if ( $is_subscription ): ?>
            <br />
            <small>
                <?php esc_attr_e( 'Subscription ID', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ); ?>:
                <strong><?php echo esc_attr( $subscription_id ); ?></strong>
            </small>
        <?php endif; ?>

        <?php if ( ! empty( $transaction_order_id ) ) : ?>
            <br/>
            <small>
                <?php esc_attr_e( 'Transaction Order ID', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ); ?>:
                <strong><?php echo esc_attr( $transaction_order_id ); ?></strong>
            </small>
        <?php endif; ?>

        <?php $payment_link = $order->get_meta( '_pensopay_payment_link' ); ?>
        <?php if ( isset( $payment_link ) && ! empty( $payment_link ) ) : ?>
            <br />
            <small>
                <?php esc_attr_e( 'Payment Link', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ); ?>:
                <br />
                <input type="text" class="pensopay-full-width" value="<?php echo esc_attr( $payment_link ) ?>" readonly/>
            </small>
        <?php endif; ?>
    </p>
<?php endif; ?>