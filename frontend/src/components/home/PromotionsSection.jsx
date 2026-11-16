
import { useEffect, useState } from "react";
import { obtenerPromociones } from "../../services/api";
import logo from "../../assets/logo siempre bella.webp";

import "../../styles/promotions.css";

function PromotionsSection() {

    const [promociones, setPromociones] = useState([]);
    const [cargando, setCargando] = useState(true);
    const [error, setError] = useState(null);

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
                                            ? `/backend-uploads/${promocion.imagen}`
                                            : logo
                                    }
                                    alt={promocion.producto}
                                    onError={(e) => {
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

                                </div>

                            </article>

                        ))}

                    </div>
                )}

            </div>

        </section>
    );
}

export default PromotionsSection;
