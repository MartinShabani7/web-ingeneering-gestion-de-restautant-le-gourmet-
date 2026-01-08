-- Table des réservations
CREATE TABLE reservations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reservation_number VARCHAR(50) UNIQUE NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_email VARCHAR(150) NOT NULL,
    customer_phone VARCHAR(20),
    customer_id INT NULL,
    reservation_date DATE NOT NULL,
    reservation_time TIME NOT NULL,
    party_size INT NOT NULL CHECK (party_size > 0 AND party_size <= 50),
    table_number VARCHAR(20),
    special_requests TEXT,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled', 'rejected') DEFAULT 'pending',
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    cancelled_at DATETIME,
    cancelled_by ENUM('customer', 'admin'),
    created_by INT, -- 0 pour invité
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_date_time (reservation_date, reservation_time),
    INDEX idx_customer (customer_id),
    INDEX idx_reservation_number (reservation_number)
);

-- Table de logs des réservations
CREATE TABLE reservation_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reservation_id INT NOT NULL,
    changed_by ENUM('customer', 'admin', 'system') NOT NULL,
    changes TEXT NOT NULL,
    changed_at DATETIME NOT NULL,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    INDEX idx_reservation (reservation_id),
    INDEX idx_changed_at (changed_at)
);

-- Table des notifications utilisateurs
CREATE TABLE user_notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error', 'reservation_created', 'reservation_status_changed') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at DATETIME NOT NULL,
    read_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_read (is_read),
    INDEX idx_created (created_at)
);

-- Table des logs d'emails
CREATE TABLE reservation_emails_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reservation_id INT NOT NULL,
    email_type ENUM('created', 'confirmation', 'completed', 'cancelled', 'cancelled_admin', 'rejected', 'admin_notification') NOT NULL,
    sent_to VARCHAR(150) NOT NULL,
    sent_at DATETIME NOT NULL,
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    error_message TEXT,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    INDEX idx_reservation (reservation_id),
    INDEX idx_sent_at (sent_at),
    INDEX idx_status (status)
);

-- Ajouter la colonne suspended_until à la table users (si pas déjà présente)
ALTER TABLE users 
ADD COLUMN suspended_until DATETIME NULL AFTER is_active;