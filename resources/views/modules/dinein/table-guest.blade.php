{{--
    Cột phụ của bàn: khách (gắn theo số điện thoại, ưu đãi của khách, tặng ưu đãi lần sau)
    và sự cố khi phục vụ. Khách không phải giữ gì: lần sau tra lại theo số điện thoại.
--}}
@php($guestErrors = $errors->getBag('guest-dialog'))
@php($incidentErrors = $errors->getBag('incident-dialog'))
@php($giftErrors = $errors->getBag('gift-dialog'))
@php($customer = $table->customer)
@php($offers = $customer ? \App\Models\CustomerOffer::where('customer_id', $customer->id)->usable()->orderBy('id')->get() : collect())

<section class="panel stack guest-panel">
    <div class="panel-head">
        <h2>{{ __('dinein.guest') }}</h2>
        @if ($customer)
            @component('partials.kebab', ['label' => __('dinein.guest_actions')])
                <button type="button" data-open-dialog="guest-dialog">{{ __('dinein.guest_change') }}</button>
                @can('dinein.offers')
                    <button type="button" data-open-dialog="gift-dialog">{{ __('dinein.gift') }}</button>
                @endcan
                <form method="post" action="{{ route('tenant.admin.dinein.tables.guest.remove', $table) }}" data-confirm-danger
                      data-confirm-title="{{ __('dinein.guest_remove') }}?" data-confirm-ok="{{ __('dinein.guest_remove') }}" data-confirm="{{ __('dinein.guest_remove_confirm') }}">
                    @csrf
                    @method('delete')
                    <button class="is-danger" type="submit">{{ __('dinein.guest_remove') }}</button>
                </form>
            @endcomponent
        @else
            <button class="btn btn-quiet btn-sm" type="button" data-open-dialog="guest-dialog">{{ __('dinein.guest_add') }}</button>
        @endif
    </div>

    @if ($customer)
        <p class="guest-name">
            <strong>{{ $customer->name }}</strong>
            <a href="tel:{{ $customer->phone }}">{{ $customer->phone }}</a>
            <small class="row-note">{{ __('dinein.guest_visits', ['count' => \App\Modules\DineIn\Models\DiningTable::where('customer_id', $customer->id)->count()]) }}</small>
        </p>
        @if ($offers->isNotEmpty())
            <div class="offer-box">
                <strong>{{ __('dinein.offers_title') }}</strong>
                <ul class="rows">
                    @foreach ($offers as $offer)
                        <li>
                            <span class="row-name">{{ $offer->summary() }}</span>
                            @if ($table->discount_type === null)
                                @can('dinein.close')
                                    <form method="post" action="{{ route('tenant.admin.dinein.tables.offer', [$table, $offer]) }}"
                                          data-confirm-title="{{ __('dinein.offer_apply') }}?" data-confirm-ok="{{ __('dinein.offer_apply') }}" data-confirm="{{ __('dinein.offer_apply_confirm') }}">
                                        @csrf
                                        <button class="btn btn-sm" type="submit">{{ __('dinein.offer_apply') }}</button>
                                    </form>
                                @endcan
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @else
        <p class="row-note">{{ __('dinein.guest_none') }}</p>
    @endif
</section>

<section class="panel stack incident-panel">
    <div class="panel-head">
        <h2>{{ __('dinein.incidents') }}</h2>
        <button class="btn btn-quiet btn-sm" type="button" data-open-dialog="incident-dialog">{{ __('dinein.incident_add') }}</button>
    </div>
    @if ($table->incidents->isNotEmpty())
        <ul class="rows">
            @foreach ($table->incidents as $incident)
                <li>
                    <span class="row-name">
                        <span class="badge badge-warn">{{ $incident->kindLabel() }}</span>
                        <span class="incident-note">{{ $incident->note }}</span>
                        <small>{{ __('dinein.incident_meta', ['time' => $incident->created_at->format('H:i'), 'name' => $incident->creator?->name ?? '—']) }}</small>
                    </span>
                </li>
            @endforeach
        </ul>
    @endif
</section>

