
import { Link, NavLink } from "react-router-dom";
import {
    Search,
    ShoppingBag,
    UserRound,
    Menu,
    X
} from "lucide-react";
import { useEffect, useState } from "react";

import { obtenerCategorias } from "../../services/api";
import { useCart } from "../../context/CartContext";

import "../../styles/header.css";

const Header = () => {
    const [menuOpen, setMenuOpen] = useState(false);
    const [categorias, setCategorias] = useState([]);

    const { totalUnidades } = useCart();

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

    return (
        <header className="header">
            <div className="header-container">

                <Link to="/" className="logo">
                    <span>SIEMPRE</span>
                    <strong>BELLA</strong>
                </Link>

                <button
                    className="menu-toggle"
                    onClick={() => setMenuOpen(!menuOpen)}
                    aria-label={menuOpen ? "Cerrar menú" : "Abrir menú"}
                    aria-expanded={menuOpen}
                >
                    {menuOpen ? <X /> : <Menu />}
                </button>

                <nav className={`navigation ${menuOpen ? "open" : ""}`}>

                    <NavLink
                        to="/"
                        onClick={() => setMenuOpen(false)}
                    >
                        Inicio
                    </NavLink>

                    <NavLink
                        to="/tienda"
                        onClick={() => setMenuOpen(false)}
                    >
                        Tienda
                    </NavLink>

                    {categorias.map((categoria) => (
                        <NavLink
                            key={categoria.id}
                            to={`/categoria/${categoria.slug}`}
                            onClick={() => setMenuOpen(false)}
                        >
                            {categoria.nombre}
                        </NavLink>
                    ))}

                    {/* <NavLink
                        to="/mayoristas"
                        onClick={() => setMenuOpen(false)}
                    >
                        Mayoristas
                    </NavLink> */}

                </nav>

                <div className="header-actions">

                    <Link
                        to="/tienda"
                        aria-label="Buscar productos"
                    >
                        <Search />
                    </Link>

                    <Link
                        to="/carrito"
                        className="header-cart-link"
                        aria-label={`Ver carrito. ${totalUnidades} productos`}
                    >
                        <ShoppingBag />

                        {totalUnidades > 0 && (
                            <span className="cart-count">
                                {totalUnidades}
                            </span>
                        )}
                    </Link>

                    <button aria-label="Mi cuenta" type="button">
                        <UserRound />
                    </button>

                </div>

            </div>
        </header>
    );
};

export default Header;
