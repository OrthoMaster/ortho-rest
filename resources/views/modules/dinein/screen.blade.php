@extends('layouts.admin')

@section('title', $station->name)

@section('content')
    <div class="page-head screen-head">
        <h1>{{ $station->name }}</h1>
        <div class="screen-tools">
            @if ($stations->count() > 1)
                @component('partials.kebab', ['label' => __('dinein.switch_station'), 'trigger' => __('dinein.switch_station')])
                    @foreach ($stations as $other)
                        <a href="{{ route('tenant.admin.dinein.screens.show', $other) }}">{{ $other->name }}</a>
                    @endforeach
                @endcomponent
            @endif
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
