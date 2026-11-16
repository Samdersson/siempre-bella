
import { useState } from "react";
import { useNavigate } from "react-router-dom";
import "../../styles/admin-login.css";
import logo from "../../assets/logo siempre bella.webp";

function Login() {
  const navigate = useNavigate();

  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [cargando, setCargando] = useState(false);

  const iniciarSesion = async (e) => {
    e.preventDefault();
    setError("");
    setCargando(true);

    try {
      const respuesta = await fetch("/api/auth/login.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json"
        },
        credentials: "include",
        body: JSON.stringify({ email, password })
      });

      const resultado = await respuesta.json();

      if (!respuesta.ok || !resultado.success) {
        setError(resultado.message || "No fue posible iniciar sesión.");
        return;
      }

      navigate("/admin");
    } catch (error) {
      setError("No se pudo conectar con el servidor.");
    } finally {
      setCargando(false);
    }
  };

  return (
    <div className="admin-login">
      <div className="admin-login-card">
        <img
            src={logo}
            alt="Siempre Bella"
            className="admin-login-logo"
            />

        <h1>Panel administrativo</h1>
        <p>Ingresa tus credenciales para continuar.</p>

        <form onSubmit={iniciarSesion}>
          <label htmlFor="email">Correo electrónico</label>
          <input
            id="email"
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="admin@siemprebella.com"
            autoComplete="username"
            required
          />

          <label htmlFor="password">Contraseña</label>
          <input
            id="password"
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            placeholder="Ingresa tu contraseña"
            autoComplete="current-password"
            required
          />

          {error && <div className="admin-login-error">{error}</div>}

          <button type="submit" disabled={cargando}>
            {cargando ? "Ingresando..." : "Iniciar sesión"}
          </button>
        </form>
      </div>
    </div>
  );
}

export default Login;
