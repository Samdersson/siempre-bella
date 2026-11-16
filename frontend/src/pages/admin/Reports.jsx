
import { useEffect, useState } from "react";

import {
  FileSpreadsheet,
  ShoppingBag,
  Users,
  DollarSign,
  Clock3,
  RefreshCw,
  Download,
  Package,
  MapPin,
  CheckCircle2,
  MessageCircle,
  XCircle,
  Truck,
  ClipboardList
} from "lucide-react";

import AdminLayout from "../../components/layout/AdminLayout.jsx";
import "../../styles/admin-reports.css";

import "../../styles/admin-reports.css";

const API_ESTADISTICAS = "/api/reportes/estadisticas.php";
const API_EXPORTAR = "/api/reportes/exportar_excel.php";

// Fecha local para evitar diferencias por zona horaria.
function obtenerFechaLocal(fecha = new Date()) {
  const anio = fecha.getFullYear();
  const mes = String(fecha.getMonth() + 1).padStart(2, "0");
  const dia = String(fecha.getDate()).padStart(2, "0");

  return `${anio}-${mes}-${dia}`;
}

function obtenerPrimerDiaMes() {
  const hoy = new Date();
  return obtenerFechaLocal(
    new Date(hoy.getFullYear(), hoy.getMonth(), 1)
  );
}

function formatoPrecio(valor) {
  return new Intl.NumberFormat("es-CO", {
    style: "currency",
    currency: "COP",
    maximumFractionDigits: 0
  }).format(Number(valor) || 0);
}

function formatoFecha(fecha) {
  if (!fecha) return "Sin fecha";

  const valor = String(fecha);

  // Las fechas YYYY-MM-DD se interpretan como fechas locales.
  const soloFecha = /^(\d{4})-(\d{2})-(\d{2})$/.exec(valor);

  let fechaLocal;

  if (soloFecha) {
    fechaLocal = new Date(
      Number(soloFecha[1]),
      Number(soloFecha[2]) - 1,
      Number(soloFecha[3])
    );
  } else {
    fechaLocal = new Date(valor.replace(" ", "T"));
  }

  if (Number.isNaN(fechaLocal.getTime())) {
    return fecha;
  }

  return fechaLocal.toLocaleDateString("es-CO", {
    day: "2-digit",
    month: "short",
    year: "numeric"
  });
}

