self.addEventListener('push', (event) => {
  if (!event.data) return;

  let payload;
  try {
    payload = event.data.json();
  } catch {
    return;
  }
  if (typeof payload.title !== 'string' || typeof payload.body !== 'string') return;

  event.waitUntil(self.registration.showNotification(payload.title, {
    body: payload.body,
    icon: '/icons/mars-192.png',
    badge: '/icons/mars.svg',
    data: { url: '/' },
  }));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
    const appClient = clients.find((client) => new URL(client.url).origin === self.location.origin);
    if (appClient) return appClient.focus();
    return self.clients.openWindow('/');
  }));
});
