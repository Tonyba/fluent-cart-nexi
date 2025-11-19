
window.addEventListener("fluent_cart_load_payments_nexi_gateway", function (e) {


    const submitButton = window.fluentcart_checkout_vars?.submit_button;

    const gatewayContainer = document.querySelector('.fluent-cart-checkout_embed_payment_container_nexi_gateway');
    const translations = window.fct_your_gateway_data?.translations || {};

    function $t(string) {
        return translations[string] || string;
    }


    fetch(e.detail.paymentInfoUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-WP-Nonce": e.detail.nonce },
        credentials: "include"
    }).then(async resp => {

        resp = await resp.json();
        console.log(resp);

        gatewayContainer.innerHTML = '<p>Paga in tutta sicurezza con carta di credito, debito e prepagata tramite Nexi.</p>' + resp.intent.cards_fragment;

        // Enable the checkout button
        e.detail.paymentLoader.enableCheckoutButton(submitButton.text);

    }).catch(e => {
        console.log(e);
    });

    // OR if you need to integrate with a third-party SDK:
    // loadYourGatewaySDK(e.detail.paymentInfoUrl, e.detail.nonce, e.detail.form, e.detail.paymentLoader);
});

function openTabWithPost(url, data) {
    // 1. Create a new, temporary form element.
    const form = document.createElement('form');
    form.style.display = 'none'; // Hide the form
    form.method = 'POST';
    form.action = url;

    // 2. Add the data as hidden input fields.
    for (const key in data) {
        if (data.hasOwnProperty(key)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = data[key];
            form.appendChild(input);
        }
    }

    // 3. Append the form to the document body and submit it.
    document.body.appendChild(form);
    form.submit();

    // 4. Remove the form after submission (optional, but good practice).
    document.body.removeChild(form);
}

window.addEventListener("fluent_cart_payment_next_action_nexi", function (e) {

    const response = e.detail.response || {};
    const fields = response.fields;
    const target_url = response.target_url;

    openTabWithPost(target_url, fields);

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