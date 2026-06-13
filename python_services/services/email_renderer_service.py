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

logger = setup_logger(__name__, 'email_renderer.log')


class EmailRendererService:
    """Service for rendering email content with enhanced HTML and styling."""
    
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
    
    def render_email(self, email_data: Dict[str, Any]) -> Dict[str, Any]:
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
            
            # Clean and enhance HTML content
            enhanced_html = self._clean_and_enhance_html(html_content)
            
            # Create responsive email template
            rendered_html = self._create_responsive_template(
                subject=subject,
                html_content=enhanced_html,
                text_content=text_content,
                sender_name=sender_name,
                sender_email=sender_email,
                email_data=email_data
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
        email_data: Dict[str, Any]
    ) -> str:
        """Create a responsive email template."""
        
        # Extract metadata
        sent_date = email_data.get('sent_date', '')
        recipients = email_data.get('to_recipients') or email_data.get('recipients', [])
        cc_recipients = email_data.get('cc_recipients', [])
        
        # Format date
        formatted_date = ''
        if sent_date:
            try:
                if isinstance(sent_date, str):
                    from datetime import datetime
                    dt = datetime.fromisoformat(sent_date.replace('Z', '+00:00'))
                    formatted_date = dt.strftime('%B %d, %Y at %I:%M %p')
                else:
                    formatted_date = str(sent_date)
            except:
                formatted_date = str(sent_date)
        
        # Create responsive template
        template = f"""
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{self._escape_html(subject)}</title>
    <style>
        body {{
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }}
        .email-container {{
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: visible;
        }}
        .email-header {{
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
        }}
        .email-subject {{
            font-size: 24px;
            font-weight: 600;
            margin: 0 0 10px 0;
            color: #2c3e50;
        }}
        .email-meta {{
            font-size: 14px;
            color: #6c757d;
            margin: 0;
        }}
        .email-meta strong {{
            color: #495057;
        }}
        .email-content {{
            padding: 30px;
            overflow-wrap: anywhere;
            word-wrap: break-word;
            max-width: 100%;
        }}
        .email-content img {{
            max-width: 100%;
            height: auto;
        }}
        .email-content table {{
            width: 100%;
            max-width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }}
        .email-content th,
        .email-content td {{
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
            overflow-wrap: anywhere;
            word-wrap: break-word;
        }}
        .email-content th {{
            background-color: #f8f9fa;
            font-weight: 600;
        }}
        .email-footer {{
            background: #f8f9fa;
            padding: 20px;
            border-top: 1px solid #e9ecef;
            font-size: 12px;
            color: #6c757d;
            text-align: center;
        }}
        .text-preview {{
            background: #f8f9fa;
            padding: 15px;
            border-left: 4px solid #007bff;
            margin: 20px 0;
            font-family: monospace;
            white-space: pre-wrap;
        }}
        @media (max-width: 600px) {{
            body {{
                padding: 10px;
            }}
            .email-header,
            .email-content,
            .email-footer {{
                padding: 15px;
            }}
            .email-subject {{
                font-size: 20px;
            }}
        }}
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1 class="email-subject">{self._escape_html(subject)}</h1>
            <div class="email-meta">
                <strong>From:</strong> {self._escape_html(sender_name or sender_email)}<br>
                {f'<strong>To:</strong> {", ".join([self._escape_html(r) for r in recipients[:3]])}' if recipients else ''}
                {f'<br><strong>Cc:</strong> {", ".join([self._escape_html(r) for r in cc_recipients[:5]])}' if cc_recipients else ''}
                {f'<br><strong>Date:</strong> {formatted_date}' if formatted_date else ''}
            </div>
        </div>
        
        <div class="email-content">
            {html_content if html_content else f'<div class="text-preview">{self._escape_html(text_content)}</div>'}
        </div>
        
        <div class="email-footer">
            <p>This email was processed by Migration Manager Email Viewer</p>
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
            
            # Limit length — enough for quoting in reply/forward, far smaller than full HTML body
            if len(text) > 2000:
                text = text[:2000] + "..."
            
            return text
            
        except Exception as e:
            logger.warning(f"Error creating text preview: {str(e)}")
            return content[:2000] if content else ""
    
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

    def render_to_pdf(self, email_data: Dict[str, Any]) -> Tuple[Optional[bytes], Optional[str], Optional[str]]:
        """
        Render parsed email data to a PDF byte stream.

        Returns:
            (pdf_bytes, text_preview, error_message)
        """
        try:
            rendering = self.render_email(email_data)
            text_preview = rendering.get('text_preview') or email_data.get('text_content', '')

            rendered_html = rendering.get('rendered_html', '')
            if not rendered_html:
                return None, text_preview, 'No rendered HTML available for PDF conversion'

            attachments = email_data.get('attachments') or []
            pdf_html = self._replace_cid_with_data_uris(rendered_html, attachments)
            pdf_html = self._prepare_html_for_pdf(pdf_html)

            try:
                from weasyprint import HTML, CSS
            except ImportError:
                return None, text_preview, 'WeasyPrint is not installed'

            pdf_bytes = HTML(string=pdf_html).write_pdf(
                stylesheets=[self._get_pdf_layout_stylesheet()]
            )
            if not pdf_bytes:
                return None, text_preview, 'WeasyPrint returned empty PDF'

            logger.info(
                f"PDF generated for email: {email_data.get('subject', 'No subject')} "
                f"({len(pdf_bytes)} bytes)"
            )
            return pdf_bytes, text_preview, None

        except Exception as e:
            logger.error(f"Error generating email PDF: {str(e)}")
            return None, email_data.get('text_content', ''), str(e)

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
