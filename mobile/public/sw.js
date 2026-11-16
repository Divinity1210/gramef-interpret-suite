// Version tag — change this to force service worker update
const SW_VERSION = 'v2';

self.addEventListener('install', (event) => {
  console.log('[SW] Installing', SW_VERSION);
  event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
  console.log('[SW] Activating', SW_VERSION);
  event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
  console.log('[SW] Push received');

  let payload = {};
  try {
    payload = event.data ? event.data.json() : {};
  } catch (e) {
    console.log('[SW] Failed to parse push data, using text fallback');
    payload = { title: event.data ? event.data.text() : 'AtoZ Interpreting' };
  }

  const title = payload.title || 'AtoZ Interpreting';
  const body = payload.body || '';
  const notificationData = payload.data || {};
  const url = payload.url || notificationData.url || '/';

  // iOS-compatible notification options
  // IMPORTANT: iOS WebKit does NOT support: vibrate, sound, silent, requireInteraction
  // Including unsupported options can cause showNotification to silently fail on iOS
  const options = {
    body: body,
    data: { ...notificationData, url },
    icon: payload.icon || '/icon-192.png',
    badge: payload.badge || '/icon-96.png',
    tag: payload.tag || 'atoz-' + Date.now(),
  };

  console.log('[SW] Showing notification:', title, body);
  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  console.log('[SW] Notification clicked');
  event.notification.close();

  const url = event.notification?.data?.url || '/';

  event.waitUntil(
    self.clients
      .matchAll({ type: 'window', includeUncontrolled: true })
      .then((clientList) => {
        for (const client of clientList) {
          if (client.url.includes(url) && 'focus' in client) {
            return client.focus();
          }
        }

        for (const client of clientList) {
          if ('navigate' in client && 'focus' in client) {
            return client.navigate(url).then(() => client.focus());
          }
        }

        if (self.clients.openWindow) {
          return self.clients.openWindow(url);
        }

        return undefined;
      })
  );
});
