{{--
    Hộp thoại của một phiếu: chưa thu thì là Thu tiền (xem lại món, số tiền, ô số hoá đơn);
    đã thu thì là Nhập / Sửa số hoá đơn.
--}}
@if (! $bill->paidAt())
    @can('cashier.collect')
        @php($bag = $errors->getBag('bill-'.$bill->key()))
        <dialog id="cash-{{ $bill->key() }}" class="cash-dialog" aria-labelledby="cash-title-{{ $bill->key() }}" data-backdrop-close @if ($bag->any()) data-auto-open @endif>
            <form method="post" action="{{ route('tenant.admin.cashier.paid', $bill->key()) }}" class="stack">
                @csrf
                <div class="panel-head dialog-head">
                    <div>
                        <h2 id="cash-title-{{ $bill->key() }}">{{ __('cashier.collect_title', ['bill' => $bill->title()]) }}</h2>
                        <small class="row-note">{{ collect([$bill->state(), $bill->customer()])->filter()->implode(' · ') }}</small>
                    </div>
                    <button class="row-btn" type="button" data-close-dialog aria-label="{{ __('ui.common.close') }}">✕</button>
                </div>
                <ul class="cash-lines">
                    @foreach ($bill->lines() as $line)
                        <li>
                            <span>{{ $line['quantity'] }} × {{ $line['name'] }}@foreach ($line['options'] as $option)<small>{{ $option }}</small>@endforeach</span>
                            <span>{{ vnd($line['total']) }}</span>
                        </li>
                    @endforeach
                    @if ($bill->discount() > 0)
                        <li class="cash-sum"><span>{{ __('cashier.subtotal') }}</span><span>{{ vnd($bill->subtotal()) }}</span></li>
                        <li><span>{{ __('cashier.discount') }}</span><span>−{{ vnd($bill->discount()) }}</span></li>
                    @endif
                    <li @class(['cash-due', 'cash-sum' => $bill->discount() === 0])><span>{{ __('cashier.total') }}</span><span>{{ vnd($bill->total()) }}</span></li>
                </ul>
                @if ($bill->isTable() && $bill->model->closed_at === null)
                    <p class="row-note">{{ __('cashier.collect_open_note') }}</p>
                @endif
                <div class="field">
                    <label for="cash-number-{{ $bill->key() }}">{{ __('cashier.number_label') }}</label>
                    <input id="cash-number-{{ $bill->key() }}" name="bill_number" maxlength="40" autocomplete="off" placeholder="{{ __('cashier.number_example') }}">
                    @if ($bag->has('bill_number'))
                        <small class="error">{{ $bag->first('bill_number') }}</small>
                    @else
                        <small class="hint">{{ __('cashier.number_hint') }}</small>
                    @endif
                </div>
                <div class="dialog-foot">
                    @if ($bill->isTable() && $bill->model->closed_at === null)
                        {{-- Bàn đang mở: khách ra quầy rồi về luôn thì thanh toán và đóng bàn một lần. --}}
                        <span class="form-actions">
                            <button class="btn" type="submit" name="close" value="1">{{ __('cashier.collect_close') }}</button>
                            <button class="btn btn-quiet" type="submit">{{ __('cashier.collect') }}</button>
                        </span>
                    @else
                        <button class="btn" type="submit">{{ __('cashier.collect') }}</button>
                    @endif
                    <button class="btn btn-quiet" type="button" data-close-dialog>{{ __('ui.common.cancel') }}</button>
                </div>
            </form>
        </dialog>
    @endcan
@else
    @php($bag = $errors->getBag('number-'.$bill->key()))
    <dialog id="number-{{ $bill->key() }}" class="confirm-dialog" aria-labelledby="number-title-{{ $bill->key() }}" @if ($bag->any()) data-auto-open @endif>
        <form method="post" action="{{ route('tenant.admin.cashier.number', $bill->key()) }}" class="stack" data-guard>
            @csrf
            @method('put')
            <h2 id="number-title-{{ $bill->key() }}">{{ $bill->billNumber() ? __('cashier.number_edit') : __('cashier.number_add') }}</h2>
            <p>{{ $bill->title() }} · {{ vnd($bill->total()) }} · {{ __('cashier.paid_at', ['time' => $bill->paidAt()->format('H:i d/m'), 'name' => $bill->payer() ?? '—']) }}</p>
            <div class="field">
                <label for="number-input-{{ $bill->key() }}">{{ __('cashier.number_label') }}</label>
                <input id="number-input-{{ $bill->key() }}" name="bill_number" value="{{ $bill->billNumber() }}" maxlength="40" autocomplete="off" placeholder="{{ __('cashier.number_example') }}">
                @if ($bag->has('bill_number'))<small class="error">{{ $bag->first('bill_number') }}</small>@endif
            </div>
            <div class="dialog-foot">
                <button class="btn" type="submit">{{ __('ui.common.save') }}</button>
                <button class="btn btn-quiet" type="button" data-close-dialog>{{ __('ui.common.cancel') }}</button>
            </div>
        </form>
    </dialog>
@endif
