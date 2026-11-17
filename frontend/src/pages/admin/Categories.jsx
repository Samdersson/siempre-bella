
import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { Plus, Pencil, Power, X, ImagePlus } from "lucide-react";
import AdminLayout from "../../components/layout/AdminLayout.jsx";
import "../../styles/admin-products.css";
import "../../styles/admin-categories.css";
import { obtenerHeadersCSRF } from "../../services/csrf.js";

const formularioInicial = {
  nombre: "",
  descripcion: "",
  activo: 1
};

function Categories() {
  const navigate = useNavigate();

  const [categorias, setCategorias] = useState([]);
  const [cargando, setCargando] = useState(true);
  const [guardando, setGuardando] = useState(false);
  const [procesandoId, setProcesandoId] = useState(null);

  const [error, setError] = useState("");
  const [mensaje, setMensaje] = useState("");

  const [modalAbierto, setModalAbierto] = useState(false);
  const [categoriaEditar, setCategoriaEditar] = useState(null);

  const [formulario, setFormulario] = useState(formularioInicial);
  const [imagen, setImagen] = useState(null);
  const [vistaPrevia, setVistaPrevia] = useState("");

  // Cargar categorías
  const cargarCategorias = async () => {
    setCargando(true);
    setError("");

    try {
      const respuesta = await fetch(
        "/api/categorias/listar_admin.php",
        { credentials: "include" }
      );

      const resultado = await respuesta.json();

      if (respuesta.status === 401) {
        navigate("/admin/login", { replace: true });
        return;
      }

      if (!respuesta.ok || !resultado.success) {
        throw new Error(
          resultado.message || "No fue posible cargar las categorías."
        );
      }

      setCategorias(resultado.data);

    } catch (error) {
      setError(error.message);

    } finally {
      setCargando(false);
    }
  };

  useEffect(() => {
    cargarCategorias();
  }, []);

  // Abrir formulario de creación
  const abrirNuevaCategoria = () => {
    setCategoriaEditar(null);
    setFormulario(formularioInicial);
    setImagen(null);
    setVistaPrevia("");
    setError("");
    setMensaje("");
    setModalAbierto(true);
  };

  // Abrir formulario de edición
  const abrirEdicion = (categoria) => {
    setCategoriaEditar(categoria);

    setFormulario({
      nombre: categoria.nombre || "",
      descripcion: categoria.descripcion || "",
      activo: Number(categoria.activo)
    });

    setImagen(null);
    setVistaPrevia(
      categoria.imagen ? `/${categoria.imagen}` : ""
    );

    setError("");
    setMensaje("");
    setModalAbierto(true);
  };

  // Cambios en los campos
  const manejarCambio = (e) => {
    const { name, value } = e.target;

    setFormulario((anterior) => ({
      ...anterior,
      [name]: value
    }));
  };

  // Seleccionar imagen
  const manejarImagen = (e) => {
    const archivo = e.target.files?.[0];

    if (!archivo) return;

    setError("");

    const tiposPermitidos = [
      "image/jpeg",
      "image/png",
      "image/webp"
    ];

    if (!tiposPermitidos.includes(archivo.type)) {
      setError("Solo se permiten imágenes JPG, PNG o WEBP.");
      e.target.value = "";
      return;
    }

    if (archivo.size > 5 * 1024 * 1024) {
      setError("La imagen debe pesar máximo 5 MB.");
      e.target.value = "";
      return;
    }

    setImagen(archivo);
    setVistaPrevia(URL.createObjectURL(archivo));
  };

  // Guardar categoría y cargar imagen si existe
  const guardarCategoria = async (e) => {
    e.preventDefault();

    setError("");
    setMensaje("");
    setGuardando(true);

    let categoriaId = categoriaEditar?.id;

    try {
      const esEdicion = Boolean(categoriaEditar);

      const endpoint = esEdicion
        ? "/api/categorias/actualizar.php"
        : "/api/categorias/crear.php";

      const datos = esEdicion
        ? {
            ...formulario,
            id: Number(categoriaId)
          }
        : formulario;

      const respuesta = await fetch(endpoint, {
        method: "POST",
        headers: await obtenerHeadersCSRF({
          "Content-Type": "application/json"
        }),
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
          resultado.message || "No fue posible guardar la categoría."
        );
      }

      if (!esEdicion) {
        categoriaId = resultado.categoria_id;
      }

      // Cargar imagen si el usuario seleccionó una
      if (imagen) {
        const datosImagen = new FormData();

        datosImagen.append("categoria_id", categoriaId);
        datosImagen.append("imagen", imagen);

        const respuestaImagen = await fetch(
          "/api/categorias/subir_imagen.php",
          {
            method: "POST",
            credentials: "include",
            headers: await obtenerHeadersCSRF(),
            body: datosImagen
          }
        );

        const resultadoImagen = await respuestaImagen.json();

        if (respuestaImagen.status === 401) {
          navigate("/admin/login", { replace: true });
          return;
        }

        if (!respuestaImagen.ok || !resultadoImagen.success) {
          throw new Error(
            resultadoImagen.message ||
            "La categoría se guardó, pero no se pudo cargar la imagen."
          );
        }
      }

      setMensaje(
        esEdicion
          ? "Categoría actualizada correctamente."
          : "Categoría creada correctamente."
      );

      setModalAbierto(false);
      setFormulario(formularioInicial);
      setImagen(null);
      setVistaPrevia("");

      await cargarCategorias();

    } catch (error) {
      setError(error.message);

    } finally {
      setGuardando(false);
    }
  };

  // Activar o desactivar categoría
  const cambiarEstado = async (categoria) => {
    const nuevoEstado = Number(categoria.activo) === 1 ? 0 : 1;

    const confirmar = window.confirm(
      nuevoEstado === 1
        ? `¿Deseas activar la categoría "${categoria.nombre}"?`
        : `¿Deseas desactivar la categoría "${categoria.nombre}"?`
    );

    if (!confirmar) return;

    setError("");
    setMensaje("");
    setProcesandoId(categoria.id);

    try {
      const respuesta = await fetch(
        "/api/categorias/cambiar_estado.php",
        {
          method: "POST",
          headers: await obtenerHeadersCSRF({
            "Content-Type": "application/json"
          }),
          credentials: "include",
          body: JSON.stringify({
            id: Number(categoria.id),
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
      await cargarCategorias();

    } catch (error) {
      setError(error.message);

    } finally {
      setProcesandoId(null);
    }
  };

  const cerrarModal = () => {
    if (guardando) return;

    setModalAbierto(false);
    setCategoriaEditar(null);
    setFormulario(formularioInicial);
    setImagen(null);
    setVistaPrevia("");
    setError("");
  };

  return (
    <AdminLayout>
      <div className="products-page categories-page">

        {/* ENCABEZADO */}
        <div className="products-heading">
          <div>
            <h1>Categorías</h1>
            <p>Organiza los productos de Siempre Bella.</p>
          </div>

          <button
            className="products-add-button"
            onClick={abrirNuevaCategoria}
          >
            <Plus size={18} />
            Nueva categoría
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
            <span>Total de categorías</span>
            <strong>{categorias.length}</strong>
          </div>
        </div>

        {/* TABLA */}
        <div className="products-table-container">

          {cargando ? (
            <p className="products-message">
              Cargando categorías...
            </p>

          ) : categorias.length === 0 ? (
            <div className="products-empty">
              <h3>Aún no tienes categorías registradas</h3>
              <p>Comienza creando la primera categoría de tu tienda.</p>
            </div>

          ) : (
            <table className="products-table">
              <thead>
                <tr>
                  <th>Categoría</th>
                  <th>Slug</th>
                  <th>Descripción</th>
                  <th>Estado</th>
                  <th>Acciones</th>
                </tr>
              </thead>

              <tbody>
                {categorias.map((categoria) => (
                  <tr key={categoria.id}>

                    <td>
                      <div className="product-table-info">
                        <div className="product-table-image">
                          {categoria.imagen ? (
                            <img
                              src={`/${categoria.imagen}`}
                              alt={categoria.nombre}
                              loading="lazy"
                            />
                          ) : (
                            <span className="category-image-empty">
                              <ImagePlus size={22} />
                            </span>
                          )}
                        </div>

                        <strong>{categoria.nombre}</strong>
                      </div>
                    </td>

                    <td>{categoria.slug}</td>

                    <td>
                      {categoria.descripcion || "Sin descripción"}
                    </td>

                    <td>
                      <span
                        className={
                          Number(categoria.activo) === 1
                            ? "product-status active"
                            : "product-status inactive"
                        }
                      >
                        {Number(categoria.activo) === 1
                          ? "Activa"
                          : "Inactiva"}
                      </span>
                    </td>

                    <td>
                      <div className="category-actions">
                        <button
                          type="button"
                          className="category-edit-button"
                          onClick={() => abrirEdicion(categoria)}
                        >
                          <Pencil size={15} />
                          Editar
                        </button>

                        <button
                          type="button"
                          className={
                            Number(categoria.activo) === 1
                              ? "category-toggle-button deactivate"
                              : "category-toggle-button activate"
                          }
                          onClick={() => cambiarEstado(categoria)}
                          disabled={procesandoId === categoria.id}
                        >
                          <Power size={15} />
                          {procesandoId === categoria.id
                            ? "Procesando..."
                            : Number(categoria.activo) === 1
                              ? "Desactivar"
                              : "Activar"}
                        </button>
                      </div>
                    </td>

                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>

        {/* MODAL DE CATEGORÍA */}
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
              aria-labelledby="category-modal-title"
            >
              <div className="category-modal-heading">
                <div>
                  <h2 id="category-modal-title">
                    {categoriaEditar
                      ? "Editar categoría"
                      : "Nueva categoría"}
                  </h2>

                  <p>
                    Completa la información de la categoría.
                  </p>
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

              <form onSubmit={guardarCategoria}>

                <div className="category-form-field">
                  <label htmlFor="categoria-nombre">
                    Nombre de la categoría *
                  </label>

                  <input
                    id="categoria-nombre"
                    name="nombre"
                    value={formulario.nombre}
                    onChange={manejarCambio}
                    placeholder="Ej. Cuidado facial"
                    maxLength={100}
                    required
                    disabled={guardando}
                  />
                </div>

                <div className="category-form-field">
                  <label htmlFor="categoria-descripcion">
                    Descripción
                  </label>

                  <textarea
                    id="categoria-descripcion"
                    name="descripcion"
                    value={formulario.descripcion}
                    onChange={manejarCambio}
                    placeholder="Describe los productos de esta categoría..."
                    rows={3}
                    maxLength={255}
                    disabled={guardando}
                  />
                </div>

                <div className="category-form-field">
                  <label>Imagen de categoría</label>

                  <label
                    htmlFor="categoria-imagen"
                    className="category-upload-area"
                  >
                    <input
                      id="categoria-imagen"
                      type="file"
                      accept="image/jpeg,image/png,image/webp"
                      onChange={manejarImagen}
                      disabled={guardando}
                    />

                    {vistaPrevia ? (
                      <img
                        src={vistaPrevia}
                        alt="Vista previa de categoría"
                        className="category-image-preview"
                      />
                    ) : (
                      <div className="category-upload-placeholder">
                        <ImagePlus size={28} />
                        <strong>Seleccionar imagen</strong>
                        <span>JPG, PNG o WEBP · Máximo 5 MB</span>
                      </div>
                    )}
                  </label>
                </div>

                <div className="category-form-field">
                  <label htmlFor="categoria-activo">
                    Estado
                  </label>

                  <select
                    id="categoria-activo"
                    name="activo"
                    value={formulario.activo}
                    onChange={manejarCambio}
                    disabled={guardando}
                  >
                    <option value={1}>Activa</option>
                    <option value={0}>Inactiva</option>
                  </select>
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
                      : categoriaEditar
                        ? "Actualizar categoría"
                        : "Crear categoría"}
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

export default Categories;
