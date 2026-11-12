{{-- Giảm giá trên phiếu (từ ưu đãi của khách) và còn phải trả. $inBill: trong phiếu tạm tính thì không có nút bỏ giảm. --}}
<div class="bill-discount">
    <span class="row-name">{{ __('dinein.bill_discount') }}<small>{{ $table->offer?->summary() ?? $table->discount_type->label($table->discount_value) }}</small></span>
    <span class="bill-discount-amount">−{{ vnd($table->discount()) }}</span>
    @if (! ($inBill ?? false))
        @can('dinein.close')
            <form method="post" action="{{ route('tenant.admin.dinein.tables.discount.remove', $table) }}"
                  data-confirm-title="{{ __('dinein.discount_remove') }}?" data-confirm-ok="{{ __('dinein.discount_remove') }}" data-confirm="{{ __('dinein.discount_remove_confirm') }}">
                @csrf
                @method('delete')
                <button class="link" type="submit">{{ __('dinein.discount_remove') }}</button>
            </form>
        @endcan
    @endif
</div>
<div class="bill-total bill-due">
    <span>{{ __('dinein.bill_due') }}</span>
    <strong>{{ vnd($table->total()) }}</strong>
</div>
