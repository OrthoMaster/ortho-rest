<div class="status-actions">
    @foreach ($reservation->status->next() as $next)
        <form method="post" action="{{ route('tenant.admin.reservation.status', $reservation) }}">
            @csrf
            <input type="hidden" name="status" value="{{ $next->value }}">
            <button @class(['btn', 'btn-sm', 'btn-quiet' => ! $loop->first]) type="submit">{{ $next->actionLabel() }}</button>
        </form>
    @endforeach
</div>
