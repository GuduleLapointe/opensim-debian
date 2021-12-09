<?php 
if (session_is_registered("authentification"))
{ // v&eacute;rification sur la session authentification 
	echo '<HR>';
	$ligne1 = '<B>A Propos de OpenSim Manager Web.</B>';
	$ligne2 = '*** <u>Moteur OpenSim selectionne: </u>'.INI_Conf_Moteur($_SESSION['opensim_select'],"name").' - '.INI_Conf_Moteur($_SESSION['opensim_select'],"version").' ***';
	echo '<div class="block" id="clean-gray"><button><CENTER>'.$ligne1.'<br>'.$ligne2.'</CENTER></button></div>';
	echo '<hr>';
	

	echo '	<center><u>Projet développé par <b>Nino85 Whitman</b></u><br>
			L\'objectif de ce projet est de permettre à la communauté et aux membres de la grille Francogrid<br>
			Une meilleure utilisation et gestion des simulateurs Opensim installés sur un serveur Linux.<br>
			<br><u>Contributeurs:</u><br><br>
				Gill Beaumont<br>
				Ryanna Ariantho<br>
			
			<br>Pour toutes informations, contacter moi ! <b><a href="mailto:contact@fgagod.net">contact@fgagod.net</a></b><br>
			<hr>
<center><br><u><b>Pour aider au developpement de OpenSim Manager Web</b></u><br><br>
<a href="paypal_don.html" TARGET="_blank"><img src="https://www.paypalobjects.com/fr_FR/FR/i/btn/btn_donateCC_LG.gif"></a>
</center>

			';
	
	echo '<HR></center>';
	//echo phpinfo();
//******************************************************				
}else{header('Location: index.php');   }
?>