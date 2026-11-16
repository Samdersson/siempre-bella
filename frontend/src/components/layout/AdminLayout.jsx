
import Sidebar from "../admin/Sidebar.jsx";
import AdminHeader from "../admin/AdminHeader.jsx";
import "../../styles/admin-sidebar.css";

function AdminLayout({ children }) {
  return (
    <div className="admin-main-layout">
      <Sidebar />

      <div className="admin-content-area">
        <AdminHeader />

        <main className="admin-page-content">
          {children}
        </main>
      </div>
    </div>
  );
}

export default AdminLayout;
