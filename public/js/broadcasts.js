(function () {
    const bannerSelector = '[data-broadcast-banner]';
    const titleSelector = '[data-broadcast-title]';
    const messageSelector = '[data-broadcast-message]';
    const metaSelector = '[data-broadcast-meta]';
    const metaTextSelector = '[data-broadcast-meta-text]';
    const markReadSelector = '[data-action="mark-read"]';
    const dismissSelector = '[data-action="dismiss"]';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const currentUserId = (() => {
        const meta = document.querySelector('meta[name="current-user-id"]');
        if (!meta) {
            return null;
        }

        const value = parseInt(meta.getAttribute('content') || '', 10);
        return Number.isFinite(value) ? value : null;
    })();

    const bannerEl = document.querySelector(bannerSelector);

    if (!bannerEl || !currentUserId) {
        return;
    }

    const titleEl = bannerEl.querySelector(titleSelector);
    const messageEl = bannerEl.querySelector(messageSelector);
    const metaEl = bannerEl.querySelector(metaSelector);
    const metaTextEl = bannerEl.querySelector(metaTextSelector);
    const markReadBtn = bannerEl.querySelector(markReadSelector);
    const dismissBtn = bannerEl.querySelectorAll(dismissSelector);

    const endpoints = {
        unread: '/notifications/broadcasts/unread',
        markRead: (id) => `/notifications/broadcasts/${id}/read`,
    };

    const state = {
        queue: [],
        active: null,
        pollingTimer: null,
        isPolling: false,
        dismissed: new Set(),
    };

    function formatTimestamp(timestamp) {
        if (!timestamp) {
            return '';
        }
        if (typeof window.formatDisplayDateTime === 'function') {
            return window.formatDisplayDateTime(timestamp);
        }
        const date = new Date(timestamp);
        if (Number.isNaN(date.getTime())) {
            return '';
        }
        return new Intl.DateTimeFormat(undefined, {
            hour: '2-digit',
            minute: '2-digit',
            day: '2-digit',
            month: 'short',
        }).format(date);
    }

    function setBannerVisible(isVisible) {
        bannerEl.classList.toggle('is-visible', isVisible);
    }

    function renderActive() {
        const broadcast = state.active;

        if (!broadcast) {
            titleEl && (titleEl.textContent = '');
            messageEl && (messageEl.textContent = '');
            metaTextEl && (metaTextEl.textContent = '');
            setBannerVisible(false);
            return;
        }

        if (titleEl) {
            titleEl.textContent = broadcast.title || '';
            titleEl.classList.toggle('has-title', Boolean(broadcast.title));
        }

        if (messageEl) {
            messageEl.innerHTML = broadcast.message || '';
        }

        if (metaTextEl) {
            const parts = [];

            if (broadcast.sender_name) {
                parts.push(broadcast.sender_name);
            }

            const formatted = formatTimestamp(broadcast.sent_at);
            if (formatted) {
                parts.push(formatted);
            }

            metaTextEl.textContent = parts.join(' • ');
        }

        setBannerVisible(true);
    }

    function showNextBroadcast() {
        if (state.queue.length === 0) {
            state.active = null;
            renderActive();
            return;
        }

        state.active = state.queue.shift();
        renderActive();
    }

    function enqueueBroadcasts(items) {
        let added = false;
        const existingIds = new Set(state.queue.map((item) => item.notification_id));
        if (state.active) {
            existingIds.add(state.active.notification_id);
        }

        items.forEach((item) => {
            if (!item || !item.notification_id || state.dismissed.has(item.notification_id)) {
                return;
            }

            if (!existingIds.has(item.notification_id)) {
                state.queue.push(item);
                existingIds.add(item.notification_id);
                added = true;
            }
        });

        if (added) {
            state.queue.sort((a, b) => new Date(a.sent_at) - new Date(b.sent_at));
        }

        if (!state.active) {
            showNextBroadcast();
        }
    }

    function fetchUnreadBroadcasts() {
        if (state.isPolling) {
            return;
        }

        state.isPolling = true;

        fetch(endpoints.unread, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
            },
            credentials: 'include',
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Failed to fetch broadcasts');
                }
                return response.json();
            })
            .then((payload) => {
                if (Array.isArray(payload?.data)) {
                    enqueueBroadcasts(payload.data);
                }
            })
            .catch((error) => {
                console.error('Broadcast polling error:', error);
            })
            .finally(() => {
                state.isPolling = false;
            });
    }

    function startPolling() {
        if (state.pollingTimer) {
            clearInterval(state.pollingTimer);
        }

        // Use longer polling interval as fallback (only if WebSocket fails)
        state.pollingTimer = setInterval(fetchUnreadBroadcasts, 60000);
    }
    
    function startRealtimeListener() {
        if (!window.Echo) {
            // Only show warning if Echo was expected but failed (not if intentionally disabled)
            if (!window.EchoDisabled) {
                console.warn('⚠️ Laravel Echo not available for broadcast notifications, using polling fallback');
            }
            startPolling();
            return;
        }
        
        if (!currentUserId) {
            console.warn('⚠️ Current user ID not found, cannot subscribe to broadcast notifications');
            startPolling();
            return;
        }
        
        console.log('✅ Subscribing to broadcast notifications for user:', currentUserId);
        
        window.Echo.private(`user.${currentUserId}`)
            .listen('.BroadcastNotificationCreated', (e) => {
                console.log('📢 Received broadcast notification:', e);
                
                // Convert the broadcast event data to the format expected by enqueueBroadcasts
                const notification = {
                    notification_id: null, // Will be fetched from server
                    batch_uuid: e.batch_uuid,
                    message: e.message,
                    title: e.title,
                    sender_id: e.sender_id,
                    sender_name: e.sender_name,
                    sent_at: e.sent_at
                };
                
                // Fetch the full notification details to get notification_id
                fetch(endpoints.unread, {
                    method: 'GET',
                    headers: {
                        Accept: 'application/json',
                    },
                    credentials: 'include',
                })
                .then((response) => response.json())
                .then((payload) => {
                    if (Array.isArray(payload?.data)) {
                        // Find the notification with matching batch_uuid
                        const fullNotification = payload.data.find(n => n.batch_uuid === e.batch_uuid);
                        if (fullNotification) {
                            enqueueBroadcasts([fullNotification]);
                        }
                    }
                })
                .catch((error) => {
                    console.error('Error fetching notification details:', error);
                });
            });
        
        // Monitor connection status
        window.Echo.connector.pusher.connection.bind('connected', () => {
            console.log('✅ Reverb connected for broadcast notifications');
            if (state.pollingTimer) {
                clearInterval(state.pollingTimer);
                state.pollingTimer = null;
            }
        });
        
        window.Echo.connector.pusher.connection.bind('disconnected', () => {
            console.warn('⚠️ Reverb disconnected, falling back to polling for broadcasts');
            startPolling();
        });
    }

    function markActiveAsRead() {
        const broadcast = state.active;
        if (!broadcast) {
            return;
        }

        fetch(endpoints.markRead(broadcast.notification_id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'include',
            body: JSON.stringify({}),
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Failed to mark broadcast as read');
                }
                return response.json();
            })
            .then(() => {
                showNextBroadcast();
            })
            .catch((error) => {
                console.error('Failed to mark broadcast as read:', error);
            });
    }

    function dismissActiveBroadcast() {
        if (!state.active) {
            return;
        }

        state.dismissed.add(state.active.notification_id);
        showNextBroadcast();
    }

    function setupEchoListeners() {
        const echo = window.Echo;
        if (!echo || !currentUserId) {
            return;
        }

        try {
            echo.channel('broadcasts').listen('.BroadcastNotificationCreated', (event) => {
                if (event?.scope === 'all') {
                    fetchUnreadBroadcasts();
                }
            });

            echo.private(`user.${currentUserId}`).listen('.BroadcastNotificationCreated', () => {
                fetchUnreadBroadcasts();
            });
        } catch (error) {
            console.warn('Unable to register broadcast channel listeners:', error);
        }
    }

    function handleVisibilityChange() {
        if (document.visibilityState === 'visible') {
            fetchUnreadBroadcasts();
        }
    }

    function init() {
        // Initial fetch to show existing broadcasts
        fetchUnreadBroadcasts();
        
        // Use real-time listener with polling fallback
        startRealtimeListener();
        
        // Setup Echo listeners for legacy code (if Echo is configured differently)
        if (window.Echo && typeof setupEchoListeners === 'function') {
            setupEchoListeners();
        }
        
        document.addEventListener('visibilitychange', handleVisibilityChange);

        if (markReadBtn) {
            markReadBtn.addEventListener('click', markActiveAsRead);
        }

        if (dismissBtn && dismissBtn.length > 0) {
            dismissBtn.forEach(btn => {
                btn.addEventListener('click', dismissActiveBroadcast);
            });
        }
    }

    init();
})();


