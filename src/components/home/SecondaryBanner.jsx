
import { useEffect, useState } from "react";
import "../../styles/secondary-banner.css";

const API = "/api/banners/listar_secundarios.php";

function SecondaryBanner() {
    const [banners, setBanners] = useState([]);
    const [cargando, setCargando] = useState(true);

    useEffect(() => {
        let activo = true;

        async function cargarBanners() {
            try {
                const respuesta = await fetch(API);
                const json = await respuesta.json();

                if (!respuesta.ok || !json.success) {
                    throw new Error("No fue posible cargar los banners.");
                }

                if (activo) {
                    setBanners(json.data || []);
                }
            } catch (error) {
                console.error("Error cargando banners secundarios:", error);
            } finally {
                if (activo) setCargando(false);
            }
        }

        cargarBanners();

        return () => {
            activo = false;
        };
    }, []);

    if (cargando || banners.length === 0) {
        return null;
    }

    return (
        <section className="secondary-banner">
            <div className="secondary-banner-grid">

                {banners.map((banner) => (
                    <article className="secondary-banner-card" key={banner.id}>

                        <picture className="secondary-banner-image">
                            {banner.imagen_movil_url && (
                                <source
                                    media="(max-width: 600px)"
                                    srcSet={banner.imagen_movil_url}
                                />
                            )}

                            <img
                                src={banner.imagen_url}
                                alt={banner.titulo || banner.nombre}
                                loading="lazy"
                            />
                        </picture>

                        <div className="secondary-banner-content">
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
                                    className="secondary-banner-button"
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

export default SecondaryBanner;
