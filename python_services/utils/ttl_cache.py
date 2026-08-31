"""
In-process TTL LRU cache for Python services.

Honours CACHE_TTL / CACHE_MAX_SIZE from config. Thread-safe for use from
thread-pool workers that handle CPU-bound PDF/email work.
"""

from __future__ import annotations

import hashlib
import threading
import time
from collections import OrderedDict
from typing import Any, Callable, Hashable, Optional, Tuple

from config import CACHE_MAX_SIZE, CACHE_TTL


class TtlLruCache:
    """Simple thread-safe LRU cache with per-entry TTL."""

    def __init__(self, max_size: int = CACHE_MAX_SIZE, ttl_seconds: int = CACHE_TTL):
        self.max_size = max(1, int(max_size))
        self.ttl_seconds = max(1, int(ttl_seconds))
        self._data: OrderedDict[Hashable, Tuple[float, Any]] = OrderedDict()
        self._lock = threading.RLock()
        self.hits = 0
        self.misses = 0

    def get(self, key: Hashable) -> Any:
        with self._lock:
            item = self._data.get(key)
            if item is None:
                self.misses += 1
                return None
            expires_at, value = item
            if expires_at < time.time():
                self._data.pop(key, None)
                self.misses += 1
                return None
            self._data.move_to_end(key)
            self.hits += 1
            return value

    def set(self, key: Hashable, value: Any) -> None:
        with self._lock:
            expires_at = time.time() + self.ttl_seconds
            if key in self._data:
                self._data.move_to_end(key)
            self._data[key] = (expires_at, value)
            while len(self._data) > self.max_size:
                self._data.popitem(last=False)

    def get_or_set(self, key: Hashable, factory: Callable[[], Any]) -> Any:
        cached = self.get(key)
        if cached is not None:
            return cached
        value = factory()
        # Do not cache failed/error-shaped results that look transient.
        if isinstance(value, dict) and value.get('success') is False:
            return value
        self.set(key, value)
        return value

    def clear(self) -> None:
        with self._lock:
            self._data.clear()
            self.hits = 0
            self.misses = 0

    def stats(self) -> dict:
        with self._lock:
            return {
                'size': len(self._data),
                'max_size': self.max_size,
                'ttl_seconds': self.ttl_seconds,
                'hits': self.hits,
                'misses': self.misses,
            }


def stable_hash(*parts: Any) -> str:
    """Build a short stable hash from arbitrary parts (for cache keys)."""
    payload = '|'.join('' if p is None else str(p) for p in parts)
    return hashlib.sha256(payload.encode('utf-8', errors='replace')).hexdigest()[:32]


# Shared process-wide caches
pdf_op_cache = TtlLruCache()
email_analysis_cache = TtlLruCache()
