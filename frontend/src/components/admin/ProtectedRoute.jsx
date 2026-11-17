
import { useEffect, useState } from "react";
import { Navigate } from "react-router-dom";

function ProtectedRoute({ children }) {
  const [autenticado, setAutenticado] = useState(null);

  useEffect(() => {
    const controlador = new AbortController();

    const verificarSesion = async () => {
      try {
        const respuesta = await fetch("/api/auth/verificar.php", {
          method: "GET",
          credentials: "include",
          cache: "no-store",
          signal: controlador.signal,
          headers: {
            Accept: "application/json"
          }
        });

        const resultado = await respuesta.json();

        setAutenticado(
          respuesta.ok && resultado.authenticated === true
        );

      } catch (error) {
        if (error.name !== "AbortError") {
          console.error("Error verificando sesión:", error);
          setAutenticado(false);
        }
      }
    };

    verificarSesion();

    return () => {
      controlador.abort();
    };
  }, []);

  if (autenticado === null) {
    return (
      <div style={{ padding: "30px", textAlign: "center" }}>
        Verificando acceso...
      </div>
    );
  }

  if (!autenticado) {
    return <Navigate to="/admin/login" replace />;
  }

  return children;
}

export default ProtectedRoute;
