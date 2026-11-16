
import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import {
  Plus,
  Pencil,
  Power,
  X,
  Tag,
  CalendarDays
} from "lucide-react";

import AdminLayout from "../../components/layout/AdminLayout.jsx";
import "../../styles/admin-products.css";
import "../../styles/admin-categories.css";

const formularioInicial = {
  producto_id: "",
  variante_id: "",
  nombre: "",
  precio_minorista_promocional: "",
  precio_mayorista_promocional: "",
  inicia_en: "",
  termina_en: ""
};

function fechaParaFormulario(fecha) {
  if (!fecha) return "";

  return fecha.replace(" ", "T").slice(0, 16);
}

function fechaParaServidor(fecha) {
  if (!fecha) return null;

  return fecha.replace("T", " ") + ":00";
}

function formatoPrecio(valor) {
  if (valor === null || valor === undefined || valor === "") {
    return "No definido";
  }

  return Number(valor).toLocaleString("es-CO", {
    style: "currency",
    currency: "COP",
    maximumFractionDigits: 0
  });
}

function estadoVigencia(promocion) {
  if (Number(promocion.activo) !== 1) {
    return "Inactiva";
  }

  const ahora = new Date();

  const inicio = promocion.inicia_en
    ? new Date(promocion.inicia_en.replace(" ", "T"))
    : null;

  const fin = promocion.termina_en
    ? new Date(promocion.termina_en.replace(" ", "T"))
    : null;

  if (inicio && ahora < inicio) return "Programada";
  if (fin && ahora > fin) return "Finalizada";

  return "Vigente";
}

