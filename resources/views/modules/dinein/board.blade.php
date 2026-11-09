@if ($tickets->isEmpty())
    <p class="empty">{{ __('dinein.board_empty') }}</p>
@else
    <div class="tickets">
        @foreach ($tickets as $lines)
            @php($first = $lines->first())
            @php($minutes = (int) $first->created_at->diffInMinutes(now()))
            <section @class(['ticket', 'is-late' => $minutes >= 15]) data-ticket>
                <div class="ticket-head">
                    <strong class="ticket-table">{{ $first->diningTable->label }}</strong>
                    <span class="row-note">{{ __('dinein.round', ['round' => $first->round]) }} · {{ $minutes < 1 ? __('dinein.just_now') : __('dinein.minutes_ago', ['count' => $minutes]) }}</span>
                </div>
                <ul class="ticket-lines">
                    @foreach ($lines as $line)
                        <li data-line-id="{{ $line->id }}">
                            <span class="ticket-qty">{{ $line->quantity }}</span>
                            <span class="ticket-name">
                                {{ $line->name }}
                                @if ($line->optionText() !== '')<small>{{ $line->optionText() }}</small>@endif
                                @if ($line->note)<small class="dine-note">{{ $line->note }}</small>@endif
                            </span>
                            <form method="post" action="{{ route('tenant.admin.dinein.screens.done', $station) }}" data-confirm="{{ __('dinein.done_confirm', ['name' => $line->quantity.' × '.$line->name]) }}" data-confirm-ok="{{ __('dinein.done') }}">
                                @csrf
                                <input type="hidden" name="lines[]" value="{{ $line->id }}">
                                <button class="btn btn-sm" type="submit">{{ __('dinein.done') }}</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
                <div class="ticket-foot">
                    <small class="row-note">{{ __('dinein.ordered_by', ['name' => $first->orderedBy?->name ?? '—']) }}</small>
                    @if ($lines->count() > 1)
                        <form method="post" action="{{ route('tenant.admin.dinein.screens.done', $station) }}" data-confirm="{{ __('dinein.done_all_confirm', ['table' => $first->diningTable->label]) }}" data-confirm-ok="{{ __('dinein.done_all') }}">
                            @csrf
                            @foreach ($lines as $line)
                                <input type="hidden" name="lines[]" value="{{ $line->id }}">
                            @endforeach
                            <button class="btn btn-quiet btn-sm" type="submit">{{ __('dinein.done_all') }}</button>
                        </form>
                    @endif
                </div>
            </section>
        @endforeach
    </div>
@endif

@if ($recent->isNotEmpty())
    <section class="panel recent-done">
        <h2>{{ __('dinein.recent') }}</h2>
        <ul class="rows">
            @foreach ($recent as $line)
                <li>
                    <span class="row-name">{{ $line->diningTable->label }} · {{ $line->quantity }} × {{ $line->name }}</span>
                    <span @class(['badge', $line->status->badge()])>{{ $line->status->label() }}</span>
                    <small class="row-note">{{ $line->done_at->format('H:i') }}</small>
                </li>
            @endforeach
        </ul>
    </section>
@endif
