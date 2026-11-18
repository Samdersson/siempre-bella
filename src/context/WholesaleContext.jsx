
import { createContext, useContext, useEffect, useState } from "react";

const WholesaleContext = createContext(null);

const STORAGE_KEY = "siempre_bella_carrito_mayorista";

// Validar y normalizar productos
function normalizarProducto(item) {
  if (!item || typeof item !== "object") return null;

  const id = Number(item.id);
  const precio = Number(item.price_unitario);
  const cantidad = Number(item.cantidad);

  if (
    !Number.isSafeInteger(id) ||
    id <= 0 ||
    !Number.isFinite(precio) ||
    precio <= 0 ||
    !Number.isSafeInteger(cantidad) ||
    cantidad <= 0
  ) {
    return null;
  }

  return {
    id,
    name: String(item.name || "Producto"),
    slug: String(item.slug || ""),
    image: item.image || "",
    brand: item.brand || "",
    price_unitario: precio,
    cantidad,
  };
}

// Recuperar carrito desde localStorage
function cargarCarritoInicial() {
  try {
    const guardados = localStorage.getItem(STORAGE_KEY);

    if (!guardados) return [];

    const productos = JSON.parse(guardados);

    if (!Array.isArray(productos)) return [];

    return productos
      .map(normalizarProducto)
      .filter(Boolean);
  } catch (error) {
    console.error("Error recuperando carrito mayorista:", error);
    return [];
  }
}

export function WholesaleProvider({ children }) {
  const [productos, setProductos] = useState(cargarCarritoInicial);

  // Guardar carrito mayorista
  useEffect(() => {
    try {
      localStorage.setItem(
        STORAGE_KEY,
        JSON.stringify(productos)
      );
    } catch (error) {
      console.error("Error guardando carrito mayorista:", error);
    }
  }, [productos]);

  // Agregar producto
  const agregarProducto = (producto) => {
    if (!producto || typeof producto !== "object") {
      return false;
    }

    const productoValidado = normalizarProducto({
      id: producto.id,
      name: producto.nombre,
      slug: producto.slug,
      image: producto.imagen || "",
      brand: producto.marca || "",
      price_unitario: producto.precio_mayorista,
      cantidad: 1,
    });

    if (
      !productoValidado ||
      producto.disponibilidad !== "disponible"
    ) {
      return false;
    }

    setProductos((actuales) => {
      const existente = actuales.find(
        (item) => Number(item.id) === productoValidado.id
      );

      if (existente) {
        return actuales.map((item) =>
          Number(item.id) === productoValidado.id
            ? {
                ...productoValidado,
                cantidad: item.cantidad + 1,
              }
            : item
        );
      }

      return [...actuales, productoValidado];
    });

    return true;
  };

  // Actualizar cantidad
  const actualizarCantidad = (id, cantidad) => {
    const idValidado = Number(id);
    const nuevaCantidad = Number(cantidad);

    if (
      !Number.isSafeInteger(idValidado) ||
      idValidado <= 0 ||
      !Number.isSafeInteger(nuevaCantidad) ||
      nuevaCantidad < 1
    ) {
      return;
    }

    setProductos((actuales) =>
      actuales.map((item) =>
        Number(item.id) === idValidado
          ? { ...item, cantidad: nuevaCantidad }
          : item
      )
    );
  };

  // Actualizar precio con el valor vigente de la API
  const actualizarPrecioProducto = (id, precio) => {
    const idValidado = Number(id);
    const nuevoPrecio = Number(precio);

    if (
      !Number.isSafeInteger(idValidado) ||
      idValidado <= 0 ||
      !Number.isFinite(nuevoPrecio) ||
      nuevoPrecio <= 0
    ) {
      return;
    }

    setProductos((actuales) =>
      actuales.map((item) =>
        Number(item.id) === idValidado
          ? { ...item, price_unitario: nuevoPrecio }
          : item
      )
    );
  };

  // Eliminar producto
  const eliminarProducto = (id) => {
    const idValidado = Number(id);

    if (!Number.isSafeInteger(idValidado) || idValidado <= 0) {
      return;
    }

    setProductos((actuales) =>
      actuales.filter(
        (item) => Number(item.id) !== idValidado
      )
    );
  };

  // Vaciar carrito
  const vaciarCarrito = () => {
    setProductos([]);
  };

  // Subtotal
  const subtotal = productos.reduce(
    (total, producto) =>
      total + producto.price_unitario * producto.cantidad,
    0
  );

  // Cantidad total de unidades
  const totalUnidades = productos.reduce(
    (total, producto) => total + producto.cantidad,
    0
  );

  return (
    <WholesaleContext.Provider
      value={{
        productos,
        agregarProducto,
        actualizarCantidad,
        actualizarPrecioProducto,
        eliminarProducto,
        vaciarCarrito,
        subtotal,
        totalUnidades,
      }}
    >
      {children}
    </WholesaleContext.Provider>
  );
}

export function useWholesaleCart() {
  const context = useContext(WholesaleContext);

  if (!context) {
    throw new Error(
      "useWholesaleCart debe utilizarse dentro de WholesaleProvider"
    );
  }

  return context;
}
