
import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import AdminLayout from "../../components/layout/AdminLayout.jsx";
import ProductForm from "../../components/products/ProductForm.jsx";

function EditProduct() {
  const { id } = useParams();
  const navigate = useNavigate();

  const [producto, setProducto] = useState(null);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    const cargarProducto = async () => {
      try {
        setCargando(true);
        setError("");

        const respuesta = await fetch(
          `/api/productos/obtener.php?id=${encodeURIComponent(id)}`,
          { credentials: "include" }
        );

        const resultado = await respuesta.json();

        if (respuesta.status === 401) {
          navigate("/admin/login", { replace: true });
          return;
        }

        if (!respuesta.ok || !resultado.success) {
          throw new Error(
            resultado.message || "No fue posible cargar el producto."
          );
        }

        setProducto(resultado.data);

      } catch (error) {
        setError(error.message);

      } finally {
        setCargando(false);
      }
    };

    cargarProducto();
  }, [id, navigate]);

  const productoActualizado = () => {
    navigate("/admin/productos");
  };

  return (
    <AdminLayout>
      <div className="products-page">
        <div className="products-heading">
          <div>
            <h1>Editar producto</h1>
            <p>Actualiza la información de tu producto.</p>
          </div>

          <button
            className="products-add-button"
            type="button"
            onClick={() => navigate("/admin/productos")}
          >
            Volver a productos
          </button>
        </div>

        {cargando ? (
          <p className="products-message">
            Cargando información del producto...
          </p>
        ) : error ? (
          <div className="products-error">
            {error}
          </div>
        ) : producto ? (
          <ProductForm
            modo="editar"
            initialData={producto}
            onSuccess={productoActualizado}
          />
        ) : null}
      </div>
    </AdminLayout>
  );
}

export default EditProduct;
