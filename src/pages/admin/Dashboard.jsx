
import { useEffect, useState } from "react";
import { Link } from "react-router-dom";

import {
  ShoppingBag,
  Clock3,
  MessageCircle,
  CheckCircle2,
  Package,
  RefreshCw,
  ArrowRight,
  Eye
} from "lucide-react";

import AdminLayout from "../../components/layout/AdminLayout.jsx";
import DashboardCharts from "../../components/admin/DashboardCharts.jsx";

import "../../styles/admin-dashboard.css";

const API_PEDIDOS = "/api/mayoristas/listar_pedidos.php";
const API_PRODUCTOS = "/api/productos/listar.php";
const API_ESTADISTICAS = "/api/dashboard/estadisticas.php";

// Formatear precios en pesos colombianos
function formatoPrecio(valor) {
  return new Intl.NumberFormat("es-CO", {
    style: "currency",
    currency: "COP",
    maximumFractionDigits: 0
  }).format(Number(valor) || 0);
}

// Formatear fechas
function formatoFecha(fecha) {
  if (!fecha) return "Sin fecha";

  const fechaLocal = new Date(fecha.replace(" ", "T"));

  if (Number.isNaN(fechaLocal.getTime())) {
    return fecha;
  }

  return fechaLocal.toLocaleString("es-CO", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit"
  });
}

