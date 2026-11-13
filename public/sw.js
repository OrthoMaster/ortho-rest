/*
 * Service worker của ứng dụng quán (PWA): nhận thông báo đẩy và mở đúng trang khi bấm.
 * Không lưu trang để dùng offline: dữ liệu quán luôn lấy mới từ server.
 */
self.addEventListener('install', function () {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', function (event) {
    var data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch (error) {
        data = { title: event.data.text() };
    }

    event.waitUntil(self.registration.showNotification(data.title || '', {
        body: data.body || '',
        tag: data.tag,
        // Cùng tag (cùng bàn, cùng khu) thì thay thông báo cũ nhưng vẫn kêu lại.
        renotify: Boolean(data.tag),
        icon: data.icon,
        data: { url: data.url || self.registration.scope },
    }));
});

self.addEventListener('notificationclick', function (event) {
    var url = (event.notification.data && event.notification.data.url) || self.registration.scope;

    event.notification.close();
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windows) {
        var open = windows.find(function (client) {
            return client.url.indexOf(self.registration.scope) === 0 && 'focus' in client;
        });

        if (open) {
            return open.focus().then(function (client) {
                return client.navigate ? client.navigate(url) : client;
            });
        }

        return self.clients.openWindow(url);
    }));
});
