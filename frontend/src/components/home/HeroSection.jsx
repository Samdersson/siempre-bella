
import { useEffect, useState } from "react";
import { ChevronLeft, ChevronRight } from "lucide-react";
import logo from "../../assets/logo-siempre-bella.webp";
import "../../styles/hero.css";

const API = "/api/banners/listar_publicos.php";
const INTERVALO = 5000;

function HeroSection() {
    const [banners, setBanners] = useState([]);
    const [indice, setIndice] = useState(0);
    const [cargando, setCargando] = useState(true);
    const [pausado, setPausado] = useState(false);

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
                console.error("Error cargando banners:", error);
            } finally {
                if (activo) setCargando(false);
            }
        }

        cargarBanners();

        return () => {
            activo = false;
        };
    }, []);

    useEffect(() => {
        if (banners.length <= 1 || pausado) return;

        const intervalo = setInterval(() => {
            setIndice((actual) => (actual + 1) % banners.length);
        }, INTERVALO);

        return () => clearInterval(intervalo);
    }, [banners.length, pausado]);

    const anterior = () => {
        setIndice((actual) =>
            actual === 0 ? banners.length - 1 : actual - 1
        );
    };

    const siguiente = () => {
        setIndice((actual) => (actual + 1) % banners.length);
    };

    const irA = (nuevoIndice) => {
        setIndice(nuevoIndice);
    };

    if (cargando) {
        return (
            <section className="hero-section hero-loading">
                <span>Cargando Siempre Bella...</span>
            </section>
        );
    }

    // Portada alternativa si todavía no hay banners publicados
    if (banners.length === 0) {
        return (
            <section className="hero-section hero-fallback">
                <div className="hero-content">
                    <img src={logo} alt="Logo Siempre Bella" className="hero-logo" />
                    <h1>Siempre Bella</h1>
                    <p>Descubre un mundo de belleza, cuidado y estilo.</p>
                    <a href="/categoria/cosmeticos" className="hero-button">
                        Descubrir productos
                    </a>
                </div>
            </section>
        );
    }

    const banner = banners[indice];

    return (
        <section
            className="hero-carousel"
            onMouseEnter={() => setPausado(true)}
            onMouseLeave={() => setPausado(false)}
            aria-roledescription="carrusel"
            aria-label="Banners principales de Siempre Bella"
        >
            <div className="hero-slide" key={banner.id}>
                <picture>
                    {banner.imagen_movil_url && (
                        <source
                            media="(max-width: 600px)"
                            srcSet={banner.imagen_movil_url}
                        />
                    )}

                    <img
                        className="hero-slide-image"
                        src={banner.imagen_url}
                        alt={banner.titulo || banner.nombre}
                        fetchPriority={indice === 0 ? "high" : "auto"}
                    />
                </picture>

                {(banner.titulo || banner.subtitulo || banner.texto_boton) && (
                    <div className="hero-slide-overlay">
                        {banner.titulo && <h1>{banner.titulo}</h1>}

                        {banner.subtitulo && <p>{banner.subtitulo}</p>}

                        {banner.texto_boton && banner.enlace_boton && (
                            <a
                                href={banner.enlace_boton}
                                className="hero-button"
                            >
                                {banner.texto_boton}
                            </a>
                        )}
                    </div>
                )}
            </div>

            {banners.length > 1 && (
                <>
                    <button
                        type="button"
                        className="hero-arrow hero-arrow-left"
                        onClick={anterior}
                        aria-label="Banner anterior"
                    >
                        <ChevronLeft size={27} />
                    </button>

                    <button
                        type="button"
                        className="hero-arrow hero-arrow-right"
                        onClick={siguiente}
                        aria-label="Banner siguiente"
                    >
                        <ChevronRight size={27} />
                    </button>

                    <div className="hero-indicators">
                        {banners.map((item, i) => (
                            <button
                                key={item.id}
                                type="button"
                                className={i === indice ? "active" : ""}
                                onClick={() => irA(i)}
                                aria-label={`Ir al banner ${i + 1}`}
                                aria-current={i === indice ? "true" : undefined}
                            />
                        ))}
                    </div>
                </>
            )}
        </section>
    );
}

export default HeroSection;
