
import { useEffect, useMemo, useState } from "react";
import { useWholesaleCart } from "../../context/WholesaleContext";

import {
  ShoppingBag,
  Plus,
  Minus,
  Trash2,
  MessageCircle,
  Store,
  LoaderCircle,
  AlertCircle,
  CheckCircle2,
  RefreshCw,
} from "lucide-react";

import "../../styles/wholesale.css";

const API_PRODUCTOS = "/api/productos/listar.php";
const API_CONFIGURACION = "/api/configuracion/footer_publico.php";

const formatoPrecio = (valor) =>
  new Intl.NumberFormat("es-CO", {
    style: "currency",
    currency: "COP",
    maximumFractionDigits: 0,
  }).format(Number(valor) || 0);

const obtenerImagen = (ruta) => {
  if (!ruta) return null;

  if (ruta.startsWith("http://") || ruta.startsWith("https://")) {
    return ruta;
  }

  return `/${ruta.replace(/^\/+/, "")}`;
};

function Wholesale() {
  const {
    productos: carrito,
    agregarProducto,
    actualizarCantidad,
    actualizarPrecioProducto,
    eliminarProducto,
    vaciarCarrito,
    subtotal,
    totalUnidades,
  } = useWholesaleCart();

  const [catalogo, setCatalogo] = useState([]);
  const [configuracion, setConfiguracion] = useState(null);
  const [cargando, setCargando] = useState(true);
  const [validandoPedido, setValidandoPedido] = useState(false);
  const [error, setError] = useState("");
  const [mensaje, setMensaje] = useState("");
  const [datosComprador, setDatosComprador] = useState({
      nombre: "",
      telefono: "",
      ciudad: "",
    });

  // Cargar catálogo y configuración
  useEffect(() => {
    const cargarDatos = async () => {
      try {
        setCargando(true);
        setError("");

        const [respuestaProductos, respuestaConfiguracion] =
          await Promise.all([
            fetch(API_PRODUCTOS),
            fetch(API_CONFIGURACION),
          ]);

        if (!respuestaProductos.ok || !respuestaConfiguracion.ok) {
          throw new Error("No fue posible consultar la información de la tienda.");
        }

        const datosProductos = await respuestaProductos.json();
        const datosConfiguracion = await respuestaConfiguracion.json();

        if (!datosProductos.success || !datosConfiguracion.success) {
          throw new Error("El servidor no pudo entregar los datos solicitados.");
        }

        const lista = Array.isArray(datosProductos.data)
          ? datosProductos.data
          : Array.isArray(datosProductos.productos)
            ? datosProductos.productos
            : [];

        const disponibles = lista.filter(
          (producto) =>
            Number(producto.precio_mayorista) > 0 &&
            producto.disponibilidad === "disponible"
        );

        setCatalogo(disponibles);
        setConfiguracion(datosConfiguracion.data);
      } catch (err) {
        console.error("Error cargando tienda mayorista:", err);
        setError(err.message || "Ocurrió un error al cargar la tienda.");
      } finally {
        setCargando(false);
      }
    };

    cargarDatos();
  }, []);

  const minimoMayorista = Number(
    configuracion?.tienda?.monto_minimo_mayorista || 150000
  );

  const whatsapp = configuracion?.tienda?.whatsapp || "";

  const progreso = useMemo(() => {
    if (!minimoMayorista) return 0;

    return Math.min(
      (Number(subtotal) / minimoMayorista) * 100,
      100
    );
  }, [subtotal, minimoMayorista]);

  const faltante = Math.max(
    minimoMayorista - Number(subtotal),
    0
  );

  const cumpleMinimo = Number(subtotal) >= minimoMayorista;

  const mostrarMensaje = (texto) => {
    setMensaje(texto);
    window.setTimeout(() => setMensaje(""), 4000);
  };

  // Agregar producto
  const agregarAlCarrito = (producto) => {
    agregarProducto(producto);
    mostrarMensaje(`${producto.nombre} agregado al pedido mayorista.`);
  };

  // Validar los productos consultando nuevamente la API


const registrarPedido = async () => {
  if (validandoPedido || carrito.length === 0) return;

  const nombre = datosComprador.nombre.trim();
  const telefono = datosComprador.telefono.trim();
  const ciudad = datosComprador.ciudad.trim();

  if (!nombre || !telefono || !ciudad) {
    mostrarMensaje("Completa tu nombre, teléfono y ciudad.");
    return;
  }

  if (nombre.length > 150 || ciudad.length > 120 || telefono.length > 40) {
    mostrarMensaje("Revisa la longitud de los datos ingresados.");
    return;
  }

  if (!/^[0-9+\s().-]{7,40}$/.test(telefono)) {
    mostrarMensaje("Ingresa un número de teléfono válido.");
    return;
  }

  if (!cumpleMinimo) {
    mostrarMensaje("Tu pedido todavía no alcanza el mínimo mayorista.");
    return;
  }

  // Abrir ventana desde el clic para evitar bloqueo de ventanas emergentes.
  const ventana = window.open("about:blank", "_blank");

  if (!ventana) {
    mostrarMensaje("Permite las ventanas emergentes para continuar.");
    return;
  }

  setValidandoPedido(true);
  setMensaje("");

  try {
    const respuesta = await fetch(
      "/api/mayoristas/registrar_pedido.php",
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        cache: "no-store",
        body: JSON.stringify({
          nombre,
          telefono,
          ciudad,
          productos: carrito.map((producto) => ({
            id: producto.id,
            cantidad: producto.cantidad,
          })),
        }),
      }
    );

    const resultado = await respuesta.json();

    if (!respuesta.ok || !resultado.success) {
      throw new Error(
        resultado.message || "No fue posible registrar el pedido."
      );
    }

    const pedido = resultado.data;

    // Construir el mensaje usando exclusivamente la respuesta de PHP.
    const lineas = pedido.productos.map((producto) => {
      return `• ${producto.nombre} x${producto.cantidad} - ${formatoPrecio(producto.subtotal)}`;
    });

    const mensajeWhatsApp = [
      `Hola, quiero continuar con mi pedido mayorista en ${pedido.tienda}.`,
      "",
      `Referencia: ${pedido.referencia}`,
      `Cliente: ${pedido.nombre}`,
      `Teléfono: ${pedido.telefono}`,
      `Ciudad: ${pedido.ciudad}`,
      "",
      "PRODUCTOS:",
      ...lineas,
      "",
      `Subtotal: ${formatoPrecio(pedido.subtotal)}`,
      `Compra mínima: ${formatoPrecio(pedido.minimo_mayorista)}`,
      `Estado inicial: ${pedido.estado}`,
      "",
      "El envío no está incluido. Por favor, confirmen disponibilidad, costo de envío y medios de pago.",
    ].join("\n");

    const numero = String(pedido.whatsapp || "").replace(/\D/g, "");

    if (!numero) {
      ventana.close();
      mostrarMensaje(
        `Pedido ${pedido.referencia} registrado, pero el WhatsApp no está configurado.`
      );
      return;
    }

    const url =
      `https://wa.me/${numero}?text=${encodeURIComponent(mensajeWhatsApp)}`;

    // Abrir WhatsApp con la referencia del pedido registrado.
    ventana.location.href = url;

    // Vaciar el carrito después de registrar correctamente.
    vaciarCarrito();

    setDatosComprador({
      nombre: "",
      telefono: "",
      ciudad: "",
    });

    mostrarMensaje(
      `¡Pedido ${pedido.referencia} registrado correctamente!`
    );

  } catch (err) {
    console.error("Error registrando pedido:", err);

    ventana.close();

    mostrarMensaje(
      err.message || "No fue posible registrar tu pedido."
    );

  } finally {
    setValidandoPedido(false);
  }
};



  if (error) {
    return (
      <section className="wholesale-page">
        <div className="wholesale-error">
          <AlertCircle size={28} />
          <h2>No pudimos cargar la tienda mayorista</h2>
          <p>{error}</p>

          <button
            type="button"
            onClick={() => window.location.reload()}
          >
            Intentar nuevamente
          </button>
        </div>
      </section>
    );
  }

  return (
    <div className="wholesale-page">

      {/* ENCABEZADO */}
      <section className="wholesale-hero">
        <div className="wholesale-hero-content">
          <span className="wholesale-eyebrow">
            <Store size={16} />
            SIEMPRE BELLA
          </span>

          <h1>Compra al por mayor</h1>

          <p>
            Encuentra tus productos favoritos con precios especiales
            para compras mayoristas.
          </p>

          <div className="wholesale-minimum">
            <span>Compra mínima</span>
            <strong>{formatoPrecio(minimoMayorista)}</strong>
            <small>El envío no está incluido en este valor.</small>
          </div>
        </div>
      </section>

      {/* MENSAJES */}
      {mensaje && (
        <div className="wholesale-toast">
          <AlertCircle size={18} />
          <span>{mensaje}</span>
        </div>
      )}

      <div className="wholesale-container">

        {/* CATÁLOGO */}
        <section className="wholesale-catalog">
          <div className="wholesale-section-heading">
            <div>
              <span className="wholesale-kicker">NUESTRO CATÁLOGO</span>
              <h2>Productos mayoristas</h2>
              <p>Precios especiales para tu negocio.</p>
            </div>

            <span className="wholesale-product-count">
              {catalogo.length} productos
            </span>
          </div>

          {catalogo.length === 0 ? (
            <div className="wholesale-empty">
              <ShoppingBag size={38} />
              <h3>Próximamente tendremos productos</h3>
              <p>
                En este momento no hay productos disponibles para compra
                mayorista.
              </p>
            </div>
          ) : (
            <div className="wholesale-products-grid">
              {catalogo.map((producto) => {
                const imagen = obtenerImagen(producto.imagen);

                return (
                  <article
                    className="wholesale-product-card"
                    key={producto.id}
                  >
                    <div className="wholesale-product-image">
                      {imagen ? (
                        <img
                          src={imagen}
                          alt={producto.nombre}
                          onError={(event) => {
                            event.currentTarget.style.display = "none";
                          }}
                        />
                      ) : (
                        <ShoppingBag size={42} />
                      )}
                    </div>

                    <div className="wholesale-product-info">
                      {producto.marca && (
                        <span className="wholesale-brand">
                          {typeof producto.marca === "object"
                            ? producto.marca.nombre
                            : producto.marca}
                        </span>
                      )}

                      <h3>{producto.nombre}</h3>

                      <span className="wholesale-price-label">
                        Precio mayorista
                      </span>

                      <strong className="wholesale-product-price">
                        {formatoPrecio(producto.precio_mayorista)}
                      </strong>

                      <button
                        type="button"
                        className="wholesale-add-button"
                        onClick={() => agregarAlCarrito(producto)}
                      >
                        <Plus size={18} />
                        Agregar al pedido
                      </button>
                    </div>
                  </article>
                );
              })}
            </div>
          )}
        </section>

        {/* CARRITO */}
        <aside className="wholesale-cart">
          <div className="wholesale-cart-header">
            <div>
              <span className="wholesale-kicker">TU COMPRA</span>
              <h2>Pedido mayorista</h2>
            </div>

            <span className="wholesale-cart-count">
              {totalUnidades || 0}
            </span>
          </div>

          {carrito.length === 0 ? (
            <div className="wholesale-cart-empty">
              <ShoppingBag size={38} />
              <p>Aún no has agregado productos.</p>
              <span>Selecciona productos del catálogo para comenzar.</span>
            </div>
          ) : (
            <>
              <div className="wholesale-cart-items">
                {carrito.map((producto) => (
                  <div
                    className="wholesale-cart-item"
                    key={producto.id}
                  >
                    <div className="wholesale-cart-item-info">
                      <strong>{producto.name}</strong>

                      <span>
                        {formatoPrecio(producto.price_unitario)} por unidad
                      </span>

                      <b>
                        {formatoPrecio(
                          producto.price_unitario * producto.cantidad
                        )}
                      </b>
                    </div>

                    <div className="wholesale-cart-controls">
                      <button
                        type="button"
                        aria-label="Disminuir cantidad"
                        onClick={() =>
                          actualizarCantidad(
                            producto.id,
                            Math.max(1, producto.cantidad - 1)
                          )
                        }
                        disabled={producto.cantidad <= 1}
                      >
                        <Minus size={14} />
                      </button>

                      <span>{producto.cantidad}</span>

                      <button
                        type="button"
                        aria-label="Aumentar cantidad"
                        onClick={() =>
                          actualizarCantidad(
                            producto.id,
                            producto.cantidad + 1
                          )
                        }
                      >
                        <Plus size={14} />
                      </button>

                      <button
                        type="button"
                        className="wholesale-remove-button"
                        aria-label="Eliminar producto"
                        onClick={() => eliminarProducto(producto.id)}
                      >
                        <Trash2 size={16} />
                      </button>
                    </div>
                  </div>
                ))}
              </div>

              <button
                type="button"
                className="wholesale-clear-button"
                onClick={vaciarCarrito}
              >
                Vaciar pedido
              </button>
            </>
          )}

          {/* PROGRESO */}
          <div className="wholesale-progress-section">
            <div className="wholesale-progress-label">
              <span>Progreso de compra mínima</span>
              <strong>{Math.round(progreso)}%</strong>
            </div>

            <div className="wholesale-progress-track">
              <div
                className="wholesale-progress-fill"
                style={{ width: `${progreso}%` }}
              />
            </div>

            {cumpleMinimo ? (
              <p className="wholesale-minimum-success">
                <CheckCircle2 size={16} />
                ¡Ya alcanzaste el mínimo mayorista!
              </p>
            ) : (
              <p className="wholesale-minimum-missing">
                Te faltan{" "}
                <strong>{formatoPrecio(faltante)}</strong> para completar
                tu compra mínima.
              </p>
            )}
          </div>

          {/* SUBTOTAL */}
          <div className="wholesale-cart-total">
            <span>Subtotal</span>
            <strong>{formatoPrecio(subtotal)}</strong>
          </div>

          <p className="wholesale-shipping-note">
            El envío se cotiza por separado y no cuenta para alcanzar el
            mínimo mayorista.
          </p>


<div className="wholesale-buyer-form">

  <h3>Datos del comprador</h3>

  <p>
    Necesitamos estos datos para registrar tu pedido.
  </p>

  <div className="wholesale-form-field">
    <label htmlFor="buyer-name">
      Nombre completo
    </label>

    <input
      id="buyer-name"
      type="text"
      maxLength={150}
      value={datosComprador.nombre}
      onChange={(e) =>
        setDatosComprador((actual) => ({
          ...actual,
          nombre: e.target.value,
        }))
      }
      placeholder="Tu nombre completo"
    />
  </div>

  <div className="wholesale-form-field">
    <label htmlFor="buyer-phone">
      Teléfono / WhatsApp
    </label>

    <input
      id="buyer-phone"
      type="tel"
      maxLength={40}
      value={datosComprador.telefono}
      onChange={(e) =>
        setDatosComprador((actual) => ({
          ...actual,
          telefono: e.target.value,
        }))
      }
      placeholder="Tu número de contacto"
    />
  </div>

  <div className="wholesale-form-field">
    <label htmlFor="buyer-city">
      Ciudad
    </label>

    <input
      id="buyer-city"
      type="text"
      maxLength={120}
      value={datosComprador.ciudad}
      onChange={(e) =>
        setDatosComprador((actual) => ({
          ...actual,
          ciudad: e.target.value,
        }))
      }
      placeholder="Ciudad donde te encuentras"
    />
  </div>

</div>


          <button
            type="button"
            className="wholesale-checkout-button"
            onClick={registrarPedido}
            disabled={
              !cumpleMinimo ||
              carrito.length === 0 ||
              !whatsapp ||
              validandoPedido
            }
          >
            {validandoPedido ? (
              <>
                <LoaderCircle className="wholesale-spinner" size={19} />
                Registrando pedido...
              </>
            ) : (
              <>
                <MessageCircle size={19} />
                Registrar pedido y continuar
              </>
            )}
          </button>

          {!whatsapp && (
            <p className="wholesale-warning">
              Configura el WhatsApp de la tienda para habilitar los pedidos.
            </p>
          )}
        </aside>
      </div>
    </div>
  );
}

export default Wholesale;
