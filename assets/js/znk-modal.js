(() => {
    //URLs
    var callbackUrl = zenkipay_payment_args.cb_url,
        cancelUrl = zenkipay_payment_args.cancel_url;

    //ORDER
    var totalAmount = zenkipay_payment_args.total_amount,
        currency = zenkipay_payment_args.currency,
        items = zenkipay_payment_args.items,
        storeOrderId = zenkipay_payment_args.order_id,
        country = zenkipay_payment_args.country;

    //COMMOS
    var zenkipayKey = zenkipay_payment_args.zenkipay_key;

    var purchaseData = {
        country: country,
        currency: currency,
        amount: totalAmount * 1,
        items: items,
    };

    var purchaseOptions = {
        style: {
            shape: 'square',
            theme: 'light',
        },
        zenkipayKey: zenkipayKey,
        purchaseData,
    };

    zenkiPay.openModal(purchaseOptions, handleZenkipayEvents);

    function handleZenkipayEvents(error, data, details) {
        const events = {
            'done': (data) => {
                data.complete = '1';
                sendPaymentRequestResponse(data);
            },
            'cancel': (data) => {
                setTimeout(redirectTo, 1000, cancelUrl);
            }
        };

        const dataRequest = {
            order_id: storeOrderId,
            complete: ''
        };

        if (error && error.postMsgType && error.postMsgType === 'error') {
            dataRequest.complete = '0'
            sendPaymentRequestResponse(dataRequest);
        } else if (details && details.postMsgType && events[details.postMsgType]) {
            events[details.postMsgType](dataRequest);
        }
    }

    function sendPaymentRequestResponse(data) {
        jQuery
            .post(callbackUrl, data)
            .success((result) => {
                const response = JSON.parse(result);
                const redirectUrl = response.redirect_url;
                setTimeout(redirectTo, 1000, redirectUrl);
            });
    };

    function redirectTo(url) {
        location.href = url;
    }
})()
