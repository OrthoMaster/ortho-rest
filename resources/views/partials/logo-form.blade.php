<form method="post" action="{{ $updateUrl }}" enctype="multipart/form-data" class="stack narrow" data-guard>
    @csrf
    @include('partials.image-upload', ['name' => 'logo', 'label' => __('ui.logo.title'), 'url' => $restaurant->logoUrl(), 'square' => true, 'required' => true, 'hideLabel' => true, 'hint' => __('ui.logo.hint')])
    <button class="btn" type="submit">{{ __('ui.logo.save') }}</button>
</form>
@if ($restaurant->logoUrl())
    <form method="post" action="{{ $destroyUrl }}" data-confirm-danger data-confirm="{{ __('ui.logo.remove_confirm') }}">
        @csrf
        @method('delete')
        <button class="link link-danger" type="submit">{{ __('ui.logo.remove') }}</button>
    </form>
@endif
