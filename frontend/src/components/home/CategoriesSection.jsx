
import logo from "../../assets/logo siempre bella.webp";
import "../../styles/categories.css";

const categorias = [
    { id: 1, nombre: "MAQUILLAJE" },
    { id: 2, nombre: "SKINCARE" },
    { id: 3, nombre: "ACCESORIOS" },
    { id: 4, nombre: "VARIOS" }
];

function CategoriesSection() {
    return (
        <section className="categories-section">

            <div className="categories-container">

                <h2 className="categories-title">
                    COMPRA POR CATEGORÍAS
                </h2>

                <div className="categories-grid">

                    {categorias.map((categoria) => (

                        <article
                            className="category-card"
                            key={categoria.id}
                        >

                            <div className="category-image">
                                <img
                                    src={logo}
                                    alt={`Logo Siempre Bella - ${categoria.nombre}`}
                                />
                            </div>

                            <h3>{categoria.nombre}</h3>

                        </article>

                    ))}

                </div>

            </div>

        </section>
    );
}

export default CategoriesSection;
