"""Laravel-aligned datetime formatting for email display (dd/mm/yyyy h:i a)."""

from __future__ import annotations

from datetime import datetime
from typing import Any, Optional

from dateutil import tz as dateutil_tz

# Matches config/app.php default used by Laravel CRM.
DEFAULT_TIMEZONE = 'Australia/Melbourne'


def resolve_timezone(tz_name: Optional[str]):
    """Resolve an IANA timezone name, falling back to the CRM default."""
    name = (tz_name or '').strip() or DEFAULT_TIMEZONE
    zone = dateutil_tz.gettz(name)
    if zone is None:
        zone = dateutil_tz.gettz(DEFAULT_TIMEZONE)
    return zone


def parse_datetime_value(value: Any) -> Optional[datetime]:
    """Parse ISO or common email datetime strings into a timezone-aware datetime."""
    if value is None or value == '':
        return None

    if isinstance(value, datetime):
        dt = value
    elif isinstance(value, str):
        raw = value.strip()
        if not raw:
            return None
        try:
            dt = datetime.fromisoformat(raw.replace('Z', '+00:00'))
        except ValueError:
            for fmt in ('%d/%m/%Y %I:%M %p', '%d/%m/%Y %H:%M', '%Y-%m-%d %H:%M:%S'):
                try:
                    dt = datetime.strptime(raw, fmt)
                    break
                except ValueError:
                    dt = None
            if dt is None:
                return None
    else:
        return None

    if dt.tzinfo is None:
        # Naive values are CRM local wall time (Melbourne), never silent UTC.
        dt = dt.replace(tzinfo=resolve_timezone(DEFAULT_TIMEZONE))
    return dt


def format_laravel_datetime(value: Any, tz_name: Optional[str] = None) -> str:
    """
    Format a datetime like Laravel's d/m/Y h:i a (e.g. 25/06/2026 02:30 pm).
    Converts from offset-aware or Melbourne-local source into the requested CRM timezone.
    """
    dt = parse_datetime_value(value)
    if dt is None:
        return str(value) if value is not None else ''

    local_dt = dt.astimezone(resolve_timezone(tz_name))
    return local_dt.strftime('%d/%m/%Y %I:%M ') + local_dt.strftime('%p').lower()
