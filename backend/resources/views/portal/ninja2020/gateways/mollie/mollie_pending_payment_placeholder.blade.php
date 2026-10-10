@extends('portal.ninja2020.layout.clean')
@section('meta_title', 'Mollie')

@section('body')
    <div class="grid lg:grid-cols-3">
        <div class="col-span-3 h-screen flex">
            <div class="m-auto md:w-1/2 lg:w-1/4 flex flex-col items-center">
                <img src="{{ \App\Http\ViewComposers\PortalBranding::logo($company ?? null) }}"
                     class="mx-auto border-b border-gray-100 h-18 pb-4 mb-4" alt="{{ isset($company) && !is_null($company) ? $company->present()->name() : \App\Http\ViewComposers\PortalBranding::PRODUCT_NAME }}">
                <span class="flex items-center text-2xl">
                    {{ ctrans('texts.mollie_payment_pending') }}
                 </span>

                <a class="button-link text-sm mt-2" href="{{ url(request()->getSchemeAndHttpHost() . '/client') }}">
                    {{ ctrans('texts.back_to', ['url' => parse_url(request()->getHttpHost())['host'] ?? request()->getHttpHost()]) }}
                </a>
            </div>
        </div>
    </div>
@endsection


