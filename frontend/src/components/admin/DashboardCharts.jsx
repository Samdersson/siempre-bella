
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  ArcElement,
  Title,
  Tooltip,
  Legend
} from "chart.js";

import { Bar, Doughnut } from "react-chartjs-2";

import "../../styles/dashboard-charts.css";

ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  ArcElement,
  Title,
  Tooltip,
  Legend
);

function DashboardCharts({ estadisticas }) {
  const mensual = estadisticas?.pedidos_mensuales || [];
  const estados = estadisticas?.pedidos_por_estado || [];
  const productos = estadisticas?.productos_mas_solicitados || [];
  const ciudades = estadisticas?.pedidos_por_ciudad || [];

  // Construir los últimos seis meses, incluyendo meses sin pedidos
  const nombresMeses = [
    "Ene", "Feb", "Mar", "Abr", "May", "Jun",
    "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"
  ];

  const ahora = new Date();

  const ultimosSeisMeses = Array.from({ length: 6 }, (_, indice) => {
    const fecha = new Date(
      ahora.getFullYear(),
      ahora.getMonth() - 5 + indice,
      1
    );

    const periodo =
      `${fecha.getFullYear()}-${String(fecha.getMonth() + 1).padStart(2, "0")}`;

    const registro = mensual.find(
      (item) => item.periodo === periodo
    );

    return {
      periodo,
      etiqueta: `${nombresMeses[fecha.getMonth()]} ${fecha.getFullYear()}`,
      pedidos: Number(registro?.total_pedidos || 0)
    };
  });

  // Gráfico de pedidos mensuales
  const datosMensuales = {
    labels: ultimosSeisMeses.map((item) => item.etiqueta),
    datasets: [
      {
        label: "Pedidos registrados",
        data: ultimosSeisMeses.map((item) => item.pedidos),
        backgroundColor: "#d94c91",
        borderRadius: 7,
        maxBarThickness: 55
      }
    ]
  };

  // Gráfico de pedidos por estado
  const nombresEstados = {
    pendiente: "Pendientes",
    contactado: "Contactados",
    confirmado: "Confirmados",
    preparando: "Preparando",
    enviado: "Enviados",
    cancelado: "Cancelados"
  };

  const datosEstados = {
    labels: estados.map(
      (item) => nombresEstados[item.estado] || item.estado
    ),
    datasets: [
      {
        data: estados.map((item) => Number(item.total)),
        backgroundColor: [
          "#f3a64b",
          "#58a2d8",
          "#45ae82",
          "#9974c9",
          "#5d9caa",
          "#df6b72"
        ],
        borderWidth: 2,
        borderColor: "#ffffff"
      }
    ]
  };

  // Gráfico de productos más solicitados
  const datosProductos = {
    labels: productos.map((item) => item.nombre_producto),
    datasets: [
      {
        label: "Unidades solicitadas",
        data: productos.map((item) => Number(item.total_unidades)),
        backgroundColor: "#8e659e",
        borderRadius: 7,
        maxBarThickness: 45
      }
    ]
  };

  // Gráfico de pedidos por ciudad
  const datosCiudades = {
    labels: ciudades.map((item) => item.ciudad),
    datasets: [
      {
        label: "Pedidos registrados",
        data: ciudades.map((item) => Number(item.total_pedidos)),
        backgroundColor: "#58a2d8",
        borderRadius: 7,
        maxBarThickness: 38
      }
    ]
  };

  // Opciones generales para gráficos de barras
  const opcionesBarras = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: false
      },
      tooltip: {
        backgroundColor: "#392b3c",
        padding: 12
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        ticks: {
          precision: 0
        },
        grid: {
          color: "#f1eaf0"
        }
      },
      x: {
        grid: {
          display: false
        }
      }
    }
  };

  // Opciones del gráfico circular
  const opcionesDona = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: "bottom",
        labels: {
          usePointStyle: true,
          padding: 18
        }
      }
    },
    cutout: "65%"
  };

  // Opciones para gráficos horizontales
  const opcionesHorizontales = {
    ...opcionesBarras,
    indexAxis: "y",
    scales: {
      x: {
        beginAtZero: true,
        ticks: {
          precision: 0
        },
        grid: {
          color: "#f1eaf0"
        }
      },
      y: {
        grid: {
          display: false
        }
      }
    }
  };

  return (
    <section className="dashboard-charts">

      {/* PEDIDOS POR MES */}
      <div className="dashboard-chart-card">
        <div className="dashboard-chart-heading">
          <div>
            <h2>Pedidos por mes</h2>
            <p>Últimos seis meses de actividad comercial.</p>
          </div>
        </div>

        <div className="dashboard-chart-canvas">
          <Bar
            data={datosMensuales}
            options={opcionesBarras}
          />
        </div>
      </div>

      {/* PEDIDOS POR ESTADO */}
      <div className="dashboard-chart-card">
        <div className="dashboard-chart-heading">
          <div>
            <h2>Pedidos por estado</h2>
            <p>Distribución actual de los pedidos.</p>
          </div>
        </div>

        <div className="dashboard-chart-canvas dashboard-doughnut">
          {estados.length > 0 ? (
            <Doughnut
              data={datosEstados}
              options={opcionesDona}
            />
          ) : (
            <div className="dashboard-chart-empty">
              No hay pedidos para mostrar.
            </div>
          )}
        </div>
      </div>

      {/* PRODUCTOS MÁS SOLICITADOS */}
      <div className="dashboard-chart-card dashboard-chart-products">
        <div className="dashboard-chart-heading">
          <div>
            <h2>Productos más solicitados</h2>
            <p>Productos con mayor cantidad de unidades solicitadas.</p>
          </div>
        </div>

        <div className="dashboard-chart-canvas">
          {productos.length > 0 ? (
            <Bar
              data={datosProductos}
              options={opcionesHorizontales}
            />
          ) : (
            <div className="dashboard-chart-empty">
              Todavía no hay productos solicitados.
            </div>
          )}
        </div>
      </div>

      {/* PEDIDOS POR CIUDAD */}
      <div className="dashboard-chart-card dashboard-chart-products">
        <div className="dashboard-chart-heading">
          <div>
            <h2>Pedidos por ciudad</h2>
            <p>Ciudades con mayor cantidad de pedidos registrados.</p>
          </div>
        </div>

        <div className="dashboard-chart-canvas">
          {ciudades.length > 0 ? (
            <Bar
              data={datosCiudades}
              options={opcionesHorizontales}
            />
          ) : (
            <div className="dashboard-chart-empty">
              Todavía no hay ciudades registradas.
            </div>
          )}
        </div>
      </div>

    </section>
  );
}

export default DashboardCharts;
