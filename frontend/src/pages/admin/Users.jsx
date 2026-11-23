import { useCallback, useEffect, useState } from "react";
import { Pencil, Plus, Trash2, Users as UsersIcon, X } from "lucide-react";
import AdminLayout from "../../components/layout/AdminLayout.jsx";
import { obtenerHeadersCSRF } from "../../services/csrf.js";
import "../../styles/admin-users.css";

const API = "/api/usuarios/gestionar.php";

function Users() {
  const [usuarios, setUsuarios] = useState([]);
  const [usuarioActual, setUsuarioActual] = useState(null);
  const [formulario, setFormulario] = useState(null);
  const [cargando, setCargando] = useState(true);
  const [guardando, setGuardando] = useState(false);
  const [error, setError] = useState("");
  const [aviso, setAviso] = useState("");

  const cargar = useCallback(async () => {
    setCargando(true);
    try {
      const respuesta = await fetch(API, { credentials: "include", cache: "no-store" });
      const resultado = await respuesta.json();
      if (!respuesta.ok || !resultado.success) throw new Error(resultado.message || "No se pudieron cargar los usuarios.");
      setUsuarios(resultado.data);
      const sesion = await fetch("/api/auth/verificar.php", { credentials: "include", cache: "no-store" });
      const estado = await sesion.json();
      if (estado.success) setUsuarioActual(Number(estado.usuario.id));
    } catch (e) {
      setError(e.message || "No se pudieron cargar los usuarios.");
    } finally {
      setCargando(false);
    }
  }, []);

  useEffect(() => { cargar(); }, [cargar]);

  const guardar = async (e) => {
    e.preventDefault();
    setGuardando(true); setError("");
    try {
      const headers = await obtenerHeadersCSRF({ "Content-Type": "application/json" });
      const respuesta = await fetch(API, {
        method: "POST", credentials: "include", headers,
        body: JSON.stringify({ ...formulario, accion: formulario.id ? "actualizar" : "crear" })
      });
      const resultado = await respuesta.json();
      if (!respuesta.ok || !resultado.success) throw new Error(resultado.message || "No fue posible guardar el usuario.");
      setFormulario(null); setAviso(resultado.message); await cargar();
    } catch (e2) { setError(e2.message || "No fue posible guardar el usuario."); }
    finally { setGuardando(false); }
  };

  const eliminar = async (usuario) => {
    if (!window.confirm(`¿Eliminar a ${usuario.nombre}? Esta acción no se puede deshacer.`)) return;
    setError("");
    try {
      const headers = await obtenerHeadersCSRF({ "Content-Type": "application/json" });
      const respuesta = await fetch(API, { method: "POST", credentials: "include", headers, body: JSON.stringify({ accion: "eliminar", id: usuario.id }) });
      const resultado = await respuesta.json();
      if (!respuesta.ok || !resultado.success) throw new Error(resultado.message || "No fue posible eliminar el usuario.");
      setAviso(resultado.message); await cargar();
    } catch (e) { setError(e.message || "No fue posible eliminar el usuario."); }
  };

  const abrirNuevo = () => { setError(""); setFormulario({ nombre: "", email: "", password: "", activo: true }); };
  const abrirEdicion = (u) => { setError(""); setFormulario({ id: Number(u.id), nombre: u.nombre, email: u.email, password: "", activo: Number(u.activo) === 1 }); };

  return <AdminLayout>
    <section className="users-page">
      <header className="users-heading">
        <div><span className="users-eyebrow">ADMINISTRACIÓN</span><h1>Usuarios</h1><p>Administra las cuentas que pueden acceder al panel.</p></div>
        <button className="users-primary" onClick={abrirNuevo}><Plus size={18} /> Crear usuario</button>
      </header>
      {error && <div className="users-alert users-alert-error" role="alert">{error}</div>}
      {aviso && <div className="users-alert users-alert-success" role="status">{aviso}<button onClick={() => setAviso("")} aria-label="Cerrar aviso"><X size={16} /></button></div>}
      <div className="users-card">
        <div className="users-card-title"><span className="users-icon"><UsersIcon size={19} /></span><div><h2>Cuentas del panel</h2><p>{usuarios.length} {usuarios.length === 1 ? "usuario" : "usuarios"}</p></div></div>
        {cargando ? <div className="users-empty">Cargando usuarios…</div> : usuarios.length === 0 ? <div className="users-empty">Aún no hay usuarios registrados.</div> :
          <div className="users-table-wrap"><table className="users-table"><thead><tr><th>Nombre</th><th>Correo</th><th>Estado</th><th>Último acceso</th><th><span className="sr-only">Acciones</span></th></tr></thead><tbody>
            {usuarios.map((u) => <tr key={u.id}><td><strong>{u.nombre}</strong>{Number(u.id) === usuarioActual && <span className="users-you">Tú</span>}</td><td>{u.email}</td><td><span className={`users-status ${Number(u.activo) === 1 ? "is-active" : "is-inactive"}`}>{Number(u.activo) === 1 ? "Activo" : "Inactivo"}</span></td><td>{u.ultimo_acceso ? new Date(`${u.ultimo_acceso.replace(" ", "T")}Z`).toLocaleString("es-CO", { dateStyle: "medium", timeStyle: "short" }) : "Sin acceso"}</td><td><div className="users-actions"><button title="Editar usuario" aria-label={`Editar ${u.nombre}`} onClick={() => abrirEdicion(u)}><Pencil size={17} /></button><button title="Eliminar usuario" aria-label={`Eliminar ${u.nombre}`} disabled={Number(u.id) === usuarioActual} onClick={() => eliminar(u)}><Trash2 size={17} /></button></div></td></tr>)}
          </tbody></table></div>}
      </div>
    </section>
    {formulario && <div className="users-modal-backdrop" onMouseDown={(e) => { if (e.target === e.currentTarget) setFormulario(null); }}><section className="users-modal" role="dialog" aria-modal="true" aria-labelledby="users-modal-title">
      <header><div><h2 id="users-modal-title">{formulario.id ? "Editar usuario" : "Crear usuario"}</h2><p>Completa los datos de acceso al panel.</p></div><button className="users-close" onClick={() => setFormulario(null)} aria-label="Cerrar"><X size={20} /></button></header>
      <form onSubmit={guardar}>
        <label>Nombre<input required maxLength="120" autoComplete="name" value={formulario.nombre} onChange={(e) => setFormulario({ ...formulario, nombre: e.target.value })} /></label>
        <label>Correo electrónico<input required type="email" maxLength="190" autoComplete="email" value={formulario.email} onChange={(e) => setFormulario({ ...formulario, email: e.target.value })} /></label>
        <label>Contraseña<input type="password" minLength="8" autoComplete="new-password" required={!formulario.id} placeholder={formulario.id ? "Dejar vacía para conservarla" : "Mínimo 8 caracteres"} value={formulario.password} onChange={(e) => setFormulario({ ...formulario, password: e.target.value })} /><small>{formulario.id ? "Solo ingresa una contraseña si deseas cambiarla." : "Usa al menos 8 caracteres."}</small></label>
        {formulario.id && <label className="users-checkbox"><input type="checkbox" checked={formulario.activo} onChange={(e) => setFormulario({ ...formulario, activo: e.target.checked })} /> Cuenta activa</label>}
        <footer><button type="button" className="users-secondary" onClick={() => setFormulario(null)}>Cancelar</button><button type="submit" className="users-primary" disabled={guardando}>{guardando ? "Guardando…" : formulario.id ? "Guardar cambios" : "Crear usuario"}</button></footer>
      </form>
    </section></div>}
  </AdminLayout>;
}

export default Users;
