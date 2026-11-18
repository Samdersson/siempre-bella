
import { useEffect, useState } from "react";
import { obtenerProductos } from "../../services/api";
import logo from "../../assets/logo siempre bella.webp";
import { useCart } from "../../context/CartContext";

import "../../styles/featured-products.css";

function FeaturedProducts() {

    const [productos, setProductos] = useState([]);
    const [cargando, setCargando] = useState(true);
    const [error, setError] = useState(null);
    const { agregarProducto } = useCart();
    const [productoAgregado, setProductoAgregado] = useState(null);

    useEffect(() => {

        async function cargarProductos() {
            try {
                const datos = await obtenerProductos();
                setProductos(datos);

            } catch (error) {
                console.error(error);
                setError("No fue posible cargar los productos.");

            } finally {
                setCargando(false);
            }
        }

        cargarProductos();

    }, []);

    return (
        <section className="featured-products">

            <div className="featured-container">

                <div className="featured-heading">
                    <h2>TIENDA SIEMPRE BELLA CAICEDONIA</h2>

                    <p>
                        Descubre maquillaje y skincare,
                        pensado para realzar tu belleza natural.
                    </p>

                    <div className="featured-tabs">
                        <button className="active">
                            Últimos lanzamientos
                        </button>

                        <button>
                            Los más vendidos
                        </button>
                    </div>
                </div>

                {cargando && (
                    <p className="featured-message">
                        Cargando productos...
                    </p>
                )}

                {error && (
                    <p className="featured-message error">
                        {error}
                    </p>
                )}

                {!cargando && !error && productos.length === 0 && (

                    <div className="featured-empty">

                        <img src={logo} alt="Siempre Bella" />

                        <h3>Próximamente nuevos productos</h3>

                        <p>
                            Estamos preparando nuestro catálogo
                            para ti.
                        </p>

                    </div>
                )}

                {!cargando && !error && productos.length > 0 && (

                    <div className="products-grid">

                        {productos.map((producto) => {

                            const tienePromocion =
                                producto.precio_promocional !== null &&
                                Number(producto.precio_promocional) > 0 &&
                                Number(producto.precio_promocional) <
                                Number(producto.precio_minorista);

                            const agotado =
                                producto.disponibilidad === "agotado";

                            return (

                                <article
                                    className="product-card"
                                    key={producto.id}
                                >

                                    <div className="product-image">

                                        <img
                                            src={
                                                producto.imagen
                                                    ? `/${producto.imagen.replace(/^\/+/, "")}`
                                                    : logo
                                            }
                                            alt={producto.nombre}
                                            onError={(e) => {
                                                e.currentTarget.onerror = null;
                                                e.currentTarget.src = logo;
                                            }}
                                        />

                                        {agotado && (
                                            <span className="product-badge">
                                                Agotado
                                            </span>
                                        )}

                                    </div>

                                    <div className="product-info">

                                        <h3>{producto.nombre}</h3>

                                        {producto.marca && (
                                            <p>{producto.marca}</p>
                                        )}

                                        <div className="product-prices">

                                            {tienePromocion && (
                                                <del>
                                                    ${Number(producto.precio_minorista).toLocaleString("es-CO")}
                                                </del>
                                            )}

                                            <strong>
                                                ${Number(
                                                    tienePromocion
                                                        ? producto.precio_promocional
                                                        : producto.precio_minorista
                                                ).toLocaleString("es-CO")}
                                            </strong>

                                        </div>

                                    <button
                                        disabled={agotado}
                                        className="product-button"
                                        onClick={() => {
                                            const agregado = agregarProducto(producto);

                                            if (agregado) {
                                                setProductoAgregado(producto.id);
                                            }
                                        }}
                                    >
                                        {agotado
                                            ? "Agotado"
                                            : Number(productoAgregado) === Number(producto.id)
                                                ? "¡Agregado!"
                                                : "Añadir al carrito"}
                                    </button>

                                    </div>

                                </article>

                            );

                        })}

                    </div>

                )}

            </div>

        </section>
    );
}

export default FeaturedProducts;
