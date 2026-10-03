-- =========================================================
-- Mobile Network Service Management and Customer Complaint
-- Reporting System — Database Schema (Phase 2)
-- =========================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------
-- ROLES & USERS
-- ---------------------------------------------------------

CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255)
) ENGINE=InnoDB;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(30),
    password_hash VARCHAR(255) NOT NULL,
    region_id INT NULL,
    zone_id INT NULL,
    woreda_id INT NULL,
    kebele_id INT NULL,
    preferred_contact_method VARCHAR(20) DEFAULT 'Email',
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- GEOGRAPHIC HIERARCHY
-- ---------------------------------------------------------

CREATE TABLE regions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    region_name VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    region_id INT NOT NULL,
    zone_name VARCHAR(100) NOT NULL,
    FOREIGN KEY (region_id) REFERENCES regions(id)
) ENGINE=InnoDB;

CREATE TABLE woredas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zone_id INT NOT NULL,
    woreda_name VARCHAR(100) NOT NULL,
    FOREIGN KEY (zone_id) REFERENCES zones(id)
) ENGINE=InnoDB;

CREATE TABLE kebeles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    woreda_id INT NOT NULL,
    kebele_name VARCHAR(100) NOT NULL,
    FOREIGN KEY (woreda_id) REFERENCES woredas(id)
) ENGINE=InnoDB;

CREATE TABLE service_areas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    area_name VARCHAR(150) NOT NULL,
    region_id INT NOT NULL,
    zone_id INT NOT NULL,
    woreda_id INT NOT NULL,
    kebele_id INT NULL,
    FOREIGN KEY (region_id) REFERENCES regions(id),
    FOREIGN KEY (zone_id) REFERENCES zones(id),
    FOREIGN KEY (woreda_id) REFERENCES woredas(id),
    FOREIGN KEY (kebele_id) REFERENCES kebeles(id)
) ENGINE=InnoDB;

-- add the FK from users to geography now that those tables exist
ALTER TABLE users
    ADD FOREIGN KEY (region_id) REFERENCES regions(id),
    ADD FOREIGN KEY (zone_id) REFERENCES zones(id),
    ADD FOREIGN KEY (woreda_id) REFERENCES woredas(id),
    ADD FOREIGN KEY (kebele_id) REFERENCES kebeles(id);

-- ---------------------------------------------------------
-- SERVICES
-- ---------------------------------------------------------

CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(50) NOT NULL UNIQUE -- Voice, SMS, Mobile Data, 2G, 3G, 4G, 5G
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- NETWORK SITES & CELLS
-- ---------------------------------------------------------

