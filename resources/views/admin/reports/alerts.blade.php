<section class="panel stack report-alerts" role="status">
    <h2>{{ __('reports.alerts_title') }}</h2>
    <ul>
        @foreach ($block['items'] as $text)
            <li>{{ $text }}</li>
        @endforeach
    </ul>
</section>
