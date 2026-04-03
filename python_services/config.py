"""
Configuration for Migration Manager Python Services

This module contains all configuration settings for the unified Python services.
Settings can be overridden via environment variables.
"""

import os
from pathlib import Path

# Base configuration
SERVICE_NAME = "Migration Manager Python Services"
SERVICE_VERSION = "1.0.0"

# Server configuration
HOST = os.getenv('SERVICE_HOST', '127.0.0.1')
PORT = int(os.getenv('SERVICE_PORT', '5001'))
DEBUG = os.getenv('DEBUG', 'False').lower() == 'true'
RELOAD = os.getenv('RELOAD', 'False').lower() == 'true'

# File upload limits
MAX_FILE_SIZE_MB = int(os.getenv('MAX_FILE_SIZE_MB', '30'))
ALLOWED_PDF_SIZE_MB = int(os.getenv('ALLOWED_PDF_SIZE_MB', '50'))
ALLOWED_EMAIL_SIZE_MB = int(os.getenv('ALLOWED_EMAIL_SIZE_MB', '30'))

# PDF processing configuration
PDF_MAX_DPI = int(os.getenv('PDF_MAX_DPI', '300'))
PDF_DEFAULT_DPI = int(os.getenv('PDF_DEFAULT_DPI', '150'))
PDF_SUPPORTED_FORMATS = ['PNG', 'JPEG', 'PDF']

# Email processing configuration
EMAIL_PARSE_TIMEOUT = int(os.getenv('EMAIL_PARSE_TIMEOUT', '60'))
EMAIL_MAX_ATTACHMENT_SIZE = int(os.getenv('EMAIL_MAX_ATTACHMENT_SIZE', '10485760'))  # 10MB

# Document Conversion Configuration
LIBREOFFICE_PATH = os.getenv('LIBREOFFICE_PATH', None)
DOCX_CONVERTER_METHOD = os.getenv('DOCX_CONVERTER_METHOD', 'auto')  # auto, libreoffice, docx2pdf, disabled

# Security configuration
SECURITY_SCAN_ATTACHMENTS = os.getenv('SECURITY_SCAN_ATTACHMENTS', 'True').lower() == 'true'
DANGEROUS_EXTENSIONS = [
    '.exe', '.bat', '.cmd', '.scr', '.pif', '.com', '.vbs', '.js',
    '.jar', '.war', '.ear', '.sh', '.ps1', '.py', '.pl'
]

# Logging configuration
LOG_LEVEL = os.getenv('LOG_LEVEL', 'INFO')
LOG_RETENTION_DAYS = int(os.getenv('LOG_RETENTION_DAYS', '30'))
LOG_DIR = Path(__file__).parent / 'logs'

# CORS configuration
CORS_ORIGINS = os.getenv('CORS_ORIGINS', '*').split(',')
CORS_ALLOW_CREDENTIALS = os.getenv('CORS_ALLOW_CREDENTIALS', 'True').lower() == 'true'

# Database configuration (if needed for caching)
DATABASE_URL = os.getenv('DATABASE_URL', 'sqlite:///./services.db')

# Cache configuration
CACHE_TTL = int(os.getenv('CACHE_TTL', '3600'))  # 1 hour
CACHE_MAX_SIZE = int(os.getenv('CACHE_MAX_SIZE', '1000'))

# Email analysis configuration
EMAIL_CATEGORIES = {
    'business': [
        'meeting', 'proposal', 'contract', 'invoice', 'payment', 'project',
        'client', 'customer', 'business', 'work', 'office', 'company'
    ],
    'personal': [
        'family', 'friend', 'personal', 'birthday', 'holiday', 'vacation',
        'weekend', 'party', 'celebration', 'home', 'personal', 'private'
    ],
    'migration': [
        'visa', 'immigration', 'application', 'tribunal', 'appeal', 'aat',
        'migration', 'permanent residence', 'citizenship', 'passport',
        'immigration agent', 'migration lawyer', 'visa application'
    ],
    'legal': [
        'legal', 'court', 'lawyer', 'attorney', 'lawsuit', 'settlement',
        'litigation', 'legal advice', 'legal document', 'contract'
    ],
    'spam': [
        'free', 'win', 'congratulations', 'urgent', 'act now', 'limited time',
        'click here', 'unsubscribe', 'viagra', 'casino', 'lottery'
    ]
}

