/**
 * API-Kommunikationsschicht für das reaktive Dashboard.
 *
 * Erstellt via Factory-Funktion für Testbarkeit (Property-Tests injizieren Mock-fetch).
 * Der Default-Export ist ein Singleton, das window.tmgmtData liest.
 */

/**
 * @param {{ nonce: string, apiUrl: string }} config
 * @param {typeof fetch} fetchFn
 */
export function createApiService(config, fetchFn = window.fetch.bind(window)) {
    /** @type {Map<string, Promise<any>>} */
    const pendingRequests = new Map()

    /**
     * Baut den vollständigen URL aus Basis-URL und Pfad.
     * @param {string} path
     * @returns {string}
     */
    function buildUrl(path) {
        const base = config.apiUrl.endsWith('/') ? config.apiUrl : config.apiUrl + '/'
        const normalizedPath = path.startsWith('/') ? path.slice(1) : path
        return base + normalizedPath
    }

    /**
     * Gemeinsame Fetch-Logik mit Nonce-Header und strukturierter Fehlerbehandlung.
     * @param {string} url
     * @param {RequestInit} options
     * @returns {Promise<any>}
     */
    async function request(url, options) {
        const headers = {
            'Content-Type': 'application/json',
            'X-WP-Nonce': config.nonce,
            ...(options.headers || {}),
        }

        let response
        try {
            response = await fetchFn(url, { ...options, headers })
        } catch (networkError) {
            return Promise.reject({
                status: 0,
                message: networkError instanceof Error ? networkError.message : String(networkError),
                data: null,
            })
        }

        if (!response.ok) {
            let body = null
            try {
                body = await response.json()
            } catch (_) {
                // Body nicht parsebar – ignorieren
            }
            return Promise.reject({
                status: response.status,
                message: (body && body.message != null) ? body.message : (response.statusText || ''),
                data: (body && body.data !== undefined) ? body.data : body,
            })
        }

        return response.json()
    }

    /**
     * GET-Anfrage. Dedupliziert gleichzeitige Aufrufe anhand der URL als Key.
     * @param {string} path
     * @returns {Promise<any>}
     */
    function get(path) {
        const url = buildUrl(path)
        const key = 'GET:' + url

        if (pendingRequests.has(key)) {
            return pendingRequests.get(key)
        }

        const promise = request(url, { method: 'GET' }).finally(() => {
            pendingRequests.delete(key)
        })

        pendingRequests.set(key, promise)
        return promise
    }

    /**
     * POST-Anfrage. Dedupliziert gleichzeitige Aufrufe anhand der URL als Key.
     * @param {string} path
     * @param {object} [body]
     * @returns {Promise<any>}
     */
    function post(path, body) {
        const url = buildUrl(path)
        const key = 'POST:' + url

        if (pendingRequests.has(key)) {
            return pendingRequests.get(key)
        }

        const promise = request(url, {
            method: 'POST',
            body: body !== undefined ? JSON.stringify(body) : undefined,
        }).finally(() => {
            pendingRequests.delete(key)
        })

        pendingRequests.set(key, promise)
        return promise
    }

    return { get, post }
}

// Singleton – liest zur Laufzeit aus window.tmgmtData
const apiService = createApiService(
    // Lazy-Proxy: config wird erst beim ersten Aufruf ausgelesen
    new Proxy({}, {
        get(_target, prop) {
            const data = (typeof window !== 'undefined' && window.tmgmtData) || {}
            return data[prop]
        },
    }),
    // Lazy fetch: immer aktuelles window.fetch verwenden (wichtig für Storybook/Tests)
    (...args) => window.fetch(...args)
)

export default apiService