function Promotions() {
  const navigate = useNavigate();

  const [promociones, setPromociones] = useState([]);
  const [productos, setProductos] = useState([]);

  const [cargando, setCargando] = useState(true);
  const [guardando, setGuardando] = useState(false);
  const [procesandoId, setProcesandoId] = useState(null);

  const [error, setError] = useState("");
  const [mensaje, setMensaje] = useState("");

  const [modalAbierto, setModalAbierto] = useState(false);
  const [promocionEditar, setPromocionEditar] = useState(null);

  const [formulario, setFormulario] = useState(formularioInicial);

  const cargarDatos = async () => {
    setCargando(true);
    setError("");

    try {
      const [respuestaPromociones, respuestaOpciones] =
        await Promise.all([
          fetch("/api/promociones/listar_admin.php", {
            credentials: "include"
          }),
          fetch("/api/promociones/listar_opciones.php", {
            credentials: "include"
          })
        ]);

      if (
        respuestaPromociones.status === 401 ||
        respuestaOpciones.status === 401
      ) {
        navigate("/admin/login", { replace: true });
        return;
      }

      const resultadoPromociones = await respuestaPromociones.json();
      const resultadoOpciones = await respuestaOpciones.json();

      if (!respuestaPromociones.ok || !resultadoPromociones.success) {
        throw new Error(
          resultadoPromociones.message ||
          "No fue posible cargar las promociones."
        );
      }

      if (!respuestaOpciones.ok || !resultadoOpciones.success) {
        throw new Error(
          resultadoOpciones.message ||
          "No fue posible cargar los productos."
        );
      }

      setPromociones(resultadoPromociones.data);
      setProductos(resultadoOpciones.data);

    } catch (error) {
      setError(error.message);

    } finally {
      setCargando(false);
    }
  };

  useEffect(() => {
    cargarDatos();
  }, []);

  const productoSeleccionado = productos.find(
    (producto) =>
      Number(producto.id) === Number(formulario.producto_id)
  );

  const varianteSeleccionada = productoSeleccionado?.variantes?.find(
    (variante) =>
      Number(variante.id) === Number(formulario.variante_id)
  );

  const preciosNormales = varianteSeleccionada || productoSeleccionado;

  const abrirNuevaPromocion = () => {
    setPromocionEditar(null);
    setFormulario(formularioInicial);
    setError("");
    setMensaje("");
    setModalAbierto(true);
  };

  const abrirEdicion = (promocion) => {
    setPromocionEditar(promocion);

    setFormulario({
      producto_id: String(promocion.producto_id),
      variante_id: promocion.variante_id
        ? String(promocion.variante_id)
        : "",
      nombre: promocion.nombre || "",
      precio_minorista_promocional:
        promocion.precio_minorista_promocional ?? "",
      precio_mayorista_promocional:
        promocion.precio_mayorista_promocional ?? "",
      inicia_en: fechaParaFormulario(promocion.inicia_en),
      termina_en: fechaParaFormulario(promocion.termina_en)
    });

    setError("");
    setMensaje("");
    setModalAbierto(true);
  };

  const manejarCambio = (e) => {
    const { name, value } = e.target;

    setFormulario((anterior) => ({
      ...anterior,
      [name]: value,
      ...(name === "producto_id" ? { variante_id: "" } : {})
    }));
  };

  const guardarPromocion = async (e) => {
    e.preventDefault();

    setError("");
    setMensaje("");
    setGuardando(true);

    try {
      const esEdicion = Boolean(promocionEditar);

      const endpoint = esEdicion
        ? "/api/promociones/actualizar.php"
        : "/api/promociones/crear.php";

      const datos = {
        ...formulario,
        producto_id: Number(formulario.producto_id),
        variante_id: formulario.variante_id
          ? Number(formulario.variante_id)
          : null,
        precio_minorista_promocional:
          formulario.precio_minorista_promocional === ""
            ? null
            : Number(formulario.precio_minorista_promocional),
        precio_mayorista_promocional:
          formulario.precio_mayorista_promocional === ""
            ? null
            : Number(formulario.precio_mayorista_promocional),
        inicia_en: fechaParaServidor(formulario.inicia_en),
        termina_en: fechaParaServidor(formulario.termina_en)
      };

      if (esEdicion) {
        datos.id = Number(promocionEditar.id);
      }

      const respuesta = await fetch(endpoint, {
        method: "POST",
        headers: {
          "Content-Type": "application/json"
        },
        credentials: "include",
        body: JSON.stringify(datos)
      });

      const resultado = await respuesta.json();

      if (respuesta.status === 401) {
        navigate("/admin/login", { replace: true });
        return;
      }

      if (!respuesta.ok || !resultado.success) {
        throw new Error(
          resultado.message || "No fue posible guardar la promoción."
        );
      }

      setMensaje(resultado.message);

      setModalAbierto(false);
      setPromocionEditar(null);
      setFormulario(formularioInicial);

      await cargarDatos();

    } catch (error) {
      setError(error.message);

    } finally {
      setGuardando(false);
    }
  };

  const cambiarEstado = async (promocion) => {
    const nuevoEstado = Number(promocion.activo) === 1 ? 0 : 1;

    const confirmar = window.confirm(
      nuevoEstado === 1
        ? `¿Deseas activar "${promocion.nombre}"?`
        : `¿Deseas desactivar "${promocion.nombre}"?`
    );

    if (!confirmar) return;

    setError("");
    setMensaje("");
    setProcesandoId(promocion.id);

    try {
      const respuesta = await fetch(
        "/api/promociones/cambiar_estado.php",
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json"
          },
          credentials: "include",
          body: JSON.stringify({
            id: Number(promocion.id),
            activo: nuevoEstado
          })
        }
      );

      const resultado = await respuesta.json();

      if (respuesta.status === 401) {
        navigate("/admin/login", { replace: true });
        return;
      }

      if (!respuesta.ok || !resultado.success) {
        throw new Error(
          resultado.message || "No fue posible cambiar el estado."
        );
      }

      setMensaje(resultado.message);
      await cargarDatos();

    } catch (error) {
      setError(error.message);

    } finally {
      setProcesandoId(null);
    }
  };

  const cerrarModal = () => {
    if (guardando) return;

    setModalAbierto(false);
    setPromocionEditar(null);
    setFormulario(formularioInicial);
    setError("");
  };

  return (
    <AdminLayout>
      <div className="products-page categories-page">

        {/* ENCABEZADO */}
        <div className="products-heading">
          <div>
            <h1>Promociones</h1>
            <p>Administra las ofertas de Siempre Bella.</p>
          </div>

          <button
            className="products-add-button"
            onClick={abrirNuevaPromocion}
          >
            <Plus size={18} />
            Nueva promoción
          </button>
        </div>

        {/* MENSAJES */}
        {mensaje && (
          <div className="category-alert success">
            {mensaje}
            <button onClick={() => setMensaje("")}>
              <X size={16} />
            </button>
          </div>
        )}

        {error && !modalAbierto && (
          <div className="category-alert error">
            {error}
            <button onClick={() => setError("")}>
              <X size={16} />
            </button>
          </div>
        )}

        {/* RESUMEN */}
        <div className="products-summary">
          <div>
            <span>Total de promociones</span>
            <strong>{promociones.length}</strong>
          </div>
        </div>

        {/* TABLA */}
        <div className="products-table-container">

          {cargando ? (
            <p className="products-message">
              Cargando promociones...
            </p>

          ) : promociones.length === 0 ? (
            <div className="products-empty">
              <h3>Aún no tienes promociones registradas</h3>
              <p>Comienza creando la primera promoción de tu tienda.</p>
            </div>

          ) : (
            <table className="products-table">
              <thead>
                <tr>
                  <th>Promoción</th>
                  <th>Producto / Variante</th>
                  <th>Precio promocional</th>
                  <th>Vigencia</th>
                  <th>Estado</th>
                  <th>Acciones</th>
                </tr>
              </thead>

              <tbody>
                {promociones.map((promocion) => {
                  const vigencia = estadoVigencia(promocion);

                  return (
                    <tr key={promocion.id}>
                      <td>
                        <div className="product-table-info">
                          <div className="product-table-image">
                            <Tag size={21} />
                          </div>
                          <strong>{promocion.nombre}</strong>
                        </div>
                      </td>

                      <td>
                        <strong>{promocion.producto_nombre}</strong>
                        <br />
                        <small>
                          {promocion.variante_nombre || "Producto general"}
                        </small>
                      </td>

                      <td>
                        <div>
                          <strong>
                            {formatoPrecio(
                              promocion.precio_minorista_promocional
                            )}
                          </strong>
                          <br />
                          <small>
                            Mayorista: {formatoPrecio(
                              promocion.precio_mayorista_promocional
                            )}
                          </small>
                        </div>
                      </td>

                      <td>
                        <small>
                          <CalendarDays size={13} />
                          {promocion.inicia_en
                            ? promocion.inicia_en.slice(0, 16)
                            : "Sin inicio"}
                        </small>
                        <br />
                        <small>
                          Hasta: {promocion.termina_en
                            ? promocion.termina_en.slice(0, 16)
                            : "Sin fecha límite"}
                        </small>
                      </td>

                      <td>
                        <span
                          className={
                            vigencia === "Vigente"
                              ? "product-status active"
                              : "product-status inactive"
                          }
                        >
                          {vigencia}
                        </span>
                      </td>

                      <td>
                        <div className="category-actions">
                          <button
                            type="button"
                            className="category-edit-button"
                            onClick={() => abrirEdicion(promocion)}
                          >
                            <Pencil size={15} />
                            Editar
                          </button>

                          <button
                            type="button"
                            className={
                              Number(promocion.activo) === 1
                                ? "category-toggle-button deactivate"
                                : "category-toggle-button activate"
                            }
                            onClick={() => cambiarEstado(promocion)}
                            disabled={procesandoId === promocion.id}
                          >
                            <Power size={15} />
                            {procesandoId === promocion.id
                              ? "Procesando..."
                              : Number(promocion.activo) === 1
                                ? "Desactivar"
                                : "Activar"}
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          )}
        </div>

        {/* MODAL */}
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
              aria-labelledby="promotion-modal-title"
            >
              <div className="category-modal-heading">
                <div>
                  <h2 id="promotion-modal-title">
                    {promocionEditar
                      ? "Editar promoción"
                      : "Nueva promoción"}
                  </h2>

                  <p>Configura los precios y la vigencia de la oferta.</p>
                </div>

                <button
                  type="button"
                  className="category-modal-close"
                  onClick={cerrarModal}
                  disabled={guardando}
                  aria-label="Cerrar"
                >
                  <X size={21} />
                </button>
              </div>

              <form onSubmit={guardarPromocion}>

                {/* NOMBRE */}
                <div className="category-form-field">
                  <label htmlFor="promo-nombre">
                    Nombre de la promoción *
                  </label>

                  <input
                    id="promo-nombre"
                    name="nombre"
                    value={formulario.nombre}
                    onChange={manejarCambio}
                    placeholder="Ej. Oferta especial"
                    maxLength={150}
                    required
                    disabled={guardando}
                  />
                </div>

                {/* PRODUCTO */}
                <div className="category-form-field">
                  <label htmlFor="promo-producto">
                    Producto *
                  </label>

                  <select
                    id="promo-producto"
                    name="producto_id"
                    value={formulario.producto_id}
                    onChange={manejarCambio}
                    required
                    disabled={guardando}
                  >
                    <option value="">Selecciona un producto</option>

                    {productos.map((producto) => (
                      <option key={producto.id} value={producto.id}>
                        {producto.nombre}
                      </option>
                    ))}
                  </select>
                </div>

                {/* VARIANTE */}
                {productoSeleccionado?.variantes?.length > 0 && (
                  <div className="category-form-field">
                    <label htmlFor="promo-variante">
                      Variante
                    </label>

                    <select
                      id="promo-variante"
                      name="variante_id"
                      value={formulario.variante_id}
                      onChange={manejarCambio}
                      disabled={guardando}
                    >
                      <option value="">
                        Promoción para todo el producto
                      </option>

                      {productoSeleccionado.variantes.map((variante) => (
                        <option key={variante.id} value={variante.id}>
                          {variante.nombre}
                        </option>
                      ))}
                    </select>
                  </div>
                )}

                {/* PRECIOS NORMALES */}
                {preciosNormales && (
                  <div className="category-alert success">
                    <strong>Precios normales</strong>
                    <p>
                      Minorista: {formatoPrecio(
                        preciosNormales.precio_minorista
                      )}
                    </p>
                    <p>
                      Mayorista: {formatoPrecio(
                        preciosNormales.precio_mayorista
                      )}
                    </p>
                  </div>
                )}

                {/* PRECIO MINORISTA */}
                <div className="category-form-field">
                  <label htmlFor="promo-minorista">
                    Precio promocional minorista
                  </label>

                  <input
                    id="promo-minorista"
                    name="precio_minorista_promocional"
                    type="number"
                    min="0"
                    step="0.01"
                    value={formulario.precio_minorista_promocional}
                    onChange={manejarCambio}
                    placeholder="Ej. 14000"
                    disabled={guardando}
                  />
                </div>

                {/* PRECIO MAYORISTA */}
                <div className="category-form-field">
                  <label htmlFor="promo-mayorista">
                    Precio promocional mayorista
                  </label>

                  <input
                    id="promo-mayorista"
                    name="precio_mayorista_promocional"
                    type="number"
                    min="0"
                    step="0.01"
                    value={formulario.precio_mayorista_promocional}
                    onChange={manejarCambio}
                    placeholder="Ej. 11000"
                    disabled={guardando}
                  />
                </div>

                {/* INICIO */}
                <div className="category-form-field">
                  <label htmlFor="promo-inicio">
                    Fecha y hora de inicio
                  </label>

                  <input
                    id="promo-inicio"
                    type="datetime-local"
                    name="inicia_en"
                    value={formulario.inicia_en}
                    onChange={manejarCambio}
                    disabled={guardando}
                  />
                </div>

                {/* FINALIZACIÓN */}
                <div className="category-form-field">
                  <label htmlFor="promo-fin">
                    Fecha y hora de finalización
                  </label>

                  <input
                    id="promo-fin"
                    type="datetime-local"
                    name="termina_en"
                    value={formulario.termina_en}
                    onChange={manejarCambio}
                    disabled={guardando}
                  />
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
                    disabled={guardando}
                  >
                    Cancelar
                  </button>

                  <button
                    type="submit"
                    className="category-save-button"
                    disabled={guardando}
                  >
                    {guardando
                      ? "Guardando..."
                      : promocionEditar
                        ? "Actualizar promoción"
                        : "Crear promoción"}
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

export default Promotions;
