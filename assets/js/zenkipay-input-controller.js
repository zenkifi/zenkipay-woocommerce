(() => {
    var e, c, d, n;
    e = document.querySelector("#woocommerce_zenkipay_testmode"),
        c = document.querySelector("#woocommerce_zenkipay_test_plugin_key"),
        d = document.querySelector("#woocommerce_zenkipay_live_plugin_key"),
        n = function () {
            e && e.checked ? (d.classList.add("znk_disabled"), c.classList.remove("znk_disabled")) : (c.classList.add("znk_disabled"), d.classList.remove("znk_disabled"))
        }, c && d && e && (n(), e.addEventListener("change", (function (e) {
            n()
        })))
})();
