@extends('layouts.admin')

@section('title', __('dinein.prep'))

@section('content')
    <div class="page-head screen-head">
        <h1>{{ __('dinein.prep') }}</h1>
        <div class="screen-tools">
            @include('modules.dinein.station-switch', ['prep' => true, 'current' => null])
            <button class="btn btn-quiet btn-sm sound-toggle" type="button" data-sound-toggle aria-pressed="false"
                    data-on="{{ __('dinein.sound_on') }}" data-off="{{ __('dinein.sound_off') }}" title="{{ __('dinein.sound_hint') }}">{{ __('dinein.sound_off') }}</button>
        </div>
    </div>

    @include('modules.dinein.subnav')

    <p class="lead">{{ __('dinein.prep_lead') }}</p>

    {{-- Tự tải lại mỗi vài giây; có đơn mới hoặc món vừa xong thì kêu. --}}
    <div data-board>
        @include('modules.dinein.prep-board')
    </div>

    {{-- Xem đơn: nội dung chép từ template của đơn được bấm. --}}
    <dialog id="prep-order" class="dine-dialog" aria-labelledby="prep-order-title" data-backdrop-close>
        <div class="stack">
            <div class="panel-head dialog-head">
                <h2 id="prep-order-title" data-order-title></h2>
                <button class="row-btn" type="button" data-close-dialog aria-label="{{ __('ui.common.close') }}">✕</button>
            </div>
            <div class="stack" data-order-body></div>
        </div>
    </dialog>
@endsection
