-- =====================================================================
--  Projet transversal SQL : Animal Crossing New Horizons (EPSI B1)
--  Création de la base « acnh_site » : 10 tables, vides
--    - créatures  : lieu, creature, disponibilite
--    - meubles    : categorie, meuble, saison, proposer (table associative)
--    - villageois : espece, personnalite, villageois
--  Les tables sont remplies ensuite par migration/import.php
-- =====================================================================

DROP DATABASE IF EXISTS acnh_site;
CREATE DATABASE acnh_site CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE acnh_site;

-- ENGINE=InnoDB : sous Wamp, le moteur par défaut est MyISAM, qui ne vérifie pas les clés étrangères.


-- ---------------------------------------------------------------------
-- 1. CRÉATURES (poissons et insectes)
-- ---------------------------------------------------------------------
CREATE TABLE lieu (
  id_lieu  INT          NOT NULL AUTO_INCREMENT,
  nom_lieu VARCHAR(120) NOT NULL,
  PRIMARY KEY (id_lieu),
  UNIQUE (nom_lieu)
) ENGINE=InnoDB;

CREATE TABLE creature (
  id_creature          INT         NOT NULL AUTO_INCREMENT,
  id_lieu              INT         NOT NULL,
  type_creature        VARCHAR(10) NOT NULL,
  numero_encyclopedie  INT         NOT NULL,
  nom_creature         VARCHAR(50) NOT NULL,
  prix_vente_creature  INT         NOT NULL,
  ombre                VARCHAR(20) NULL,
  difficulte           VARCHAR(20) NULL,
  meteo                VARCHAR(20) NOT NULL,
  captures_requises    INT         NOT NULL,
  description          TEXT        NOT NULL,
  phrase_capture       TEXT        NOT NULL,
  nom_fichier_creature VARCHAR(40) NOT NULL,
  PRIMARY KEY (id_creature),
  UNIQUE (type_creature, nom_creature),
  UNIQUE (type_creature, numero_encyclopedie),
  FOREIGN KEY (id_lieu) REFERENCES lieu (id_lieu),
  CHECK (type_creature IN ('poisson', 'insecte')),
  CHECK (prix_vente_creature >= 0)
) ENGINE=InnoDB;

-- Une ligne = une plage horaire d'un mois, dans un hémisphère.
-- La créature est là de heure_debut (incluse) à heure_fin (exclue).
-- Une plage qui passe minuit est enregistrée en deux lignes : 21 h -> 4 h donne 21 -> 24 et 0 -> 4.
CREATE TABLE disponibilite (
  id_disponibilite INT     NOT NULL AUTO_INCREMENT,
  id_creature      INT     NOT NULL,
  hemisphere       CHAR(2) NOT NULL,
  mois             INT     NOT NULL,
  heure_debut      INT     NOT NULL,
  heure_fin        INT     NOT NULL,
  PRIMARY KEY (id_disponibilite),
  UNIQUE (id_creature, hemisphere, mois, heure_debut),
  FOREIGN KEY (id_creature) REFERENCES creature (id_creature),
  CHECK (hemisphere IN ('NH', 'SH')),
  CHECK (mois BETWEEN 1 AND 12),
  CHECK (heure_debut >= 0 AND heure_debut < heure_fin AND heure_fin <= 24)
) ENGINE=InnoDB;


-- ---------------------------------------------------------------------
-- 2. MEUBLES ET SAISONS
-- ---------------------------------------------------------------------
CREATE TABLE categorie (
  id_categorie  INT         NOT NULL AUTO_INCREMENT,
  nom_categorie VARCHAR(30) NOT NULL,
  PRIMARY KEY (id_categorie),
  UNIQUE (nom_categorie)
) ENGINE=InnoDB;

CREATE TABLE meuble (
  id_meuble            INT          NOT NULL AUTO_INCREMENT,
  id_categorie         INT          NOT NULL,
  nom_meuble           VARCHAR(100) NOT NULL,
  prix_achat           INT          NULL,
  prix_vente_meuble    INT          NOT NULL,
  bricolable           BOOLEAN      NOT NULL,
  personnalisable      BOOLEAN      NOT NULL,
  taille               VARCHAR(10)  NOT NULL,
  exterieur            BOOLEAN      NOT NULL,
  etiquette            VARCHAR(40)  NOT NULL,
  serie                VARCHAR(40)  NULL,
  catalogue            VARCHAR(20)  NOT NULL,
  points_hha           INT          NOT NULL,
  version_ajout_meuble VARCHAR(10)  NOT NULL,
  id_interne           INT          NOT NULL,
  PRIMARY KEY (id_meuble),
  UNIQUE (id_interne),
  FOREIGN KEY (id_categorie) REFERENCES categorie (id_categorie),
  CHECK (catalogue IN ('For sale', 'Not for sale', 'Not in catalog', 'Seasonal'))
) ENGINE=InnoDB;

-- Une saison ou un événement du jeu (Halloween, Toy Day, Summer…)
CREATE TABLE saison (
  id_saison   INT         NOT NULL AUTO_INCREMENT,
  nom_saison  VARCHAR(80) NOT NULL,
  type_saison VARCHAR(30) NOT NULL,
  PRIMARY KEY (id_saison),
  UNIQUE (nom_saison)
) ENGINE=InnoDB;

-- Table associative : un meuble peut être proposé pendant plusieurs saisons,
-- et une saison propose plusieurs meubles.
CREATE TABLE proposer (
  id_meuble INT NOT NULL,
  id_saison INT NOT NULL,
  PRIMARY KEY (id_meuble, id_saison),
  FOREIGN KEY (id_meuble) REFERENCES meuble (id_meuble),
  FOREIGN KEY (id_saison) REFERENCES saison (id_saison)
) ENGINE=InnoDB;


-- ---------------------------------------------------------------------
-- 3. VILLAGEOIS
-- ---------------------------------------------------------------------
CREATE TABLE espece (
  id_espece  INT         NOT NULL AUTO_INCREMENT,
  nom_espece VARCHAR(30) NOT NULL,
  PRIMARY KEY (id_espece),
  UNIQUE (nom_espece)
) ENGINE=InnoDB;

CREATE TABLE personnalite (
  id_personnalite  INT         NOT NULL AUTO_INCREMENT,
  nom_personnalite VARCHAR(20) NOT NULL,
  PRIMARY KEY (id_personnalite),
  UNIQUE (nom_personnalite)
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
  UNIQUE (nom_villageois),
  FOREIGN KEY (id_espece) REFERENCES espece (id_espece),
  FOREIGN KEY (id_personnalite) REFERENCES personnalite (id_personnalite),
  CHECK (genre IN ('Male', 'Female')),
  CHECK (jour_anniversaire BETWEEN 1 AND 31 AND mois_anniversaire BETWEEN 1 AND 12)
) ENGINE=InnoDB;
