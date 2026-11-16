
const API_URL = "/api";

export async function obtenerSecciones() {
    try {
        const respuesta = await fetch(
            `${API_URL}/secciones/listar.php`
        );

        if (!respuesta.ok) {
            throw new Error("Error al consultar la API");
        }

        const resultado = await respuesta.json();

        if (!resultado.success) {
            throw new Error("No se pudieron obtener las secciones");
        }

        return resultado.data;

    } catch (error) {
        console.error("Error en obtenerSecciones:", error);
        throw error;
    }
}



export async function obtenerProductos() {
    try {
        const respuesta = await fetch(`${API_URL}/productos/listar.php`);

        if (!respuesta.ok) {
            throw new Error("Error al consultar los productos");
        }

        const resultado = await respuesta.json();

        if (!resultado.success) {
            throw new Error("No se pudieron obtener los productos");
        }

        return resultado.data;

    } catch (error) {
        console.error("Error en obtenerProductos:", error);
        throw error;
    }
}



export async function obtenerPromociones() {
    try {
        const respuesta = await fetch(
            `${API_URL}/promociones/listar.php`
        );

        if (!respuesta.ok) {
            throw new Error("Error al consultar las promociones");
        }

        const resultado = await respuesta.json();

        if (!resultado.success) {
            throw new Error("No se pudieron obtener las promociones");
        }

        return resultado.data;

    } catch (error) {
        console.error("Error en obtenerPromociones:", error);
        throw error;
    }
}
