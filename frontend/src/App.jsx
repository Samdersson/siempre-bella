
import { BrowserRouter, Routes, Route } from "react-router-dom";

// Componentes de la tienda
import Header from "./components/layout/header";
import Footer from "./components/layout/footer";
import Reports from "./pages/admin/Reports.jsx";

// Contextos de los carritos
import { CartProvider } from "./context/CartContext";
import { WholesaleProvider } from "./context/WholesaleContext";

// Protección administrativa
import ProtectedRoute from "./components/admin/ProtectedRoute";

// Páginas públicas
import Home from "./pages/store/home";
import Catalog from "./pages/store/catalog";
import ProductDetail from "./pages/store/ProductDetail";
import Cart from "./pages/store/Cart";
import Wholesale from "./pages/store/Wholesale";

// Páginas administrativas
import Login from "./pages/admin/login";
import Dashboard from "./pages/admin/Dashboard";
import Products from "./pages/admin/Products.jsx";
import NewProduct from "./pages/admin/NewProduct.jsx";
import EditProduct from "./pages/admin/EditProduct.jsx";
import Categories from "./pages/admin/Categories.jsx";
import Brands from "./pages/admin/Brands.jsx";
import Promotions from "./pages/admin/Promotions.jsx";
import MediaLibrary from "./pages/admin/MediaLibrary.jsx";
import Banners from "./pages/admin/Banners.jsx";
import Configuration from "./pages/admin/Configuration.jsx";
import WholesaleOrders from "./pages/admin/WholesaleOrders.jsx";

// Estilos
import "./styles/App.css";

// Diseño general de la tienda pública
function StoreLayout({ children }) {
  return (
    <>
      <Header />
      <main>{children}</main>
      <Footer />
    </>
  );
}

function App() {
  return (
    <CartProvider>
      <WholesaleProvider>
        <BrowserRouter>
          <Routes>

            {/* ==============================
                TIENDA PÚBLICA
            ============================== */}

            <Route
              path="/"
              element={
                <StoreLayout>
                  <Home />
                </StoreLayout>
              }
            />

            <Route
              path="/categoria/:slug"
              element={
                <StoreLayout>
                  <Catalog />
                </StoreLayout>
              }
            />

            <Route
              path="/producto/:slug"
              element={
                <StoreLayout>
                  <ProductDetail />
                </StoreLayout>
              }
            />

            {/* Carrito minorista */}
            <Route
              path="/carrito"
              element={
                <StoreLayout>
                  <Cart />
                </StoreLayout>
              }
            />

            {/* Tienda mayorista */}
            <Route
              path="/mayoristas"
              element={
                <StoreLayout>
                  <Wholesale />
                </StoreLayout>
              }
            />

            {/* ==============================
                AUTENTICACIÓN
            ============================== */}

            <Route
              path="/admin/login"
              element={<Login />}
            />

            {/* ==============================
                PANEL ADMINISTRATIVO
            ============================== */}

            <Route
              path="/admin"
              element={
                <ProtectedRoute>
                  <Dashboard />
                </ProtectedRoute>
              }
            />

            {/* PRODUCTOS */}
            <Route
              path="/admin/productos"
              element={
                <ProtectedRoute>
                  <Products />
                </ProtectedRoute>
              }
            />

            <Route
              path="/admin/productos/nuevo"
              element={
                <ProtectedRoute>
                  <NewProduct />
                </ProtectedRoute>
              }
            />

            <Route
              path="/admin/productos/editar/:id"
              element={
                <ProtectedRoute>
                  <EditProduct />
                </ProtectedRoute>
              }
            />

            {/* CATEGORÍAS */}
            <Route
              path="/admin/categorias"
              element={
                <ProtectedRoute>
                  <Categories />
                </ProtectedRoute>
              }
            />

            {/* MARCAS */}
            <Route
              path="/admin/marcas"
              element={
                <ProtectedRoute>
                  <Brands />
                </ProtectedRoute>
              }
            />

            {/* PROMOCIONES */}
            <Route
              path="/admin/promociones"
              element={
                <ProtectedRoute>
                  <Promotions />
                </ProtectedRoute>
              }
            />

            {/* BIBLIOTECA MULTIMEDIA */}
            <Route
              path="/admin/multimedia"
              element={
                <ProtectedRoute>
                  <MediaLibrary />
                </ProtectedRoute>
              }
            />

            {/* BANNERS Y CONTENIDO */}
            <Route
              path="/admin/contenido"
              element={
                <ProtectedRoute>
                  <Banners />
                </ProtectedRoute>
              }
            />

            {/* CONFIGURACIÓN */}
            <Route
              path="/admin/configuracion"
              element={
                <ProtectedRoute>
                  <Configuration />
                </ProtectedRoute>
              }
            />

            <Route
              path="/admin/pedidos-mayoristas"
              element={
                <ProtectedRoute>
                  <WholesaleOrders />
                </ProtectedRoute>
              }
            />

            {/* REPORTES COMERCIALES */}
            <Route
              path="/admin/reportes"
              element={
                <ProtectedRoute>
                  <Reports />
                </ProtectedRoute>
              }
            />

            <Route
                path="/tienda"
                element={
                    <StoreLayout>
                        <Catalog />
                    </StoreLayout>
                }
            />

          </Routes>
        </BrowserRouter>
      </WholesaleProvider>
    </CartProvider>
  );
}

export default App;
