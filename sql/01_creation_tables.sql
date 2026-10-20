-- =====================================================================
--  Projet transversal SQL — Animal Crossing : New Horizons (EPSI B1)
--  Étape 1 de la migration : création de la base « acnh_site » (10 tables, vides)
--    - créatures  : lieu, creature, disponibilite
--    - meubles    : categorie, meuble, saison, proposer (table associative)
--    - villageois : espece, personnalite, villageois
--  Lancé par migration/import.php ; le remplissage est fait par sql/02_migration.sql.
-- =====================================================================

DROP DATABASE IF EXISTS acnh_site;
CREATE DATABASE acnh_site CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE acnh_site;

-- ---------------------------------------------------------------------
-- 1. CRÉATURES (poissons et insectes)
-- ---------------------------------------------------------------------
CREATE TABLE lieu (
  id_lieu   INT          NOT NULL AUTO_INCREMENT,
  nom_lieu  VARCHAR(120) NOT NULL,
  zone_lieu VARCHAR(20)  NOT NULL,
  PRIMARY KEY (id_lieu),
  UNIQUE KEY uq_nom_lieu (nom_lieu)
) ENGINE=InnoDB;

CREATE TABLE creature (
  id_creature          INT         NOT NULL AUTO_INCREMENT,
  id_lieu              INT         NOT NULL,
  type_creature        VARCHAR(10) NOT NULL,
  numero_encyclopedie  INT         NOT NULL,
  nom_creature         VARCHAR(50) NOT NULL,
  prix_vente_creature  INT         NOT NULL,
  ombre                VARCHAR(20) NULL,            -- poissons uniquement
  difficulte           VARCHAR(20) NULL,            -- poissons uniquement
  meteo                VARCHAR(20) NOT NULL,
  captures_requises    INT         NOT NULL,
  description          TEXT        NOT NULL,
  phrase_capture       TEXT        NOT NULL,
  nom_fichier_creature VARCHAR(40) NOT NULL,
  PRIMARY KEY (id_creature),
  UNIQUE KEY uq_type_nom (type_creature, nom_creature),
  UNIQUE KEY uq_type_numero (type_creature, numero_encyclopedie),
  KEY idx_lieu (id_lieu),
  CONSTRAINT fk_creature_lieu FOREIGN KEY (id_lieu) REFERENCES lieu (id_lieu),
  CONSTRAINT ck_type_creature CHECK (type_creature IN ('poisson', 'insecte')),
  CONSTRAINT ck_prix_creature CHECK (prix_vente_creature >= 0)
) ENGINE=InnoDB;

