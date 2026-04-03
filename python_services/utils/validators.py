"""
Validation utilities for file uploads and data processing.
"""

import mimetypes
from pathlib import Path
from typing import List


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
    return ext in [e.lower() for e in allowed_extensions]


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

