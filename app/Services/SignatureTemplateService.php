<?php

namespace App\Services;

class SignatureTemplateService
{
    /**
     * Get available signature templates.
     */
    public static function getTemplates(): array
    {
        return [
            'custom' => [
                'name' => 'Custom',
                'description' => 'Create your own signature from scratch',
                'html' => '',
                'css' => '',
            ],
            'professional' => [
                'name' => 'Professional',
                'description' => 'Clean and professional business signature',
                'html' => self::getProfessionalTemplate(),
                'css' => self::getProfessionalCSS(),
            ],
            'business' => [
                'name' => 'Business',
                'description' => 'Corporate-style signature with logo space',
                'html' => self::getBusinessTemplate(),
                'css' => self::getBusinessCSS(),
            ],
            'creative' => [
                'name' => 'Creative',
                'description' => 'Modern design with social media integration',
                'html' => self::getCreativeTemplate(),
                'css' => self::getCreativeCSS(),
            ],
        ];
    }

    /**
     * Get a specific template.
     */
    public static function getTemplate(string $type): ?array
    {
        $templates = self::getTemplates();
        return $templates[$type] ?? null;
    }

    /**
     * Professional template HTML.
     */
    private static function getProfessionalTemplate(): string
    {
        return '
        <div style="font-family: \'Aptos Display\', Arial, sans-serif; font-size: 15px; line-height: 1.4; color: #333; max-width: 600px;">
            <p style="margin: 0 0 15px 0; color: #333;">Kind Regards,</p>
            
            <div style="display: flex; align-items: flex-start; margin-bottom: 15px;">
                <div style="width: 60px; height: 60px; background: #1e40af; margin-right: 15px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 18px; border-radius: 4px;">B</div>
                <div style="flex: 1;">
                    <h3 style="font-size: 18px; font-weight: bold; margin: 0 0 5px 0; color: #1e40af;">AJAY BANSAL</h3>
                    <p style="font-size: 14px; font-weight: 600; margin: 0 0 3px 0; color: #374151;">PRINCIPAL SOLICITOR</p>
                    <p style="font-size: 12px; margin: 0; color: #6b7280;">BSc. L.L.B. (Deakin), Grad Dip of Legal Practice, Grad Cert Australian Migration Law.</p>
                </div>
            </div>
            
            <div style="margin: 15px 0;">
                <p style="margin: 3px 0; font-size: 12px; color: #374151;"><strong>T:</strong> 03 9602 1330</p>
                <p style="margin: 3px 0; font-size: 12px; color: #374151;"><strong>M:</strong> 0404 000 058</p>
                <p style="margin: 3px 0; font-size: 12px; color: #374151;"><strong>W:</strong> <a href="http://www.bansalimmigration.com.au" style="color: #1e40af; text-decoration: none;">www.bansalimmigration.com.au</a></p>
                <p style="margin: 3px 0; font-size: 12px; color: #374151;"><strong>E:</strong> <a href="mailto:arun@bansalimmigration.com.au" style="color: #1e40af; text-decoration: none;">arun@bansalimmigration.com.au</a></p>
                <p style="margin: 3px 0; font-size: 12px; color: #374151;"><strong>A:</strong> Level 8/278 Collins St, Melbourne 3000</p>
            </div>
            
            <div style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #e5e7eb;">
                <p style="font-style: italic; font-size: 11px; color: #6b7280; margin: 0;">We are here to serve you</p>
            </div>
        </div>';
    }

