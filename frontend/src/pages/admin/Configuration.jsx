import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import {
  Save,
  Plus,
  Pencil,
  Power,
  Trash2,
  X,
  Store,
  Share2,
  Globe,
} from "lucide-react";
import AdminLayout from "../../components/layout/AdminLayout.jsx";
import "../../styles/admin-products.css";
import "../../styles/admin-categories.css";
import "../../styles/admin-configuration.css";

const tiendaInicial = {
  nombre_tienda: "",
  whatsapp: "",
  monto_minimo_mayorista: "",
  moneda: "COP",
};

const redInicial = {
  plataforma: "",
  url: "",
  orden: 1,
};

function Configuration() {
  const navigate = useNavigate();

  const [tienda, setTienda] = useState(tiendaInicial);
  const [redes, setRedes] = useState([]);

  const [cargando, setCargando] = useState(true);
  const [guardandoTienda, setGuardandoTienda] = useState(false);
  const [guardandoRed, setGuardandoRed] = useState(false);
  const [procesandoId, setProcesandoId] = useState(null);

  const [error, setError] = useState("");
  const [mensaje, setMensaje] = useState("");

  const [modalAbierto, setModalAbierto] = useState(false);
  const [redEditar, setRedEditar] = useState(null);
  const [formularioRed, setFormularioRed] = useState(redInicial);

  // Manejo de respuestas y sesión
  const procesarRespuesta = async (respuesta) => {
    const resultado = await respuesta.json();

    if (respuesta.status === 401) {
      navigate("/admin/login", { replace: true });
      return null;
    }

    if (!respuesta.ok || !resultado.success) {
      throw new Error(
        resultado.message || "No fue posible completar la solicitud."
      );
    }

    return resultado;
  };

  // Cargar información de la tienda y redes sociales
  const cargarConfiguracion = async () => {
    setCargando(true);
    setError("");

    try {
      const [respuestaTienda, respuestaRedes] = await Promise.all([
        fetch("/api/configuracion/obtener.php", {
          credentials: "include",
        }),
        fetch("/api/configuracion/redes_listar.php", {
          credentials: "include",
        }),
      ]);

      const resultadoTienda = await procesarRespuesta(respuestaTienda);
      if (!resultadoTienda) return;

      const resultadoRedes = await procesarRespuesta(respuestaRedes);
      if (!resultadoRedes) return;

      setTienda({
        nombre_tienda:
          resultadoTienda.data?.tienda?.nombre_tienda || "",
        whatsapp: resultadoTienda.data?.tienda?.whatsapp || "",
        monto_minimo_mayorista:
          resultadoTienda.data?.tienda?.monto_minimo_mayorista ?? "",
        moneda: resultadoTienda.data?.tienda?.moneda || "COP",
      });

      setRedes(resultadoRedes.data || []);
    } catch (error) {
      setError(error.message);
    } finally {
      setCargando(false);
    }
  };

  useEffect(() => {
    cargarConfiguracion();
  }, []);

  // Cambios en información de la tienda
  const manejarCambioTienda = (e) => {
    const { name, value } = e.target;

    setTienda((anterior) => ({
      ...anterior,
      [name]: value,
    }));
  };

  // Guardar información de la tienda
  const guardarTienda = async (e) => {
    e.preventDefault();

    setError("");
    setMensaje("");
    setGuardandoTienda(true);

    try {
      const respuesta = await fetch("/api/configuracion/actualizar.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        credentials: "include",
        body: JSON.stringify({
          ...tienda,
          monto_minimo_mayorista:
            tienda.monto_minimo_mayorista === ""
              ? null
              : Number(tienda.monto_minimo_mayorista),
        }),
      });

      const resultado = await procesarRespuesta(respuesta);
      if (!resultado) return;

      setMensaje(
        resultado.message || "Información de la tienda actualizada correctamente."
      );
    } catch (error) {
      setError(error.message);
    } finally {
      setGuardandoTienda(false);
    }
  };

  // Abrir formulario de nueva red
  const abrirNuevaRed = () => {
    setRedEditar(null);
    setFormularioRed({
      ...redInicial,
      orden: redes.length + 1,
    });
    setError("");
    setMensaje("");
    setModalAbierto(true);
  };

  // Abrir formulario de edición
  const abrirEdicionRed = (red) => {
    setRedEditar(red);

    setFormularioRed({
      plataforma: red.plataforma || "",
      url: red.url || "",
      orden: Number(red.orden) || 0,
    });

    setError("");
    setMensaje("");
    setModalAbierto(true);
  };

  const manejarCambioRed = (e) => {
    const { name, value } = e.target;

    setFormularioRed((anterior) => ({
      ...anterior,
      [name]: value,
    }));
  };

  // Crear o actualizar red social
  const guardarRed = async (e) => {
    e.preventDefault();

    setError("");
    setMensaje("");
    setGuardandoRed(true);

    try {
      const esEdicion = Boolean(redEditar);

      const respuesta = await fetch(
        esEdicion
          ? "/api/configuracion/redes_actualizar.php"
          : "/api/configuracion/redes_crear.php",
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          credentials: "include",
          body: JSON.stringify(
            esEdicion
              ? {
                  ...formularioRed,
                  id: Number(redEditar.id),
                  orden: Number(formularioRed.orden),
                }
              : {
                  ...formularioRed,
                  orden: Number(formularioRed.orden),
                }
          ),
        }
      );

      const resultado = await procesarRespuesta(respuesta);
      if (!resultado) return;

      setMensaje(
        resultado.message ||
          (esEdicion
            ? "Red social actualizada correctamente."
            : "Red social agregada correctamente.")
      );

      setModalAbierto(false);
      setRedEditar(null);
      setFormularioRed(redInicial);

      await cargarRedes();
    } catch (error) {
      setError(error.message);
    } finally {
      setGuardandoRed(false);
    }
  };

  // Recargar redes sociales
  const cargarRedes = async () => {
    const respuesta = await fetch(
      "/api/configuracion/redes_listar.php",
      { credentials: "include" }
    );

    const resultado = await procesarRespuesta(respuesta);
    if (resultado) {
      setRedes(resultado.data || []);
    }
  };

  // Activar o desactivar red social
  const cambiarEstadoRed = async (red) => {
    const nuevoEstado = Number(red.activo) === 1 ? 0 : 1;

    const confirmar = window.confirm(
      nuevoEstado === 1
        ? `¿Deseas activar ${red.plataforma}?`
        : `¿Deseas desactivar ${red.plataforma}?`
    );

    if (!confirmar) return;

    setError("");
    setMensaje("");
    setProcesandoId(red.id);

    try {
      const respuesta = await fetch(
        "/api/configuracion/redes_cambiar_estado.php",
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          credentials: "include",
          body: JSON.stringify({
            id: Number(red.id),
            activo: nuevoEstado,
          }),
        }
      );

      const resultado = await procesarRespuesta(respuesta);
      if (!resultado) return;

      setMensaje(
        resultado.message || "Estado de la red social actualizado."
      );

      await cargarRedes();
    } catch (error) {
      setError(error.message);
    } finally {
      setProcesandoId(null);
    }
  };

  // Eliminar red social
  const eliminarRed = async (red) => {
    const confirmar = window.confirm(
      `¿Estás seguro de eliminar la red social "${red.plataforma}"? Esta acción no se puede deshacer.`
    );

    if (!confirmar) return;

    setError("");
    setMensaje("");
    setProcesandoId(red.id);

    try {
      const respuesta = await fetch(
        "/api/configuracion/redes_eliminar.php",
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          credentials: "include",
          body: JSON.stringify({
            id: Number(red.id),
          }),
        }
      );

      const resultado = await procesarRespuesta(respuesta);
      if (!resultado) return;

      setMensaje(
        resultado.message || "Red social eliminada correctamente."
      );

      await cargarRedes();
    } catch (error) {
      setError(error.message);
    } finally {
      setProcesandoId(null);
    }
  };

  const cerrarModal = () => {
    if (guardandoRed) return;

    setModalAbierto(false);
    setRedEditar(null);
    setFormularioRed(redInicial);
    setError("");
  };

  const obtenerIconoRed = (plataforma) => {
    const nombre = plataforma.toLowerCase();

    if (nombre.includes("instagram")) return <Instagram size={20} />;
    if (nombre.includes("facebook")) return <Facebook size={20} />;
    if (nombre.includes("tiktok")) return <Music2 size={20} />;

    return <Globe size={20} />;
  };

  return (
    <AdminLayout>
      <div className="products-page categories-page configuration-page">
        <div className="products-heading">
          <div>
            <h1>Configuración</h1>
            <p>
              Administra la información comercial y las redes sociales de
              Siempre Bella.
            </p>
          </div>
        </div>

        {mensaje && (
          <div className="category-alert success">
            {mensaje}
            <button type="button" onClick={() => setMensaje("")}>
              <X size={16} />
            </button>
          </div>
        )}

        {error && !modalAbierto && (
          <div className="category-alert error">
            {error}
            <button type="button" onClick={() => setError("")}>
              <X size={16} />
            </button>
          </div>
        )}

        {cargando ? (
          <div className="products-table-container">
            <p className="products-message">
              Cargando configuración...
            </p>
          </div>
        ) : (
          <>
            {/* INFORMACIÓN DE LA TIENDA */}
            <section className="configuration-section">
              <div className="configuration-section-heading">
                <div className="configuration-section-icon">
                  <Store size={21} />
                </div>
                <div>
                  <h2>Información de la tienda</h2>
                  <p>
                    Estos datos identifican tu negocio y permiten configurar
                    las condiciones comerciales.
                  </p>
                </div>
              </div>

              <form
                className="configuration-form"
                onSubmit={guardarTienda}
              >
                <div className="configuration-fields-grid">
                  <div className="category-form-field">
                    <label htmlFor="tienda-nombre">
                      Nombre de la tienda *
                    </label>
                    <input
                      id="tienda-nombre"
                      name="nombre_tienda"
                      value={tienda.nombre_tienda}
                      onChange={manejarCambioTienda}
                      placeholder="Ej. Siempre Bella"
                      maxLength={150}
                      required
                      disabled={guardandoTienda}
                    />
                  </div>

                  <div className="category-form-field">
                    <label htmlFor="tienda-whatsapp">
                      WhatsApp de contacto
                    </label>
                    <input
                      id="tienda-whatsapp"
                      name="whatsapp"
                      value={tienda.whatsapp}
                      onChange={manejarCambioTienda}
                      placeholder="Ej. 3173562745"
                      maxLength={30}
                      disabled={guardandoTienda}
                    />
                    <small>
                      Incluye el indicativo del país si corresponde.
                    </small>
                  </div>

                  <div className="category-form-field">
                    <label htmlFor="tienda-mayorista">
                      Compra mínima mayorista
                    </label>
                    <input
                      id="tienda-mayorista"
                      name="monto_minimo_mayorista"
                      type="number"
                      min="0"
                      step="0.01"
                      value={tienda.monto_minimo_mayorista}
                      onChange={manejarCambioTienda}
                      placeholder="Ej. 150000"
                      disabled={guardandoTienda}
                    />
                    <small>
                      Deja el campo vacío si no deseas establecer un mínimo.
                    </small>
                  </div>

                  <div className="category-form-field">
                    <label htmlFor="tienda-moneda">Moneda</label>
                    <select
                      id="tienda-moneda"
                      name="moneda"
                      value={tienda.moneda}
                      onChange={manejarCambioTienda}
                      disabled={guardandoTienda}
                    >
                      <option value="COP">COP - Peso colombiano</option>
                      <option value="USD">USD - Dólar estadounidense</option>
                      <option value="EUR">EUR - Euro</option>
                    </select>
                  </div>
                </div>

                <div className="configuration-form-actions">
                  <button
                    type="submit"
                    className="products-add-button"
                    disabled={guardandoTienda}
                  >
                    <Save size={17} />
                    {guardandoTienda
                      ? "Guardando..."
                      : "Guardar información"}
                  </button>
                </div>
              </form>
            </section>

            {/* REDES SOCIALES */}
            <section className="configuration-section">
              <div className="configuration-section-heading configuration-social-heading">
                <div className="configuration-section-title">
                  <div className="configuration-section-icon">
                    <Share2 size={21} />
                  </div>
                  <div>
                    <h2>Redes sociales</h2>
                    <p>
                      Gestiona los perfiles sociales que se mostrarán en la
                      tienda.
                    </p>
                  </div>
                </div>

                <button
                  type="button"
                  className="products-add-button"
                  onClick={abrirNuevaRed}
                >
                  <Plus size={18} />
                  Agregar red
                </button>
              </div>

              <div className="configuration-social-list">
                {redes.length === 0 ? (
                  <div className="configuration-empty">
                    <Share2 size={30} />
                    <h3>Aún no hay redes sociales</h3>
                    <p>
                      Agrega los perfiles oficiales de Siempre Bella para
                      mostrarlos en la tienda.
                    </p>
                  </div>
                ) : (
                  redes.map((red) => (
                    <div
                      className="configuration-social-item"
                      key={red.id}
                    >
                      <div className="configuration-social-info">
                        <div className="configuration-social-icon">
                          {obtenerIconoRed(red.plataforma)}
                        </div>

                        <div className="configuration-social-text">
                          <strong>{red.plataforma}</strong>
                          <a
                            href={red.url}
                            target="_blank"
                            rel="noreferrer"
                          >
                            {red.url}
                          </a>
                          <span
                            className={
                              Number(red.activo) === 1
                                ? "product-status active"
                                : "product-status inactive"
                            }
                          >
                            {Number(red.activo) === 1
                              ? "Activa"
                              : "Inactiva"}
                          </span>
                        </div>
                      </div>

                      <div className="configuration-social-actions">
                        <button
                          type="button"
                          className="category-edit-button"
                          onClick={() => abrirEdicionRed(red)}
                          disabled={procesandoId === red.id}
                        >
                          <Pencil size={15} />
                          Editar
                        </button>

                        <button
                          type="button"
                          className={
                            Number(red.activo) === 1
                              ? "category-toggle-button deactivate"
                              : "category-toggle-button activate"
                          }
                          onClick={() => cambiarEstadoRed(red)}
                          disabled={procesandoId === red.id}
                        >
                          <Power size={15} />
                          {procesandoId === red.id
                            ? "Procesando..."
                            : Number(red.activo) === 1
                              ? "Desactivar"
                              : "Activar"}
                        </button>

                        <button
                          type="button"
                          className="configuration-delete-button"
                          onClick={() => eliminarRed(red)}
                          disabled={procesandoId === red.id}
                          aria-label={`Eliminar ${red.plataforma}`}
                          title="Eliminar red social"
                        >
                          <Trash2 size={16} />
                        </button>
                      </div>
                    </div>
                  ))
                )}
              </div>
            </section>
          </>
        )}

        {/* MODAL DE RED SOCIAL */}
        {modalAbierto && (
          <div
            className="category-modal-overlay"
            onMouseDown={(e) => {
              if (e.target === e.currentTarget) cerrarModal();
            }}
          >
            <div
              className="category-modal"
              role="dialog"
              aria-modal="true"
              aria-labelledby="social-modal-title"
            >
              <div className="category-modal-heading">
                <div>
                  <h2 id="social-modal-title">
                    {redEditar ? "Editar red social" : "Nueva red social"}
                  </h2>
                  <p>
                    Ingresa el nombre de la plataforma y el enlace del perfil.
                  </p>
                </div>

                <button
                  type="button"
                  className="category-modal-close"
                  onClick={cerrarModal}
                  disabled={guardandoRed}
                  aria-label="Cerrar"
                >
                  <X size={21} />
                </button>
              </div>

              <form onSubmit={guardarRed}>
                <div className="category-form-field">
                  <label htmlFor="red-plataforma">
                    Plataforma *
                  </label>
                  <input
                    id="red-plataforma"
                    name="plataforma"
                    value={formularioRed.plataforma}
                    onChange={manejarCambioRed}
                    placeholder="Ej. Instagram"
                    maxLength={60}
                    required
                    disabled={guardandoRed}
                  />
                </div>

                <div className="category-form-field">
                  <label htmlFor="red-url">
                    URL del perfil *
                  </label>
                  <input
                    id="red-url"
                    name="url"
                    type="url"
                    value={formularioRed.url}
                    onChange={manejarCambioRed}
                    placeholder="https://www.instagram.com/tu_perfil"
                    maxLength={500}
                    required
                    disabled={guardandoRed}
                  />
                </div>

                <div className="category-form-field">
                  <label htmlFor="red-orden">
                    Orden de visualización
                  </label>
                  <input
                    id="red-orden"
                    name="orden"
                    type="number"
                    min="0"
                    step="1"
                    value={formularioRed.orden}
                    onChange={manejarCambioRed}
                    required
                    disabled={guardandoRed}
                  />
                  <small>
                    Los números menores aparecerán primero.
                  </small>
                </div>

                {error && (
                  <div className="category-alert error">
                    {error}
                  </div>
                )}

                <div className="category-modal-actions">
                  <button
                    type="button"
                    className="category-cancel-button"
                    onClick={cerrarModal}
                    disabled={guardandoRed}
                  >
                    Cancelar
                  </button>

                  <button
                    type="submit"
                    className="category-save-button"
                    disabled={guardandoRed}
                  >
                    {guardandoRed
                      ? "Guardando..."
                      : redEditar
                        ? "Actualizar red"
                        : "Agregar red"}
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}
      </div>
    </AdminLayout>
  );
}

export default Configuration;