// assets/js/mercantil-checkout.js
(function($) {
    'use strict';

    class MercantilCheckout {
        constructor() {
            this.init();
        }

        init() {
            this.setupValidation();
            this.setupFormHandling();
        }

        setupValidation() {
            // Validación en tiempo real
            $('form.checkout').on('change', '#mercantil-card-number, #mercantil-card-expiry, #mercantil-card-cvc, #mercantil-card-holder', function() {
                MercantilCheckout.validateField($(this));
            });

            // Validación antes de enviar
            $('form.checkout').on('checkout_place_order_mercantil_gateway', function() {
                return MercantilCheckout.validateAllFields();
            });
        }

        setupFormHandling() {
            // Mostrar/ocultar campos según método de pago
            $('form.checkout').on('change', 'input[name="payment_method"]', function() {
                MercantilCheckout.toggleMercantilFields($(this).val() === 'mercantil_gateway');
            });

            // Inicializar estado
            MercantilCheckout.toggleMercantilFields($('input[name="payment_method"]:checked').val() === 'mercantil_gateway');
        }

        static toggleMercantilFields(show) {
            const $form = $('.mercantil-checkout-form');
            if (show) {
                $form.slideDown(300);
            } else {
                $form.slideUp(300);
            }
        }

        static validateField($field) {
            const fieldId = $field.attr('id');
            let isValid = true;
            let errorMessage = '';

            switch (fieldId) {
                case 'mercantil-card-number':
                    const cardNumber = $field.val().replace(/\s/g, '');
                    isValid = /^\d{13,19}$/.test(cardNumber);
                    errorMessage = 'Número de tarjeta inválido';
                    break;

                case 'mercantil-card-expiry':
                    isValid = /^(0[1-9]|1[0-2])\/20\d{2}$/.test($field.val());
                    errorMessage = 'Formato de fecha inválido (MM/AAAA)';
                    break;

                case 'mercantil-card-cvc':
                    isValid = /^\d{3,4}$/.test($field.val());
                    errorMessage = 'CVV inválido';
                    break;

                case 'mercantil-card-holder':
                    isValid = /^[VE]\d+$/i.test($field.val());
                    errorMessage = 'Cédula inválida (V/E + números)';
                    break;
            }

            MercantilCheckout.setFieldStatus($field, isValid, errorMessage);
            return isValid;
        }

        static validateAllFields() {
            const fields = [
                '#mercantil-card-number',
                '#mercantil-card-expiry', 
                '#mercantil-card-cvc',
                '#mercantil-card-holder'
            ];

            let allValid = true;

            fields.forEach(selector => {
                const $field = $(selector);
                if ($field.is(':visible')) {
                    const isValid = MercantilCheckout.validateField($field);
                    if (!isValid) {
                        allValid = false;
                    }
                }
            });

            if (!allValid) {
                MercantilCheckout.showGeneralError('Por favor corrige los errores en el formulario de pago.');
            }

            return allValid;
        }

        static setFieldStatus($field, isValid, errorMessage) {
            // Remover estados anteriores
            $field.removeClass('mercantil-valid mercantil-invalid');
            $field.next('.mercantil-field-error').remove();

            if (isValid) {
                $field.addClass('mercantil-valid');
            } else {
                $field.addClass('mercantil-invalid');
                if (errorMessage && $field.val()) {
                    $field.after(`<span class="mercantil-field-error">${errorMessage}</span>`);
                }
            }
        }

        static showGeneralError(message) {
            // Remover notificación anterior
            $('.mercantil-checkout-error').remove();

            // Agregar nueva notificación
            $('.woocommerce-notices-wrapper').prepend(
                `<div class="woocommerce-error mercantil-checkout-error">${message}</div>`
            );

            // Scroll al error
            $('html, body').animate({
                scrollTop: $('.mercantil-checkout-error').offset().top - 100
            }, 500);
        }
    }

    // Inicializar cuando el documento esté listo
    $(document).ready(function() {
        new MercantilCheckout();
    });

})(jQuery);