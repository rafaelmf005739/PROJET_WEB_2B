-- ═══════════════════════════════════════════════════════
--  OmnesEvent — schema.sql (version finale)
--  Toutes les tables + modifications de la session
--  Import via phpMyAdmin : sélectionner la BDD → onglet SQL → Importer ce fichier
-- ═══════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS omnesevent
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE omnesevent;


CREATE TABLE IF NOT EXISTS utilisateurs (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  nom           VARCHAR(100)  NOT NULL,
  email         VARCHAR(150)  NOT NULL UNIQUE,
  mot_de_passe  VARCHAR(255)  NOT NULL,
  role          ENUM('admin','organisateur','participant') NOT NULL DEFAULT 'participant',
  association   VARCHAR(100)  DEFAULT NULL,
  avatar        VARCHAR(10)   DEFAULT NULL,
  photo         VARCHAR(255)  DEFAULT NULL,
  actif         TINYINT(1)    NOT NULL DEFAULT 1,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS evenements (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  titre           VARCHAR(200)  NOT NULL,
  description     TEXT          NOT NULL,
  categorie       ENUM('Soirée','Sport','Culture') NOT NULL,
  date_event      DATE          NOT NULL,
  lieu            VARCHAR(200)  NOT NULL,
  association     VARCHAR(100)  NOT NULL,
  capacite        INT           NOT NULL CHECK (capacite > 0),
  inscrits        INT           NOT NULL DEFAULT 0,
  affiche         VARCHAR(255)  DEFAULT NULL,
  emoji           VARCHAR(10)   DEFAULT '📅',
  statut          ENUM('ouvert','complet','annule') NOT NULL DEFAULT 'ouvert',
  organisateur_id INT           NOT NULL,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (organisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS reservations (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  code           VARCHAR(20)   NOT NULL UNIQUE,
  utilisateur_id INT           NOT NULL,
  evenement_id   INT           NOT NULL,
  statut         ENUM('confirme','annule','utilise') NOT NULL DEFAULT 'confirme',
  created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
  FOREIGN KEY (evenement_id)   REFERENCES evenements(id)   ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS demandes_organisateur (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nom         VARCHAR(100)  NOT NULL,
  email       VARCHAR(150)  NOT NULL,
  association VARCHAR(100)  NOT NULL,
  statut      ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS signalements (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  evenement_id INT           NOT NULL,
  raison       TEXT          NOT NULL,
  signale_par  INT           NOT NULL,
  traite       TINYINT(1)    NOT NULL DEFAULT 0,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (evenement_id) REFERENCES evenements(id)   ON DELETE CASCADE,
  FOREIGN KEY (signale_par)  REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;


INSERT INTO utilisateurs (nom, email, mot_de_passe, role, association, avatar) VALUES
  ('Sophie Martin', 'admin@omnes.edu',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin',        NULL,  'SM'),
  ('Lucas Bernard', 'orga@omnes.edu',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'organisateur', 'BDE', 'LB'),
  ('Emma Dubois',   'emma@omnes.edu',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'participant',  NULL,  'ED'),
  ('Paul Lefèvre',  'paul@omnes.edu',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'participant',  NULL,  'PL'),
  ('Clara Moreau',  'orga2@omnes.edu',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'organisateur', 'BDS', 'CM');

INSERT INTO evenements (titre, description, categorie, date_event, lieu, association, capacite, inscrits, emoji, statut, organisateur_id) VALUES
  ('Soirée d\'intégration',     'Grande soirée d\'intégration pour rencontrer les nouveaux étudiants. Musique live, animations, buvette et surprises !', 'Soirée',  '2026-05-25', 'Campus Omnes – Salle Panoramique',    'BDE',             150, 126, '🎉', 'ouvert',  2),
  ('Tournoi de football',        'Tournoi inter-promo organisé par le BDS. Équipes de 5, matchs de 15 min. Inscriptions par équipe uniquement.',         'Sport',   '2026-05-30', 'Stade universitaire – Terrain 3',     'BDS',              80,  80, '⚽', 'complet', 5),
  ('Conférence Entrepreneuriat', 'Conférence autour de la création d\'entreprise. Intervenants : 3 entrepreneurs Omnes Alumni.',                         'Culture', '2026-06-02', 'Amphithéâtre A – Bâtiment principal', 'Junior Entreprise',120,  64, '💡', 'ouvert',  2),
  ('Tournoi de tennis de table', 'Open de ping-pong du campus ! Ouvert à tous les niveaux. Plateaux et raquettes fournis.',                              'Sport',   '2026-06-10', 'Salle des sports – Niveau 0',         'BDS',              32,  18, '🏓', 'ouvert',  5),
  ('Soirée cinéma en plein air', 'Projection en plein air d\'un film culte. Transats, popcorn et bonne humeur garantis !',                               'Culture', '2026-06-18', 'Parvis central – Campus Omnes',       'BDE',             200,  45, '🎬', 'ouvert',  2);

INSERT INTO reservations (code, utilisateur_id, evenement_id, statut) VALUES
  ('TKT-A1B2C3', 3, 1, 'confirme'),
  ('TKT-D4E5F6', 3, 2, 'confirme');

INSERT INTO demandes_organisateur (nom, email, association, statut) VALUES
  ('Antoine Roux', 'a.roux@omnes.edu',    'Club Photo',      'pending'),
  ('Léa Garnier',  'l.garnier@omnes.edu', 'Association Eco', 'pending'),
  ('Noah Petit',   'n.petit@omnes.edu',   'Club Débat',      'pending');

INSERT INTO signalements (evenement_id, raison, signale_par) VALUES
  (1, 'Contenu inapproprié dans la description', 3),
  (3, 'Date incorrecte signalée',                4);
