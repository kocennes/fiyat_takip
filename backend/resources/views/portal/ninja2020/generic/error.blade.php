@extends('portal.ninja2020.layout.clean')
@section('meta_title', ctrans('texts.confirmation'))

@section('body')

    <div class="flex h-screen">
        <div class="m-auto md:w-1/3 lg:w-1/2">
            <div class="flex flex-col items-center">

                @if($company)
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

                <h1 class="text-center text-3xl mt-10">{{ $title }}</h1>
                <p class="text-center opacity-75">{{ $notification }}</p>
            </div>
        </div>
    </div>

@stop

@push('footer')

@endpush
