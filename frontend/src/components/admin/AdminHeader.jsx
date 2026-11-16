
import { useNavigate } from "react-router-dom";
import { LogOut } from "lucide-react";

function AdminHeader() {
  const navigate = useNavigate();

  const cerrarSesion = async () => {
    try {
      const respuesta = await fetch("/api/auth/logout.php", {
        method: "POST",
        credentials: "include"
      });

      const resultado = await respuesta.json();

      if (respuesta.ok && resultado.success) {
        navigate("/admin/login", { replace: true });
      } else {
        alert("No fue posible cerrar la sesión.");
      }
    } catch (error) {
      alert("No se pudo conectar con el servidor.");
    }
  };

  return (
    <header className="admin-header">
      <div>
        <span>Administración</span>
        <strong>Siempre Bella</strong>
      </div>

      <button
        type="button"
        className="admin-logout-button"
        onClick={cerrarSesion}
      >
        <LogOut size={18} />
        <span>Cerrar sesión</span>
      </button>
    </header>
  );
}

export default AdminHeader;
