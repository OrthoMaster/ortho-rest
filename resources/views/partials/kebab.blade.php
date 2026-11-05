<details class="kebab">
    <summary aria-label="{{ $label }}">
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.9"/><circle cx="12" cy="12" r="1.9"/><circle cx="12" cy="19" r="1.9"/></svg>
    </summary>
    <div class="kebab-menu">
        {{ $slot }}
    </div>
</details>
