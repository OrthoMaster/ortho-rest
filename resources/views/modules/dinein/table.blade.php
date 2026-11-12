@extends('layouts.admin')

@section('title', __('dinein.table_title', ['table' => $label]))

@section('content')
    @component('partials.page-title', ['title' => __('dinein.table_title', ['table' => $label]), 'back' => route('tenant.admin.dinein.tables.index')])
        <p class="page-sub">{{ $area->name }}@if ($table) · {{ __('dinein.opened_by', ['time' => $table->created_at->format('H:i'), 'name' => $table->opener?->name ?? '—']) }}@endif</p>
    @endcomponent

    @if (! $table)
        <section class="panel empty-state">
            <h2>{{ __('dinein.table_free') }}</h2>
            <p class="row-note">{{ __('dinein.table_free_hint') }}</p>
            <p><a class="btn" href="{{ route('tenant.admin.dinein.tables.order', $label) }}">{{ __('dinein.order') }}</a></p>
        </section>
    @else
        {{-- Tự tải lại mỗi vài giây: món khu vừa báo xong hiện nút Đã mang ra ngay. --}}
        <div class="stack" data-board>
            @include('modules.dinein.table-board')
        </div>
    @endif
@endsection
