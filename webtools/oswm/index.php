<html>
<head>
<style type="text/css">
* {font-size: 10pt;}
</style>
<link rel="stylesheet" href="menu_style.css" type="text/css" />
</head>
<body>
<?php
include 'fonctions.php';
include 'variables.php';

session_start(); // On relaye la session
	//******************************************************
	$btnN1 = "disabled"; $btnN2 = "disabled"; $btnN3 = "disabled";
	if( $_SESSION['privilege']==4){$btnN1="";$btnN2="";$btnN3="";}		//  Niv 4	Super Administrateur
	if( $_SESSION['privilege']==3){$btnN1="";$btnN2="";$btnN3="";}		//  Niv 3	Administrateur
	if( $_SESSION['privilege']==2){$btnN1="";$btnN2="";}				//	Niv 2	Gestionnaire (sauvegarde)
	if( $_SESSION['privilege']==1){$btnN1="";}							//	Niv 1	Utilisateurs
	//******************************************************

//****************************************************************************************	
//****************************************************************************************
if (isset($_POST['firstname']) && isset($_POST['lastname']) && isset($_POST['pass']))
{	
	$_SESSION['login'] = $_POST['firstname'].' '. $_POST['lastname']; // Son Login	
    $auth = false; // On part du principe que l'utilisateur n'est pas authentifié
	 $filename2 = "users.ini";	
	if (file_exists($filename2)) 
	{//echo "Le fichier $filename2 existe.<br>";
		$filename = $filename2 ;
	}else {
			echo "Le fichier $filename2 n'existe pas.<br> Creation effectue, actualiser la page !";
			Creation_UsersINI();
			Creation_ConfigINI();
			Creation_MoteursINI();
			exec_command("chmod 777 /var/www".INI_Conf("Parametre_OSMW","cheminAppli")."users.ini");
			exec_command("chmod 777 /var/www".INI_Conf("Parametre_OSMW","cheminAppli")."config.ini");
			exec_command("chmod 777 /var/www".INI_Conf("Parametre_OSMW","cheminAppli")."moteurs.ini");
	}
	$tableauIni = parse_ini_file($filename, true);
	if($tableauIni == FALSE){echo 'prb lecture ini '.$filename.'<br>';}
	while (list($key, $val) = each($tableauIni))
	{
		if ( ( $key == $_SESSION['login'] ) && ( $tableauIni[$key]['pass'] == $_POST['pass'] ) ) {$auth = true;   break;}
	}
    if ( ! $auth ) {echo 'Vous ne pouvez pas accéder à cette page'; exit;}
    else {
        echo 'Bienvenue sur la page administration du site';
		$_SESSION['privilege'] = $tableauIni[$key]['privilege'];
		 $filename3 = "moteurs.ini";	
		if (file_exists($filename3)){$tableauIni1 = parse_ini_file($filename3, true);}
		while (list($key1) = each($tableauIni1))
			{$_SESSION['opensim_select'] = $tableauIni1[$key1]['name']; break;}
		session_register("authentification");
    }
}

