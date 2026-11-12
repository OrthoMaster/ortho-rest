@extends('layouts.admin')

@section('title', $station->name)

@section('content')
    <div class="page-head screen-head">
        <h1>{{ $station->name }}</h1>
        <div class="screen-tools">
            @include('modules.dinein.station-switch')
            <button class="btn btn-quiet btn-sm sound-toggle" type="button" data-sound-toggle aria-pressed="false"
                    data-on="{{ __('dinein.sound_on') }}" data-off="{{ __('dinein.sound_off') }}" title="{{ __('dinein.sound_hint') }}">{{ __('dinein.sound_off') }}</button>
        </div>
    </div>

    @include('modules.dinein.subnav')

    {{-- Phần này tự tải lại mỗi vài giây; có món mới thì kêu. --}}
    <div data-board data-board-station="{{ $station->id }}">
        @include('modules.dinein.board')
    </div>
@endsection