-- Une ligne = une plage horaire d'un mois, pour un hémisphère.
-- heure_fin est exclue ; si heure_debut > heure_fin, la plage passe minuit.
CREATE TABLE disponibilite (
  id_disponibilite INT     NOT NULL AUTO_INCREMENT,
  id_creature      INT     NOT NULL,
  hemisphere       CHAR(2) NOT NULL,
  mois             INT     NOT NULL,
  heure_debut      INT     NOT NULL,
  heure_fin        INT     NOT NULL,
  PRIMARY KEY (id_disponibilite),
  UNIQUE KEY uq_dispo (id_creature, hemisphere, mois, heure_debut),
  KEY idx_dispo_moment (hemisphere, mois),
  CONSTRAINT fk_dispo_creature FOREIGN KEY (id_creature)
    REFERENCES creature (id_creature) ON DELETE CASCADE,
  CONSTRAINT ck_hemisphere CHECK (hemisphere IN ('NH', 'SH')),
  CONSTRAINT ck_mois       CHECK (mois BETWEEN 1 AND 12),
  CONSTRAINT ck_heures     CHECK (heure_debut BETWEEN 0 AND 23
                                  AND heure_fin BETWEEN 0 AND 24)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. MEUBLES (Housewares, Miscellaneous, Wall-mounted, Ceiling Decor) ET SAISONS
-- ---------------------------------------------------------------------
CREATE TABLE categorie (
  id_categorie  INT         NOT NULL AUTO_INCREMENT,
  nom_categorie VARCHAR(30) NOT NULL,
  PRIMARY KEY (id_categorie),
  UNIQUE KEY uq_nom_categorie (nom_categorie)
) ENGINE=InnoDB;

CREATE TABLE meuble (
  id_meuble            INT          NOT NULL AUTO_INCREMENT,
  id_categorie         INT          NOT NULL,
  nom_meuble           VARCHAR(100) NOT NULL,
  prix_achat           INT          NULL,           -- NULL = pas en vente
  prix_vente_meuble    INT          NOT NULL,
  bricolable           TINYINT(1)   NOT NULL,
  personnalisable      TINYINT(1)   NOT NULL,
  taille               VARCHAR(10)  NOT NULL,
  exterieur            TINYINT(1)   NOT NULL,
  etiquette            VARCHAR(40)  NOT NULL,
  serie                VARCHAR(40)  NULL,
  catalogue            VARCHAR(20)  NOT NULL,       -- 'Seasonal' = meuble de saison
  points_hha           INT          NOT NULL,
  version_ajout_meuble VARCHAR(10)  NOT NULL,
  id_interne           INT          NOT NULL,
  PRIMARY KEY (id_meuble),
  UNIQUE KEY uq_id_interne (id_interne),
  KEY idx_categorie (id_categorie),
  KEY idx_catalogue (catalogue),
  CONSTRAINT fk_meuble_categorie FOREIGN KEY (id_categorie)
    REFERENCES categorie (id_categorie),
  CONSTRAINT ck_catalogue CHECK (catalogue IN
    ('For sale', 'Not for sale', 'Not in catalog', 'Seasonal'))
) ENGINE=InnoDB;

-- Une saison ou un événement du jeu (Halloween, Toy Day, Summer…), d'après l'onglet
-- « Seasons and Events » du Google Sheet. Seules les saisons qui proposent des meubles sont gardées.
CREATE TABLE saison (
  id_saison   INT         NOT NULL AUTO_INCREMENT,
  nom_saison  VARCHAR(80) NOT NULL,
  type_saison VARCHAR(30) NOT NULL,
  PRIMARY KEY (id_saison),
  UNIQUE KEY uq_nom_saison (nom_saison)
) ENGINE=InnoDB;

-- Table associative (plusieurs à plusieurs) : un meuble peut être proposé pendant plusieurs
-- saisons, et une saison propose plusieurs meubles.
CREATE TABLE proposer (
  id_meuble INT NOT NULL,
  id_saison INT NOT NULL,
  PRIMARY KEY (id_meuble, id_saison),
  KEY idx_proposer_saison (id_saison),
  CONSTRAINT fk_proposer_meuble FOREIGN KEY (id_meuble)
    REFERENCES meuble (id_meuble) ON DELETE CASCADE,
  CONSTRAINT fk_proposer_saison FOREIGN KEY (id_saison)
    REFERENCES saison (id_saison) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. VILLAGEOIS
-- ---------------------------------------------------------------------
CREATE TABLE espece (
  id_espece  INT         NOT NULL AUTO_INCREMENT,
  nom_espece VARCHAR(30) NOT NULL,
  PRIMARY KEY (id_espece),
  UNIQUE KEY uq_nom_espece (nom_espece)
) ENGINE=InnoDB;

CREATE TABLE personnalite (
  id_personnalite  INT         NOT NULL AUTO_INCREMENT,
  nom_personnalite VARCHAR(20) NOT NULL,
  PRIMARY KEY (id_personnalite),
  UNIQUE KEY uq_nom_personnalite (nom_personnalite)
) ENGINE=InnoDB;

CREATE TABLE villageois (
  id_villageois            INT         NOT NULL AUTO_INCREMENT,
  id_espece                INT         NOT NULL,
  id_personnalite          INT         NOT NULL,
  nom_villageois           VARCHAR(30) NOT NULL,
  genre                    VARCHAR(10) NOT NULL,
  sous_type                CHAR(1)     NOT NULL,
  jour_anniversaire        INT         NOT NULL,
  mois_anniversaire        INT         NOT NULL,
  phrase_fetiche           VARCHAR(50) NOT NULL,
  chanson_preferee         VARCHAR(50) NOT NULL,
  citation_preferee        TEXT        NOT NULL,
  version_ajout_villageois VARCHAR(10) NOT NULL,
  nom_fichier_villageois   VARCHAR(20) NOT NULL,
  PRIMARY KEY (id_villageois),
  UNIQUE KEY uq_nom_villageois (nom_villageois),
  KEY idx_espece (id_espece),
  KEY idx_personnalite (id_personnalite),
  CONSTRAINT fk_villageois_espece FOREIGN KEY (id_espece)
    REFERENCES espece (id_espece),
  CONSTRAINT fk_villageois_personnalite FOREIGN KEY (id_personnalite)
    REFERENCES personnalite (id_personnalite),
  CONSTRAINT ck_genre CHECK (genre IN ('Male', 'Female')),
  CONSTRAINT ck_anniv CHECK (jour_anniversaire BETWEEN 1 AND 31
                             AND mois_anniversaire BETWEEN 1 AND 12)
) ENGINE=InnoDB;
