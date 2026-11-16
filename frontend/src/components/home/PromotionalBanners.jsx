
import { useEffect, useState } from "react";
import "../../styles/promotional-banners.css";

function PromotionalBanners() {

    const [banners, setBanners] = useState([]);
    const [cargando, setCargando] = useState(true);
    const [error, setError] = useState(false);

    useEffect(() => {

        async function cargarBanners() {

            try {

                const respuesta = await fetch(
                    "/api/banners/listar_promocionales.php"
                );

                if (!respuesta.ok) {
                    throw new Error("Error al consultar los banners.");
                }

                const resultado = await respuesta.json();

                if (!resultado.success) {
                    throw new Error(resultado.message);
                }

                setBanners(resultado.data || []);

            } catch (error) {

                console.error("Error en banners promocionales:", error);
                setError(true);

            } finally {

                setCargando(false);

            }
        }

        cargarBanners();

    }, []);

    if (cargando || error || banners.length === 0) {
        return null;
    }

    return (

        <section className="promotional-banners-section">

            <div className="promotional-banners-container">

                {banners.map((banner) => (

                    <article
                        className="promotional-banner-card"
                        key={banner.id}
                    >

                        <picture className="promotional-banner-image">

                            {banner.imagen_movil_url && (
                                <source
                                    media="(max-width: 768px)"
                                    srcSet={banner.imagen_movil_url}
                                />
                            )}

                            <img
                                src={banner.imagen_url}
                                alt={banner.nombre}
                                loading="lazy"
                            />

                        </picture>

                        <div className="promotional-banner-overlay"></div>

                        <div className="promotional-banner-content">

                            <span>SIEMPRE BELLA</span>

                            {banner.titulo && (
                                <h2>{banner.titulo}</h2>
                            )}

                            {banner.subtitulo && (
                                <p>{banner.subtitulo}</p>
                            )}

                            {banner.texto_boton && banner.enlace_boton && (

                                <a
                                    href={banner.enlace_boton}
                                    className="promotional-banner-button"
                                >
                                    {banner.texto_boton}
                                </a>

                            )}

                        </div>

                    </article>

                ))}

            </div>

        </section>

    );
}

export default PromotionalBanners;
