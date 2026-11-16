
import { Link, NavLink } from "react-router-dom";
import {
  Search,
  ShoppingBag,
  UserRound,
  Menu,
  X
} from "lucide-react";
import { useState } from "react";
import "../../styles/header.css";
import { useCart } from "../../context/CartContext";

  const Header = () => {
  const [menuOpen, setMenuOpen] = useState(false);
  const { totalUnidades } = useCart();

  // Categorías de demostración.
  // Más adelante vendrán desde nuestra API PHP.
  const categorias = [
    { nombre: "Maquillaje", slug: "maquillaje" },
    { nombre: "Accesorios", slug: "accesorios" },
    { nombre: "Skincare", slug: "skincare" },
    { nombre: "Varios", slug: "varios" }
  ];

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
          aria-label="Abrir menú"
        >
          {menuOpen ? <X /> : <Menu />}
        </button>

        <nav className={`navigation ${menuOpen ? "open" : ""}`}>
          <NavLink to="/" onClick={() => setMenuOpen(false)}>
            Inicio
          </NavLink>

          {categorias.map((categoria) => (
            <NavLink
              key={categoria.slug}
              to={`/categoria/${categoria.slug}`}
              onClick={() => setMenuOpen(false)}
            >
              {categoria.nombre}
            </NavLink>
          ))}

          <NavLink to="/mayoristas" onClick={() => setMenuOpen(false)}>
            Mayoristas
          </NavLink>
        </nav>

        <div className="header-actions">
          <button aria-label="Buscar productos">
            <Search />
          </button>

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

          <button aria-label="Mi cuenta">
            <UserRound />
          </button>
        </div>

      </div>
    </header>
  );
};

export default Header;
