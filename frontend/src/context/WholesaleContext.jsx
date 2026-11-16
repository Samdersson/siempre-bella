
import { createContext, useContext, useEffect, useState } from "react";

const WholesaleContext = createContext(null);

const STORAGE_KEY = "siempre_bella_carrito_mayorista";

export function WholesaleProvider({ children }) {
  const [productos, setProductos] = useState(() => {
    try {
      const guardados = localStorage.getItem(STORAGE_KEY);
      return guardados ? JSON.parse(guardados) : [];
    } catch {
      return [];
    }
  });

  // Guardar carrito mayorista
  useEffect(() => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(productos));
  }, [productos]);

  // Agregar producto
  const agregarProducto = (producto) => {
    const precio = Number(producto.precio_mayorista);

    if (
      !producto.id ||
      precio <= 0 ||
      producto.disponibilidad !== "disponible"
    ) {
      return;
    }

    setProductos((actuales) => {
      const existente = actuales.find(
        (item) => Number(item.id) === Number(producto.id)
      );

      if (existente) {
        return actuales.map((item) =>
          Number(item.id) === Number(producto.id)
            ? { ...item, cantidad: item.cantidad + 1 }
            : item
        );
      }

      return [
        ...actuales,
        {
          id: producto.id,
          name: producto.nombre,
          slug: producto.slug || "",
          image: producto.imagen || "",
          brand: producto.marca || "",
          price_unitario: precio,
          cantidad: 1,
        },
      ];
    });
  };

  // Actualizar cantidad
  const actualizarCantidad = (id, cantidad) => {
    const nuevaCantidad = Math.max(
      1,
      Math.floor(Number(cantidad) || 1)
    );

    setProductos((actuales) =>
      actuales.map((item) =>
        Number(item.id) === Number(id)
          ? { ...item, cantidad: nuevaCantidad }
          : item
      )
    );
  };

  // Actualizar precio con el valor vigente de la API
  const actualizarPrecioProducto = (id, precio) => {
    const nuevoPrecio = Number(precio);

    if (!Number.isFinite(nuevoPrecio) || nuevoPrecio <= 0) {
      return;
    }

    setProductos((actuales) =>
      actuales.map((item) =>
        Number(item.id) === Number(id)
          ? { ...item, price_unitario: nuevoPrecio }
          : item
      )
    );
  };

  // Eliminar producto
  const eliminarProducto = (id) => {
    setProductos((actuales) =>
      actuales.filter((item) => Number(item.id) !== Number(id))
    );
  };

  // Vaciar carrito
  const vaciarCarrito = () => {
    setProductos([]);
  };

  // Subtotal
  const subtotal = productos.reduce(
    (total, producto) =>
      total +
      Number(producto.price_unitario) * Number(producto.cantidad),
    0
  );

  // Cantidad total de unidades
  const totalUnidades = productos.reduce(
    (total, producto) =>
      total + Number(producto.cantidad),
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
