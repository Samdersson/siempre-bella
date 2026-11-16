
import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import {
  ShoppingBag,
  Trash2,
  Plus,
  Minus,
  MessageCircle,
  ArrowLeft,
  ShoppingCart,
} from "lucide-react";

import { useCart } from "../../context/CartContext";
import logo from "../../assets/logo siempre bella.webp";

import "../../styles/cart.css";

function Cart() {
  const {
    items,
    totalUnidades,
    total,
    actualizarCantidad,
    eliminarProducto,
    vaciarCarrito,
  } = useCart();

  const [whatsapp, setWhatsapp] = useState("");
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState("");

  // Obtener el WhatsApp configurado en el administrador
  useEffect(() => {
    async function cargarConfiguracion() {
      try {
        const respuesta = await fetch(
          "/api/configuracion/footer_publico.php"
        );

        const resultado = await respuesta.json();

        if (!respuesta.ok || !resultado.success) {
          throw new Error("No fue posible obtener el WhatsApp.");
        }

        setWhatsapp(resultado.data.tienda?.whatsapp || "");
      } catch (error) {
        console.error(error);
        setError("No fue posible cargar el contacto de la tienda.");
      } finally {
        setCargando(false);
      }
    }

    cargarConfiguracion();
  }, []);

  const formatoPrecio = (valor) =>
    Number(valor).toLocaleString("es-CO", {
      style: "currency",
      currency: "COP",
      maximumFractionDigits: 0,
    });

  // Construir pedido para WhatsApp
  const enviarPedido = () => {
    const numero = whatsapp.replace(/\D/g, "");

    if (!numero) {
      setError("La tienda todavía no tiene un WhatsApp configurado.");
      return;
    }

    if (items.length === 0) return;

    const detalle = items
      .map((item, indice) => {
        const subtotal = item.precio_unitario * item.cantidad;

        return (
          `${indice + 1}. ${item.nombre}\n` +
          `   Cantidad: ${item.cantidad}\n` +
          `   Precio: ${formatoPrecio(item.precio_unitario)}\n` +
          `   Subtotal: ${formatoPrecio(subtotal)}`
        );
      })
      .join("\n\n");

    const mensaje =
      `¡Hola, Siempre Bella! Quiero realizar el siguiente pedido:\n\n` +
      `${detalle}\n\n` +
      `TOTAL: ${formatoPrecio(total)}\n\n` +
      `¿Me pueden confirmar disponibilidad, envío y medios de pago?`;

    const url = `https://wa.me/${numero}?text=${encodeURIComponent(mensaje)}`;

    window.open(url, "_blank", "noopener,noreferrer");
  };

  return (
    <main className="cart-page">
      <div className="cart-container">

        <div className="cart-heading">
          <div>
            <span className="cart-eyebrow">SIEMPRE BELLA</span>
            <h1>Mi carrito</h1>
            <p>Revisa tus productos antes de realizar tu pedido.</p>
          </div>

          <div className="cart-heading-icon">
            <ShoppingBag size={27} />
          </div>
        </div>

        {items.length === 0 ? (
          <section className="cart-empty">
            <div className="cart-empty-icon">
              <ShoppingCart size={42} />
            </div>

            <h2>Tu carrito está vacío</h2>

            <p>
              Descubre nuestros productos y encuentra
              tus favoritos.
            </p>

            <Link to="/" className="cart-continue-button">
              <ArrowLeft size={17} />
              Explorar productos
            </Link>
          </section>
        ) : (
          <div className="cart-layout">

            {/* LISTA DE PRODUCTOS */}

            <section className="cart-products">

              <div className="cart-products-heading">
                <h2>Productos seleccionados</h2>

                <span>
                  {totalUnidades} unidades
                </span>
              </div>

              <div className="cart-products-list">

                {items.map((item) => (
                  <article className="cart-item" key={item.id}>

                    <div className="cart-item-image">
                      <img
                        src={
                          item.imagen
                            ? `/${item.imagen.replace(/^\/+/, "")}`
                            : logo
                        }
                        alt={item.nombre}
                        onError={(e) => {
                          e.currentTarget.onerror = null;
                          e.currentTarget.src = logo;
                        }}
                      />
                    </div>

                    <div className="cart-item-info">

                      <div className="cart-item-details">
                        <h3>{item.nombre}</h3>

                        {item.marca && (
                          <p>{item.marca}</p>
                        )}

                        <strong>
                          {formatoPrecio(item.precio_unitario)}
                        </strong>
                      </div>

                      <div className="cart-item-actions">

                        <div className="cart-quantity">

                          <button
                            type="button"
                            aria-label="Disminuir cantidad"
                            disabled={item.cantidad <= 1}
                            onClick={() =>
                              actualizarCantidad(
                                item.id,
                                item.cantidad - 1
                              )
                            }
                          >
                            <Minus size={15} />
                          </button>

                          <span>{item.cantidad}</span>

                          <button
                            type="button"
                            aria-label="Aumentar cantidad"
                            onClick={() =>
                              actualizarCantidad(
                                item.id,
                                item.cantidad + 1
                              )
                            }
                          >
                            <Plus size={15} />
                          </button>

                        </div>

                        <button
                          type="button"
                          className="cart-remove-button"
                          onClick={() => eliminarProducto(item.id)}
                          aria-label={`Eliminar ${item.nombre}`}
                        >
                          <Trash2 size={17} />
                          Eliminar
                        </button>

                      </div>

                    </div>

                    <div className="cart-item-subtotal">
                      {formatoPrecio(
                        item.precio_unitario * item.cantidad
                      )}
                    </div>

                  </article>
                ))}

              </div>

              <div className="cart-bottom-actions">

                <Link to="/" className="cart-back-link">
                  <ArrowLeft size={17} />
                  Seguir comprando
                </Link>

                <button
                  type="button"
                  className="cart-clear-button"
                  onClick={() => {
                    if (
                      window.confirm(
                        "¿Deseas eliminar todos los productos del carrito?"
                      )
                    ) {
                      vaciarCarrito();
                    }
                  }}
                >
                  <Trash2 size={16} />
                  Vaciar carrito
                </button>

              </div>

            </section>

            {/* RESUMEN DEL PEDIDO */}

            <aside className="cart-summary">

              <h2>Resumen del pedido</h2>

              <div className="cart-summary-row">
                <span>Productos</span>
                <strong>{totalUnidades}</strong>
              </div>

              <div className="cart-summary-row">
                <span>Subtotal</span>
                <strong>{formatoPrecio(total)}</strong>
              </div>

              <div className="cart-summary-row">
                <span>Envío</span>
                <span>Por confirmar</span>
              </div>

              <div className="cart-summary-total">
                <span>Total de productos</span>
                <strong>{formatoPrecio(total)}</strong>
              </div>

              <p className="cart-summary-note">
                El envío y los medios de pago se coordinarán
                directamente con la tienda por WhatsApp.
              </p>

              {error && (
                <p className="cart-error">{error}</p>
              )}

              <button
                type="button"
                className="cart-whatsapp-button"
                onClick={enviarPedido}
                disabled={cargando || !whatsapp}
              >
                <MessageCircle size={19} />

                {cargando
                  ? "Cargando contacto..."
                  : "Realizar pedido por WhatsApp"}
              </button>

              <div className="cart-security-note">
                <ShoppingBag size={15} />
                <span>Tu pedido será confirmado por la tienda.</span>
              </div>

            </aside>

          </div>
        )}

      </div>
    </main>
  );
}

export default Cart;
