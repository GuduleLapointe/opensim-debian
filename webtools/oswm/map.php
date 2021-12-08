

<?php 
include 'variables.php';
	echo '<HR>';
	$ligne1 = '<B>Map des regions pour le serveur.</B>';
	$ligne2 = '*** <u>Moteur OpenSim selectionne: </u>'.INI_Conf_Moteur($_SESSION['opensim_select'],"name").' - '.INI_Conf_Moteur($_SESSION['opensim_select'],"version").' ***';
	echo '<div class="block" id="clean-gray"><button><CENTER>'.$ligne1.'<br>'.$ligne2.'</CENTER></button></div>';
	echo '<hr>';

//*******************************************************
 //Initialisation des variables ET du tableau
 //*******************************************************
for($x=-30;$x < 30;$x++) 		// Limite de 50x50
{
	//echo "<hr>X:".$x.'<hr>';
	for($y=-30;$y < 30;$y++) 			// Limite de 50x50
	{	//	echo "Y:".$y.'<br />';
		$Matrice[$x][$y]['name']="";	
		$Matrice[$x][$y]['img']="";
		$Matrice[$x][$y]['ip']="";
		$Matrice[$x][$y]['port']="";	
		$Matrice[$x][$y]['uuid']="";
	} 
} 
//*******************************************************

	
//*******************************************************	
// Lecture des regions.ini et enregistrement dans Matrice
//*******************************************************
// Parcours des serveur installes
	// *** Lecture Fichier Regions.ini ***
	$filename2 = "moteurs.ini";	

	if (file_exists($filename2)) 
		{//echo "Le fichier $filename2 existe.<br>";
		$filename = $filename2 ;
		}else {echo "Le fichier $filename2 n'existe pas.<br>";
		}
	$tableauIni0 = parse_ini_file($filename, true);
	if($tableauIni0 == FALSE){echo 'prb lecture ini '.$filename.'<br>';}
	
	while (list($key, $val) = each($tableauIni0))
	{
	$tableauIni = parse_ini_file(INI_Conf_Moteur($key,"address")."Regions/Regions.ini", true);
	if($tableauIni == FALSE){echo 'prb lecture ini '.INI_Conf_Moteur($key,"address")."Regions/Regions.ini".'<br>';}
		echo '<hr>Serveur Name:'.INI_Conf_Moteur($key,"name").' - Version:'.INI_Conf_Moteur($key,"version").'<br>';

		//*******************************************************
		// Pour chaque serveur
		while (list($keyi, $vali) = each($tableauIni))
		{
			// **** Recuperation du port http du serveur ******		
			$filename = INI_Conf_Moteur($key,"address")."OpenSimDefaults.ini";		
			if (!$fp = fopen($filename,"r")){echo "Echec de l'ouverture du fichier ".$filename;}		
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
			//****************************************************	
		
			//*******************************************************
			// Recuperation des valeurs ET enregistrement des valeurs dans le tableau
			//echo $key.$tableauIni[$key]['RegionUUID'].$tableauIni[$key]['Location'].$tableauIni[$key]['InternalPort'].'<br>';
			$location = explode(",", $tableauIni[$keyi]['Location']);
			 $coordX = $location[0]-7000;
			 $coordY = $location[1]-7000;		
			 $Matrice[$coordX][$coordY]['name']=$keyi;	
			 $ImgMap = "http://".$tableauIni[$keyi]['ExternalHostName'].":".trim($srvOS)."/index.php?method=regionImage".str_replace("-","",$tableauIni[$keyi]['RegionUUID']);
			 $Matrice[$coordX][$coordY]['img'] = $ImgMap ;
			 $Matrice[$coordX][$coordY]['ip']=$tableauIni[$keyi]['ExternalHostName'];
			 $Matrice[$coordX][$coordY]['port']=$tableauIni[$keyi]['InternalPort'];	
			 $Matrice[$coordX][$coordY]['uuid']=$key.$tableauIni[$keyi]['RegionUUID'];
		}
		//****************
	}

//*******************************************************	
// Map en construction *******************
//*******************************************************	
//echo $_POST['zooming'];
if (isset($_POST['zooming']))
{ $widthMap = $_POST['zooming']; $heightMap = $_POST['zooming'];}
else{$widthMap = "64";$heightMap= "64";  // Par default si pas zoom
}

echo '<hr><table border=0 align=center WIDTH=100%><tr align=center><td>
<FORM METHOD=POST ACTION="">
	<select name="zooming">
	  <option value="32" name="id">Zoom 1</option>
	  <option value="64" name="id">Zoom 2</option>
	  <option value="128" name="id">Zoom 3</option>
	  <option value="256" name="id">Zoom 4</option>
	</select><input type="submit" name="goto" value="Appliquer Zoom">
</form></td></tr></table>

<table border=0 align=center><tr align=center>';

for($y=30;$y > -30;$y--) 		// Limite Y
{
	echo '<tr>';
	for($x=-30;$x < 30;$x++) 			// Limite X
	{
		echo '<td>';
		//ECHO '<font-size: 6pt>'.$Matrice[$x][$y]['name'].'<BR>x:'.$x.' y:'.$y.'<br></font>';
		//echo $Matrice[$x][$y]['name'].'<br>';	
		//echo $Matrice[$x][$y]['img'];
		if(Test_Url($Matrice[$x][$y]['img']) <> '1'){echo '';}
			else{
			$textemap = $Matrice[$x][$y]['name'];
			echo '<img src="'.$Matrice[$x][$y]['img'].'" width="'.$widthMap.'" height="'.$heightMap.'" BORDER="0" alt="'.$textemap.'">';
			}
		// $Matrice[$x][$y]['ip'];
		// $Matrice[$x][$y]['port'];	
		// $Matrice[$x][$y]['uuid']
		echo "</td>";
	} 
	echo '</tr>';
} 
echo "</table>";
//*******************************************

function Test_Url($server)
{
// Temps avant expiration du test de connexion 
define('TIMEOUT', 10); 
 
	$tab = parse_url($server); 
	$tab['port'] = isset($tab['port']) ? $tab['port'] : 40; 
	if(false !== ($fp = fsockopen($tab['host'], $tab['port'], $errno, $errstr, TIMEOUT))) { 
		fclose($fp); 
		//echo 'Location: ' . $server; 
		return 1;
	} else { 
		//echo 'Erreur #' . $errno . ' : ' . $errstr; 
		return 0;
	} 
}
?>