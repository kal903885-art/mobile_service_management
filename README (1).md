# Mobile Network Service Management and Customer Complaint Reporting System

An educational simulation web application for managing telecom customer complaints, network sites, equipment, transmission links, alarms, and incidents. Built with PHP 8, MySQL, PDO, and Bootstrap 5.

> **Important:** This is a simulation system for learning purposes. It does **not** connect to, monitor, or control any real telecom network equipment (BTS, BBU, RRU, routers, OSS/NMS, or vendor systems). All "network" data is simulated and entered manually or via sample-data generators within the app.

---

## Tech Stack

- **Frontend:** HTML5, CSS3, JavaScript, Bootstrap 5, Bootstrap Icons
- **Backend:** PHP 8+ with PDO
- **Database:** MySQL 8+
- **Charts:** Chart.js
- **Maps:** Leaflet.js + OpenStreetMap
- **Local server:** XAMPP (Apache + MySQL + PHP)

---

## Prerequisites

- [XAMPP](https://www.apachefriends.org/) installed (includes Apache, MySQL, and PHP)
- A modern web browser (Chrome, Edge, Firefox)
- Basic familiarity with phpMyAdmin

---

## Setup Instructions

### 1. Install XAMPP

Download and install XAMPP for your OS. Make sure the **Apache** and **MySQL** modules are available.

### 2. Copy the project into htdocs

Copy the entire project folder into your XAMPP web root, so the path looks like:

```
C:\xampp\htdocs\mobile-network-service-management\
```

(On macOS/Linux, this is typically `/Applications/XAMPP/htdocs/` or `/opt/lampp/htdocs/`.)

### 3. Start Apache and MySQL

Open the **XAMPP Control Panel** and click **Start** next to both **Apache** and **MySQL**.

### 4. Create the database

1. Open `http://localhost/phpmyadmin` in your browser.
2. Click **New** in the left sidebar.
3. Name the database `mobile_network_service_management` and click **Create**.
4. Click on the new database, go to the **Import** tab, choose the provided `database.sql` file, and click **Go**.

This will create all required tables (users, roles, sites, equipment, transmission_links, alarms, incidents, customer_complaints, notifications, audit_logs, etc.) and seed reference data (roles, service areas).

### 5. Configure the database connection

Open `config/database.php` and confirm the connection settings match your local MySQL setup. By default, XAMPP's MySQL runs with:

- Host: `127.0.0.1`
- Port: `3306` (some setups use `4306` — check your XAMPP MySQL config if the app can't connect)
- User: `root`
- Password: *(empty by default)*

The file supports environment variables (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`) for production use, falling back to local XAMPP defaults for development.

### 6. Create your first Administrator account

Since there's no public "create admin" page, insert one directly via phpMyAdmin's SQL tab. Generate a bcrypt password hash first (see "Creating Test Accounts" below), then run:

```sql
INSERT INTO users (full_name, username, email, phone, password_hash, role_id, is_active)
VALUES (
    'System Administrator',
    'admin',
    'admin@example.com',
    '0900000000',
    '<your-bcrypt-hash-here>',
    (SELECT id FROM roles WHERE role_name = 'Administrator'),
    1
);
```

### 7. Visit the application

Open your browser to:

```
http://localhost/mobile-network-service-management/login.php
```

Log in with the Administrator account you just created.

---

## User Roles

| Role | Description |
|---|---|
| Administrator | Full system access |
| Customer | Reports and tracks their own complaints |
| Customer Service Agent | Manages and updates customer complaints |
| Network Monitoring Operator | Monitors simulated network status, manages sites, alarms, incidents |
| Network Engineer | Investigates and resolves assigned incidents |
| Transmission Engineer | Manages transmission/microwave/fiber links |
| Report Viewer | Read-only access to all reports |

Customers can self-register via `register.php`. All other roles must be created directly in the database (see below) since there is currently no admin UI for user management.

---

## Creating Test Accounts

Since passwords must be bcrypt-hashed, you can't just type a plain password into the `users` table. Generate a hash using PHP:

```php
<?php
echo password_hash('YourPassword123', PASSWORD_DEFAULT);
```

Run this via a temporary PHP file, or via the command line:

```
php -r "echo password_hash('YourPassword123', PASSWORD_DEFAULT);"
```

Then insert a user with that hash and the desired role:

```sql
INSERT INTO users (full_name, username, email, phone, password_hash, role_id, is_active)
VALUES (
    'Test User',
    'test_user',
    'test_user@example.com',
    '0911000000',
    '<paste-hash-here>',
    (SELECT id FROM roles WHERE role_name = 'Network Engineer'),
    1
);
```

---

## Project Structure

```
mobile-network-service-management/
├── admin/              (planned: user management)
├── alarms/             Alarm management (raise, acknowledge, clear)
├── complaints/         Staff-side complaint management
├── config/             Database connection
├── customer/           Customer-facing pages (dashboard, report problem, etc.)
├── equipment/          Equipment inventory (CRUD)
├── incidents/          Incident management (create, assign, resolve)
├── includes/           Shared auth, header/footer, helper functions
├── map/                Interactive network map (Leaflet + OpenStreetMap)
├── notifications/      In-app notification center
├── performance/        Simulated network performance data
├── reports/            Reporting module (6 report types + CSV export)
├── service/            Service status management
├── sites/              Network site management (CRUD)
├── transmission/       Transmission link management (CRUD)
├── login.php
├── register.php
├── logout.php
├── database.sql        Full schema + seed data
└── README.md
```

---

## Security Notes

- All database queries use PDO prepared statements.
- All output is escaped with `htmlspecialchars()`.
- Forms include CSRF tokens, validated server-side.
- Sessions use `httponly` and `SameSite=Lax` cookies, with a 30-minute inactivity timeout.
- Sensitive actions (create/update/delete across equipment, transmission, sites, complaints, incidents, alarms, and login attempts) are recorded in the `audit_logs` table.

To adjust the session timeout, edit `SESSION_TIMEOUT_SECONDS` in `includes/auth.php`.

---

## Known Limitations

- No admin UI yet for creating/managing staff user accounts (must be done via phpMyAdmin).
- Network performance and alarm data are simulated/manually entered — there is no real equipment integration.
- Email/SMS notifications are out of scope; all notifications are in-app only.

---

## Backing Up the Database

Before making significant changes, export a backup via phpMyAdmin:

1. Select the database.
2. Click **Export** → Quick → SQL → **Go**.
3. Save the downloaded `.sql` file somewhere outside the project folder.

To restore, use phpMyAdmin's **Import** tab with the backup file.
