@extends('layouts.admin')

@section('title', __('schedule.settings'))

@section('content')
    <div class="page-head">
        <h1>{{ __('schedule.title') }}</h1>
    </div>

    @include('modules.schedule.subnav')

    @php($targets = $stations->map(fn ($station) => ['key' => 's'.$station->id, 'name' => $station->name, 'code' => null, 'departments' => $station->department_ids ?? []])
        ->concat($areas->map(fn ($area) => ['key' => 'a'.$area->id, 'name' => $area->name, 'code' => $area->code, 'departments' => $area->department_ids ?? []])))

    {{-- Máy tính: cột trái là cài đặt chung và bộ phận (nút Lưu ngay dưới), cột phải là việc trong ca. --}}
    <div class="schedule-settings">
    <form method="post" action="{{ route('tenant.admin.schedule.settings.update') }}" class="stack schedule-settings-main" data-guard data-multi-manual>
        @csrf
        @method('put')
        <section class="stack">
            <h2>{{ __('schedule.workday') }}</h2>
            @include('partials.field', ['name' => 'workday_hours', 'type' => 'number', 'label' => __('schedule.workday_hours'),
                'value' => rtrim(rtrim(number_format($workdayHours, 1, '.', ''), '0'), '.'), 'step' => '0.5', 'min' => 1, 'max' => 24, 'hint' => __('schedule.workday_hint')])
            <div class="toggle-list">
                <label class="toggle-row">
                    <span>{{ __('schedule.include_owner') }}<small>{{ __('schedule.include_owner_hint') }}</small></span>
                    <input type="hidden" name="include_owner" value="0">
                    <input type="checkbox" name="include_owner" value="1" @checked($includeOwner)>
                </label>
            </div>
        </section>

        @if ($targets->isNotEmpty())
            <section class="stack">
                <div>
                    <h2>{{ __('schedule.target_departments') }}</h2>
                    <small class="section-hint">{{ __('schedule.target_departments_hint') }}</small>
                </div>
                <div class="toggle-list has-menus">
                    @foreach ($targets as $target)
                        <div class="toggle-row target-department">
                            <span>@if ($target['code'])<span class="area-code">{{ $target['code'] }}</span> @endif{{ $target['name'] }}</span>
                            @include('partials.filter-multi', [
                                'name' => 'departments['.$target['key'].']',
                                'label' => __('staff.department'),
                                'options' => $departments->pluck('name', 'id')->all(),
                                'values' => array_map('intval', old('departments.'.$target['key'], $target['departments'])),
                                'all' => __('schedule.any_department'),
                            ])
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <div><button class="btn" type="submit">{{ __('ui.common.save') }}</button></div>
    </form>

    <section class="stack task-settings">
        <div>
            <h2>{{ __('schedule.tasks') }}</h2>
            <small class="section-hint">{{ __('schedule.tasks_hint') }}</small>
        </div>

        @foreach ($targets->push(['key' => 'g', 'name' => __('schedule.general_tasks'), 'code' => null]) as $target)
            @php($list = $tasks->get($target['key'], collect()))
            <section class="panel task-group">
                <h3>@if ($target['code'])<span class="area-code">{{ $target['code'] }}</span> @endif{{ $target['name'] }}</h3>
                @if ($list->isNotEmpty())
                    <ul class="rows" data-sortable data-sort-url="{{ route('tenant.admin.schedule.tasks.reorder') }}">
                        @foreach ($list as $task)
                            <li class="row-link" data-sort-id="{{ $task->id }}">
                                @include('partials.drag-handle', ['label' => $task->name])
                                <span class="row-name"><button class="row-open" type="button" data-open-dialog="task-dialog-{{ $task->id }}">{{ $task->name }}</button></span>
                                <div class="row-tools">
                                    @component('partials.kebab', ['label' => __('schedule.task_actions', ['name' => $task->name])])
                                        <button type="button" data-open-dialog="task-dialog-{{ $task->id }}">{{ __('schedule.task_rename') }}</button>
                                        <form method="post" action="{{ route('tenant.admin.schedule.tasks.destroy', $task) }}" data-confirm-danger
                                              data-confirm-title="{{ __('schedule.task_delete') }}?" data-confirm-ok="{{ __('schedule.task_delete') }}"
                                              data-confirm="{{ __('schedule.task_delete_confirm', ['name' => $task->name]) }}">
                                            @csrf
                                            @method('delete')
                                            <button class="is-danger" type="submit">{{ __('schedule.task_delete') }}</button>
                                        </form>
                                    @endcomponent
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <form method="post" action="{{ route('tenant.admin.schedule.tasks.store') }}" class="inline-form task-add">
                    @csrf
                    <input type="hidden" name="target" value="{{ $target['key'] }}">
                    <label class="visually-hidden" for="task-{{ $target['key'] }}">{{ __('schedule.task_name') }}</label>
                    <input id="task-{{ $target['key'] }}" name="name" maxlength="60" required placeholder="{{ $target['key'] === 'g' ? __('schedule.task_example_general') : __('schedule.task_example') }}"
                           value="{{ $errors->hasBag('task-new') && old('target') === $target['key'] ? old('name') : '' }}">
                    <button class="btn btn-quiet" type="submit">{{ __('schedule.task_add') }}</button>
                    @if ($errors->hasBag('task-new') && old('target') === $target['key'])
                        <small class="error">{{ $errors->getBag('task-new')->first('name') }}</small>
                    @endif
                </form>
            </section>
        @endforeach
    </section>
    </div>

    @foreach ($tasks->flatten() as $task)
        @php($bag = 'task-'.$task->id)
        <dialog id="task-dialog-{{ $task->id }}" @if ($errors->hasBag($bag)) data-auto-open @endif>
            <form method="post" action="{{ route('tenant.admin.schedule.tasks.update', $task) }}" class="stack">
                @csrf
                @method('put')
                <h2>{{ __('schedule.task_rename') }}</h2>
                <div class="field">
                    <label for="task-name-{{ $task->id }}">{{ __('schedule.task_name') }}@include('partials.required')</label>
                    <input id="task-name-{{ $task->id }}" name="name" maxlength="60" required value="{{ $errors->hasBag($bag) ? old('name') : $task->name }}">
                    @if ($errors->hasBag($bag))
                        <small class="error">{{ $errors->getBag($bag)->first('name') }}</small>
                    @endif
                </div>
                <div class="dialog-foot">
                    <button class="btn" type="submit">{{ __('ui.common.save') }}</button>
                    <button class="btn btn-quiet" type="button" data-close-dialog>{{ __('ui.common.close') }}</button>
                </div>
            </form>
        </dialog>
    @endforeach
@endsection
