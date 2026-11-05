<div class="status-actions">
    @foreach ($order->status->next() as $next)
        <form method="post" action="{{ route('tenant.admin.takeaway.status', $order) }}">
            @csrf
            <input type="hidden" name="status" value="{{ $next->value }}">
            <button @class(['btn', 'btn-sm', 'btn-quiet' => ! $loop->first]) type="submit">{{ $next->actionLabel() }}</button>
        </form>
    @endforeach
</div>
