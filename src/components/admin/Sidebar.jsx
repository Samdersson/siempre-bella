import { NavLink } from "react-router-dom";

import {
  LayoutDashboard,
  Package,
  Tags,
  Award,
  Image,
  Images,
  Megaphone,
  Settings,
  Store,
  ShoppingBag,
  FileSpreadsheet
} from "lucide-react";

import "../../styles/admin-sidebar.css";

function Sidebar() {
  const enlaces = [
    { nombre: "Resumen", ruta: "/admin", icono: LayoutDashboard, exacto: true },
    { nombre: "Productos", ruta: "/admin/productos", icono: Package },
    { nombre: "Pedidos mayoristas", ruta: "/admin/pedidos-mayoristas", icono: ShoppingBag },
    { nombre: "Categorías", ruta: "/admin/categorias", icono: Tags },
    { nombre: "Marcas", ruta: "/admin/marcas", icono: Award },
    { nombre: "Promociones", ruta: "/admin/promociones", icono: Megaphone },
    { nombre: "Biblioteca multimedia", ruta: "/admin/multimedia", icono: Images },
    { nombre: "Banners y contenido", ruta: "/admin/contenido", icono: Image },
    { nombre: "Configuración", ruta: "/admin/configuracion", icono: Settings },
    { nombre: "Reportes comerciales", ruta: "/admin/reportes", icono: FileSpreadsheet },
  ];

  return (
    <aside className="admin-sidebar">
      <div className="admin-sidebar-brand">
        <Store size={25} />
        <div>
          <strong>Siempre Bella</strong>
          <span>Administración</span>
        </div>
      </div>

      <nav className="admin-sidebar-nav">
        <span className="admin-sidebar-label">MENÚ PRINCIPAL</span>

        {enlaces.map((enlace) => {
          const Icono = enlace.icono;

          return (
            <NavLink
              key={enlace.ruta}
              to={enlace.ruta}
              end={enlace.exacto}
              className={({ isActive }) =>
                `admin-sidebar-link ${isActive ? "active" : ""}`
              }
            >
              <Icono size={19} />
              <span>{enlace.nombre}</span>
            </NavLink>
          );
        })}
      </nav>

      <div className="admin-sidebar-footer">
        <span>Siempre Bella © 2026</span>
        <small>Panel administrativo</small>
      </div>
    </aside>
  );
}

export default Sidebar;