# Priority keywords
PRIORITY_KEYWORDS = {
    'high': [
        'urgent', 'asap', 'immediately', 'critical', 'emergency',
        'deadline', 'expires', 'final notice', 'action required'
    ],
    'medium': [
        'please', 'request', 'follow up', 'reminder', 'update',
        'status', 'progress', 'review', 'consider'
    ],
    'low': [
        'information', 'FYI', 'for your information', 'note',
        'reference', 'background', 'optional'
    ]
}

# Security patterns for HTML cleaning
SECURITY_PATTERNS = [
    r'<script[^>]*>.*?</script>',
    r'javascript:',
    r'vbscript:',
    r'on\w+\s*=',
    r'<iframe[^>]*>',
    r'<object[^>]*>',
    r'<embed[^>]*>',
    r'<form[^>]*>',
    r'<input[^>]*>',
    r'<button[^>]*>'
]

# Sentiment analysis keywords
SENTIMENT_KEYWORDS = {
    'positive': [
        'good', 'great', 'excellent', 'wonderful', 'amazing', 'fantastic',
        'thank', 'thanks', 'appreciate', 'pleased', 'happy', 'delighted',
        'success', 'successful', 'congratulations', 'celebrate'
    ],
    'negative': [
        'bad', 'terrible', 'awful', 'horrible', 'disappointed', 'angry',
        'frustrated', 'upset', 'concerned', 'worried', 'problem', 'issue',
        'error', 'failed', 'failure', 'complaint', 'unhappy'
    ]
}

# Language detection keywords
LANGUAGE_KEYWORDS = {
    'english': ['the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'],
    'spanish': ['el', 'la', 'de', 'que', 'y', 'a', 'en', 'un', 'es', 'se', 'no', 'te', 'lo', 'le'],
    'french': ['le', 'la', 'de', 'et', 'à', 'un', 'il', 'que', 'ne', 'se', 'ce', 'pas', 'tout', 'plus']
}

# API endpoints configuration
API_PREFIX = "/api"
HEALTH_ENDPOINT = "/health"
DOCS_ENDPOINT = "/docs"
REDOC_ENDPOINT = "/redoc"

# Rate limiting (requests per minute)
RATE_LIMIT = int(os.getenv('RATE_LIMIT', '100'))

# Timeout configurations
REQUEST_TIMEOUT = int(os.getenv('REQUEST_TIMEOUT', '120'))
UPLOAD_TIMEOUT = int(os.getenv('UPLOAD_TIMEOUT', '300'))

# Development settings
if DEBUG:
    LOG_LEVEL = 'DEBUG'
    RELOAD = True

# Create logs directory if it doesn't exist
LOG_DIR.mkdir(exist_ok=True)

# Export configuration
__all__ = [
    'SERVICE_NAME', 'SERVICE_VERSION', 'HOST', 'PORT', 'DEBUG', 'RELOAD',
    'MAX_FILE_SIZE_MB', 'ALLOWED_PDF_SIZE_MB', 'ALLOWED_EMAIL_SIZE_MB',
    'PDF_MAX_DPI', 'PDF_DEFAULT_DPI', 'PDF_SUPPORTED_FORMATS',
    'EMAIL_PARSE_TIMEOUT', 'EMAIL_MAX_ATTACHMENT_SIZE',
    'LIBREOFFICE_PATH', 'DOCX_CONVERTER_METHOD',
    'SECURITY_SCAN_ATTACHMENTS', 'DANGEROUS_EXTENSIONS',
    'LOG_LEVEL', 'LOG_RETENTION_DAYS', 'LOG_DIR',
    'CORS_ORIGINS', 'CORS_ALLOW_CREDENTIALS',
    'DATABASE_URL', 'CACHE_TTL', 'CACHE_MAX_SIZE',
    'EMAIL_CATEGORIES', 'PRIORITY_KEYWORDS', 'SENTIMENT_KEYWORDS',
    'LANGUAGE_KEYWORDS', 'SECURITY_PATTERNS',
    'API_PREFIX', 'HEALTH_ENDPOINT', 'DOCS_ENDPOINT', 'REDOC_ENDPOINT',
    'RATE_LIMIT', 'REQUEST_TIMEOUT', 'UPLOAD_TIMEOUT'
]
