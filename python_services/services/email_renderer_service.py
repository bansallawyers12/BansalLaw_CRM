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
from typing import Dict, List, Any, Optional
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
        recipients = email_data.get('recipients', [])
        
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
            overflow: hidden;
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
        }}
        .email-content img {{
            max-width: 100%;
            height: auto;
        }}
        .email-content table {{
            width: 100%;
            border-collapse: collapse;
        }}
        .email-content th,
        .email-content td {{
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
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
            
            # Limit length
            if len(text) > 500:
                text = text[:500] + "..."
            
            return text
            
        except Exception as e:
            logger.warning(f"Error creating text preview: {str(e)}")
            return content[:500] if content else ""
    
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
