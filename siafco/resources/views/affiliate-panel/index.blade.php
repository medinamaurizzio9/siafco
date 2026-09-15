@include('layouts.app', [
    'title' => 'Inicio',
    'credentialAssets' => $isActive && $hasCredential,
    'slot' => view('affiliate-panel.content', get_defined_vars())->render(),
])
