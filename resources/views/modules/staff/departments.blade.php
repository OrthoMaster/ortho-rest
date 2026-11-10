@extends('layouts.admin')

@section('title', __('staff.departments'))

@section('content')
    <div class="page-head">
        <h1>{{ __('modules.staff') }}</h1>
        <button class="btn" type="button" data-open-dialog="department-dialog-new">{{ __('staff.department_add') }}</button>
    </div>

    @include('modules.staff.subnav')

    <p class="lead">{{ __('staff.departments_lead') }}</p>

    @if ($departments->isEmpty())
        <p class="empty">{{ __('staff.no_departments') }}</p>
    @else
        <section class="panel">
            <ul class="rows" data-sortable data-sort-url="{{ route('tenant.admin.staff.departments.reorder') }}">
                @foreach ($departments as $department)
                    <li class="row-link" data-sort-id="{{ $department->id }}">
                        @include('partials.drag-handle', ['label' => $department->name])
                        <span class="row-name">
                            <button class="row-open" type="button" data-open-dialog="department-dialog-{{ $department->id }}"><span class="person-pill tone-{{ $department->tone }}">{{ $department->name }}</span></button>
                            <small>{{ trans_choice('staff.department_people', $department->users_count, ['count' => $department->users_count]) }}</small>
                        </span>
                        <div class="row-tools">
                            @component('partials.kebab', ['label' => __('staff.actions_for', ['name' => $department->name])])
                                <button type="button" data-open-dialog="department-dialog-{{ $department->id }}">{{ __('staff.department_edit') }}</button>
                                <form method="post" action="{{ route('tenant.admin.staff.departments.destroy', $department) }}" data-confirm-danger
                                      data-confirm-title="{{ __('staff.department_delete') }}?" data-confirm-ok="{{ __('staff.department_delete') }}"
                                      data-confirm="{{ __('staff.department_delete_confirm', ['name' => $department->name, 'count' => $department->users_count]) }}">
                                    @csrf
                                    @method('delete')
                                    <button class="is-danger" type="submit">{{ __('staff.department_delete') }}</button>
                                </form>
                            @endcomponent
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @foreach ([null, ...$departments] as $department)
        @php($bag = 'department-'.($department?->id ?? 'new'))
        @php($fails = $errors->hasBag($bag))
        @php($tone = (int) ($fails ? old('tone') : ($department?->tone ?? \App\Models\Department::nextTone())))
        <dialog id="department-dialog-{{ $department?->id ?? 'new' }}" @if ($fails) data-auto-open @endif>
            <form method="post" class="stack" action="{{ $department ? route('tenant.admin.staff.departments.update', $department) : route('tenant.admin.staff.departments.store') }}">
                @csrf
                @if ($department) @method('put') @endif
                <h2>{{ $department ? __('staff.department_edit') : __('staff.department_add') }}</h2>
                <div class="field">
                    <label for="department-name-{{ $department?->id ?? 'new' }}">{{ __('staff.department_name') }}@include('partials.required')</label>
                    <input id="department-name-{{ $department?->id ?? 'new' }}" name="name" maxlength="60" required
                           value="{{ $fails ? old('name') : $department?->name }}" placeholder="{{ __('staff.department_example') }}">
                    @if ($fails)
                        <small class="error">{{ $errors->getBag($bag)->first('name') }}</small>
                    @endif
                </div>
                <fieldset class="tone-pick">
                    <legend>{{ __('staff.department_tone') }}</legend>
                    @foreach (range(1, \App\Models\Department::TONES) as $option)
                        <label class="tone-swatch tone-{{ $option }}">
                            <input type="radio" name="tone" value="{{ $option }}" @checked($tone === $option)>
                            <span class="visually-hidden">{{ __('staff.tone', ['number' => $option]) }}</span>
                        </label>
                    @endforeach
                </fieldset>
                <div class="dialog-foot">
                    <button class="btn" type="submit">{{ __('ui.common.save') }}</button>
                    <button class="btn btn-quiet" type="button" data-close-dialog>{{ __('ui.common.close') }}</button>
                </div>
            </form>
        </dialog>
    @endforeach
@endsection
