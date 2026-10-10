@extends('portal.ninja2020.layout.clean')
@section('meta_title', $__env->yieldContent('title'))

@section('body')
    <div class="grid lg:grid-cols-3">
        <div class="col-span-3 h-screen flex">
            <div class="m-auto md:w-1/2 lg:w-1/3 flex flex-col items-center">
                <div>
                    <img src="{{ \App\Http\ViewComposers\PortalBranding::logo($company ?? null) }}"
                         class="mx-auto border-b border-gray-100 h-18 pb-4" alt="{{ isset($company) && !is_null($company) ? $company->present()->name() : \App\Http\ViewComposers\PortalBranding::PRODUCT_NAME }}">
                </div>

                <span class="flex items-center text-2xl mt-4">
                    @yield('code') — @yield('message')
                </span>

                @if (\App\Utils\Ninja::isSelfHost())
                    <span class="flex items-center text-1xl">
                        {{ ctrans('texts.bis_contact_admin_for_details') }}
                    </span>
                @endif

                <a class="button-link text-sm mt-2" href="{{ url(request()->getSchemeAndHttpHost() . '/client') }}">
                    {{ ctrans('texts.back_to', ['url' => parse_url(request()->getHttpHost())['host'] ?? request()->getHttpHost()]) }}
                </a>
            </div>
        </div>
    </div>
@endsection


