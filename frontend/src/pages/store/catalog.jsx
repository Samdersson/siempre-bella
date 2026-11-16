
import { useEffect, useMemo, useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";

import {
    obtenerCatalogo,
    obtenerCategorias
} from "../../services/api";

import { useCart } from "../../context/CartContext";

import logo from "../../assets/logo siempre bella.webp";
import "../../styles/catalog.css";

function Catalog() {
    const { slug } = useParams();
    const navigate = useNavigate();
    const { agregarProducto } = useCart();

    const [productos, setProductos] = useState([]);
    const [categorias, setCategorias] = useState([]);
    const [busqueda, setBusqueda] = useState("");
    const [marca, setMarca] = useState("");
    const [orden, setOrden] = useState("recientes");

    const [cargando, setCargando] = useState(true);
    const [error, setError] = useState(null);
    const [productoAgregado, setProductoAgregado] = useState(null);

    useEffect(() => {
        async function cargarCategorias() {
            try {
                const datos = await obtenerCategorias();
                setCategorias(datos);
            } catch (error) {
                console.error("Error al cargar categorías:", error);
            }
        }

        cargarCategorias();
    }, []);

    useEffect(() => {
        let cancelado = false;

        async function cargarCatalogo() {
            setCargando(true);
            setError(null);

            try {
                const datos = await obtenerCatalogo({
                    categoria: slug || "",
                    buscar: busqueda.trim(),
                    marca,
                    orden
                });

                if (!cancelado) {
                    setProductos(datos);
                }
            } catch (error) {
                console.error("Error al cargar catálogo:", error);

                if (!cancelado) {
                    setError("No fue posible cargar los productos.");
                }
            } finally {
                if (!cancelado) {
                    setCargando(false);
                }
            }
        }

        cargarCatalogo();

        return () => {
            cancelado = true;
        };
    }, [slug, busqueda, marca, orden]);

    const categoriaActual = categorias.find(
        (categoria) => categoria.slug === slug
    );

    const marcas = useMemo(() => {
        const disponibles = productos
            .filter((producto) => producto.marca && producto.marca_slug)
            .map((producto) => ({
                nombre: producto.marca,
                slug: producto.marca_slug
            }));

        return Array.from(
            new Map(disponibles.map((item) => [item.slug, item])).values()
        );
    }, [productos]);

    const agregarAlCarrito = (producto) => {
        const agregado = agregarProducto(producto);

        if (agregado) {
            setProductoAgregado(producto.id);

            setTimeout(() => {
                setProductoAgregado(null);
            }, 1800);
        }
    };

    return (
        <section className="catalog-page">

            <div className="catalog-container">

                <header className="catalog-heading">
                    <span>SIEMPRE BELLA</span>

                    <h1>
                        {categoriaActual
                            ? categoriaActual.nombre
                            : "NUESTROS PRODUCTOS"}
                    </h1>

                    <p>
                        Descubre nuestros productos de belleza y cuidado personal.
                    </p>
                </header>

                <div className="catalog-categories">

                    <button
                        type="button"
                        className={!slug ? "active" : ""}
                        onClick={() => navigate("/tienda")}
                    >
                        Todos
                    </button>

                    {categorias.map((categoria) => (
                        <button
                            type="button"
                            key={categoria.id}
                            className={slug === categoria.slug ? "active" : ""}
                            onClick={() => navigate(`/categoria/${categoria.slug}`)}
                        >
                            {categoria.nombre}
                        </button>
                    ))}

                </div>

                <div className="catalog-toolbar">

                    <div className="catalog-search">
                        <input
                            type="search"
                            placeholder="Buscar productos..."
                            value={busqueda}
                            onChange={(e) => setBusqueda(e.target.value)}
                            aria-label="Buscar productos"
                        />
                    </div>

                    <div className="catalog-filters">

                        <select
                            value={marca}
                            onChange={(e) => setMarca(e.target.value)}
                            aria-label="Filtrar por marca"
                        >
                            <option value="">Todas las marcas</option>

                            {marcas.map((item) => (
                                <option key={item.slug} value={item.slug}>
                                    {item.nombre}
                                </option>
                            ))}
                        </select>

                        <select
                            value={orden}
                            onChange={(e) => setOrden(e.target.value)}
                            aria-label="Ordenar productos"
                        >
                            <option value="recientes">Más recientes</option>
                            <option value="nombre_asc">Nombre A-Z</option>
                            <option value="nombre_desc">Nombre Z-A</option>
                            <option value="precio_asc">Menor precio</option>
                            <option value="precio_desc">Mayor precio</option>
                        </select>

                    </div>

                </div>

                <div className="catalog-results">
                    <span>
                        {cargando
                            ? "Cargando productos..."
                            : `${productos.length} productos encontrados`}
                    </span>
                </div>

                {error && (
                    <div className="catalog-message catalog-error">
                        {error}
                    </div>
                )}

                {!cargando && !error && productos.length === 0 && (
                    <div className="catalog-empty">
                        <img src={logo} alt="Siempre Bella" />

                        <h2>No encontramos productos</h2>

                        <p>
                            Intenta cambiar tu búsqueda o seleccionar otra categoría.
                        </p>

                        <button
                            type="button"
                            onClick={() => {
                                setBusqueda("");
                                setMarca("");
                                navigate("/tienda");
                            }}
                        >
                            Ver todos los productos
                        </button>
                    </div>
                )}

                {!error && productos.length > 0 && (
                    <div className="catalog-grid">

                        {productos.map((producto) => {

                            const precioNormal = Number(producto.precio_minorista);
                            const precioPromocional = Number(producto.precio_promocional);

                            const tienePromocion =
                                producto.precio_promocional !== null &&
                                producto.precio_promocional !== undefined &&
                                precioPromocional > 0 &&
                                precioPromocional < precioNormal;

                            const agotado =
                                producto.disponibilidad === "agotado";

                            return (
                                <article
                                    className="catalog-product-card"
                                    key={producto.id}
                                >

                                    <Link
                                        to={`/producto/${producto.slug}`}
                                        className="catalog-product-image"
                                    >
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

                                        {tienePromocion && (
                                            <span className="catalog-promo-badge">
                                                Oferta
                                            </span>
                                        )}

                                        {agotado && (
                                            <span className="catalog-soldout-badge">
                                                Agotado
                                            </span>
                                        )}
                                    </Link>

                                    <div className="catalog-product-info">

                                        {producto.marca && (
                                            <span className="catalog-brand">
                                                {producto.marca}
                                            </span>
                                        )}

                                        <Link
                                            to={`/producto/${producto.slug}`}
                                            className="catalog-product-name"
                                        >
                                            {producto.nombre}
                                        </Link>

                                        <div className="catalog-prices">

                                            {tienePromocion && (
                                                <del>
                                                    ${precioNormal.toLocaleString("es-CO")}
                                                </del>
                                            )}

                                            <strong>
                                                ${(
                                                    tienePromocion
                                                        ? precioPromocional
                                                        : precioNormal
                                                ).toLocaleString("es-CO")}
                                            </strong>

                                        </div>

                                        <button
                                            type="button"
                                            className="catalog-add-button"
                                            disabled={agotado}
                                            onClick={() => agregarAlCarrito(producto)}
                                        >
                                            {agotado
                                                ? "Producto agotado"
                                                : Number(productoAgregado) === Number(producto.id)
                                                    ? "¡Agregado!"
                                                    : "Agregar al carrito"}
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

export default Catalog;
