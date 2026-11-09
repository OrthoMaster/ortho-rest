@include('partials.subnav', ['links' => [
    ['url' => route('tenant.admin.menu.index'), 'label' => __('menu.all'), 'active' => $current === 'all'],
    ['url' => route('tenant.admin.menu.index', ['menu' => 'dine_in']), 'label' => __('menu.dine_in'), 'active' => $current === 'dine_in'],
    ['url' => route('tenant.admin.menu.index', ['menu' => 'takeaway']), 'label' => __('menu.takeaway'), 'active' => $current === 'takeaway'],
    ...(auth()->user()->can('menu.manage') ? [['url' => route('tenant.admin.menu.tags.index'), 'label' => __('menu.tags'), 'active' => $current === 'tags']] : []),
]])
