
import { useEffect, useState } from "react";
import { obtenerHeadersCSRF } from "../../services/csrf.js";
import "../../styles/admin-product-form.css";

const estadoInicial = {
  nombre: "",
  sku: "",
  descripcion: "",
  categoria_id: "",
  marca_id: "",
  precio_minorista: "",
  precio_mayorista: "",
  disponibilidad: "disponible",
  activo: 1
};

function ProductForm({
  onSuccess,
  modo = "crear",
  initialData = null
}) {
  const [formulario, setFormulario] = useState(estadoInicial);

  const [categorias, setCategorias] = useState([]);
  const [marcas, setMarcas] = useState([]);

  const [imagenes, setImagenes] = useState([]);
  const [imagenesExistentes, setImagenesExistentes] = useState([]);

  const [cargandoDatos, setCargandoDatos] = useState(true);
  const [guardando, setGuardando] = useState(false);
  const [gestionandoImagenId, setGestionandoImagenId] = useState(null);

  const [error, setError] = useState("");
  const [mensaje, setMensaje] = useState("");

  const [productoPendienteId, setProductoPendienteId] = useState(null);

  const totalImagenes = imagenesExistentes.length + imagenes.length;

  // Cargar categorías y marcas
  useEffect(() => {
    const cargarOpciones = async () => {
      try {
        const [respuestaCategorias, respuestaMarcas] = await Promise.all([
          fetch("/api/categorias/listar.php", {
            credentials: "include"
          }),
          fetch("/api/marcas/listar.php", {
            credentials: "include"
          })
        ]);

        const resultadoCategorias = await respuestaCategorias.json();
        const resultadoMarcas = await respuestaMarcas.json();

        if (!respuestaCategorias.ok || !resultadoCategorias.success) {
          throw new Error("No fue posible cargar las categorías.");
        }

        if (!respuestaMarcas.ok || !resultadoMarcas.success) {
          throw new Error("No fue posible cargar las marcas.");
        }

        setCategorias(resultadoCategorias.data);
        setMarcas(resultadoMarcas.data);

      } catch (error) {
        setError(error.message);
      } finally {
        setCargandoDatos(false);
      }
    };

    cargarOpciones();
  }, []);

  // Cargar información del producto en modo edición
  useEffect(() => {
    if (modo !== "editar" || !initialData) return;

    const producto = initialData.producto;

    setFormulario({
      nombre: producto.nombre || "",
      sku: producto.sku || "",
      descripcion: producto.descripcion || "",
      categoria_id: String(producto.categoria_id || ""),
      marca_id: producto.marca_id
        ? String(producto.marca_id)
        : "",
      precio_minorista: producto.precio_minorista || "",
      precio_mayorista: producto.precio_mayorista || "",
      disponibilidad: producto.disponibilidad || "disponible",
      activo: Number(producto.activo)
    });

    setImagenesExistentes(initialData.imagenes || []);
    setImagenes([]);
  }, [modo, initialData]);

  // Cambios en los campos
  const manejarCambio = (e) => {
    const { name, value } = e.target;

    setFormulario((anterior) => ({
      ...anterior,
      [name]: value
    }));
  };

  // Seleccionar imágenes
  const manejarImagenes = (e) => {
    const seleccionadas = Array.from(e.target.files || []);

    setError("");

    if (totalImagenes + seleccionadas.length > 8) {
      setError("Puedes tener máximo 8 imágenes por producto.");
      e.target.value = "";
      return;
    }

    const archivosValidos = seleccionadas.filter((archivo) => {
      const tipoPermitido = [
        "image/jpeg",
        "image/png",
        "image/webp"
      ].includes(archivo.type);

      if (!tipoPermitido) {
        setError("Solo se permiten imágenes JPG, PNG o WEBP.");
        return false;
      }

      if (archivo.size > 5 * 1024 * 1024) {
        setError("Cada imagen debe pesar máximo 5 MB.");
        return false;
      }

      return true;
    });

    setImagenes((anteriores) => [
      ...anteriores,
      ...archivosValidos
    ]);

    e.target.value = "";
  };

  // Eliminar imagen nueva seleccionada
  const eliminarImagenNueva = (indice) => {
    setImagenes((anteriores) =>
      anteriores.filter((_, i) => i !== indice)
    );
  };

  // Gestionar imágenes existentes
  const gestionarImagen = async (imagen, accion) => {
    if (!initialData?.producto?.id || !imagen?.id) {
      setError("No se pudo identificar el producto o la imagen.");
      return;
    }

    const confirmacion =
      accion === "eliminar"
        ? window.confirm("¿Deseas eliminar esta imagen del producto?")
        : true;

    if (!confirmacion) return;

    setError("");
    setMensaje("");
    setGestionandoImagenId(Number(imagen.id));

    try {
      const headers = await obtenerHeadersCSRF({
        "Content-Type": "application/json"
      });

      const respuesta = await fetch(
        "/api/productos/gestionar_imagenes.php",
        {
          method: "POST",
          headers,
          credentials: "include",
          body: JSON.stringify({
            accion,
            producto_id: Number(initialData.producto.id),
            imagen_id: Number(imagen.id)
          })
        }
      );

      const texto = await respuesta.text();

      let resultado;

      try {
        resultado = JSON.parse(texto);
      } catch {
        throw new Error(
          `El servidor devolvió una respuesta no válida. HTTP ${respuesta.status}.`
        );
      }

      if (respuesta.status === 401) {
        throw new Error(
          "Tu sesión ha expirado. Inicia sesión nuevamente."
        );
      }

      if (respuesta.status === 403) {
        throw new Error(
          "La validación de seguridad falló. Recarga la página e inténtalo nuevamente."
        );
      }

      if (!respuesta.ok || !resultado.success) {
        throw new Error(
          resultado.message || "No fue posible gestionar la imagen."
        );
      }

      if (accion === "eliminar") {
        setImagenesExistentes((anteriores) =>
          anteriores.filter(
            (item) => Number(item.id) !== Number(imagen.id)
          )
        );
      }

      if (accion === "principal") {
        setImagenesExistentes((anteriores) =>
          anteriores.map((item) => ({
            ...item,
            es_principal:
              Number(item.id) === Number(imagen.id) ? 1 : 0
          }))
        );
      }

      setMensaje(resultado.message);

    } catch (error) {
      setError(error.message);
    } finally {
      setGestionandoImagenId(null);
    }
  };

  // Guardar producto
  const guardarProducto = async (e) => {
    e.preventDefault();

    setError("");
    setMensaje("");
    setGuardando(true);

    try {
      let productoId;

      const datosProducto = {
        ...formulario,
        categoria_id: Number(formulario.categoria_id),
        marca_id: formulario.marca_id
          ? Number(formulario.marca_id)
          : null,
        activo: Number(formulario.activo)
      };

      // MODO EDICIÓN
      if (modo === "editar") {
        productoId = initialData.producto.id;

        const headers = await obtenerHeadersCSRF({
          "Content-Type": "application/json"
        });

        const respuesta = await fetch(
          "/api/productos/actualizar.php",
          {
            method: "POST",
            headers,
            credentials: "include",
            body: JSON.stringify({
              ...datosProducto,
              id: Number(productoId)
            })
          }
        );

        const resultado = await respuesta.json();

        if (respuesta.status === 401) {
          throw new Error(
            "Tu sesión ha expirado. Inicia sesión nuevamente."
          );
        }

        if (respuesta.status === 403) {
          throw new Error(
            "La validación de seguridad falló. Recarga la página e inténtalo nuevamente."
          );
        }

        if (!respuesta.ok || !resultado.success) {
          throw new Error(
            resultado.message || "No fue posible actualizar el producto."
          );
        }

      } else {
        // MODO CREACIÓN
        productoId = productoPendienteId;

        if (!productoId) {
          const headers = await obtenerHeadersCSRF({
            "Content-Type": "application/json"
          });

          const respuesta = await fetch(
            "/api/productos/crear.php",
            {
              method: "POST",
              headers,
              credentials: "include",
              body: JSON.stringify(datosProducto)
            }
          );

          const resultado = await respuesta.json();

          if (respuesta.status === 401) {
            throw new Error(
              "Tu sesión ha expirado. Vuelve a iniciar sesión."
            );
          }

          if (respuesta.status === 403) {
            throw new Error(
              "La validación de seguridad falló. Recarga la página e inténtalo nuevamente."
            );
          }

          if (!respuesta.ok || !resultado.success) {
            throw new Error(
              resultado.message || "No fue posible guardar el producto."
            );
          }

          productoId = resultado.producto_id;

          if (!productoId) {
            throw new Error(
              "El servidor no devolvió el ID del producto creado."
            );
          }

          setProductoPendienteId(productoId);
        }
      }

      // Subir nuevas imágenes
      if (imagenes.length > 0) {
        const datosImagenes = new FormData();

        datosImagenes.append("producto_id", productoId);

        imagenes.forEach((imagen) => {
          datosImagenes.append("imagenes[]", imagen);
        });

        const headers = await obtenerHeadersCSRF();

        const respuestaImagenes = await fetch(
          "/api/productos/subir_imagenes.php",
          {
            method: "POST",
            headers,
            credentials: "include",
            body: datosImagenes
          }
        );

        const resultadoImagenes = await respuestaImagenes.json();

        if (respuestaImagenes.status === 401) {
          throw new Error(
            "Tu sesión ha expirado. Inicia sesión nuevamente."
          );
        }

        if (respuestaImagenes.status === 403) {
          throw new Error(
            "La validación de seguridad falló. Recarga la página e inténtalo nuevamente."
          );
        }

        if (!respuestaImagenes.ok || !resultadoImagenes.success) {
          throw new Error(
            resultadoImagenes.message ||
            "Los datos se guardaron, pero las imágenes no pudieron cargarse."
          );
        }
      }

      setMensaje(
        modo === "editar"
          ? "Producto actualizado correctamente."
          : "Producto registrado correctamente."
      );

      if (modo === "crear") {
        setFormulario(estadoInicial);
        setImagenes([]);
        setProductoPendienteId(null);
      }

      if (onSuccess) {
        onSuccess({
          producto_id: productoId
        });
      }

    } catch (error) {
      setError(error.message);
    } finally {
      setGuardando(false);
    }
  };

  if (cargandoDatos) {
    return <p>Cargando categorías y marcas...</p>;
  }

  return (
    <form className="product-form" onSubmit={guardarProducto}>

      {/* INFORMACIÓN GENERAL */}
      <section className="product-form-section">
        <h2>
          {modo === "editar"
            ? "Editar información del producto"
            : "Información general"}
        </h2>

        <div className="product-form-grid">
          <div className="product-field full-width">
            <label htmlFor="nombre">
              Nombre del producto *
            </label>

            <input
              id="nombre"
              name="nombre"
              value={formulario.nombre}
              onChange={manejarCambio}
              maxLength={180}
              placeholder="Ej. Shampoo hidratante"
              required
              disabled={guardando}
            />
          </div>

          <div className="product-field">
            <label htmlFor="sku">
              SKU / Referencia
            </label>

            <input
              id="sku"
              name="sku"
              value={formulario.sku}
              onChange={manejarCambio}
              maxLength={80}
              placeholder="Ej. SHA-001"
              disabled={guardando}
            />
          </div>

          <div className="product-field full-width">
            <label htmlFor="descripcion">
              Descripción
            </label>

            <textarea
              id="descripcion"
              name="descripcion"
              value={formulario.descripcion}
              onChange={manejarCambio}
              rows={4}
              placeholder="Describe las características del producto..."
              disabled={guardando}
            />
          </div>
        </div>
      </section>

      {/* CATEGORÍAS Y MARCAS */}
      <section className="product-form-section">
        <h2>Categoría y marca</h2>

        <div className="product-form-grid">
          <div className="product-field">
            <label htmlFor="categoria_id">
              Categoría *
            </label>

            <select
              id="categoria_id"
              name="categoria_id"
              value={formulario.categoria_id}
              onChange={manejarCambio}
              required
              disabled={guardando}
            >
              <option value="">
                Seleccionar categoría
              </option>

              {categorias.map((categoria) => (
                <option key={categoria.id} value={categoria.id}>
                  {categoria.nombre}
                </option>
              ))}
            </select>
          </div>

          <div className="product-field">
            <label htmlFor="marca_id">
              Marca
            </label>

            <select
              id="marca_id"
              name="marca_id"
              value={formulario.marca_id}
              onChange={manejarCambio}
              disabled={guardando}
            >
              <option value="">
                Sin marca
              </option>

              {marcas.map((marca) => (
                <option key={marca.id} value={marca.id}>
                  {marca.nombre}
                </option>
              ))}
            </select>
          </div>
        </div>
      </section>

      {/* PRECIOS */}
      <section className="product-form-section">
        <h2>Precios</h2>

        <div className="product-form-grid">
          <div className="product-field">
            <label htmlFor="precio_minorista">
              Precio minorista (COP) *
            </label>

            <input
              id="precio_minorista"
              name="precio_minorista"
              type="number"
              min="1"
              step="0.01"
              value={formulario.precio_minorista}
              onChange={manejarCambio}
              placeholder="0"
              required
              disabled={guardando}
            />
          </div>

          <div className="product-field">
            <label htmlFor="precio_mayorista">
              Precio mayorista (COP)
            </label>

            <input
              id="precio_mayorista"
              name="precio_mayorista"
              type="number"
              min="0"
              step="0.01"
              value={formulario.precio_mayorista}
              onChange={manejarCambio}
              placeholder="Opcional"
              disabled={guardando}
            />
          </div>
        </div>
      </section>

      {/* DISPONIBILIDAD */}
      <section className="product-form-section">
        <h2>Disponibilidad y publicación</h2>

        <div className="product-form-grid">
          <div className="product-field">
            <label htmlFor="disponibilidad">
              Disponibilidad
            </label>

            <select
              id="disponibilidad"
              name="disponibilidad"
              value={formulario.disponibilidad}
              onChange={manejarCambio}
              disabled={guardando}
            >
              <option value="disponible">Disponible</option>
              <option value="agotado">Agotado</option>
            </select>
          </div>

          <div className="product-field">
            <label htmlFor="activo">
              Publicación
            </label>

            <select
              id="activo"
              name="activo"
              value={formulario.activo}
              onChange={manejarCambio}
              disabled={guardando}
            >
              <option value={1}>Activo</option>
              <option value={0}>Inactivo</option>
            </select>
          </div>
        </div>
      </section>

      {/* IMÁGENES DEL PRODUCTO */}
      <section className="product-form-section">

        <div className="product-image-heading">
          <div>
            <h2>Imágenes del producto</h2>
            <p>
              Las imágenes ayudan a tus clientes a conocer mejor el producto.
            </p>
          </div>

          <span className="product-image-counter">
            {totalImagenes}/8 imágenes
          </span>
        </div>

        {/* IMÁGENES EXISTENTES */}
        {imagenesExistentes.length > 0 && (
          <div className="product-existing-images">
            <h3>Imágenes actuales</h3>

            <div className="product-image-preview">
              {imagenesExistentes.map((imagen) => {
                const esPrincipal = Number(imagen.es_principal) === 1;
                const procesando =
                  Number(gestionandoImagenId) === Number(imagen.id);

                return (
                  <div
                    className="product-image-preview-item"
                    key={imagen.id}
                  >
                    <img
                      src={`/${imagen.ruta}`}
                      alt={imagen.texto_alternativo || formulario.nombre}
                    />

                    {esPrincipal && (
                      <span className="product-main-image-label">
                        Principal
                      </span>
                    )}

                    <span>{imagen.ruta.split("/").pop()}</span>

                    <div className="product-image-management">
                      <button
                        type="button"
                        onClick={() => gestionarImagen(imagen, "principal")}
                        disabled={
                          guardando ||
                          gestionandoImagenId !== null ||
                          esPrincipal
                        }
                      >
                        {procesando && !esPrincipal
                          ? "Procesando..."
                          : esPrincipal
                            ? "Imagen principal"
                            : "Establecer como principal"}
                      </button>

                      <button
                        type="button"
                        className="product-image-delete"
                        onClick={() => gestionarImagen(imagen, "eliminar")}
                        disabled={guardando || gestionandoImagenId !== null}
                      >
                        {procesando ? "Procesando..." : "Eliminar"}
                      </button>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        )}

        {/* SELECTOR DE NUEVAS IMÁGENES */}
        <label
          htmlFor="imagenes"
          className={`product-upload-zone ${
            totalImagenes >= 8 ? "upload-disabled" : ""
          }`}
        >
          <input
            id="imagenes"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            multiple
            onChange={manejarImagenes}
            disabled={totalImagenes >= 8 || guardando}
          />

          <div className="product-upload-icon">
            <svg
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="1.6"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <path d="M12 16V4" />
              <path d="m7 9 5-5 5 5" />
              <path d="M20 16.5v2A1.5 1.5 0 0 1 18.5 20h-13A1.5 1.5 0 0 1 4 18.5v-2" />
            </svg>
          </div>

          <div className="product-upload-text">
            <strong>
              Haz clic para seleccionar tus imágenes
            </strong>

            <span>
              JPG, PNG o WEBP · Máximo 5 MB por imagen
            </span>
          </div>

          <span className="product-upload-button">
            Explorar archivos
          </span>
        </label>

        {/* VISTA PREVIA DE NUEVAS IMÁGENES */}
        {imagenes.length > 0 && (
          <div className="product-image-preview">
            {imagenes.map((imagen, indice) => (
              <div
                className="product-image-preview-item"
                key={`${imagen.name}-${indice}`}
              >
                <img
                  src={URL.createObjectURL(imagen)}
                  alt={`Vista previa ${indice + 1}`}
                />

                <button
                  type="button"
                  onClick={() => eliminarImagenNueva(indice)}
                  disabled={guardando}
                  aria-label={`Eliminar ${imagen.name}`}
                >
                  ×
                </button>

                <span>{imagen.name}</span>
              </div>
            ))}
          </div>
        )}

        <p className="product-image-footer">
          <span>ⓘ</span> Puedes tener hasta 8 imágenes por producto.
          Las nuevas se optimizarán automáticamente al guardarlas.
        </p>

      </section>

      {/* MENSAJES */}
      {error && (
        <div className="product-form-error">
          {error}
        </div>
      )}

      {mensaje && (
        <div className="product-form-success">
          {mensaje}
        </div>
      )}

      {/* BOTÓN DE ACCIÓN */}
      <div className="product-form-actions">
        <button
          type="submit"
          disabled={guardando || categorias.length === 0}
        >
          {guardando
            ? "Guardando..."
            : modo === "editar"
              ? "Actualizar producto"
              : productoPendienteId
                ? "Reintentar carga de imágenes"
                : "Guardar producto"}
        </button>
      </div>

      {categorias.length === 0 && (
        <p className="product-form-error">
          No hay categorías activas disponibles.
        </p>
      )}

    </form>
  );
}

export default ProductForm;
