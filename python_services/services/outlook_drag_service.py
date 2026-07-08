"""
Resolve Outlook emails for browser drag-and-drop on Windows.

Browsers cannot read Outlook's virtual drag payload reliably. This service
falls back to Outlook COM automation and reads mail items from the active
Outlook explorer selection or open inspector window.
"""

from __future__ import annotations

import base64
import os
import re
import struct
import sys
import tempfile
from typing import Any, Dict, List, Optional, Tuple

OL_MAIL_ITEM = 43
OL_POST_ITEM = 45
OL_SAVE_AS_MSG = 3
OL_SAVE_AS_EML = 10

FILEDESCRIPTORW_FMT = '<I16s8s8sIIIIII520s'
FILEDESCRIPTORW_SIZE = struct.calcsize(FILEDESCRIPTORW_FMT)


def is_supported() -> bool:
    return sys.platform == 'win32'


def _sanitize_filename(value: str, fallback: str) -> str:
    stem = re.sub(r'[\\/:*?"<>|]+', '_', (value or '').strip())
    stem = re.sub(r'\s+', ' ', stem).strip(' ._')
    if len(stem) > 180:
        stem = stem[:180].rstrip(' ._')
    return stem or fallback


def _encode_file_result(filename: str, content: bytes, source: str, index: int = 0) -> Dict[str, Any]:
    return {
        'filename': filename,
        'content_base64': base64.b64encode(content).decode('ascii'),
        'size': len(content),
        'index': index,
        'source': source,
    }


def _is_mail_item(item) -> bool:
    try:
        class_id = int(getattr(item, 'Class', 0) or 0)
        return class_id in (OL_MAIL_ITEM, OL_POST_ITEM)
    except Exception:
        return False


def _get_outlook_application():
    import win32com.client

    for getter in (
        lambda: win32com.client.GetActiveObject('Outlook.Application'),
        lambda: win32com.client.GetObject(Class='Outlook.Application'),
        lambda: win32com.client.Dispatch('Outlook.Application'),
    ):
        try:
            app = getter()
            if app is not None:
                return app
        except Exception:
            continue

    return None


def _append_mail_item(target: List[Any], seen_entry_ids: set, item, max_items: int) -> None:
    if item is None or len(target) >= max_items:
        return

    if not _is_mail_item(item):
        return

    try:
        entry_id = str(getattr(item, 'EntryID', '') or '')
        if entry_id:
            if entry_id in seen_entry_ids:
                return
            seen_entry_ids.add(entry_id)
        target.append(item)
    except Exception:
        pass


def _append_selection(target: List[Any], seen_entry_ids: set, selection, max_items: int) -> None:
    if selection is None:
        return

    try:
        count = int(getattr(selection, 'Count', 0) or 0)
    except Exception:
        return

    for index in range(1, count + 1):
        if len(target) >= max_items:
            return
        try:
            _append_mail_item(target, seen_entry_ids, selection.Item(index), max_items)
        except Exception:
            continue


def _collect_outlook_mail_items(outlook, max_items: int = 10) -> List[Any]:
    collected: List[Any] = []
    seen_entry_ids: set = set()

    try:
        explorer = outlook.ActiveExplorer()
        if explorer is not None:
            _append_selection(collected, seen_entry_ids, explorer.Selection, max_items)
    except Exception:
        pass

    try:
        explorers = outlook.Explorers
        explorer_count = int(getattr(explorers, 'Count', 0) or 0)
        for index in range(1, explorer_count + 1):
            if len(collected) >= max_items:
                break
            try:
                _append_selection(collected, seen_entry_ids, explorers.Item(index).Selection, max_items)
            except Exception:
                continue
    except Exception:
        pass

    try:
        inspector = outlook.ActiveInspector()
        if inspector is not None:
            _append_mail_item(collected, seen_entry_ids, inspector.CurrentItem, max_items)
    except Exception:
        pass

    try:
        inspectors = outlook.Inspectors
        inspector_count = int(getattr(inspectors, 'Count', 0) or 0)
        for index in range(1, inspector_count + 1):
            if len(collected) >= max_items:
                break
            try:
                _append_mail_item(collected, seen_entry_ids, inspectors.Item(index).CurrentItem, max_items)
            except Exception:
                continue
    except Exception:
        pass

    return collected


def _read_mail_item_bytes(mail_item) -> Optional[Dict[str, Any]]:
    subject = _sanitize_filename(getattr(mail_item, 'Subject', '') or '', 'email')
    filename = f'{subject}.msg'

    temp_path = None
    try:
        temp_fd, temp_path = tempfile.mkstemp(suffix='.msg')
        os.close(temp_fd)
        mail_item.SaveAs(temp_path, OL_SAVE_AS_MSG)
        with open(temp_path, 'rb') as handle:
            content = handle.read()
        if not content:
            return None
        return _encode_file_result(filename, content, 'outlook_com')
    except Exception:
        temp_path = None
        try:
            temp_fd, temp_path = tempfile.mkstemp(suffix='.eml')
            os.close(temp_fd)
            mail_item.SaveAs(temp_path, OL_SAVE_AS_EML)
            with open(temp_path, 'rb') as handle:
                content = handle.read()
            if not content:
                return None
            return _encode_file_result(f'{subject}.eml', content, 'outlook_com')
        except Exception:
            return None
    finally:
        if temp_path and os.path.exists(temp_path):
            try:
                os.remove(temp_path)
            except OSError:
                pass


