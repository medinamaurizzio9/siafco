<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PlaceholderController extends Controller
{
    public function __invoke(Request $request)
    {
        return view('placeholder', [
            'title' => $request->route('title', 'Modulo en preparacion'),
            'message' => $request->route('message', 'Esta seccion esta preparada para una fase posterior.'),
        ]);
    }
}
