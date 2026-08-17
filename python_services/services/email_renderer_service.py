"""
Email Renderer Service

Provides enhanced HTML email rendering capabilities including:
- HTML content cleaning and sanitization
- CSS inlining for better email client compatibility
- Image processing and optimization
- Link tracking and security
- Responsive email templates
- Text preview generation
"""

import re
import json
import base64
import mimetypes
from typing import Dict, List, Any, Optional, Tuple
from datetime import datetime
from urllib.parse import urlparse, urljoin
from pathlib import Path

try:
    from bs4 import BeautifulSoup
except ImportError:
    BeautifulSoup = None

from utils.logger import setup_logger
from utils.datetime_format import format_laravel_datetime, DEFAULT_TIMEZONE

logger = setup_logger(__name__, 'email_renderer.log')


class EmailRendererService:
    """Service for rendering email content with enhanced HTML and styling."""

    MAX_INLINE_IMAGE_BYTES_FOR_PDF = 5 * 1024 * 1024
    
    def __init__(self):
        self.safe_tags = {
            'p', 'div', 'span', 'br', 'hr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'del', 'ins',
            'ul', 'ol', 'li', 'dl', 'dt', 'dd',
            'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
            'a', 'img', 'blockquote', 'pre', 'code',
            'font', 'center', 'small', 'big'
        }
        
        self.safe_attributes = {
            'href', 'src', 'alt', 'title', 'width', 'height', 'border',
            'cellpadding', 'cellspacing', 'colspan', 'rowspan',
            'style', 'class', 'id', 'align', 'valign',
            'color', 'size', 'face', 'bgcolor'
        }
        
        logger.info("Email Renderer Service initialized")
    
    def _format_sent_date(self, sent_date: Any, display_timezone: Optional[str] = None) -> str:
        """Format sent/received date for PDF and HTML display (Laravel dd/mm/yyyy h:i a)."""
        return format_laravel_datetime(sent_date, display_timezone or DEFAULT_TIMEZONE)

    def render_email(self, email_data: Dict[str, Any], display_timezone: Optional[str] = None) -> Dict[str, Any]:
        """
        Render email content with enhanced HTML and styling.
        
        Args:
            email_data: Dictionary containing email data
        
        Returns:
            Dict containing rendering results
        """
        try:
            logger.info(f"Rendering email: {email_data.get('subject', 'No subject')}")
            
            html_content = email_data.get('html_content', '')
            text_content = email_data.get('text_content', '')
            subject = email_data.get('subject', '')
            sender_name = email_data.get('sender_name', '')
            sender_email = email_data.get('sender_email', '')

            # Never dump raw ICS into the PDF/HTML body — use a readable summary.
            if self._is_calendar_payload(text_content):
                text_content = self._summarize_calendar_payload(text_content)
            if self._is_calendar_payload(html_content):
                if not (text_content or '').strip():
                    text_content = self._summarize_calendar_payload(html_content)
                html_content = ''
            
            # Clean and enhance HTML content
            enhanced_html = self._clean_and_enhance_html(html_content)
            
            # Create responsive email template
            rendered_html = self._create_responsive_template(
                subject=subject,
                html_content=enhanced_html,
                text_content=text_content,
                sender_name=sender_name,
                sender_email=sender_email,
                email_data=email_data,
                display_timezone=display_timezone,
            )
            
            # Extract and process images
            images = self._extract_images(enhanced_html)
            
            # Process links
            links = self._process_links(enhanced_html)
            
            # Generate text preview
            text_preview = self._create_text_preview(text_content or enhanced_html)
            
            result = {
                'rendered_html': rendered_html,
                'enhanced_html': enhanced_html,
                'images': images,
                'links': links,
                'text_preview': text_preview,
                'rendering_timestamp': datetime.now().isoformat()
            }
            
            logger.info("Email rendering completed successfully")
            
            return result
            
        except Exception as e:
            logger.error(f"Error rendering email: {str(e)}")
            return {
                'rendered_html': email_data.get('html_content', ''),
                'enhanced_html': email_data.get('html_content', ''),
                'images': [],
                'links': [],
                'text_preview': email_data.get('text_content', ''),
                'rendering_timestamp': datetime.now().isoformat(),
                'error': str(e)
            }
    
    def _clean_and_enhance_html(self, html_content: str) -> str:
        """Clean and enhance HTML content."""
        if not html_content:
            return ""
        
        try:
            if BeautifulSoup:
                soup = BeautifulSoup(html_content, 'html.parser')
                
                # Remove dangerous elements
                for element in soup.find_all(['script', 'iframe', 'object', 'embed', 'form', 'input', 'button']):
                    element.decompose()
                
                # Remove dangerous attributes
                for tag in soup.find_all():
                    for attr in list(tag.attrs.keys()):
                        if attr.startswith('on') or attr in ['javascript:', 'vbscript:']:
                            del tag.attrs[attr]
                
                # Clean up empty tags
                for tag in soup.find_all():
                    if not tag.get_text(strip=True) and not tag.find(['img', 'br', 'hr']):
                        tag.decompose()
                
                return str(soup)
            else:
                # Fallback: basic cleaning using regex
                cleaned = html_content
                
                # Remove dangerous elements
                dangerous_patterns = [
                    r'<script[^>]*>.*?</script>',
                    r'<iframe[^>]*>.*?</iframe>',
                    r'<object[^>]*>.*?</object>',
                    r'<embed[^>]*>.*?</embed>',
                    r'<form[^>]*>.*?</form>',
                    r'<input[^>]*>',
                    r'<button[^>]*>.*?</button>'
                ]
                
                for pattern in dangerous_patterns:
                    cleaned = re.sub(pattern, '', cleaned, flags=re.IGNORECASE | re.DOTALL)
                
                # Remove dangerous attributes
                cleaned = re.sub(r'\s*on\w+\s*=\s*["\'][^"\']*["\']', '', cleaned, flags=re.IGNORECASE)
                cleaned = re.sub(r'\s*javascript\s*:', '', cleaned, flags=re.IGNORECASE)
                cleaned = re.sub(r'\s*vbscript\s*:', '', cleaned, flags=re.IGNORECASE)
                
                return cleaned
                
        except Exception as e:
            logger.warning(f"Error cleaning HTML content: {str(e)}")
            return html_content
    
    def _create_responsive_template(
        self,
        subject: str,
        html_content: str,
        text_content: str,
        sender_name: str,
        sender_email: str,
        email_data: Dict[str, Any],
        display_timezone: Optional[str] = None,
    ) -> str:
        """Create a responsive email template."""
        
        # Extract metadata
        sent_date = email_data.get('sent_date', '')
        recipients = email_data.get('to_recipients') or email_data.get('recipients', [])
        cc_recipients = email_data.get('cc_recipients', [])
        bcc_recipients = email_data.get('bcc_recipients', [])
        
        formatted_date = self._format_sent_date(sent_date, display_timezone) if sent_date else ''
        
        # Outlook / Gmail reading-pane style layout for PDF and HTML preview
        template = f"""
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{self._escape_html(subject)}</title>
    <style>
        body {{
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.5;
            color: #242424;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }}
        .email-container {{
            background: #ffffff;
            max-width: 100%;
        }}
        .email-header {{
            padding: 16px 20px 14px;
            border-bottom: 1px solid #edebe9;
            background: #ffffff;
        }}
        .email-subject {{
            font-size: 18px;
            font-weight: 600;
            margin: 0 0 12px 0;
            color: #242424;
            line-height: 1.35;
        }}
        .email-meta {{
            font-size: 13px;
            color: #616161;
            margin: 0;
            line-height: 1.6;
        }}
        .email-meta strong {{
            color: #424242;
            font-weight: 600;
        }}
        .email-content {{
            padding: 20px;
            overflow-wrap: anywhere;
            word-wrap: break-word;
            max-width: 100%;
            font-size: 14px;
            color: #242424;
        }}
        .email-content img {{
            max-width: 100%;
            height: auto;
        }}
        .email-content table {{
            max-width: 100%;
            border-collapse: collapse;
        }}
        .email-content th,
        .email-content td {{
            padding: 6px 10px;
            vertical-align: top;
            overflow-wrap: anywhere;
            word-wrap: break-word;
        }}
        .email-content a {{
            color: #0f6cbd;
            text-decoration: none;
        }}
        .email-content blockquote {{
            margin: 8px 0 8px 12px;
            padding-left: 12px;
            border-left: 3px solid #c8c6c4;
            color: #424242;
        }}
        .text-preview {{
            white-space: pre-wrap;
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Helvetica, Arial, sans-serif;
            font-size: 14px;
            line-height: 1.5;
        }}
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1 class="email-subject">{self._escape_html(subject)}</h1>
            <div class="email-meta">
                <strong>From:</strong> {self._escape_html(sender_name or sender_email)}<br>
                {f'<strong>To:</strong> {", ".join([self._escape_html(r) for r in recipients[:8]])}' if recipients else ''}
                {f'<br><strong>Cc:</strong> {", ".join([self._escape_html(r) for r in cc_recipients[:8]])}' if cc_recipients else ''}
                {f'<br><strong>Bcc:</strong> {", ".join([self._escape_html(r) for r in bcc_recipients[:8]])}' if bcc_recipients else ''}
                {f'<br><strong>Date:</strong> {formatted_date}' if formatted_date else ''}
            </div>
        </div>
        <div class="email-content">
            {html_content if html_content else f'<div class="text-preview">{self._escape_html(text_content)}</div>'}
        </div>
    </div>
</body>
</html>
"""
        
        return template.strip()
    
    def _extract_images(self, html_content: str) -> List[Dict[str, Any]]:
        """Extract and analyze images from HTML content."""
        if not html_content:
            return []
        
        images = []
        
        try:
            if BeautifulSoup:
                soup = BeautifulSoup(html_content, 'html.parser')
                img_tags = soup.find_all('img')
                
                for img in img_tags:
                    src = img.get('src', '')
                    alt = img.get('alt', '')
                    width = img.get('width', '')
                    height = img.get('height', '')
                    
                    if src:
                        images.append({
                            'src': src,
                            'alt': alt,
                            'width': width,
                            'height': height,
                            'is_inline': src.startswith('data:'),
                            'is_external': src.startswith(('http://', 'https://'))
                        })
            else:
                # Fallback: extract using regex
                img_pattern = r'<img[^>]+src=["\']([^"\']+)["\'][^>]*>'
                matches = re.findall(img_pattern, html_content, re.IGNORECASE)
                
                for src in matches:
                    images.append({
                        'src': src,
                        'alt': '',
                        'width': '',
                        'height': '',
                        'is_inline': src.startswith('data:'),
                        'is_external': src.startswith(('http://', 'https://'))
                    })
        
        except Exception as e:
            logger.warning(f"Error extracting images: {str(e)}")
        
        return images
    
    def _process_links(self, html_content: str) -> List[Dict[str, Any]]:
        """Process and analyze links in HTML content."""
        if not html_content:
            return []
        
        links = []
        
        try:
            if BeautifulSoup:
                soup = BeautifulSoup(html_content, 'html.parser')
                a_tags = soup.find_all('a')
                
                for a in a_tags:
                    href = a.get('href', '')
                    text = a.get_text(strip=True)
                    
                    if href:
                        links.append({
                            'url': href,
                            'text': text,
                            'is_external': href.startswith(('http://', 'https://')),
                            'is_email': href.startswith('mailto:'),
                            'is_suspicious': self._is_suspicious_link(href)
                        })
            else:
                # Fallback: extract using regex
                link_pattern = r'<a[^>]+href=["\']([^"\']+)["\'][^>]*>([^<]*)</a>'
                matches = re.findall(link_pattern, html_content, re.IGNORECASE)
                
                for href, text in matches:
                    links.append({
                        'url': href,
                        'text': text.strip(),
                        'is_external': href.startswith(('http://', 'https://')),
                        'is_email': href.startswith('mailto:'),
                        'is_suspicious': self._is_suspicious_link(href)
                    })
        
        except Exception as e:
            logger.warning(f"Error processing links: {str(e)}")
        
        return links
    
    def _is_suspicious_link(self, url: str) -> bool:
        """Check if a link is suspicious."""
        suspicious_domains = [
            'bit.ly', 'tinyurl.com', 'goo.gl', 't.co', 'ow.ly',
            'shortened', 'redirect', 'click-here'
        ]
        
        try:
            parsed = urlparse(url)
            domain = parsed.netloc.lower()
            
            # Check for suspicious domains
            if any(suspicious in domain for suspicious in suspicious_domains):
                return True
            
            # Check for suspicious patterns
            if any(pattern in url.lower() for pattern in ['phishing', 'malware', 'virus']):
                return True
                
        except:
            pass
        
        return False
    
    def _create_text_preview(self, content: str) -> str:
        """Create a clean text preview of the email content."""
        if not content:
            return ""

        if self._is_calendar_payload(content):
            summary = self._summarize_calendar_payload(content)
            return summary[:2000] if summary else "Calendar invitation"
        
        try:
            if BeautifulSoup:
                soup = BeautifulSoup(content, 'html.parser')
                text = soup.get_text()
            else:
                # Fallback: basic HTML tag removal
                text = re.sub(r'<[^>]+>', '', content)
            
            # Clean up whitespace
            text = re.sub(r'\s+', ' ', text)
            text = text.strip()

            if self._is_calendar_payload(text):
                summary = self._summarize_calendar_payload(text)
                return (summary or "Calendar invitation")[:2000]
            
            # Limit length — enough for quoting in reply/forward, far smaller than full HTML body
            if len(text) > 2000:
                text = text[:2000] + "..."
            
            return text
            
        except Exception as e:
            logger.warning(f"Error creating text preview: {str(e)}")
            return content[:2000] if content else ""

    def _is_calendar_payload(self, value: Any) -> bool:
        if value is None:
            return False
        text = str(value).lstrip('\ufeff').strip()
        if not text:
            return False
        upper = text.upper()
        if upper.startswith('BEGIN:VCALENDAR'):
            return True
        return 'BEGIN:VCALENDAR' in upper[:800] and 'BEGIN:VEVENT' in upper

    def _ics_unfold_and_get(self, ics_text: str, field: str) -> str:
        if not ics_text or not field:
            return ''
        unfolded = re.sub(r'\r?\n[ \t]', '', str(ics_text))
        pattern = rf'(?im)^{re.escape(field)}(?:;[^:]*)?:(.+)$'
        match = re.search(pattern, unfolded)
        if not match:
            return ''
        value = match.group(1).strip()
        value = value.replace('\\n', '\n').replace('\\,', ',').replace('\\;', ';').replace('\\\\', '\\')
        return value.strip()

    def _summarize_calendar_payload(self, value: Any) -> str:
        if not self._is_calendar_payload(value):
            return ''
        text = str(value)
        summary = self._ics_unfold_and_get(text, 'SUMMARY') or 'Calendar invitation'
        location = self._ics_unfold_and_get(text, 'LOCATION')
        description = self._ics_unfold_and_get(text, 'DESCRIPTION')
        dtstart = self._ics_unfold_and_get(text, 'DTSTART')
        dtend = self._ics_unfold_and_get(text, 'DTEND')

        lines = [summary, '', 'This message is a calendar invitation.']
        if dtstart:
            lines.append(f'When: {dtstart}' + (f' – {dtend}' if dtend else ''))
        if location:
            lines.append(f'Where: {location}')
        if description:
            desc = re.sub(r'<[^>]+>', ' ', description)
            desc = re.sub(r'\s+', ' ', desc).strip()
            if desc:
                if len(desc) > 600:
                    desc = desc[:600].rstrip() + '…'
                lines.extend(['', desc])
        return '\n'.join(lines).strip()
    
    def _escape_html(self, text: str) -> str:
        """Escape HTML special characters."""
        if not text:
            return ""
        
        html_escape_table = {
            "&": "&amp;",
            '"': "&quot;",
            "'": "&#x27;",
            ">": "&gt;",
            "<": "&lt;",
        }
        
        return "".join(html_escape_table.get(c, c) for c in str(text))
    
    def _get_default_rendering(self, email_data: Dict[str, Any]) -> Dict[str, Any]:
        """Return default rendering when processing fails."""
        return {
            'rendered_html': email_data.get('html_content', ''),
            'enhanced_html': email_data.get('html_content', ''),
            'images': [],
            'links': [],
            'text_preview': email_data.get('text_content', ''),
            'rendering_timestamp': datetime.now().isoformat(),
            'error': 'Rendering failed'
        }

    def render_to_pdf(
        self,
        email_data: Dict[str, Any],
        display_timezone: Optional[str] = None,
    ) -> Tuple[Optional[bytes], Optional[str], Optional[str], Optional[str]]:
        """
        Render parsed email data to a PDF byte stream.

        Returns:
            (pdf_bytes, text_preview, error_message, renderer_name)
        """
        rendering = self.render_email(email_data, display_timezone=display_timezone)
        text_preview = rendering.get('text_preview') or email_data.get('text_content', '')

        rendered_html = rendering.get('rendered_html', '')
        if not rendered_html:
            return None, text_preview, 'No rendered HTML available for PDF conversion', None

        attachments = email_data.get('attachments') or []
        pdf_html = self._replace_cid_with_data_uris(rendered_html, attachments)
        weasy_html = self._prepare_html_for_pdf(pdf_html)

        weasy_error = None
        try:
            from weasyprint import HTML

            pdf_bytes = HTML(string=weasy_html).write_pdf(
                stylesheets=[self._get_pdf_layout_stylesheet()]
            )
            if pdf_bytes:
                logger.info(
                    f"PDF generated via WeasyPrint for email: {email_data.get('subject', 'No subject')} "
                    f"({len(pdf_bytes)} bytes)"
                )
                return pdf_bytes, text_preview, None, 'weasyprint'
            weasy_error = 'WeasyPrint returned empty PDF'
        except ImportError:
            weasy_error = 'WeasyPrint is not installed'
        except Exception as e:
            weasy_error = str(e)
            logger.warning(f"WeasyPrint PDF failed, trying PyMuPDF HTML fallback: {weasy_error}")

        pdf_bytes, pymupdf_error = self._render_to_pdf_with_pymupdf(pdf_html)
        if pdf_bytes:
            logger.info(
                f"PDF generated via PyMuPDF for email: {email_data.get('subject', 'No subject')} "
                f"({len(pdf_bytes)} bytes)"
            )
            return pdf_bytes, text_preview, None, 'pymupdf'

        if pymupdf_error:
            logger.warning('PyMuPDF HTML PDF unavailable: %s', pymupdf_error)

        logger.warning(
            f"PyMuPDF HTML PDF unavailable, using xhtml2pdf HTML fallback: {weasy_error}"
        )
        pdf_bytes = self._render_to_pdf_with_xhtml2pdf(pdf_html)
        if pdf_bytes:
            logger.info(
                f"PDF generated via xhtml2pdf fallback for email: {email_data.get('subject', 'No subject')} "
                f"({len(pdf_bytes)} bytes)"
            )
            return pdf_bytes, text_preview, None, 'xhtml2pdf'

        # As requested: do NOT convert to plain text if HTML PDF fails.
        logger.error(f"Error generating email PDF: {weasy_error or 'PDF generation failed'}")
        return None, text_preview, weasy_error or 'PDF generation failed', None

    def _render_to_pdf_with_xhtml2pdf(self, html_content: str) -> Optional[bytes]:
        """Fallback PDF renderer using xhtml2pdf to preserve HTML formatting."""
        try:
            from xhtml2pdf import pisa
            import io
            
            buffer = io.BytesIO()
            pisa_status = pisa.CreatePDF(html_content, dest=buffer)
            
            if pisa_status.err:
                logger.error(f"xhtml2pdf error: {pisa_status.err}")
                return None
                
            pdf_bytes = buffer.getvalue()
            return pdf_bytes if pdf_bytes else None
        except ImportError:
            logger.warning('xhtml2pdf is not installed')
            return None
        except Exception as e:
            logger.warning(f"xhtml2pdf failed: {str(e)}")
            return None

    def _render_to_pdf_with_pymupdf(self, html_content: str) -> Tuple[Optional[bytes], Optional[str]]:
        """Render full HTML email layout to PDF (Outlook/Gmail-style) when WeasyPrint is unavailable."""
        if not html_content:
            return None, 'empty html'

        try:
            import fitz
            from io import BytesIO
        except ImportError:
            logger.warning('PyMuPDF is not installed; cannot render HTML email PDF')
            return None, 'PyMuPDF is not installed'

        candidates = [
            html_content,
            self._simplify_html_for_story(html_content),
        ]
        last_error: Optional[str] = None

        for index, candidate in enumerate(candidates):
            if not candidate:
                continue
            try:
                pdf_bytes = self._pymupdf_story_to_pdf(candidate, fitz, BytesIO)
                if pdf_bytes:
                    if index > 0:
                        logger.info('PyMuPDF succeeded with simplified HTML layout')
                    return pdf_bytes, None
            except Exception as e:
                last_error = str(e)
                logger.warning('PyMuPDF HTML PDF attempt %s failed: %s', index + 1, e)

        return None, last_error or 'PyMuPDF could not render HTML'

    def _pymupdf_story_to_pdf(self, html_content: str, fitz_module, buffer_class) -> Optional[bytes]:
        buffer = buffer_class()
        writer = fitz_module.DocumentWriter(buffer)
        story = fitz_module.Story(html=html_content)
        mediabox = fitz_module.paper_rect('a4')
        content_rect = mediabox + (36, 36, -36, -36)
        more = True
        page_count = 0
        max_pages = 100

        while more:
            device = writer.begin_page(mediabox)
            more, _ = story.place(content_rect)
            story.draw(device)
            writer.end_page()
            page_count += 1
            if page_count >= max_pages:
                logger.warning('PyMuPDF email PDF truncated at %s pages', max_pages)
                break

        writer.close()
        pdf_bytes = buffer.getvalue()
        return pdf_bytes if pdf_bytes else None

    def _simplify_html_for_story(self, html_content: str) -> str:
        """Build a minimal HTML document when PyMuPDF cannot parse the full Outlook template."""
        inner = html_content
        if BeautifulSoup:
            soup = BeautifulSoup(html_content, 'html.parser')
            content_root = soup.select_one('.email-content') or soup.body or soup
            inner = content_root.decode_contents() if hasattr(content_root, 'decode_contents') else str(content_root)

        return f"""<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body {{
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 14px;
            line-height: 1.5;
            color: #242424;
            margin: 0;
            padding: 0;
        }}
        img {{ max-width: 100%; height: auto; }}
        table {{ border-collapse: collapse; max-width: 100%; }}
        td, th {{ vertical-align: top; padding: 4px 6px; }}
        a {{ color: #0f6cbd; }}
        blockquote {{
            margin: 8px 0 8px 12px;
            padding-left: 12px;
            border-left: 3px solid #c8c6c4;
        }}
        pre, .text-preview {{ white-space: pre-wrap; }}
    </style>
</head>
<body>{inner}</body>
</html>"""

    def _render_to_pdf_with_reportlab(
        self,
        email_data: Dict[str, Any],
        text_preview: str,
        display_timezone: Optional[str] = None,
    ) -> Optional[bytes]:
        """Fallback PDF renderer when WeasyPrint/Cairo is unavailable (e.g. local Windows)."""
        try:
            from io import BytesIO

            from reportlab.lib import colors
            from reportlab.lib.enums import TA_LEFT
            from reportlab.lib.pagesizes import A4
            from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
            from reportlab.lib.units import cm
            from reportlab.platypus import Paragraph, SimpleDocTemplate, Spacer
        except ImportError:
            logger.warning('ReportLab is not installed; cannot fall back from WeasyPrint')
            return None

        subject = str(email_data.get('subject') or '(No subject)').strip()
        sender = str(email_data.get('sender_name') or email_data.get('sender_email') or '').strip()
        recipients = email_data.get('to_recipients') or email_data.get('recipients') or []
        cc_recipients = email_data.get('cc_recipients') or []
        bcc_recipients = email_data.get('bcc_recipients') or []
        sent_date = str(email_data.get('sent_date') or '').strip()

        body_text = str(text_preview or email_data.get('text_content') or '').strip()
        if self._is_calendar_payload(body_text):
            body_text = self._summarize_calendar_payload(body_text)
        if not body_text:
            html_content = str(email_data.get('html_content') or '')
            if self._is_calendar_payload(html_content):
                body_text = self._summarize_calendar_payload(html_content)
            elif html_content and BeautifulSoup:
                body_text = BeautifulSoup(html_content, 'html.parser').get_text('\n', strip=True)
            elif html_content:
                body_text = re.sub(r'<[^>]+>', ' ', html_content)
            body_text = re.sub(r'\s+', ' ', body_text).strip()

        if not body_text:
            body_text = '(No message body)'

        buffer = BytesIO()
        doc = SimpleDocTemplate(
            buffer,
            pagesize=A4,
            leftMargin=2 * cm,
            rightMargin=2 * cm,
            topMargin=2 * cm,
            bottomMargin=2 * cm,
            title=subject[:120],
        )

        styles = getSampleStyleSheet()
        title_style = ParagraphStyle(
            'EmailTitle',
            parent=styles['Heading2'],
            fontSize=14,
            leading=18,
            textColor=colors.HexColor('#2c3e50'),
            spaceAfter=10,
        )
        meta_style = ParagraphStyle(
            'EmailMeta',
            parent=styles['Normal'],
            fontSize=9,
            leading=12,
            textColor=colors.HexColor('#6c757d'),
            spaceAfter=4,
        )
        body_style = ParagraphStyle(
            'EmailBody',
            parent=styles['Normal'],
            fontSize=10,
            leading=14,
            alignment=TA_LEFT,
            spaceBefore=8,
        )

        story = [Paragraph(self._escape_reportlab(subject), title_style)]

        if sender:
            story.append(Paragraph(f'<b>From:</b> {self._escape_reportlab(sender)}', meta_style))
        if recipients:
            to_line = ', '.join(str(r) for r in recipients[:8])
            story.append(Paragraph(f'<b>To:</b> {self._escape_reportlab(to_line)}', meta_style))
        if cc_recipients:
            cc_line = ', '.join(str(r) for r in cc_recipients[:8])
            story.append(Paragraph(f'<b>Cc:</b> {self._escape_reportlab(cc_line)}', meta_style))
        if bcc_recipients:
            bcc_line = ', '.join(str(r) for r in bcc_recipients[:8])
            story.append(Paragraph(f'<b>Bcc:</b> {self._escape_reportlab(bcc_line)}', meta_style))
        if sent_date:
            formatted_date = self._format_sent_date(sent_date, display_timezone)
            story.append(Paragraph(f'<b>Date:</b> {self._escape_reportlab(formatted_date)}', meta_style))

        story.append(Spacer(1, 0.25 * cm))

        for paragraph in self._split_reportlab_paragraphs(body_text):
            story.append(Paragraph(self._escape_reportlab(paragraph), body_style))
            story.append(Spacer(1, 0.12 * cm))

        doc.build(story)
        pdf_bytes = buffer.getvalue()
        return pdf_bytes if pdf_bytes else None

    def _escape_reportlab(self, text: str) -> str:
        if not text:
            return ''
        return (
            str(text)
            .replace('&', '&amp;')
            .replace('<', '&lt;')
            .replace('>', '&gt;')
            .replace('"', '&quot;')
        )

    def _split_reportlab_paragraphs(self, text: str, max_len: int = 3500) -> List[str]:
        chunks: List[str] = []
        for block in re.split(r'\n{2,}', text):
            block = block.strip()
            if not block:
                continue
            while len(block) > max_len:
                split_at = block.rfind(' ', 0, max_len)
                if split_at <= 0:
                    split_at = max_len
                chunks.append(block[:split_at].strip())
                block = block[split_at:].strip()
            if block:
                chunks.append(block)
        return chunks or ['(No message body)']

    _PDF_LAYOUT_TAGS = frozenset({
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th',
        'colgroup', 'col', 'div', 'span', 'p', 'center', 'font',
    })
    _PDF_STYLE_PROPS_TO_REMOVE = frozenset({
        'width', 'min-width', 'max-width',
        'overflow', 'overflow-x', 'overflow-y',
        'table-layout',
    })

    def _parse_pixel_width(self, value: str) -> Optional[int]:
        """Return pixel width from an HTML width attribute/style value, if parseable."""
        if not value:
            return None
        value = str(value).strip().lower()
        match = re.match(r'^(\d+(?:\.\d+)?)\s*px?$', value)
        if match:
            return int(float(match.group(1)))
        if value.isdigit():
            return int(value)
        return None

    def _clean_inline_style_for_pdf(self, style: str) -> str:
        """Remove layout constraints from inline styles that clip content in WeasyPrint."""
        if not style:
            return ''

        cleaned: List[str] = []
        for part in style.split(';'):
            part = part.strip()
            if not part or ':' not in part:
                continue

            prop, _, raw_value = part.partition(':')
            prop = prop.strip().lower()
            value = raw_value.strip()

            if prop in self._PDF_STYLE_PROPS_TO_REMOVE:
                continue
            if prop == 'white-space' and value.lower() in ('nowrap', 'pre'):
                cleaned.append('white-space: normal')
                continue

            cleaned.append(f'{prop}: {value}')

        return '; '.join(cleaned)

    def _should_remove_width_attr_for_pdf(self, tag_name: str, width_val: str) -> bool:
        """Decide whether a width attribute should be stripped before PDF rendering."""
        if tag_name == 'img':
            px = self._parse_pixel_width(width_val)
            return px is None or px > 480

        return tag_name in self._PDF_LAYOUT_TAGS

    def _normalize_layout_tag_for_pdf(self, tag) -> None:
        """Strip fixed widths and nowrap styles from a single tag (BeautifulSoup element)."""
        tag_name = (tag.name or '').lower()
        if tag_name not in self._PDF_LAYOUT_TAGS and tag_name != 'img':
            return

        width_val = tag.attrs.get('width')
        if width_val is not None and self._should_remove_width_attr_for_pdf(tag_name, str(width_val)):
            del tag.attrs['width']

        if tag_name == 'img':
            height_val = tag.attrs.get('height')
            if height_val is not None:
                px = self._parse_pixel_width(str(height_val))
                if px is None or px > 480:
                    del tag.attrs['height']
            return

        style = tag.attrs.get('style')
        if style:
            cleaned_style = self._clean_inline_style_for_pdf(str(style))
            if cleaned_style:
                tag.attrs['style'] = cleaned_style
            else:
                del tag.attrs['style']

    def _prepare_html_for_pdf_with_soup(self, html_content: str) -> str:
        soup = BeautifulSoup(html_content, 'html.parser')
        content_root = soup.select_one('.email-content') or soup.body or soup

        for tag in content_root.find_all(True):
            self._normalize_layout_tag_for_pdf(tag)

        return str(soup)

    def _prepare_html_for_pdf_with_regex(self, html_content: str) -> str:
        """Best-effort layout normalization when BeautifulSoup is unavailable."""
        cleaned = html_content

        cleaned = re.sub(
            r'(<(?:table|td|th|colgroup|col|div|span|p|center)\b[^>]*?)\s+width\s*=\s*["\'][^"\']*["\']',
            r'\1',
            cleaned,
            flags=re.IGNORECASE,
        )
        cleaned = re.sub(
            r'(<img\b[^>]*?)\s+width\s*=\s*["\'](?:[5-9]\d{2}|\d{4,})[^"\']*["\']',
            r'\1',
            cleaned,
            flags=re.IGNORECASE,
        )
        cleaned = re.sub(
            r'style\s*=\s*["\']([^"\']*)["\']',
            lambda match: (
                f'style="{self._clean_inline_style_for_pdf(match.group(1))}"'
                if self._clean_inline_style_for_pdf(match.group(1))
                else ''
            ),
            cleaned,
            flags=re.IGNORECASE,
        )
        return cleaned

    def _prepare_html_for_pdf(self, html_content: str) -> str:
        """
        Normalize wide Outlook-style HTML for PDF output only.

        Does not affect render_email() output used for previews, links, or images.
        """
        if not html_content:
            return html_content

        try:
            if BeautifulSoup:
                return self._prepare_html_for_pdf_with_soup(html_content)
            return self._prepare_html_for_pdf_with_regex(html_content)
        except Exception as e:
            logger.warning(f"Error preparing HTML for PDF: {str(e)}")
            return html_content

    def _get_pdf_layout_stylesheet(self):
        """PDF-only CSS to prevent clipped text in WeasyPrint output."""
        from weasyprint import CSS

        return CSS(string='''
            @page {
                size: A4;
                margin: 1.2cm;
            }
            *, *::before, *::after {
                box-sizing: border-box;
            }
            html, body {
                max-width: 100% !important;
                width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
                background-color: #fff;
            }
            .email-container {
                width: 100% !important;
                max-width: 100% !important;
                overflow: visible !important;
                box-shadow: none;
                border-radius: 0;
            }
            .email-header,
            .email-content,
            .email-footer {
                padding: 12px !important;
            }
            .email-content {
                overflow: visible !important;
                overflow-wrap: anywhere;
                word-wrap: break-word;
                word-break: break-word;
                max-width: 100% !important;
            }
            .email-content p,
            .email-content div,
            .email-content span,
            .email-content li,
            .email-content td,
            .email-content th,
            .email-content blockquote,
            .email-content a,
            .email-content font,
            .email-content center {
                overflow: visible !important;
                overflow-wrap: anywhere;
                word-wrap: break-word;
                word-break: break-word;
                white-space: normal !important;
                max-width: 100% !important;
            }
            .email-content table {
                width: 100% !important;
                max-width: 100% !important;
                table-layout: auto !important;
                border-collapse: collapse;
            }
            .email-content col,
            .email-content colgroup {
                width: auto !important;
                max-width: 100% !important;
            }
            .email-content blockquote {
                margin-left: 0;
                padding-left: 10px;
                border-left: 3px solid #ccc;
            }
            .email-content pre,
            .email-content code {
                white-space: pre-wrap !important;
                overflow-wrap: anywhere;
                word-wrap: break-word;
            }
            .email-content img {
                max-width: 100% !important;
                height: auto !important;
            }
        ''')

    def _replace_cid_with_data_uris(self, html_content: str, attachments: List[Dict[str, Any]]) -> str:
        """Replace cid: image references with inline data URIs for PDF rendering."""
        if not html_content or not attachments:
            return html_content

        cid_map: Dict[str, str] = {}
        for attachment in attachments:
            if not isinstance(attachment, dict):
                continue

            content_id = str(attachment.get('content_id') or '').strip().strip('<>')
            data_b64 = attachment.get('data')
            if not content_id or not data_b64:
                continue

            content_type = attachment.get('content_type') or 'application/octet-stream'
            if not str(content_type).lower().startswith('image/'):
                continue

            try:
                raw_size = len(base64.b64decode(data_b64, validate=False))
            except Exception:
                raw_size = 0
            if raw_size > self.MAX_INLINE_IMAGE_BYTES_FOR_PDF:
                logger.info(
                    'Skipping inline image for PDF (%s bytes): %s',
                    raw_size,
                    attachment.get('filename') or content_id,
                )
                continue

            cid_map[content_id.lower()] = f"data:{content_type};base64,{data_b64}"

            filename = str(attachment.get('filename') or '').strip()
            if filename:
                cid_map[filename.lower()] = cid_map[content_id.lower()]

        if not cid_map:
            return html_content

        def replace_cid(match: re.Match) -> str:
            cid_value = match.group(1).strip().strip('<>').lower()
            if cid_value in cid_map:
                return f'src="{cid_map[cid_value]}"'
            return match.group(0)

        return re.sub(r'src=["\']cid:([^"\']+)["\']', replace_cid, html_content, flags=re.IGNORECASE)