function Dashboard() {
  const [pedidos, setPedidos] = useState([]);
  const [estadisticas, setEstadisticas] = useState({});
  const [datosGraficos, setDatosGraficos] = useState(null);

  const [totalProductos, setTotalProductos] = useState(0);

  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState("");

  // Consultar toda la información del Dashboard
  async function cargarDashboard() {
    try {
      setCargando(true);
      setError("");

      // Consultar los tres endpoints simultáneamente
      const [
        respuestaPedidos,
        respuestaProductos,
        respuestaEstadisticas
      ] = await Promise.all([
        fetch(API_PEDIDOS, {
          credentials: "include"
        }),

        fetch(API_PRODUCTOS, {
          credentials: "include"
        }),

        fetch(API_ESTADISTICAS, {
          credentials: "include"
        })
      ]);

      // Validar respuestas HTTP
      if (!respuestaPedidos.ok) {
        throw new Error("No fue posible consultar los pedidos.");
      }

      if (!respuestaProductos.ok) {
        throw new Error("No fue posible consultar los productos.");
      }

      if (!respuestaEstadisticas.ok) {
        throw new Error("No fue posible consultar las estadísticas.");
      }

      // Convertir respuestas a JSON
      const [
        datosPedidos,
        datosProductos,
        datosEstadisticas
      ] = await Promise.all([
        respuestaPedidos.json(),
        respuestaProductos.json(),
        respuestaEstadisticas.json()
      ]);

      // Validar respuestas de los endpoints
      if (!datosPedidos.success) {
        throw new Error(
          datosPedidos.message || "Error consultando pedidos."
        );
      }

      if (!datosProductos.success) {
        throw new Error(
          datosProductos.message || "Error consultando productos."
        );
      }

      if (!datosEstadisticas.success) {
        throw new Error(
          datosEstadisticas.message || "Error consultando estadísticas."
        );
      }

      // Guardar pedidos y estadísticas generales
      setPedidos(datosPedidos.data || []);
      setEstadisticas(datosPedidos.estadisticas || {});

      // Guardar productos
      const listaProductos = Array.isArray(datosProductos.data)
        ? datosProductos.data
        : [];

      setTotalProductos(listaProductos.length);

      // Guardar información para los gráficos
      setDatosGraficos(datosEstadisticas);

    } catch (err) {
      console.error("Error en Dashboard:", err);

      setError(
        err.message || "Ocurrió un error cargando el Dashboard."
      );

    } finally {
      setCargando(false);
    }
  }

  // Cargar información al entrar al Dashboard
  useEffect(() => {
    cargarDashboard();
  }, []);

  // Indicadores principales
  const indicadores = [
    {
      titulo: "Total de pedidos",
      valor: estadisticas.total ?? 0,
      icono: ShoppingBag,
      clase: "dashboard-purple"
    },
    {
      titulo: "Pedidos pendientes",
      valor: estadisticas.pendientes ?? 0,
      icono: Clock3,
      clase: "dashboard-orange"
    },
    {
      titulo: "Contactados",
      valor: estadisticas.contactados ?? 0,
      icono: MessageCircle,
      clase: "dashboard-blue"
    },
    {
      titulo: "Confirmados",
      valor: estadisticas.confirmados ?? 0,
      icono: CheckCircle2,
      clase: "dashboard-green"
    }
  ];

  return (
    <AdminLayout>
      <div className="dashboard-page">

        {/* ENCABEZADO */}
        <header className="dashboard-header">
          <div>
            <span className="dashboard-eyebrow">
              PANEL DE CONTROL
            </span>

            <h1>Bienvenido a Siempre Bella</h1>

            <p>
              Resumen general de la actividad de tu tienda.
            </p>
          </div>

          <button
            className="dashboard-refresh"
            onClick={cargarDashboard}
            disabled={cargando}
          >
            <RefreshCw
              size={17}
              className={cargando ? "dashboard-spin" : ""}
            />

            Actualizar
          </button>
        </header>

        {/* MENSAJE DE ERROR */}
        {error && (
          <div className="dashboard-error">
            {error}

            <button onClick={cargarDashboard}>
              Reintentar
            </button>
          </div>
        )}

        {/* INDICADORES PRINCIPALES */}
        <section className="dashboard-stats">
          {indicadores.map((item) => {
            const Icono = item.icono;

            return (
              <article
                className="dashboard-stat-card"
                key={item.titulo}
              >
                <div className={`dashboard-stat-icon ${item.clase}`}>
                  <Icono size={23} />
                </div>

                <div>
                  <span>{item.titulo}</span>

                  <strong>
                    {cargando ? "..." : Number(item.valor)}
                  </strong>
                </div>
              </article>
            );
          })}
        </section>

        {/* PRODUCTOS REGISTRADOS */}

        <section className="dashboard-secondary">

          <article className="dashboard-product-card">
            <div className="dashboard-secondary-icon">
              <Package size={23} />
            </div>

            <div>
              <span>Productos registrados</span>
              <strong>{cargando ? "..." : totalProductos}</strong>
            </div>

            <Link to="/admin/productos" className="dashboard-card-link">
              Ver productos
              <ArrowRight size={16} />
            </Link>
          </article>

          <article className="dashboard-product-card">
            <div className="dashboard-value-icon">
              <ShoppingBag size={23} />
            </div>

            <div>
              <span>Valor total de pedidos</span>
              <strong>
                {cargando
                  ? "..."
                  : formatoPrecio(datosGraficos?.resumen?.valor_pedidos)}
              </strong>
            </div>

            <span className="dashboard-value-note">
              Pedidos registrados
            </span>
          </article>

        </section>


        {/* GRÁFICOS ESTADÍSTICOS */}
        {datosGraficos && (
          <DashboardCharts estadisticas={datosGraficos} />
        )}

        {/* PEDIDOS RECIENTES */}
        <section className="dashboard-orders">

          <div className="dashboard-section-header">
            <div>
              <h2>Pedidos recientes</h2>

              <p>
                Últimos pedidos mayoristas registrados.
              </p>
            </div>

            <Link to="/admin/pedidos-mayoristas">
              Ver todos
              <ArrowRight size={17} />
            </Link>
          </div>

          {cargando ? (
            <div className="dashboard-empty">
              Cargando pedidos...
            </div>

          ) : pedidos.length === 0 ? (
            <div className="dashboard-empty">
              Todavía no hay pedidos registrados.
            </div>

          ) : (
            <div className="dashboard-table-wrapper">

              <table className="dashboard-table">

                <thead>
                  <tr>
                    <th>Referencia</th>
                    <th>Cliente</th>
                    <th>Fecha</th>
                    <th>Subtotal</th>
                    <th>Estado</th>
                    <th>Acción</th>
                  </tr>
                </thead>

                <tbody>
                  {pedidos.slice(0, 5).map((pedido) => (
                    <tr key={pedido.id}>

                      <td className="dashboard-reference">
                        {pedido.referencia}
                      </td>

                      <td>
                        {pedido.nombre_cliente}
                      </td>

                      <td>
                        {formatoFecha(pedido.fecha_creacion)}
                      </td>

                      <td className="dashboard-price">
                        {formatoPrecio(pedido.subtotal)}
                      </td>

                      <td>
                        <span
                          className={`dashboard-status status-${pedido.estado}`}
                        >
                          {pedido.estado}
                        </span>
                      </td>

                      <td>
                        <Link
                          to="/admin/pedidos-mayoristas"
                          className="dashboard-view-button"
                          title="Consultar pedidos"
                        >
                          <Eye size={17} />
                        </Link>
                      </td>

                    </tr>
                  ))}
                </tbody>

              </table>
            </div>
          )}
        </section>

        {/* ACCESOS RÁPIDOS */}
        <section className="dashboard-shortcuts">

          <h2>Accesos rápidos</h2>

          <div className="dashboard-shortcut-grid">

            <Link
              to="/admin/productos/nuevo"
              className="dashboard-shortcut"
            >
              <Package size={21} />

              <span>Agregar producto</span>

              <ArrowRight size={17} />
            </Link>

            <Link
              to="/admin/promociones"
              className="dashboard-shortcut"
            >
              <ShoppingBag size={21} />

              <span>Administrar promociones</span>

              <ArrowRight size={17} />
            </Link>

            <Link
              to="/admin/pedidos-mayoristas"
              className="dashboard-shortcut"
            >
              <MessageCircle size={21} />

              <span>Gestionar pedidos</span>

              <ArrowRight size={17} />
            </Link>

          </div>
        </section>

      </div>
    </AdminLayout>
  );
}

export default Dashboard;
