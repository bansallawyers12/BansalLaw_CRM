<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Matter workflow HTTP surface (stages, deadlines, discontinue/reopen/delete).
 * Delegates to ClientMatterHubController to avoid duplicating large matter logic.
 */
class ClientMatterWorkflowController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    private function hub(): ClientMatterHubController
    {
        return app(ClientMatterHubController::class);
    }

    public function loadMatterUpsert(Request $request)
    {
        return $this->hub()->loadMatterUpsert($request);
    }

    public function getClientPortalDetail(Request $request)
    {
        return $this->hub()->getClientPortalDetail($request);
    }

    public function completestage(Request $request)
    {
        return $this->hub()->completestage($request);
    }

    public function updatestage(Request $request)
    {
        return $this->hub()->updatestage($request);
    }

    public function updatebackstage(Request $request)
    {
        return $this->hub()->updatebackstage($request);
    }

    public function updateClientMatterNextStage(Request $request)
    {
        return $this->hub()->updateClientMatterNextStage($request);
    }

    public function updateClientMatterPreviousStage(Request $request)
    {
        return $this->hub()->updateClientMatterPreviousStage($request);
    }

    public function changeClientMatterWorkflow(Request $request)
    {
        return $this->hub()->changeClientMatterWorkflow($request);
    }

    public function discontinueClientMatter(Request $request)
    {
        return $this->hub()->discontinueClientMatter($request);
    }

    public function reopenClientMatter(Request $request)
    {
        return $this->hub()->reopenClientMatter($request);
    }

    public function deleteClientMatter(Request $request)
    {
        return $this->hub()->deleteClientMatter($request);
    }

    public function updateClientMatterDeadline(Request $request)
    {
        return $this->hub()->updateClientMatterDeadline($request);
    }
}
