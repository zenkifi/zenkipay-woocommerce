jQuery(document).ready(function () {
    var $form = jQuery('form.checkout,form#order_review');

    // Zenkipay params
    var purchaseData = zenkipay_payment_args.purchase_data;
    var zenkipaySignature = zenkipay_payment_args.zenkipay_signature;
    var zenkipayKey = zenkipay_payment_args.zenkipay_key;

    var purchaseOptions = {
        style: {
            shape: 'square',
            theme: 'light',
        },
        zenkipayKey,
        purchaseData,
        signature: {
            zenkipaySignature,
        },
    };

    jQuery('form#order_review').submit(function () {
        if (jQuery('input[name=payment_method]:checked').val() !== 'zenkipay') {
            return true;
        }

        formHandler();
        return false;
    });

    // Bind to the checkout_place_order event to add the token
    jQuery('form.checkout').bind('checkout_place_order', function (e) {
        if (jQuery('input[name=payment_method]:checked').val() !== 'zenkipay') {
            return true;
        }

        // Pass if we have a token
        if ($form.find('[name=zenkipay_order_id]').length) {
            return true;
        }

        formHandler();

        // Prevent the form from submitting with the default action
        return false;
    });

    function formHandler() {
        zenkiPay.openModal(purchaseOptions, handleZenkipayEvents);
    }

    function handleZenkipayEvents(error, data, details) {
        if (!error && details.postMsgType === 'done') {
            var zenkipayOrderId = data.orderId;
            $form.append('<input type="hidden" name="zenkipay_order_id" value="' + zenkipayOrderId + '" />');
            $form.submit();
        }

        if (error && details.postMsgType === 'error') {
            var errorMsg = 'An unexpected error occurred';
            jQuery('.woocommerce_error, .woocommerce-error, .woocommerce-message, .woocommerce_message').remove();
            jQuery('#zenkipay-payment-container')
                .closest('div')
                .before('<ul style="background-color: #e2401c; color: #fff;" class="woocommerce_error woocommerce-error"><li> ERROR ' + errorMsg + '</li></ul>');
        }
    }
});
