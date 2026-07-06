"""
Email Parser Service

Handles parsing of Outlook email files (.msg via extract_msg, .eml via stdlib email).
Provides comprehensive email data extraction including metadata, content, and attachments.
"""

import json
import sys
import os
import re
import base64
import tempfile
from email import policy
from email.message import Message
from email.utils import getaddresses, parsedate_to_datetime
from email.header import decode_header
import email
from datetime import datetime, timezone
from pathlib import Path
from typing import Dict, Any, List, Optional, Tuple

try:
    import extract_msg
    from extract_msg.enums import RecipientType
except ImportError as e:
    print(f"Warning: extract_msg not installed: {e}")
    RecipientType = None

from utils.logger import setup_logger

logger = setup_logger(__name__, 'email_parser.log')


class EmailParserService:
    """Service for parsing Outlook email files (.msg, .eml)."""

    ATTACHMENT_MAX_BYTES = 31457280  # 30MB

    MIME_EXTENSION_MAP = {
        'image/jpeg': 'jpg',
        'image/jpg': 'jpg',
        'image/pjpeg': 'jpg',
        'image/png': 'png',
        'image/gif': 'gif',
        'image/bmp': 'bmp',
        'image/webp': 'webp',
        'image/tiff': 'tiff',
        'image/x-png': 'png',
        'application/pdf': 'pdf',
        'application/msword': 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'docx',
        'application/vnd.ms-excel': 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'xlsx',
    }
    
    def __init__(self):
        logger.info("Email Parser Service initialized")

    def _sanitize_text_content(self, value: Any) -> Any:
        """Remove null bytes and normalize text fields for JSON/DB safety."""
        if not isinstance(value, str):
            return value
        return value.replace('\0', '')

    def _infer_attachment_extension(self, filename: str, content_type: str) -> str:
        name = str(filename or '').strip()
        ext = os.path.splitext(name)[1].lstrip('.').lower()
        if ext:
            return ext

        mime = str(content_type or '').split(';', 1)[0].strip().lower()
        return self.MIME_EXTENSION_MAP.get(mime, '')

    def _normalize_attachment_filename(self, filename: str, content_type: str) -> str:
        name = str(filename or 'attachment').strip() or 'attachment'
        ext = self._infer_attachment_extension(name, content_type)
        if ext and not os.path.splitext(name)[1]:
            return f'{name}.{ext}'
        return name

    def _read_msg_attachment_bytes(self, attachment) -> bytes:
        data = getattr(attachment, 'data', None)
        if data:
            if isinstance(data, bytes):
                return data
            try:
                return data.encode('latin-1')
            except Exception:
                pass

        try:
            with tempfile.TemporaryDirectory() as tmpdir:
                attachment.save(customPath=tmpdir)
                for entry in os.listdir(tmpdir):
                    path = os.path.join(tmpdir, entry)
                    if os.path.isfile(path):
                        with open(path, 'rb') as handle:
                            return handle.read()
        except Exception as e:
            logger.warning(f"Could not read attachment bytes via save(): {getattr(attachment, 'longFilename', 'unknown')} - {e}")

        return b''

    def _build_attachment_record(
        self,
        filename: str,
        content_type: str,
        content_id: str,
        is_inline: bool,
        payload: bytes,
    ) -> dict:
        normalized_name = self._normalize_attachment_filename(filename, content_type)
        attachment_data = {
            'filename': self._safe_get(normalized_name, 'attachment'),
            'display_name': self._safe_get(normalized_name, 'attachment'),
            'content_type': self._safe_get(content_type or 'application/octet-stream', 'application/octet-stream'),
            'content_id': content_id or '',
            'is_inline': bool(is_inline),
            'size': len(payload or b''),
            'file_size': len(payload or b''),
            'data': None,
        }

        if payload and len(payload) <= self.ATTACHMENT_MAX_BYTES:
            try:
                attachment_data['data'] = base64.b64encode(payload).decode('ascii')
            except Exception as e:
                logger.error(f"Failed to encode attachment {attachment_data['filename']}: {str(e)}")
                attachment_data['data'] = None

        return attachment_data

    def _is_inline_attachment(self, content_id: str, combined_body: str, disposition: str = '') -> bool:
        disposition = str(disposition or '').lower()
        if 'attachment' in disposition:
            return False
        if 'inline' in disposition:
            return True

        content_id = str(content_id or '').strip().strip('<>')
        if not content_id or not combined_body:
            return False

        cid_ref = f"cid:{content_id}".lower()
        return cid_ref in str(combined_body).lower()

    def _decode_eml_header_value(self, value: Optional[str]) -> str:
        if not value:
            return ''
        try:
            parts = decode_header(str(value))
            chunks: List[str] = []
            for part, charset in parts:
                if isinstance(part, bytes):
                    chunks.append(part.decode(charset or 'utf-8', errors='ignore'))
                else:
                    chunks.append(str(part))
            return ''.join(chunks).strip()
        except Exception:
            return str(value).strip()

    def _get_eml_part_filename(self, part: Message) -> Optional[str]:
        filename = part.get_filename()
        if filename:
            return self._decode_eml_header_value(filename)

        for header_name in ('Content-Disposition', 'Content-Type'):
            header_value = str(part.get(header_name, '') or '')
            if not header_value:
                continue
            match = re.search(
                r'''filename\*=(?:UTF-8''([^;]+)|"([^"]+)"|([^;\s]+))''',
                header_value,
                re.IGNORECASE,
            )
            if match:
                return self._decode_eml_header_value(
                    match.group(1) or match.group(2) or match.group(3)
                )
            match = re.search(
                r'''name\*=(?:UTF-8''([^;]+)|"([^"]+)"|([^;\s]+))''',
                header_value,
                re.IGNORECASE,
            )
            if match:
                return self._decode_eml_header_value(
                    match.group(1) or match.group(2) or match.group(3)
                )

        return None

    def _read_eml_part_bytes(self, part: Message) -> bytes:
        try:
            content = part.get_content()
            if isinstance(content, bytes):
                return content
            if isinstance(content, str):
                return content.encode('utf-8', errors='ignore')
        except Exception:
            pass

        try:
            payload = part.get_payload(decode=True)
            if isinstance(payload, bytes):
                return payload or b''
            if isinstance(payload, str):
                return payload.encode('utf-8', errors='ignore')
        except Exception:
            pass

        return b''

    def _is_eml_attachment_candidate(self, part: Message) -> bool:
        if part.get_content_maintype() == 'multipart':
            return False

        content_type = (part.get_content_type() or '').lower()
        if content_type in ('text/plain', 'text/html'):
            return False

        disposition = str(part.get('Content-Disposition', '') or '').lower()
        if 'attachment' in disposition:
            return True

        if self._get_eml_part_filename(part):
            return True

        maintype = part.get_content_maintype()
        if maintype in ('application', 'image', 'audio', 'video'):
            return True

        if maintype == 'message' and content_type == 'message/rfc822':
            return True

        return False

    def parse_email_file(self, file_path: str) -> Dict[str, Any]:
        """Parse an Outlook email file based on its extension or file contents."""
        extension = Path(file_path).suffix.lower()
        if extension == '.eml':
            return self.parse_eml_file(file_path)
        if extension == '.msg':
            return self.parse_msg_file(file_path)

        detected = self._detect_email_file_format(file_path)
        if detected == 'eml':
            return self.parse_eml_file(file_path)
        if detected == 'msg':
            return self.parse_msg_file(file_path)

        return self.parse_msg_file(file_path)

    def _detect_email_file_format(self, file_path: str) -> Optional[str]:
        try:
            with open(file_path, 'rb') as handle:
                header = handle.read(4096) or b''
        except Exception:
            return None

        if len(header) >= 4 and header[:4] == b'\xD0\xCF\x11\xE0':
            return 'msg'

        try:
            text = header.decode('utf-8', errors='ignore')
        except Exception:
            text = ''

        if re.match(r'^(From:|Return-Path:|Received:|MIME-Version:|Date:|X-)', text, re.IGNORECASE):
            return 'eml'

        return None
    
    def parse_msg_file(self, file_path: str) -> Dict[str, Any]:
        """
        Parse a .msg file and extract all email data.
        
        Args:
            file_path: Path to the .msg file
        
        Returns:
            Dict containing parsed email data
        """
        try:
            logger.info(f"Parsing .msg file: {file_path}")
            
            if not os.path.exists(file_path):
                return {
                    'success': False,
                    'error': f'File not found: {file_path}'
                }

            file_size = os.path.getsize(file_path)
            if file_size <= 0:
                return {
                    'success': False,
                    'error': 'Uploaded email file is empty.'
                }
            
            # Parse the .msg file
            msg = extract_msg.Message(file_path)
            
            try:
                # Extract basic information
                email_data = {
                    'success': True,
                    'subject': self._safe_get(msg.subject, ''),
                    'sender_name': '',
                    'sender_email': '',
                    'sent_date': self._safe_get(getattr(msg, 'date', None) or getattr(msg, 'parsedDate', None) or getattr(msg, 'receivedTime', None)),
                    'received_date': self._safe_get(getattr(msg, 'receivedTime', None)),
                    'html_content': self._safe_get(msg.htmlBody, ''),
                    'text_content': self._safe_get(msg.body, ''),
                    'recipients': [],
                    'attachments': [],
                    'headers': {},
                    'message_id': self._safe_get(getattr(msg, 'messageId', ''), ''),
                    'file_path': file_path,
                    'file_size': os.path.getsize(file_path)
                }
                
                # Extract sender information
                sender_info = self._extract_sender_info(msg)
                email_data['sender_name'] = sender_info['name']
                email_data['sender_email'] = sender_info['email']
                
                # Extract recipients (To / Cc / Bcc)
                recipient_groups = self._extract_recipient_groups(msg)
                email_data['to_recipients'] = recipient_groups['to']
                email_data['cc_recipients'] = recipient_groups['cc']
                email_data['bcc_recipients'] = recipient_groups['bcc']
                # Backward compatibility for consumers expecting a single list
                email_data['recipients'] = recipient_groups['to']
                
                # Set received date (usually same as sent date for incoming emails)
                if email_data['sent_date']:
                    email_data['received_date'] = email_data['sent_date']
                
                # Extract attachments
                email_data['attachments'] = self._extract_attachments(msg)
                
                # Extract headers
                email_data['headers'] = self._extract_headers(msg)

                email_data['html_content'] = self._sanitize_text_content(email_data.get('html_content', ''))
                email_data['text_content'] = self._sanitize_text_content(email_data.get('text_content', ''))
                
                logger.info(f"Successfully parsed email: {email_data['subject']}")
                
                return email_data
            finally:
                # Always close the message to release file handle (critical for Windows)
                try:
                    msg.close()
                except:
                    pass
            
        except Exception as e:
            logger.error(f"Error parsing .msg file {file_path}: {str(e)}")
            return {
                'success': False,
                'error': str(e),
                'file_path': file_path
            }

    def parse_eml_file(self, file_path: str) -> Dict[str, Any]:
        """Parse a .eml (RFC 822) email file saved from Outlook or other clients."""
        try:
            logger.info(f"Parsing .eml file: {file_path}")

            if not os.path.exists(file_path):
                return {
                    'success': False,
                    'error': f'File not found: {file_path}'
                }

            if os.path.getsize(file_path) <= 0:
                return {
                    'success': False,
                    'error': 'Uploaded email file is empty.'
                }

            with open(file_path, 'rb') as handle:
                msg = email.message_from_binary_file(handle, policy=policy.default)

            sender_name, sender_email = self._extract_email_from_string(msg.get('From', '') or '')
            sent_date = self._parse_eml_date(msg.get('Date'))
            received_date = self._parse_eml_date(msg.get('Delivery-Date')) or sent_date

            html_content, text_content, attachments = self._extract_eml_body_and_attachments(msg)
            recipient_groups = self._extract_eml_recipient_groups(msg)

            email_data = {
                'success': True,
                'subject': self._safe_get(msg.get('Subject', ''), ''),
                'sender_name': sender_name or '',
                'sender_email': sender_email or '',
                'sent_date': sent_date,
                'received_date': received_date or sent_date,
                'html_content': self._sanitize_text_content(html_content),
                'text_content': self._sanitize_text_content(text_content),
                'recipients': recipient_groups['to'],
                'to_recipients': recipient_groups['to'],
                'cc_recipients': recipient_groups['cc'],
                'bcc_recipients': recipient_groups['bcc'],
                'attachments': attachments,
                'headers': self._extract_eml_headers(msg),
                'message_id': self._safe_get(msg.get('Message-ID', ''), ''),
                'file_path': file_path,
                'file_size': os.path.getsize(file_path),
            }

            logger.info(f"Successfully parsed EML email: {email_data['subject']}")
            return email_data

        except Exception as e:
            logger.error(f"Error parsing .eml file {file_path}: {str(e)}")
            return {
                'success': False,
                'error': str(e),
                'file_path': file_path
            }

    def _parse_eml_date(self, value: Any) -> Any:
        if not value:
            return None
        try:
            parsed = parsedate_to_datetime(str(value))
            if parsed.tzinfo is None:
                parsed = parsed.replace(tzinfo=timezone.utc)
            return parsed.isoformat()
        except Exception:
            return self._safe_get(value, None)

    def _extract_eml_recipient_groups(self, msg: Message) -> Dict[str, List[str]]:
        groups = {
            'to': self._addresses_from_header(msg.get('To', '')),
            'cc': self._addresses_from_header(msg.get('Cc', '')),
            'bcc': self._addresses_from_header(msg.get('Bcc', '')),
        }
        for key in groups:
            groups[key] = self._dedupe_preserve_order(groups[key])
        return groups

    def _addresses_from_header(self, header_value: str) -> List[str]:
        if not header_value:
            return []
        results: List[str] = []
        for _name, addr in getaddresses([header_value]):
            addr = (addr or '').strip()
            if addr:
                results.append(addr)
                continue
            name = (_name or '').strip()
            if name:
                results.append(name)
        return results

    def _extract_eml_headers(self, msg: Message) -> dict:
        headers = {}
        for key in ('From', 'To', 'Cc', 'Bcc', 'Subject', 'Date', 'Message-ID', 'Reply-To'):
            value = msg.get(key)
            if value:
                headers[key] = self._safe_get(value, '')
        return headers

    def _extract_eml_body_and_attachments(self, msg: Message) -> Tuple[str, str, list]:
        html_content = ''
        text_content = ''
        attachment_parts: List[Message] = []

        if msg.is_multipart():
            for part in msg.walk():
                if part.get_content_maintype() == 'multipart':
                    continue

                if self._is_eml_attachment_candidate(part):
                    attachment_parts.append(part)
                    continue

                content_type = part.get_content_type()
                if content_type == 'text/html' and not html_content:
                    html_content = self._safe_get(part.get_content(), '')
                elif content_type == 'text/plain' and not text_content:
                    text_content = self._safe_get(part.get_content(), '')
        else:
            content_type = msg.get_content_type()
            if content_type == 'text/html':
                html_content = self._safe_get(msg.get_content(), '')
            elif self._is_eml_attachment_candidate(msg):
                attachment_parts.append(msg)
            else:
                text_content = self._safe_get(msg.get_content(), '')

        combined_body = f"{text_content}{html_content}"
        attachments = []
        for part in attachment_parts:
            attachment_data = self._build_eml_attachment(part, combined_body)
            if attachment_data:
                attachments.append(attachment_data)

        return html_content, text_content, attachments

    def _build_eml_attachment(self, part: Message, combined_body: str = '') -> Optional[dict]:
        try:
            payload = self._read_eml_part_bytes(part)
            filename = self._get_eml_part_filename(part) or 'attachment'
            content_id = self._safe_get(part.get('Content-ID', ''), '')
            disposition = str(part.get('Content-Disposition', '') or '').lower()
            content_type = self._safe_get(part.get_content_type(), 'application/octet-stream')
            is_inline = self._is_inline_attachment(content_id, combined_body, disposition)

            return self._build_attachment_record(
                filename,
                content_type,
                content_id,
                is_inline,
                payload,
            )
        except Exception as e:
            logger.warning(f"Error processing EML attachment: {str(e)}")
            return None
    
    def _safe_get(self, value: Any, default: Any = None) -> Any:
        """Safely get value and convert to JSON-serializable format."""
        if value is None:
            return default
        
        if isinstance(value, str):
            return value
        elif isinstance(value, bytes):
            try:
                return value.decode('utf-8', errors='ignore')
            except:
                return str(value)
        elif isinstance(value, datetime):
            # Ensure datetime is timezone-aware before converting to ISO
            # If naive (no timezone), assume UTC to preserve the exact time
            if value.tzinfo is None:
                # Naive datetime - assume UTC to preserve original time
                value = value.replace(tzinfo=timezone.utc)
            return value.isoformat()
        elif isinstance(value, (int, float, bool)):
            return value
        elif isinstance(value, (list, tuple)):
            return [self._safe_get(item) for item in value]
        elif isinstance(value, dict):
            return {str(k): self._safe_get(v) for k, v in value.items()}
        else:
            try:
                return str(value)
            except:
                return default
    
    def _extract_sender_info(self, msg) -> Dict[str, str]:
        """Extract sender name and email from message."""
        sender_fields = [
            'sender', 'from', 'senderEmail', 'senderEmailAddress', 'senderName',
            'from_', 'fromAddress', 'fromAddresses', 'fromEmail', 'fromEmailAddress',
            'fromName', 'fromDisplayName', 'fromDisplay', 'fromUser', 'fromUserEmail',
            'senderAddress', 'senderAddresses', 'senderDisplayName', 'senderDisplay',
            'senderUser', 'senderUserEmail', 'senderEmailAddresses', 'senderEmails'
        ]
        
        sender_info = None
        for field in sender_fields:
            try:
                if hasattr(msg, field):
                    value = getattr(msg, field)
                    if value:
                        sender_info = value
                        break
            except:
                continue
        
        if not sender_info:
            return {'name': '', 'email': ''}
        
        # Parse sender information
        name, email = self._extract_email_from_string(str(sender_info))
        return {'name': name or '', 'email': email or ''}
    
    def _extract_recipient_groups(self, msg) -> Dict[str, List[str]]:
        """Extract To, Cc, and Bcc recipients from a parsed .msg message."""
        groups: Dict[str, List[str]] = {'to': [], 'cc': [], 'bcc': []}

        type_map = {}
        if RecipientType is not None:
            type_map = {
                RecipientType.TO: 'to',
                RecipientType.CC: 'cc',
                RecipientType.BCC: 'bcc',
            }

        # Primary: typed Recipient objects from extract_msg
        try:
            if hasattr(msg, 'recipients') and msg.recipients:
                for recipient in msg.recipients:
                    try:
                        rtype = getattr(recipient, 'type', None)
                        group_key = type_map.get(rtype)
                        if not group_key:
                            continue
                        entry = self._format_recipient_entry(recipient)
                        if entry:
                            groups[group_key].append(entry)
                    except Exception as e:
                        logger.warning(f"Error processing recipient object: {str(e)}")
        except Exception as e:
            logger.warning(f"Error reading typed recipients: {str(e)}")

        # Fallback: header/string fields when typed extraction is empty
        string_field_map = {
            'to': 'to',
            'cc': 'cc',
            'bcc': 'bcc',
        }
        for group_key, attr in string_field_map.items():
            if groups[group_key]:
                continue
            try:
                if hasattr(msg, attr):
                    value = getattr(msg, attr)
                    if value:
                        groups[group_key] = self._parse_recipient_string(str(value))
            except Exception as e:
                logger.warning(f"Error reading {attr} field: {str(e)}")

        for group_key in groups:
            groups[group_key] = self._dedupe_preserve_order(groups[group_key])

        return groups

    def _format_recipient_entry(self, recipient) -> Optional[str]:
        """Normalize a Recipient object to an email address or display name."""
        try:
            if hasattr(recipient, 'formatted') and recipient.formatted:
                name, email = self._extract_email_from_string(str(recipient.formatted))
                if email:
                    return email
                if name:
                    return name

            if hasattr(recipient, 'email') and recipient.email:
                email = str(recipient.email).strip()
                if email:
                    return email

            if hasattr(recipient, 'name') and recipient.name:
                name = str(recipient.name).strip()
                if name:
                    return name
        except Exception as e:
            logger.warning(f"Error formatting recipient entry: {str(e)}")

        return None

    def _parse_recipient_string(self, text: str) -> List[str]:
        """Parse a To/Cc/Bcc header string (comma or semicolon separated)."""
        if not text:
            return []

        results: List[str] = []
        for part in re.split(r'[;,]', text):
            part = part.strip()
            if not part:
                continue
            name, email = self._extract_email_from_string(part)
            if email:
                results.append(email)
            elif name:
                results.append(name)

        return results

    def _dedupe_preserve_order(self, items: List[str]) -> List[str]:
        """Remove duplicate recipient entries while preserving order."""
        seen = set()
        deduped: List[str] = []
        for item in items:
            key = item.strip().lower()
            if not key or key in seen:
                continue
            seen.add(key)
            deduped.append(item.strip())
        return deduped
    
    def _extract_email_from_string(self, text: str) -> Tuple[Optional[str], Optional[str]]:
        """Extract email address from string that might contain name and email."""
        if not text:
            return None, None
        
        text = str(text).strip()
        
        # Format: "Name <email@domain.com>"
        if '<' in text and '>' in text:
            try:
                email_part = text.split('<')[1].split('>')[0].strip()
                name_part = text.split('<')[0].strip()
                
                # Validate email
                if '@' in email_part and '.' in email_part.split('@')[1]:
                    return name_part if name_part else None, email_part
            except:
                pass
        
        # Format: "email@domain.com" or "Name email@domain.com"
        if '@' in text:
            parts = text.split()
            email_part = None
            name_parts = []
            
            for part in parts:
                if '@' in part and '.' in part.split('@')[1]:
                    email_part = part
                else:
                    name_parts.append(part)
            
            if email_part:
                name_part = ' '.join(name_parts) if name_parts else None
                return name_part, email_part
        
        # No valid email found
        return text if text else None, None
    
    def _extract_attachments(self, msg) -> list:
        """Extract attachment information from message."""
        attachments = []
        
        # Get email body to check for inline references
        body = self._safe_get(msg.body, '')
        html_body = self._safe_get(msg.htmlBody, '')
        combined_body = f"{body}{html_body}"
        
        try:
            for attachment in msg.attachments:
                try:
                    content_id = self._safe_get(getattr(attachment, 'contentId', ''), '')
                    disposition = self._safe_get(getattr(attachment, 'contentDisposition', ''), '')
                    is_inline = self._is_inline_attachment(content_id, combined_body, disposition)
                    payload = self._read_msg_attachment_bytes(attachment)
                    content_type = self._safe_get(
                        getattr(attachment, 'contentType', 'application/octet-stream'),
                        'application/octet-stream',
                    )
                    filename = self._safe_get(
                        attachment.longFilename or attachment.shortFilename,
                        'Unknown',
                    )

                    attachments.append(self._build_attachment_record(
                        filename,
                        content_type,
                        content_id,
                        is_inline,
                        payload,
                    ))
                    
                except Exception as e:
                    logger.warning(f"Error processing attachment: {str(e)}")
                    attachments.append({
                        'filename': 'Unknown',
                        'display_name': 'Unknown',
                        'content_type': 'application/octet-stream',
                        'content_id': '',
                        'is_inline': False,
                        'size': 0,
                        'file_size': 0,
                        'data': None
                    })
        except Exception as e:
            logger.warning(f"Error extracting attachments: {str(e)}")
        
        return attachments
    
    def _extract_headers(self, msg) -> dict:
        """Extract email headers from message."""
        headers = {}
        
        try:
            if hasattr(msg, 'headers') and msg.headers:
                if isinstance(msg.headers, dict):
                    headers = {k: self._safe_get(v) for k, v in msg.headers.items()}
                elif isinstance(msg.headers, str):
                    # Parse headers manually
                    for line in msg.headers.split('\n'):
                        line = line.strip()
                        if ':' in line:
                            header_name, header_value = line.split(':', 1)
                            headers[header_name.strip()] = header_value.strip()
        except Exception as e:
            logger.warning(f"Error extracting headers: {str(e)}")
        
        return headers
    
    def test_parsing(self, file_path: str) -> Dict[str, Any]:
        """Test parsing on a specific file and return debug information."""
        try:
            logger.info(f"Testing parsing for: {file_path}")
            
            result = self.parse_msg_file(file_path)
            
            return {
                'success': True,
                'file_path': file_path,
                'file_exists': os.path.exists(file_path),
                'file_size': os.path.getsize(file_path) if os.path.exists(file_path) else 0,
                'parsed_data': result,
                'extract_msg_available': 'extract_msg' in sys.modules
            }
            
        except Exception as e:
            logger.error(f"Error in test parsing: {str(e)}")
            return {
                'success': False,
                'error': str(e),
                'file_path': file_path,
                'file_exists': os.path.exists(file_path)
            }
