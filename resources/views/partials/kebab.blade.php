{{-- Menu thao tác. Mặc định là nút 3 chấm; truyền $trigger để nút hiện chữ (ví dụ ô trong bảng). --}}
<details class="kebab">
    <summary @class(['row-btn' => ! isset($trigger), 'cell-btn' => isset($trigger)]) aria-label="{{ $label }}">
        @isset($trigger)
            {{ $trigger }}
        @else
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.9"/><circle cx="12" cy="12" r="1.9"/><circle cx="12" cy="19" r="1.9"/></svg>
        @endisset
    </summary>
    <div class="kebab-menu">
        {{ $slot }}
    </div>
</details>
