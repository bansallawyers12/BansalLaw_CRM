#!/usr/bin/env python3
"""Verify WeasyPrint can generate PDFs (used by start_services.bat)."""

from __future__ import annotations

import sys

from utils.weasyprint_env import configure_weasyprint_dll_paths


def main() -> int:
    dll_dir = configure_weasyprint_dll_paths()

    try:
        from weasyprint import HTML
    except Exception as exc:  # noqa: BLE001 - surface full import error to user
        print('WeasyPrint import failed:', exc)
        if sys.platform == 'win32':
            print()
            print('On Windows, install GTK3 runtime for WeasyPrint, then restart:')
            print('  powershell -ExecutionPolicy Bypass -File setup_weasyprint_windows.ps1')
        return 1

    try:
        pdf = HTML(string='<html><body><p>WeasyPrint OK</p></body></html>').write_pdf()
    except Exception as exc:  # noqa: BLE001
        print('WeasyPrint PDF test failed:', exc)
        return 1

    if not pdf:
        print('WeasyPrint returned empty PDF bytes')
        return 1

    if dll_dir:
        print('WeasyPrint OK (DLL dir:', dll_dir + ')')
    else:
        print('WeasyPrint OK')

    return 0


if __name__ == '__main__':
    raise SystemExit(main())
