-- =============================================================================
--  Gestion des frais scolaires — Institut des Oliviers (Kolwezi, RDC)
--  Schéma de la base de données (MySQL / MariaDB, InnoDB, utf8mb4)
--
--  Installation : créer la base `gestion_frais` (interclassement utf8mb4_unicode_ci)
--  puis importer ce fichier, ou lancer simplement : php database/seed.php
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS tentative_connexion, taux_change, notification, recu, paiement, frais, categorie_frais, eleve, classe, utilisateur;
SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
--  Tables du modèle de données
-- -----------------------------------------------------------------------------

CREATE TABLE utilisateur (
  id_utilisateur INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  mot_de_passe VARCHAR(255) NOT NULL,           -- password_hash (bcrypt)
  role ENUM('admin','comptable','parent') NOT NULL,
  telephone VARCHAR(20), adresse VARCHAR(150),
  statut ENUM('actif','inactif') NOT NULL DEFAULT 'actif',
  date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE classe (
  id_classe INT AUTO_INCREMENT PRIMARY KEY,
  niveau VARCHAR(20) NOT NULL, section VARCHAR(50) NOT NULL, libelle VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE eleve (
  id_eleve INT AUTO_INCREMENT PRIMARY KEY,
  matricule VARCHAR(50) NOT NULL UNIQUE,
  nom VARCHAR(50) NOT NULL, prenom VARCHAR(50) NOT NULL,
  date_inscription DATE NOT NULL,
  id_classe INT NOT NULL, id_parent INT NOT NULL,
  FOREIGN KEY (id_classe) REFERENCES classe(id_classe) ON DELETE RESTRICT,
  FOREIGN KEY (id_parent) REFERENCES utilisateur(id_utilisateur) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categorie_frais (
  id_categorie INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE, libelle VARCHAR(100) NOT NULL,
  montant_defaut DECIMAL(10,2) NOT NULL CHECK (montant_defaut > 0),
  periodicite ENUM('unique','mensuelle','trimestrielle','annuelle') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE frais (
  id_frais INT AUTO_INCREMENT PRIMARY KEY,
  montant DECIMAL(10,2) NOT NULL CHECK (montant > 0),
  montant_paye DECIMAL(10,2) NOT NULL DEFAULT 0,
  echeance DATE NOT NULL,
  statut ENUM('impaye','partiel','paye') NOT NULL DEFAULT 'impaye',
  id_eleve INT NOT NULL, id_categorie INT NOT NULL,
  UNIQUE KEY uq_frais (id_eleve, id_categorie, echeance),
  CHECK (montant_paye >= 0 AND montant_paye <= montant),
  FOREIGN KEY (id_eleve) REFERENCES eleve(id_eleve) ON DELETE RESTRICT,
  FOREIGN KEY (id_categorie) REFERENCES categorie_frais(id_categorie) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE paiement (
  id_paiement INT AUTO_INCREMENT PRIMARY KEY,
  reference VARCHAR(100) NOT NULL UNIQUE,
  montant DECIMAL(10,2) NOT NULL CHECK (montant > 0),   -- en devise de base (FC) : compte dans les soldes
  devise_versee CHAR(3) NOT NULL,                        -- devise du versement (CDF ou USD)
  montant_verse DECIMAL(12,2) NOT NULL CHECK (montant_verse > 0),  -- montant réellement versé, dans cette devise
  taux_applique DECIMAL(14,6) NULL,                      -- taux utilisé si devise étrangère (FC pour 1 USD)
  mode ENUM('mobile_money','carte','especes') NOT NULL,
  statut ENUM('en_attente','reussi','echoue') NOT NULL DEFAULT 'en_attente',
  date_paiement DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  id_frais INT NOT NULL, id_comptable INT NULL,
  FOREIGN KEY (id_frais) REFERENCES frais(id_frais) ON DELETE RESTRICT,
  FOREIGN KEY (id_comptable) REFERENCES utilisateur(id_utilisateur) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE recu (
  id_recu INT AUTO_INCREMENT PRIMARY KEY,
  numero VARCHAR(30) NOT NULL UNIQUE,            -- ex. REC-2026-000123
  code_qr VARCHAR(255) NOT NULL,                 -- URL de vérification signée
  fichier_pdf VARCHAR(255) NOT NULL,
  date_emission DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  id_paiement INT NOT NULL UNIQUE,
  FOREIGN KEY (id_paiement) REFERENCES paiement(id_paiement) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notification (
  id_notification INT AUTO_INCREMENT PRIMARY KEY,
  type ENUM('paiement','echeance') NOT NULL,
  message VARCHAR(255) NOT NULL,
  lu BOOLEAN NOT NULL DEFAULT FALSE,
  date_envoi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  id_utilisateur INT NOT NULL,
  FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Taux de change saisis par le comptable (historique conservé) :
-- nombre d'unités de la devise de base pour une unité de la devise étrangère.
CREATE TABLE taux_change (
  id_taux INT AUTO_INCREMENT PRIMARY KEY,
  devise CHAR(3) NOT NULL,
  taux DECIMAL(14,6) NOT NULL CHECK (taux > 0),
  date_application DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  id_utilisateur INT NOT NULL,
  INDEX idx_taux_devise_date (devise, date_application),
  FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
--  Index de performance
-- -----------------------------------------------------------------------------
CREATE INDEX idx_frais_statut       ON frais (statut);
CREATE INDEX idx_frais_echeance     ON frais (echeance);
CREATE INDEX idx_paiement_date      ON paiement (date_paiement);
CREATE INDEX idx_paiement_statut    ON paiement (statut);
CREATE INDEX idx_eleve_nom          ON eleve (nom, prenom);
CREATE INDEX idx_notification_user  ON notification (id_utilisateur, lu);

-- -----------------------------------------------------------------------------
--  Table technique (hors modèle métier) : limitation des tentatives de connexion
--  5 échecs pour un même e-mail depuis une même adresse IP → blocage de 5 minutes.
-- -----------------------------------------------------------------------------
CREATE TABLE tentative_connexion (
  id_tentative INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(100) NOT NULL,
  adresse_ip VARCHAR(45) NOT NULL,
  date_tentative DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tentative (email, adresse_ip, date_tentative)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
