-- =====================================================================
-- Projet transversal Animal Crossing : New Horizons — EPSI B1
-- Script de création de la base « acnh » (MySQL 8, via phpMyAdmin ou migration/import.php)
-- 21 tables : 15 tables issues des entités du MCD + 6 tables associatives.
--
-- ENGINE=InnoDB est écrit sur chaque table : WampServer crée les tables en
-- MyISAM par défaut, et MyISAM ne vérifie pas les clés étrangères.
--
-- Ordre : tables de référence -> tables principales -> tables associatives
-- Conventions : snake_case, sans accent ; id_<table> = clé primaire ;
-- une clé étrangère porte le nom de la clé primaire qu'elle référence.
-- =====================================================================

DROP DATABASE IF EXISTS acnh;
CREATE DATABASE acnh CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE acnh;

-- ---------------------------------------------------------------------
-- Tables de référence (listes de valeurs partagées)
-- ---------------------------------------------------------------------

CREATE TABLE couleur (
    id_couleur   INT          NOT NULL AUTO_INCREMENT,
    nom_couleur  VARCHAR(20)  NOT NULL,
    PRIMARY KEY (id_couleur),
    UNIQUE (nom_couleur)
) ENGINE=InnoDB;

CREATE TABLE lieu (
    id_lieu    INT           NOT NULL AUTO_INCREMENT,
    nom_lieu   VARCHAR(120)  NOT NULL,             -- texte du Google Sheet (Where/How)
    zone_lieu  VARCHAR(20)   NOT NULL,             -- regroupement pour les filtres : Mer, Rivière, Étang…
    PRIMARY KEY (id_lieu),
    UNIQUE (nom_lieu)
) ENGINE=InnoDB;

CREATE TABLE espece (
    id_espece   INT          NOT NULL AUTO_INCREMENT,
    nom_espece  VARCHAR(30)  NOT NULL,
    PRIMARY KEY (id_espece),
    UNIQUE (nom_espece)
) ENGINE=InnoDB;

CREATE TABLE personnalite (
    id_personnalite   INT          NOT NULL AUTO_INCREMENT,
    nom_personnalite  VARCHAR(20)  NOT NULL,
    PRIMARY KEY (id_personnalite),
    UNIQUE (nom_personnalite)
) ENGINE=InnoDB;

CREATE TABLE loisir (
    id_loisir   INT          NOT NULL AUTO_INCREMENT,
    nom_loisir  VARCHAR(20)  NOT NULL,
    PRIMARY KEY (id_loisir),
    UNIQUE (nom_loisir)
) ENGINE=InnoDB;

CREATE TABLE style (
    id_style   INT          NOT NULL AUTO_INCREMENT,
    nom_style  VARCHAR(20)  NOT NULL,
    PRIMARY KEY (id_style),
    UNIQUE (nom_style)
) ENGINE=InnoDB;

CREATE TABLE categorie (
    id_categorie   INT          NOT NULL AUTO_INCREMENT,
    nom_categorie  VARCHAR(30)  NOT NULL,          -- = nom de l'onglet (Housewares, Miscellaneous…)
    PRIMARY KEY (id_categorie),
    UNIQUE (nom_categorie)
) ENGINE=InnoDB;

