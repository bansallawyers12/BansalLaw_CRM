"""
Convert Office documents (DOC/DOCX/PPT/…) to PDF via LibreOffice headless.

Used by CRM embed preview for near-WYSIWYG layout when soffice is installed.
Falls back is handled by the PHP caller (LegacyDoc HTML / PhpWord).
"""

from __future__ import annotations

import os
import shutil
import subprocess
import tempfile
import time
from pathlib import Path
from typing import Optional, Tuple

from utils.logger import setup_logger

logger = setup_logger(__name__)

SUPPORTED_EXTENSIONS = {
    ".doc",
    ".docx",
    ".docm",
    ".rtf",
    ".odt",
    ".ppt",
    ".pptx",
    ".odp",
    ".xls",
    ".xlsx",
    ".ods",
}


class DocumentConverterService:
    def __init__(self) -> None:
        self._soffice_path: Optional[str] = None
        self._probed = False
        self._available: Optional[bool] = None
        self.timeout_seconds = int(os.getenv("LIBREOFFICE_TIMEOUT", "120"))

    def find_soffice(self) -> Optional[str]:
        if self._probed:
            return self._soffice_path

        self._probed = True
        candidates: list[str] = []

        env_path = (os.getenv("LIBREOFFICE_PATH") or os.getenv("SOFFICE_PATH") or "").strip()
        if env_path:
            candidates.append(env_path)

        which = shutil.which("soffice") or shutil.which("libreoffice")
        if which:
            candidates.append(which)

        candidates.extend(
            [
                r"C:\Program Files\LibreOffice\program\soffice.exe",
                r"C:\Program Files (x86)\LibreOffice\program\soffice.exe",
                "/usr/bin/soffice",
                "/usr/bin/libreoffice",
                "/usr/lib/libreoffice/program/soffice",
                "/snap/bin/libreoffice",
            ]
        )

        for path in candidates:
            if path and Path(path).is_file():
                self._soffice_path = path
                logger.info("LibreOffice found at %s", path)
                return self._soffice_path

        logger.warning("LibreOffice (soffice) not found - Office-to-PDF preview disabled")
        self._soffice_path = None
        return None

    def is_available(self) -> bool:
        if self._available is not None:
            return self._available
        self._available = self.find_soffice() is not None
        return self._available

    def convert_to_pdf(self, file_bytes: bytes, filename: str) -> Tuple[Optional[bytes], Optional[str]]:
        """
        Convert Office bytes to PDF.

        Returns (pdf_bytes, error_message).
        """
        if not file_bytes:
            return None, "empty_file"

        ext = Path(filename or "document.bin").suffix.lower()
        if ext not in SUPPORTED_EXTENSIONS:
            return None, f"unsupported_extension:{ext or 'none'}"

        soffice = self.find_soffice()
        if not soffice:
            return None, "libreoffice_unavailable"

        work_dir = Path(tempfile.mkdtemp(prefix="crm_office_pdf_"))
        try:
            safe_name = "".join(ch if ch.isalnum() or ch in "._-" else "_" for ch in Path(filename).name)
            if not safe_name.lower().endswith(ext):
                safe_name = f"{safe_name or 'document'}{ext}"

            input_path = work_dir / safe_name
            input_path.write_bytes(file_bytes)

            # Unique UserInstallation profile avoids "profile locked" when concurrent converts run.
            profile_dir = work_dir / "lo_profile"
            profile_dir.mkdir(parents=True, exist_ok=True)
            profile_uri = profile_dir.as_uri()

            cmd = [
                soffice,
                "--headless",
                "--nologo",
                "--nolockcheck",
                "--nodefault",
                "--nofirststartwizard",
                f"-env:UserInstallation={profile_uri}",
                "--convert-to",
                "pdf:writer_pdf_Export",
                "--outdir",
                str(work_dir),
                str(input_path),
            ]

            # Spreadsheets / presentations use generic pdf filter when writer filter fails.
            started = time.monotonic()
            result = subprocess.run(
                cmd,
                capture_output=True,
                text=True,
                timeout=self.timeout_seconds,
                check=False,
            )

            pdf_path = input_path.with_suffix(".pdf")
            if not pdf_path.is_file():
                # Retry with generic pdf filter (better for ppt/xls).
                cmd_generic = [
                    soffice,
                    "--headless",
                    "--nologo",
                    "--nolockcheck",
                    "--nodefault",
                    "--nofirststartwizard",
                    f"-env:UserInstallation={profile_uri}",
                    "--convert-to",
                    "pdf",
                    "--outdir",
                    str(work_dir),
                    str(input_path),
                ]
                result = subprocess.run(
                    cmd_generic,
                    capture_output=True,
                    text=True,
                    timeout=self.timeout_seconds,
                    check=False,
                )

            if not pdf_path.is_file():
                stderr = (result.stderr or result.stdout or "").strip()
                logger.error(
                    "LibreOffice conversion failed for %s (exit=%s, %.1fs): %s",
                    safe_name,
                    result.returncode,
                    time.monotonic() - started,
                    stderr[:500],
                )
                return None, "conversion_failed"

            pdf_bytes = pdf_path.read_bytes()
            if not pdf_bytes.startswith(b"%PDF"):
                return None, "invalid_pdf_output"

            logger.info(
                "Converted %s to PDF (%s bytes, %.1fs)",
                safe_name,
                len(pdf_bytes),
                time.monotonic() - started,
            )
            return pdf_bytes, None
        except subprocess.TimeoutExpired:
            logger.error("LibreOffice conversion timed out for %s", filename)
            return None, "timeout"
        except Exception as exc:  # noqa: BLE001
            logger.exception("LibreOffice conversion error for %s: %s", filename, exc)
            return None, str(exc)
        finally:
            shutil.rmtree(work_dir, ignore_errors=True)
