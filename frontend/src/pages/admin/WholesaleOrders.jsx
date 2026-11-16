
import { useCallback, useEffect, useState } from "react";

import {
  Search,
  RefreshCw,
  Eye,
  MessageCircle,
  ShoppingBag,
  Clock,
  CheckCircle2,
  Package,
  Truck,
  XCircle,
  X,
  LoaderCircle,
  MapPin,
  Phone,
  User,
  CalendarDays,
  ChevronDown,
} from "lucide-react";

import AdminLayout from "../../components/layout/AdminLayout";
import "../../styles/admin-wholesale-orders.css";

const API_LISTAR = "/api/mayoristas/listar_pedidos.php";
const API_DETALLE = "/api/mayoristas/detalle_pedido.php";
const API_ESTADO = "/api/mayoristas/actualizar_estado.php";

const ESTADOS = [
  { value: "pendiente", label: "Pendiente" },
  { value: "contactado", label: "Contactado" },
  { value: "confirmado", label: "Confirmado" },
  { value: "preparando", label: "Preparando" },
  { value: "enviado", label: "Enviado" },
  { value: "cancelado", label: "Cancelado" },
];

const formatoPrecio = (valor) =>
  new Intl.NumberFormat("es-CO", {
    style: "currency",
    currency: "COP",
    maximumFractionDigits: 0,
  }).format(Number(valor) || 0);

const formatoFecha = (fecha) => {
  if (!fecha) return "Sin fecha";

  const [dia, hora] = fecha.split(" ");
  const [anio, mes, numeroDia] = dia.split("-");

  return `${numeroDia}/${mes}/${anio} ${hora?.slice(0, 5) || ""}`;
};

const iconosEstado = {
  pendiente: Clock,
  contactado: MessageCircle,
  confirmado: CheckCircle2,
  preparando: Package,
  enviado: Truck,
  cancelado: XCircle,
};

