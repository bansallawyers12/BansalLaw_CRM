"""
Validation utilities for file uploads and data processing.
"""

import mimetypes
import re
from pathlib import Path
from typing import List, Optional


def detect_email_upload_extension(content: bytes) -> Optional[str]:
    if not content:
        return None

    if len(content) >= 4 and content[:4] == b'\xD0\xCF\x11\xE0':
        return 'msg'

    try:
        header = content[:4096].decode('utf-8', errors='ignore')
    except Exception:
        header = ''

    if re.match(r'^(From:|Return-Path:|Received:|MIME-Version:|Date:|X-)', header, re.IGNORECASE):
        return 'eml'

    return None


def validate_email_upload(filename: str, content: bytes, allowed_extensions: List[str]) -> bool:
    if validate_file_type(filename, allowed_extensions):
        return True

    detected = detect_email_upload_extension(content)
    if not detected:
        return False

    normalized_allowed = [
        ext.lower().lstrip('.')
        for ext in allowed_extensions
    ]
    return detected in normalized_allowed


def resolve_email_upload_filename(filename: str, content: bytes) -> str:
    if validate_file_type(filename, allowed_extensions=['.msg', '.eml']):
        return filename

    detected = detect_email_upload_extension(content)
    if not detected:
        return filename

    stem = Path(filename).stem if filename else f'email_{int(__import__("time").time() * 1000)}'
    return f'{stem}.{detected}'


def validate_file_type(filename: str, allowed_extensions: List[str]) -> bool:
    """
    Validate file extension.
    
    Args:
        filename: Name of the file
        allowed_extensions: List of allowed extensions (e.g., ['.pdf', '.msg'])
    
    Returns:
        True if valid, False otherwise
    """
    if not filename:
        return False
    
    ext = Path(filename).suffix.lower()
    normalized_allowed = [e.lower() if e.startswith('.') else f'.{e.lower()}' for e in allowed_extensions]
    return ext in normalized_allowed


def validate_file_size(file_size: int, max_size_mb: int = 20) -> bool:
    """
    Validate file size.
    
    Args:
        file_size: Size in bytes
        max_size_mb: Maximum allowed size in MB
    
    Returns:
        True if valid, False otherwise
    """
    max_size_bytes = max_size_mb * 1024 * 1024
    return file_size <= max_size_bytes


def sanitize_filename(filename: str) -> str:
    """
    Sanitize filename to prevent security issues.
    
    Args:
        filename: Original filename
    
    Returns:
        Sanitized filename
    """
    # Get just the filename (remove any path components)
    filename = Path(filename).name
    
    # Replace potentially dangerous characters
    dangerous_chars = ['..', '/', '\\', '\x00']
    for char in dangerous_chars:
        filename = filename.replace(char, '_')
    
    return filename


def validate_email_address(email: str) -> bool:
    """
    Basic email address validation.
    
    Args:
        email: Email address to validate
    
    Returns:
        True if valid format, False otherwise
    """
    if not email or '@' not in email:
        return False
    
    parts = email.split('@')
    if len(parts) != 2:
        return False
    
    local, domain = parts
    
    # Basic checks
    if not local or not domain:
        return False
    
    if '.' not in domain:
        return False
    
    return True

