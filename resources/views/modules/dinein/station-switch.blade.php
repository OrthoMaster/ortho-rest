{{-- Đổi màn hình: Chuẩn bị đơn mang đi (luôn đầu) và các khu. --}}
@if ($stations->count() + ($prep ? 1 : 0) > 1)
    @component('partials.kebab', ['label' => __('dinein.switch_station'), 'trigger' => __('dinein.switch_station')])
        @if ($prep)
            <a href="{{ route('tenant.admin.dinein.screens.takeaway') }}">{{ __('dinein.prep') }}</a>
        @endif
        @foreach ($stations as $other)
            <a href="{{ route('tenant.admin.dinein.screens.show', $other) }}">{{ $other->name }}</a>
        @endforeach
    @endcomponent
@endif
