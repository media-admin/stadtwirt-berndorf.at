(function ($) {
    const fluentAbCartWoo = {
        appVars: {},
        checkoutData: {},
        $emailField: null,
        timer: null,
        checkoutModule: null,
        isChanged: true,
        optedOut: false,
        gdprPushed: false,

        init: function () {
            if (!window.wp || !window.wp.data || !window.wp.data.select('wc/store/cart')) {
                return;
            }

            this.appVars = window.fc_ab_cart_blocks;
            var that = this;

            this.checkoutModule = window.wp.data.select('wc/store/cart');
            this.prepareCheckoutData();
            this.addGdprMessage();

            this.timer = setInterval(() => {
                this.prepareCheckoutData();
            }, 5000);
        },

        addGdprMessage: function () {
            if (!this.appVars.__gdpr_message || this.gdprPushed) {
                return;
            }

            if (!$('.wc-block-components-address-form__email').length) {
                // try after 2 seconds
                setTimeout(() => {
                    this.addGdprMessage();
                }, 2000);
                return;
            }


            // check if the gdpr message is already added
            if ($('#fc_ab_cart_gdpr_message').length) {
                return;
            }

            // create a dom element to store the gdpr message
            let dom = document.createElement('p');
            dom.id = 'fc_ab_cart_gdpr_message';

            let classes = ['wc-block-components-checkout-step__description', 'fc-ab-cart-gdpr-message'];
            classes.forEach(function (c) {
                dom.classList.add(c);
            });

            dom.style.marginTop = '10px';
            dom.style.marginBottom = '0';

            dom.innerHTML = "<label style='font-size: small; font-weight: normal;'>" + this.appVars.__gdpr_message + "</label>";

            // append the gdpr message to the email input
            $('.wc-block-components-address-form__email').after(dom);

            this.gdprPushed = true;

            this.handleNoThanks();
        },

        handleNoThanks: function () {
            const linkBtn = $('#fc_ab_cart_gdpr_message a#fc_ab_opt_out');

            if (!linkBtn.length) {
                return;
            }

            var that = this;

            linkBtn.on('click', function (e) {
                e.preventDefault();
                linkBtn.css('cursor', 'not-allowed');
                // send ajax request to set the gdpr cookie.
                const data = {
                    action: 'fc_ab_cart_skip_track_data',
                    _nonce: that.appVars.nonce
                };

                that.ajaxRequest(data, that.appVars.wc_no_thanks_url)
                    .then(response => {
                        if (response.message) {
                            $('#fc_ab_cart_gdpr_message').empty().append("<span style='font-size: small'>" + response.message + "</span>").delay(2000).fadeOut();
                        }
                        this.optedOut = true;
                        clearInterval(that.timer);
                    })
                    .catch(error => {
                        $('#fc_ab_cart_gdpr_message').empty().append("<span style='font-size: small'>" + error.response?.message || 'Failed to optout' + "</span>").delay(2000).fadeOut();
                        console.log(error);
                    });
            });
        },

        ajaxRequest: function (data = {}, url = null) {
            if (!url) {
                url = this.appVars.ajaxurl;
            }
            data.query_timestamp = Date.now();
            return new Promise((resolve, reject) => {
                // create ajax post request using jquery
                $.ajax({
                    type: 'POST',
                    url: url,
                    data: data,
                    success: function (response) {
                        resolve(response);
                    },
                    error: function (error) {
                        reject(error);
                    }
                });
            });
        },

        prepareCheckoutData: function () {
            var checkoutData = this.checkoutModule.getCartData();
            let isSameAddress = window.wp.data.select('wc/store/checkout').getUseShippingAsBilling();
            let shippingAddress = checkoutData.shippingAddress;
            let billingAddress = checkoutData.billingAddress;

            let data = {
                order_comments: window.wp.data.select('wc/store/checkout').getOrderNotes(),
                billingAddress: billingAddress,
                shippingAddress: shippingAddress,
                differentShipping: isSameAddress ? 'no' : 'yes',
                billing_email: billingAddress.email
            };

            let email = data.billing_email;
            if (!email || !this.validateEmail(email)) {
                return false;
            }

            // filter out empty values
            data = Object.fromEntries(Object.entries(data).filter(([_, v]) => v));

            // check if the data has been changed or not
            if (JSON.stringify(this.checkoutData) === JSON.stringify(data)) {
                return false;
            }

            this.checkoutData = data;

            this.updateCheckoutData();
        },

        updateCheckoutData: function () {
            if (this.optedOut) {
                return;
            }

            const data = {
                action: 'fc_ab_cart_update_cart_data',
                _nonce: this.appVars.nonce,
                __page_id: this.appVars._post_id,
                ...this.checkoutData
            };

            this.ajaxRequest(data, this.appVars.wc_ajaxurl);
        },

        validateEmail: function (email) {
            if (!email) {
                return false;
            }
            const regex = /^[\w-]+(\.[\w-]+)*@([\w-]+\.)+[a-zA-Z]{2,7}$/;
            return regex.test(email);
        }
    }

    $(document).ready(function () {
        fluentAbCartWoo.init();
    });
})
(jQuery);
