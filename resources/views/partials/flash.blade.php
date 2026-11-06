@if (session('status'))
    <p class="flash" role="status">{{ session('status') }}</p>
@endif
@if (session('warning'))
    <p class="flash flash-warn" role="status">{{ session('warning') }}</p>
@endif
@if (session('error'))
    <p class="flash flash-error" role="alert">{{ session('error') }}</p>
@endif
