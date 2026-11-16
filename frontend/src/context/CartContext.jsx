
import {
  createContext,
  useContext,
  useEffect,
  useMemo,
  useState,
} from "react";

const CartContext = createContext(null);

const STORAGE_KEY = "siempre_bella_carrito";

function cargarCarritoInicial() {
  try {
    const guardado = localStorage.getItem(STORAGE_KEY);

    if (!guardado) return [];

    const productos = JSON.parse(guardado);

    return Array.isArray(productos) ? productos : [];
  } catch (error) {
    console.error("No fue posible recuperar el carrito:", error);
    return [];
  }
}

export function CartProvider({ children }) {
  const [items, setItems] = useState(cargarCarritoInicial);

  // Guardar automáticamente los cambios
  useEffect(() => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
  }, [items]);

  // Agregar un producto
  const agregarProducto = (producto, cantidad = 1) => {
    if (!producto?.id || producto.disponibilidad === "agotado") {
      return false;
    }

    const precioNormal = Number(producto.precio_minorista);

    const precioPromocional = Number(producto.precio_promocional);

    const tienePromocion =
      producto.precio_promocional !== null &&
      producto.precio_promocional !== undefined &&
      precioPromocional > 0 &&
      precioPromocional < precioNormal;

    const precioUnitario = tienePromocion
      ? precioPromocional
      : precioNormal;

    if (!Number.isFinite(precioUnitario) || precioUnitario <= 0) {
      return false;
    }

    const cantidadValida = Math.max(1, Math.floor(Number(cantidad) || 1));

    setItems((actuales) => {
      const existente = actuales.find(
        (item) => Number(item.id) === Number(producto.id)
      );

      if (existente) {
        return actuales.map((item) =>
          Number(item.id) === Number(producto.id)
            ? {
                ...item,
                cantidad: item.cantidad + cantidadValida,
              }
            : item
        );
      }

      return [
        ...actuales,
        {
          id: producto.id,
          nombre: producto.nombre,
          slug: producto.slug,
          imagen: producto.imagen || null,
          marca: producto.marca || null,
          precio_minorista: precioNormal,
          precio_promocional: tienePromocion
            ? precioPromocional
            : null,
          precio_unitario: precioUnitario,
          cantidad: cantidadValida,
        },
      ];
    });

    return true;
  };

  // Cambiar cantidad
  const actualizarCantidad = (id, cantidad) => {
    const nuevaCantidad = Math.floor(Number(cantidad));

    if (!Number.isFinite(nuevaCantidad) || nuevaCantidad < 1) {
      return;
    }

    setItems((actuales) =>
      actuales.map((item) =>
        Number(item.id) === Number(id)
          ? { ...item, cantidad: nuevaCantidad }
          : item
      )
    );
  };

  // Eliminar producto
  const eliminarProducto = (id) => {
    setItems((actuales) =>
      actuales.filter((item) => Number(item.id) !== Number(id))
    );
  };

  // Vaciar carrito
  const vaciarCarrito = () => {
    setItems([]);
  };

  // Total de unidades
  const totalUnidades = items.reduce(
    (total, item) => total + item.cantidad,
    0
  );

  // Valor total
  const total = items.reduce(
    (suma, item) => suma + item.precio_unitario * item.cantidad,
    0
  );

  const valor = useMemo(
    () => ({
      items,
      totalUnidades,
      total,
      agregarProducto,
      actualizarCantidad,
      eliminarProducto,
      vaciarCarrito,
    }),
    [items, totalUnidades, total]
  );

  return (
    <CartContext.Provider value={valor}>
      {children}
    </CartContext.Provider>
  );
}

export function useCart() {
  const contexto = useContext(CartContext);

  if (!contexto) {
    throw new Error(
      "useCart debe utilizarse dentro de un CartProvider."
    );
  }

  return contexto;
}
