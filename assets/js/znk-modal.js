jQuery(document).ready(function () {
    // Zenkipay params
    var purchaseData = zenkipay_payment_args.purchase_data;
    var purchaseSignature = zenkipay_payment_args.zenkipay_signature;
    var zenkipayKey = zenkipay_payment_args.zenkipay_key;
    var callbackUrl = zenkipay_payment_args.cb_url;
    var failedUrl = zenkipay_payment_args.failed_url;
    var storeOrderId = zenkipay_payment_args.order_id;

    var purchaseOptions = {
        style: {
            shape: 'square',
            theme: 'light',
        },
        zenkipayKey,
        purchaseData,
        purchaseSignature,
    };

    formHandler();

    function formHandler() {
        zenkiPay.openModal(purchaseOptions, handleZenkipayEvents);
    }

    function handleZenkipayEvents(error, data, details) {
        console.log('handleZenkipayEvents error', error);

        if (error && details.postMsgType === 'error') {
            var payload = { order_id: storeOrderId, complete: '0' };
            sendPaymentRequest(payload);
            return;
        }

        if ((!error && details.postMsgType === 'close') || details.postMsgType === 'cancel') {
            location.href = callbackUrl;
            return;
        }
    }

    function sendPaymentRequest(data) {
        jQuery.post(failedUrl, data).success((result) => {
            const response = JSON.parse(result);
            location.href = response.redirect_url;
        });
    }
});
