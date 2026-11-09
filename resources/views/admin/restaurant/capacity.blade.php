@extends('layouts.admin')

@section('title', __('reservation.capacity'))

@section('content')
    <div class="page-head">
        <h1>{{ __('ui.restaurant.title') }}</h1>
        <button class="btn" type="button" data-open-dialog="area-dialog-new">{{ __('reservation.area_add') }}</button>
    </div>

    @include('admin.restaurant.subnav')

    <p class="lead">{{ __('reservation.area_hint') }}</p>

    @if ($areas->isEmpty())
        <p class="empty">{{ __('reservation.no_areas') }}</p>
    @else
        <div class="table-wrap area-table">
            <table>
                <thead>
                    <tr>
                        <th scope="col" class="col-handle"><span class="visually-hidden">{{ __('reservation.area_order') }}</span></th>
                        <th scope="col" class="col-code">{{ __('reservation.area_code') }}</th>
                        <th scope="col">{{ __('reservation.area_name') }}</th>
                        <th scope="col" class="num">{{ __('reservation.area_tables_short') }}</th>
                        <th scope="col" class="num">{{ __('reservation.area_seats_short') }}</th>
                        <th scope="col" class="col-tools"><span class="visually-hidden">{{ __('reservation.area_actions') }}</span></th>
                    </tr>
                </thead>
                <tbody data-sortable data-sort-url="{{ route('tenant.admin.restaurant.capacity.reorder') }}">
                    @foreach ($areas as $area)
                        <tr class="row-link" data-sort-id="{{ $area->id }}">
                            <td class="col-handle">@include('partials.drag-handle', ['label' => __('reservation.area_move', ['name' => $area->name])])</td>
                            <td class="col-code"><span class="area-code">{{ $area->code }}</span></td>
                            <td><button class="row-open" type="button" data-open-dialog="area-dialog-{{ $area->id }}">{{ $area->name }}</button></td>
                            <td class="num">{{ $area->table_count }}</td>
                            <td class="num">{{ $area->capacity }}</td>
                            <td class="col-tools">
                                @component('partials.kebab', ['label' => __('reservation.area_actions_for', ['name' => $area->name])])
                                    <button type="button" data-open-dialog="area-dialog-{{ $area->id }}">{{ __('reservation.area_edit') }}</button>
                                    <form method="post" action="{{ route('tenant.admin.restaurant.capacity.destroy', $area) }}" data-confirm-danger data-confirm="{{ __('reservation.area_delete_confirm', ['name' => $area->name]) }}">
                                        @csrf
                                        @method('delete')
                                        <button class="is-danger" type="submit">{{ __('reservation.area_delete') }}</button>
                                    </form>
                                @endcomponent
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td></td>
                        <td></td>
                        <th scope="row">{{ __('reservation.area_total') }}</th>
                        <td class="num"><strong>{{ $areas->sum('table_count') }}</strong></td>
                        <td class="num"><strong>{{ $areas->sum('capacity') }}</strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif

    @foreach ([null, ...$areas] as $area)
        @php($dialogId = 'area-dialog-'.($area?->id ?? 'new'))
        {{-- Lưu lỗi (ví dụ trùng mã) thì mở lại đúng hộp thoại, giữ chữ đã nhập. --}}
        @php($failed = old('_dialog') === $dialogId)
        <dialog id="{{ $dialogId }}" @if ($failed) data-auto-open @endif>
            <form method="post" action="{{ $area ? route('tenant.admin.restaurant.capacity.update', $area) : route('tenant.admin.restaurant.capacity.store') }}" class="stack">
                @csrf
                @if ($area) @method('put') @endif
                <input type="hidden" name="_dialog" value="{{ $dialogId }}">
                <h2>{{ $area ? __('reservation.area_edit') : __('reservation.area_add') }}</h2>
                <div class="field">
                    <label for="{{ $dialogId }}-name">{{ __('reservation.area_name') }}@include('partials.required')</label>
                    <input id="{{ $dialogId }}-name" name="name" value="{{ $failed ? old('name') : $area?->name }}" placeholder="{{ __('reservation.area_example') }}" maxlength="255" required>
                </div>
                <div class="field">
                    <label for="{{ $dialogId }}-code">{{ __('reservation.area_code') }}@include('partials.required')</label>
                    <input id="{{ $dialogId }}-code" class="code-input" name="code" value="{{ $failed ? old('code') : $area?->code }}" maxlength="2" pattern="[A-Za-z]{1,2}" autocomplete="off" autocapitalize="characters" required>
                    <small>{{ __('reservation.area_code_hint') }}</small>
                    @if ($failed)
                        @error('code')<small class="error">{{ $message }}</small>@enderror
                        @error('table_count')<small class="error">{{ $message }}</small>@enderror
                    @endif
                </div>
                <div class="field">
                    <label for="{{ $dialogId }}-tables">{{ __('reservation.area_tables') }}@include('partials.required')</label>
                    <div class="spin-group number-stepper" data-number-stepper>
                        <button type="button" data-number-step="-1" aria-label="{{ __('ui.number.decrease', ['label' => __('reservation.area_tables')]) }}">−</button>
                        <span class="number-field">
                            <input id="{{ $dialogId }}-tables" type="number" name="table_count" value="{{ $failed ? old('table_count') : ($area?->table_count ?? 5) }}" min="1" max="500" step="1" inputmode="numeric" required>
                            <span>{{ __('reservation.unit_tables') }}</span>
                        </span>
                        <button type="button" data-number-step="1" aria-label="{{ __('ui.number.increase', ['label' => __('reservation.area_tables')]) }}">+</button>
                    </div>
                </div>
                <div class="field">
                    <label for="{{ $dialogId }}-capacity">{{ __('reservation.area_capacity') }}@include('partials.required')</label>
                    <div class="spin-group number-stepper" data-number-stepper>
                        <button type="button" data-number-step="-1" aria-label="{{ __('ui.number.decrease', ['label' => __('reservation.area_capacity')]) }}">−</button>
                        <span class="number-field">
                            <input id="{{ $dialogId }}-capacity" type="number" name="capacity" value="{{ $failed ? old('capacity') : ($area?->capacity ?? 20) }}" min="1" max="10000" step="1" inputmode="numeric" required>
                            <span>{{ __('reservation.unit_seats') }}</span>
                        </span>
                        <button type="button" data-number-step="1" aria-label="{{ __('ui.number.increase', ['label' => __('reservation.area_capacity')]) }}">+</button>
                    </div>
                </div>
                <div class="dialog-foot">
                    <button class="btn" type="submit">{{ __('ui.common.save') }}</button>
                    <button class="btn btn-quiet" type="button" data-close-dialog>{{ __('ui.common.close') }}</button>
                </div>
            </form>
        </dialog>
    @endforeach
@endsection
