<?php

namespace App\Console\Commands;

use App\Http\Controllers\CRM\OutlookAddinController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateOutlookAddinManifest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'outlook:manifest
                            {--url= : Target HTTPS base URL (e.g. https://legal.bansalcrm.com or a tunnel URL)}
                            {--production : Generate production manifests for the live CRM URL}
                            {--local : Generate local/tunnel manifests for Outlook sideloading}
                            {--all : Generate both production and local manifests}
                            {--output= : Custom path to save the XML file (defaults by mode)}
                            {--stdout : Output raw XML to stdout instead of writing to file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Microsoft Office Outlook add-in manifests for production and/or local HTTPS tunnels';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $controller = app(OutlookAddinController::class);
        $productionUrl = rtrim((string) config('services.outlook_addin.production_url', 'https://legal.bansalcrm.com'), '/');
        $localUrl = rtrim((string) ($this->option('url') ?: config('services.outlook_addin.base_url') ?: ''), '/');

        $wantProduction = (bool) $this->option('production') || (bool) $this->option('all');
        $wantLocal = (bool) $this->option('local') || (bool) $this->option('all');

        // Default: if neither mode flag is set, use --url / env, else production URL.
        if (! $wantProduction && ! $wantLocal) {
            if ($this->option('url') || (app()->environment('local') && $localUrl !== '')) {
                $wantLocal = true;
            } else {
                $wantProduction = true;
            }
        }

        if ($this->option('stdout')) {
            $rawUrl = (string) ($this->option('url') ?: ($wantLocal && $localUrl !== '' ? $localUrl : $productionUrl));
            $rawUrl = $this->normalizeHttpsUrl($rawUrl);
            $this->output->write($controller->buildManifestXml($rawUrl));

            return self::SUCCESS;
        }

        $written = 0;

        if ($wantProduction) {
            $url = $this->normalizeHttpsUrl($this->option('url') && ! $wantLocal ? (string) $this->option('url') : $productionUrl);
            $path = $this->option('output') && ! $wantLocal
                ? (string) $this->option('output')
                : public_path('outlook-addin/manifest.production.xml');
            $this->writeManifest($controller, $url, $path);
            // Canonical deployable copy always tracks production when generating prod.
            if (! $this->option('output')) {
                $this->writeManifest($controller, $url, public_path('outlook-addin/manifest.xml'));
            }
            $written++;
        }

        if ($wantLocal) {
            if ($localUrl === '') {
                $this->error('Local manifest needs a tunnel URL. Pass --url=https://YOUR-TUNNEL.trycloudflare.com or set OUTLOOK_ADDIN_BASE_URL in .env');

                return self::FAILURE;
            }
            $url = $this->normalizeHttpsUrl($localUrl);
            $path = $this->option('output') && ! $wantProduction
                ? (string) $this->option('output')
                : public_path('outlook-addin/manifest.local.xml');
            $this->writeManifest($controller, $url, $path);
            // Only overwrite manifest.xml with tunnel URLs when generating local alone
            // (keeps committed/deployable manifest.xml pointing at production when using --all).
            if (! $wantProduction && ! $this->option('output')) {
                $this->writeManifest($controller, $url, public_path('outlook-addin/manifest.xml'));
            }
            $written++;
        }

        if ($written === 0) {
            $this->error('Nothing to generate. Use --production, --local, --all, or --url=...');

            return self::FAILURE;
        }

        $this->line('');
        $this->line('Deployment:');
        $this->line("  Production (M365): {$productionUrl}/outlook-addin/manifest.xml");
        $this->line('  Or upload: public/outlook-addin/manifest.production.xml');
        $this->line('  Local sideload: public/outlook-addin/manifest.local.xml');

        return self::SUCCESS;
    }

    protected function normalizeHttpsUrl(string $rawUrl): string
    {
        $rawUrl = rtrim(trim($rawUrl), '/');

        if ($rawUrl === '' || str_starts_with($rawUrl, 'http://')) {
            $rawUrl = 'https://' . preg_replace('#^https?://#', '', $rawUrl);
        }

        return rtrim($rawUrl, '/');
    }

    protected function writeManifest(OutlookAddinController $controller, string $baseUrl, string $outputPath): void
    {
        File::ensureDirectoryExists(dirname($outputPath));
        File::put($outputPath, $controller->buildManifestXml($baseUrl));

        $this->info('✓ Outlook Add-in manifest generated');
        $this->line("  Base URL:  <comment>{$baseUrl}</comment>");
        $this->line("  Saved to:  <comment>{$outputPath}</comment>");
    }
}