    /**
     * Professional template CSS.
     */
    private static function getProfessionalCSS(): string
    {
        return '
        .signature-professional {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            max-width: 600px;
        }
        .signature-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .signature-logo {
            width: 60px;
            height: 60px;
            margin-right: 15px;
            background: #f0f0f0;
            border: 1px solid #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: #666;
        }
        .signature-name {
            font-size: 16px;
            font-weight: bold;
            margin: 0 0 5px 0;
            color: #1e40af;
        }
        .signature-title {
            font-size: 14px;
            font-weight: 600;
            margin: 0 0 3px 0;
            color: #374151;
        }
        .signature-company {
            font-size: 12px;
            margin: 0;
            color: #6b7280;
        }
        .signature-contact {
            margin: 10px 0;
        }
        .contact-item {
            margin: 2px 0;
            font-size: 11px;
        }
        .contact-label {
            font-weight: bold;
            color: #374151;
            margin-right: 5px;
        }
        .signature-footer {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
        }
        .signature-slogan {
            font-style: italic;
            font-size: 11px;
            color: #6b7280;
            margin: 0;
        }';
    }

    /**
     * Business template HTML.
     */
    private static function getBusinessTemplate(): string
    {
        return '
        <div class="signature-business">
            <div class="signature-content">
                <div class="signature-header">
                    <div class="signature-logo-section">
                        <div class="signature-logo">
                            <!-- Logo will be inserted here -->
                        </div>
                        <div class="signature-brand">
                            <h2 class="company-name">[COMPANY NAME]</h2>
                            <p class="company-tagline">[Company Tagline]</p>
                        </div>
                    </div>
                </div>
                <div class="signature-body">
                    <div class="signature-personal">
                        <h3 class="person-name">[Your Name]</h3>
                        <p class="person-title">[Your Title]</p>
                        <p class="person-credentials">[Credentials/Qualifications]</p>
                    </div>
                    <div class="signature-contact">
                        <div class="contact-grid">
                            <div class="contact-item">
                                <span class="contact-icon">📞</span>
                                <span class="contact-text">[Phone]</span>
                            </div>
                            <div class="contact-item">
                                <span class="contact-icon">📱</span>
                                <span class="contact-text">[Mobile]</span>
                            </div>
                            <div class="contact-item">
                                <span class="contact-icon">🌐</span>
                                <span class="contact-text">[Website]</span>
                            </div>
                            <div class="contact-item">
                                <span class="contact-icon">✉️</span>
                                <span class="contact-text">[Email]</span>
                            </div>
                            <div class="contact-item">
                                <span class="contact-icon">📍</span>
                                <span class="contact-text">[Address]</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="signature-social">
                    <a href="#" class="social-link">Facebook</a>
                    <a href="#" class="social-link">LinkedIn</a>
                    <a href="#" class="social-link">Twitter</a>
                    <a href="#" class="social-link">Instagram</a>
                </div>
            </div>
        </div>';
    }

    /**
     * Business template CSS.
     */
    private static function getBusinessCSS(): string
    {
        return '
        .signature-business {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            max-width: 600px;
            border-left: 4px solid #1e40af;
            padding-left: 15px;
        }
        .signature-logo-section {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .signature-logo {
            width: 80px;
            height: 80px;
            margin-right: 15px;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: #64748b;
        }
        .company-name {
            font-size: 18px;
            font-weight: bold;
            margin: 0 0 5px 0;
            color: #1e40af;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .company-tagline {
            font-size: 11px;
            margin: 0;
            color: #64748b;
            font-style: italic;
        }
        .signature-body {
            display: flex;
            gap: 20px;
        }
        .signature-personal {
            flex: 1;
        }
        .person-name {
            font-size: 16px;
            font-weight: bold;
            margin: 0 0 5px 0;
            color: #1e40af;
        }
        .person-title {
            font-size: 13px;
            font-weight: 600;
            margin: 0 0 3px 0;
            color: #374151;
        }
        .person-credentials {
            font-size: 11px;
            margin: 0;
            color: #6b7280;
        }
        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 5px;
        }
        .contact-item {
            display: flex;
            align-items: center;
            font-size: 11px;
        }
        .contact-icon {
            margin-right: 5px;
            font-size: 10px;
        }
        .contact-text {
            color: #374151;
        }
        .signature-social {
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
        }
        .social-link {
            display: inline-block;
            margin-right: 10px;
            font-size: 11px;
            color: #1e40af;
            text-decoration: none;
        }
        .social-link:hover {
            text-decoration: underline;
        }';
    }

