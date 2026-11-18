
import { useNavigate } from "react-router-dom";
import AdminLayout from "../../components/layout/AdminLayout.jsx";
import ProductForm from "../../components/products/ProductForm.jsx";

function NewProduct() {
  const navigate = useNavigate();

  const productoCreado = () => {
    navigate("/admin/productos");
  };

  return (
    <AdminLayout>
      <div className="products-page">
        <div className="products-heading">
          <div>
            <h1>Nuevo producto</h1>
            <p>Registra un nuevo producto en Siempre Bella.</p>
          </div>
        </div>

        <ProductForm onSuccess={productoCreado} />
      </div>
    </AdminLayout>
  );
}

export default NewProduct;
