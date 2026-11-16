
import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import AdminLayout from "../../components/layout/AdminLayout.jsx";
import "../../styles/admin-products.css";

function Products() {
  const navigate = useNavigate();

  const [productos, setProductos] = useState([]);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState("");

  // Cargar productos desde el backend
  useEffect(() => {
    const cargarProductos = async () => {
      try {
        const respuesta = await fetch(
          "/api/productos/listar_admin.php",
          {
            credentials: "include"
          }
        );

        const resultado = await respuesta.json();

        if (respuesta.status === 401) {
          navigate("/admin/login", { replace: true });
          return;
        }

        if (!respuesta.ok || !resultado.success) {
          throw new Error(
            resultado.message || "Error al consultar productos."
          );
        }

        setProductos(resultado.data);

      } catch (error) {
        setError(
          error.message || "No fue posible cargar los productos."
        );

      } finally {
        setCargando(false);
      }
    };

    cargarProductos();
  }, [navigate]);

  // Formatear precios en pesos colombianos
  const formatearPrecio = (precio) => {
    return Number(precio || 0).toLocaleString("es-CO", {
      style: "currency",
      currency: "COP",
      maximumFractionDigits: 0
    });
  };

  // Navegar al formulario de edición
  const editarProducto = (id) => {
    navigate(`/admin/productos/editar/${id}`);
  };

  // Navegar al formulario de creación
  const nuevoProducto = () => {
    navigate("/admin/productos/nuevo");
  };

  return (
    <AdminLayout>
      <div className="products-page">

        {/* ENCABEZADO */}
        <div className="products-heading">
          <div>
            <h1>Productos</h1>
            <p>Administra el catálogo de Siempre Bella.</p>
          </div>

          <button
            className="products-add-button"
            onClick={nuevoProducto}
          >
            + Nuevo producto
          </button>
        </div>

        {/* RESUMEN */}
        <div className="products-summary">
          <div>
            <span>Total de productos</span>
            <strong>{productos.length}</strong>
          </div>
        </div>

        {/* TABLA DE PRODUCTOS */}
        <div className="products-table-container">

          {cargando ? (

            <p className="products-message">
              Cargando productos...
            </p>

          ) : error ? (

            <p className="products-error">
              {error}
            </p>

          ) : productos.length === 0 ? (

            <div className="products-empty">
              <h3>Aún no tienes productos registrados</h3>
              <p>
                Comienza agregando el primer producto de tu tienda.
              </p>
            </div>

          ) : (

            <table className="products-table">

              <thead>
                <tr>
                  <th>Producto</th>
                  <th>SKU</th>
                  <th>Categoría</th>
                  <th>Marca</th>
                  <th>Precio minorista</th>
                  <th>Precio mayorista</th>
                  <th>Estado</th>
                  <th>Acciones</th>
                </tr>
              </thead>

              <tbody>

                {productos.map((producto) => (

                  <tr key={producto.id}>

                    {/* PRODUCTO E IMAGEN */}
                    <td>
                      <div className="product-table-info">

                        <div className="product-table-image">

                          {producto.imagen ? (
                            <img
                              src={`/${producto.imagen}`}
                              alt={producto.nombre}
                              loading="lazy"
                              onError={(e) => {
                                e.currentTarget.style.display = "none";
                              }}
                            />
                          ) : (
                            <img
                              src="/src/assets/logo%20siempre%20bella.webp"
                              alt="Siempre Bella"
                              className="product-placeholder-logo"
                            />
                          )}

                        </div>

                        <strong>{producto.nombre}</strong>

                      </div>
                    </td>

                    {/* SKU */}
                    <td>
                      {producto.sku || "—"}
                    </td>

                    {/* CATEGORÍA */}
                    <td>
                      {producto.categoria || "—"}
                    </td>

                    {/* MARCA */}
                    <td>
                      {producto.marca || "—"}
                    </td>

                    {/* PRECIO MINORISTA */}
                    <td>
                      {formatearPrecio(producto.precio_minorista)}
                    </td>

                    {/* PRECIO MAYORISTA */}
                    <td>
                      {formatearPrecio(producto.precio_mayorista)}
                    </td>

                    {/* ESTADO */}
                    <td>
                      <span
                        className={
                          Number(producto.activo) === 1
                            ? "product-status active"
                            : "product-status inactive"
                        }
                      >
                        {Number(producto.activo) === 1
                          ? "Activo"
                          : "Inactivo"}
                      </span>
                    </td>

                    {/* ACCIONES */}
                    <td>
                      <button
                        type="button"
                        className="product-edit-button"
                        onClick={() => editarProducto(producto.id)}
                      >
                        Editar
                      </button>
                    </td>

                  </tr>

                ))}

              </tbody>

            </table>

          )}

        </div>
      </div>
    </AdminLayout>
  );
}

export default Products;
