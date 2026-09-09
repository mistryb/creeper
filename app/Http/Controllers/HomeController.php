<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * The landing page.
 */
class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('welcome');
    }
}
