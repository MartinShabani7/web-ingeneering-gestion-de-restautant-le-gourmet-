-- Base de données pour l'application de gestion de restaurant
-- Création de la base de données
CREATE DATABASE IF NOT EXISTS restaurant_gourmet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE restaurant_gourmet;

-- Table des utilisateurs
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    avatar VARCHAR(255),
    role ENUM('admin', 'manager', 'staff', 'customer') DEFAULT 'customer',
    is_active BOOLEAN DEFAULT TRUE,
    email_verified BOOLEAN DEFAULT FALSE,
    email_verification_token VARCHAR(255),
    password_reset_token VARCHAR(255),
    password_reset_expires DATETIME,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_active (is_active)
);

-- Table des catégories de produits
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    image VARCHAR(255),
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_active (is_active),
    INDEX idx_sort (sort_order)
);

-- Table des produits/plats
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    category_id INT,
    image VARCHAR(255),
    ingredients TEXT,
    allergens TEXT,
    preparation_time INT, -- en minutes
    is_available BOOLEAN DEFAULT TRUE,
    is_featured BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_category (category_id),
    INDEX idx_available (is_available),
    INDEX idx_featured (is_featured),
    INDEX idx_price (price)
);

-- Table des commandes
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(20) UNIQUE NOT NULL,
    customer_id INT,
    table_number VARCHAR(10),
    order_type ENUM('dine_in', 'takeaway', 'delivery') DEFAULT 'dine_in',
    status ENUM('pending', 'confirmed', 'preparing', 'ready', 'served', 'cancelled') DEFAULT 'pending',
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
    payment_method ENUM('cash', 'card', 'online', 'mobile_money') NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_customer (customer_id),
    INDEX idx_status (status),
    INDEX idx_payment_status (payment_status),
    INDEX idx_created_at (created_at),
    INDEX idx_order_number (order_number)
);

ALTER TABLE orders 
ADD COLUMN payment_details JSON DEFAULT NULL AFTER payment_method;

-- =================================== tables pour la commande =============================

-- Historique des statuts de commande
CREATE TABLE order_status_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    status ENUM('pending', 'confirmed', 'preparing', 'ready', 'served', 'cancelled') NOT NULL,
    notes TEXT,
    changed_by VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order (order_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Adresses de livraison
CREATE TABLE delivery_addresses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    address TEXT NOT NULL,
    zipcode VARCHAR(10) NOT NULL,
    city VARCHAR(100) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order (order_id)
);

-- Remboursements
CREATE TABLE refunds (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reason TEXT,
    status ENUM('pending', 'processed', 'failed') DEFAULT 'pending',
    processed_at DATETIME,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order (order_id),
    INDEX idx_status (status)
);

-- Table des détails de commande
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    special_instructions TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_order (order_id),
    INDEX idx_product (product_id)
);

-- Table des réservations
CREATE TABLE reservations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT,
    customer_name VARCHAR(200) NOT NULL,
    customer_email VARCHAR(255),
    customer_phone VARCHAR(20),
    reservation_date DATE NOT NULL,
    reservation_time TIME NOT NULL,
    party_size INT NOT NULL DEFAULT 1,
    status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
    special_requests TEXT,
    table_number VARCHAR(10),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_customer (customer_id),
    INDEX idx_date (reservation_date),
    INDEX idx_status (status)
);

-- Table des tables
CREATE TABLE tables (
    id INT PRIMARY KEY AUTO_INCREMENT,
    table_name VARCHAR(50) UNIQUE NOT NULL,
    capacity INT NOT NULL,
    location VARCHAR(100),
    image VARCHAR(255),
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_available (is_available)
);

-- Occupation des tables
CREATE TABLE table_occupations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    table_id INT,
    table_name VARCHAR(50) NOT NULL,
    order_id INT NOT NULL,
    customer_id INT,
    start_time DATETIME NOT NULL,
    end_time DATETIME,
    expected_end_time DATETIME,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (table_id) REFERENCES tables(id) ON DELETE SET NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_table (table_id),
    INDEX idx_order (order_id),
    INDEX idx_time (start_time, end_time)
);

-- Table des stocks
CREATE TABLE inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT,
    ingredient_name VARCHAR(200) NOT NULL,
    current_stock DECIMAL(10,2) NOT NULL DEFAULT 0,
    unit VARCHAR(20) NOT NULL, -- kg, L, pièces, etc.
    min_stock_level DECIMAL(10,2) DEFAULT 0,
    cost_per_unit DECIMAL(10,2) DEFAULT 0,
    supplier VARCHAR(200),
    expiry_date DATE,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    INDEX idx_product (product_id),
    INDEX idx_stock_level (current_stock),
    INDEX idx_expiry (expiry_date)
);

