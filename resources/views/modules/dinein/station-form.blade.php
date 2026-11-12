@extends('layouts.admin')

@section('title', $station->name)

@section('content')
    @include('partials.page-title', ['title' => $station->name, 'back' => route('tenant.admin.restaurant.capacity.index')])

    <form method="post" action="{{ route('tenant.admin.dinein.stations.update', $station) }}" class="stack narrow" data-guard>
        @csrf
        @method('put')
        <section class="panel">
            @include('partials.field', ['name' => 'name', 'label' => __('dinein.station_name'), 'value' => $station->name, 'maxlength' => 60])
        </section>

        {{-- Chọn món cho khu chỉ khi bật Màn hình khu vực. --}}
        @if ($kitchen)
            <section class="panel">
                <div>
                    <h2>{{ __('dinein.station_items') }}</h2>
                    <small class="section-hint">{{ __('dinein.station_items_hint') }}</small>
                    <small class="section-hint">{{ __('dinein.station_no_items') }}</small>
                </div>
                @foreach ($categories as $category)
                    <div class="station-items">
                        <h3>{{ $category->name }}</h3>
                        @foreach ($category->items as $item)
                            <label class="check">
                                <input type="checkbox" name="items[]" value="{{ $item->id }}" @checked(in_array($item->id, array_map('intval', old('items', $category->items->where('dinein_station_id', $station->id)->pluck('id')->all())), true))>
                                <span>{{ $item->name }}@if ($item->dineinStation && ! $item->dineinStation->is($station)) <small class="row-note">{{ __('dinein.station_in', ['name' => $item->dineinStation->name]) }}</small>@endif</span>
                            </label>
                        @endforeach
                    </div>
                @endforeach
            </section>
        @endif

        <button class="btn" type="submit">{{ __('dinein.save') }}</button>
    </form>

    <form method="post" action="{{ route('tenant.admin.dinein.stations.destroy', $station) }}" class="narrow" data-confirm-danger data-confirm-ok="{{ __('dinein.station_delete') }}" data-confirm="{{ __('dinein.station_delete_confirm', ['name' => $station->name]) }}">
        @csrf
        @method('delete')
        <button class="link link-danger" type="submit">{{ __('dinein.station_delete') }}</button>
    </form>
@endsection
