<div class="logo-editor">
    @include('partials.restaurant-logo', ['restaurant' => $restaurant, 'size' => 'lg'])
    <div class="stack">
        <form method="post" action="{{ $updateUrl }}" enctype="multipart/form-data" class="stack">
            @csrf
            @include('partials.field', ['name' => 'logo', 'type' => 'file', 'accept' => 'image/png,image/jpeg,image/webp', 'label' => $restaurant->logoUrl() ? __('ui.logo.replace') : __('ui.logo.upload'), 'hint' => __('ui.logo.hint')])
            <button class="btn" type="submit">{{ __('ui.logo.save') }}</button>
        </form>
        @if ($restaurant->logoUrl())
            <form method="post" action="{{ $destroyUrl }}" onsubmit="return confirm(this.dataset.confirm)" data-confirm="{{ __('ui.logo.remove_confirm') }}">
                @csrf
                @method('delete')
                <button class="link link-danger" type="submit">{{ __('ui.logo.remove') }}</button>
            </form>
        @endif
    </div>
</div>