-- Table des fournisseurs
CREATE TABLE suppliers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    contact_person VARCHAR(100),
    email VARCHAR(255),
    phone VARCHAR(20),
    address TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_active (is_active)
);

-- Table des mouvements de stock
CREATE TABLE stock_movements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    inventory_id INT NOT NULL,
    movement_type ENUM('in', 'out', 'adjustment') NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    reason VARCHAR(200),
    reference VARCHAR(100), -- numéro de commande, facture, etc.
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (inventory_id) REFERENCES inventory(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_inventory (inventory_id),
    INDEX idx_type (movement_type),
    INDEX idx_created_at (created_at)
);

-- Table des promotions
CREATE TABLE promotions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    discount_type ENUM('percentage', 'fixed_amount') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    min_order_amount DECIMAL(10,2) DEFAULT 0,
    max_discount_amount DECIMAL(10,2),
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    usage_limit INT,
    used_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_active (is_active),
    INDEX idx_dates (start_date, end_date)
);

-- Table des avis clients
CREATE TABLE reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT,
    product_id INT,
    order_id INT,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    is_approved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    INDEX idx_customer (customer_id),
    INDEX idx_product (product_id),
    INDEX idx_rating (rating),
    INDEX idx_approved (is_approved)
);

-- Table des paramètres système
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table des logs d'activité
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- Table pour la protection contre la force brute

CREATE TABLE brute_force_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ip_address VARCHAR(45) NOT NULL,
    email VARCHAR(255) NOT NULL,
    attempt_time DATETIME NOT NULL,
    INDEX idx_ip (ip_address),
    INDEX idx_email (email),
    INDEX idx_time (attempt_time)
);

-- Table pour la fonctionnalité "Se souvenir de moi"
CREATE TABLE IF NOT EXISTS remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_token_hash (token_hash),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table des templates de rapports
CREATE TABLE report_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category ENUM('ventes', 'inventaire', 'clients', 'personnel', 'financier') NOT NULL,
    sql_query TEXT NOT NULL,
    columns_config JSON,
    is_public BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des rapports générés
CREATE TABLE generated_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    template_id INT,
    user_id INT,
    report_name VARCHAR(255) NOT NULL,
    parameters JSON,
    file_path VARCHAR(500),
    file_format ENUM('pdf', 'excel', 'word', 'csv', 'html') NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (template_id) REFERENCES report_templates(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Table des visiteurs
CREATE TABLE visiteurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45),
    user_agent TEXT,
    page_visited VARCHAR(255),
    referrer VARCHAR(255),
    date_visite DATETIME,
    session_id VARCHAR(255),
    pays VARCHAR(100),
    ville VARCHAR(100),
    region VARCHAR(100),
    fournisseur_internet VARCHAR(150),
    coordonnees VARCHAR(50),
    navigateur VARCHAR(100),
    systeme_exploitation VARCHAR(100)
);

-- Table des visites en temps réel
CREATE TABLE visites_en_temps_reel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) UNIQUE,
    ip_address VARCHAR(45),
    derniere_activite DATETIME,
    page_actuelle VARCHAR(255)
);

CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    icon VARCHAR(100) NOT NULL,
    button_text VARCHAR(100) NOT NULL,
    button_link VARCHAR(255) NOT NULL,
    button_color VARCHAR(50) DEFAULT 'primary',
    background_color VARCHAR(50) DEFAULT 'primary',
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table pour logguer les emails envoyés
CREATE TABLE reservation_emails_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reservation_id INT NOT NULL,
    email_type VARCHAR(50) NOT NULL,
    sent_to VARCHAR(255) NOT NULL,
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'sent',
    error_message TEXT,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE
);

-- Index pour optimiser les recherches
CREATE INDEX idx_reservation_email ON reservation_emails_log(reservation_id);
CREATE INDEX idx_sent_at ON reservation_emails_log(sent_at);


