<?php 
include 'variables.php';

if (session_is_registered("authentification"))
{ // v&eacute;rification sur la session authentification 
	echo '<HR>';
	$ligne1 = '<B>Gestion des Sims connectes.</B>';
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
// Selon ACTION bouton => CONSTRUCTION de la commande pour ENVOI sur la console via  SSH
//******************************************************
	if($_POST['cmd'])
	{
	// *** Affichage mode debug ***
	echo '#   '.$_POST['cmd'].'   #<br>';
	//echo $_POST['name_sim'];	echo '<BR><HR>';
		 
		if($_POST['cmd'] == 'Refresh'){ $commande = $cmd_etat_OS;}  
		if($_POST['cmd'] == 'Region Root'){ $commande = $cmd_region_root;}  
		if($_POST['cmd'] == 'Update Client'){ $commande = $cmd_forceupdate;} 
		if($_POST['cmd'] == 'Start'){ $commande = $cmd_start;}
		if($_POST['cmd'] == 'Stop'){ $commande = $cmd_stop;}
		if($_POST['cmd'] == 'Restart'){ $commande = $cmd_restart;}
		if($_POST['cmd'] == 'Alerte'){ $commande=$pre_cmd.'change region '.$_POST['name_sim'].';'.$pre_cmd.' alert general '.$_POST['msg_alert'];}
		if($_POST['cmd'] == 'Alerte General'){ $commande=$pre_cmd.'change region root;'.$pre_cmd.' alert general '.$_POST['msg_alert'];}		
	}	
//	echo $commande;
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
	//echo " ok: logged in...\n";
	//echo $commande ;
				if (!($stream = ssh2_exec($con, $commande ))) {
					echo " fail: unable to execute command\n";
				} else {
					// collect returning data from command
					stream_set_blocking($stream, true);	$data = "";
					while ($buf = fread($stream,4096)) 
					{ $data .= $buf."\n";}
					fclose($stream);
				}
			}
		}	
	}
	
//******************************************************
//  Actions en RETOUR de la console pour la commande SSH 
//******************************************************
	// *** Affichage mode debug ***
	//	echo $data.'<br>';
	//	echo '<br>Commande soumise.';
	// *** Affichage mode debug ***

	// Test le retour de la console 
	if($_POST['cmd'] == 'Refresh')
	{
		$tableau = explode("mono", $data);
		while (list($key, $val) = each($tableau))
		{echo $val.'<br>';}
		//echo 'N° Instance PID du serveur: ',$PID_Opensim = substr($data,0,$pos);
	}
//**************************************************************************
//**************************************************************************

//******************************************************
//  Affichage page principale
//******************************************************

// *** Lecture Fichier Regions.ini ***
	$filename2 = INI_Conf_Moteur($_SESSION['opensim_select'],"address")."Regions/Regions.ini";	// *** V 0.7.1 ***
	if (file_exists($filename2)) 
		{//echo "Le fichier $filename2 existe.<br>";
		$filename = $filename2 ;
		}else {//echo "Le fichier $filename2 n'existe pas.<br>";
		}
	$tableauIni = parse_ini_file($filename, true);
	if($tableauIni == FALSE){echo 'prb lecture ini '.$filename.'<br>';}
	