CREATE TABLE sites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_code VARCHAR(30) NOT NULL UNIQUE,
    site_name VARCHAR(150) NOT NULL,
    service_area_id INT NOT NULL,
    latitude DECIMAL(10,6),
    longitude DECIMAL(10,6),
    site_type VARCHAR(30), -- Macro, Micro, Rooftop, Indoor, Tower
    technology VARCHAR(30), -- 2G, 3G, 4G, 5G, Multi-Technology
    status VARCHAR(30) DEFAULT 'Online', -- Online, Down, Degraded, Maintenance, Planned
    installation_date DATE,
    responsible_engineer_id INT NULL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_area_id) REFERENCES service_areas(id),
    FOREIGN KEY (responsible_engineer_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE cells (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    cell_name VARCHAR(100) NOT NULL,
    cell_code VARCHAR(30) NOT NULL UNIQUE,
    technology VARCHAR(10),
    frequency_band VARCHAR(30),
    frequency VARCHAR(30),
    pci INT,
    tac INT,
    sector INT,
    status VARCHAR(30) DEFAULT 'Online',
    capacity DECIMAL(10,2),
    current_traffic DECIMAL(10,2),
    signal_strength DECIMAL(6,2),
    availability DECIMAL(5,2),
    FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- NETWORK PERFORMANCE
-- ---------------------------------------------------------

CREATE TABLE network_performance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    cell_id INT NULL,
    recorded_date DATE NOT NULL,
    recorded_time TIME NOT NULL,
    technology VARCHAR(10),
    traffic DECIMAL(10,2),
    load_percent DECIMAL(5,2),
    availability DECIMAL(5,2),
    signal_strength DECIMAL(6,2),
    signal_quality DECIMAL(6,2),
    users_connected INT,
    throughput DECIMAL(10,2),
    download_speed DECIMAL(10,2),
    upload_speed DECIMAL(10,2),
    latency DECIMAL(6,2),
    packet_loss DECIMAL(5,2),
    call_success_rate DECIMAL(5,2),
    drop_rate DECIMAL(5,2),
    FOREIGN KEY (site_id) REFERENCES sites(id),
    FOREIGN KEY (cell_id) REFERENCES cells(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- EQUIPMENT / BBU / RRU
-- ---------------------------------------------------------

CREATE TABLE equipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    equipment_name VARCHAR(100),
    equipment_type VARCHAR(50), -- BTS, NodeB, eNodeB, gNodeB, Router, Switch, Rectifier, Battery, Antenna...
    vendor VARCHAR(50),
    model VARCHAR(100),
    serial_number VARCHAR(100),
    status VARCHAR(30) DEFAULT 'Online',
    installation_date DATE,
    description TEXT,
    FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB;

CREATE TABLE bbu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    vendor VARCHAR(50),
    model VARCHAR(100),
    serial_number VARCHAR(100),
    status VARCHAR(30) DEFAULT 'Online',
    software_version VARCHAR(50),
    ip_address VARCHAR(45),
    temperature DECIMAL(5,2),
    cpu_usage DECIMAL(5,2),
    memory_usage DECIMAL(5,2),
    FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB;

CREATE TABLE rru (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    bbu_id INT NULL,
    sector INT,
    vendor VARCHAR(50),
    model VARCHAR(100),
    serial_number VARCHAR(100),
    frequency_band VARCHAR(30),
    power DECIMAL(6,2),
    temperature DECIMAL(5,2),
    status VARCHAR(30) DEFAULT 'Online',
    FOREIGN KEY (site_id) REFERENCES sites(id),
    FOREIGN KEY (bbu_id) REFERENCES bbu(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- TRANSMISSION
-- ---------------------------------------------------------

CREATE TABLE transmission_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    link_name VARCHAR(150),
    link_type VARCHAR(20), -- Microwave, Fiber, IP
    source_site_id INT NOT NULL,
    destination_site_id INT NOT NULL,
    frequency VARCHAR(30),
    bandwidth VARCHAR(30),
    capacity VARCHAR(30),
    distance DECIMAL(6,2),
    availability DECIMAL(5,2),
    signal_level DECIMAL(6,2),
    modulation VARCHAR(20), -- QPSK, 16QAM, 64QAM, 256QAM, 1024QAM
    status VARCHAR(30) DEFAULT 'Online',
    FOREIGN KEY (source_site_id) REFERENCES sites(id),
    FOREIGN KEY (destination_site_id) REFERENCES sites(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- ALARMS
-- ---------------------------------------------------------

CREATE TABLE alarms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    cell_id INT NULL,
    equipment_id INT NULL,
    alarm_code VARCHAR(30),
    alarm_title VARCHAR(150),
    description TEXT,
    severity VARCHAR(20), -- Critical, Major, Minor, Warning
    status VARCHAR(20) DEFAULT 'Active', -- Active, Acknowledged, Cleared
    occurred_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    acknowledged_at DATETIME NULL,
    cleared_at DATETIME NULL,
    acknowledged_by INT NULL,
    cleared_by INT NULL,
    FOREIGN KEY (site_id) REFERENCES sites(id),
    FOREIGN KEY (cell_id) REFERENCES cells(id),
    FOREIGN KEY (equipment_id) REFERENCES equipment(id),
    FOREIGN KEY (acknowledged_by) REFERENCES users(id),
    FOREIGN KEY (cleared_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- SERVICE STATUS (simulated)
-- ---------------------------------------------------------

CREATE TABLE service_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_area_id INT NOT NULL,
    service_id INT NOT NULL,
    technology VARCHAR(10),
    status VARCHAR(30) DEFAULT 'Normal', -- Normal, Degraded, Unavailable, Maintenance, Planned Outage
    reason VARCHAR(255),
    start_time DATETIME,
    expected_resolution DATETIME,
    actual_resolution DATETIME NULL,
    updated_by INT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (service_area_id) REFERENCES service_areas(id),
    FOREIGN KEY (service_id) REFERENCES services(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- COMPLAINTS
-- ---------------------------------------------------------

CREATE TABLE customer_complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_number VARCHAR(20) NOT NULL UNIQUE, -- CMP-2026-000001
    customer_id INT NOT NULL,
    service_area_id INT NOT NULL,
    problem_category VARCHAR(50), -- No Network, Weak Signal, etc.
    affected_service VARCHAR(20),
    technology VARCHAR(10),
    description TEXT,
    problem_started_date DATE,
    problem_started_time TIME,
    contact_phone VARCHAR(30),
    latitude DECIMAL(10,6) NULL,
    longitude DECIMAL(10,6) NULL,
    priority VARCHAR(20) DEFAULT 'Medium', -- Critical, High, Medium, Low
    status VARCHAR(30) DEFAULT 'Submitted',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id),
    FOREIGN KEY (service_area_id) REFERENCES service_areas(id)
) ENGINE=InnoDB;

CREATE TABLE complaint_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    updated_by INT NULL,
    status VARCHAR(30),
    message TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES customer_complaints(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- INCIDENTS
-- ---------------------------------------------------------

CREATE TABLE incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incident_number VARCHAR(20) NOT NULL UNIQUE, -- INC-2026-000001
    title VARCHAR(200),
    description TEXT,
    service_area_id INT NULL,
    site_id INT NULL,
    technology VARCHAR(10),
    related_complaint_id INT NULL,
    related_alarm_id INT NULL,
    priority VARCHAR(20) DEFAULT 'Medium',
    assigned_engineer_id INT NULL,
    status VARCHAR(30) DEFAULT 'Open', -- Open, Assigned, Investigating, In Progress, Resolved, Closed
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    started_at DATETIME NULL,
    resolved_at DATETIME NULL,
    resolution TEXT,
    remarks TEXT,
    FOREIGN KEY (service_area_id) REFERENCES service_areas(id),
    FOREIGN KEY (site_id) REFERENCES sites(id),
    FOREIGN KEY (related_complaint_id) REFERENCES customer_complaints(id),
    FOREIGN KEY (related_alarm_id) REFERENCES alarms(id),
    FOREIGN KEY (assigned_engineer_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE incident_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incident_id INT NOT NULL,
    updated_by INT NULL,
    status VARCHAR(30),
    message TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (incident_id) REFERENCES incidents(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- MAINTENANCE
-- ---------------------------------------------------------

CREATE TABLE maintenance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    title VARCHAR(150),
    description TEXT,
    scheduled_start DATETIME,
    scheduled_end DATETIME,
    status VARCHAR(30) DEFAULT 'Planned', -- Planned, In Progress, Completed, Cancelled
    performed_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id),
    FOREIGN KEY (performed_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- NOTIFICATIONS
-- ---------------------------------------------------------

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150),
    message TEXT,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- AUDIT LOGS
-- ---------------------------------------------------------

CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100),
    module VARCHAR(100),
    record_id INT NULL,
    ip_address VARCHAR(45),
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- SEED DATA — roles + one admin account
-- =========================================================

INSERT INTO roles (role_name, description) VALUES
('Administrator', 'Full system access'),
('Customer', 'Reports and tracks complaints'),
('Customer Service Agent', 'Manages customer complaints'),
('Network Monitoring Operator', 'Monitors simulated network status'),
('Network Engineer', 'Investigates and resolves incidents'),
('Transmission Engineer', 'Handles transmission/microwave/fiber links'),
('Report Viewer', 'Read-only access to reports');

-- Password below is a bcrypt hash of: Admin@123
-- We'll properly generate/verify this in Phase 3 (Authentication)
INSERT INTO users (role_id, full_name, username, email, phone, password_hash) VALUES
(1, 'System Administrator', 'admin', 'admin@example.com', '0900000000',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Basic services
INSERT INTO services (service_name) VALUES
('Voice'), ('SMS'), ('Mobile Data'), ('2G'), ('3G'), ('4G'), ('5G');

-- Sample geography (more will be added as needed)
INSERT INTO regions (region_name) VALUES ('Amhara');
INSERT INTO zones (region_id, zone_name) VALUES (1, 'North Wollo');
INSERT INTO woredas (zone_id, woreda_name) VALUES (1, 'Woldia');
INSERT INTO kebeles (woreda_id, kebele_name) VALUES (1, 'Kebele 01');

INSERT INTO service_areas (area_name, region_id, zone_id, woreda_id, kebele_id) VALUES
('Woldia Central', 1, 1, 1, 1);