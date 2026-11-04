/* Stripe payment page: Apple Pay / Google Pay via the Express Checkout Element, cards via the Payment Element. */
(function () {
    'use strict';

    const root = document.querySelector('[data-pay]');
    if (!root || !window.Stripe) return;

    const stripe = window.Stripe(root.dataset.key);
    const clientSecret = root.dataset.secret;
    const returnUrl = root.dataset.return;
    const errorBox = root.querySelector('[data-pay-error]');
    const payButton = root.querySelector('[data-pay-button]');
    const payLabel = payButton.textContent;

    const elements = stripe.elements({
        clientSecret,
        appearance: {
            theme: 'stripe',
            variables: { colorPrimary: '#b9461b', colorText: '#1f1a17', borderRadius: '10px', fontFamily: 'Inter, system-ui, sans-serif' },
        },
        fonts: [{ cssSrc: 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap' }],
    });

    const showError = (message) => {
        errorBox.textContent = message;
        errorBox.hidden = !message;
    };

    const confirm = async () => {
        showError('');
        const { error } = await stripe.confirmPayment({ elements, clientSecret, confirmParams: { return_url: returnUrl } });
        // On success Stripe redirects to return_url, so reaching here means the payment did not go through.
        if (error) showError(error.message || 'Payment failed. Please try another method.');
    };

    // Apple Pay shows in Safari with a card in Apple Wallet; Google Pay in Chrome with a saved card.
    const express = elements.create('expressCheckout', {
        buttonType: { applePay: 'order', googlePay: 'order' },
        buttonHeight: 50,
        paymentMethods: { applePay: 'always', googlePay: 'always', link: 'never', amazonPay: 'never', paypal: 'never' },
    });
    express.on('ready', ({ availablePaymentMethods }) => {
        if (!availablePaymentMethods) root.querySelector('[data-wallets]').hidden = true;
    });
    express.on('confirm', confirm);
    express.mount('[data-express]');

    const card = elements.create('payment', {
        layout: 'tabs',
        wallets: { applePay: 'never', googlePay: 'never' }, // wallets already have their own buttons above
    });
    card.on('ready', () => { payButton.disabled = false; });
    card.mount('[data-payment-element]');

    root.querySelector('[data-card-form]').addEventListener('submit', async (event) => {
        event.preventDefault();
        payButton.disabled = true;
        payButton.textContent = 'Processing…';
        const { error: submitError } = await elements.submit();
        if (submitError) {
            showError(submitError.message);
        } else {
            await confirm();
        }
        payButton.disabled = false;
        payButton.textContent = payLabel;
    });
})();
