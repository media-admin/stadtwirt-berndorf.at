(function () {
    'use strict';

    if (typeof fc_ab_fct_cart === 'undefined') {
        return;
    }

    var gdprShown = false;

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function showGDPR() {
        if (gdprShown || !fc_ab_fct_cart.__gdpr_message) {
            return;
        }

        gdprShown = true;

        var gdprDiv = document.createElement('div');
        gdprDiv.id = 'fc_ab_cart_gdpr';
        gdprDiv.style.cssText = 'padding: 10px; margin: 10px 0; font-size: 13px; color: #666; background: #f9f9f9; border-radius: 4px;';
        gdprDiv.innerHTML = fc_ab_fct_cart.__gdpr_message;

        var section = document.getElementById('billing_personal_information_section');
        if (section) {
            section.appendChild(gdprDiv);
        }

        document.addEventListener('click', function (e) {
            if (!e.target.closest('#fc_ab_opt_out, .fc-ab-cart-opt-out')) {
                return;
            }

            e.preventDefault();

            var ajaxUrl = window.fluentcart_checkout_vars && window.fluentcart_checkout_vars.ajaxurl;
            if (!ajaxUrl) {
                return;
            }

            var params = new URLSearchParams();
            params.append('action', 'fc_ab_fct_cart_skip');
            params.append('_nonce', fc_ab_fct_cart.nonce);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    var el = document.getElementById('fc_ab_cart_gdpr');
                    if (el) {
                        el.style.display = 'none';
                    }
                }
            };
            xhr.send(params.toString());
        });
    }

    // Use capture phase since blur doesn't bubble
    document.addEventListener('blur', function (e) {
        if (e.target.id === 'billing_email' && e.target.value && isValidEmail(e.target.value.trim())) {
            showGDPR();
        }
    }, true);

    // Check if email is already filled (e.g., logged-in user)
    setTimeout(function () {
        var emailField = document.getElementById('billing_email');
        if (emailField && emailField.value && isValidEmail(emailField.value.trim())) {
            showGDPR();
        }
    }, 2000);
})();
