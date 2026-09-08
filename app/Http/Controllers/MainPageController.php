<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class MainPageController extends Controller
{
    public function show(): View
    {
        return view('pages.home');
    }
}
