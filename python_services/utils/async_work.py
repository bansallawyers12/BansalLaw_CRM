"""
Helpers to run CPU-bound / blocking sync work off the asyncio event loop.
"""

from __future__ import annotations

import asyncio
import functools
from concurrent.futures import ThreadPoolExecutor
from typing import Any, Callable, Optional, TypeVar

from config import WORKER_THREADS

T = TypeVar('T')

_executor: Optional[ThreadPoolExecutor] = None


def get_executor() -> ThreadPoolExecutor:
    global _executor
    if _executor is None:
        _executor = ThreadPoolExecutor(
            max_workers=max(2, int(WORKER_THREADS)),
            thread_name_prefix='py-svc-cpu',
        )
    return _executor


async def run_sync(func: Callable[..., T], *args: Any, **kwargs: Any) -> T:
    """
    Execute a synchronous callable in the shared thread pool.

    Keeps FastAPI's event loop free while PDF/email CPU work runs.
    """
    loop = asyncio.get_running_loop()
    if kwargs:
        bound = functools.partial(func, *args, **kwargs)
        return await loop.run_in_executor(get_executor(), bound)
    return await loop.run_in_executor(get_executor(), func, *args)