CREATE TABLE testimonials (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE partenaires (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(255) NOT NULL,
    adresse TEXT,
    mail VARCHAR(255),
    contact VARCHAR(50),
    photo VARCHAR(500),
    est_actif BOOLEAN DEFAULT TRUE,
    est_en_avant BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Supprimez la colonne table_number
ALTER TABLE reservations DROP COLUMN table_number;

-- Ajoutez la colonne table_id avec clé étrangère
ALTER TABLE reservations ADD COLUMN table_id INT NULL AFTER special_requests;

-- Ajoutez la clé étrangère
ALTER TABLE reservations 
ADD CONSTRAINT fk_reservations_table_id 
FOREIGN KEY (table_id) 
REFERENCES tables(id) 
ON DELETE SET NULL;


-- Mise à jour de la table report_templates pour renommer certaines colennes
UPDATE report_templates 
SET sql_query = 'SELECT 
    p.name as produit,
    i.current_stock as stock_actuel,
    i.min_stock_level as stock_minimum
FROM products p
JOIN inventory i ON p.id = i.product_id
WHERE i.current_stock <= i.min_stock_level
ORDER BY i.current_stock ASC'
WHERE sql_query LIKE '%stock_quantity%' AND sql_query LIKE '%min_stock%';

-- ================================================
-- TEMPLATES DE RAPPORTS COMPLETS
-- ================================================

-- Modifiez la table pour ajouter les catégories manquantes
ALTER TABLE report_templates 
MODIFY COLUMN category ENUM(
    'ventes', 
    'inventaire', 
    'clients', 
    'personnel', 
    'financier', 
    'utilisateurs', 
    'tables', 
    'menu'
) NOT NULL;

-- Templates qui utilisent des tables qui existent SÛREMENT
INSERT INTO report_templates (name, description, sql_query, columns_config, category, is_public) VALUES
-- Utilisateurs (table 'users' existe toujours)
('Liste des utilisateurs', 'Tous les utilisateurs du système',
 'SELECT id, CONCAT(first_name, " ", last_name) as nom_complet, email, role, created_at, last_login FROM users ORDER BY created_at DESC',
 '{"id": {"display_name": "ID"}, "nom_complet": {"display_name": "Nom complet"}, "email": {"display_name": "Email"}, "role": {"display_name": "Rôle"}, "created_at": {"display_name": "Date création"}, "last_login": {"display_name": "Dernière connexion"}}',
 'utilisateurs', 1),

('Utilisateurs actifs', 'Utilisateurs actuellement actifs',
 'SELECT CONCAT(first_name, " ", last_name) as nom_complet, email, role, last_login FROM users WHERE is_active = 1 ORDER BY role, last_name',
 '{"nom_complet": {"display_name": "Nom complet"}, "email": {"display_name": "Email"}, "role": {"display_name": "Rôle"}, "last_login": {"display_name": "Dernière connexion"}}',
 'utilisateurs', 1),

-- Tables (table 'tables' existe dans votre système)
('Liste des tables', 'Toutes les tables du restaurant',
 'SELECT table_name, capacity, location, is_available FROM tables ORDER BY table_name',
 '{"table_name": {"display_name": "Nom de table"}, "capacity": {"display_name": "Capacité"}, "location": {"display_name": "Emplacement"}, "is_available": {"display_name": "Disponible"}}',
 'tables', 1),

-- Inventaire (tables 'products' et 'inventory' existent)
('Stock faible (alerte)', 'Produits avec stock inférieur au minimum',
 'SELECT p.name as produit, i.current_stock as stock_actuel, i.min_stock_level as stock_minimum, i.unit FROM products p JOIN inventory i ON p.id = i.product_id WHERE i.current_stock <= i.min_stock_level ORDER BY i.current_stock ASC',
 '{"produit": {"display_name": "Produit"}, "stock_actuel": {"display_name": "Stock actuel"}, "stock_minimum": {"display_name": "Stock minimum"}, "unit": {"display_name": "Unité"}}',
 'inventaire', 1),

('Inventaire complet', 'État complet de l''inventaire',
 'SELECT i.ingredient_name, i.current_stock, i.unit, i.min_stock_level, i.cost_per_unit, i.supplier FROM inventory i ORDER BY i.ingredient_name',
 '{"ingredient_name": {"display_name": "Ingrédient"}, "current_stock": {"display_name": "Stock actuel"}, "unit": {"display_name": "Unité"}, "min_stock_level": {"display_name": "Stock minimum"}, "cost_per_unit": {"display_name": "Prix unitaire"}, "supplier": {"display_name": "Fournisseur"}}',
 'inventaire', 1),

-- Plats/Menu (table 'products' existe)
('Carte complète', 'Tous les plats disponibles',
 'SELECT name, description, price, category, is_available FROM products ORDER BY category, name',
 '{"name": {"display_name": "Nom"}, "description": {"display_name": "Description"}, "price": {"display_name": "Prix"}, "category": {"display_name": "Catégorie"}, "is_available": {"display_name": "Disponible"}}',
 'menu', 1);

UPDATE report_templates 
SET sql_query = 'SELECT p.name, p.description, p.price, c.name as category, p.is_available FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY c.name, p.name'
WHERE sql_query = 'SELECT name, description, price, category, is_available FROM products ORDER BY category, name';


-- Insertion de données d'exemple
INSERT INTO partenaires (nom, adresse, mail, contact, photo, est_actif, est_en_avant) VALUES
('Entreprise A', '123 Rue Example, Paris', 'contact@entreprise-a.fr', '+243123456789', 'partenaire_a.jpg', TRUE, TRUE),
('Société B', '456 Avenue Test, Lyon', 'info@societe-b.com', '+243456789123', 'partenaire_b.jpg', TRUE, FALSE),
('Compagnie C', '789 Boulevard Demo, Marseille', 'contact@compagnie-c.fr', '+250789123456', 'partenaire_c.jpg', FALSE, FALSE);

-- Templates par défaut
INSERT INTO report_templates (name, description, category, sql_query, columns_config) VALUES
(
    'Rapport des Ventes Quotidiennes',
    'Chiffre d''affaire et nombre de commandes par jour',
    'ventes',
    'SELECT DATE(created_at) as date, COUNT(*) as nb_commandes, SUM(total_amount) as chiffre_affaire FROM orders WHERE created_at BETWEEN ? AND ? GROUP BY DATE(created_at) ORDER BY date DESC',
    '{"date": {"display_name": "Date", "type": "date"}, "nb_commandes": {"display_name": "Nb Commandes", "type": "number"}, "chiffre_affaire": {"display_name": "Chiffre d''Affaire", "type": "currency"}}'
),
(
    'État des Stocks',
    'Niveau de stock actuel des produits',
    'inventaire',
    'SELECT name as produit, stock_quantity as stock_actuel, min_stock as stock_minimum FROM products WHERE stock_quantity <= min_stock ORDER BY stock_quantity ASC',
    '{"produit": {"display_name": "Produit", "type": "text"}, "stock_actuel": {"display_name": "Stock Actuel", "type": "number"}, "stock_minimum": {"display_name": "Stock Minimum", "type": "number"}}'
);


-- Insertion des données initiales

INSERT INTO services (title, description, icon, button_text, button_link, button_color, background_color, sort_order) VALUES
('Cuisine Gastronomique', 'Des créations culinaires d exception préparées avec des ingrédients frais et de saison. Une symphonie de saveurs qui éveillera tous vos sens.', 'fas fa-utensils', 'Découvrir le Menu', 'pages/menu.php', 'primary', 'primary', 1),
('Cave à Vins Sélectionnée', 'Une collection prestigieuse de vins du monde entier, soigneusement sélectionnés par notre sommelier pour sublimer chaque plat.', 'fas fa-wine-glass-alt', 'Voir les Boissons', 'pages/menu_boissons.php', 'success', 'success', 2),
('Événements Privés', 'Créons ensemble des moments mémorables. Mariages, séminaires, anniversaires... Chaque événement est une histoire unique.', 'fas fa-calendar-alt', 'Voir les Événements', 'pages/evenements.php', 'warning', 'warning', 3),
('Service Traiteur', 'Faites voyager nos saveurs jusqu à chez vous. Service traiteur premium pour vos réceptions et événements privés.', 'fas fa-truck', 'Nous Contacter', 'pages/contact.php', 'info', 'info', 4),
('Ateliers Culinaires', 'Apprenez les secrets de nos chefs lors d ateliers interactifs. Devenez l artiste de votre propre cuisine.', 'fas fa-graduation-cap', 'Découvrir les Ateliers', 'pages/evenements.php?category=ateliers', 'danger', 'danger', 5),
('Cartes Cadeaux', 'Offrez une expérience culinaire inoubliable. Cartes cadeaux personnalisables pour toutes les occasions.', 'fas fa-gift', 'Offrir un Cadeau', 'pages/contact.php', 'purple', 'purple', 6),
('Réservation en Ligne', 'Réservez votre table en quelques clics. Système de réservation simple et efficace pour planifier votre visite.', 'fas fa-calendar-check', 'Réserver Maintenant', 'pages/reservation.php', 'primary', 'primary', 7),
('Livraison à Domicile', 'Savourez nos plats depuis le confort de votre maison. Service de livraison rapide et soigné.', 'fas fa-motorcycle', 'Commander en Ligne', 'pages/livraison.php', 'success', 'success', 8),
('Brunch Dominical', 'Détendez-vous lors de nos brunchs dominicaux légendaires. Buffet sucré-salé et ambiance conviviale.', 'fas fa-coffee', 'Voir le Brunch', 'pages/menu.php?category=brunch', 'warning', 'warning', 9),
('Menu Dégustation', 'Laissez-vous guider par notre chef avec un menu surprise composé de plusieurs services.', 'fas fa-star', 'Découvrir le Menu', 'pages/menu_degustation.php', 'info', 'info', 10),
('Espace Business', 'Salles de réunion équipées et service traiteur pour vos événements professionnels.', 'fas fa-briefcase', 'Réserver une Salle', 'pages/contact.php', 'dark', 'dark', 11),
('Cours de Mixologie', 'Apprenez à créer des cocktails exceptionnels avec nos barmans expérimentés.', 'fas fa-cocktail', 'Voir les Cours', 'pages/evenements.php?category=mixologie', 'purple', 'purple', 12);
-- Catégories par défaut
INSERT INTO categories (name, description, sort_order) VALUES
('Entrées', 'Nos délicieuses entrées pour commencer votre repas', 1),
('Plats Principaux', 'Nos spécialités culinaires', 2),
('Desserts', 'Nos douceurs maison', 3),
('Boissons', 'Boissons chaudes et froides', 4),
('Vins', 'Sélection de vins soigneusement choisie', 5);

-- Tables par défaut
INSERT INTO tables (table_name, capacity, location) VALUES
('Nuit Étoilée', 2, 'Terrasse couverte'),
('Baiser Secret', 2, 'Coin intimiste'),
('Rêverie Lunaire', 4, 'Près de la baie vitrée'),
('Soupir d''Amour', 2, 'Jardin d''hiver'),
('Oracle Céleste', 6, 'Salon principal'),
('Sanctuaire Ancestral', 4, 'Mezzanine'),
('Mirage Doré', 2, 'Rotonde'),
('Écho des Abysses', 8, 'Salle privative'),
('Newton''s Dream', 4, 'Bibliothèque'),
('Quantum Leap', 6, 'Espace moderne'),
('Galaxie Intérieure', 4, 'Salle étoilée'),
('ADN Gourmand', 2, 'Laboratoire culinaire'),
('Célébration Éternelle', 10, 'Grande salle'),
('Rendez-vous des Anges', 4, 'Belvédère'),
('Harmonie Universelle', 6, 'Espace musique'),
('Défi des Titans', 12, 'Espace banquet'),
('Révélation Soudaine', 2, 'Alcôve privée'),
('Vertige Suprême', 4, 'Pont suspendu'),
('Infini Possibilité', 8, 'Salle polyvalente'),
('Souvenir d''Enfance', 4, 'Jardin fleuri'),
('Cœur Battant', 2, 'Près de la cheminée');

-- Paramètres système par défaut
INSERT INTO settings (setting_key, setting_value, description) VALUES
('restaurant_name', 'Restaurant Le Gourmet', 'Nom du restaurant'),
('restaurant_address', '123 Rue de la Gastronomie, 75001 Paris', 'Adresse du restaurant'),
('restaurant_phone', '+33 1 23 45 67 89', 'Téléphone du restaurant'),
('restaurant_email', 'contact@legourmet.fr', 'Email du restaurant'),
('tax_rate', '20', 'Taux de TVA en pourcentage'),
('currency', 'EUR', 'Devise utilisée'),
('timezone', 'Europe/Paris', 'Fuseau horaire'),
('max_reservation_days', '30', 'Nombre maximum de jours pour réserver à l\'avance'),
('min_reservation_hours', '2', 'Nombre minimum d\'heures pour réserver à l\'avance');

-- Produits d'exemple
INSERT INTO products (name, description, price, category_id, ingredients, preparation_time, is_featured) VALUES
('Foie gras de canard', 'Foie gras de canard du Sud-Ouest, chutney de figues', 28.50, 1, 'Foie gras, figues, épices', 15, TRUE),
('Escargots de Bourgogne', 'Escargots au beurre d\'ail et persil', 18.00, 1, 'Escargots, beurre, ail, persil', 20, FALSE),
('Magret de canard', 'Magret de canard aux cerises, purée de pommes de terre', 32.00, 2, 'Magret de canard, cerises, pommes de terre', 25, TRUE),
('Saumon grillé', 'Saumon d\'Écosse, légumes de saison, sauce hollandaise', 26.00, 2, 'Saumon, légumes, beurre, œufs', 20, FALSE),
('Tarte Tatin', 'Tarte Tatin aux pommes, crème chantilly', 12.00, 3, 'Pommes, pâte brisée, sucre, crème', 15, TRUE),
('Mousse au chocolat', 'Mousse au chocolat noir, coulis de fruits rouges', 10.00, 3, 'Chocolat, œufs, crème, fruits rouges', 10, FALSE);