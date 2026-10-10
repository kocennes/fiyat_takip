@extends('portal.ninja2020.layout.clean')
@section('meta_title', $__env->yieldContent('title'))

@section('body')
    <div class="grid lg:grid-cols-3 mx-6 md:mx-0">
        <div class="col-span-3 h-screen flex">
            <div class="m-auto flex-col items-center">
                @if(isset($company) && !is_null($company))
                    <div>
                        <img src="{{ \App\Http\ViewComposers\PortalBranding::logo($company) }}"
                             class="mx-auto border-b border-gray-100 h-18 pb-4" alt="{{ $company->present()->name() }}">
                    </div>
                @else
                    <div>
                        <img src="{{ \App\Http\ViewComposers\PortalBranding::defaultLogo() }}"
                             class="mx-auto border-b border-gray-100 h-18 pb-4" alt="{{ \App\Http\ViewComposers\PortalBranding::PRODUCT_NAME }}">
                    </div>
                @endif
            <div class="m-auto flex-col items-center mt-4">
                <span class="flex items-center text-2xl mt-4">
                    @yield('code') — @yield('message')
                </span>

                <a class="button-link text-sm mt-4" href="{{ url(request()->getSchemeAndHttpHost() . '/client') }}">
                    {{ ctrans('texts.back_to', ['url' => parse_url(request()->getHttpHost())['host'] ?? request()->getHttpHost()]) }}
                </a>
            </div>
        </div>
    </div>
@endsection


