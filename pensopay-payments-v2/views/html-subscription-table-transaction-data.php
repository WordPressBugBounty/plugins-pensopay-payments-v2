<?php if ( ! defined( 'ABSPATH' ) ) {
    exit;
} ?>
<div class="woocommerce-pensopay-order-transaction-data">
    <table border="0" cellpadding="0" cellspacing="0" class="meta">
        <tr>
            <td><?php esc_attr_e( 'ID', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ) ?>:</td>
            <td>#<?php echo esc_attr( $transaction_id ) ?></td>
        </tr>
        <tr style="display:none;">
            <td><?php esc_attr_e( 'Method', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ) ?>:</td>
            <td>
				<span class="transaction-brand">
                    <img src="<?= esc_attr( $transaction_brand_logo_url ) ?>"
                         alt="<?= esc_attr( $transaction_brand ) ?>" title="<?= esc_attr( $transaction_brand ) ?>"
                         style="max-width:20px"/>
                </span>
            </td>
        </tr>
    </table>
    <div class="tags">
        <?php if ( $transaction_is_test ) : ?>
            <?php $tip_transaction_test = esc_attr__( 'This order has been paid with test card data!', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ) ?>
            <span class="tag is-test tips"
                  data-tip="<?php echo esc_attr( $tip_transaction_test ) ?>"><?php esc_attr_e( 'Test', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ) ?></span>
        <?php endif; ?>

        <?php if ( $transaction_status ): ?>
            <span class="tag is-<?php echo esc_attr( $transaction_status ) ?>">
                <?php echo esc_attr( $transaction_status ) ?>
            </span>
        <?php endif; ?>

        <?php if ( $is_cached ) : ?>
            <?php $tip_transaction_cached = esc_attr( __( 'NB: The transaction data is served from cached results. Click to view the order and update the cached data.', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ) ) ?>
            <span class="tag tips"
                  data-tip="<?php echo esc_attr( $tip_transaction_cached ) ?>"><?php esc_attr_e( 'Cached', Pensopay_Payments_V2_Gateway::TEXT_DOMAIN ) ?></span>
        <?php endif; ?>

    </div>
</div>