def _outlook_drag_diagnostic(outlook=None) -> Dict[str, Any]:
    diagnostic: Dict[str, Any] = {
        'outlook_running': outlook is not None,
        'active_explorer': False,
        'explorer_count': 0,
        'inspector_count': 0,
        'selection_count': 0,
        'mail_items_found': 0,
    }

    if outlook is None:
        return diagnostic

    try:
        diagnostic['active_explorer'] = outlook.ActiveExplorer() is not None
    except Exception:
        pass

    try:
        diagnostic['explorer_count'] = int(getattr(outlook.Explorers, 'Count', 0) or 0)
    except Exception:
        pass

    try:
        diagnostic['inspector_count'] = int(getattr(outlook.Inspectors, 'Count', 0) or 0)
    except Exception:
        pass

    try:
        diagnostic['mail_items_found'] = len(_collect_outlook_mail_items(outlook, max_items=10))
    except Exception:
        pass

    try:
        explorer = outlook.ActiveExplorer()
        if explorer is not None:
            diagnostic['selection_count'] = int(getattr(explorer.Selection, 'Count', 0) or 0)
    except Exception:
        pass

    return diagnostic


def _is_outlook_process_running() -> bool:
    import subprocess

    try:
        completed = subprocess.run(
            ['tasklist', '/FI', 'IMAGENAME eq OUTLOOK.EXE'],
            capture_output=True,
            text=True,
            timeout=5,
            check=False,
        )
        return 'OUTLOOK.EXE' in (completed.stdout or '').upper()
    except Exception:
        return False


def _build_failure_message(diagnostic: Dict[str, Any]) -> str:
    if diagnostic.get('timed_out'):
        if not _is_outlook_process_running():
            return 'Outlook is not running. Open Outlook on this computer, then try again.'
        return (
            'Outlook did not respond in time. Click the email in Outlook, then try again. '
            'If Outlook shows a security prompt, click Allow.'
        )

    if diagnostic.get('worker_error'):
        return (
            'Could not read the email from Outlook. Make sure Outlook is open, the email is selected, '
            'and the local Python service is running.'
        )

    if not diagnostic.get('outlook_running'):
        return 'Outlook is not running. Open Outlook on this computer, then try again.'

    if diagnostic.get('mail_items_found', 0) <= 0:
        if diagnostic.get('explorer_count', 0) <= 0 and diagnostic.get('inspector_count', 0) <= 0:
            return (
                'Outlook is open but no mail window was found. Open your Inbox in Outlook, '
                'click the email you want to upload, then try again.'
            )

        if diagnostic.get('selection_count', 0) <= 0:
            return (
                'No email is selected in Outlook. Click the email in Outlook first, '
                'then use import selected from Outlook or drag it again.'
            )

        return (
            'Outlook is open but the selected item could not be exported. '
            'Click the email in Outlook, then try again. If Outlook shows a security prompt, click Allow.'
        )

    return 'No Outlook email could be exported.'


def _read_outlook_selected_mail_files_impl(max_items: int = 10) -> Tuple[List[Dict[str, Any]], Dict[str, Any]]:
    import pythoncom

    pythoncom.CoInitialize()
    try:
        outlook = _get_outlook_application()
        diagnostic = _outlook_drag_diagnostic(outlook)
        if outlook is None:
            return [], diagnostic

        mail_items = _collect_outlook_mail_items(outlook, max_items=max_items)
        diagnostic['mail_items_found'] = len(mail_items)

        files: List[Dict[str, Any]] = []
        for mail_item in mail_items:
            encoded = _read_mail_item_bytes(mail_item)
            if encoded is None:
                continue
            encoded['index'] = len(files)
            files.append(encoded)

        diagnostic['exported_files'] = len(files)
        return files, diagnostic
    finally:
        pythoncom.CoUninitialize()


