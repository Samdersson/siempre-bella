
import {
  createContext,
  useContext,
  useEffect,
  useState,
} from "react";

const CartContext = createContext(null);

const STORAGE_KEY = "siempre_bella_carrito";

// Validar y normalizar un producto recuperado del almacenamiento
function normalizarProducto(item) {
  if (!item || typeof item !== "object") {
    return null;
  }

  const id = Number(item.id);
  const precioMinorista = Number(item.precio_minorista);
  const precioPromocional = Number(item.precio_promocional);
  const cantidad = Math.floor(Number(item.cantidad));

  if (
    !Number.isSafeInteger(id) ||
    id <= 0 ||
    !Number.isFinite(precioMinorista) ||
    precioMinorista <= 0 ||
    !Number.isSafeInteger(cantidad) ||
    cantidad < 1
  ) {
    return null;
  }

  const tienePromocion =
    item.precio_promocional !== null &&
    item.precio_promocional !== undefined &&
    Number.isFinite(precioPromocional) &&
    precioPromocional > 0 &&
    precioPromocional < precioMinorista;

  return {
    id,
    nombre: String(item.nombre || "Producto"),
    slug: String(item.slug || ""),
    imagen: item.imagen || null,
    marca: item.marca || null,
    precio_minorista: precioMinorista,
    precio_promocional: tienePromocion
      ? precioPromocional
      : null,
    precio_unitario: tienePromocion
      ? precioPromocional
      : precioMinorista,
    cantidad,
  };
}

// Recuperar carrito
function cargarCarritoInicial() {
  try {
    const guardado = localStorage.getItem(STORAGE_KEY);

    if (!guardado) return [];

    const productos = JSON.parse(guardado);

    if (!Array.isArray(productos)) {
      return [];
    }

    return productos
      .map(normalizarProducto)
      .filter(Boolean);

  } catch (error) {
    console.error(
      "No fue posible recuperar el carrito:",
      error
    );

    return [];
  }
}

export function CartProvider({ children }) {
  const [items, setItems] = useState(cargarCarritoInicial);

  // Guardar automáticamente los cambios
  useEffect(() => {
    try {
      localStorage.setItem(
        STORAGE_KEY,
        JSON.stringify(items)
      );
    } catch (error) {
      console.error(
        "No fue posible guardar el carrito:",
        error
      );
    }
  }, [items]);

  // Agregar producto
  const agregarProducto = (producto, cantidad = 1) => {
    const id = Number(producto?.id);

    if (
      !Number.isSafeInteger(id) ||
      id <= 0 ||
      producto.disponibilidad === "agotado"
    ) {
      return false;
    }

    const productoValidado = normalizarProducto({
      ...producto,
      cantidad: 1,
    });

    if (!productoValidado) {
      return false;
    }

    const cantidadSolicitada = Math.floor(Number(cantidad));

    const cantidadValida =
      Number.isSafeInteger(cantidadSolicitada) &&
      cantidadSolicitada > 0
        ? cantidadSolicitada
        : 1;

    setItems((actuales) => {
      const existente = actuales.find(
        (item) => Number(item.id) === id
      );

      if (existente) {
        return actuales.map((item) =>
          Number(item.id) === id
            ? {
                ...productoValidado,
                cantidad: item.cantidad + cantidadValida,
              }
            : item
        );
      }

      return [
        ...actuales,
        {
          ...productoValidado,
          cantidad: cantidadValida,
        },
      ];
    });

    return true;
  };

  // Cambiar cantidad
  const actualizarCantidad = (id, cantidad) => {
    const productoId = Number(id);
    const nuevaCantidad = Math.floor(Number(cantidad));

    if (
      !Number.isSafeInteger(productoId) ||
      productoId <= 0 ||
      !Number.isSafeInteger(nuevaCantidad) ||
      nuevaCantidad < 1
    ) {
      return;
    }

    setItems((actuales) =>
      actuales.map((item) =>
        Number(item.id) === productoId
          ? {
              ...item,
              cantidad: nuevaCantidad,
            }
          : item
      )
    );
  };

  // Eliminar producto
  const eliminarProducto = (id) => {
    const productoId = Number(id);

    setItems((actuales) =>
      actuales.filter(
        (item) => Number(item.id) !== productoId
      )
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

  // Total de compra
  const total = items.reduce(
    (suma, item) =>
      suma + item.precio_unitario * item.cantidad,
    0
  );

  const valor = {
    items,
    totalUnidades,
    total,
    agregarProducto,
    actualizarCantidad,
    eliminarProducto,
    vaciarCarrito,
  };

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