function Reports() {
  const [fechaInicio, setFechaInicio] = useState(obtenerPrimerDiaMes);
  const [fechaFin, setFechaFin] = useState(() => obtenerFechaLocal());

  const [datos, setDatos] = useState(null);
  const [cargando, setCargando] = useState(true);
  const [exportando, setExportando] = useState(false);
  const [error, setError] = useState("");
  const [mensaje, setMensaje] = useState("");

  // Consultar reportes comerciales.
  async function cargarReportes() {
    try {
      setCargando(true);
      setError("");
      setMensaje("");

      if (!fechaInicio || !fechaFin) {
        throw new Error("Selecciona las dos fechas del periodo.");
      }

      if (fechaInicio > fechaFin) {
        throw new Error(
          "La fecha inicial no puede ser posterior a la fecha final."
        );
      }

      const parametros = new URLSearchParams({
        fecha_inicio: fechaInicio,
        fecha_fin: fechaFin
      });

      const respuesta = await fetch(
        `${API_ESTADISTICAS}?${parametros.toString()}`,
        {
          method: "GET",
          credentials: "include"
        }
      );

      const resultado = await respuesta.json();

      if (!respuesta.ok || !resultado.success) {
        throw new Error(
          resultado.message || "No fue posible consultar los reportes."
        );
      }

      setDatos(resultado);

    } catch (err) {
      console.error("Error cargando reportes:", err);
      setError(err.message || "Ocurrió un error consultando los reportes.");
      setDatos(null);

    } finally {
      setCargando(false);
    }
  }

  // Descargar Excel con el mismo periodo consultado.
  async function exportarExcel() {
    try {
      setExportando(true);
      setError("");
      setMensaje("");

      if (!datos) {
        throw new Error("Primero debes consultar los reportes.");
      }

      const parametros = new URLSearchParams({
        fecha_inicio: fechaInicio,
        fecha_fin: fechaFin
      });

      const respuesta = await fetch(
        `${API_EXPORTAR}?${parametros.toString()}`,
        {
          method: "GET",
          credentials: "include"
        }
      );

      if (!respuesta.ok) {
        const tipo = respuesta.headers.get("content-type") || "";

        if (tipo.includes("application/json")) {
          const resultado = await respuesta.json();
          throw new Error(
            resultado.message || "No fue posible exportar el Excel."
          );
        }

        throw new Error("No fue posible generar el archivo Excel.");
      }

      const archivo = await respuesta.blob();

      if (!archivo.size) {
        throw new Error("El archivo Excel está vacío.");
      }

      const url = window.URL.createObjectURL(archivo);
      const enlace = document.createElement("a");

      enlace.href = url;
      enlace.download =
        `Reporte_Siempre_Bella_${fechaInicio}_al_${fechaFin}.xlsx`;

      document.body.appendChild(enlace);
      enlace.click();
      enlace.remove();

      window.URL.revokeObjectURL(url);

      setMensaje("El reporte Excel se descargó correctamente.");

    } catch (err) {
      console.error("Error exportando Excel:", err);
      setError(err.message || "No fue posible descargar el Excel.");

    } finally {
      setExportando(false);
    }
  }

  useEffect(() => {
    cargarReportes();
  }, []);

  const resumen = datos?.resumen || {};

  const indicadores = [
    {
      titulo: "Total de pedidos",
      valor: resumen.total_pedidos ?? 0,
      icono: ShoppingBag,
      clase: "report-purple"
    },
    {
      titulo: "Valor de pedidos",
      valor: formatoPrecio(resumen.valor_pedidos),
      icono: DollarSign,
      clase: "report-green"
    },
    {
      titulo: "Clientes únicos",
      valor: resumen.clientes_unicos ?? 0,
      icono: Users,
      clase: "report-blue"
    },
    {
      titulo: "Pedidos pendientes",
      valor: resumen.pendientes ?? 0,
      icono: Clock3,
      clase: "report-orange"
    }
  ];

  const estados = [
    {
      nombre: "Pendientes",
      valor: resumen.pendientes ?? 0,
      icono: Clock3,
      clase: "report-orange"
    },
    {
      nombre: "Contactados",
      valor: resumen.contactados ?? 0,
      icono: MessageCircle,
      clase: "report-blue"
    },
    {
      nombre: "Confirmados",
      valor: resumen.confirmados ?? 0,
      icono: CheckCircle2,
      clase: "report-green"
    },
    {
      nombre: "Preparando",
      valor: resumen.preparando ?? 0,
      icono: Package,
      clase: "report-purple"
    },
    {
      nombre: "Enviados",
      valor: resumen.enviados ?? 0,
      icono: Truck,
      clase: "report-blue"
    },
    {
      nombre: "Cancelados",
      valor: resumen.cancelados ?? 0,
      icono: XCircle,
      clase: "report-red"
    }
  ];

  return (
    <AdminLayout>
      <div className="reports-page">

        {/* ENCABEZADO */}
        <header className="reports-header">
          <div>
            <span className="reports-eyebrow">
              ANÁLISIS COMERCIAL
            </span>

            <h1>Reportes comerciales</h1>

            <p>
              Consulta el comportamiento de los pedidos mayoristas,
              clientes y productos de Siempre Bella.
            </p>
          </div>

          <button
            type="button"
            className="reports-export-button"
            onClick={exportarExcel}
            disabled={cargando || exportando || !datos}
          >
            {exportando ? (
              <RefreshCw size={18} className="reports-spin" />
            ) : (
              <FileSpreadsheet size={19} />
            )}

            {exportando ? "Generando Excel..." : "Exportar a Excel"}
          </button>
        </header>

        {/* FILTROS */}
        <section className="reports-filter-card">
          <div className="reports-filter-title">
            <div className="reports-filter-icon">
              <ClipboardList size={20} />
            </div>

            <div>
              <h2>Periodo del reporte</h2>
              <p>Selecciona las fechas que deseas consultar.</p>
            </div>
          </div>

          <div className="reports-filter-controls">
            <label>
              <span>Fecha inicial</span>
              <input
                type="date"
                value={fechaInicio}
                max={fechaFin || undefined}
                onChange={(e) => setFechaInicio(e.target.value)}
              />
            </label>

            <label>
              <span>Fecha final</span>
              <input
                type="date"
                value={fechaFin}
                min={fechaInicio || undefined}
                max={obtenerFechaLocal()}
                onChange={(e) => setFechaFin(e.target.value)}
              />
            </label>

            <button
              type="button"
              className="reports-search-button"
              onClick={cargarReportes}
              disabled={cargando}
            >
              <RefreshCw
                size={17}
                className={cargando ? "reports-spin" : ""}
              />
              Consultar
            </button>
          </div>
        </section>

        {/* MENSAJES */}
        {error && (
          <div className="reports-alert reports-alert-error">
            <span>{error}</span>

            <button type="button" onClick={cargarReportes}>
              Reintentar
            </button>
          </div>
        )}

        {mensaje && (
          <div className="reports-alert reports-alert-success">
            {mensaje}
          </div>
        )}

        {/* INDICADORES PRINCIPALES */}
        <section className="reports-section">
          <div className="reports-section-heading">
            <div>
              <h2>Indicadores generales</h2>
              <p>Resultados del periodo seleccionado.</p>
            </div>

            {datos && (
              <span className="reports-period-label">
                {formatoFecha(fechaInicio)} — {formatoFecha(fechaFin)}
              </span>
            )}
          </div>

          <div className="reports-stats-grid">
            {indicadores.map((item) => {
              const Icono = item.icono;

              return (
                <article className="reports-stat-card" key={item.titulo}>
                  <div className={`reports-stat-icon ${item.clase}`}>
                    <Icono size={23} />
                  </div>

                  <div className="reports-stat-content">
                    <span>{item.titulo}</span>

                    <strong>
                      {cargando ? "..." : item.valor}
                    </strong>
                  </div>
                </article>
              );
            })}
          </div>
        </section>

        {/* VALORES COMERCIALES */}
        <section className="reports-financial-grid">
          <article className="reports-financial-card">
            <span>Valor total de pedidos</span>

            <strong>
              {cargando ? "..." : formatoPrecio(resumen.valor_pedidos)}
            </strong>

            <small>Incluye todos los pedidos registrados.</small>
          </article>

          <article className="reports-financial-card reports-financial-highlight">
            <span>Valor de pedidos no cancelados</span>

            <strong>
              {cargando
                ? "..."
                : formatoPrecio(resumen.valor_pedidos_no_cancelados)}
            </strong>

            <small>Excluye los pedidos cancelados.</small>
          </article>
        </section>

        {/* ESTADOS DE PEDIDOS */}
        <section className="reports-section">
          <div className="reports-section-heading">
            <div>
              <h2>Estado de los pedidos</h2>
              <p>Distribución de pedidos según su estado actual.</p>
            </div>
          </div>

          <div className="reports-status-grid">
            {estados.map((estado) => {
              const Icono = estado.icono;

              return (
                <article className="reports-status-card" key={estado.nombre}>
                  <div className={`reports-status-icon ${estado.clase}`}>
                    <Icono size={19} />
                  </div>

                  <div>
                    <span>{estado.nombre}</span>
                    <strong>{cargando ? "..." : estado.valor}</strong>
                  </div>
                </article>
              );
            })}
          </div>
        </section>

        {/* PRODUCTOS */}
        <section className="reports-table-card">
          <div className="reports-table-heading">
            <div className="reports-table-icon">
              <Package size={21} />
            </div>

            <div>
              <h2>Productos más solicitados</h2>
              <p>Los productos con mayor cantidad de unidades solicitadas.</p>
            </div>
          </div>

          {cargando ? (
            <div className="reports-empty">Cargando productos...</div>
          ) : !datos?.productos?.length ? (
            <div className="reports-empty">
              No hay productos registrados en este periodo.
            </div>
          ) : (
            <div className="reports-table-wrapper">
              <table className="reports-table">
                <thead>
                  <tr>
                    <th>Producto</th>
                    <th>Unidades</th>
                    <th>Pedidos</th>
                    <th>Valor solicitado</th>
                  </tr>
                </thead>

                <tbody>
                  {datos.productos.map((producto, index) => (
                    <tr key={`${producto.nombre_producto}-${index}`}>
                      <td className="reports-primary-cell">
                        {producto.nombre_producto}
                      </td>

                      <td>{Number(producto.total_unidades)}</td>

                      <td>{Number(producto.total_pedidos)}</td>

                      <td className="reports-price-cell">
                        {formatoPrecio(producto.valor_solicitado)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>

        {/* CLIENTES */}
        <section className="reports-table-card">
          <div className="reports-table-heading">
            <div className="reports-table-icon reports-icon-blue">
              <Users size={21} />
            </div>

            <div>
              <h2>Clientes principales</h2>
              <p>Clientes con mayor número de pedidos en el periodo.</p>
            </div>
          </div>

          {cargando ? (
            <div className="reports-empty">Cargando clientes...</div>
          ) : !datos?.clientes?.length ? (
            <div className="reports-empty">
              No hay clientes registrados en este periodo.
            </div>
          ) : (
            <div className="reports-table-wrapper">
              <table className="reports-table">
                <thead>
                  <tr>
                    <th>Cliente</th>
                    <th>Teléfono</th>
                    <th>Ciudad</th>
                    <th>Pedidos</th>
                    <th>Valor comprado</th>
                    <th>Último pedido</th>
                  </tr>
                </thead>

                <tbody>
                  {datos.clientes.map((cliente, index) => (
                    <tr key={`${cliente.telefono}-${index}`}>
                      <td className="reports-primary-cell">
                        {cliente.nombre_cliente}
                      </td>

                      <td>{cliente.telefono}</td>

                      <td>{cliente.ciudad}</td>

                      <td>{Number(cliente.total_pedidos)}</td>

                      <td className="reports-price-cell">
                        {formatoPrecio(cliente.valor_pedidos)}
                      </td>

                      <td>{formatoFecha(cliente.ultimo_pedido)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>

        {/* CIUDADES */}
        <section className="reports-table-card">
          <div className="reports-table-heading">
            <div className="reports-table-icon reports-icon-orange">
              <MapPin size={21} />
            </div>

            <div>
              <h2>Pedidos por ciudad</h2>
              <p>Distribución geográfica de los pedidos mayoristas.</p>
            </div>
          </div>

          {cargando ? (
            <div className="reports-empty">Cargando ciudades...</div>
          ) : !datos?.ciudades?.length ? (
            <div className="reports-empty">
              No hay ciudades registradas en este periodo.
            </div>
          ) : (
            <div className="reports-table-wrapper">
              <table className="reports-table">
                <thead>
                  <tr>
                    <th>Ciudad</th>
                    <th>Total de pedidos</th>
                    <th>Valor de pedidos</th>
                  </tr>
                </thead>

                <tbody>
                  {datos.ciudades.map((ciudad, index) => (
                    <tr key={`${ciudad.ciudad}-${index}`}>
                      <td className="reports-primary-cell">
                        {ciudad.ciudad}
                      </td>

                      <td>{Number(ciudad.total_pedidos)}</td>

                      <td className="reports-price-cell">
                        {formatoPrecio(ciudad.valor_pedidos)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>

      </div>
    </AdminLayout>
  );
}

export default Reports;