//****************************************************************************************
//****************************************************************************************
//**************************** PAGE EN ACCES SECURISE ************************************
//**************** DEBUT *****************************
if (session_is_registered("authentification")){ // vérification sur la session authentification 

	echo $MENU_LATTERALE;
	echo '<HR>';
	// ********** SI le moteur selectionné à changé
	if($_POST['OSSelect']){$_SESSION['opensim_select'] = trim($_POST['OSSelect']);}
	// ********** Page appelé pour le telechargement de fichier
	if($_GET['f']){include('GestDirectory.php');exit;}	 
	
	// REDIRECTION DES PAGES *************************************************************
	if($_POST['a'] or $_GET['a']){
		if($_POST['a']){$vers = $_POST['a'];}
		if($_GET['a']){$vers = $_GET['a'];}
			//**************************************************** V1.1 = Configuration par ini
			// 	 				index.php											// V1.1
			if($vers =="1"){include('GestSims.php');}								// V1.1
			if($vers =="2"){include('GestSaveRestore.php');}						// V1.1
			if($vers =="3"){include('GestTerrain.php');}							// V1.1
			if($vers =="4"){include('GestImportExport.php');}  						// V1.1
			if($vers =="5"){include('GestOpensim.php');}			// admin		// V1.1
			if($vers =="6"){include('GestRegion.php');}				// admin		// V1.1
			if($vers =="7"){include('GestLog.php');}								// V1.1
			if($vers =="8"){include('GestAdminServ.php');}			// admin		// V1.1
			if($vers =="9"){include('contact.php');}								// V1.1
			if($vers =="10"){include('GestDirectory.php');}							// V1.1
			if($vers =="11"){include('map.php');}									// V1.1
			if($vers =="12"){include('GestIdentite.php');}			// admin		// V1.1	
			if($vers =="13"){include('Aide.php');}  								// V1.1
			if($vers =="14"){include('Apropos.php');}  								// V1.1
			if($vers =="15"){include('GestUsers.php');}				// admin		// V1.1
			if($vers =="16"){include('GestAdmin.php');}				// super admin	// V1.1	
			if($vers =="17"){include('GestMoteur.php');}			// admin		// V1.1	
			if($vers =="18"){include('GestConfig.php');}			// admin		// V1.1	
			if($vers =="19"){include('GestXMLRPC.php');}			// admin		// V1.1	
			if($vers =="logout"){session_start();$_SESSION = array();session_destroy();session_unset();header('Location: index.php');  }	
	}
	else
		{
	//********************************************************************************************
	// ************************   ***************   ****  Affichage Principal ********************
	//********************************************************************************************
	// **************   Choix du moteur Opensim
	//********************************************************************************************
	$ligne1 = '<b>Bienvenue &quot;'.$_SESSION['login'].'&quot; dans votre espace s&eacute;curis&eacute;. Niv:'.$_SESSION['privilege'].'</b>';
	$ligne2 = '*** <u>Moteur OpenSim selectionne: </u>'.INI_Conf_Moteur($_SESSION['opensim_select'],"name").' - '.INI_Conf_Moteur($_SESSION['opensim_select'],"version").' ***';
	echo '<div class="block" id="clean-gray"><button><CENTER>'.$ligne1.'<br>'.$ligne2.'</CENTER></button></div>';
	echo '<hr>';
	
	// *** Lecture Fichier .ini ***
	$filename2 = "/var/www".INI_Conf("Parametre_OSMW","cheminAppli")."moteurs.ini";	
	if (file_exists($filename2)) 
		{//echo "Le fichier $filename2 existe.<br>";
		$filename = $filename2 ;
		}else {echo "Le fichier $filename2 n'existe pas.<br>";
		}
	$tableauIni = parse_ini_file($filename, true);
	if($tableauIni == FALSE){echo 'prb lecture ini '.$filename.'<br>';}
	//*************** Formulaire de choix du moteur a selectionné *****************
	echo '<CENTER><FORM METHOD=POST ACTION="">
			<select name="OSSelect"><option value="'.INI_Conf_Moteur($_SESSION['opensim_select'],"name").'">Selectionner le Moteur Opensim ?</option>';
			while (list($key) = each($tableauIni))
				{echo '<option value="'.$key.'">'.INI_Conf_Moteur($key,"name").' - '.INI_Conf_Moteur($key,"version").'</option>';}
			echo'</select>
		<INPUT TYPE="submit" VALUE="Choisir" >
	</FORM></CENTER>';
	//********************************************************************************************
	//  ********** MENU  ***************
	//********************************************************************************************
	echo '<HR>';
	echo '<center>';
	echo '<div class="block" id="clean-gray"><button>Section Utilisateur</button></div><br>';
	echo '<div class="block" id="pale-blue"><a href="?a=1"><button>Gestion des Sims.</button></a></div>';		
	echo '<div class="block" id="pale-blue"><a href="?a=2"><button>Gestion des Sauvegardes.</button></a></div>';		
	echo '<div class="block" id="pale-blue"><a href="?a=3"><button>Gestion des Terrains.</button></a></div>';		
	echo '<div class="block" id="pale-blue"><a href="?a=10"><button>Gestion des fichiers de sauvegardes.</button></a></div>';		
	echo '<div class="block" id="pale-blue"><a href="?a=4"><button>Exporter son inventaire.</button></a></div>';		
	echo '<div class="block" id="pale-blue"><a href="?a=7"><button>Gestion du Log.</button></a></div>';	
	echo '<div class="block" id="pale-blue"><a href="?a=11"><button>Cartographie des Regions.</button></a></div>';
	echo '<div class="block" id="pale-blue"><a href="?a=9"><button>Contacter l\'assistance.</button></a></div>';		
	echo '<div class="block" id="pale-blue"><a href="?a=13"><button>Aide.</button></a></div>';	
	echo '<div class="block" id="pale-blue"><a href="?a=14"><button>A Propos.</button></a></div>';	
	echo '<hr>';
	//*********** Menu administrateur *************
	if( $_SESSION['privilege']>=3){
		echo '<div class="block" id="clean-gray"><button>Section Administrateur</button></div><br>';
		//echo '<center><u>Section Administrateur</u></center>';
		echo '<div class="block" id="pale-blue"><a href="?a=6"><button>Configuration des Regions (Regions.ini).*</button></a></div>';	
		echo '<div class="block" id="pale-blue"><a href="?a=5"><button>Configuration Opensim (OpenSimDefaults.ini).*</button></a></div>';	
		echo '<div class="block" id="pale-blue"><a href="?a=16"><button>Visualiser la configuration Serveur.(config.ini)**</button></a></div>';
		echo '<div class="block" id="pale-blue"><a href="?a=18"><button>Configuration de OpenSim Manager Web.(config.ini)*</button></a></div>';
		echo '<div class="block" id="pale-blue"><a href="?a=17"><button>Configuration des Moteurs.(moteurs.ini)*</button></a></div>';
		echo '<div class="block" id="pale-blue"><a href="?a=8"><button>Administration du Serveur.*</button></a></div>';
		echo '<div class="block" id="pale-blue"><a href="?a=12"><button>Parametres du Serveur.*</button></a></div>';
		echo '<div class="block" id="pale-blue"><a href="?a=15"><button>Gestion des Utilisateurs.*</button></a></div>';
	//	echo '<div class="block" id="pale-blue"><a href="?a=19"><button>Gestion XMLRPC.*</button></a></div>';
		echo '<HR>';
	}
	echo '<div class="block" id="pale-blue"><a href="?a=logout"><button>D&eacute;connexion.</button></a></div>';	
	echo '</center>';
	echo '* administrateur / ** root';
	}
// FIN REDIRECTION DES PAGES *************************************************************
}
// FIN *****************************
else{
?>			

<span>
	<CENTER><div class="block" id="fat-blue"><button>OpenSim Manager Web</button></div></CENTER>
<form action="" method="post" name="connect">
	  <p align="center" ><strong>      
		  <?php if(isset($_GET['erreur']) && ($_GET['erreur'] == "login")) { // Affiche l'erreur  ?>
		  <span class="Style5">Echec d'authentification !!! &gt; login ou mot de passe incorrect</span>    <?php } ?>
		  <?php if(isset($_GET['erreur']) && ($_GET['erreur'] == "delog")) { // Affiche l'erreur ?>
		  <span class="Style2">D&eacute;connexion r&eacute;ussie... A bient&ocirc;t !</span>    <?php } ?>
		  <?php if(isset($_GET['erreur']) && ($_GET['erreur'] == "intru")) { // Affiche l'erreur ?>
		  <span class="Style5">Echec d'authentification !!! &gt; Aucune session n'est ouverte</span>
		  <span class="Style5">ou vous n'avez pas les droits pour afficher cette page </span>
		  <?php } ?></strong></p>
	<CENTER><table  border="0" cellpadding="10" cellspacing="0"  border="1" >
		<tr><td colspan="2" align="center">
			<img src="logoserver.png"  BORDER=1>
			<br><br><div class="block" id="pale-blue"><button>Authentification</button></div>
		</td></tr>
		<tr><td>&nbsp; Firstname </td><td><input name="firstname" type="text" id="firstname"></td></tr>
		<tr><td>&nbsp; Lastname </td><td><input name="lastname" type="text" id="lastname"></td></tr>
		<tr><td>&nbsp; MOT DE PASSE </td><td><input name="pass" type="password" id="pass"></td></tr>
		<tr><td height="34" colspan="2"><CENTER><input type="submit" name="Submit" value="Se connecter"></CENTER></td></tr>
	</table><CENTER>
</FORM>
</span>
<?}
echo $PIED_DE_PAGE;
?>
</body>
</html>