function WholesaleOrders() {
  const [pedidos, setPedidos] = useState([]);
  const [estadisticas, setEstadisticas] = useState({});
  const [busqueda, setBusqueda] = useState("");
  const [filtroEstado, setFiltroEstado] = useState("");
  const [cargando, setCargando] = useState(true);
  const [actualizando, setActualizando] = useState(false);
  const [error, setError] = useState("");
  const [mensaje, setMensaje] = useState("");

  const [pedidoSeleccionado, setPedidoSeleccionado] = useState(null);
  const [cargandoDetalle, setCargandoDetalle] = useState(false);
  const [guardandoEstado, setGuardandoEstado] = useState(false);

  const cargarPedidos = useCallback(async () => {
    try {
      setCargando(true);
      setError("");

      const parametros = new URLSearchParams();

      if (busqueda.trim()) {
        parametros.set("buscar", busqueda.trim());
      }

      if (filtroEstado) {
        parametros.set("estado", filtroEstado);
      }

      const respuesta = await fetch(
        `${API_LISTAR}?${parametros.toString()}`,
        {
          credentials: "include",
          cache: "no-store",
        }
      );

      const resultado = await respuesta.json();

      if (!respuesta.ok || !resultado.success) {
        throw new Error(
          resultado.message || "No fue posible consultar los pedidos."
        );
      }

      setPedidos(resultado.data || []);
      setEstadisticas(resultado.estadisticas || {});
    } catch (err) {
      console.error(err);
      setError(err.message || "Error consultando los pedidos.");
    } finally {
      setCargando(false);
      setActualizando(false);
    }
  }, [busqueda, filtroEstado]);

  useEffect(() => {
    cargarPedidos();
  }, [cargarPedidos]);

  const mostrarMensaje = (texto) => {
    setMensaje(texto);
    window.setTimeout(() => setMensaje(""), 3500);
  };

  const abrirDetalle = async (id) => {
    try {
      setPedidoSeleccionado(null);
      setCargandoDetalle(true);

      const respuesta = await fetch(
        `${API_DETALLE}?id=${encodeURIComponent(id)}`,
        {
          credentials: "include",
          cache: "no-store",
        }
      );

      const resultado = await respuesta.json();

      if (!respuesta.ok || !resultado.success) {
        throw new Error(
          resultado.message || "No fue posible consultar el pedido."
        );
      }

      setPedidoSeleccionado(resultado.data);
    } catch (err) {
      mostrarMensaje(err.message);
    } finally {
      setCargandoDetalle(false);
    }
  };

  const cambiarEstado = async (id, estado) => {
    if (!id || !estado || guardandoEstado) return;

    try {
      setGuardandoEstado(true);

      const respuesta = await fetch(API_ESTADO, {
        method: "POST",
        credentials: "include",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ id, estado }),
      });

      const resultado = await respuesta.json();

      if (!respuesta.ok || !resultado.success) {
        throw new Error(
          resultado.message || "No fue posible actualizar el estado."
        );
      }

      mostrarMensaje("Estado actualizado correctamente.");

      await cargarPedidos();

      if (
        pedidoSeleccionado &&
        Number(pedidoSeleccionado.pedido.id) === Number(id)
      ) {
        await abrirDetalle(id);
      }
    } catch (err) {
      mostrarMensaje(err.message);
    } finally {
      setGuardandoEstado(false);
    }
  };

  const actualizarListado = () => {
    setActualizando(true);
    cargarPedidos();
  };

  const abrirWhatsApp = (telefono, referencia) => {
    const numero = String(telefono || "").replace(/\D/g, "");

    if (!numero) {
      mostrarMensaje("El teléfono del cliente no es válido.");
      return;
    }

    const texto = encodeURIComponent(
      `Hola, te contactamos de Siempre Bella con respecto a tu pedido mayorista ${referencia}.`
    );

    window.open(
      `https://wa.me/${numero}?text=${texto}`,
      "_blank",
      "noopener,noreferrer"
    );
  };

  const totalPedidos = Number(estadisticas.total || 0);
  const pendientes = Number(estadisticas.pendientes || 0);
  const contactados = Number(estadisticas.contactados || 0);
  const confirmados = Number(estadisticas.confirmados || 0);

  return (
    <AdminLayout>
      <div className="wholesale-orders-page">

        {/* ENCABEZADO */}
        <div className="wo-page-heading">
          <div>
            <span className="wo-eyebrow">GESTIÓN COMERCIAL</span>
            <h1>Pedidos mayoristas</h1>
            <p>
              Consulta, administra y realiza seguimiento a los pedidos
              de tus clientes mayoristas.
            </p>
          </div>

          <button
            type="button"
            className="wo-refresh-button"
            onClick={actualizarListado}
            disabled={actualizando}
          >
            <RefreshCw
              size={17}
              className={actualizando ? "wo-spinning" : ""}
            />
            Actualizar
          </button>
        </div>

        {/* MENSAJES */}
        {mensaje && (
          <div className="wo-toast">
            <CheckCircle2 size={18} />
            {mensaje}
          </div>
        )}

        {error && (
          <div className="wo-error">
            <XCircle size={19} />
            {error}
            <button onClick={actualizarListado}>
              Reintentar
            </button>
          </div>
        )}

        {/* ESTADÍSTICAS */}
        <div className="wo-stats-grid">

          <div className="wo-stat-card">
            <div className="wo-stat-icon wo-stat-purple">
              <ShoppingBag size={21} />
            </div>
            <div>
              <span>Total de pedidos</span>
              <strong>{totalPedidos}</strong>
            </div>
          </div>

          <div className="wo-stat-card">
            <div className="wo-stat-icon wo-stat-orange">
              <Clock size={21} />
            </div>
            <div>
              <span>Pendientes</span>
              <strong>{pendientes}</strong>
            </div>
          </div>

          <div className="wo-stat-card">
            <div className="wo-stat-icon wo-stat-blue">
              <MessageCircle size={21} />
            </div>
            <div>
              <span>Contactados</span>
              <strong>{contactados}</strong>
            </div>
          </div>

          <div className="wo-stat-card">
            <div className="wo-stat-icon wo-stat-green">
              <CheckCircle2 size={21} />
            </div>
            <div>
              <span>Confirmados</span>
              <strong>{confirmados}</strong>
            </div>
          </div>

        </div>

        {/* LISTADO */}
        <section className="wo-table-section">

          <div className="wo-table-heading">
            <div>
              <h2>Listado de pedidos</h2>
              <p>{pedidos.length} resultados encontrados</p>
            </div>
          </div>

          {/* FILTROS */}
          <div className="wo-filters">

            <div className="wo-search">
              <Search size={18} />

              <input
                type="search"
                placeholder="Buscar referencia, cliente o teléfono..."
                value={busqueda}
                onChange={(e) => setBusqueda(e.target.value)}
              />
            </div>

            <div className="wo-select-wrapper">
              <select
                value={filtroEstado}
                onChange={(e) => setFiltroEstado(e.target.value)}
              >
                <option value="">Todos los estados</option>

                {ESTADOS.map((estado) => (
                  <option key={estado.value} value={estado.value}>
                    {estado.label}
                  </option>
                ))}
              </select>

              <ChevronDown size={16} />
            </div>

          </div>

          {/* TABLA */}
          {cargando ? (
            <div className="wo-loading">
              <LoaderCircle className="wo-spinning" size={30} />
              <p>Cargando pedidos...</p>
            </div>
          ) : pedidos.length === 0 ? (
            <div className="wo-empty">
              <ShoppingBag size={42} />
              <h3>No encontramos pedidos</h3>
              <p>
                Prueba cambiando los filtros o realiza una nueva búsqueda.
              </p>
            </div>
          ) : (
            <div className="wo-table-scroll">
              <table className="wo-table">
                <thead>
                  <tr>
                    <th>Pedido</th>
                    <th>Cliente</th>
                    <th>Fecha</th>
                    <th>Productos</th>
                    <th>Subtotal</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                  </tr>
                </thead>

                <tbody>
                  {pedidos.map((pedido) => {
                    const IconoEstado =
                      iconosEstado[pedido.estado] || Clock;

                    return (
                      <tr key={pedido.id}>

                        <td>
                          <strong className="wo-reference">
                            {pedido.referencia}
                          </strong>
                        </td>

                        <td>
                          <strong>{pedido.nombre_cliente}</strong>
                          <span className="wo-secondary">
                            {pedido.telefono}
                          </span>
                        </td>

                        <td>
                          <span className="wo-date">
                            {formatoFecha(pedido.fecha_creacion)}
                          </span>
                        </td>

                        <td>
                          <strong>{pedido.total_productos}</strong>
                          <span className="wo-secondary">
                            {pedido.total_unidades} unidades
                          </span>
                        </td>

                        <td>
                          <strong className="wo-price">
                            {formatoPrecio(pedido.subtotal)}
                          </strong>
                        </td>

                        <td>
                          <span className={`wo-status wo-status-${pedido.estado}`}>
                            <IconoEstado size={14} />
                            {pedido.estado}
                          </span>
                        </td>

                        <td>
                          <div className="wo-actions">

                            <button
                              type="button"
                              className="wo-action-detail"
                              title="Ver detalle"
                              onClick={() => abrirDetalle(pedido.id)}
                            >
                              <Eye size={17} />
                            </button>

                            <button
                              type="button"
                              className="wo-action-whatsapp"
                              title="Contactar por WhatsApp"
                              onClick={() =>
                                abrirWhatsApp(
                                  pedido.telefono,
                                  pedido.referencia
                                )
                              }
                            >
                              <MessageCircle size={17} />
                            </button>

                          </div>
                        </td>

                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}

        </section>

        {/* MODAL DETALLE */}
        {(cargandoDetalle || pedidoSeleccionado) && (
          <div
            className="wo-modal-overlay"
            onMouseDown={(e) => {
              if (e.target === e.currentTarget) {
                setPedidoSeleccionado(null);
              }
            }}
          >
            <div className="wo-modal">

              <div className="wo-modal-header">
                <div>
                  <span className="wo-eyebrow">DETALLE DEL PEDIDO</span>
                  <h2>
                    {pedidoSeleccionado
                      ? pedidoSeleccionado.pedido.referencia
                      : "Cargando pedido..."}
                  </h2>
                </div>

                <button
                  type="button"
                  className="wo-modal-close"
                  onClick={() => setPedidoSeleccionado(null)}
                >
                  <X size={21} />
                </button>
              </div>

              {cargandoDetalle ? (
                <div className="wo-loading">
                  <LoaderCircle size={30} className="wo-spinning" />
                  <p>Cargando información...</p>
                </div>
              ) : pedidoSeleccionado && (
                <>
                  <div className="wo-customer-grid">

                    <div className="wo-detail-field">
                      <User size={17} />
                      <div>
                        <span>Cliente</span>
                        <strong>
                          {pedidoSeleccionado.pedido.nombre_cliente}
                        </strong>
                      </div>
                    </div>

                    <div className="wo-detail-field">
                      <Phone size={17} />
                      <div>
                        <span>Teléfono</span>
                        <strong>
                          {pedidoSeleccionado.pedido.telefono}
                        </strong>
                      </div>
                    </div>

                    <div className="wo-detail-field">
                      <MapPin size={17} />
                      <div>
                        <span>Ciudad</span>
                        <strong>
                          {pedidoSeleccionado.pedido.ciudad}
                        </strong>
                      </div>
                    </div>

                    <div className="wo-detail-field">
                      <CalendarDays size={17} />
                      <div>
                        <span>Fecha</span>
                        <strong>
                          {formatoFecha(
                            pedidoSeleccionado.pedido.fecha_creacion
                          )}
                        </strong>
                      </div>
                    </div>

                  </div>

                  <div className="wo-detail-products">
                    <h3>Productos del pedido</h3>

                    <div className="wo-detail-product-header">
                      <span>Producto</span>
                      <span>Cantidad</span>
                      <span>Precio</span>
                      <span>Subtotal</span>
                    </div>

                    {pedidoSeleccionado.productos.map((producto) => (
                      <div
                        className="wo-detail-product"
                        key={producto.id}
                      >
                        <strong>{producto.nombre_producto}</strong>
                        <span>{producto.cantidad}</span>
                        <span>
                          {formatoPrecio(producto.precio_unitario)}
                        </span>
                        <strong>
                          {formatoPrecio(producto.subtotal)}
                        </strong>
                      </div>
                    ))}
                  </div>

                  <div className="wo-detail-summary">
                    <div>
                      <span>Mínimo mayorista</span>
                      <strong>
                        {formatoPrecio(
                          pedidoSeleccionado.pedido.minimo_mayorista
                        )}
                      </strong>
                    </div>

                    <div className="wo-detail-grand-total">
                      <span>Subtotal del pedido</span>
                      <strong>
                        {formatoPrecio(
                          pedidoSeleccionado.pedido.subtotal
                        )}
                      </strong>
                    </div>
                  </div>

                  <div className="wo-status-editor">
                    <label htmlFor="wo-status-select">
                      Estado del pedido
                    </label>

                    <select
                      id="wo-status-select"
                      value={pedidoSeleccionado.pedido.estado}
                      onChange={(e) =>
                        cambiarEstado(
                          pedidoSeleccionado.pedido.id,
                          e.target.value
                        )
                      }
                      disabled={guardandoEstado}
                    >
                      {ESTADOS.map((estado) => (
                        <option
                          key={estado.value}
                          value={estado.value}
                        >
                          {estado.label}
                        </option>
                      ))}
                    </select>

                    {guardandoEstado && (
                      <LoaderCircle
                        size={17}
                        className="wo-spinning"
                      />
                    )}
                  </div>

                  <button
                    type="button"
                    className="wo-modal-whatsapp"
                    onClick={() =>
                      abrirWhatsApp(
                        pedidoSeleccionado.pedido.telefono,
                        pedidoSeleccionado.pedido.referencia
                      )
                    }
                  >
                    <MessageCircle size={18} />
                    Contactar por WhatsApp
                  </button>
                </>
              )}

            </div>
          </div>
        )}

      </div>
    </AdminLayout>
  );
}

export default WholesaleOrders;
