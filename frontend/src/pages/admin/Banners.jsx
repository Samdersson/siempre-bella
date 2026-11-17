
import { useEffect, useState } from "react";
import {
  ImagePlus,
  Plus,
  Search,
  RefreshCw,
  Pencil,
  Trash2,
  Power,
  X,
  Monitor,
  Smartphone,
  CalendarDays,
  Images
} from "lucide-react";

import AdminLayout from "../../components/layout/AdminLayout.jsx";
import "../../styles/banners.css";
import { obtenerHeadersCSRF } from "../../services/csrf.js";

const API = "/api/banners";
const API_MULTIMEDIA = "/api/multimedia";

const FORMULARIO_INICIAL = {
  nombre: "",
  titulo: "",
  subtitulo: "",
  imagen_id: "",
  imagen_movil_id: "",
  texto_boton: "",
  enlace_boton: "",
  posicion: "principal",
  orden: 0,
  fecha_inicio: "",
  fecha_fin: ""
};

function Banners() {
  const [banners, setBanners] = useState([]);
  const [cargando, setCargando] = useState(true);
  const [guardando, setGuardando] = useState(false);

  const [modal, setModal] = useState(false);
  const [modalImagen, setModalImagen] = useState(null);

  const [formulario, setFormulario] = useState(FORMULARIO_INICIAL);
  const [bannerEditar, setBannerEditar] = useState(null);

  const [recursos, setRecursos] = useState([]);
  const [cargandoRecursos, setCargandoRecursos] = useState(false);

  const [buscar, setBuscar] = useState("");
  const [filtroPosicion, setFiltroPosicion] = useState("todos");
  const [mensaje, setMensaje] = useState(null);

  // Consultar banners
  const cargarBanners = async () => {
    setCargando(true);

    try {
      const respuesta = await fetch(`${API}/listar_admin.php`, {
        credentials: "include"
      });

      const json = await respuesta.json();

      if (!respuesta.ok || !json.success) {
        throw new Error(json.message || "No fue posible cargar los banners.");
      }

      setBanners(json.data || []);
    } catch (error) {
      notificar("error", error.message);
    } finally {
      setCargando(false);
    }
  };

  useEffect(() => {
    cargarBanners();
  }, []);

  const notificar = (tipo, texto) => {
    setMensaje({ tipo, texto });
  };

  const actualizarCampo = (e) => {
    const { name, value } = e.target;

    setFormulario((actual) => ({
      ...actual,
      [name]: value
    }));
  };

  // Consultar biblioteca multimedia
  const cargarRecursos = async () => {
    setCargandoRecursos(true);

    try {
      const parametros = new URLSearchParams({
        pagina: "1",
        limite: "100"
      });

      const respuesta = await fetch(
        `${API_MULTIMEDIA}/listar.php?${parametros}`,
        { credentials: "include" }
      );

      const json = await respuesta.json();

      if (!respuesta.ok || !json.success) {
        throw new Error(json.message || "No fue posible cargar las imágenes.");
      }

      setRecursos(
        (json.data || []).filter(
          (recurso) => Number(recurso.activo) === 1
        )
      );
    } catch (error) {
      notificar("error", error.message);
    } finally {
      setCargandoRecursos(false);
    }
  };

  // Abrir selector de imágenes
  const abrirSelector = async (campo) => {
    setModalImagen(campo);
    await cargarRecursos();
  };

  // Seleccionar imagen
  const seleccionarImagen = (recurso) => {
    setFormulario((actual) => ({
      ...actual,
      [modalImagen]: recurso.id
    }));

    setModalImagen(null);
  };

  // Obtener recurso seleccionado
  const obtenerRecurso = (id) => {
    return recursos.find((recurso) => Number(recurso.id) === Number(id));
  };

  // Fechas para el backend
  const convertirFecha = (fecha) => {
    if (!fecha) return null;
    return fecha.replace("T", " ") + ":00";
  };

  // Fechas recibidas desde MySQL
  const convertirFechaInput = (fecha) => {
    if (!fecha) return "";

    return fecha.replace(" ", "T").slice(0, 16);
  };

  // Abrir formulario para crear
  const nuevoBanner = () => {
    setBannerEditar(null);
    setFormulario({ ...FORMULARIO_INICIAL });
    setModal(true);
  };

  // Abrir formulario para editar
  const editarBanner = async (banner) => {
    setBannerEditar(banner);

    setFormulario({
      nombre: banner.nombre || "",
      titulo: banner.titulo || "",
      subtitulo: banner.subtitulo || "",
      imagen_id: banner.imagen_id || "",
      imagen_movil_id: banner.imagen_movil_id || "",
      texto_boton: banner.texto_boton || "",
      enlace_boton: banner.enlace_boton || "",
      posicion: banner.posicion || "principal",
      orden: banner.orden ?? 0,
      fecha_inicio: convertirFechaInput(banner.fecha_inicio),
      fecha_fin: convertirFechaInput(banner.fecha_fin)
    });

    setModal(true);
  };

  // Guardar banner
  const guardarBanner = async (e) => {
    e.preventDefault();

    if (!formulario.imagen_id) {
      notificar("error", "Selecciona una imagen principal.");
      return;
    }

    if (
      formulario.imagen_movil_id &&
      Number(formulario.imagen_movil_id) === Number(formulario.imagen_id)
    ) {
      notificar("error", "La imagen móvil debe ser diferente a la principal.");
      return;
    }

    setGuardando(true);

    try {
      const datos = {
        ...formulario,
        imagen_id: Number(formulario.imagen_id),
        imagen_movil_id: formulario.imagen_movil_id
          ? Number(formulario.imagen_movil_id)
          : null,
        orden: Number(formulario.orden),
        fecha_inicio: convertirFecha(formulario.fecha_inicio),
        fecha_fin: convertirFecha(formulario.fecha_fin)
      };

      const endpoint = bannerEditar
        ? `${API}/actualizar.php`
        : `${API}/crear.php`;

      if (bannerEditar) {
        datos.id = Number(bannerEditar.id);
      }


      const respuesta = await fetch(endpoint, {
        method: "POST",
        credentials: "include",
        headers: await obtenerHeadersCSRF({
          "Content-Type": "application/json"
        }),
        body: JSON.stringify(datos)
      });

      const json = await respuesta.json();

      if (!respuesta.ok || !json.success) {
        throw new Error(json.message || "No fue posible guardar el banner.");
      }

      setModal(false);
      setFormulario({ ...FORMULARIO_INICIAL });
      setBannerEditar(null);

      notificar(
        "success",
        bannerEditar
          ? "Banner actualizado correctamente."
          : "Banner creado correctamente."
      );

      await cargarBanners();
    } catch (error) {
      notificar("error", error.message);
    } finally {
      setGuardando(false);
    }
  };

  // Activar o desactivar
  const cambiarEstado = async (banner) => {
    const nuevoEstado = Number(banner.activo) === 1 ? 0 : 1;

    const confirmar = window.confirm(
      nuevoEstado === 1
        ? `¿Deseas activar "${banner.nombre}"?`
        : `¿Deseas desactivar "${banner.nombre}"?`
    );

    if (!confirmar) return;

    try {
      const respuesta = await fetch(`${API}/cambiar_estado.php`, {
        method: "POST",
        credentials: "include",
        headers: await obtenerHeadersCSRF({
          "Content-Type": "application/json"
        }),
        body: JSON.stringify({
          id: Number(banner.id),
          activo: nuevoEstado
        })
      });

      const json = await respuesta.json();

      if (!respuesta.ok || !json.success) {
        throw new Error(json.message || "No fue posible cambiar el estado.");
      }

      notificar("success", json.message);
      await cargarBanners();
    } catch (error) {
      notificar("error", error.message);
    }
  };

  // Eliminar banner
  const eliminarBanner = async (banner) => {
    const confirmar = window.confirm(
      `¿Estás seguro de eliminar "${banner.nombre}"? Esta acción no se puede deshacer.`
    );

    if (!confirmar) return;

    try {
      const respuesta = await fetch(`${API}/eliminar.php`, {
        method: "POST",
        credentials: "include",
        headers: await obtenerHeadersCSRF({
          "Content-Type": "application/json"
        }),
        body: JSON.stringify({
          id: Number(banner.id)
        })
      });

      const json = await respuesta.json();

      if (!respuesta.ok || !json.success) {
        throw new Error(json.message || "No fue posible eliminar el banner.");
      }

      notificar("success", json.message);
      await cargarBanners();
    } catch (error) {
      notificar("error", error.message);
    }
  };

  // Filtros
  const bannersFiltrados = banners.filter((banner) => {
    const coincideBusqueda =
      `${banner.nombre} ${banner.titulo || ""}`
        .toLowerCase()
        .includes(buscar.toLowerCase());

    const coincidePosicion =
      filtroPosicion === "todos" || banner.posicion === filtroPosicion;

    return coincideBusqueda && coincidePosicion;
  });

  const activos = banners.filter(
    (banner) => Number(banner.activo) === 1
  ).length;

  const recursoPrincipal = obtenerRecurso(formulario.imagen_id);
  const recursoMovil = obtenerRecurso(formulario.imagen_movil_id);

  return (
    <AdminLayout>
      <div className="banners-page">

        <div className="banners-header">
          <div>
            <h1>Banners y contenido</h1>
            <p>Administra los espacios visuales de tu tienda Siempre Bella.</p>
          </div>

          <button className="banners-primary-button" onClick={nuevoBanner}>
            <Plus size={19} />
            Crear banner
          </button>
        </div>

        {mensaje && (
          <div className={`banners-message ${mensaje.tipo}`}>
            <span>{mensaje.texto}</span>
            <button onClick={() => setMensaje(null)} aria-label="Cerrar">
              <X size={17} />
            </button>
          </div>
        )}

        <div className="banners-stats">
          <div className="banners-stat">
            <span>Total de banners</span>
            <strong>{banners.length}</strong>
          </div>

          <div className="banners-stat">
            <span>Banners activos</span>
            <strong>{activos}</strong>
          </div>

          <div className="banners-stat">
            <span>Banners inactivos</span>
            <strong>{banners.length - activos}</strong>
          </div>
        </div>

        <div className="banners-toolbar">
          <div className="banners-search">
            <Search size={18} />
            <input
              type="search"
              placeholder="Buscar banner..."
              value={buscar}
              onChange={(e) => setBuscar(e.target.value)}
            />
          </div>

          <select
            value={filtroPosicion}
            onChange={(e) => setFiltroPosicion(e.target.value)}
          >
            <option value="todos">Todas las posiciones</option>
            <option value="principal">Principal</option>
            <option value="secundario">Secundario</option>
            <option value="promocional">Promocional</option>
          </select>

          <button
            className="banners-refresh"
            onClick={cargarBanners}
            title="Actualizar"
          >
            <RefreshCw size={18} />
          </button>
        </div>

        {cargando ? (
          <div className="banners-empty">
            <RefreshCw className="banners-spin" size={27} />
            <p>Cargando banners...</p>
          </div>
        ) : bannersFiltrados.length === 0 ? (
          <div className="banners-empty">
            <ImagePlus size={43} />
            <h3>No hay banners para mostrar</h3>
            <p>Crea tu primer banner para comenzar a personalizar la tienda.</p>
            <button onClick={nuevoBanner}>
              <Plus size={17} />
              Crear banner
            </button>
          </div>
        ) : (
          <div className="banners-grid">
            {bannersFiltrados.map((banner) => (
              <article className="banner-card" key={banner.id}>
                <div className="banner-card-image">
                  <img
                    src={banner.imagen_url}
                    alt={banner.titulo || banner.nombre}
                  />

                  <span
                    className={`banner-status ${
                      Number(banner.activo) === 1 ? "active" : "inactive"
                    }`}
                  >
                    {Number(banner.activo) === 1 ? "Activo" : "Inactivo"}
                  </span>

                  <span className="banner-position">
                    {banner.posicion}
                  </span>
                </div>

                <div className="banner-card-content">
                  <h3>{banner.nombre}</h3>
                  <p>{banner.titulo || "Sin título"}</p>

                  {banner.imagen_movil_url && (
                    <span className="banner-mobile-label">
                      <Smartphone size={14} />
                      Tiene imagen móvil
                    </span>
                  )}

                  <div className="banner-card-order">
                    <span>Orden: {banner.orden}</span>
                    <span>ID: {banner.id}</span>
                  </div>

                  <div className="banner-card-actions">
                    <button
                      className="banner-edit-button"
                      onClick={() => editarBanner(banner)}
                    >
                      <Pencil size={15} />
                      Editar
                    </button>

                    <button
                      className="banner-state-button"
                      onClick={() => cambiarEstado(banner)}
                      title="Cambiar estado"
                    >
                      <Power size={16} />
                    </button>

                    <button
                      className="banner-delete-button"
                      onClick={() => eliminarBanner(banner)}
                      title="Eliminar banner"
                    >
                      <Trash2 size={16} />
                    </button>
                  </div>
                </div>
              </article>
            ))}
          </div>
        )}
      </div>

      {/* Modal crear / editar */}
      {modal && (
        <div className="banners-modal-overlay">
          <div className="banners-modal">
            <div className="banners-modal-header">
              <div>
                <h2>{bannerEditar ? "Editar banner" : "Crear banner"}</h2>
                <p>Configura el contenido visual de tu tienda.</p>
              </div>

              <button
                onClick={() => setModal(false)}
                disabled={guardando}
                aria-label="Cerrar"
              >
                <X size={21} />
              </button>
            </div>

            <form onSubmit={guardarBanner}>
              <div className="banners-form-grid">

                <div className="banners-field full">
                  <label>Nombre administrativo *</label>
                  <input
                    name="nombre"
                    value={formulario.nombre}
                    onChange={actualizarCampo}
                    maxLength={150}
                    required
                    placeholder="Ej. Banner principal de temporada"
                  />
                </div>

                <div className="banners-field full">
                  <label>Título visible</label>
                  <input
                    name="titulo"
                    value={formulario.titulo}
                    onChange={actualizarCampo}
                    maxLength={200}
                    placeholder="Título del banner"
                  />
                </div>

                <div className="banners-field full">
                  <label>Subtítulo</label>
                  <textarea
                    name="subtitulo"
                    value={formulario.subtitulo}
                    onChange={actualizarCampo}
                    maxLength={500}
                    rows={3}
                    placeholder="Descripción o mensaje complementario"
                  />
                </div>

                <div className="banners-field full">
                  <label>Imagen principal *</label>

                  <button
                    type="button"
                    className="banner-image-selector"
                    onClick={() => abrirSelector("imagen_id")}
                  >
                    {bannerEditar && !recursoPrincipal ? (
                      <img src={bannerEditar.imagen_url} alt="" />
                    ) : recursoPrincipal ? (
                      <img src={recursoPrincipal.url} alt="" />
                    ) : (
                      <ImagePlus size={25} />
                    )}

                    <span>
                      {formulario.imagen_id
                        ? "Cambiar imagen principal"
                        : "Seleccionar imagen principal"}
                    </span>
                  </button>
                </div>

                <div className="banners-field full">
                  <label>Imagen móvil (opcional)</label>

                  <button
                    type="button"
                    className="banner-image-selector mobile"
                    onClick={() => abrirSelector("imagen_movil_id")}
                  >
                    {bannerEditar &&
                    formulario.imagen_movil_id &&
                    !recursoMovil ? (
                      <img
                        src={bannerEditar.imagen_movil_url}
                        alt=""
                      />
                    ) : recursoMovil ? (
                      <img src={recursoMovil.url} alt="" />
                    ) : (
                      <Smartphone size={25} />
                    )}

                    <span>
                      {formulario.imagen_movil_id
                        ? "Cambiar imagen móvil"
                        : "Seleccionar imagen móvil"}
                    </span>
                  </button>

                  {formulario.imagen_movil_id && (
                    <button
                      type="button"
                      className="banner-remove-mobile"
                      onClick={() =>
                        setFormulario((actual) => ({
                          ...actual,
                          imagen_movil_id: ""
                        }))
                      }
                    >
                      Quitar imagen móvil
                    </button>
                  )}
                </div>

                <div className="banners-field">
                  <label>Texto del botón</label>
                  <input
                    name="texto_boton"
                    value={formulario.texto_boton}
                    onChange={actualizarCampo}
                    maxLength={100}
                    placeholder="Comprar ahora"
                  />
                </div>

                <div className="banners-field">
                  <label>Enlace del botón</label>
                  <input
                    name="enlace_boton"
                    value={formulario.enlace_boton}
                    onChange={actualizarCampo}
                    maxLength={500}
                    placeholder="/categoria/cosmeticos"
                  />
                </div>

                <div className="banners-field">
                  <label>Posición *</label>
                  <select
                    name="posicion"
                    value={formulario.posicion}
                    onChange={actualizarCampo}
                  >
                    <option value="principal">Principal</option>
                    <option value="secundario">Secundario</option>
                    <option value="promocional">Promocional</option>
                  </select>
                </div>

                <div className="banners-field">
                  <label>Orden</label>
                  <input
                    type="number"
                    name="orden"
                    min="0"
                    value={formulario.orden}
                    onChange={actualizarCampo}
                  />
                </div>

                <div className="banners-field">
                  <label>
                    <CalendarDays size={15} /> Fecha de inicio
                  </label>
                  <input
                    type="datetime-local"
                    name="fecha_inicio"
                    value={formulario.fecha_inicio}
                    onChange={actualizarCampo}
                  />
                </div>

                <div className="banners-field">
                  <label>
                    <CalendarDays size={15} /> Fecha de finalización
                  </label>
                  <input
                    type="datetime-local"
                    name="fecha_fin"
                    value={formulario.fecha_fin}
                    onChange={actualizarCampo}
                  />
                </div>
              </div>

              <div className="banners-modal-footer">
                <button
                  type="button"
                  className="banners-cancel-button"
                  onClick={() => setModal(false)}
                  disabled={guardando}
                >
                  Cancelar
                </button>

                <button
                  type="submit"
                  className="banners-save-button"
                  disabled={guardando}
                >
                  {guardando
                    ? "Guardando..."
                    : bannerEditar
                      ? "Guardar cambios"
                      : "Crear banner"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Selector de biblioteca multimedia */}
      {modalImagen && (
        <div className="banners-modal-overlay">
          <div className="banners-image-modal">
            <div className="banners-modal-header">
              <div>
                <h2>Biblioteca multimedia</h2>
                <p>Selecciona una imagen activa.</p>
              </div>

              <button onClick={() => setModalImagen(null)} aria-label="Cerrar">
                <X size={21} />
              </button>
            </div>

            {cargandoRecursos ? (
              <div className="banners-empty">
                <RefreshCw className="banners-spin" size={25} />
                <p>Cargando imágenes...</p>
              </div>
            ) : recursos.length === 0 ? (
              <div className="banners-empty">
                <Images size={38} />
                <h3>No hay imágenes activas</h3>
                <p>Primero carga imágenes en la biblioteca multimedia.</p>
              </div>
            ) : (
              <div className="banner-image-grid">
                {recursos.map((recurso) => (
                  <button
                    type="button"
                    key={recurso.id}
                    className="banner-image-option"
                    onClick={() => seleccionarImagen(recurso)}
                  >
                    <img src={recurso.url} alt={recurso.texto_alternativo || ""} />
                    <span>{recurso.nombre_original}</span>
                  </button>
                ))}
              </div>
            )}
          </div>
        </div>
      )}
    </AdminLayout>
  );
}

export default Banners;
