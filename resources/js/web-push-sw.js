// Livechat Web Push service worker: toont bureaubladmeldingen en opent het
// bijbehorende gesprek in het CMS bij een klik.

self.addEventListener('push', function (event) {
    if (!event.data) {
        return;
    }

    var payload = {};
    try {
        payload = event.data.json();
    } catch (e) {
        payload = { title: 'Nieuw chatbericht', body: event.data.text() };
    }

    var title = payload.title || 'Livechat';
    var options = {
        body: payload.body || '',
        tag: payload.tag || undefined,
        renotify: !!payload.tag,
        data: { url: payload.url || '/' },
        icon: '/favicon.ico',
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var url = (event.notification.data && event.notification.data.url) || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windowClients) {
            for (var i = 0; i < windowClients.length; i++) {
                var client = windowClients[i];
                if (client.url === url && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        })
    );
});
