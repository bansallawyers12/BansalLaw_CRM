"""
Email Analyzer Service

Provides AI-powered email content analysis including:
- Content categorization (Business, Personal, Migration, Legal, etc.)
- Priority detection (High, Medium, Low)
- Sentiment analysis (Positive, Negative, Neutral)
- Security scanning (detect dangerous content)
- Link extraction and validation
- Thread detection
- Language detection
"""

import re
import json
import logging
from typing import Dict, List, Any, Optional
from datetime import datetime
from urllib.parse import urlparse

try:
    from bs4 import BeautifulSoup
except ImportError:
    BeautifulSoup = None

from utils.logger import setup_logger

logger = setup_logger(__name__, 'email_analyzer.log')


class EmailAnalyzerService:
    """Service for analyzing email content and providing insights."""
    
    def __init__(self):
        self.categories = {
            'business': [
                'meeting', 'proposal', 'contract', 'invoice', 'payment', 'project',
                'client', 'customer', 'business', 'work', 'office', 'company',
                'revenue', 'profit', 'sales', 'marketing', 'strategy'
            ],
            'personal': [
                'family', 'friend', 'personal', 'birthday', 'holiday', 'vacation',
                'weekend', 'party', 'celebration', 'home', 'personal', 'private'
            ],
            'spam': [
                'free', 'win', 'congratulations', 'urgent', 'act now', 'limited time',
                'click here', 'unsubscribe', 'viagra', 'casino', 'lottery', 'winner',
                'guaranteed', 'no risk', 'make money', 'work from home'
            ],
            'newsletter': [
                'newsletter', 'subscribe', 'unsubscribe', 'marketing', 'promotion',
                'offer', 'deal', 'discount', 'sale', 'news', 'update', 'digest'
            ],
            'system': [
                'notification', 'alert', 'warning', 'error', 'system', 'automated',
                'no-reply', 'noreply', 'do-not-reply', 'support', 'help', 'service'
            ],
            'migration': [
                'visa', 'immigration', 'application', 'tribunal', 'appeal', 'aat',
                'migration', 'permanent residence', 'citizenship', 'passport',
                'immigration agent', 'migration lawyer', 'visa application',
                'department of home affairs', 'dha', 'immi', 'bridging visa',
                'student visa', 'work visa', 'partner visa', 'parent visa'
            ],
            'legal': [
                'legal', 'court', 'lawyer', 'attorney', 'lawsuit', 'settlement',
                'litigation', 'legal advice', 'legal document', 'contract',
                'agreement', 'terms', 'conditions', 'legal notice'
            ]
        }
        
        self.priority_keywords = {
            'high': [
                'urgent', 'asap', 'immediately', 'critical', 'emergency',
                'deadline', 'expires', 'final notice', 'action required',
                'important', 'priority', 'urgent action'
            ],
            'medium': [
                'please', 'request', 'follow up', 'reminder', 'update',
                'status', 'progress', 'review', 'consider', 'discuss'
            ],
            'low': [
                'information', 'FYI', 'for your information', 'note',
                'reference', 'background', 'optional', 'when convenient'
            ]
        }
        
        self.security_patterns = [
            r'<script[^>]*>.*?</script>',
            r'javascript:',
            r'vbscript:',
            r'on\w+\s*=',
            r'<iframe[^>]*>',
            r'<object[^>]*>',
            r'<embed[^>]*>',
            r'<form[^>]*>',
            r'<input[^>]*>',
            r'<button[^>]*>',
            r'<link[^>]*>',
            r'<meta[^>]*>'
        ]
        
        self.sentiment_positive = [
            'good', 'great', 'excellent', 'wonderful', 'amazing', 'fantastic',
            'thank', 'thanks', 'appreciate', 'pleased', 'happy', 'delighted',
            'success', 'successful', 'congratulations', 'celebrate', 'welcome'
        ]
        
        self.sentiment_negative = [
            'bad', 'terrible', 'awful', 'horrible', 'disappointed', 'angry',
            'frustrated', 'upset', 'concerned', 'worried', 'problem', 'issue',
            'error', 'failed', 'failure', 'complaint', 'unhappy', 'dissatisfied'
        ]
        
        logger.info("Email Analyzer Service initialized")
    
    def analyze_content(self, email_data: Dict[str, Any]) -> Dict[str, Any]:
        """
        Analyze email content and return comprehensive analysis.
        
        Args:
            email_data: Dictionary containing email data (subject, content, etc.)
        
        Returns:
            Dict containing analysis results
        """
        try:
            logger.info(f"Analyzing email: {email_data.get('subject', 'No subject')}")
            
            # Extract text content for analysis
            text_content = email_data.get('text_content', '')
            html_content = email_data.get('html_content', '')
            subject = email_data.get('subject', '')
            
            # Combine all text for analysis
            full_text = f"{subject} {text_content} {html_content}".lower()
            
            # Sanitize input
            full_text = self._sanitize_input(full_text)
            
            # Perform analysis
            analysis = {
                'category': self._categorize_email(full_text),
                'priority': self._determine_priority(email_data, full_text),
                'security_issues': self._scan_security_issues(html_content),
                'links': self._extract_links(html_content),
                'enhanced_html': self._enhance_html_content(html_content),
                'thread_info': self._detect_thread_info(email_data),
                'sentiment': self._analyze_sentiment(full_text),
                'language': self._detect_language(full_text),
                'attachments_analysis': self._analyze_attachments(email_data.get('attachments', [])),
                'processing_timestamp': datetime.now().isoformat()
            }
            
            logger.info(f"Analysis completed: {analysis['category']} - {analysis['priority']} priority")
            
            return analysis
            
        except Exception as e:
            logger.error(f"Error analyzing email content: {str(e)}")
            return self._get_default_analysis(str(e))
    
    def _sanitize_input(self, text: str) -> str:
        """Sanitize input text to prevent injection attacks."""
        if not isinstance(text, str):
            return ""
        
        # Remove potentially dangerous characters
        sanitized = re.sub(r'[^\w\s\-_.,!?@#$%&*()+=:;"\'<>\/\\[\]{}|~`]', '', text)
        
        # Limit length to prevent memory issues
        return sanitized[:50000]
    
    def _categorize_email(self, text: str) -> str:
        """Categorize email based on content analysis."""
        text_lower = text.lower()
        
        # Count keyword matches for each category
        category_scores = {}
        for category, keywords in self.categories.items():
            score = sum(1 for keyword in keywords if keyword in text_lower)
            category_scores[category] = score
        
        # Find category with highest score
        if category_scores:
            best_category = max(category_scores, key=category_scores.get)
            if category_scores[best_category] > 0:
                return best_category.title()
        
        # Default to Uncategorized
        return 'Uncategorized'
    
    def _determine_priority(self, email_data: Dict[str, Any], text: str) -> str:
        """Determine email priority based on content and metadata."""
        text_lower = text.lower()
        
        # Check for high priority keywords
        for keyword in self.priority_keywords['high']:
            if keyword in text_lower:
                return 'high'
        
        # Check sender importance
        sender_email = email_data.get('sender_email', '').lower()
        if any(domain in sender_email for domain in ['@gmail.com', '@outlook.com', '@hotmail.com']):
            # Personal emails might be lower priority
            pass
        
        # Check for medium priority keywords
        for keyword in self.priority_keywords['medium']:
            if keyword in text_lower:
                return 'medium'
        
        # Check for low priority keywords
        for keyword in self.priority_keywords['low']:
            if keyword in text_lower:
                return 'low'
        
        # Default to medium priority
        return 'medium'
    
    def _scan_security_issues(self, html_content: str) -> List[str]:
        """Scan HTML content for potential security issues."""
        if not html_content:
            return []
        
        issues = []
        
        for pattern in self.security_patterns:
            matches = re.findall(pattern, html_content, re.IGNORECASE | re.DOTALL)
            if matches:
                issues.append(f"Potentially dangerous content detected: {pattern}")
        
        # Check for suspicious links
        links = self._extract_links(html_content)
        for link in links:
            if self._is_suspicious_link(link):
                issues.append(f"Suspicious link detected: {link}")
        
        return issues
    
    def _extract_links(self, html_content: str) -> List[str]:
        """Extract and validate links from HTML content."""
        if not html_content:
            return []
        
        links = []
        
        # Extract links using regex
        link_pattern = r'<a[^>]+href=["\']([^"\']+)["\'][^>]*>'
        matches = re.findall(link_pattern, html_content, re.IGNORECASE)
        
        for link in matches:
            if link and link.startswith(('http://', 'https://', 'mailto:')):
                links.append(link)
        
        return list(set(links))  # Remove duplicates
    
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
    
    def _enhance_html_content(self, html_content: str) -> str:
        """Enhance HTML content by cleaning and improving it."""
        if not html_content:
            return ""
        
        try:
            if BeautifulSoup:
                soup = BeautifulSoup(html_content, 'html.parser')
                
                # Remove dangerous elements
                for element in soup.find_all(['script', 'iframe', 'object', 'embed', 'form']):
                    element.decompose()
                
                # Remove dangerous attributes
                for tag in soup.find_all():
                    for attr in list(tag.attrs.keys()):
                        if attr.startswith('on') or attr in ['javascript:', 'vbscript:']:
                            del tag.attrs[attr]
                
                return str(soup)
            else:
                # Fallback: basic cleaning using regex
                cleaned = html_content
                for pattern in self.security_patterns:
                    cleaned = re.sub(pattern, '', cleaned, flags=re.IGNORECASE | re.DOTALL)
                return cleaned
                
        except Exception as e:
            logger.warning(f"Error enhancing HTML content: {str(e)}")
            return html_content
    
    def _detect_thread_info(self, email_data: Dict[str, Any]) -> Dict[str, Any]:
        """Detect email thread information."""
        subject = email_data.get('subject', '')
        
        # Check for reply indicators
        is_reply = any(indicator in subject.lower() for indicator in ['re:', 'fwd:', 'fw:'])
        
        # Extract thread subject (remove reply indicators)
        thread_subject = subject
        for indicator in ['re:', 'fwd:', 'fw:']:
            if thread_subject.lower().startswith(indicator):
                thread_subject = thread_subject[len(indicator):].strip()
                break
        
        return {
            'is_reply': is_reply,
            'thread_subject': thread_subject,
            'original_subject': subject
        }
    
    def _analyze_sentiment(self, text: str) -> str:
        """Analyze sentiment of the email content."""
        text_lower = text.lower()
        
        positive_count = sum(1 for word in self.sentiment_positive if word in text_lower)
        negative_count = sum(1 for word in self.sentiment_negative if word in text_lower)
        
        if positive_count > negative_count:
            return 'positive'
        elif negative_count > positive_count:
            return 'negative'
        else:
            return 'neutral'
    
    def _detect_language(self, text: str) -> str:
        """Detect the primary language of the email content."""
        # Simple language detection based on common words
        english_words = ['the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by']
        spanish_words = ['el', 'la', 'de', 'que', 'y', 'a', 'en', 'un', 'es', 'se', 'no', 'te', 'lo', 'le']
        french_words = ['le', 'la', 'de', 'et', 'à', 'un', 'il', 'que', 'ne', 'se', 'ce', 'pas', 'tout', 'plus']
        
        text_lower = text.lower()
        
        english_count = sum(1 for word in english_words if word in text_lower)
        spanish_count = sum(1 for word in spanish_words if word in text_lower)
        french_count = sum(1 for word in french_words if word in text_lower)
        
        if english_count > spanish_count and english_count > french_count:
            return 'english'
        elif spanish_count > french_count:
            return 'spanish'
        elif french_count > 0:
            return 'french'
        else:
            return 'unknown'
    
    def _analyze_attachments(self, attachments: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        """Analyze email attachments for type and safety."""
        analysis = []
        
        for attachment in attachments:
            att_analysis = {
                'filename': attachment.get('filename', 'Unknown'),
                'content_type': attachment.get('content_type', 'application/octet-stream'),
                'size': attachment.get('size', 0),
                'is_safe': True,
                'warnings': []
            }
            
            # Check file extension
            filename = attachment.get('filename', '').lower()
            dangerous_extensions = ['.exe', '.bat', '.cmd', '.scr', '.pif', '.com', '.vbs', '.js']
            
            if any(filename.endswith(ext) for ext in dangerous_extensions):
                att_analysis['is_safe'] = False
                att_analysis['warnings'].append('Potentially dangerous file type')
            
            # Check file size
            size = attachment.get('size', 0)
            if size > 10 * 1024 * 1024:  # 10MB
                att_analysis['warnings'].append('Large file size')
            
            # Check content type
            content_type = attachment.get('content_type', '').lower()
            if 'executable' in content_type or 'application/x-msdownload' in content_type:
                att_analysis['is_safe'] = False
                att_analysis['warnings'].append('Executable file detected')
            
            analysis.append(att_analysis)
        
        return analysis
    
    def _get_default_analysis(self, error: str = None) -> Dict[str, Any]:
        """Return default analysis when processing fails."""
        return {
            'category': 'Uncategorized',
            'priority': 'low',
            'security_issues': [],
            'links': [],
            'enhanced_html': None,
            'thread_info': {'is_reply': False, 'thread_subject': '', 'original_subject': ''},
            'sentiment': 'neutral',
            'language': 'unknown',
            'attachments_analysis': [],
            'processing_timestamp': datetime.now().isoformat(),
            'error': error or 'Analysis failed'
        }
