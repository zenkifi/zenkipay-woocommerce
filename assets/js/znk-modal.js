jQuery(document).ready(function () {
    var $form = jQuery('form.checkout,form#order_review');

    // Zenkipay params
    var amount = zenkipay_payment_args.total_amount;
    var currency = zenkipay_payment_args.currency;
    var items = zenkipay_payment_args.items;
    var country = zenkipay_payment_args.country;
    var zenkipayKey = zenkipay_payment_args.zenkipay_key;

    var purchaseData = {
        amount,
        country,
        currency,
        items,
    };

    var purchaseOptions = {
        style: {
            shape: 'square',
            theme: 'light',
        },
        zenkipayKey,
        purchaseData,
    };

    jQuery('form#order_review').submit(function () {
        console.log('form#order_review');
        if (jQuery('input[name=payment_method]:checked').val() !== 'zenkipay') {
            return true;
        }

        openpayFormHandler();
        return false;
    });

    // Bind to the checkout_place_order event to add the token
    jQuery('form.checkout').bind('checkout_place_order', function (e) {
        console.log('form.checkout');
        if (jQuery('input[name=payment_method]:checked').val() !== 'zenkipay') {
            return true;
        }

        console.log('purchaseOptions', purchaseOptions);

        // Pass if we have a token
        if ($form.find('[name=zenkipay_order_id]').length) {
            console.log('zenkipay_order_id = true');
            return true;
        }

        openpayFormHandler();

        // Prevent the form from submitting with the default action
        return false;
    });

    function openpayFormHandler() {
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
            jQuery('#openpay_cc')
                .closest('div')
                .before('<ul style="background-color: #e2401c; color: #fff;" class="woocommerce_error woocommerce-error"><li> ERROR ' + errorMsg + '</li></ul>');
        }
    }
});
