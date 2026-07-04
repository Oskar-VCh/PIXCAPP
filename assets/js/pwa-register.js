/* ============================================
   PIXCAPP - Registro del Service Worker
   Incluye este archivo en TODAS tus páginas HTML
   (index.html, login.html, registro-*.html)
   con: <script src="assets/js/pwa-register.js"></script>
   ============================================ */

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        // Confirmado: el proyecto corre en http://localhost/PIXCAPP/
        navigator.serviceWorker.register('/PIXCAPP/service-worker.js')
            .then((reg) => {
                console.log('[PWA] Service Worker registrado:', reg.scope);

                // Detecta cuando hay una nueva versión del SW disponible
                reg.addEventListener('updatefound', () => {
                    const newWorker = reg.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            console.log('[PWA] Nueva versión disponible. Recarga la página para actualizar.');
                            // Opcional: aquí podrías mostrar un banner "Hay una actualización disponible"
                        }
                    });
                });
            })
            .catch((err) => {
                console.error('[PWA] Error al registrar Service Worker:', err);
            });
    });
}

// Aviso visual simple de estado de conexión (opcional pero útil para una app de campo)
window.addEventListener('online', () => console.log('[PWA] Conexión recuperada, sincronizando...'));
window.addEventListener('offline', () => console.log('[PWA] Sin conexión. Trabajando en modo offline.'));