def read_outlook_selected_mail_files(max_items: int = 10, timeout_seconds: int = 20) -> Tuple[List[Dict[str, Any]], Dict[str, Any]]:
    if not is_supported():
        return [], {'outlook_running': False}

    if not _is_outlook_process_running():
        return [], {
            'outlook_running': False,
            'outlook_process': False,
        }

    import json
    import subprocess

    worker = (
        'import json; '
        'from services.outlook_drag_service import _read_outlook_selected_mail_files_impl; '
        f'files, diagnostic = _read_outlook_selected_mail_files_impl({int(max_items)}); '
        'print(json.dumps({"files": files, "diagnostic": diagnostic}))'
    )

    try:
        completed = subprocess.run(
            [sys.executable, '-c', worker],
            cwd=os.path.dirname(os.path.dirname(__file__)),
            capture_output=True,
            text=True,
            timeout=timeout_seconds,
            check=False,
        )
    except subprocess.TimeoutExpired:
        return [], {
            'outlook_running': None,
            'timed_out': True,
        }

    if completed.returncode != 0 or not completed.stdout.strip():
        return [], {
            'outlook_running': None,
            'worker_error': (completed.stderr or completed.stdout or '').strip()[:500],
        }

    try:
        payload = json.loads(completed.stdout)
    except json.JSONDecodeError:
        return [], {'outlook_running': None, 'worker_error': 'invalid_worker_response'}

    if not isinstance(payload, dict):
        return [], {'outlook_running': None}

    files = payload.get('files') if isinstance(payload.get('files'), list) else []
    diagnostic = payload.get('diagnostic') if isinstance(payload.get('diagnostic'), dict) else {}
    return files, diagnostic


def _decode_filename(raw: bytes) -> str:
    if not raw:
        return ''
    trimmed = raw.split(b'\x00', 1)[0]
    for encoding in ('utf-16-le', 'utf-8', 'latin-1'):
        try:
            return trimmed.decode(encoding).strip()
        except UnicodeDecodeError:
            continue
    return trimmed.decode('latin-1', errors='ignore').strip()


def _read_stream_bytes(stream, size_hint: int = 0) -> bytes:
    chunks: List[bytes] = []
    remaining = max(size_hint, 0)
    while True:
        to_read = 65536 if remaining <= 0 else min(65536, remaining)
        try:
            data = stream.Read(to_read)
        except Exception:
            break
        if not data:
            break
        if isinstance(data, str):
            data = data.encode('latin-1', errors='ignore')
        chunks.append(bytes(data))
        if remaining > 0:
            remaining -= len(data)
            if remaining <= 0:
                break
    return b''.join(chunks)


def _read_file_contents(data_object, fmt_contents: int, index: int, size_hint: int = 0) -> bytes:
    import pythoncom

    attempts = (
        pythoncom.TYMED_ISTREAM,
        pythoncom.TYMED_HGLOBAL,
        pythoncom.TYMED_ISTORAGE,
    )

    for tymed in attempts:
        formatetc = (fmt_contents, None, pythoncom.DVASPECT_CONTENT, index, tymed)
        try:
            medium = data_object.GetData(formatetc)
        except Exception:
            continue

        try:
            if tymed == pythoncom.TYMED_ISTREAM and medium.data is not None:
                return _read_stream_bytes(medium.data, size_hint)
            if tymed == pythoncom.TYMED_HGLOBAL and medium.data is not None:
                payload = medium.data
                return bytes(payload) if not isinstance(payload, bytes) else payload
        except Exception:
            continue

    return b''


def read_outlook_clipboard_virtual_files() -> List[Dict[str, Any]]:
    if not is_supported():
        return []

    import pythoncom
    import win32clipboard

    pythoncom.CoInitialize()
    try:
        try:
            data_object = pythoncom.OleGetClipboard()
        except Exception:
            return []

        fmt_fgd = win32clipboard.RegisterClipboardFormat('FileGroupDescriptorW')
        fmt_contents = win32clipboard.RegisterClipboardFormat('FileContents')

        formatetc = (fmt_fgd, None, pythoncom.DVASPECT_CONTENT, -1, pythoncom.TYMED_HGLOBAL)
        try:
            medium = data_object.GetData(formatetc)
        except Exception:
            return []

        blob = medium.data
        if not isinstance(blob, bytes):
            blob = bytes(blob)
        if len(blob) < 4:
            return []

        count = struct.unpack('<I', blob[:4])[0]
        if count <= 0:
            return []

        files: List[Dict[str, Any]] = []
        offset = 4

        for index in range(count):
            if offset + FILEDESCRIPTORW_SIZE > len(blob):
                break

            unpacked = struct.unpack_from(FILEDESCRIPTORW_FMT, blob, offset)
            offset += FILEDESCRIPTORW_SIZE

            filename = _decode_filename(unpacked[10]) or f'email_{index + 1}.msg'
            size_low = unpacked[8]
            size_high = unpacked[9]
            size_hint = (size_high << 32) + size_low

            content = _read_file_contents(data_object, fmt_contents, index, size_hint)
            if not content:
                continue

            files.append(_encode_file_result(filename, content, 'clipboard', index))

        return files
    finally:
        pythoncom.CoUninitialize()


def read_outlook_virtual_files(max_items: int = 10) -> Tuple[List[Dict[str, Any]], Dict[str, Any]]:
    selected, diagnostic = read_outlook_selected_mail_files(max_items=max_items)
    if selected:
        return selected, diagnostic

    clipboard_files = read_outlook_clipboard_virtual_files()
    return clipboard_files, diagnostic
