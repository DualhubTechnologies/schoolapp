{{-- Any other refused request, e.g. ID cards asked for with details missing (422). --}}
@include('errors.layout', [
    'code' => $exception->getStatusCode().' · Could not continue',
    'title' => 'That could not be done',
    'message' => $exception->getMessage() ?: 'The request could not be completed. Go back, check the details and try again.',
])
