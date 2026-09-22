#!/usr/bin/env python3
"""
One-shot CLI for CRM email parsing (no HTTP daemon required).

Used by Laravel when http://127.0.0.1:5002 is unreachable (common on
production before systemd/nohup is started).

Requires Python 3.9+. Prefer python3.13 on the host (same as start_services.sh).

Usage:
  python cli_email.py parse <file> [--timezone TZ] [--metadata-only]
  python cli_email.py parse-render-pdf <file> [--timezone TZ]

Prints a single JSON object to stdout.
"""

import argparse
import json
import sys
from pathlib import Path

if sys.version_info < (3, 9):
    sys.stdout.write(json.dumps({
        'success': False,
        'error': (
            'Python %s.%s is too old for email parsing. '
            'Install/use Python 3.9+ (prefer python3.13).'
            % (sys.version_info[0], sys.version_info[1])
        ),
    }))
    sys.stdout.write('\n')
    raise SystemExit(1)

ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT))

from utils.weasyprint_env import configure_weasyprint_dll_paths

configure_weasyprint_dll_paths()

from services.email_parser_service import EmailParserService
from services.email_renderer_service import EmailRendererService
from utils.datetime_format import DEFAULT_TIMEZONE, format_laravel_datetime
from utils.pdf_payload import attach_pdf_payload, ensure_pdf_output_dir
from utils.validators import resolve_email_upload_filename, validate_email_upload


ALLOWED_EMAIL_EXTENSIONS = ['.msg', '.eml']


def _emit(payload, exit_code=0):
    sys.stdout.write(json.dumps(payload, default=str, ensure_ascii=False))
    sys.stdout.write('\n')
    return exit_code


def _safe_unlink(path):
    try:
        if path.exists():
            path.unlink()
    except Exception:
        pass


def _load_file(path):
    content = path.read_bytes()
    name = path.name
    if not validate_email_upload(name, content, ALLOWED_EMAIL_EXTENSIONS):
        raise ValueError('Invalid file type. Only Outlook email files (.msg, .eml) are allowed.')
    resolved = resolve_email_upload_filename(name, content)
    return content, resolved


def cmd_parse(file_path, metadata_only):
    content, resolved = _load_file(file_path)
    temp_dir = ROOT / 'temp'
    temp_dir.mkdir(parents=True, exist_ok=True)
    temp_path = temp_dir / ('cli_' + Path(resolved).name)
    temp_path.write_bytes(content)
    try:
        parser = EmailParserService()
        result = parser.parse_email_file(str(temp_path))
        if metadata_only:
            result = parser.strip_attachment_payloads(result)
        return result
    finally:
        _safe_unlink(temp_path)


def cmd_parse_render_pdf(file_path, timezone):
    content, resolved = _load_file(file_path)
    temp_dir = ROOT / 'temp'
    temp_dir.mkdir(parents=True, exist_ok=True)
    ensure_pdf_output_dir()
    temp_path = temp_dir / ('cli_' + Path(resolved).name)
    temp_path.write_bytes(content)
    try:
        parser = EmailParserService()
        renderer = EmailRendererService()
        parsed = parser.parse_email_file(str(temp_path))
        if parsed.get('success') is False or parsed.get('error'):
            return parsed

        display_tz = (timezone or DEFAULT_TIMEZONE).strip() or DEFAULT_TIMEZONE
        pdf_bytes, text_preview, pdf_error, pdf_renderer = renderer.render_to_pdf(
            parsed,
            display_timezone=display_tz,
        )

        result = dict(parsed)
        if text_preview:
            result['text_preview'] = text_preview
        if parsed.get('sent_date'):
            result['sent_date_display'] = format_laravel_datetime(parsed['sent_date'], display_tz)

        if pdf_bytes:
            attach_pdf_payload(result, pdf_bytes, pdf_renderer)
        else:
            result['pdf_base64'] = None
            result['pdf_file_path'] = None
            result['pdf_size'] = 0
            result['pdf_generated'] = False
            result['pdf_error'] = pdf_error or 'PDF generation failed'

        return result
    finally:
        _safe_unlink(temp_path)


def main(argv=None):
    parser = argparse.ArgumentParser(description='CRM email parse CLI (no HTTP daemon)')
    parser.add_argument('command', choices=['parse', 'parse-render-pdf'])
    parser.add_argument('file', type=Path)
    parser.add_argument('--timezone', default=DEFAULT_TIMEZONE)
    parser.add_argument('--metadata-only', action='store_true')
    args = parser.parse_args(argv)

    if not args.file.is_file():
        return _emit({'success': False, 'error': 'File not found: %s' % args.file}, 1)

    try:
        if args.command == 'parse':
            result = cmd_parse(args.file, args.metadata_only)
        else:
            result = cmd_parse_render_pdf(args.file, args.timezone)
        ok = not (result.get('success') is False or result.get('error'))
        return _emit(result, 0 if ok else 1)
    except Exception as exc:
        return _emit({'success': False, 'error': str(exc)}, 1)


if __name__ == '__main__':
    raise SystemExit(main())
