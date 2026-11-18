
import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import "../../styles/footer.css";

function Footer() {
  const [datos, setDatos] = useState({
    tienda: null,
    redes: [],
  });

  const [cargando, setCargando] = useState(true);

  useEffect(() => {
    const cargarFooter = async () => {
      try {
        const respuesta = await fetch(
          "/api/configuracion/footer_publico.php"
        );

        const resultado = await respuesta.json();

        if (!respuesta.ok || !resultado.success) {
          throw new Error("No fue posible cargar el footer.");
        }

        setDatos({
          tienda: resultado.data.tienda,
          redes: resultado.data.redes || [],
        });
      } catch (error) {
        console.error("Error al cargar el footer:", error);
      } finally {
        setCargando(false);
      }
    };

    cargarFooter();
  }, []);

  const nombreTienda =
    datos.tienda?.nombre_tienda || "Siempre Bella";

  const whatsapp = datos.tienda?.whatsapp
    ? datos.tienda.whatsapp.replace(/\D/g, "")
    : "";

  const obtenerNombreRed = (plataforma) => {
    const nombre = plataforma.toLowerCase();

    if (nombre.includes("instagram") || nombre.includes("istagram")) {
      return "Instagram";
    }

    if (nombre.includes("facebook")) return "Facebook";
    if (nombre.includes("tiktok")) return "TikTok";
    if (nombre.includes("youtube")) return "YouTube";

    return plataforma;
  };

  return (
    <footer className="store-footer">
      <div className="footer-container">

        <div className="footer-main">

          {/* IDENTIDAD DE LA TIENDA */}
          <div className="footer-brand">
            <h2>{nombreTienda}</h2>

            <p>
              Tu belleza, nuestro cuidado.
              Descubre productos para resaltar tu belleza.
            </p>
          </div>

          {/* NAVEGACIÓN */}
          <div className="footer-column">
            <h3>Nuestra tienda</h3>

            <Link to="/">Inicio</Link>
            <Link to="/mayoristas">Mayoristas</Link>
          </div>

          {/* ATENCIÓN */}
          <div className="footer-column">
            <h3>Atención al cliente</h3>

            {whatsapp && (
              <a
                href={`https://wa.me/${whatsapp}`}
                target="_blank"
                rel="noreferrer"
              >
                WhatsApp
              </a>
            )}

            <span>Estamos para atenderte.</span>
          </div>

          {/* REDES SOCIALES */}
          <div className="footer-column">
            <h3>Síguenos</h3>

            {!cargando && datos.redes.length === 0 && (
              <span>Próximamente en redes sociales.</span>
            )}

            {datos.redes.map((red) => (
              <a
                key={`${red.plataforma}-${red.url}`}
                href={red.url}
                target="_blank"
                rel="noopener noreferrer"
              >
                {obtenerNombreRed(red.plataforma)}
              </a>
            ))}
          </div>

        </div>

        <div className="footer-bottom">
          <p>
            © {new Date().getFullYear()} {nombreTienda}.
            Todos los derechos reservados.
          </p>
        </div>

      </div>
    </footer>
  );
}

export default Footer;
