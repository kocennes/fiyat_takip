@component('email.template.master', ['design' => 'light', 'settings' => $settings])
    @slot('header')
        @include('email.components.header', ['logo' => asset('brand/bisavunma-logo.png'), 'company_name' => 'BISAVUNMA'])
    @endslot

    <h1>{{ ctrans('texts.invoice_paid') }}</h1>
    <p>{{ ctrans('texts.bis_invoice_payment_completed_body') }}</p>
@endcomponent
