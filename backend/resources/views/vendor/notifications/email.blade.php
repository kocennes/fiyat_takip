@component('mail::message')
{{-- Greeting --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
@if ($level === 'error')
# {{ ctrans('texts.bis_mail_whoops') }}
@else
# {{ ctrans('texts.bis_mail_hello') }}
@endif
@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Action Button --}}
@isset($actionText)
<?php
    switch ($level) {
        case 'success':
        case 'error':
            $color = $level;
            break;
        default:
            $color = 'primary';
    }
?>
@component('mail::button', ['url' => $actionUrl, 'color' => $color])
{{ $actionText }}
@endcomponent
@endisset

{{-- Outro Lines --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Salutation --}}
@if (! empty($salutation))
{{ $salutation }}
@else
{{ ctrans('texts.bis_mail_regards') }},<br>{{ \App\Http\ViewComposers\PortalBranding::PRODUCT_NAME }}
@endif

{{-- Subcopy --}}
@isset($actionText)
@slot('subcopy')
{{ ctrans('texts.bis_mail_button_trouble', ['actiontext' => $actionText, 'actionurl' => $actionUrl]) }}
@endslot
@endisset
@endcomponent
