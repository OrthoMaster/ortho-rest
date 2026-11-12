{{-- Các lượt gọi món, tạm tính và nút của một bàn đang có khách. --}}
@foreach ($rounds as $round => $lines)
    <section class="panel">
        <div class="panel-head">
            <h2>{{ __('dinein.round', ['round' => $round]) }}</h2>
            <small class="row-note">{{ __('dinein.round_meta', ['time' => $lines->first()->created_at->format('H:i'), 'name' => $lines->first()->orderedBy?->name ?? '—']) }}</small>
        </div>
        <ul class="rows dine-lines">
            @foreach ($lines as $line)
                @include('modules.dinein.line')
            @endforeach
        </ul>
    </section>
@endforeach

<section class="panel dine-bill">
    <div class="panel-head">
        <div>
            <h2>{{ __('dinein.subtotal') }}</h2>
            <small class="row-note">{{ __('dinein.subtotal_hint') }}</small>
        </div>
        <strong class="dine-total">{{ vnd($table->subtotal()) }}</strong>
    </div>
</section>

<div class="dine-actions">
    <a class="btn" href="{{ route('tenant.admin.dinein.tables.order', $label) }}">{{ __('dinein.order_more') }}</a>
    @can('dinein.close')
        <form method="post" action="{{ route('tenant.admin.dinein.tables.close', $table) }}"
              data-confirm-title="{{ __('dinein.close_title', ['table' => $label]) }}" data-confirm-ok="{{ __('dinein.close') }}"
              data-confirm="{{ __('dinein.close_confirm', ['total' => vnd($table->subtotal())]) }}">
            @csrf
            <button class="btn btn-quiet" type="submit">{{ __('dinein.close') }}</button>
        </form>
    @endcan
</div>
