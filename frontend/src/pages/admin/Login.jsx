
import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { Eye, EyeOff } from "lucide-react";
import "../../styles/admin-login.css";
import logo from "../../assets/logo-siempre-bella.webp";

function Login({ isModal = false }) {
  const navigate = useNavigate();

  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [mostrarPassword, setMostrarPassword] = useState(false);
  const [error, setError] = useState("");
  const [cargando, setCargando] = useState(false);

  const iniciarSesion = async (e) => {
    e.preventDefault();

    if (cargando) return;

    setError("");

    const correoNormalizado = email.trim().toLowerCase();

    if (!correoNormalizado || !password) {
      setError("Ingresa tu correo y contraseña.");
      return;
    }

    setCargando(true);

    try {
      const respuesta = await fetch("/api/auth/login.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json"
        },
        credentials: "include",
        body: JSON.stringify({
          email: correoNormalizado,
          password
        })
      });

      // Comprobar que el servidor devuelva JSON.
      const contenido = await respuesta.text();

      let resultado;

      try {
        resultado = JSON.parse(contenido);
      } catch {
        throw new Error(
          "El servidor devolvió una respuesta no válida. Intenta nuevamente."
        );
      }

      if (!respuesta.ok || !resultado.success) {
        setError(
          resultado.message || "No fue posible iniciar sesión."
        );
        return;
      }

      // Limpiar los campos después de autenticar.
      setPassword("");

      navigate("/admin", { replace: true });

    } catch (error) {
      setError(
        error.message || "No se pudo conectar con el servidor."
      );
    } finally {
      setCargando(false);
    }
  };

  return (
    <div className={`admin-login ${isModal ? "admin-login--modal" : ""}`}>
      <div className="admin-login-card">

        <img
          src={logo}
          alt="Siempre Bella"
          className="admin-login-logo"
        />

        <h1>Panel administrativo</h1>

        <p>Ingresa tus credenciales para continuar.</p>

        <form onSubmit={iniciarSesion}>

          <label htmlFor="email">
            Correo electrónico
          </label>

          <input
            id="email"
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="admin@siemprebella.com"
            autoComplete="username"
            maxLength={190}
            required
            disabled={cargando}
          />

          <label htmlFor="password">
            Contraseña
          </label>

          <div className="admin-login-password-wrap">
          <input
            id="password"
            type={mostrarPassword ? "text" : "password"}
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            placeholder="Ingresa tu contraseña"
            autoComplete="current-password"
            required
            disabled={cargando}
          />
          <button
            type="button"
            className="admin-login-password-toggle"
            onClick={() => setMostrarPassword((visible) => !visible)}
            aria-label={mostrarPassword ? "Ocultar contraseña" : "Mostrar contraseña"}
            aria-pressed={mostrarPassword}
            disabled={cargando}
          >
            {mostrarPassword ? <EyeOff size={19} /> : <Eye size={19} />}
          </button>
          </div>

          {error && (
            <div
              className="admin-login-error"
              role="alert"
              aria-live="polite"
            >
              {error}
            </div>
          )}

          <button
            type="submit"
            disabled={cargando}
          >
            {cargando
              ? "Ingresando..."
              : "Iniciar sesión"}
          </button>

        </form>
      </div>
    </div>
  );
}

export default Login;
