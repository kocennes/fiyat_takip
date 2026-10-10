<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4"
    id="stripe-acss-authorize">

    @if($company_gateway->getConfigField('account_id'))
        <meta name="stripe-account-id" content="{{ $company_gateway->getConfigField('account_id') }}">
        <meta name="stripe-publishable-key" content="{{ config('ninja.ninja_stripe_publishable_key') }}">
    @else
        <meta name="stripe-publishable-key" content="{{ $company_gateway->getPublishableKey() }}">
        @endif
        
    <meta name="stripe-pi-client-secret" content="{{ $pi_client_secret }}">
    <meta name="only-authorization" content="true">

    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::ACSS]) }}" method="post"
        id="server_response">
        @csrf
        <input type="hidden" name="company_gateway_id" value="{{ $company_gateway->gateway_id }}">
        <input type="hidden" name="payment_method_id" value="1">
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="is_default" id="is_default">
        <input type="hidden" name="post_auth_response" value="{{ $post_auth_response }}">
        <input type="hidden" name="one_page_checkout" value="1" />
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>
    @component('portal.ninja2020.components.general.card-element-single', ['title' => 'SEPA', 'show_title' => false])
    <p>{{ ctrans('texts.bis_acss_pad_1', ['company' => $company->present()->name()]) }}</p>
    <br>
    <p>{{ ctrans('texts.bis_acss_pad_2') }}</p>
    <br>
    <p>{{ ctrans('texts.bis_acss_pad_3', ['company' => $company->present()->name()]) }}</p>
    <br>
    <p>{{ ctrans('texts.bis_acss_pad_4') }}</p>
    <br>
    <p>{{ ctrans('texts.bis_acss_pad_5') }}</p>
    <br>
    <p>{{ ctrans('texts.bis_acss_pad_6', ['email' => $company->owner()->email]) }}</p>
    <br>
    <p>{{ ctrans('texts.bis_acss_pad_7', ['company' => $company->present()->name()]) }}</p>


    <div>
        <label for="acss-name">
            <input class="input w-full" id="acss-name" type="text"
                placeholder="{{ ctrans('texts.bank_account_holder') }}" value="{{ $client->present()->name() }}">
        </label>
        <label for="acss-email">
            <input class="input w-full" id="acss-email-address" type="email" placeholder="{{ ctrans('texts.email') }}"
                value="{{ $client->present()->email() }}">
        </label>
    </div>

    @endcomponent
    @component('portal.ninja2020.gateways.includes.pay_now', ['id' => 'authorize-acss'])
    {{ ctrans('texts.add_payment_method') }}
    @endcomponent
</div>

@assets
<script src="https://js.stripe.com/v3/"></script>
@vite('resources/js/clients/payment_methods/authorize-stripe-acss.js')
@endassets