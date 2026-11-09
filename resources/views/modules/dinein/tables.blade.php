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
        {{-- Gõ nhanh số bàn, ví dụ A12. --}}
        <form method="post" action="{{ route('tenant.admin.dinein.tables.go') }}" class="table-go">
            @csrf
            <label class="visually-hidden" for="table-go">{{ __('dinein.table_label') }}</label>
            <input id="table-go" name="label" value="{{ old('label') }}" placeholder="{{ __('dinein.table_example') }}" maxlength="5" autocomplete="off" autocapitalize="characters" required @error('label') aria-invalid="true" @enderror>
            <button class="btn" type="submit">{{ __('dinein.table_open') }}</button>
            @error('label')<small class="error">{{ $message }}</small>@enderror
        </form>

        @foreach ($areas as $area)
            <section class="table-area">
                <h2><span class="area-code">{{ $area->code }}</span> {{ $area->name }}</h2>
                <ul class="table-grid">
                    @for ($number = 1; $number <= $area->table_count; $number++)
                        @php($table = $open->get($area->id.'-'.$number))
                        @php($ready = $table?->lines->where('status', \App\Modules\DineIn\LineStatus::Done)->sum('quantity') ?? 0)
                        @php($cooking = $table?->lines->where('status', \App\Modules\DineIn\LineStatus::New)->sum('quantity') ?? 0)
                        <li>
                            <a href="{{ route('tenant.admin.dinein.tables.show', $area->tableLabel($number)) }}" @class(['table-tile', 'is-open' => $table, 'has-ready' => $ready > 0])>
                                <strong>{{ $area->tableLabel($number) }}</strong>
                                @if ($table)
                                    <span>{{ vnd($table->subtotal()) }}</span>
                                    @if ($ready > 0)
                                        <small class="badge badge-ok">{{ __('dinein.ready_count', ['count' => $ready]) }}</small>
                                    @elseif ($cooking > 0)
                                        <small class="badge badge-warn">{{ __('dinein.in_progress', ['count' => $cooking]) }}</small>
                                    @endif
                                @else
                                    <small>{{ __('dinein.free') }}</small>
                                @endif
                            </a>
                        </li>
                    @endfor
                </ul>
            </section>
        @endforeach
    @endif
@endsection
