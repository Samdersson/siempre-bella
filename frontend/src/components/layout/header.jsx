
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
import Login from "../../pages/admin/Login.jsx";

import "../../styles/header.css";

const Header = () => {
    const [menuOpen, setMenuOpen] = useState(false);
    const [loginOpen, setLoginOpen] = useState(false);
    const [categorias, setCategorias] = useState([]);

    const { totalUnidades } = useCart();

    useEffect(() => {
        if (!loginOpen) return;
        const cerrarConEscape = (event) => {
            if (event.key === "Escape") setLoginOpen(false);
        };
        window.addEventListener("keydown", cerrarConEscape);
        return () => window.removeEventListener("keydown", cerrarConEscape);
    }, [loginOpen]);

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

                    <button aria-label="Iniciar sesión como administrador" type="button" onClick={() => setLoginOpen(true)}>
                        <UserRound />
                    </button>

                </div>

            </div>
            {loginOpen && (
                <div className="admin-login-modal" role="presentation" onMouseDown={(event) => {
                    if (event.target === event.currentTarget) setLoginOpen(false);
                }}>
                    <section className="admin-login-modal-content" role="dialog" aria-modal="true" aria-label="Inicio de sesión del administrador">
                        <button className="admin-login-modal-close" type="button" aria-label="Cerrar inicio de sesión" onClick={() => setLoginOpen(false)}>×</button>
                        <Login isModal />
                    </section>
                </div>
            )}
        </header>
    );
};

export default Header;
