@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
Configuration requise pour OSWebManager:
	-- SSH
	-- Mono
	-- Apache
	-- Mysql
	-- PHPMyAdmin
	-- Lib php SSH2
Fonctionnement:
	-- OSMW envoi des commandes au simulateur via le fichier "Screensend" dans un screen Unix
	-- Le simulateur est lancé par le fichier batch "RunOpensim.sh" qui contient le nom du screen renseigné dans le fichier config.ini
	-- Ces fichiers doivent avoir les droits 777
	-- Les fichiers doivent etre installés dans chaque simulateurs (moteurs)
	-- ATTENTION aux droits d'accés aux fichiers 
		--> Régions.ini (droits écriture) et OpensimDefaults.ini qui doivent etre accessible
Gestion des Utilisateurs:
	=> 3 Niveaux d'accés sont autorisés
	-- Administrateurs 
	-- Gestionnaires de sauvegardes
	-- Invités (consultation)
	-- 1 seul compte root
@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

******************************************
********* Suivi de versions **********
******************************************
*** V 1.1 ***
-- Refonte complete de l'interface
-- Système d'instalation simplifié
-- Gestion des moteurs OpenSim, des utilisateurs et de la config en .INI
-- ...

*** V 1.0 ***
-- Ajout de la gestion multi-Utilisateurs dans OSMW

*** V0.9.11 ***
-- Authentification multi-users via fichier texte  (pas encore intégrer à OSMW)

*** V0.7.11 ***
-- Ajouts de Fonctionnaltées;
	-- Cartographie ajouté
	-- TOUS demarrer et arreter d'une seule fois
	-- Une serie de tests pour voir si tous fonctionne bien
	-- Ce fichier LOL
-- Optimisations du code


*** V0.6.11 ***
-- Premiere version de OSWebManager