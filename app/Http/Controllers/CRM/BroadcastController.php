<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;

class BroadcastController extends Controller
{
    /**
     * Display the broadcast notification console.
     */
    public function index()
    {
        return view('crm.broadcasts.index');
    }
}


