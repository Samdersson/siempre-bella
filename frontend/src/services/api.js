
const API_URL = "https://abundant-gratitude-production.up.railway.app/api";

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


export async function obtenerCategorias() {
    try {
        const respuesta = await fetch(
            `${API_URL}/categorias/listar.php`
        );

        if (!respuesta.ok) {
            throw new Error("Error al consultar las categorías");
        }

        const resultado = await respuesta.json();

        if (!resultado.success) {
            throw new Error("No se pudieron obtener las categorías");
        }

        return resultado.data;

    } catch (error) {
        console.error("Error en obtenerCategorias:", error);
        throw error;
    }
}


export async function obtenerCatalogo(filtros = {}) {
    try {
        const parametros = new URLSearchParams();

        if (filtros.categoria) {
            parametros.set("categoria", filtros.categoria);
        }

        if (filtros.buscar) {
            parametros.set("buscar", filtros.buscar);
        }

        if (filtros.marca) {
            parametros.set("marca", filtros.marca);
        }

        if (filtros.orden) {
            parametros.set("orden", filtros.orden);
        }

        const respuesta = await fetch(
            `${API_URL}/productos/catalogo.php?${parametros.toString()}`
        );

        if (!respuesta.ok) {
            throw new Error("Error al consultar el catálogo");
        }

        const resultado = await respuesta.json();

        if (!resultado.success) {
            throw new Error("No se pudo obtener el catálogo");
        }

        return resultado.data;

    } catch (error) {
        console.error("Error en obtenerCatalogo:", error);
        throw error;
    }
}


export async function obtenerProductoPorSlug(slug) {
    try {
        const respuesta = await fetch(
            `${API_URL}/productos/detalle.php?slug=${encodeURIComponent(slug)}`
        );

        if (!respuesta.ok) {
            throw new Error("No fue posible consultar el producto");
        }

        const resultado = await respuesta.json();

        if (!resultado.success) {
            throw new Error("No se encontró el producto");
        }

        return resultado.data;

    } catch (error) {
        console.error("Error en obtenerProductoPorSlug:", error);
        throw error;
    }
}

