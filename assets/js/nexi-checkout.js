
window.addEventListener("fluent_cart_load_payments_nexi_gateway", function (e) {
    const submitButton = window.fluentcart_checkout_vars?.submit_button;

    const gatewayContainer = document.querySelector('.fluent-cart-checkout_embed_payment_container_nexi_gateway');
    const translations = window.fct_your_gateway_data?.translations || {};

    function $t(string) {
        return translations[string] || string;
    }

    // Simple implementation (like COD/offline payments)
    if (gatewayContainer) {
        gatewayContainer.innerHTML = `<p>${$t('Your payment instructions here.')}</p>`;
    }



    fetch(e.detail.paymentInfoUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-WP-Nonce": e.detail.nonce },
        credentials: "include"
    }).then(async resp => {

        resp = await resp.json();
        console.log(resp);

        // Enable the checkout button
        e.detail.paymentLoader.enableCheckoutButton(submitButton.text);

    }).catch(e => {
        console.log(e);
    });

    // OR if you need to integrate with a third-party SDK:
    // loadYourGatewaySDK(e.detail.paymentInfoUrl, e.detail.nonce, e.detail.form, e.detail.paymentLoader);
});

// Example function for loading a more complex gateway SDK
function loadYourGatewaySDK(paymentInfoUrl, nonce, form, paymentLoader) {
    // Fetch payment information from server
    fetch(paymentInfoUrl, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": nonce,
        },
        credentials: 'include'
    }).then(response => response.json())
        .then(data => {
            // Initialize your gateway SDK with the data
            // When ready, enable the checkout button:
            paymentLoader.enableCheckoutButton('Pay Now');
        });
}