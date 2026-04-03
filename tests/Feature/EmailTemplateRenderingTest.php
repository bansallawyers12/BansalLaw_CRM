<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\View;

class EmailTemplateRenderingTest extends TestCase
{
    /** @test */
    public function signature_send_template_renders_correctly()
    {
        $data = [
            'signerName' => 'John Doe',
            'documentTitle' => 'Service Agreement',
            'signingUrl' => 'https://example.com/sign/123/token',
            'message' => 'Please sign this document.',
            'documentType' => 'agreement',
            'dueDate' => 'January 15, 2025'
        ];

        $html = View::make('emails.signature.send', $data)->render();

        // Assert key content is present
        $this->assertStringContainsString('John Doe', $html);
        $this->assertStringContainsString('Service Agreement', $html);
        $this->assertStringContainsString('https://example.com/sign/123/token', $html);
        $this->assertStringContainsString('Please sign this document.', $html);
        $this->assertStringContainsString('January 15, 2025', $html);
        $this->assertStringContainsString('Bansal Migration', $html);
        
        // Assert branding elements
        $this->assertStringContainsString('Document Signature Request', $html);
        $this->assertStringContainsString('Review & Sign Document', $html);
        
        // Assert footer content
        $this->assertStringContainsString('info@bansalimmigration.com.au', $html);
        $this->assertStringContainsString('www.bansalimmigration.com.au', $html);
    }

    /** @test */
    public function signature_send_template_handles_optional_fields()
    {
        $data = [
            'signerName' => 'Jane Smith',
            'documentTitle' => 'Document',
            'signingUrl' => 'https://example.com/sign/456/token',
            'message' => 'Please sign.',
            'documentType' => 'general',
            'dueDate' => null // Optional field
        ];

        $html = View::make('emails.signature.send', $data)->render();

        $this->assertStringContainsString('Jane Smith', $html);
        $this->assertStringContainsString('Document', $html);
        // Should not crash when dueDate is null
        $this->assertIsString($html);
        $this->assertNotEmpty($html);
    }

    /** @test */
    public function signature_send_agreement_template_renders_correctly()
    {
        $data = [
            'signerName' => 'John Client',
            'documentTitle' => 'Cost Agreement',
            'signingUrl' => 'https://example.com/sign/789/token',
            'message' => 'Please review and sign this agreement.',
            'documentType' => 'agreement',
            'dueDate' => 'February 1, 2025'
        ];

        $html = View::make('emails.signature.send_agreement', $data)->render();

        // Assert key content is present
        $this->assertStringContainsString('John Client', $html);
        $this->assertStringContainsString('Cost Agreement', $html);
        $this->assertStringContainsString('https://example.com/sign/789/token', $html);
        $this->assertStringContainsString('Please review and sign this agreement.', $html);
        
        // Assert agreement-specific elements
        $this->assertStringContainsString('Agreement Signature Request', $html);
        $this->assertStringContainsString('Review & Sign Agreement', $html);
        $this->assertStringContainsString('Important Legal Notice', $html);
        
        // Assert green theme (agreement template uses green)
        $this->assertStringContainsString('#047857', $html);
        $this->assertStringContainsString('#10b981', $html);
    }

    /** @test */
    public function signature_reminder_template_renders_correctly()
    {
        $data = [
            'signerName' => 'Bob Wilson',
            'documentTitle' => 'Pending Document',
            'signingUrl' => 'https://example.com/sign/999/token',
            'reminderNumber' => 2,
            'dueDate' => 'January 20, 2025'
        ];

        $html = View::make('emails.signature.reminder', $data)->render();

        // Assert key content is present
        $this->assertStringContainsString('Bob Wilson', $html);
        $this->assertStringContainsString('Pending Document', $html);
        $this->assertStringContainsString('https://example.com/sign/999/token', $html);
        $this->assertStringContainsString('January 20, 2025', $html);
        $this->assertStringContainsString('Reminder #2', $html);
        
        // Assert reminder-specific elements
        $this->assertStringContainsString('Document Signature Reminder', $html);
        $this->assertStringContainsString('Sign Now', $html);
        $this->assertStringContainsString('Action Required', $html);
        
        // Assert urgent theme (reminder template uses red/orange)
        $this->assertStringContainsString('#dc2626', $html);
        $this->assertStringContainsString('#f59e0b', $html);
    }

