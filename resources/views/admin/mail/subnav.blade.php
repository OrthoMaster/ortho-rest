@include('partials.subnav', ['links' => [
    ['url' => route('tenant.admin.mail.server'), 'label' => __('mail.tabs.server'), 'active' => request()->routeIs('tenant.admin.mail.server')],
    ['url' => route('tenant.admin.mail.templates'), 'label' => __('mail.tabs.templates'), 'active' => request()->routeIs('tenant.admin.mail.templates', 'tenant.admin.mail.edit')],
]])
