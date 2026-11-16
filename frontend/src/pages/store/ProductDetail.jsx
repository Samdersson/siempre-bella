
import { useEffect, useState } from "react";
import { Link, useParams } from "react-router-dom";

import { obtenerProductoPorSlug } from "../../services/api";
import { useCart } from "../../context/CartContext";

import logo from "../../assets/logo siempre bella.webp";
import "../../styles/product-detail.css";

function ProductDetail() {
    const { slug } = useParams();
    const { agregarProducto } = useCart();

    const [producto, setProducto] = useState(null);
    const [imagenSeleccionada, setImagenSeleccionada] = useState(null);
    const [cantidad, setCantidad] = useState(1);

    const [cargando, setCargando] = useState(true);
    const [error, setError] = useState(null);
    const [agregado, setAgregado] = useState(false);

    useEffect(() => {
        let cancelado = false;

        async function cargarProducto() {
            setCargando(true);
            setError(null);
            setProducto(null);
            setImagenSeleccionada(null);
            setCantidad(1);
            setAgregado(false);

            try {
                const datos = await obtenerProductoPorSlug(slug);

                if (!cancelado) {
                    setProducto(datos);

                    const principal =
                        datos.imagen ||
                        datos.imagenes?.[0]?.ruta ||
                        null;

                    setImagenSeleccionada(principal);
                }
            } catch (error) {
                console.error("Error al cargar el producto:", error);

                if (!cancelado) {
                    setError("No fue posible encontrar este producto.");
                }
            } finally {
                if (!cancelado) {
                    setCargando(false);
                }
            }
        }

        cargarProducto();

        return () => {
            cancelado = true;
        };
    }, [slug]);

    const cambiarCantidad = (valor) => {
        setCantidad((actual) => Math.max(1, actual + valor));
        setAgregado(false);
    };

    const agregarAlCarrito = () => {
        if (!producto || producto.disponibilidad !== "disponible") {
            return;
        }

        const resultado = agregarProducto(producto, cantidad);

        if (resultado) {
            setAgregado(true);
        }
    };

    if (cargando) {
        return (
            <section className="product-detail-message">
                Cargando producto...
            </section>
        );
    }

    if (error || !producto) {
        return (
            <section className="product-detail-message">
                <h2>Producto no encontrado</h2>
                <p>{error}</p>
                <Link to="/tienda">Volver a la tienda</Link>
            </section>
        );
    }

    const precioNormal = Number(producto.precio_minorista);
    const precioPromocional = Number(producto.precio_promocional);

    const tienePromocion =
        producto.precio_promocional !== null &&
        producto.precio_promocional !== undefined &&
        precioPromocional > 0 &&
        precioPromocional < precioNormal;

    const precioFinal = tienePromocion
        ? precioPromocional
        : precioNormal;

    const agotado = producto.disponibilidad !== "disponible";

    const imagenes = producto.imagenes?.length
        ? producto.imagenes
        : producto.imagen
            ? [{
                id: "principal",
                ruta: producto.imagen,
                texto_alternativo: producto.nombre
            }]
            : [];

    const rutaImagen = (ruta) =>
        ruta ? `/${ruta.replace(/^\/+/, "")}` : logo;

    return (
        <section className="product-detail-page">

            <div className="product-detail-container">

                <nav className="product-breadcrumb">
                    <Link to="/">Inicio</Link>
                    <span>/</span>
                    <Link to="/tienda">Tienda</Link>
                    <span>/</span>
                    <Link to={`/categoria/${producto.categoria_slug}`}>
                        {producto.categoria}
                    </Link>
                    <span>/</span>
                    <span>{producto.nombre}</span>
                </nav>

                <div className="product-detail-layout">

                    <div className="product-gallery">

                        <div className="product-main-image">

                            {tienePromocion && (
                                <span className="product-detail-promo">
                                    Oferta especial
                                </span>
                            )}

                            {agotado && (
                                <span className="product-detail-soldout">
                                    Agotado
                                </span>
                            )}

                            <img
                                src={rutaImagen(imagenSeleccionada)}
                                alt={producto.nombre}
                                onError={(e) => {
                                    e.currentTarget.onerror = null;
                                    e.currentTarget.src = logo;
                                }}
                            />

                        </div>

                        {imagenes.length > 1 && (
                            <div className="product-thumbnails">

                                {imagenes.map((imagen) => (
                                    <button
                                        type="button"
                                        key={imagen.id}
                                        className={
                                            imagenSeleccionada === imagen.ruta
                                                ? "selected"
                                                : ""
                                        }
                                        onClick={() =>
                                            setImagenSeleccionada(imagen.ruta)
                                        }
                                        aria-label="Ver fotografía del producto"
                                    >
                                        <img
                                            src={rutaImagen(imagen.ruta)}
                                            alt={
                                                imagen.texto_alternativo ||
                                                producto.nombre
                                            }
                                            onError={(e) => {
                                                e.currentTarget.onerror = null;
                                                e.currentTarget.src = logo;
                                            }}
                                        />
                                    </button>
                                ))}

                            </div>
                        )}

                    </div>

                    <div className="product-detail-info">

                        <span className="product-detail-category">
                            {producto.categoria}
                        </span>

                        <h1>{producto.nombre}</h1>

                        {producto.marca && (
                            <p className="product-detail-brand">
                                Marca: {producto.marca}
                            </p>
                        )}

                        <div className="product-detail-prices">

                            {tienePromocion && (
                                <del>
                                    ${precioNormal.toLocaleString("es-CO")}
                                </del>
                            )}

                            <strong>
                                ${precioFinal.toLocaleString("es-CO")}
                            </strong>

                            {tienePromocion && (
                                <span>Precio promocional</span>
                            )}

                        </div>

                        <div className="product-detail-availability">
                            <span className={agotado ? "unavailable" : "available"}>
                                {agotado ? "Producto agotado" : "Producto disponible"}
                            </span>
                        </div>

                        {producto.descripcion && (
                            <div className="product-description">
                                <h3>Descripción</h3>
                                <p>{producto.descripcion}</p>
                            </div>
                        )}

                        <div className="product-quantity">
                            <span>Cantidad</span>

                            <div className="quantity-controls">

                                <button
                                    type="button"
                                    onClick={() => cambiarCantidad(-1)}
                                    disabled={cantidad <= 1}
                                    aria-label="Disminuir cantidad"
                                >
                                    −
                                </button>

                                <span>{cantidad}</span>

                                <button
                                    type="button"
                                    onClick={() => cambiarCantidad(1)}
                                    aria-label="Aumentar cantidad"
                                >
                                    +
                                </button>

                            </div>
                        </div>

                        <div className="product-detail-subtotal">
                            <span>Subtotal</span>
                            <strong>
                                ${(precioFinal * cantidad).toLocaleString("es-CO")}
                            </strong>
                        </div>

                        <button
                            type="button"
                            className="product-detail-add"
                            disabled={agotado}
                            onClick={agregarAlCarrito}
                        >
                            {agotado
                                ? "Producto agotado"
                                : agregado
                                    ? "¡Agregado al carrito!"
                                    : "Agregar al carrito"}
                        </button>

                        <Link
                            to={`/categoria/${producto.categoria_slug}`}
                            className="product-back-link"
                        >
                            Seguir explorando esta categoría
                        </Link>

                    </div>

                </div>

            </div>

        </section>
    );
}

export default ProductDetail;