    /** @test */
    public function all_templates_have_responsive_design()
    {
        $data = [
            'signerName' => 'Test User',
            'documentTitle' => 'Test Document',
            'signingUrl' => 'https://example.com/sign/test/token',
            'message' => 'Test message',
            'documentType' => 'general',
            'dueDate' => 'Test Date',
            'reminderNumber' => 1
        ];

        $templates = [
            'emails.signature.send',
            'emails.signature.send_agreement',
            'emails.signature.reminder'
        ];

        foreach ($templates as $template) {
            $html = View::make($template, $data)->render();
            
            // Assert responsive meta tag
            $this->assertStringContainsString('viewport', $html);
            
            // Assert media queries for mobile
            $this->assertStringContainsString('@media only screen and (max-width: 600px)', $html);
            
            // Assert max-width container
            $this->assertStringContainsString('max-width: 600px', $html);
        }
    }

    /** @test */
    public function all_templates_have_proper_html_structure()
    {
        $data = [
            'signerName' => 'Test User',
            'documentTitle' => 'Test Document',
            'signingUrl' => 'https://example.com/sign/test/token',
            'message' => 'Test message',
            'documentType' => 'general',
            'dueDate' => 'Test Date',
            'reminderNumber' => 1
        ];

        $templates = [
            'emails.signature.send',
            'emails.signature.send_agreement',
            'emails.signature.reminder'
        ];

        foreach ($templates as $template) {
            $html = View::make($template, $data)->render();
            
            // Assert proper HTML structure
            $this->assertStringContainsString('<!DOCTYPE html>', $html);
            $this->assertStringContainsString('<html', $html);
            $this->assertStringContainsString('</html>', $html);
            $this->assertStringContainsString('<head>', $html);
            $this->assertStringContainsString('</head>', $html);
            $this->assertStringContainsString('<body>', $html);
            $this->assertStringContainsString('</body>', $html);
            
            // Assert UTF-8 charset
            $this->assertStringContainsString('charset="UTF-8"', $html);
        }
    }

    /** @test */
    public function templates_escape_user_input_properly()
    {
        $data = [
            'signerName' => '<script>alert("xss")</script>John',
            'documentTitle' => '<img src=x onerror=alert(1)>',
            'signingUrl' => 'https://example.com/sign/test/token',
            'message' => '<b>Bold</b> and <script>dangerous</script>',
            'documentType' => 'general',
            'dueDate' => null,
            'reminderNumber' => 1
        ];

        $templates = [
            'emails.signature.send',
            'emails.signature.send_agreement',
            'emails.signature.reminder'
        ];

        foreach ($templates as $template) {
            $html = View::make($template, $data)->render();
            
            // Assert dangerous scripts are escaped or removed
            $this->assertStringNotContainsString('<script>alert("xss")</script>', $html);
            $this->assertStringNotContainsString('onerror=alert(1)', $html);
            
            // In send_agreement, message uses {!! !!} with nl2br(e())
            // So <b> tags should be escaped but newlines preserved
            if ($template === 'emails.signature.send_agreement') {
                $this->assertStringContainsString('&lt;b&gt;Bold&lt;/b&gt;', $html);
            }
        }
    }

    /** @test */
    public function templates_have_call_to_action_buttons()
    {
        $data = [
            'signerName' => 'Test User',
            'documentTitle' => 'Test Document',
            'signingUrl' => 'https://example.com/sign/test/token',
            'message' => 'Test message',
            'documentType' => 'general',
            'dueDate' => null,
            'reminderNumber' => 1
        ];

        $templates = [
            'emails.signature.send',
            'emails.signature.send_agreement',
            'emails.signature.reminder'
        ];

        foreach ($templates as $template) {
            $html = View::make($template, $data)->render();
            
            // Assert CTA button is present
            $this->assertStringContainsString('cta-button', $html);
            $this->assertStringContainsString('https://example.com/sign/test/token', $html);
            
            // Assert button text varies by template
            if ($template === 'emails.signature.reminder') {
                $this->assertStringContainsString('Sign Now', $html);
            } else {
                $this->assertStringContainsString('Review & Sign', $html);
            }
        }
    }

    /** @test */
    public function templates_include_footer_with_contact_information()
    {
        $data = [
            'signerName' => 'Test User',
            'documentTitle' => 'Test Document',
            'signingUrl' => 'https://example.com/sign/test/token',
            'message' => 'Test message',
            'documentType' => 'general',
            'dueDate' => null,
            'reminderNumber' => 1
        ];

        $templates = [
            'emails.signature.send',
            'emails.signature.send_agreement',
            'emails.signature.reminder'
        ];

        foreach ($templates as $template) {
            $html = View::make($template, $data)->render();
            
            // Assert footer information
            $this->assertStringContainsString('Bansal Migration', $html);
            $this->assertStringContainsString('info@bansalimmigration.com.au', $html);
            $this->assertStringContainsString('www.bansalimmigration.com.au', $html);
            $this->assertStringContainsString('Privacy Policy', $html);
            $this->assertStringContainsString('Terms of Service', $html);
        }
    }
}

