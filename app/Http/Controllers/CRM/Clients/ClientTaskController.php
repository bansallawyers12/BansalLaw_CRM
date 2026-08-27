<?php

namespace App\Http\Controllers\CRM\Clients;

use App\Http\Controllers\Controller;
use App\Services\ClientTaskActionService;
use Illuminate\Http\Request;

/**
 * Client/lead action (Note-based task) endpoints extracted from ClientsController.
 */
class ClientTaskController extends Controller
{
    public function __construct(
        private readonly ClientTaskActionService $tasks
    ) {
        $this->middleware('auth:admin');
    }

    public function store(Request $request)
    {
        return $this->tasks->taskStore($request);
    }

    public function storePersonal(Request $request)
    {
        return $this->tasks->storePersonalTask($request);
    }

    public function update(Request $request)
    {
        return $this->tasks->updateTask($request);
    }

    public function reassign(Request $request)
    {
        return $this->tasks->reassignTask($request);
    }
}
