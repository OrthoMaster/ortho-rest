@extends('layouts.admin')

@section('title', __('staff.positions'))

@section('content')
    <div class="page-head">
        <h1>{{ __('modules.staff') }}</h1>
        <a class="btn" href="{{ route('tenant.admin.staff.positions.create') }}">{{ __('staff.position_add') }}</a>
    </div>

    @include('modules.staff.subnav')

    <div class="stack w-md">
    <p class="lead">{{ __('staff.positions_lead') }}</p>

    <section class="panel">
    <ul class="rows">
        {{-- Chủ quán luôn toàn quyền, không sửa được. --}}
        <li class="is-fixed">
            <span class="row-name">
                <strong>{{ __('ui.roles.owner') }}</strong>
                <small>{{ __('staff.owner_all') }}</small>
            </span>
        </li>
    </ul>
    </section>

    @if ($positions->isEmpty())
        <p class="empty">{{ __('staff.no_positions') }}</p>
    @else
        <section class="panel">
        <ul class="rows" data-sortable data-sort-url="{{ route('tenant.admin.staff.positions.reorder') }}">
            @foreach ($positions as $position)
                <li class="row-link" data-sort-id="{{ $position->id }}">
                    @include('partials.drag-handle', ['label' => __('staff.position_move', ['name' => $position->name])])
                    <span class="row-name">
                        <a href="{{ route('tenant.admin.staff.positions.edit', $position) }}">{{ $position->name }}</a>
                        <small>{{ __('staff.position_summary', ['people' => $position->users_count, 'count' => count(array_intersect($position->permissions, array_merge(...array_values(array_map('array_keys', \App\Support\Permissions::groupsFor(tenant())))))), 'total' => $total]) }}</small>
                    </span>
                    <div class="row-tools">
                        @component('partials.kebab', ['label' => __('staff.actions_for', ['name' => $position->name])])
                            <a href="{{ route('tenant.admin.staff.positions.edit', $position) }}">{{ __('staff.position_edit') }}</a>
                            <form method="post" action="{{ route('tenant.admin.staff.positions.destroy', $position) }}"
                                  data-confirm="{{ trans_choice('staff.position_delete_confirm', $position->users_count, ['name' => $position->name, 'count' => $position->users_count]) }}"
                                  data-confirm-title="{{ __('staff.position_delete') }}?" data-confirm-ok="{{ __('staff.position_delete') }}" data-confirm-danger>
                                @csrf
                                @method('delete')
                                <button class="is-danger" type="submit">{{ __('staff.position_delete') }}</button>
                            </form>
                        @endcomponent
                    </div>
                </li>
            @endforeach
        </ul>
        </section>
    @endif
    </div>
@endsection
