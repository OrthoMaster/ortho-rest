@extends('layouts.admin')

@section('title', __('modules.dinein'))

@section('content')
    <div class="page-head">
        <h1>{{ __('modules.dinein') }}</h1>
    </div>

    @include('modules.dinein.subnav')

    @if ($areas->isEmpty())
        <p class="empty">{{ __('dinein.no_areas') }}</p>
        @can('settings.manage')
            <p><a class="btn btn-quiet" href="{{ route('tenant.admin.restaurant.capacity.index') }}">{{ __('dinein.manage_areas') }}</a></p>
        @endcan
    @else
        @if ($locked && ! session('warning'))
            <p class="flash flash-warn" role="status">{{ $locked }}</p>
        @endif
        {{-- Gõ nhanh số bàn, ví dụ A12. --}}
        <form @if ($locked) hidden @endif method="post" action="{{ route('tenant.admin.dinein.tables.go') }}" class="table-go">
            @csrf
            <label class="visually-hidden" for="table-go">{{ __('dinein.table_label') }}</label>
            <input id="table-go" name="label" value="{{ old('label') }}" placeholder="{{ __('dinein.table_example') }}" maxlength="5" autocomplete="off" autocapitalize="characters" required @error('label') aria-invalid="true" @enderror>
            <button class="btn" type="submit">{{ __('dinein.table_open') }}</button>
            @error('label')<small class="error">{{ $message }}</small>@enderror
        </form>

        {{-- Tự tải lại mỗi vài giây: bàn có món đã xong mà chưa mang ra hiện dấu cảnh báo. --}}
        <div class="stack" data-board>
            @include('modules.dinein.tables-board')
        </div>
    @endif
@endsection
