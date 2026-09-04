# SQLite + Portable Apache Setup

## 1) Database switched to SQLite
- The app now uses `data/sam.sqlite` via `db.php`.
- Schema bootstrap runs automatically from `schema_sqlite.sql` on first request.
- A default admin user is seeded:
  - `username`: `ketan`
  - Set `SAM_ADMIN_USERNAME` and `SAM_ADMIN_PASSWORD`, then run `php admin_register.php` from a terminal.

## 2) Optional data migration
- Your old MySQL dump is `sam.sql`.
- This change does not auto-import the MySQL dump because it contains MySQL-specific SQL and large blob hex payloads.
- If you want, I can add a one-time migration script next.

## 3) Apache portable config (desktop)
Files added:
- `apache/httpd-sam-include.conf`
- `apache/sam-vhost.conf`
- `apache/start-apache-portable.bat`
- `apache/stop-apache-portable.bat`

### Steps
1. Place portable Apache at `portable/Apache24` under this project root.
2. In your Apache `httpd.conf`, add:
   - `Include "E:/Sam portable/sam/apache/httpd-sam-include.conf"`
3. Start with:
   - `apache/start-apache-portable.bat`
4. Open:
   - `http://127.0.0.1:8088/`

### Important
- If your project path is different, update absolute paths in:
  - `apache/httpd-sam-include.conf`
  - `apache/sam-vhost.conf`
- Ensure Apache has PHP + `pdo_sqlite` enabled.
