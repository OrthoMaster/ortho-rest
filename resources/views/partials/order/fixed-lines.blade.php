{{-- Món của đơn cũ không còn trong menu: giữ nguyên, không sửa được. --}}
@if ($lines->isNotEmpty())
    <ul class="rows dine-lines">
        @foreach ($lines as $line)
            <li><span class="row-name">{{ $line->quantity }} × {{ $line->name }}@foreach ($line->optionLines() as $optionLine)<small>{{ $optionLine }}</small>@endforeach<small>{{ __('takeaway.item_removed_from_menu') }}</small></span><strong class="line-total">{{ vnd($line->lineTotal()) }}</strong></li>
        @endforeach
    </ul>
@endif
