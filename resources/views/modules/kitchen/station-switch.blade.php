{{-- Đổi màn hình: Chuẩn bị đơn mang đi (luôn đầu) và các khu. --}}
@if ($stations->count() + ($prep ? 1 : 0) > 1)
    @component('partials.kebab', ['label' => __('kitchen.switch_station'), 'trigger' => __('kitchen.switch_station')])
        @if ($prep)
            <a href="{{ route('tenant.admin.kitchen.takeaway') }}">{{ __('kitchen.prep') }}</a>
        @endif
        @foreach ($stations as $other)
            <a href="{{ route('tenant.admin.kitchen.show', $other) }}">{{ $other->name }}</a>
        @endforeach
    @endcomponent
@endif
