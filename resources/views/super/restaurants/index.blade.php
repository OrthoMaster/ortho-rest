@extends('layouts.super')

@section('title', __('ui.super.title'))

@section('content')
    <div class="page-head">
        <h1>{{ __('ui.super.title') }}</h1>
        <a class="btn" href="{{ route('super.restaurants.create') }}">{{ __('ui.super.create') }}</a>
    </div>

    <form class="search" method="get">
        <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('ui.super.search') }}" aria-label="{{ __('ui.super.search') }}">
        <button class="btn btn-quiet" type="submit">{{ __('ui.super.search_submit') }}</button>
    </form>

    @if ($restaurants->isEmpty())
        <p class="empty">{{ $search !== '' ? __('ui.super.no_results', ['q' => $search]) : __('ui.super.empty') }}</p>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('ui.super.col_name') }}</th>
                        <th>{{ __('ui.super.col_url') }}</th>
                        <th>{{ __('ui.super.col_owner') }}</th>
                        <th>{{ __('ui.super.col_status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($restaurants as $restaurant)
                        @php($domain = $restaurant->domains->whereNotNull('verified_at')->first())
                        <tr>
                            <td><a href="{{ route('super.restaurants.show', $restaurant) }}">{{ $restaurant->name }}</a></td>
                            <td>{{ $domain?->domain ?? '/'.$restaurant->slug }}</td>
                            <td>{{ $restaurant->users->first()?->email ?? __('ui.super.no_owner_label') }}</td>
                            <td>
                                @if ($restaurant->isLocked())
                                    <span class="badge badge-bad">{{ __('ui.super.status_locked') }}</span>
                                @else
                                    <span class="badge badge-ok">{{ __('ui.super.status_active') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $restaurants->links('partials.pagination') }}
    @endif
@endsection
