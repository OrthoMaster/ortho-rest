{{-- Một món đã gọi ở bàn: tên, tuỳ chọn, ghi chú, trạng thái và thao tác. --}}
@php($status = $line->status)
<li @class(['dine-line', 'is-cancelled' => $status === \App\Modules\DineIn\LineStatus::Cancelled])>
    <span class="row-name">
        {{ $line->quantity }} × {{ $line->name }}
        @if ($line->optionText() !== '')<small>{{ $line->optionText() }}</small>@endif
        @if ($line->note)<small class="dine-note">{{ $line->note }}</small>@endif
        <span @class(['badge', $status->badge()])>{{ $status->label() }}</span>
    </span>
    <strong class="line-total">{{ vnd($line->total()) }}</strong>
    <span class="line-tools">
        @if ($status === \App\Modules\DineIn\LineStatus::Done)
            <form method="post" action="{{ route('tenant.admin.dinein.lines.serve', $line) }}" data-confirm="{{ __('dinein.serve_confirm', ['name' => $line->name]) }}" data-confirm-ok="{{ __('dinein.serve') }}">
                @csrf
                <button class="btn btn-sm" type="submit">{{ __('dinein.serve') }}</button>
            </form>
        @endif
        @if ($status === \App\Modules\DineIn\LineStatus::New || ($status === \App\Modules\DineIn\LineStatus::Done && auth()->user()->can('dinein.cancel')))
            @component('partials.kebab', ['label' => __('dinein.line_actions')])
                <form method="post" action="{{ route('tenant.admin.dinein.lines.cancel', $line) }}" data-confirm-danger data-confirm-title="{{ __('dinein.cancel') }}" data-confirm-ok="{{ __('dinein.cancel') }}" data-confirm="{{ __('dinein.cancel_confirm', ['name' => $line->name]) }}">
                    @csrf
                    <button class="is-danger" type="submit">{{ __('dinein.cancel') }}</button>
                </form>
            @endcomponent
        @endif
    </span>
</li>
