<?php

namespace App\Jobs;

use App\Services\LegalFormScopeAiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateLegalFormScopeAiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 1;

    /**
     * @param  array{client_id:int,client_matter_id:?int,matter_reference:?string,form_type:string,field:string}  $payload
     */
    public function __construct(
        public string $jobId,
        public array $payload,
        public int $staffId,
    ) {
        $this->timeout = max(60, (int) config('crm.legal_forms.ai_timeout_seconds', 120));
    }

    public function handle(LegalFormScopeAiService $service): void
    {
        $service->run($this->jobId, $this->payload, $this->staffId);
    }
}
