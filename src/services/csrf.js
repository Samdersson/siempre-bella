
const API_URL = "/api";

/**
 * Solicita un token CSRF asociado a la sesión administrativa.
 */
export async function obtenerTokenCSRF() {
    const respuesta = await fetch(`${API_URL}/auth/csrf.php`, {
        method: "GET",
        credentials: "include",
        cache: "no-store",
        headers: {
            Accept: "application/json"
        }
    });

    let resultado;

    try {
        resultado = await respuesta.json();
    } catch {
        throw new Error("El servidor devolvió una respuesta no válida al solicitar el token de seguridad.");
    }

    if (respuesta.status === 401) {
        throw new Error("Tu sesión ha expirado. Inicia sesión nuevamente.");
    }

    if (!respuesta.ok || !resultado.success || !resultado.token) {
        throw new Error(
            resultado.message || "No fue posible obtener el token de seguridad."
        );
    }

    return resultado.token;
}

/**
 * Construye las cabeceras para solicitudes protegidas.
 */
export async function obtenerHeadersCSRF(headersAdicionales = {}) {
    const token = await obtenerTokenCSRF();

    return {
        ...headersAdicionales,
        "X-CSRF-Token": token
    };
}
