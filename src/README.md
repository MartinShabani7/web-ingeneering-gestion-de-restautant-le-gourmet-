# Projet de Gestion de Restaurant

Ce projet est un système complet de gestion de restaurant, conçu pour optimiser les opérations quotidiennes, de la prise de commande à la gestion des réservations et des stocks. Il comprend des interfaces distinctes pour les administrateurs et les membres (clients/personnel), ainsi qu'une page d'accueil publique.

## Table des Matières

- [Fonctionnalités Clés](#fonctionnalités-clés)
- [Technologies Utilisées](#technologies-utilisées)
- [Structure du Projet](#structure-du-projet)
- [Installation](#installation)
  - [Prérequis](#prérequis)
  - [Via Docker Compose (Recommandé)](#via-docker-compose-recommandé)
  - [Manuelle](#manuelle)
- [Configuration](#configuration)
- [Utilisation](#utilisation)
- [Accès aux Interfaces](#accès-aux-interfaces)

## Fonctionnalités Clés

Le système offre une gamme étendue de fonctionnalités pour gérer efficacement un restaurant :

### Interface Publique
- **Page d'Accueil Attrayante** : Présentation du restaurant, des services et des témoignages clients.
- **Formulaire de Contact** : Permet aux visiteurs d'envoyer des messages directement au restaurant.
- **Suivi des Visiteurs** : Statistiques de base sur les visites du site.

### Interface Membre (Clients & Personnel)
- **Tableau de Bord Personnel** : Vue d'ensemble des activités et informations pertinentes.
- **Gestion des Commandes** : Historique des commandes, possibilité de passer de nouvelles commandes.
- **Gestion des Réservations** : Historique des réservations, possibilité d'effectuer de nouvelles réservations de table.
- **Gestion du Profil** : Mise à jour des informations personnelles.
- **Témoignages** : Soumission de commentaires et d'avis sur le restaurant.

### Interface Administrateur
- **Tableau de Bord Complet** : Vue centralisée des opérations du restaurant.
- **Gestion du Menu** : Création, modification et suppression de catégories et de produits (plats).
- **Gestion des Commandes** : Suivi et mise à jour du statut des commandes.
- **Gestion des Réservations** : Affichage, confirmation et annulation des réservations.
- **Gestion des Tables** : Configuration et suivi de la disponibilité des tables.
- **Gestion des Stocks** : Suivi de l'inventaire des ingrédients et des produits.
- **Gestion des Partenaires** : Administration des informations sur les partenaires.
- **Gestion des Témoignages** : Modération et approbation des avis clients.
- **Gestion des Utilisateurs** : Création, modification et gestion des rôles (admin, manager, staff, customer).
- **Rapports Détaillés** : Génération de rapports personnalisés (ventes, réservations, etc.) exportables en PDF, Excel, ou Word.
- **Statistiques des Visiteurs** : Analyse approfondie du trafic du site.
- **Paramètres Système** : Configuration générale de l'application.

## Technologies Utilisées

Le projet est développé en utilisant les technologies suivantes :

- **Backend** : PHP
- **Base de Données** : MySQL (via PDO)
- **Serveur Web** : Nginx (en environnement Docker)
- **Frontend** : HTML, CSS, JavaScript (avec des composants Bootstrap)
- **Gestion des Dépendances PHP** : Composer
- **Conteneurisation** : Docker, Docker Compose
- **Bibliothèques PHP** :
    - `phpoffice/phpspreadsheet` : Pour la génération de fichiers Excel.
    - `tecnickcom/tcpdf` : Pour la génération de fichiers PDF.
    - `phpoffice/phpword` : Pour la génération de fichiers Word.
    - `phpmailer/phpmailer` : Pour l'envoi d'e-mails (par exemple, pour le formulaire de contact).
    - `dompdf/dompdf` : Une autre bibliothèque pour la génération de PDF.

## Structure du Projet

Le projet est organisé de manière modulaire :

```
web-ingeneering/
├── database/                 # Scripts SQL pour l'initialisation de la base de données
│   └── init.sql              # (Potentiellement un dossier vide, le schéma est dans src/database/)
├── docker/                   # Fichiers de configuration Docker (Nginx, PHP)
│   ├── nginx/
│   └── php/
├── docker-compose.yml        # Configuration Docker Compose pour l'environnement de développement
└── src/                      # Code source de l'application PHP
    ├── admin/                # Modules de l'interface administrateur
    │   ├── api/              # API pour l'administration
    │   ├── assets/           # Actifs (CSS, JS, images) spécifiques à l'admin
    │   ├── rapports/          # Gestion et génération de rapports
    │   ├── statistique_visiteurs/ # Suivi des visiteurs
    │   └── ...               # Autres modules (categories, products, orders, users, etc.)
    ├── assets/               # Actifs globaux (CSS, JS, images)
    ├── auth/                 # Logique d'authentification (connexion, inscription, etc.)
    ├── config/               # Fichiers de configuration (base de données, sécurité, mailer, helpers)
    ├── database/             # Scripts SQL et fichiers de connexion à la base de données
    │   ├── schema.sql        # Schéma de la base de données
    │   └── reservation.sql   # Schéma spécifique aux réservations
    │   └── init.php          # Initialisation de la connexion PDO
    ├── include/              # Fichiers inclus (en-têtes, pieds de page, etc.)
    ├── logs/                 # Répertoire pour les logs
    ├── member/               # Modules de l'interface membre
    │   └── ...               # (commandes, reservations, profile, etc.)
    ├── pages/                # Pages publiques ou statiques
    ├── vendor/               # Dépendances PHP gérées par Composer
    ├── .env.example          # Exemple de fichier d'environnement
    ├── composer.json         # Dépendances Composer
    ├── index.php             # Point d'entrée principal de l'application
    └── send_messag.php       # Script pour l'envoi de messages (contact)
```

## Installation

### Prérequis

Assurez-vous d'avoir les éléments suivants installés sur votre machine :

- [Docker](https://docs.docker.com/get-docker/)
- [Docker Compose](https://docs.docker.com/compose/install/)

### Via Docker Compose (Recommandé)

1.  **Cloner le dépôt** (si ce n'est pas déjà fait) :
    ```bash
    git clone <URL_DU_DEPOT>
    cd web-ingeneering
    ```
2.  **Créer le fichier d'environnement** :
    Copiez le fichier `.env.example` et renommez-le en `.env` dans le répertoire `src/`.
    ```bash
    cp src/.env.example src/.env
    ```
    Modifiez les variables d'environnement dans `src/.env` si nécessaire. Les valeurs par défaut dans `docker-compose.yml` sont :
    ```
    DB_HOST=mysql
    DB_PORT=3306
    DB_NAME=restaurant_gourmet
    DB_USER=root
    DB_PASSWORD=root
    ```
3.  **Démarrer les services Docker** :
    Naviguez vers le répertoire racine du projet (`web-ingeneering/`) et exécutez :
    ```bash
    docker-compose up --build -d
    ```
    Cela va construire les images Docker, créer les conteneurs (Nginx, PHP, MySQL, PHPMyAdmin) et les démarrer en arrière-plan.

4.  **Initialiser la base de données** :
    Le fichier `database/init.sql` est automatiquement exécuté lors du démarrage du conteneur MySQL pour créer la base de données et les tables. Si vous avez des données initiales ou des modifications de schéma, assurez-vous qu'elles sont incluses dans ce fichier ou dans `src/database/schema.sql`. En suite exécutez le fichier schema.sql dans phpmyadmin pour créer la base de données et les tables nécessaires pour le projet.

5.  **Installer les dépendances PHP** :
    Accédez au conteneur PHP et installez les dépendances Composer :
    ```bash
    docker-compose exec php composer install -d /var/www/html
    ```

### Manuelle (Alternative)

1.  **Prérequis** :
    - Serveur Web (Apache ou Nginx)
    - PHP 7.4+ (avec les extensions PDO, MySQLi, GD, etc.)
    - MySQL 8.0+
    - Composer

2.  **Configuration du Serveur Web** :
    Configurez votre serveur web pour pointer la racine du document vers le répertoire `src/`.

3.  **Configuration de la Base de Données** :
    - Créez une base de données MySQL (par exemple, `restaurant_gourmet`).
    - Importez le fichier `src/database/schema.sql` et `src/database/reservation.sql` dans votre base de données.

4.  **Fichier d'environnement** :
    Copiez `src/.env.example` en `src/.env` et configurez les informations de connexion à votre base de données.

5.  **Installation des dépendances PHP** :
    Naviguez vers le répertoire `src/` et exécutez :
    ```bash
    composer install
    ```

## Configuration

Le fichier `src/.env` contient les variables d'environnement essentielles :

DB_HOST=localhost
DB_PORT=3306
DB_NAME=restaurant_gourmet
DB_USER=root
DB_PASSWORD=root

## Utilisation

Après l'installation et la configuration, vous pouvez accéder à l'application via votre navigateur web.

- **URL de l'application (Docker)** : `http://localhost:8080`
- **URL de PHPMyAdmin (Docker)** : `http://localhost:8081` (Utilisateur: `root`, Mot de passe: `root`)

## Accès aux Interfaces

### Accès Administrateur

Pour accéder à l'interface administrateur, vous devrez créer un utilisateur avec le rôle `admin` directement dans la base de données ou via un script d'initialisation qui est dans le dossier DATABASE et se nomme: ini.php; Lorsque vous exécutez ce fichier, un utilisateur avec le rôle de l'Admin sera automatiquement créé; en suivant les processuss, à la fin le système vous donnera les identifiants pour vous connecter et avoir accès à l'espace Admin du système. L'application redirige automatiquement les utilisateurs connectés avec le rôle `admin` vers `/admin/dashboard.php`.

### Accès Membre

Les utilisateurs avec le rôle `customer` ou `staff` seront redirigés vers `/member/dashboard.php` après connexion; mais pour celà, il faut d'abord créer un compte dans le système si vous en n'avez pas encore