
import { useEffect, useState } from "react";
import { Link } from "react-router-dom";

import { obtenerCategorias } from "../../services/api";

import logo from "../../assets/logo-siempre-bella.webp";
import "../../styles/categories.css";

function CategoriesSection() {

    const [categorias, setCategorias] = useState([]);
    const [cargando, setCargando] = useState(true);
    const [error, setError] = useState(false);

    useEffect(() => {
        let cancelado = false;

        async function cargarCategorias() {
            try {
                const datos = await obtenerCategorias();

                if (!cancelado) {
                    setCategorias(datos);
                }

            } catch (error) {
                console.error("Error al cargar categorías:", error);

                if (!cancelado) {
                    setError(true);
                }

            } finally {
                if (!cancelado) {
                    setCargando(false);
                }
            }
        }

        cargarCategorias();

        return () => {
            cancelado = true;
        };
    }, []);

    if (cargando) {
        return (
            <section className="categories-section">
                <p className="categories-message">
                    Cargando categorías...
                </p>
            </section>
        );
    }

    if (error) {
        return (
            <section className="categories-section">
                <p className="categories-message">
                    No fue posible cargar las categorías.
                </p>
            </section>
        );
    }

    return (
        <section className="categories-section">

            <div className="categories-container">

                <h2 className="categories-title">
                    COMPRA POR CATEGORÍAS
                </h2>

                <div className="categories-grid">

                    {categorias.map((categoria) => (

                        <Link
                            to={`/categoria/${categoria.slug}`}
                            className="category-card"
                            key={categoria.id}
                        >

                            <div className="category-image">

                                <img
                                    src={
                                        categoria.imagen
                                            ? `/${categoria.imagen.replace(/^\/+/, "")}`
                                            : logo
                                    }
                                    alt={categoria.nombre}
                                    loading="lazy"
                                    onError={(e) => {
                                        e.currentTarget.onerror = null;
                                        e.currentTarget.src = logo;
                                    }}
                                />

                            </div>

                            <h3>{categoria.nombre}</h3>

                        </Link>

                    ))}

                </div>

            </div>

        </section>
    );
}

export default CategoriesSection;
