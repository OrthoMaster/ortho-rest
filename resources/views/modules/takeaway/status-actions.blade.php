<div class="status-actions">
    @foreach ($order->status->next() as $next)
        @if ($next === \App\Modules\Takeaway\OrderStatus::Delivered)
            {{-- Giao khách: hỏi lại, kèm ô số hoá đơn máy tính tiền (không bắt buộc). --}}
            <button @class(['btn', 'btn-sm', 'btn-quiet' => ! $loop->first]) type="button" data-open-dialog="deliver-{{ $order->id }}">{{ $next->actionLabel() }}</button>
            <dialog id="deliver-{{ $order->id }}" class="confirm-dialog" aria-labelledby="deliver-title-{{ $order->id }}">
                <form method="post" action="{{ route('tenant.admin.takeaway.status', $order) }}" class="stack">
                    @csrf
                    <input type="hidden" name="status" value="{{ $next->value }}">
                    <h2 id="deliver-title-{{ $order->id }}">{{ __('takeaway.deliver_title', ['code' => $order->code()]) }}</h2>
                    <p>{{ __('takeaway.deliver_confirm', ['name' => $order->customer->name, 'total' => vnd($order->total)]) }}</p>
                    <div class="field">
                        <label for="deliver-bill-{{ $order->id }}">{{ __('dinein.bill_number') }}</label>
                        <input id="deliver-bill-{{ $order->id }}" name="bill_number" maxlength="40" autocomplete="off" placeholder="{{ __('dinein.bill_number_example') }}">
                        <small class="hint">{{ __('dinein.bill_number_hint') }}</small>
                    </div>
                    <div class="dialog-foot">
                        <button class="btn" type="submit">{{ $next->actionLabel() }}</button>
                        <button class="btn btn-quiet" type="button" data-close-dialog>{{ __('ui.common.cancel') }}</button>
                    </div>
                </form>
            </dialog>
        @else
            <form method="post" action="{{ route('tenant.admin.takeaway.status', $order) }}">
                @csrf
                <input type="hidden" name="status" value="{{ $next->value }}">
                <button @class(['btn', 'btn-sm', 'btn-quiet' => ! $loop->first]) type="submit">{{ $next->actionLabel() }}</button>
            </form>
        @endif
    @endforeach
</div>
