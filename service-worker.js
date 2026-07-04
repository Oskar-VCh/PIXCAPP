/* ============================================
   PIXCAPP - SERVICE WORKER
   Estrategia: 
   - App Shell (HTML/CSS/JS/imágenes) -> Cache First (funciona 100% offline)
   - API PHP (/api/...) -> Network First con fallback a cache (datos frescos si hay internet)
   ============================================ */

// Sube este número cada vez que cambies archivos del "app shell"
// para forzar que los usuarios reciban la versión nueva.
const CACHE_VERSION = 'v1';
const APP_SHELL_CACHE = `pixcapp-shell-${CACHE_VERSION}`;
const API_CACHE = `pixcapp-api-${CACHE_VERSION}`;

// IMPORTANTE: debe coincidir EXACTAMENTE con el "scope" y "start_url" de tu manifest.json
// Confirmado: el proyecto corre en http://localhost/PIXCAPP/
const BASE = '/PIXCAPP/';

// Archivos que se descargan y guardan desde el primer momento
// (el "esqueleto" mínimo para que la app abra sin internet)
//
// OJO: tu carpeta "assets" vive dentro de "app/" (no en la raíz), por eso
// las rutas dicen "app/assets/..." y no "assets/...".
const APP_SHELL_FILES = [
    `${BASE}`,
    `${BASE}index.html`,
    `${BASE}login.html`,
    `${BASE}registro-agricultor.html`,
    `${BASE}app/assets/js/pwa-register.js`,
    `${BASE}app/assets/img/logo.png`,
    `${BASE}app/assets/img/hero-bg.jpeg`,

    // Páginas del módulo agricultor (app/agricultor/)
    `${BASE}app/agricultor/dashboard.html`,
    `${BASE}app/agricultor/ajustes.html`,
    `${BASE}app/agricultor/configuracion.html`,
    `${BASE}app/agricultor/cultivo.html`,
    `${BASE}app/agricultor/cultivos.html`,
    `${BASE}app/agricultor/guia.html`,
    `${BASE}app/agricultor/ingeniero.html`,
    `${BASE}app/agricultor/nueva-parcela.html`,
    `${BASE}app/agricultor/perfil.html`,
    `${BASE}app/agricultor/registro-fertilizacion.html`,
    `${BASE}app/agricultor/registro-plaga.html`,
    `${BASE}app/agricultor/registro-riego.html`,
    `${BASE}app/agricultor/reportes.html`,
    `${BASE}app/agricultor/tomar-foto.html`,

    // Agrega aquí tus .css externos si los separaste en archivos propios,
    // por ejemplo: `${BASE}app/assets/css/estilos.css`
];

/* ============================================
   INSTALL: se descarga y guarda el app shell
   ============================================ */
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(APP_SHELL_CACHE).then((cache) => {
            // addAll falla completo si UN solo archivo no existe,
            // así que los agregamos uno por uno para no romper la instalación
            // si falta algún ícono o imagen.
            return Promise.all(
                APP_SHELL_FILES.map((url) =>
                    cache.add(url).catch((err) => {
                        console.warn('[SW] No se pudo cachear:', url, err);
                    })
                )
            );
        })
    );
    self.skipWaiting(); // activa el nuevo SW sin esperar a cerrar pestañas
});

/* ============================================
   ACTIVATE: borra caches viejos de versiones anteriores
   ============================================ */
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames
                    .filter((name) => name !== APP_SHELL_CACHE && name !== API_CACHE)
                    .map((name) => caches.delete(name))
            );
        })
    );
    self.clients.claim();
});

/* ============================================
   FETCH: intercepta todas las peticiones
   ============================================ */
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Solo manejamos peticiones GET; POST/PUT (formularios, sync) pasan directo a la red.
    // Esto es clave porque tus registros (registro-agricultor.html, etc.) probablemente
    // envían datos por POST a /api/, y eso NO se debe cachear.
    if (request.method !== 'GET') {
        return; // deja pasar sin interceptar
    }

    // Estrategia para llamadas a tu API PHP: Network First
    // (intenta internet primero, si falla usa la última respuesta guardada)
    if (url.pathname.includes('/api/')) {
        event.respondWith(networkFirst(request));
        return;
    }

    // Estrategia para todo lo demás (HTML, CSS, imágenes, JS): Cache First
    // (carga instantánea y funciona sin internet)
    event.respondWith(cacheFirst(request));
});

/* ============================================
   ESTRATEGIAS
   ============================================ */

// Cache First: si está en cache, lo sirve de inmediato.
// Si no está, lo busca en la red y lo guarda para la próxima vez.
async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) return cached;

    try {
        const response = await fetch(request);
        // Solo guardamos respuestas válidas (evita cachear errores 404/500)
        if (response && response.status === 200) {
            const cache = await caches.open(APP_SHELL_CACHE);
            cache.put(request, response.clone());
        }
        return response;
    } catch (err) {
        // Sin internet y sin cache: si pidieron una página HTML, muestra el index como fallback
        if (request.headers.get('accept')?.includes('text/html')) {
            const fallback = await caches.match(`${BASE}index.html`);
            if (fallback) return fallback;
        }
        throw err;
    }
}

// Network First: intenta traer datos frescos de la API.
// Si no hay internet, regresa la última respuesta guardada (datos "viejos" pero disponibles).
async function networkFirst(request) {
    try {
        const response = await fetch(request);
        if (response && response.status === 200) {
            const cache = await caches.open(API_CACHE);
            cache.put(request, response.clone());
        }
        return response;
    } catch (err) {
        const cached = await caches.match(request);
        if (cached) return cached;
        // No hay red ni cache: devolvemos un error controlado en JSON
        return new Response(
            JSON.stringify({ error: true, offline: true, message: 'Sin conexión. Los datos se sincronizarán cuando vuelva el internet.' }),
            { headers: { 'Content-Type': 'application/json' }, status: 503 }
        );
    }
}