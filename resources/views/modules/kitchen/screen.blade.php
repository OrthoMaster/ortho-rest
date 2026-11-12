@extends('layouts.admin')

@section('title', $station->name)

@section('content')
    <div class="page-head screen-head">
        <h1>{{ $station->name }}</h1>
        <div class="screen-tools">
            @include('modules.kitchen.station-switch')
            <button class="btn btn-quiet btn-sm sound-toggle" type="button" data-sound-toggle aria-pressed="false"
                    data-on="{{ __('kitchen.sound_on') }}" data-off="{{ __('kitchen.sound_off') }}" title="{{ __('kitchen.sound_hint') }}">{{ __('kitchen.sound_off') }}</button>
        </div>
    </div>

    {{-- Phần này tự tải lại mỗi vài giây; có món mới thì kêu. --}}
    <div data-board data-board-station="{{ $station->id }}">
        @include('modules.kitchen.board')
    </div>
@endsection
