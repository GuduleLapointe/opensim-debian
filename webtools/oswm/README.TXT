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
	-- Le simulateur est lanc� par le fichier batch "RunOpensim.sh" qui contient le nom du screen renseign� dans le fichier config.ini
	-- Ces fichiers doivent avoir les droits 777
	-- Les fichiers doivent etre install�s dans chaque simulateurs (moteurs)
	-- ATTENTION aux droits d'acc�s aux fichiers 
		--> R�gions.ini (droits �criture) et OpensimDefaults.ini qui doivent etre accessible
Gestion des Utilisateurs:
	=> 3 Niveaux d'acc�s sont autoris�s
	-- Administrateurs 
	-- Gestionnaires de sauvegardes
	-- Invit�s (consultation)
	-- 1 seul compte root
@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

******************************************
********* Suivi de versions **********
******************************************
*** V 1.1 ***
-- Refonte complete de l'interface
-- Syst�me d'instalation simplifi�
-- Gestion des moteurs OpenSim, des utilisateurs et de la config en .INI
-- ...

*** V 1.0 ***
-- Ajout de la gestion multi-Utilisateurs dans OSMW

*** V0.9.11 ***
-- Authentification multi-users via fichier texte  (pas encore int�grer � OSMW)

*** V0.7.11 ***
-- Ajouts de Fonctionnalt�es;
	-- Cartographie ajout�
	-- TOUS demarrer et arreter d'une seule fois
	-- Une serie de tests pour voir si tous fonctionne bien
	-- Ce fichier LOL
-- Optimisations du code


*** V0.6.11 ***
-- Premiere version de OSWebManager