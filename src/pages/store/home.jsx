
import { useEffect, useState } from "react";
import { obtenerSecciones } from "../../services/api";

import HeroSection from "../../components/home/HeroSection";
import CategoriesSection from "../../components/home/CategoriesSection";
import FeaturedProducts from "../../components/home/FeaturedProducts";
import PromotionsSection from "../../components/home/PromotionsSection";
import SecondaryBanner from "../../components/home/SecondaryBanner";
import PromotionalBanners from "../../components/home/PromotionalBanners";

function Home() {

    const [secciones, setSecciones] = useState([]);
    const [cargando, setCargando] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {

        async function cargarSecciones() {
            try {
                const datos = await obtenerSecciones();
                setSecciones(datos);

            } catch (error) {
                console.error(error);
                setError("No fue posible cargar las secciones.");

            } finally {
                setCargando(false);
            }
        }

        cargarSecciones();

    }, []);

    if (cargando) {
        return <p>Cargando tienda...</p>;
    }

    if (error) {
        return <p>{error}</p>;
    }

    return (
        <main className="home">

    {secciones.map((seccion) => {

                switch (seccion.identificador) {

                    case "banner_principal":
                        return <HeroSection key={seccion.id} />;

                    case "categorias_destacadas":
                        return <CategoriesSection key={seccion.id} />;

                    case "productos_destacados":
                        return <FeaturedProducts key={seccion.id} />;

                    case "banner_promocional":
                        return <PromotionalBanners key={seccion.id} />;

                    case "promociones":
                        return <PromotionsSection key={seccion.id} />;

                    case "banner_secundario":
                        return <SecondaryBanner key={seccion.id} />;

                    default:
                        return null;
                }

            })}

        </main>
    );
}

export default Home;
