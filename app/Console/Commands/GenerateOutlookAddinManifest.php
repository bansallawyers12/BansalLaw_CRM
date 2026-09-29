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
                            {--url= : Target HTTPS base URL for the CRM instance (e.g. https://crm.bansallawyers.com.au or https://your-tunnel.loca.lt)}
                            {--output= : Custom path to save the XML file (defaults to public/outlook-addin/manifest.xml)}
                            {--stdout : Output raw XML to stdout instead of writing to file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and export a Microsoft Office manifest.xml configured for production, staging, or local development';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $rawUrl = (string) ($this->option('url') ?: config('services.outlook_addin.base_url', config('app.url')));
        $rawUrl = rtrim(trim($rawUrl), '/');

        if (empty($rawUrl) || $rawUrl === 'http://localhost' || $rawUrl === 'http://127.0.0.1:8000') {
            $this->warn("Specified/configured URL is '{$rawUrl}'.");
            $this->warn("Microsoft Office requires an HTTPS URL (https://) for manifest icons and taskpane locations.");
            $rawUrl = 'https://' . preg_replace('#^https?://#', '', $rawUrl);
        }

        if (str_starts_with($rawUrl, 'http://')) {
            $rawUrl = 'https://' . substr($rawUrl, 7);
        }

        $controller = app(OutlookAddinController::class);
        $manifestXml = $controller->buildManifestXml($rawUrl);

        if ($this->option('stdout')) {
            $this->output->write($manifestXml);
            return self::SUCCESS;
        }

        $outputPath = $this->option('output') ?: public_path('outlook-addin/manifest.xml');
        File::ensureDirectoryExists(dirname($outputPath));
        File::put($outputPath, $manifestXml);

        $this->info("✓ Outlook Add-in manifest generated successfully!");
        $this->line("  Base URL:  <comment>{$rawUrl}</comment>");
        $this->line("  Saved to:  <comment>{$outputPath}</comment>");
        $this->line("");
        $this->line("Deployment options:");
        $this->line("  1. Microsoft 365 Admin Center: Deploy centrally for all firm staff via Integrated Apps using this file or URL: {$rawUrl}/outlook-addin/manifest.xml");
        $this->line("  2. Sideload in Outlook: In Outlook Apps > Manage add-ins > My add-ins > Add a custom add-in > Add from File.");

        return self::SUCCESS;
    }
}
