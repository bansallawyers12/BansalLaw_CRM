"""
PDF payload helpers: prefer temp file paths over large base64 in JSON responses.
"""

from __future__ import annotations

import base64
import time
from pathlib import Path
from typing import Any, Dict, Optional

from config import PDF_INLINE_MAX_BYTES, PDF_OUTPUT_DIR, PDF_TEMP_RETENTION_SECONDS
from utils.logger import setup_logger

logger = setup_logger(__name__)


def ensure_pdf_output_dir() -> Path:
    PDF_OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    return PDF_OUTPUT_DIR


def cleanup_stale_pdf_outputs(max_age_seconds: Optional[int] = None) -> int:
    """Delete old generated PDFs under PDF_OUTPUT_DIR. Returns count removed."""
    age = max_age_seconds if max_age_seconds is not None else PDF_TEMP_RETENTION_SECONDS
    age = max(60, int(age))
    root = ensure_pdf_output_dir()
    cutoff = time.time() - age
    removed = 0
    for path in root.glob('*.pdf'):
        try:
            if path.stat().st_mtime < cutoff:
                path.unlink(missing_ok=True)
                removed += 1
        except OSError as exc:
            logger.warning(f'Could not remove stale PDF {path}: {exc}')
    return removed


def write_pdf_output(pdf_bytes: bytes, prefix: str = 'email') -> Path:
    """Write PDF bytes to a unique temp file and return the absolute path."""
    ensure_pdf_output_dir()
    safe_prefix = ''.join(c if c.isalnum() or c in '-_' else '_' for c in (prefix or 'email'))[:40]
    filename = f'{safe_prefix}_{int(time.time() * 1000)}.pdf'
    out_path = PDF_OUTPUT_DIR / filename
    out_path.write_bytes(pdf_bytes)
    return out_path.resolve()


def attach_pdf_payload(result: Dict[str, Any], pdf_bytes: Optional[bytes], pdf_renderer: Optional[str] = None) -> Dict[str, Any]:
    """
    Attach PDF to a response dict using file path by default.

    Includes pdf_base64 only when the PDF is small enough for inline JSON
    (PDF_INLINE_MAX_BYTES). Larger PDFs are file-path only to avoid huge payloads.
    """
    if not pdf_bytes:
        result['pdf_base64'] = None
        result['pdf_file_path'] = None
        result['pdf_size'] = 0
        result['pdf_generated'] = False
        return result

    out_path = write_pdf_output(pdf_bytes)
    result['pdf_file_path'] = str(out_path)
    result['pdf_size'] = len(pdf_bytes)
    result['pdf_generated'] = True
    result['pdf_renderer'] = pdf_renderer or 'unknown'

    if len(pdf_bytes) <= PDF_INLINE_MAX_BYTES:
        result['pdf_base64'] = base64.b64encode(pdf_bytes).decode('ascii')
        result['pdf_delivery'] = 'inline_and_file'
    else:
        result['pdf_base64'] = None
        result['pdf_delivery'] = 'file'
        logger.info(
            f'PDF payload via file path only ({len(pdf_bytes)} bytes > inline max {PDF_INLINE_MAX_BYTES})'
        )

    return result


def strip_pdf_for_json_fallback(result: Dict[str, Any]) -> Dict[str, Any]:
    """Omit inline base64 if JSON encoding still fails; keep file path if present."""
    result.pop('pdf_base64', None)
    if result.get('pdf_file_path'):
        result['pdf_generated'] = True
        result['pdf_delivery'] = 'file'
        result['pdf_error'] = result.get('pdf_error')
    else:
        result['pdf_generated'] = False
        result['pdf_size'] = 0
        result['pdf_error'] = result.get('pdf_error') or 'PDF omitted from service response due to size limits'
    return result
