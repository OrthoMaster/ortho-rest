{{--
    Thanh lọc chung của danh sách. Máy tính: một hàng, đổi là lọc ngay.
    Điện thoại: hàng ngày + nút Lọc, bấm mở bảng lọc từ dưới lên.
    $date: slot hàng ngày. $slot: các ô lọc (partials/filter-multi). $keep: tham số giữ lại khi lọc.
    $pills: [['label' => ..., 'url' => ...]] điều kiện đang lọc, bấm ✕ để bỏ. $clearUrl: bỏ hết lọc.
--}}
@php($panelId = 'filter-panel-'.($id ?? 'list'))
@php($count = count($pills ?? []))
<div @class(['filter-bar', $class ?? '']) data-filter-bar>
    <div class="filter-top">
        {{ $date ?? '' }}
        @unless ($noPanel ?? false)
        <button class="row-btn filter-toggle" type="button" data-filter-open aria-expanded="false" aria-controls="{{ $panelId }}" aria-label="{{ __('ui.filter.open') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5h16l-6 7.5V19l-4-2v-4.5z"/></svg>
            @if ($count > 0)<span class="filter-count">{{ $count }}</span>@endif
        </button>
        @endunless
    </div>

    @unless ($noPanel ?? false)
    <div class="filter-backdrop" data-filter-close></div>
    <form method="get" class="filter-panel" id="{{ $panelId }}" data-filter-form aria-label="{{ __('ui.filter.title') }}">
        @foreach ($keep ?? [] as $keepName => $keepValue)
            <input type="hidden" name="{{ $keepName }}" value="{{ $keepValue }}">
        @endforeach
        <div class="filter-head">
            <strong>{{ __('ui.filter.title') }}</strong>
            <button class="row-btn" type="button" data-filter-close aria-label="{{ __('ui.common.close') }}">✕</button>
        </div>
        <div class="filter-fields">
            {{ $slot }}
        </div>
        <div class="filter-foot">
            <a class="btn btn-quiet" href="{{ $clearUrl }}">{{ __('ui.filter.clear') }}</a>
            <button class="btn" type="submit">{{ __('ui.filter.apply') }}</button>
        </div>
        <noscript><button class="btn btn-quiet" type="submit">{{ __('ui.filter.apply') }}</button></noscript>
    </form>
    @endunless

    @if ($count > 0)
        <div class="filter-pills">
            @foreach ($pills as $pill)
                <a class="filter-pill" href="{{ $pill['url'] }}" aria-label="{{ __('ui.filter.remove', ['label' => $pill['label']]) }}">{{ $pill['label'] }}<span aria-hidden="true">✕</span></a>
            @endforeach
        </div>
    @endif
</div>
