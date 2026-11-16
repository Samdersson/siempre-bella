
import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { Plus, Pencil, Power, X, ImagePlus } from "lucide-react";
import AdminLayout from "../../components/layout/AdminLayout.jsx";
import "../../styles/admin-products.css";
import "../../styles/admin-categories.css";

const formularioInicial = {
  nombre: "",
  descripcion: "",
  activo: 1
};

function Brands() {
  const navigate = useNavigate();

  const [marcas, setMarcas] = useState([]);
  const [cargando, setCargando] = useState(true);
  const [guardando, setGuardando] = useState(false);
  const [procesandoId, setProcesandoId] = useState(null);

  const [error, setError] = useState("");
  const [mensaje, setMensaje] = useState("");

  const [modalAbierto, setModalAbierto] = useState(false);
  const [marcaEditar, setMarcaEditar] = useState(null);

  const [formulario, setFormulario] = useState(formularioInicial);
  const [logo, setLogo] = useState(null);
  const [vistaPrevia, setVistaPrevia] = useState("");

  // Cargar marcas
  const cargarMarcas = async () => {
    setCargando(true);
    setError("");

    try {
      const respuesta = await fetch(
        "/api/marcas/listar_admin.php",
        { credentials: "include" }
      );

      const resultado = await respuesta.json();

      if (respuesta.status === 401) {
        navigate("/admin/login", { replace: true });
        return;
      }

      if (!respuesta.ok || !resultado.success) {
        throw new Error(
          resultado.message || "No fue posible cargar las marcas."
        );
      }

      setMarcas(resultado.data);

    } catch (error) {
      setError(error.message);

    } finally {
      setCargando(false);
    }
  };

  useEffect(() => {
    cargarMarcas();
  }, []);

  // Abrir formulario de creación
  const abrirNuevaMarca = () => {
    setMarcaEditar(null);
    setFormulario(formularioInicial);
    setLogo(null);
    setVistaPrevia("");
    setError("");
    setMensaje("");
    setModalAbierto(true);
  };

  // Abrir formulario de edición
  const abrirEdicion = (marca) => {
    setMarcaEditar(marca);

    setFormulario({
      nombre: marca.nombre || "",
      descripcion: marca.descripcion || "",
      activo: Number(marca.activo)
    });

    setLogo(null);
    setVistaPrevia(marca.logo || "");

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

  // Seleccionar logotipo
  const manejarLogo = (e) => {
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
      setError("El logotipo debe pesar máximo 5 MB.");
      e.target.value = "";
      return;
    }

    setLogo(archivo);
    setVistaPrevia(URL.createObjectURL(archivo));
  };

  // Guardar marca y cargar logotipo si existe
  const guardarMarca = async (e) => {
    e.preventDefault();

    setError("");
    setMensaje("");
    setGuardando(true);

    let marcaId = marcaEditar?.id;

    try {
      const esEdicion = Boolean(marcaEditar);

      const endpoint = esEdicion
        ? "/api/marcas/actualizar.php"
        : "/api/marcas/crear.php";

      const datos = esEdicion
        ? {
            ...formulario,
            id: Number(marcaId)
          }
        : formulario;

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
          resultado.message || "No fue posible guardar la marca."
        );
      }

      if (!esEdicion) {
        marcaId = resultado.marca_id;

        // Permite reintentar el logo sin duplicar la marca
        setMarcaEditar({ id: marcaId });
      }

      // Subir logotipo si se seleccionó uno
      if (logo) {
        const datosLogo = new FormData();

        datosLogo.append("marca_id", marcaId);
        datosLogo.append("logo", logo);

        const respuestaLogo = await fetch(
          "/api/marcas/subir_logo.php",
          {
            method: "POST",
            credentials: "include",
            body: datosLogo
          }
        );

        const resultadoLogo = await respuestaLogo.json();

        if (respuestaLogo.status === 401) {
          navigate("/admin/login", { replace: true });
          return;
        }

        if (!respuestaLogo.ok || !resultadoLogo.success) {
          throw new Error(
            resultadoLogo.message ||
            "La marca se guardó, pero no se pudo cargar el logotipo."
          );
        }
      }

      setMensaje(
        esEdicion
          ? "Marca actualizada correctamente."
          : "Marca creada correctamente."
      );

      setModalAbierto(false);
      setMarcaEditar(null);
      setFormulario(formularioInicial);
      setLogo(null);
      setVistaPrevia("");

      await cargarMarcas();

    } catch (error) {
      setError(error.message);

    } finally {
      setGuardando(false);
    }
  };

  // Activar o desactivar marca
  const cambiarEstado = async (marca) => {
    const nuevoEstado = Number(marca.activo) === 1 ? 0 : 1;

    const confirmar = window.confirm(
      nuevoEstado === 1
        ? `¿Deseas activar la marca "${marca.nombre}"?`
        : `¿Deseas desactivar la marca "${marca.nombre}"?`
    );

    if (!confirmar) return;

    setError("");
    setMensaje("");
    setProcesandoId(marca.id);

    try {
      const respuesta = await fetch(
        "/api/marcas/cambiar_estado.php",
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json"
          },
          credentials: "include",
          body: JSON.stringify({
            id: Number(marca.id),
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
      await cargarMarcas();

    } catch (error) {
      setError(error.message);

    } finally {
      setProcesandoId(null);
    }
  };

  // Cerrar modal
  const cerrarModal = () => {
    if (guardando) return;

    setModalAbierto(false);
    setMarcaEditar(null);
    setFormulario(formularioInicial);
    setLogo(null);
    setVistaPrevia("");
    setError("");
  };

  return (
    <AdminLayout>
      <div className="products-page categories-page">

        {/* ENCABEZADO */}
        <div className="products-heading">
          <div>
            <h1>Marcas</h1>
            <p>Administra las marcas de los productos de Siempre Bella.</p>
          </div>

          <button
            className="products-add-button"
            onClick={abrirNuevaMarca}
          >
            <Plus size={18} />
            Nueva marca
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
            <span>Total de marcas</span>
            <strong>{marcas.length}</strong>
          </div>
        </div>

        {/* TABLA */}
        <div className="products-table-container">

          {cargando ? (
            <p className="products-message">
              Cargando marcas...
            </p>

          ) : marcas.length === 0 ? (
            <div className="products-empty">
              <h3>Aún no tienes marcas registradas</h3>
              <p>Comienza creando la primera marca de tu tienda.</p>
            </div>

          ) : (
            <table className="products-table">
              <thead>
                <tr>
                  <th>Marca</th>
                  <th>Slug</th>
                  <th>Descripción</th>
                  <th>Estado</th>
                  <th>Acciones</th>
                </tr>
              </thead>

              <tbody>
                {marcas.map((marca) => (
                  <tr key={marca.id}>

                    <td>
                      <div className="product-table-info">
                        <div className="product-table-image">
                          {marca.logo ? (
                            <img
                              src={marca.logo}
                              alt={`Logotipo de ${marca.nombre}`}
                              loading="lazy"
                            />
                          ) : (
                            <span className="category-image-empty">
                              <ImagePlus size={22} />
                            </span>
                          )}
                        </div>

                        <strong>{marca.nombre}</strong>
                      </div>
                    </td>

                    <td>{marca.slug}</td>

                    <td>
                      {marca.descripcion || "Sin descripción"}
                    </td>

                    <td>
                      <span
                        className={
                          Number(marca.activo) === 1
                            ? "product-status active"
                            : "product-status inactive"
                        }
                      >
                        {Number(marca.activo) === 1
                          ? "Activa"
                          : "Inactiva"}
                      </span>
                    </td>

                    <td>
                      <div className="category-actions">
                        <button
                          type="button"
                          className="category-edit-button"
                          onClick={() => abrirEdicion(marca)}
                        >
                          <Pencil size={15} />
                          Editar
                        </button>

                        <button
                          type="button"
                          className={
                            Number(marca.activo) === 1
                              ? "category-toggle-button deactivate"
                              : "category-toggle-button activate"
                          }
                          onClick={() => cambiarEstado(marca)}
                          disabled={procesandoId === marca.id}
                        >
                          <Power size={15} />
                          {procesandoId === marca.id
                            ? "Procesando..."
                            : Number(marca.activo) === 1
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

        {/* MODAL DE MARCA */}
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
              aria-labelledby="brand-modal-title"
            >
              <div className="category-modal-heading">
                <div>
                  <h2 id="brand-modal-title">
                    {marcaEditar
                      ? "Editar marca"
                      : "Nueva marca"}
                  </h2>

                  <p>
                    Completa la información de la marca.
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

              <form onSubmit={guardarMarca}>

                <div className="category-form-field">
                  <label htmlFor="marca-nombre">
                    Nombre de la marca *
                  </label>

                  <input
                    id="marca-nombre"
                    name="nombre"
                    value={formulario.nombre}
                    onChange={manejarCambio}
                    placeholder="Ej. La Poción"
                    maxLength={120}
                    required
                    disabled={guardando}
                  />
                </div>

                <div className="category-form-field">
                  <label htmlFor="marca-descripcion">
                    Descripción
                  </label>

                  <textarea
                    id="marca-descripcion"
                    name="descripcion"
                    value={formulario.descripcion}
                    onChange={manejarCambio}
                    placeholder="Describe la marca..."
                    rows={3}
                    maxLength={255}
                    disabled={guardando}
                  />
                </div>

                <div className="category-form-field">
                  <label>Logotipo de la marca</label>

                  <label
                    htmlFor="marca-logo"
                    className="category-upload-area"
                  >
                    <input
                      id="marca-logo"
                      type="file"
                      accept="image/jpeg,image/png,image/webp"
                      onChange={manejarLogo}
                      disabled={guardando}
                    />

                    {vistaPrevia ? (
                      <img
                        src={vistaPrevia}
                        alt="Vista previa del logotipo"
                        className="category-image-preview"
                      />
                    ) : (
                      <div className="category-upload-placeholder">
                        <ImagePlus size={28} />
                        <strong>Seleccionar logotipo</strong>
                        <span>JPG, PNG o WEBP · Máximo 5 MB</span>
                      </div>
                    )}
                  </label>
                </div>

                <div className="category-form-field">
                  <label htmlFor="marca-activo">
                    Estado
                  </label>

                  <select
                    id="marca-activo"
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
                      : marcaEditar
                        ? "Actualizar marca"
                        : "Crear marca"}
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

export default Brands;
