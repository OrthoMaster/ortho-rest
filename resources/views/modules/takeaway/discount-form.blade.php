@extends('layouts.admin')

@section('title', $discount->exists ? $discount->code : __('takeaway.code_add'))

@section('content')
    @include('partials.page-title', ['title' => $discount->exists ? $discount->code : __('takeaway.code_add'), 'back' => route('tenant.admin.takeaway.discounts.index')])

    <form method="post" class="panel narrow stack" data-guard
          action="{{ $discount->exists ? route('tenant.admin.takeaway.discounts.update', $discount) : route('tenant.admin.takeaway.discounts.store') }}">
        @csrf
        @if ($discount->exists) @method('put') @endif
        @include('partials.field', ['name' => 'code', 'label' => __('takeaway.code'), 'value' => $discount->code, 'hint' => __('takeaway.code_hint')])
        @include('partials.select', ['name' => 'type', 'label' => __('takeaway.code_type'), 'value' => $discount->type?->value, 'options' => [
            'percent' => __('takeaway.code_type_percent'),
            'amount' => __('takeaway.code_type_amount'),
        ]])
        @include('partials.field', ['name' => 'value', 'type' => 'number', 'label' => __('takeaway.code_value'), 'value' => $discount->value, 'hint' => __('takeaway.code_value_hint')])
        <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $discount->is_active))> {{ __('takeaway.code_active') }}</label>
        <button class="btn" type="submit">{{ __('takeaway.code_save') }}</button>
    </form>

    @if ($discount->exists)
        <form method="post" action="{{ route('tenant.admin.takeaway.discounts.destroy', $discount) }}" data-confirm-danger data-confirm="{{ __('takeaway.code_delete_confirm', ['code' => $discount->code]) }}">
            @csrf
            @method('delete')
            <button class="link link-danger" type="submit">{{ __('takeaway.code_delete') }}</button>
        </form>
    @endif
@endsection
