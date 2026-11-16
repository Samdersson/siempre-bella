
import { useEffect, useRef, useState } from "react";
import {
  Images,
  Upload,
  Search,
  ImagePlus,
  Eye,
  Power,
  X,
  FileImage,
  HardDrive,
  CheckCircle,
  Image as ImageIcon,
  RefreshCw,
  ChevronLeft,
  ChevronRight
} from "lucide-react";

import AdminLayout from "../../components/layout/AdminLayout.jsx";
import "../../styles/media-library.css";

const API = "/api/multimedia";

function MediaLibrary() {
  const [recursos, setRecursos] = useState([]);
  const [cargando, setCargando] = useState(true);
  const [subiendo, setSubiendo] = useState(false);

  const [buscar, setBuscar] = useState("");
  const [filtro, setFiltro] = useState("todos");

  const [pagina, setPagina] = useState(1);
  const [total, setTotal] = useState(0);
  const [totalPaginas, setTotalPaginas] = useState(1);

  const [modalVista, setModalVista] = useState(null);
  const [archivo, setArchivo] = useState(null);
  const [textoAlternativo, setTextoAlternativo] = useState("");

  const [mensaje, setMensaje] = useState(null);

  const inputArchivo = useRef(null);

  const LIMITE = 12;

  // Consultar biblioteca multimedia
  const cargarRecursos = async (paginaActual = pagina) => {
    setCargando(true);

    try {
      const parametros = new URLSearchParams({
        buscar: buscar.trim(),
        pagina: paginaActual,
        limite: LIMITE
      });

      const respuesta = await fetch(
        `${API}/listar.php?${parametros.toString()}`,
        {
          method: "GET",
          credentials: "include"
        }
      );

      const texto = await respuesta.text();

      let json;

      try {
        json = JSON.parse(texto);
      } catch {
        throw new Error("El servidor devolvió una respuesta no válida.");
      }

      if (!respuesta.ok || !json.success) {
        throw new Error(json.message || "No fue posible consultar los recursos.");
      }

      setRecursos(json.data || []);
      setTotal(Number(json.total) || 0);
      setTotalPaginas(Math.max(1, Number(json.total_paginas) || 1));

    } catch (error) {
      setMensaje({
        tipo: "error",
        texto: error.message
      });
    } finally {
      setCargando(false);
    }
  };

  useEffect(() => {
    cargarRecursos(pagina);
  }, [pagina, buscar]);

  // Mostrar mensajes temporales
  const notificar = (tipo, texto) => {
    setMensaje({ tipo, texto });
  };

  // Seleccionar archivo
  const seleccionarArchivo = (e) => {
    const seleccionado = e.target.files?.[0];

    if (!seleccionado) return;

    const tipos = ["image/jpeg", "image/png", "image/webp"];

    if (!tipos.includes(seleccionado.type)) {
      notificar("error", "Formato no permitido. Utiliza JPG, PNG o WEBP.");
      e.target.value = "";
      return;
    }

    if (seleccionado.size > 5 * 1024 * 1024) {
      notificar("error", "La imagen no puede superar los 5 MB.");
      e.target.value = "";
      return;
    }

    setArchivo(seleccionado);
    setTextoAlternativo("");
    setModalVista({ tipo: "subir" });
  };

  // Subir imagen
  const subirImagen = async (e) => {
    e.preventDefault();

    if (!archivo) return;

    setSubiendo(true);

    try {
      const datos = new FormData();

      datos.append("archivo", archivo);
      datos.append("texto_alternativo", textoAlternativo);

      const respuesta = await fetch(`${API}/subir.php`, {
        method: "POST",
        credentials: "include",
        body: datos
      });

      const texto = await respuesta.text();

      let json;

      try {
        json = JSON.parse(texto);
      } catch {
        throw new Error("El servidor devolvió una respuesta no válida.");
      }

      if (!respuesta.ok || !json.success) {
        throw new Error(json.message || "No fue posible subir la imagen.");
      }

      setModalVista(null);
      setArchivo(null);
      setTextoAlternativo("");

      if (inputArchivo.current) {
        inputArchivo.current.value = "";
      }

      setPagina(1);
      notificar("success", "Imagen cargada correctamente.");

      // Consultar nuevamente la primera página
      await cargarRecursos(1);

    } catch (error) {
      notificar("error", error.message);
    } finally {
      setSubiendo(false);
    }
  };

  // Activar o desactivar recurso
  const cambiarEstado = async (recurso) => {
    const nuevoEstado = Number(recurso.activo) === 1 ? 0 : 1;

    const confirmacion = window.confirm(
      nuevoEstado === 0
        ? `¿Deseas desactivar "${recurso.nombre_original}"?`
        : `¿Deseas activar "${recurso.nombre_original}"?`
    );

    if (!confirmacion) return;

    try {
      const respuesta = await fetch(`${API}/cambiar_estado.php`, {
        method: "POST",
        credentials: "include",
        headers: {
          "Content-Type": "application/json"
        },
        body: JSON.stringify({
          id: recurso.id,
          activo: nuevoEstado
        })
      });

      const texto = await respuesta.text();

      let json;

      try {
        json = JSON.parse(texto);
      } catch {
        throw new Error("El servidor devolvió una respuesta no válida.");
      }

      if (!respuesta.ok || !json.success) {
        throw new Error(json.message || "No fue posible cambiar el estado.");
      }

      notificar(
        "success",
        nuevoEstado === 1
          ? "Recurso activado correctamente."
          : "Recurso desactivado correctamente."
      );

      await cargarRecursos();

    } catch (error) {
      notificar("error", error.message);
    }
  };

  // Estadísticas
  const activos = recursos.filter(
    (recurso) => Number(recurso.activo) === 1
  ).length;

  const inactivos = recursos.filter(
    (recurso) => Number(recurso.activo) === 0
  ).length;

  const recursosFiltrados = recursos.filter((recurso) => {
    if (filtro === "activos") return Number(recurso.activo) === 1;
    if (filtro === "inactivos") return Number(recurso.activo) === 0;
    return true;
  });

  // Formatear peso
  const formatoPeso = (bytes) => {
    const valor = Number(bytes) || 0;

    if (valor < 1024) return `${valor} B`;

    if (valor < 1024 * 1024) {
      return `${(valor / 1024).toFixed(1)} KB`;
    }

    return `${(valor / (1024 * 1024)).toFixed(2)} MB`;
  };

  // Formatear fecha
  const formatoFecha = (fecha) => {
    if (!fecha) return "Sin fecha";

    const fechaLocal = new Date(fecha.replace(" ", "T"));

    if (Number.isNaN(fechaLocal.getTime())) return fecha;

    return fechaLocal.toLocaleDateString("es-CO", {
      day: "2-digit",
      month: "short",
      year: "numeric"
    });
  };

  return (
    <AdminLayout>
      <div className="media-library">

        {/* Encabezado */}
        <div className="media-header">
          <div>
            <h1>Biblioteca multimedia</h1>
            <p>Administra las imágenes y recursos visuales de Siempre Bella.</p>
          </div>

          <button
            className="media-upload-button"
            onClick={() => inputArchivo.current?.click()}
          >
            <Upload size={18} />
            Subir imagen
          </button>

          <input
            ref={inputArchivo}
            type="file"
            accept="image/jpeg,image/png,image/webp"
            hidden
            onChange={seleccionarArchivo}
          />
        </div>

        {/* Mensajes */}
        {mensaje && (
          <div className={`media-message ${mensaje.tipo}`}>
            <span>{mensaje.texto}</span>
            <button onClick={() => setMensaje(null)} aria-label="Cerrar mensaje">
              <X size={17} />
            </button>
          </div>
        )}

        {/* Estadísticas */}
        <div className="media-stats">

          <div className="media-stat-card">
            <div className="media-stat-icon pink">
              <Images size={21} />
            </div>
            <div>
              <span>Recursos encontrados</span>
              <strong>{total}</strong>
            </div>
          </div>

          <div className="media-stat-card">
            <div className="media-stat-icon green">
              <CheckCircle size={21} />
            </div>
            <div>
              <span>Activos en esta página</span>
              <strong>{activos}</strong>
            </div>
          </div>

          <div className="media-stat-card">
            <div className="media-stat-icon gray">
              <FileImage size={21} />
            </div>
            <div>
              <span>Inactivos en esta página</span>
              <strong>{inactivos}</strong>
            </div>
          </div>

          <div className="media-stat-card">
            <div className="media-stat-icon purple">
              <HardDrive size={21} />
            </div>
            <div>
              <span>Espacio de esta página</span>
              <strong>
                {formatoPeso(
                  recursos.reduce(
                    (acumulado, recurso) =>
                      acumulado + Number(recurso.tamanio_bytes || 0),
                    0
                  )
                )}
              </strong>
            </div>
          </div>

        </div>

        {/* Herramientas */}
        <div className="media-toolbar">

          <div className="media-search">
            <Search size={18} />
            <input
              type="search"
              placeholder="Buscar por nombre o descripción..."
              value={buscar}
              onChange={(e) => {
                setBuscar(e.target.value);
                setPagina(1);
              }}
            />
          </div>

          <div className="media-filters">
            <button
              className={filtro === "todos" ? "active" : ""}
              onClick={() => setFiltro("todos")}
            >
              Todos
            </button>

            <button
              className={filtro === "activos" ? "active" : ""}
              onClick={() => setFiltro("activos")}
            >
              Activos
            </button>

            <button
              className={filtro === "inactivos" ? "active" : ""}
              onClick={() => setFiltro("inactivos")}
            >
              Inactivos
            </button>
          </div>

          <button
            className="media-refresh"
            onClick={() => cargarRecursos()}
            title="Actualizar biblioteca"
          >
            <RefreshCw size={18} />
          </button>

        </div>

        {/* Galería */}
        {cargando ? (

          <div className="media-loading">
            <RefreshCw size={25} className="media-spin" />
            <p>Cargando biblioteca multimedia...</p>
          </div>

        ) : recursosFiltrados.length === 0 ? (

          <div className="media-empty">
            <ImagePlus size={45} />
            <h3>No hay imágenes para mostrar</h3>
            <p>
              {buscar
                ? "No encontramos recursos con esa búsqueda."
                : "Comienza cargando tu primera imagen."}
            </p>

            {!buscar && (
              <button onClick={() => inputArchivo.current?.click()}>
                <Upload size={17} />
                Subir primera imagen
              </button>
            )}
          </div>

        ) : (

          <div className="media-grid">

            {recursosFiltrados.map((recurso) => (

              <article className="media-card" key={recurso.id}>

                <div className="media-image-container">

                  <img
                    src={recurso.url}
                    alt={recurso.texto_alternativo || recurso.nombre_original}
                    loading="lazy"
                  />

                  <span
                    className={`media-status ${
                      Number(recurso.activo) === 1 ? "enabled" : "disabled"
                    }`}
                  >
                    {Number(recurso.activo) === 1 ? "Activo" : "Inactivo"}
                  </span>

                  <button
                    className="media-preview-button"
                    onClick={() => setModalVista({
                      tipo: "detalle",
                      recurso
                    })}
                    title="Ver imagen"
                  >
                    <Eye size={19} />
                  </button>

                </div>

                <div className="media-card-info">

                  <h3 title={recurso.nombre_original}>
                    {recurso.nombre_original}
                  </h3>

                  <p>
                    {recurso.extension.toUpperCase()} ·{" "}
                    {formatoPeso(recurso.tamanio_bytes)}
                  </p>

                  <small>
                    {formatoFecha(recurso.fecha_creacion)}
                  </small>

                  <div className="media-card-actions">

                    <button
                      className="media-action-preview"
                      onClick={() => setModalVista({
                        tipo: "detalle",
                        recurso
                      })}
                    >
                      <Eye size={15} />
                      Ver
                    </button>

                    <button
                      className={
                        Number(recurso.activo) === 1
                          ? "media-action-disable"
                          : "media-action-enable"
                      }
                      onClick={() => cambiarEstado(recurso)}
                    >
                      <Power size={15} />
                      {Number(recurso.activo) === 1
                        ? "Desactivar"
                        : "Activar"}
                    </button>

                  </div>

                </div>

              </article>

            ))}

          </div>

        )}

        {/* Paginación */}
        {!cargando && totalPaginas > 1 && (
          <div className="media-pagination">

            <button
              disabled={pagina <= 1}
              onClick={() => setPagina((actual) => actual - 1)}
            >
              <ChevronLeft size={18} />
              Anterior
            </button>

            <span>
              Página {pagina} de {totalPaginas}
            </span>

            <button
              disabled={pagina >= totalPaginas}
              onClick={() => setPagina((actual) => actual + 1)}
            >
              Siguiente
              <ChevronRight size={18} />
            </button>

          </div>
        )}

      </div>

      {/* Modal de carga y vista previa */}
      {modalVista && (

        <div
          className="media-modal-overlay"
          onMouseDown={(e) => {
            if (e.target === e.currentTarget && !subiendo) {
              setModalVista(null);
            }
          }}
        >

          <div className="media-modal">

            <div className="media-modal-header">
              <h2>
                {modalVista.tipo === "subir"
                  ? "Subir nueva imagen"
                  : "Detalles del recurso"}
              </h2>

              <button
                onClick={() => setModalVista(null)}
                disabled={subiendo}
                aria-label="Cerrar ventana"
              >
                <X size={21} />
              </button>
            </div>

            {modalVista.tipo === "subir" ? (

              <form onSubmit={subirImagen}>

                {archivo && (
                  <div className="media-upload-preview">
                    <img
                      src={URL.createObjectURL(archivo)}
                      alt="Vista previa de la imagen"
                    />

                    <div>
                      <strong>{archivo.name}</strong>
                      <span>{formatoPeso(archivo.size)}</span>
                    </div>
                  </div>
                )}

                <label htmlFor="textoAlternativo">
                  Texto alternativo
                </label>

                <input
                  id="textoAlternativo"
                  type="text"
                  maxLength={255}
                  value={textoAlternativo}
                  onChange={(e) => setTextoAlternativo(e.target.value)}
                  placeholder="Describe brevemente la imagen"
                />

                <p className="media-help">
                  JPG, PNG o WEBP. Máximo 5 MB. Se optimizará automáticamente.
                </p>

                <button
                  className="media-modal-submit"
                  type="submit"
                  disabled={subiendo}
                >
                  {subiendo ? "Procesando imagen..." : "Guardar imagen"}
                </button>

              </form>

            ) : (

              <div className="media-detail">

                <img
                  src={modalVista.recurso.url}
                  alt={
                    modalVista.recurso.texto_alternativo ||
                    modalVista.recurso.nombre_original
                  }
                />

                <div className="media-detail-data">
                  <p>
                    <strong>Nombre:</strong>{" "}
                    {modalVista.recurso.nombre_original}
                  </p>

                  <p>
                    <strong>Formato:</strong>{" "}
                    {modalVista.recurso.extension.toUpperCase()}
                  </p>

                  <p>
                    <strong>Tamaño:</strong>{" "}
                    {formatoPeso(modalVista.recurso.tamanio_bytes)}
                  </p>

                  <p>
                    <strong>Descripción:</strong>{" "}
                    {modalVista.recurso.texto_alternativo || "Sin descripción"}
                  </p>

                  <p>
                    <strong>Fecha:</strong>{" "}
                    {formatoFecha(modalVista.recurso.fecha_creacion)}
                  </p>

                  <p>
                    <strong>Estado:</strong>{" "}
                    {Number(modalVista.recurso.activo) === 1
                      ? "Activo"
                      : "Inactivo"}
                  </p>

                  <button
                    className="media-copy-url"
                    onClick={async () => {
                      try {
                        await navigator.clipboard.writeText(
                          modalVista.recurso.url
                        );
                        notificar("success", "Ruta copiada correctamente.");
                      } catch {
                        notificar("error", "No fue posible copiar la ruta.");
                      }
                    }}
                  >
                    Copiar ruta de imagen
                  </button>
                </div>

              </div>

            )}

          </div>

        </div>

      )}

    </AdminLayout>
  );
}

export default MediaLibrary;
