
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

        {/* FOOTER COMERCIAL DE LA TIENDA */}
        <div className="footer-main">

          {/* IDENTIDAD */}
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
            {/* <Link to="/mayoristas">Mayoristas</Link> */}
          </div>

          {/* ATENCIÓN */}
          <div className="footer-column">
            <h3>Atención al cliente</h3>

            {whatsapp && (
              <a
                href={`https://wa.me/${whatsapp}`}
                target="_blank"
                rel="noopener noreferrer"
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

        {/* DERECHOS DE LA TIENDA */}
        <div className="footer-bottom">
          <p>
            © {new Date().getFullYear()} {nombreTienda}.
            Todos los derechos reservados.
          </p>
        </div>

        {/* CRÉDITOS DEL DESARROLLADOR */}
        <div className="site-footer">

          <p className="footer-text">
            Desarrollado por{" "}
            <a
              href="https://samdersson.netlify.app/"
              target="_blank"
              rel="noopener noreferrer"
              className="dev-link"
            >
              <strong>Samdersson Nuñez</strong>
            </a>
            {" "}•{" "}
            <a
              href="https://360digital.netlify.app/"
              target="_blank"
              rel="noopener noreferrer"
              className="dev-link"
            >
              <strong>360 Digital</strong>
            </a>
          </p>

          <div className="footer-links">

            <a
              href="https://360digital.netlify.app/"
              target="_blank"
              rel="noopener noreferrer"
              className="btn-dev-social btn-web"
            >
              <i className="fas fa-globe"></i>
              Sitio Web 360 Digital
            </a>

            <a
              href="https://samdersson.netlify.app/"
              target="_blank"
              rel="noopener noreferrer"
              className="btn-dev-social btn-portfolio"
            >
              <i className="fas fa-user-code"></i>
              Portafolio desarrollador
            </a>

            <a
              href="https://www.instagram.com/360digital.co"
              target="_blank"
              rel="noopener noreferrer"
              className="btn-dev-social btn-instagram"
            >
              <i className="fab fa-instagram"></i>
              @360digital.co
            </a>

            <a
              href="https://wa.me/573187752351?text=%C2%A1Hola%20360Digital!%20Me%20interesa%20solicitar%20el%20desarrollo%20de%20un%20sitio%20web."
              target="_blank"
              rel="noopener noreferrer"
              className="btn-dev-social btn-whatsapp"
            >
              <i className="fab fa-whatsapp"></i>
              Solicitar Desarrollo
            </a>

          </div>
        </div>

      </div>
    </footer>
  );
}

export default Footer;
