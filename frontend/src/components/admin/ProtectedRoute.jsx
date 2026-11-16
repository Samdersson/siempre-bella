
import { useEffect, useState } from "react";
import { Navigate } from "react-router-dom";

function ProtectedRoute({ children }) {
  const [autenticado, setAutenticado] = useState(null);

  useEffect(() => {
    const verificarSesion = async () => {
      try {
        const respuesta = await fetch("/api/auth/verificar.php", {
          credentials: "include"
        });

        const resultado = await respuesta.json();

        setAutenticado(
          respuesta.ok && resultado.authenticated === true
        );
      } catch (error) {
        setAutenticado(false);
      }
    };

    verificarSesion();
  }, []);

  if (autenticado === null) {
    return <p style={{ padding: "30px" }}>Verificando acceso...</p>;
  }

  if (!autenticado) {
    return <Navigate to="/admin/login" replace />;
  }

  return children;
}

export default ProtectedRoute;