CREATE TABLE source (
    id_source   INT          NOT NULL AUTO_INCREMENT,
    nom_source  VARCHAR(50)  NOT NULL,             -- Nook's Cranny, Crafting, C.J.…
    PRIMARY KEY (id_source),
    UNIQUE (nom_source)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Poissons et insectes
-- ---------------------------------------------------------------------

CREATE TABLE creature (
    id_creature          INT          NOT NULL AUTO_INCREMENT,
    id_lieu              INT          NOT NULL,
    type_creature        VARCHAR(10)  NOT NULL,     -- poisson / insecte
    numero_encyclopedie  INT          NOT NULL,     -- colonne # (ordre de l'encyclopédie)
    nom_creature         VARCHAR(50)  NOT NULL,
    prix_vente_creature  INT          NOT NULL,     -- en clochettes (entier)
    ombre                VARCHAR(20)  NULL,         -- poissons uniquement
    difficulte           VARCHAR(20)  NULL,         -- poissons uniquement
    meteo                VARCHAR(20)  NOT NULL,     -- Any weather / Any except rain / Rain only
    captures_requises    INT          NOT NULL,     -- captures totales avant apparition
    description          TEXT         NOT NULL,
    phrase_capture       TEXT         NOT NULL,
    nom_fichier_creature VARCHAR(40)  NOT NULL,     -- image = CDN + /MenuIcon/ + fichier + .png
    PRIMARY KEY (id_creature),
    UNIQUE (type_creature, nom_creature),
    UNIQUE (type_creature, numero_encyclopedie),
    CHECK (type_creature IN ('poisson', 'insecte')),
    CHECK (prix_vente_creature >= 0),
    FOREIGN KEY (id_lieu) REFERENCES lieu (id_lieu)
) ENGINE=InnoDB;

-- Une ligne = un créneau horaire, pour un mois, dans un hémisphère.
-- "All day" = 0 -> 24 ; un créneau qui passe minuit a heure_debut > heure_fin (ex. 16 -> 9).
CREATE TABLE disponibilite (
    id_disponibilite  INT      NOT NULL AUTO_INCREMENT,
    id_creature       INT      NOT NULL,
    hemisphere        CHAR(2)  NOT NULL,            -- NH (nord) / SH (sud)
    mois              INT      NOT NULL,
    heure_debut       INT      NOT NULL,            -- incluse
    heure_fin         INT      NOT NULL,            -- exclue
    PRIMARY KEY (id_disponibilite),
    UNIQUE (id_creature, hemisphere, mois, heure_debut),
    INDEX idx_dispo_moment (hemisphere, mois),          -- recherche « disponible à l'instant T »
    CHECK (hemisphere IN ('NH', 'SH')),
    CHECK (mois BETWEEN 1 AND 12),
    CHECK (heure_debut BETWEEN 0 AND 23),
    CHECK (heure_fin BETWEEN 1 AND 24),
    FOREIGN KEY (id_creature) REFERENCES creature (id_creature) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Villageois
-- ---------------------------------------------------------------------

CREATE TABLE villageois (
    id_villageois             INT          NOT NULL AUTO_INCREMENT,
    id_espece                 INT          NOT NULL,
    id_personnalite           INT          NOT NULL,
    id_loisir                 INT          NOT NULL,
    nom_villageois            VARCHAR(30)  NOT NULL,
    genre                     VARCHAR(10)  NOT NULL,     -- Male / Female
    sous_type                 CHAR(1)      NOT NULL,     -- A / B
    jour_anniversaire         INT          NOT NULL,
    mois_anniversaire         INT          NOT NULL,
    phrase_fetiche            VARCHAR(50)  NOT NULL,
    chanson_preferee          VARCHAR(50)  NOT NULL,
    citation_preferee         TEXT         NOT NULL,
    version_ajout_villageois  VARCHAR(10)  NOT NULL,
    nom_fichier_villageois    VARCHAR(20)  NOT NULL,     -- image = CDN + /NpcIcon/ + fichier + .png
    PRIMARY KEY (id_villageois),
    UNIQUE (nom_villageois),
    CHECK (genre IN ('Male', 'Female')),
    CHECK (sous_type IN ('A', 'B')),
    CHECK (jour_anniversaire BETWEEN 1 AND 31),
    CHECK (mois_anniversaire BETWEEN 1 AND 12),
    FOREIGN KEY (id_espece)       REFERENCES espece (id_espece),
    FOREIGN KEY (id_personnalite) REFERENCES personnalite (id_personnalite),
    FOREIGN KEY (id_loisir)       REFERENCES loisir (id_loisir)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Meubles (onglets Housewares, Miscellaneous, Wall-mounted, Ceiling Decor)
-- Le Google Sheet a une ligne par variante : on sépare l'objet (meuble)
-- de ses déclinaisons (variante).
-- ---------------------------------------------------------------------

CREATE TABLE meuble (
    id_meuble             INT          NOT NULL AUTO_INCREMENT,
    id_categorie          INT          NOT NULL,
    nom_meuble            VARCHAR(100) NOT NULL,
    prix_achat            INT          NULL,          -- NULL = NFS (pas en vente)
    prix_vente_meuble     INT          NOT NULL,
    bricolable            BOOLEAN      NOT NULL,      -- colonne DIY
    personnalisable       BOOLEAN      NOT NULL,      -- colonne Body Customize
    taille                VARCHAR(10)  NOT NULL,      -- ex. 2x1 (en cases)
    exterieur             BOOLEAN      NOT NULL,      -- colonne Outdoor
    etiquette             VARCHAR(40)  NOT NULL,      -- colonne Tag (Table, Chair…)
    serie                 VARCHAR(40)  NULL,          -- colonne HHA Series (NULL = aucune)
    catalogue             VARCHAR(20)  NOT NULL,      -- For sale / Not for sale / Seasonal / Not in catalog
    exclusif_evenement    BOOLEAN      NULL,          -- NULL = meuble sans saison/événement
    points_hha            INT          NOT NULL,
    version_ajout_meuble  VARCHAR(10)  NOT NULL,
    id_interne            INT          NOT NULL,      -- Internal ID du jeu (sert à relier les villageois)
    PRIMARY KEY (id_meuble),
    UNIQUE (id_interne),
    CHECK (prix_achat IS NULL OR prix_achat >= 0),
    CHECK (prix_vente_meuble >= 0),
    FOREIGN KEY (id_categorie) REFERENCES categorie (id_categorie)
) ENGINE=InnoDB;

CREATE TABLE variante (
    id_variante           INT          NOT NULL AUTO_INCREMENT,
    id_meuble             INT          NOT NULL,
    nom_variation         VARCHAR(60)  NULL,          -- NULL = pas de variation
    nom_motif             VARCHAR(60)  NULL,          -- NULL = pas de motif
    nom_fichier_variante  VARCHAR(80)  NOT NULL,      -- image = CDN + /FtrIcon/ + fichier + .png
    id_unique             VARCHAR(20)  NOT NULL,      -- Unique Entry ID du Google Sheet
    PRIMARY KEY (id_variante),
    UNIQUE (id_unique),
    FOREIGN KEY (id_meuble) REFERENCES meuble (id_meuble) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Saisons et événements (onglet Seasons and Events)
CREATE TABLE evenement (
    id_evenement    INT          NOT NULL AUTO_INCREMENT,
    nom_evenement   VARCHAR(80)  NOT NULL,            -- clé de liaison avec la colonne Season/Event des meubles
    type_evenement  VARCHAR(30)  NOT NULL,            -- Calendar season, Special event…
    nom_affiche     VARCHAR(80)  NOT NULL,
    date_variable   BOOLEAN      NOT NULL,            -- la date change selon l'année
    PRIMARY KEY (id_evenement),
    UNIQUE (nom_evenement)
) ENGINE=InnoDB;

CREATE TABLE periode (
    id_periode          INT      NOT NULL AUTO_INCREMENT,
    id_evenement        INT      NOT NULL,
    annee               INT      NOT NULL,
    hemisphere_periode  CHAR(2)  NOT NULL,
    date_debut          DATE     NOT NULL,
    date_fin            DATE     NOT NULL,
    PRIMARY KEY (id_periode),
    UNIQUE (id_evenement, annee, hemisphere_periode),
    INDEX idx_periode_dates (hemisphere_periode, date_debut, date_fin),   -- recherche par date
    CHECK (hemisphere_periode IN ('NH', 'SH')),
    CHECK (date_fin >= date_debut),
    FOREIGN KEY (id_evenement) REFERENCES evenement (id_evenement) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tables associatives (associations N:N du MCD)
-- ---------------------------------------------------------------------

-- AIMER : un villageois aime 1 ou 2 styles (Style 1, Style 2)
CREATE TABLE aimer (
    id_villageois  INT  NOT NULL,
    id_style       INT  NOT NULL,
    PRIMARY KEY (id_villageois, id_style),
    FOREIGN KEY (id_villageois) REFERENCES villageois (id_villageois) ON DELETE CASCADE,
    FOREIGN KEY (id_style)      REFERENCES style (id_style)
) ENGINE=InnoDB;

-- PREFERER : un villageois préfère 1 ou 2 couleurs (Color 1, Color 2)
CREATE TABLE preferer (
    id_villageois  INT  NOT NULL,
    id_couleur     INT  NOT NULL,
    PRIMARY KEY (id_villageois, id_couleur),
    FOREIGN KEY (id_villageois) REFERENCES villageois (id_villageois) ON DELETE CASCADE,
    FOREIGN KEY (id_couleur)    REFERENCES couleur (id_couleur)
) ENGINE=InnoDB;

-- MEUBLER : meubles présents dans la maison d'un villageois (Furniture List)
CREATE TABLE meubler (
    id_villageois  INT  NOT NULL,
    id_meuble      INT  NOT NULL,
    quantite       INT  NOT NULL DEFAULT 1,
    PRIMARY KEY (id_villageois, id_meuble),
    CHECK (quantite >= 1),
    FOREIGN KEY (id_villageois) REFERENCES villageois (id_villageois) ON DELETE CASCADE,
    FOREIGN KEY (id_meuble)     REFERENCES meuble (id_meuble) ON DELETE CASCADE
) ENGINE=InnoDB;

-- COLORER : couleurs d'une variante de meuble (Color 1, Color 2)
CREATE TABLE colorer (
    id_variante  INT  NOT NULL,
    id_couleur   INT  NOT NULL,
    PRIMARY KEY (id_variante, id_couleur),
    FOREIGN KEY (id_variante) REFERENCES variante (id_variante) ON DELETE CASCADE,
    FOREIGN KEY (id_couleur)  REFERENCES couleur (id_couleur)
) ENGINE=InnoDB;

-- PROVENIR : où obtenir un meuble (colonne Source, valeurs séparées par « ; »)
CREATE TABLE provenir (
    id_meuble  INT  NOT NULL,
    id_source  INT  NOT NULL,
    PRIMARY KEY (id_meuble, id_source),
    FOREIGN KEY (id_meuble) REFERENCES meuble (id_meuble) ON DELETE CASCADE,
    FOREIGN KEY (id_source) REFERENCES source (id_source)
) ENGINE=InnoDB;

-- PROPOSER : saisons/événements pendant lesquels un meuble est disponible
CREATE TABLE proposer (
    id_meuble     INT  NOT NULL,
    id_evenement  INT  NOT NULL,
    PRIMARY KEY (id_meuble, id_evenement),
    FOREIGN KEY (id_meuble)    REFERENCES meuble (id_meuble) ON DELETE CASCADE,
    FOREIGN KEY (id_evenement) REFERENCES evenement (id_evenement)
) ENGINE=InnoDB;
