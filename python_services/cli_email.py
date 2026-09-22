#!/usr/bin/env python3
"""
One-shot CLI for CRM email parsing (used as fallback when HTTP :5002 is down).

Usage:
  python cli_email.py parse <file> [--original-name NAME] [--timezone TZ] [--metadata-only]
  python cli_email.py parse-render-pdf <file> [--original-name NAME] [--timezone TZ]

Outputs JSON to stdout.
"""

import argparse
import contextlib
import io
import json
import logging
import os
import sys
import time
import warnings
from pathlib import Path

# Keep stdout as pure JSON — PyMuPDF and other libs may print deprecation
# warnings (`import fitz` → "Use import pymupdf") which break PHP json_decode.
os.environ.setdefault('PYTHONWARNINGS', 'ignore')
warnings.filterwarnings('ignore')
logging.disable(logging.CRITICAL)

ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT))

ALLOWED_EMAIL_EXTENSIONS = ['.msg', '.eml']


def _emit(payload: dict, exit_code: int = 0) -> int:
    sys.stdout.write(json.dumps(payload, default=str, ensure_ascii=False))
    sys.stdout.write('\n')
    return exit_code


def _safe_unlink(path: Path):
    try:
        if path and path.exists():
            path.unlink()
    except Exception:
        pass


def _prepare_temp_file(file_path: Path, original_name: str | None) -> tuple[Path, str]:
    from utils.validators import resolve_email_upload_filename, validate_email_upload

    content = file_path.read_bytes()
    validation_name = original_name or file_path.name

    if not validate_email_upload(validation_name, content, ALLOWED_EMAIL_EXTENSIONS):
        raise ValueError(
            f"Invalid file type for '{validation_name}'. Only Outlook email files (.msg, .eml) are allowed."
        )

    resolved_name = resolve_email_upload_filename(validation_name, content)
    temp_dir = ROOT / 'temp'
    temp_dir.mkdir(parents=True, exist_ok=True)
    temp_file = temp_dir / f"cli_{int(time.time() * 1000)}_{Path(resolved_name).name}"
    temp_file.write_bytes(content)
    return temp_file, resolved_name


def cmd_parse(file_path: Path, original_name: str | None, metadata_only: bool) -> dict:
    from services.email_parser_service import EmailParserService

    temp_path, _ = _prepare_temp_file(file_path, original_name)
    try:
        parser = EmailParserService()
        result = parser.parse_email_file(str(temp_path))
        if metadata_only:
            result = parser.strip_attachment_payloads(result)
        return result
    finally:
        _safe_unlink(temp_path)


def cmd_parse_render_pdf(file_path: Path, original_name: str | None, timezone: str) -> dict:
    from services.email_parser_service import EmailParserService
    from utils.datetime_format import DEFAULT_TIMEZONE, format_laravel_datetime
    from utils.pdf_payload import attach_pdf_payload, ensure_pdf_output_dir

    temp_path, _ = _prepare_temp_file(file_path, original_name)
    try:
        parser = EmailParserService()
        parsed = parser.parse_email_file(str(temp_path))
        if parsed.get('success') is False or parsed.get('error'):
            return parsed

        display_tz = (timezone or DEFAULT_TIMEZONE).strip() or DEFAULT_TIMEZONE
        result = dict(parsed)

        if parsed.get('sent_date'):
            try:
                result['sent_date_display'] = format_laravel_datetime(parsed['sent_date'], display_tz)
            except Exception:
                pass

        try:
            # Lazy import renderer
            try:
                from utils.weasyprint_env import configure_weasyprint_dll_paths
                configure_weasyprint_dll_paths()
            except Exception:
                pass

            from services.email_renderer_service import EmailRendererService

            renderer = EmailRendererService()
            ensure_pdf_output_dir()
            pdf_bytes, text_preview, pdf_error, pdf_renderer = renderer.render_to_pdf(
                parsed,
                display_timezone=display_tz,
            )

            if text_preview:
                result['text_preview'] = text_preview

            if pdf_bytes:
                attach_pdf_payload(result, pdf_bytes, pdf_renderer)
            else:
                result['pdf_base64'] = None
                result['pdf_file_path'] = None
                result['pdf_size'] = 0
                result['pdf_generated'] = False
                result['pdf_error'] = pdf_error or 'PDF generation failed'
        except Exception as render_exc:
            result['pdf_base64'] = None
            result['pdf_file_path'] = None
            result['pdf_size'] = 0
            result['pdf_generated'] = False
            result['pdf_error'] = str(render_exc)

        return result
    finally:
        _safe_unlink(temp_path)


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="CRM email parse CLI (no HTTP daemon)")
    parser.add_argument('command', choices=['parse', 'parse-render-pdf'])
    parser.add_argument('file', type=Path)
    parser.add_argument('--original-name', default=None, help='Original client filename with extension')
    parser.add_argument('--timezone', default='Australia/Melbourne')
    parser.add_argument('--metadata-only', action='store_true')
    args = parser.parse_args(argv)

    if not args.file.is_file():
        return _emit({'success': False, 'error': f'File not found: {args.file}'}, 1)

    try:
        # Capture library print() noise (e.g. PyMuPDF fitz deprecation) so only JSON hits stdout.
        sink = io.StringIO()
        with contextlib.redirect_stdout(sink):
            if args.command == 'parse':
                result = cmd_parse(args.file, args.original_name, args.metadata_only)
            else:
                result = cmd_parse_render_pdf(args.file, args.original_name, args.timezone)

        leaked = sink.getvalue().strip()
        if leaked:
            sys.stderr.write(leaked + '\n')

        ok = not (result.get('success') is False or result.get('error'))
        return _emit(result, 0 if ok else 1)
    except Exception as exc:
        return _emit({'success': False, 'error': str(exc)}, 1)


if __name__ == '__main__':
    raise SystemExit(main())
