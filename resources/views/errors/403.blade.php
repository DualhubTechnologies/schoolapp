@include('errors.layout', [
    'code' => '403 · No access',
    'title' => 'You don\'t have access to this page',
    'message' => ($exception->getMessage() && $exception->getMessage() !== 'This action is unauthorized.') ? $exception->getMessage() : 'Your account is not allowed to open this part of SchoolHub.',
    'hint' => 'If you need it for your work, ask your school administrator to give you access under Settings → Users.',
])
