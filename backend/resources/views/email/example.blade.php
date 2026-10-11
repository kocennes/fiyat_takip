@component('email.template.master', ['design' => 'light'])

@slot('header')
    @component('email.components.header', ['logo' => asset('brand/bisavunma-logo.png'), 'company_name' => 'BISAVUNMA Fiyat Takip'])
    @endcomponent

@endslot

@slot('greeting')
    {{ ctrans('texts.hello') }}
@endslot

{{ ctrans('texts.bis_example_email_body') }}

@component('email.components.button', ['url' => url('/'), 'show_link' => true])
    {{ ctrans('texts.account_login') }}
@endcomponent

@slot('signature')
    {{ ctrans('texts.email_signature') }} {{ ctrans('texts.bis_email_from') }}
@endslot

@endcomponent