// *** Lecture Fichier OpenSimDefaults ***
		$filename2 = INI_Conf_Moteur($_SESSION['opensim_select'],"address")."OpenSimDefaults.ini";		//*** V 0.7.1
	if (file_exists($filename2)) 
		{//echo "Le fichier $filename2 existe.<br>";
		$filename = $filename2 ;
		}else {//echo "Le fichier $filename2 n'existe pas.<br>";
		}

	// **** Recuperation du port http du serveur ******		
		if (!$fp = fopen($filename,"r")) 
		{echo "Echec de l'ouverture du fichier ".$filename;}		
		$tabfich=file($filename); 
		for( $i = 1 ; $i < count($tabfich) ; $i++ )
		{
		$porthttp = strstr($tabfich[$i],"http_listener_port");
			if($porthttp)
			{
				$posEgal = strpos($porthttp,'=');
				$longueur = strlen($porthttp);
				$srvOS = substr($porthttp, $posEgal + 1);
			}
		}
		fclose($fp);
	//**********************************************************
	echo 'Nb regions connectes: '.count($tableauIni).'<HR>';
	echo '<table width="100%" BORDER=1><TR>';
		echo '<TD><br><center><FORM METHOD=POST ACTION=""><INPUT TYPE="submit" VALUE="Region Root" NAME="cmd" '.$btnN1.'><INPUT TYPE="hidden" VALUE="'.$key.'" NAME="name_sim"></FORM></center></TD>';
		echo '<TD><br><center><FORM METHOD=POST ACTION=""><INPUT TYPE="submit" VALUE="Refresh" NAME="cmd" '.$btnN1.'><INPUT TYPE="hidden" VALUE="'.$key.'" NAME="name_sim"></FORM></center></TD>';
		echo '<TD><br><center><FORM METHOD=POST ACTION=""><INPUT TYPE="submit" VALUE="Update Client" NAME="cmd" '.$btnN1.'><INPUT TYPE="hidden" VALUE="'.$key.'" NAME="name_sim"></FORM></center></TD>';
		echo '<TD><br><center><FORM METHOD=POST ACTION=""><INPUT TYPE="submit" VALUE="Restart" NAME="cmd" '.$btnN2.'><INPUT TYPE="hidden" VALUE="'.$key.'" NAME="name_sim"></FORM></center></TD>';
		echo '<TD><br><center><FORM METHOD=POST ACTION=""><INPUT TYPE="submit" VALUE="Start" NAME="cmd" '.$btnN3.'><INPUT TYPE="hidden" VALUE="'.$key.'" NAME="name_sim"></FORM></center></TD>';
		echo '<TD><br><center><FORM METHOD=POST ACTION=""><INPUT TYPE="submit" VALUE="Stop" NAME="cmd" '.$btnN3.'><INPUT TYPE="hidden" VALUE="'.$key.'" NAME="name_sim"></FORM></center></TD>';
	echo '</TR>';
	echo '<tr><td colspan=6 align=center><FORM METHOD=POST ACTION="">Message pour TOUTES les Sims<br><INPUT TYPE="text" NAME="msg_alert"  style="width:300px; height:25px;"><INPUT TYPE="submit" VALUE="Alerte General" NAME="cmd" '.$btnN2.'><INPUT TYPE="hidden" VALUE="'.$key.'" NAME="name_sim"></FORM></td></tr>';
	echo '</TABLE>';
	echo '<HR>';	

	echo '<center><table width="60%" BORDER=1>';
	while (list($key, $val) = each($tableauIni))
	{
		//****************** Lien vers la map ***************************
			$ImgMap = "http://".INI_Conf("Parametre_OSMW","hostnameSSH").":".trim($srvOS)."/index.php?method=regionImage".str_replace("-","",$tableauIni[$key]['RegionUUID']);
		//******************************************************
		if(Test_Url($ImgMap) <> '1'){$Couleur_Feux = $Couleur_Feux_R;}else{$Couleur_Feux = $Couleur_Feux_V;}
		echo '		<tr>
		<td align=center><img src="'.$ImgMap.'" width=90 height=90 BORDER=1></td>
		<td align=center><FORM METHOD=POST ACTION=""><center><b><u>'.$key.'</u></b>  - <a href="secondlife://hg.francogrid.org:80:'.$key.'">Se teleporter</a> -<br>Message pour la Sim.<br><INPUT TYPE="text" NAME="msg_alert" style="width:300px; height:25px;"><INPUT TYPE="submit" VALUE="Alerte" NAME="cmd" '.$btnN2.'><INPUT TYPE="hidden" VALUE="'.$key.'" NAME="name_sim"> </center></FORM></td>
		<td align=center><img src="'.$Couleur_Feux.'" width=50 height=80 BORDER=1></td>
		</tr>';
	}
	echo '</table></center><hr>';

//******************************************************				
}else{header('Location: index.php');   }


function Test_Url($server)
{
// Temps avant expiration du test de connexion 
define('TIMEOUT', 30); 
 
	$tab = parse_url($server); 
	$tab['port'] = isset($tab['port']) ? $tab['port'] : 80; 
	if(false !== ($fp = fsockopen($tab['host'], $tab['port'], $errno, $errstr, TIMEOUT))) { 
		fclose($fp); 
		//echo 'Location: ' . $server; 
		return 1;
	} else { 
		echo 'Erreur #' . $errno . ' : ' . $errstr; 
		return 0;
	} 
}
?>