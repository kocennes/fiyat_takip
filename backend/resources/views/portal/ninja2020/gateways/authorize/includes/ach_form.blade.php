<div id="authorize-ach-container">
@component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.account_holder_name')])
        <input class="input w-full" id="account_holder_name" type="text" placeholder="{{ ctrans('texts.account_holder_name') }}" 
            maxlength="22" required>
@endcomponent

@component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.account_type')])
    <span class="flex items-center mr-4">
        <input class="form-radio mr-2" type="radio" value="checking" name="account_type" checked>
        <span>{{ ctrans('texts.checking') }}</span>
    </span>
    <span class="flex items-center mt-2">
        <input class="form-radio mr-2" type="radio" value="savings" name="account_type">
        <span>{{ ctrans('texts.savings') }}</span>
    </span>
    <span class="flex items-center mt-2">
        <input class="form-radio mr-2" type="radio" value="businessChecking" name="account_type">
        <span>{{ ctrans('texts.bis_business_checking') }}</span>
    </span>
@endcomponent

@component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.routing_number')])
    <input class="input w-full" id="routing_number" type="text" pattern="[0-9]{9}" 
        minlength="9" maxlength="9" inputmode="numeric" 
        oninput="this.value = this.value.replace(/[^0-9]/g, '')"
        placeholder="{{ ctrans('texts.bis_n_digits', ['count' => '9']) }}" required>
@endcomponent

@component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.account_number')])
    <input class="input w-full" id="account_number" type="text" pattern="[0-9]{1,17}" 
        minlength="1" maxlength="17" inputmode="numeric"
        oninput="this.value = this.value.replace(/[^0-9]/g, '')"
        placeholder="{{ ctrans('texts.bis_n_digits', ['count' => '1-17']) }}" required>
@endcomponent

@component('portal.ninja2020.components.general.card-element-single')
    <input type="checkbox" class="form-checkbox mr-1" id="accept-terms" required>
    <label for="accept-terms" class="cursor-pointer">{{ ctrans('texts.ach_authorization', ['company' => auth()->guard('contact')->user()->company->present()->name, 'email' => auth()->guard('contact')->user()->client->company->settings->email]) }}</label>
@endcomponent
</div>