{{-- Gắn khách: số điện thoại bắt buộc vì lần sau tra theo số này. --}}
<dialog id="guest-dialog" @if ($guestErrors->any()) data-auto-open @endif aria-labelledby="guest-title">
    <form method="post" action="{{ route('tenant.admin.dinein.tables.guest', $table) }}" class="stack">
        @csrf
        <h2 id="guest-title">{{ $customer ? __('dinein.guest_change') : __('dinein.guest_add') }}</h2>
        <div class="field">
            <label for="guest-phone">{{ __('reservation.customer_phone') }}@include('partials.required')</label>
            <input id="guest-phone" name="phone" type="tel" value="{{ old('phone') }}" required autocomplete="off">
            @if ($guestErrors->has('phone'))<small class="error">{{ $guestErrors->first('phone') }}</small>@endif
        </div>
        <div class="field">
            <label for="guest-name">{{ __('reservation.customer_name') }}@include('partials.required')</label>
            <input id="guest-name" name="name" value="{{ old('name') }}" maxlength="255" required>
            @if ($guestErrors->has('name'))<small class="error">{{ $guestErrors->first('name') }}</small>@endif
        </div>
        <div class="dialog-foot">
            <button class="btn" type="submit">{{ __('ui.common.save') }}</button>
            <button class="btn btn-quiet" type="button" data-close-dialog>{{ __('ui.common.cancel') }}</button>
        </div>
    </form>
</dialog>

<dialog id="incident-dialog" @if ($incidentErrors->any()) data-auto-open @endif aria-labelledby="incident-title">
    <form method="post" action="{{ route('tenant.admin.dinein.tables.incidents', $table) }}" class="stack">
        @csrf
        <h2 id="incident-title">{{ __('dinein.incident_add') }}</h2>
        <div class="field">
            <label for="incident-kind">{{ __('dinein.incident_kind') }}@include('partials.required')</label>
            <select id="incident-kind" name="kind" required>
                @foreach (\App\Modules\DineIn\Models\Incident::KINDS as $kind)
                    <option value="{{ $kind }}" @selected(old('kind') === $kind)>{{ __('dinein.incident_kinds.'.$kind) }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="incident-note">{{ __('dinein.incident_note') }}@include('partials.required')</label>
            <textarea id="incident-note" name="note" rows="3" maxlength="1000" required placeholder="{{ __('dinein.incident_note_example') }}">{{ old('note') }}</textarea>
            @if ($incidentErrors->has('note'))<small class="error">{{ $incidentErrors->first('note') }}</small>@endif
        </div>
        <div class="dialog-foot">
            <button class="btn" type="submit">{{ __('ui.common.save') }}</button>
            <button class="btn btn-quiet" type="button" data-close-dialog>{{ __('ui.common.cancel') }}</button>
        </div>
    </form>
</dialog>

@if ($customer)
    @can('dinein.offers')
        <dialog id="gift-dialog" @if ($giftErrors->any()) data-auto-open @endif aria-labelledby="gift-title">
            <form method="post" action="{{ route('tenant.admin.dinein.tables.gift', $table) }}" class="stack gift-form">
                @csrf
                <div>
                    <h2 id="gift-title">{{ __('dinein.gift') }}</h2>
                    <small class="row-note">{{ __('dinein.gift_lead') }}</small>
                </div>
                @php($giftType = old('type', 'percent'))
                <div class="segmented" role="radiogroup" aria-label="{{ __('dinein.gift_type') }}">
                    <label><input type="radio" name="type" value="percent" @checked($giftType === 'percent')> <span>{{ __('dinein.gift_percent') }}</span></label>
                    <label><input type="radio" name="type" value="amount" @checked($giftType === 'amount')> <span>{{ __('dinein.gift_amount') }}</span></label>
                </div>
                <div data-gift="percent">
                    @include('partials.number-stepper', ['name' => 'percent', 'label' => __('dinein.gift_percent_value'), 'value' => 10, 'min' => 1, 'max' => 100, 'suffix' => '%'])
                </div>
                <div data-gift="amount">
                    @include('partials.number-stepper', ['name' => 'thousands', 'label' => __('dinein.gift_amount_value'), 'value' => 20, 'min' => 1, 'max' => 999999, 'suffix' => __('takeaway.thousands_suffix')])
                </div>
                <div class="field">
                    <label for="gift-reason">{{ __('dinein.gift_reason') }}@include('partials.required')</label>
                    <input id="gift-reason" name="reason" value="{{ old('reason', $table->incidents->first()?->kindLabel()) }}" maxlength="300" required placeholder="{{ __('dinein.gift_reason_example') }}">
                    @if ($giftErrors->has('reason'))<small class="error">{{ $giftErrors->first('reason') }}</small>@endif
                </div>
                @include('partials.date-picker', ['name' => 'expires_on', 'label' => __('dinein.gift_expires'), 'value' => today()->addDays(90)->toDateString(), 'min' => today()->toDateString(), 'placeholder' => __('dinein.gift_expires_none'), 'withYear' => true])
                <div class="dialog-foot">
                    <button class="btn" type="submit">{{ __('dinein.gift') }}</button>
                    <button class="btn btn-quiet" type="button" data-close-dialog>{{ __('ui.common.cancel') }}</button>
                </div>
            </form>
        </dialog>
    @endcan
@endif
