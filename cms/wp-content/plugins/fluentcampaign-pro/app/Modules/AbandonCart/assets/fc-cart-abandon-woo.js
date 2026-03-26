console.log(window.fc_ab_cart);

(function ($) {
    const fluentAbCartWoo = {

        checkoutData: {},
        $emailField: null,
        timer: null,

        init: function () {
            this.$emailField = $('#billing_email');

            console.log(this.$emailField);

            if (!this.$emailField.length) {
                return;
            }

            this.addGdprMessage();
            var that = this;

            setTimeout(() => {
                this.prepareCheckoutData();
                console.log('Init');
                // run prepareCheckoutData every 10 seconds
                this.timer = setInterval(() => {
                    this.prepareCheckoutData();
                }, 15000);

            }, 2000);


            $(document.body).on('updated_checkout', function () {
                console.log('updated_checkout');
                that.prepareCheckoutData();
            });

            $(document).on('blur', '#billing_email, #billing_first_name, #billing_last_name, #billing_phone', function () {
                console.log('Input Blur');
                that.prepareCheckoutData();
            });

        },

        addGdprMessage: function () {
            if (!window.fc_ab_cart.__gdpr_message) {
                return;
            }

            // check if the gdpr message is already added
            if ($('#fc_ab_cart_gdpr_message').length) {
                return;
            }

            // create a dom element to store the gdpr message
            let dom = document.createElement('p');
            dom.id = 'fc_ab_cart_gdpr_message';

            let classes = ['form-row', 'form-row-wide', 'fc-ab-cart-gdpr-message'];
            classes.forEach(function (c) {
                dom.classList.add(c);
            });

            dom.innerHTML = "<label style='font-size: small; font-weight: normal;'>" + window.fc_ab_cart.__gdpr_message + "</label>";

            // append the gdpr message to the email input
            $('#billing_email').after(dom);

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
                    _nonce: window.fc_ab_cart.nonce
                };

                that.ajaxRequest(data, window.fc_ab_cart.wc_no_thanks_url)
                    .then(response => {
                        if (response.message) {
                            $('#fc_ab_cart_gdpr_message').empty().append("<span style='font-size: small'>" + response.message + "</span>").delay(2000).fadeOut();
                        }
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

            if (!this.$emailField.length) {
                return false;
            }

            // check if cursor is on the email field
            if (this.$emailField.is(':focus')) {
                return false;
            }

            let email = this.$emailField.val();
            if (!email || !this.validateEmail(email)) {
                return false;
            }

            let data = {
                billingAddress: {
                    first_name: $('#billing_first_name').val(),
                    last_name: $('#billing_last_name').val(),
                    company: $('#billing_company').val(),
                    country: $('#billing_country').val(),
                    address_1: $('#billing_address_1').val(),
                    address_2: $('#billing_address_2').val(),
                    city: $('#billing_city').val(),
                    state: $('#billing_state').val(),
                    postcode: $('#billing_postcode').val(),
                    phone: $('#billing_phone').val(),
                },
                billing_email: email,
                differentShipping: $('#ship-to-different-address-checkbox').is(':checked') ? 'yes' : 'no',
                order_comments: $('#order_comments').val(),
                shipping_method: $('input[name="shipping_method[0]"]:checked').val(),
                payment_method: $('input[name="payment_method"]:checked').val(),
            };

            if (data.differentShipping) {
                data.shippingAddress = {
                    first_name: $('#shipping_first_name').val(),
                    last_name: $('#shipping_last_name').val(),
                    company: $('#shipping_company').val(),
                    country: $('#shipping_country').val(),
                    address_1: $('#shipping_address_1').val(),
                    address_2: $('#shipping_address_2').val(),
                    city: $('#shipping_city').val(),
                    state: $('#shipping_state').val(),
                    postcode: $('#shipping_postcode').val(),
                }
            }

            // filter out empty values
            data = Object.fromEntries(Object.entries(data).filter(([_, v]) => v));

            // check if the data has been changed or not
            if (JSON.stringify(this.checkoutData) === JSON.stringify(data)) {
                return false;
            }

            this.checkoutData = data;

            this.updateCheckoutData();

            console.log(this.checkoutData);
        },

        updateCheckoutData: function () {
            const data = {
                action: 'fc_ab_cart_update_cart_data',
                _nonce: window.fc_ab_cart.nonce,
                __page_id: window.fc_ab_cart._post_id,
                ...this.checkoutData
            };

            this.ajaxRequest(data, window.fc_ab_cart.wc_ajaxurl)
                .then(response => {
                    console.log(response);
                    // do nothing here!
                });
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

})(jQuery);
