<?php

/*
 * Only the messages SchoolHub words differently; everything else comes from
 * Laravel's own validation messages.
 */
return [

    // Livewire's message when a file upload fails. Its :attribute is the
    // field's internal name (e.g. "data.photo.8f3c…"), so it is left out.
    'uploaded' => 'This file could not be uploaded. It may be too large (up to 10 MB), or the connection dropped. Please try again.',

];
