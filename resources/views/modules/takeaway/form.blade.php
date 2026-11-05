@extends('layouts.admin')

@section('title', __('takeaway.add'))

@section('content')
    @include('partials.page-title', ['title' => __('takeaway.add'), 'back' => back_url(route('tenant.admin.takeaway.index'))])

    @if ($categories->isEmpty())
        <p class="empty">{{ __('takeaway.no_menu') }}</p>
    @else
        <form method="post" action="{{ route('tenant.admin.takeaway.store') }}" class="stack" data-guard>
            @csrf
            <section class="panel narrow stack">
                @include('partials.field', ['name' => 'customer_name', 'label' => __('reservation.customer_name')])
                @include('partials.field', ['name' => 'customer_phone', 'type' => 'tel', 'label' => __('reservation.customer_phone')])
                @include('partials.field', ['name' => 'pickup_at', 'type' => 'datetime-local', 'label' => __('takeaway.pickup_at'), 'value' => now()->addMinutes(30)->format('Y-m-d\TH:i')])
                @include('partials.textarea', ['name' => 'notes', 'label' => __('reservation.notes')])
            </section>

            <section class="panel">
                <h2>{{ __('takeaway.items') }}</h2>
                @error('items')<small class="error">{{ $message }}</small>@enderror
                @foreach ($categories as $category)
                    <h3>{{ $category->name }}</h3>
                    <ul class="rows">
                        @foreach ($category->items as $item)
                            <li>
                                <label class="row-name" for="qty-{{ $item->id }}">{{ $item->name }}<small>{{ vnd($item->price_takeaway) }}</small></label>
                                <input class="input-short" id="qty-{{ $item->id }}" type="number" min="0" max="999" name="items[{{ $item->id }}]" value="{{ old('items.'.$item->id, 0) }}">
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </section>

            <button class="btn" type="submit">{{ __('takeaway.save') }}</button>
        </form>
    @endif
@endsection
