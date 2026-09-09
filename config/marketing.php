<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Marketing Mode
    |--------------------------------------------------------------------------
    |
    | Whether this install has a public face. Off by default: a self-hosted
    | copy, a staging environment or a private install has no one to pitch to,
    | so "/" is the way in and nothing else. Turn this on to serve the landing
    | page there instead, with the sign-in page one link away.
    |
    */

    'enabled' => (bool) env('MARKETING_MODE', false),

];
