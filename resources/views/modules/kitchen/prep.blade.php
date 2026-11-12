@extends('layouts.admin')

@section('title', __('kitchen.prep'))

@section('content')
    <div class="page-head screen-head">
        <h1>{{ __('kitchen.prep') }}</h1>
        <div class="screen-tools">
            @include('modules.kitchen.station-switch', ['prep' => true, 'current' => null])
            <button class="btn btn-quiet btn-sm sound-toggle" type="button" data-sound-toggle aria-pressed="false"
                    data-on="{{ __('kitchen.sound_on') }}" data-off="{{ __('kitchen.sound_off') }}" title="{{ __('kitchen.sound_hint') }}">{{ __('kitchen.sound_off') }}</button>
        </div>
    </div>

    <p class="lead">{{ __('kitchen.prep_lead') }}</p>

    {{-- Tự tải lại mỗi vài giây; có đơn mới hoặc món vừa xong thì kêu. --}}
    <div data-board>
        @include('modules.kitchen.prep-board')
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
