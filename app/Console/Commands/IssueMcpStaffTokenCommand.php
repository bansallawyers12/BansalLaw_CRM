<?php

namespace App\Console\Commands;

use App\Models\Staff;
use Illuminate\Console\Command;

class IssueMcpStaffTokenCommand extends Command
{
    protected $signature = 'mcp:issue-token
                            {email : Staff email}
                            {--name=Grok Bot CRM MCP : Sanctum token name}
                            {--revoke-others : Revoke existing tokens with the same name for this staff}';

    protected $description = 'Issue a Sanctum personal access token for read-only CRM MCP access';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $tokenName = (string) $this->option('name');

        $staff = Staff::query()->where('email', $email)->first();
        if (! $staff) {
            $this->error("No staff found with email [{$email}].");

            return self::FAILURE;
        }

        if ((int) ($staff->status ?? 0) !== 1) {
            $this->warn('Warning: this staff account status is not active (status != 1).');
        }

        if ($this->option('revoke-others')) {
            $staff->tokens()->where('name', $tokenName)->delete();
            $this->info("Revoked existing tokens named [{$tokenName}] for {$email}.");
        }

        $expirationMinutes = (int) config('sanctum.expiration', 10080);
        $expiresAt = $expirationMinutes > 0 ? now()->addMinutes($expirationMinutes) : null;
        $token = $staff->createToken($tokenName, ['*'], $expiresAt)->plainTextToken;

        $this->newLine();
        $this->info('MCP endpoint: '.rtrim((string) config('app.url'), '/').'/mcp/crm');
        $this->info("Staff: {$staff->first_name} {$staff->last_name} <{$staff->email}> (id {$staff->id})");
        $this->line('Authorization header:');
        $this->line('  Bearer '.$token);
        $this->newLine();
        $this->warn('Store this token as a secret. It will not be shown again.');

        return self::SUCCESS;
    }
}
