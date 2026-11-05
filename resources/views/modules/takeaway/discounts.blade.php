@extends('layouts.admin')

@section('title', __('takeaway.discounts'))

@section('content')
    <div class="page-head">
        <h1>{{ __('modules.takeaway') }}</h1>
        <a class="btn" href="{{ route('tenant.admin.takeaway.discounts.create') }}">{{ __('takeaway.code_add') }}</a>
    </div>

    @include('modules.takeaway.subnav')

    @if ($codes->isEmpty())
        <p class="empty">{{ __('takeaway.discounts_empty') }}</p>
    @else
        <section class="panel">
            <ul class="rows">
                @foreach ($codes as $code)
                    <li class="row-link">
                        <span class="row-name"><a href="{{ route('tenant.admin.takeaway.discounts.edit', $code) }}">{{ $code->code }}</a><small>{{ $code->label() }}</small></span>
                        @unless ($code->is_active)<span class="badge">{{ __('takeaway.code_inactive') }}</span>@endunless
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
