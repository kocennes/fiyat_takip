{{-- BISAVUNMA: translated messages for the compiled portal scripts (resources/js/**).
     The scripts read window.BIS_I18N[key] and fall back to their built-in text. --}}
@php
    $bis_js_keys = [
        'success',
        'bis_setup_check_failed',
        'bis_cvv_required',
        'bis_refreshing',
        'bis_btc_price_updates_in',
        'bis_payment_session_missing',
        'bis_payment_failed_try_again',
        'bis_payment_form_load_failed',
        'bis_session_missing',
        'bis_add_payment_method_failed',
        'bis_form_load_failed',
        'missing_account_holder_name',
        'provide_email',
        'invalid_expiry',
        'bis_routing_number_required',
        'bis_account_number_required',
        'bis_tokenization_failed',
        'bis_gateway_not_found_or_verified',
        'bis_payment_method_auth_issue',
        'bis_unknown_error',
        'bis_transaction_not_processed',
        'bis_accept_mandate_terms',
        'bis_accept_mandate_terms_add',
        'bis_billing_address_required_bank',
        'bis_unexpected_error',
        'bis_bank_account_payment_failed_try_other',
        'bis_unable_to_process_payment',
        'bis_bank_authorization_renew_failed',
        'bis_bank_verification_email',
        'bis_complete_bank_verification',
        'bis_payment_processing_error',
        'bis_card_authorization_error',
        'bis_square_init_failed',
        'bis_card_verification_problem',
        'bis_gateway_error_code',
    ];

    $bis_js_translations = [];

    foreach ($bis_js_keys as $bis_js_key) {
        $bis_js_value = ctrans('texts.' . $bis_js_key);

        // Leave untranslated keys out so the scripts keep their built-in text.
        if ($bis_js_value !== 'texts.' . $bis_js_key) {
            $bis_js_translations[$bis_js_key] = $bis_js_value;
        }
    }
@endphp
<script>
    window.BIS_I18N = Object.assign(window.BIS_I18N || {}, {!! json_encode($bis_js_translations, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!});
</script>
