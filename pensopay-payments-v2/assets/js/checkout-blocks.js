((wp, wc) => {
    // noinspection JSUnresolvedReference
    const {registerPaymentMethod} = wc.wcBlocksRegistry;
    const {decodeEntities} = wp.htmlEntities;
    const {createElement} = wp.element;
    // noinspection JSUnresolvedReference
    const pluginSettings = wc.wcSettings.getSetting('pensopay-gateway-plugin', {gateways: []})
    pluginSettings.gateways.forEach(gateway => {
        // noinspection JSUnresolvedReference
        const gatewaySettings = wc.wcSettings.getSetting('payment-gateway-' + gateway + '_data')
        if (!gatewaySettings) {
            return;
        }
        const ariaLabel = () => {
            return wp.hooks.applyFilters('pensopay_paymentsgw_checkout-block_label', gatewaySettings.label, gateway, gatewaySettings);
        }
        const Label = () => {
            return createElement('span', {className: 'blocks-woocommerce-pensopay-inner'},
                createElement('span', {
                    className: 'blocks-woocommerce-pensopay-inner__title'
                }, ariaLabel()),
                createElement('span', {
                    dangerouslySetInnerHTML: {__html: gatewaySettings.icon},
                    className: 'blocks-woocommerce-pensopay-inner__icons'
                }),
            );
        };
        const Content = () => {
            return decodeEntities(gatewaySettings.description)
        };
        registerPaymentMethod({
            name: gateway,
            label: Object(createElement)(Label, null),
            ariaLabel: ariaLabel(),
            content: Object(createElement)(Content, null),
            edit: Object(createElement)(Content, null),
            canMakePayment: () => true,
            paymentMethodId: gateway,
            supports: {
                features: gatewaySettings.supports,
            },
        })
    });
})(window.wp, window.wc);