    /**
     * Creative template HTML.
     */
    private static function getCreativeTemplate(): string
    {
        return '
        <div class="signature-creative">
            <div class="signature-content">
                <div class="signature-header">
                    <div class="signature-logo">
                        <!-- Logo will be inserted here -->
                    </div>
                    <div class="signature-brand">
                        <h2 class="company-name">[COMPANY NAME]</h2>
                        <p class="company-slogan">[Company Slogan]</p>
                    </div>
                </div>
                <div class="signature-body">
                    <div class="signature-personal">
                        <h3 class="person-name">[Your Name]</h3>
                        <p class="person-title">[Your Title]</p>
                        <p class="person-credentials">[Credentials]</p>
                    </div>
                    <div class="signature-contact">
                        <div class="contact-list">
                            <div class="contact-item">
                                <span class="contact-label">T:</span> [Phone]
                            </div>
                            <div class="contact-item">
                                <span class="contact-label">M:</span> [Mobile]
                            </div>
                            <div class="contact-item">
                                <span class="contact-label">W:</span> [Website]
                            </div>
                            <div class="contact-item">
                                <span class="contact-label">E:</span> [Email]
                            </div>
                            <div class="contact-item">
                                <span class="contact-label">A:</span> [Address]
                            </div>
                        </div>
                    </div>
                </div>
                <div class="signature-social">
                    <div class="social-icons">
                        <a href="#" class="social-icon">f</a>
                        <a href="#" class="social-icon">📷</a>
                        <a href="#" class="social-icon">🎵</a>
                        <a href="#" class="social-icon">P</a>
                        <a href="#" class="social-icon">▶</a>
                    </div>
                </div>
                <div class="signature-cta">
                    <a href="#" class="cta-button">Click to Book Appointment</a>
                </div>
                <div class="signature-disclaimer">
                    <p class="disclaimer-text">[Legal Disclaimer]</p>
                </div>
            </div>
        </div>';
    }

    /**
     * Creative template CSS.
     */
    private static function getCreativeCSS(): string
    {
        return '
        .signature-creative {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            max-width: 600px;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
        }
        .signature-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #1e40af;
        }
        .signature-logo {
            width: 100px;
            height: 100px;
            margin-right: 20px;
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: white;
            font-weight: bold;
        }
        .company-name {
            font-size: 20px;
            font-weight: bold;
            margin: 0 0 5px 0;
            color: #1e40af;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .company-slogan {
            font-size: 12px;
            margin: 0;
            color: #64748b;
            font-style: italic;
        }
        .signature-body {
            display: flex;
            gap: 30px;
            margin-bottom: 20px;
        }
        .signature-personal {
            flex: 1;
        }
        .person-name {
            font-size: 18px;
            font-weight: bold;
            margin: 0 0 8px 0;
            color: #1e40af;
        }
        .person-title {
            font-size: 14px;
            font-weight: 600;
            margin: 0 0 5px 0;
            color: #374151;
        }
        .person-credentials {
            font-size: 11px;
            margin: 0;
            color: #6b7280;
        }
        .contact-list {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        .contact-item {
            font-size: 11px;
        }
        .contact-label {
            font-weight: bold;
            color: #1e40af;
            margin-right: 5px;
        }
        .signature-social {
            margin-bottom: 15px;
        }
        .social-icons {
            display: flex;
            gap: 5px;
        }
        .social-icon {
            display: inline-block;
            width: 25px;
            height: 25px;
            background: #1e40af;
            color: white;
            text-align: center;
            line-height: 25px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 10px;
            font-weight: bold;
        }
        .signature-cta {
            margin-bottom: 15px;
        }
        .cta-button {
            display: inline-block;
            background: #1e40af;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 11px;
            font-weight: bold;
        }
        .signature-disclaimer {
            font-size: 9px;
            color: #64748b;
            font-style: italic;
            line-height: 1.3;
        }
        .disclaimer-text {
            margin: 0;
        }';
    }
}
