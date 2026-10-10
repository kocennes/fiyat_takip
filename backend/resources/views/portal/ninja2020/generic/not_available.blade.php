@extends('portal.ninja2020.layout.clean')
@section('meta_title', ctrans('texts.error'))

@section('body')

    <div class="flex h-screen">
        <div class="m-auto md:w-1/2 lg:w-1/2">
            <div class="flex flex-col items-center">
                
                @if(isset($passed_company) && !is_null($passed_company))
                    <div>
                        <img src="{{ \App\Http\ViewComposers\PortalBranding::logo($passed_company) }}"
                             class="mx-auto border-b border-gray-100 h-18 pb-4" alt="{{ $passed_company->present()->name() }}">
                    </div>
                @else
                    <div>
                        <img src="{{ \App\Http\ViewComposers\PortalBranding::defaultLogo() }}"
                             class="mx-auto border-b border-gray-100 h-18 pb-4" alt="{{ \App\Http\ViewComposers\PortalBranding::PRODUCT_NAME }}">
                    </div>
                @endif
                <h1 class="text-center text-3xl mt-10">{{ ctrans("texts.entity_removed_title") }}</h1>
                <p class="text-center opacity-75 mt-10">{{ ctrans('texts.entity_removed') }}</p>
            </div>
        </div>
    </div>

@stop

@push('footer')

@endpush
