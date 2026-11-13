@extends('layouts.admin')

@section('title', __('cashier.title'))

@section('content')
    <div class="page-head">
        <h1>{{ __('cashier.title') }}</h1>
    </div>

    @include('modules.cashier.subnav')

    {{-- Tự tải lại mỗi 5 giây: phục vụ vừa đóng bàn là phiếu hiện ở Chờ thu tiền. --}}
    <div data-board>
        @include('modules.cashier.board')
    </div>
@endsection
