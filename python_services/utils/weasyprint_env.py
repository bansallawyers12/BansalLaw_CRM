"""Configure WeasyPrint native library paths on Windows before import."""

from __future__ import annotations

import os
from pathlib import Path


def configure_weasyprint_dll_paths() -> str | None:
    """
    Add common GTK/Cairo/Pango install locations to WeasyPrint search paths.
    Returns the directory used, or None if no known runtime was found.
    """
    if os.name != 'nt':
        return None

    script_root = Path(__file__).resolve().parent.parent
    local_gtk_bin = script_root / 'vendor' / 'GTK3-Runtime' / 'bin'

    candidates = [
        os.environ.get('WEASYPRINT_DLL_DIRECTORIES', '').strip(),
        str(local_gtk_bin),
        r'C:\Program Files\GTK3-Runtime Win64\bin',
        r'C:\msys64\mingw64\bin',
        r'C:\msys64\ucrt64\bin',
    ]

    for candidate in candidates:
        if not candidate:
            continue

        dll_dir = Path(candidate)
        if not dll_dir.is_dir():
            continue

        cairo_dll = dll_dir / 'libcairo-2.dll'
        if not cairo_dll.exists():
            continue

        os.environ['WEASYPRINT_DLL_DIRECTORIES'] = str(dll_dir)
        current_path = os.environ.get('PATH', '')
        dll_path = str(dll_dir)
        if dll_path.lower() not in current_path.lower():
            os.environ['PATH'] = dll_path + os.pathsep + current_path

        return dll_path

    return None
