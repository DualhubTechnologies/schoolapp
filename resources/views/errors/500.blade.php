@include('errors.layout', [
    'code' => '500 · Something went wrong',
    'title' => 'Sorry, something went wrong on our side',
    'message' => 'This was not caused by anything you did. The SchoolHub team has been told about it automatically.',
    'hint' => 'Please try again in a moment. If it keeps happening, contact us with the reference below.',
    'reference' => app(\App\Support\ErrorRecorder::class)->reference(),
])
