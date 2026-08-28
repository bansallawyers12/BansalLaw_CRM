@if ($message = Session::get('success'))
    @include('Elements.crm-flash-alert', [
        'type' => 'success',
        'message' => e($message),
        'title' => 'Success',
    ])
@endif

@if ($message = Session::get('error'))
    @include('Elements.crm-flash-alert', [
        'type' => 'danger',
        'message' => e($message),
        'title' => 'Error',
    ])
@endif

@if ($message = Session::get('warning'))
    @include('Elements.crm-flash-alert', [
        'type' => 'warning',
        'message' => e($message),
        'title' => 'Warning',
    ])
@endif

@if ($message = Session::get('info'))
    @include('Elements.crm-flash-alert', [
        'type' => 'info',
        'message' => e($message),
        'title' => 'Information',
    ])
@endif

@if ($errors->any())
    @include('Elements.crm-flash-alert', [
        'type' => 'danger',
        'title' => 'Please check the form',
        'message' => '<span>' . e($errors->first()) . '</span>',
        'autoDismiss' => 0,
    ])
@endif
