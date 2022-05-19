! function () {
    var e = zenkipay_payment_args.cb_url,
        n = zenkipay_payment_args.cancel_url,
        a = zenkipay_payment_args.total_amount,
        t = zenkipay_payment_args.currency,
        p = zenkipay_payment_args.items,
        r = zenkipay_payment_args.order_id,
        o = zenkipay_payment_args.country,
        s = {
            style: {
                shape: "square",
                theme: "light"
            },
            zenkipayKey: zenkipay_payment_args.zenkipay_key,
            purchaseData: {
                country: o,
                currency: t,
                amount: 1 * a,
                items: p
            }
        };

    function y(n) {
        jQuery.post(e, n).success((function (e) {
            var n = JSON.parse(e).redirect_url;
            setTimeout(i, 1e3, n)
        }))
    }

    function i(e) {
        location.href = e
    }
    zenkiPay.openModal(s, (function (e, a, t) {
        var p = {
                done: function (e) {
                    e.complete = "1", y(e)
                },
                cancel: function (e) {
                    setTimeout(i, 1e3, n)
                }
            },
            o = {
                order_id: r,
                complete: ""
            };
        e && e.postMsgType && "error" === e.postMsgType ? (o.complete = "0", y(o)) : t && t.postMsgType && p[t.postMsgType] && p[t.postMsgType](o)
    }))
}();
