<?php 
include 'variables.php';

if (session_is_registered("authentification")){ // v&eacute;rification sur la session authentification 
	echo '<HR>';
	$ligne1 = '<B>Gestion des Terrains connectes.</B>';
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
	

//******************************************************
// CONSTRUCTION de la commande pour ENVOI sur la console via  SSH
//******************************************************
	if($_POST['cmd'])
	{
	// *** Affichage mode debug ***
	//echo $_POST['cmd'];
	/*		echo $_POST['name_sim'];	echo '<BR><HR>';
	*/	
		//*********************************
		// === Commande BACKUP ===
		//*********************************
		if($_POST['cmd'] == 'Backup Terrain'){$commande=$pre_cmd.'change region '.$_POST['name_sim'].';'.$pre_cmd.' terrain save BackupMap_'.$_POST['name_sim'].'.raw';
		echo '<center><b> ==>> Fichier en cours de creation  ou cree  !!  Consultez le log !! <<== </b></center><BR>';
		}
		//*********************************
		// === Commande ELEVATE ===
		//*********************************
		if($_POST['cmd'] == 'Modifier Hauteur du Terrain')
		{
		echo $_POST['hauteur'];
			$commande = $commande=$pre_cmd.'change region '.$_POST['name_sim'].';'.$pre_cmd.' terrain elevate '.$_POST['hauteur'];
		}

//**************************************************************************
// Envoi de la commande par ssh  *******************************************
//**************************************************************************
	if($commande <> '')
	{	
		if (!function_exists("ssh2_connect")) die(" function ssh2_connect doesn't exist");
		// log in at server1.example.com on port 22
		if(!($con = ssh2_connect(INI_Conf("Parametre_OSMW","hostnameSSH"), 22))){
			echo " fail: unable to establish connection\n";
		} else 
		{// try to authenticate with username root, password secretpassword
			if(!ssh2_auth_password($con, INI_Conf("Parametre_OSMW","usernameSSH"), INI_Conf("Parametre_OSMW","passwordSSH") )) {
				echo "fail: unable to authenticate\n";
			} else {
				// allright, we're in!
	//echo " ok: logged in...\n";
				// execute a command
				if (!($stream = ssh2_exec($con, $commande ))) {
					echo " fail: unable to execute command\n";
				} else {
					// collect returning data from command
					stream_set_blocking($stream, true);
					$data = "";
					while ($buf = fread($stream,4096)) 
					{
						$data .= $buf."\n";
					}
					fclose($stream);
				}
			}
		}
	}
//******************************************************
//  Actions en RETOUR de la console pour la commande SSH 
//******************************************************
	// *** Affichage mode debug ***
	//	echo $data;
	//	echo '<br>Commande soumise.';
	// *** Affichage mode debug ***
	

//**************************************************************************
//**************************************************************************
}
//******************************************************
//  Affichage page principale
//******************************************************

// *** Lecture Fichier Region.ini ***
//	$filename1 = INI_Conf_Moteur($_SESSION['opensim_select'],"address")."Regions/Region.ini";	// *** V 0.6.9 ***
	$filename2 = INI_Conf_Moteur($_SESSION['opensim_select'],"address")."Regions/Regions.ini";	// *** V 0.7.1 ***
	if (file_exists($filename1)) 
		{//echo "Le fichier $filename1 existe.<br>";
		$filename = $filename1 ;
		}else {//echo "Le fichier $filename1 n'existe pas.<br>";
		}
	if (file_exists($filename2)) 
		{//echo "Le fichier $filename2 existe.<br>";
		$filename = $filename2 ;
		}else {//echo "Le fichier $filename2 n'existe pas.<br>";
		}
	$tableauIni = parse_ini_file($filename, true);
	if($tableauIni == FALSE){echo 'prb lecture ini $filename<br>';}
	
	
// *** Lecture Fichier OpenSimDefaults.ini ***
//	$filename1 = INI_Conf_Moteur($_SESSION['opensim_select'],"address")."OpenSim.ini";				//*** V 0.6.9
	$filename2 = INI_Conf_Moteur($_SESSION['opensim_select'],"address")."OpenSimDefaults.ini";		//*** V 0.7.1
	if (file_exists($filename1)) 
		{//echo "Le fichier $filename1 existe.<br>";
		$filename = $filename1 ;
		}else {//echo "Le fichier $filename1 n'existe pas.<br>";
		}
	if (file_exists($filename2)) 
		{//echo "Le fichier $filename2 existe.<br>";
		$filename = $filename2 ;
		}else {//echo "Le fichier $filename2 n'existe pas.<br>";
		}

// **** Recuperation du port http du serveur ******		
if (!$fp = fopen($filename,"r")) 
	{echo "Echec de l'ouverture du fichier $filename";}		
	$tabfich=file($filename); 
	for( $i = 1 ; $i < count($tabfich) ; $i++ )
	{
	//echo $tabfich[$i]."</br>";
	$porthttp = strstr($tabfich[$i],"http_listener_port");
		if($porthttp)
		{
			$posEgal = strpos($porthttp,'=');
			$longueur = strlen($porthttp);
			$srvOS = substr($porthttp, $posEgal + 1);
		}
	}
	fclose($fp);
	$i=0;
//
	echo 'Nb regions connectes: '.count($tableauIni).'<HR>';
	
	echo '<center><table width="80%" BORDER=1>';
	while (list($key, $val) = each($tableauIni))
	{//echo '<tr><td>'.$key.'</td><td>'.$tableauIni[$key]['RegionUUID'].'</td><td>'.$tableauIni[$key]['InternalAddress'].'</td><td>'.$tableauIni[$key]['InternalPort'].'</td><td><img src="'.$ImgMap.'" width=30 height=30></td></tr>';
		//****************** Lien vers la map 2 choix ***************************
		//	$ImgMap = "http://87.98.161.41:9000/index.php?method=regionImage24d1809db4e94993aa2b878d6df32426";
		$ImgMap = "http://".INI_Conf("Parametre_OSMW","hostnameSSH").":".trim($srvOS)."/index.php?method=regionImage".str_replace("-","",$tableauIni[$key]['RegionUUID']);
		//******************************************************
		echo '<tr">
		<td align=center><center><b><u>'.$key.'</u></b></center><br><img src="'.$ImgMap.'" width=90 height=90 BORDER=1></td>';
		//<td align=left><center><b><u>'.$key.'</u></b></center><br> IP Serveur: '.$tableauIni[$key]['InternalAddress'].'<br> Port Serveur: 9000 <br> Port Sim: '.$tableauIni[$key]['InternalPort'].'<br></td>
		echo '<td align=center>
		<FORM METHOD=POST ACTION="">Sauvegarde au format RAW.<br><INPUT TYPE="submit" VALUE="Backup Terrain" NAME="cmd" '.$btnN2.'><INPUT TYPE="hidden" VALUE="'.$key.'" NAME="name_sim"></FORM>
		</td>
		<td align=center>
			<FORM METHOD=POST ACTION="">Hauteur de la map.(ex: -3 soit -3m, ex: 2 soit +2m)<br>
			<INPUT TYPE="text" NAME="hauteur">
			<INPUT TYPE="submit" VALUE="Modifier Hauteur du Terrain" NAME="cmd" '.$btnN3.'>
			<INPUT TYPE="hidden" VALUE="'.$key.'" NAME="name_sim">
			</FORM>
		</td>
		</tr>';
	}
	echo '</table></center><hr>';

//******************************************************	
}else{header('Location: index.php');   }
?>