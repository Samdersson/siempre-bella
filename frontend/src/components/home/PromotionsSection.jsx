
import { useEffect, useState } from "react";
import { obtenerPromociones } from "../../services/api";
import { useCart } from "../../context/CartContext";
import logo from "../../assets/logo-siempre-bella.webp";

import "../../styles/promotions.css";

function PromotionsSection() {
    const [promociones, setPromociones] = useState([]);
    const [cargando, setCargando] = useState(true);
    const [error, setError] = useState(null);
    const [mensaje, setMensaje] = useState("");

    const { agregarProducto } = useCart();

    useEffect(() => {
        async function cargarPromociones() {
            try {
                const datos = await obtenerPromociones();
                setPromociones(datos);
            } catch (error) {
                console.error(error);
                setError("No fue posible cargar las promociones.");
            } finally {
                setCargando(false);
            }
        }

        cargarPromociones();
    }, []);

    const agregarAlCarrito = (promocion) => {
        const producto = {
            id: promocion.producto_id,
            nombre: promocion.producto,
            slug: promocion.slug,
            imagen: promocion.imagen,
            marca: promocion.marca,
            disponibilidad: promocion.disponibilidad,
            precio_minorista: promocion.precio_minorista,
            precio_promocional:
                promocion.precio_minorista_promocional,
        };

        const agregado = agregarProducto(producto);

        if (agregado) {
            setMensaje(`${promocion.producto} agregado al carrito.`);
        } else {
            setMensaje("No fue posible agregar este producto al carrito.");
        }
    };

    return (
        <section className="promotions-section">
            <div className="promotions-container">

                <div className="promotions-heading">
                    <span>SIEMPRE BELLA</span>
                    <h2>OFERTAS ESPECIALES</h2>
                    <p>
                        Descubre nuestras promociones y productos especiales.
                    </p>
                </div>

                {cargando && (
                    <p className="promotions-message">
                        Cargando promociones...
                    </p>
                )}

                {error && (
                    <p className="promotions-message">
                        {error}
                    </p>
                )}

                {!cargando && !error && promociones.length === 0 && (
                    <div className="promotions-empty">
                        <img src={logo} alt="Siempre Bella" />
                        <h3>Próximamente nuevas promociones</h3>
                        <p>
                            Estamos preparando ofertas especiales para ti.
                        </p>
                    </div>
                )}

                {!cargando && !error && promociones.length > 0 && (
                    <div className="promotions-grid">

                        {promociones.map((promocion) => (
                            <article
                                className="promotion-card"
                                key={promocion.promocion_id}
                            >
                                <img
                                    src={
                                        promocion.imagen
                                            ? `/${promocion.imagen.replace(/^\/+/, "")}`
                                            : logo
                                    }
                                    alt={promocion.producto}
                                    onError={(e) => {
                                        e.currentTarget.onerror = null;
                                        e.currentTarget.src = logo;
                                    }}
                                />

                                <div className="promotion-info">
                                    <h3>{promocion.producto}</h3>

                                    {promocion.marca && (
                                        <p>{promocion.marca}</p>
                                    )}

                                    {promocion.precio_minorista_promocional && (
                                        <strong>
                                            ${Number(
                                                promocion.precio_minorista_promocional
                                            ).toLocaleString("es-CO")}
                                        </strong>
                                    )}

                                    <button
                                        type="button"
                                        className="promotion-add-button"
                                        disabled={
                                            promocion.disponibilidad !== "disponible"
                                        }
                                        onClick={() => agregarAlCarrito(promocion)}
                                    >
                                        {promocion.disponibilidad === "disponible"
                                            ? "Agregar al carrito"
                                            : "Producto agotado"}
                                    </button>
                                </div>
                            </article>
                        ))}

                    </div>
                )}

                {mensaje && (
                    <p className="promotions-message" role="status">
                        {mensaje}
                    </p>
                )}

            </div>
        </section>
    );
}

export default PromotionsSection;
