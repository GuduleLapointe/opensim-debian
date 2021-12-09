<?php 
include 'variables.php';

if (session_is_registered("authentification") && $_SESSION['privilege']>=3){ // v&eacute;rification sur la session authentification 
	echo '<HR>';
	$ligne1 = '<B>Gestion Identit&eacute du Serveur.</B>';
	$ligne2 = '*** <u>Moteur OpenSim selectionne: </u>'.INI_Conf_Moteur($_SESSION['opensim_select'],"name").' - '.INI_Conf_Moteur($_SESSION['opensim_select'],"version").' ***';
	echo '<div class="block" id="clean-gray"><button><CENTER>'.$ligne1.'<br>'.$ligne2.'</CENTER></button></div>';
	echo '<hr>';
	//******************************************************
	$btnN1 = "disabled"; $btnN2 = "disabled"; $btnN3 = "disabled";
	if( $_SESSION['privilege']==4){$btnN1="";$btnN2="";$btnN3="";}		//  Niv 4	
	if( $_SESSION['privilege']==3){$btnN1="";$btnN2="";$btnN3="";}		//  Niv 3
	if( $_SESSION['privilege']==2){$btnN1="";$btnN2="";}				//	Niv 2
	if( $_SESSION['privilege']==1){$btnN1="";}							//	Niv 1
	//******************************************************
	
	$ipdest = "http://fgagod.net/OSMWList/transact.php";		// Page transaction
	$imglogo = 'http://'.INI_Conf("Parametre_OSMW","hostnameSSH").INI_Conf("Parametre_OSMW","cheminAppli").'logoserver.png';		// Lien logo serveur
	
	
//******************************************************
// CONTROLEUR
//******************************************************
	if($_POST['cmd'])
	{
	// *** Affichage mode debug ***
	//echo $_POST['cmd'].'<br>';
		
		//******************************************************
		if($_POST['cmd'] == 'ENREGISTRER MODIFICATION')
		{ 
		 	 $cmdUrl = $ipdest.'?'.'osmw=MOD'
			.'&srvip='.INI_Conf("Parametre_OSMW","hostnameSSH")
			.'&LibNom='.rawurlencode ($_POST['LibNom'])  
			.'&LibDesc='.rawurlencode ($_POST['LibDesc']) 
			.'&LibWeb='.$_POST['LibWeb'] 
			.'&LibPhoto='.$imglogo
			.'&Liblst='.$_POST['EnregListing'] ;

			 $content2 = file_get_contents( $cmdUrl);
		}//******************************************************
		
		//******************************************************
		if($_POST['cmd'] == 'Enregistrer') // Enregistre IP du serveur sur fgagod.net
		{
		 $cmdUrl = $ipdest.'?'.'osmw=INS&srvip='.INI_Conf("Parametre_OSMW","hostnameSSH").'&img='.$imglogo;
		 $content2 = file_get_contents( $cmdUrl);
		}//******************************************************
		
		//"Visuliser la liste"
		//******************************************************
		if($_POST['cmd'] == 'VISUALISER LES REGIONS') // 
		{		
			// Vidage des regions
			$cmdUrl = $ipdest.'?'.'osmw=REGV&srvip='.INI_Conf("Parametre_OSMW","hostnameSSH");
			$content2 = file_get_contents( $cmdUrl);
			
			// Ajout de chaque region	
			$filename = "moteurs.ini";	
			if (file_exists($filename)) 
			{//echo "Le fichier $filename1 existe.<br>";
			}else {echo "Le fichier $filename n'existe pas.<br>";}
			$tableauIni0 = parse_ini_file($filename, true);
			if($tableauIni0 == FALSE){echo 'prb lecture ini $filename<br>';}			
			// echo 'Nombre de serveurs : '.count($server).'<br>';
			while (list($key, $val) = each($tableauIni0))
			{
				//echo '<hr>Serveur Name:'.$server[$key]['name'].'<br>'; 
				// *** Lecture Fichier OpenSimDefaults.ini *** 
				$filename2 = INI_Conf_Moteur($key,"address")."OpenSimDefaults.ini";		
				if (file_exists($filename2)) {$filename = $filename2 ;	}

				// **** Recuperation du port http du serveur ******		
				if (!$fp = fopen($filename,"r")) 
				{echo "Echec de l'ouverture du fichier ".$filename;}		
				$tabfich=file($filename); 
				for( $i = 1 ; $i < count($tabfich) ; $i++ )
				{
					$porthttp = strstr($tabfich[$i],"http_listener_port");
					if($porthttp){$posEgal = strpos($porthttp,'=');	$longueur = strlen($porthttp);	$srvOS = substr($porthttp, $posEgal + 1);	}
				}
				fclose($fp);
				
				 // *** Lecture Fichier Regions.ini ***
				$filename2 = INI_Conf_Moteur($key,"address")."Regions/Regions.ini";	// *** V 0.7.1 ***
				if (file_exists($filename2)) 
					{$filename = $filename2 ;}
				$tableauIni = parse_ini_file($filename, true);
				if($tableauIni == FALSE){echo 'prb lecture ini '.$filename.'<br>';}
					while (list($keyA, $valA) = each($tableauIni))
					{
						$ImgMap = "http://".INI_Conf("Parametre_OSMW","hostnameSSH").":".trim($srvOS)."/index.php?method=regionImage".str_replace("-","",$tableauIni[$keyA]['RegionUUID']);
						$cmdUrl = $ipdest.'?'.'osmw=REGA&srvip='.INI_Conf("Parametre_OSMW","hostnameSSH").'&uuid='.$tableauIni[$keyA]['RegionUUID'].'&region='.$keyA.'&simul='.INI_Conf_Moteur($_SESSION['opensim_select'],"name").'&img='.$ImgMap;
						$content2 = file_get_contents( $cmdUrl);
					}
			}
		$cmdUrl = $ipdest.'?'.'osmw=READ&srvip='.INI_Conf("Parametre_OSMW","hostnameSSH");
		echo $content2 = file_get_contents( $cmdUrl);
		}//******************************************************
		
		//  Envoi du logo sur serveur 
		//******************************************************
		if($_POST['cmd'] == 'Envoyer')
		{
		//*** Chemin de destination du fichier envoyé ***

		$chemin_destination = "/var/www".INI_Conf("Parametre_OSMW","cheminAppli")."logoserver.png";
		//*********************************************
			if(!empty($_FILES['nom_du_fichier']['tmp_name']) AND is_uploaded_file($_FILES['nom_du_fichier']['tmp_name']))
			{
				//On va vérifier la taille du fichier en ne passant pas par $_FILES['nom_du_fichier']['size'] pour éviter les failles de sécurité
				if(filesize($_FILES['nom_du_fichier']['tmp_name']))
				{
				//On vérifie maintenant le type de l'image à l'aide de la fonction getimagesize()
				list($largeur, $hauteur, $type, $attr)=getimagesize($_FILES['nom_du_fichier']['tmp_name']);
					//Copie le fichier dans le répertoire de destination
					if(move_uploaded_file($_FILES['nom_du_fichier']['tmp_name'], $chemin_destination))
					{		echo 'Ok, fichier envoyé correctement';	
					}
					else
					{		echo 'Erreur lors de la copie du fichier';			}
				}
			}
		}
		//******************************************************
	}	
	
//******************************************************
//  Affichage page principale
//******************************************************

	$cmdUrl = $ipdest."?osmw=ID&srvip=".INI_Conf("Parametre_OSMW","hostnameSSH")."&cmd=";
	$content1 = file_get_contents( $cmdUrl.'1');
	
	if ($content1 == "0") // Si IP non trouvé
	{
		 $content1="IP introuvable, Enregistrer votre serveur !";
		echo $content1.'<center><br><FORM METHOD=POST ACTION=""><INPUT TYPE="submit" VALUE="Enregistrer" NAME="cmd" '.$btnN3.'></FORM></center>';
	}		
	else				// IP Trové extraction des infos
	{
		$content2 = file_get_contents( $cmdUrl.'2');
		if ($content2 == "0") {$content2="Libelle de votre serveur.";}
		$content3 = file_get_contents( $cmdUrl.'3');
		if ($content3 == "0") {$content3="Description de votre serveur.";}
		$content4 = file_get_contents( $cmdUrl.'4');
		if ($content4 == "0") {$content4="Url Web NOK";}
		$content6 = file_get_contents( $cmdUrl.'6');
		if ($content6 == "0") {$content6=" ";}else{$content6="checked";}

		echo '<center>
	<table>
	<tr>
		<td>
		<table  border=0>
			<tr><td><center><img src="'.$imglogo.'"  BORDER=1></center></td></tr>
			<tr><td>
			<FORM METHOD=POST ACTION="" enctype="multipart/form-data">Format PNG<br>
				<input type="hidden" name="MAX_FILE_SIZE" value="2097152">    
				<input type="file" name="nom_du_fichier">   
				<input type="submit" value="Envoyer" NAME="cmd" '.$btnN3.'> 
			</FORM>	
		</td>
			</tr>
		</table>
		</td>
		<td>
		<table><FORM METHOD=POST ACTION="">
			<tr>
				<td><u>Titre du Serveur:</u></td>
				<td><b>'.$content2.'</b></td>
			</tr>
			<tr>
				<td><u>IP Serveur:</u></td>
				<td><b>'.$content1.'</b></td>
			</tr>
			<tr>
				<td><u>Titre:</u> </td>
				<td><INPUT TYPE="text" VALUE="'.$content2.'" NAME="LibNom"></td>
			</tr>
			<tr>
				<td><u>Description:</u></td>
				<td><textarea cols="50" rows="15" name="LibDesc" '.$btnN3.' >'.$content3.'</textarea></td>
			</tr>
			<tr>
				<td><u>Lien Web:</u></td>
				<td><INPUT TYPE="text" VALUE="'.$content4.'" NAME="LibWeb"><b> <a href="'.$content4.'" target=_blank>'.$content4.'</a></b></td>
			</tr>
			<tr><td align=right><INPUT TYPE="checkbox" NAME="EnregListing" '.$content6.'></td><td> Enregistrer le serveur au Listing de Francogrid. (recommand&eacute;)</td></tr>
			<tr><td colspan=2><INPUT TYPE="submit" VALUE="ENREGISTRER MODIFICATION" NAME="cmd" '.$btnN3.'></td></tr>
		</table>
		</td>
	</tr>
	</table>
</FORM><hr>
<table>
<tr>
	<td><FORM METHOD=POST ACTION=""><INPUT TYPE="submit" VALUE="VISUALISER LES REGIONS" NAME="cmd" '.$btnN3.'></FORM></td>
</tr>
</table>
</center>';		
	}

//******************************************************	
}else{header('Location: index.php');   }
?>