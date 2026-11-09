@extends('layouts.admin')

@section('title', __('reservation.areas'))

@section('content')
    <div class="page-head">
        <h1>{{ __('modules.reservation') }}</h1>
        <button class="btn" type="button" data-open-dialog="area-dialog-new">{{ __('reservation.area_add') }}</button>
    </div>

    @include('modules.reservation.subnav')

    <p class="lead">{{ __('reservation.area_hint') }}</p>

    @if ($areas->isEmpty())
        <p class="empty">{{ __('reservation.no_areas') }}</p>
    @else
        <div class="table-wrap area-table">
            <table>
                <thead>
                    <tr>
                        <th scope="col" class="col-handle"><span class="visually-hidden">{{ __('reservation.area_order') }}</span></th>
                        <th scope="col">{{ __('reservation.area_name') }}</th>
                        <th scope="col" class="num">{{ __('reservation.area_capacity') }}</th>
                        <th scope="col" class="col-tools"><span class="visually-hidden">{{ __('reservation.area_actions') }}</span></th>
                    </tr>
                </thead>
                <tbody data-sortable data-sort-url="{{ route('tenant.admin.reservation.areas.reorder') }}">
                    @foreach ($areas as $area)
                        <tr class="row-link" data-sort-id="{{ $area->id }}">
                            <td class="col-handle">@include('partials.drag-handle', ['label' => __('reservation.area_move', ['name' => $area->name])])</td>
                            <td><button class="row-open" type="button" data-open-dialog="area-dialog-{{ $area->id }}">{{ $area->name }}</button></td>
                            <td class="num">{{ $area->capacity }}</td>
                            <td class="col-tools">
                                @component('partials.kebab', ['label' => __('reservation.area_actions_for', ['name' => $area->name])])
                                    <button type="button" data-open-dialog="area-dialog-{{ $area->id }}">{{ __('reservation.area_edit') }}</button>
                                    <form method="post" action="{{ route('tenant.admin.reservation.areas.destroy', $area) }}" data-confirm-danger data-confirm="{{ __('reservation.area_delete_confirm', ['name' => $area->name]) }}">
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
                        <th scope="row">{{ __('reservation.area_total') }}</th>
                        <td class="num"><strong>{{ $areas->sum('capacity') }}</strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif

    @foreach ([null, ...$areas] as $area)
        @php($dialogId = 'area-dialog-'.($area?->id ?? 'new'))
        <dialog id="{{ $dialogId }}">
            <form method="post" action="{{ $area ? route('tenant.admin.reservation.areas.update', $area) : route('tenant.admin.reservation.areas.store') }}" class="stack">
                @csrf
                @if ($area) @method('put') @endif
                <h2>{{ $area ? __('reservation.area_edit') : __('reservation.area_add') }}</h2>
                <div class="field">
                    <label for="{{ $dialogId }}-name">{{ __('reservation.area_name') }}</label>
                    <input id="{{ $dialogId }}-name" name="name" value="{{ $area?->name }}" placeholder="{{ __('reservation.area_example') }}" maxlength="255" required>
                </div>
                <div class="field">
                    <label for="{{ $dialogId }}-capacity">{{ __('reservation.area_capacity') }}</label>
                    <div class="spin-group number-stepper" data-number-stepper>
                        <button type="button" data-number-step="-1" aria-label="{{ __('ui.number.decrease', ['label' => __('reservation.area_capacity')]) }}">−</button>
                        <span class="number-field">
                            <input id="{{ $dialogId }}-capacity" type="number" name="capacity" value="{{ $area?->capacity ?? 20 }}" min="1" max="10000" step="1" inputmode="numeric" required>
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
