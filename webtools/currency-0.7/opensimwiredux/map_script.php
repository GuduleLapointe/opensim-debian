<?php
/////////////////////////////////////////////////////////////////////////////////
//
// Modified From OpenSim WebInterface Redux v0.28 
//													by Fumi.Iseki
//
//
// $size, $centerX, $centerY, $world_map_url, ENV_HELPER_PATH are needed
//

//
require_once(ENV_HELPER_PATH.'/../include/opensim.mysql.php');



$display_marker = 'dr';	// infomation marker


if ($size==16){
	$minuszoom = 0;   $pluszoom = 32;  $infosize = 8;
}
else if ($size==32){
	$minuszoom = 16;  $pluszoom = 64;  $infosize = 10;
}
else if ($size==64){
	$minuszoom = 32;  $pluszoom = 128; $infosize = 12;
}
else if ($size==128) {
	$minuszoom = 64;  $pluszoom = 256; $infosize = 20;
}
else if ($size==256) {
	$minuszoom = 128; $pluszoom = 512; $infosize = 40;
}
else if ($size==512) {
	$minuszoom = 256; $pluszoom = 0;   $infosize = 60;
}

?>

function loadmap() {
	mapInstance = new ZoomSize(<?php echo $size?>);
	mapInstance = new WORLDMap(document.getElementById('map-container'), {hasZoomControls: false, hasPanningControls: true});
	mapInstance.centerAndZoomAtWORLDCoord(new XYPoint(<?php echo $centerX?>, <?php echo $centerY?>), 1);
<?php
	$DbLink = & opensim_new_db();
	$DbLink->query('SELECT uuid,regionName,serverIP,serverURI,locX,locY,serverHttpPort FROM regions ORDER BY locX');

	while($DbLink->Errno==0 and list($uuid, $regionName, $serverIP, $serverURI, $locX, $locY, $serverHttpPort)=$DbLink->next_record())
	{
		$name = opensim_get_estate_owner($uuid);
		$firstN = $name['firstname'];
		$lastN  = $name['lastname'];

		$dx = 0.00; $dy = 0.00;
		if ($display_marker=='tl') {
			$dx = -0.40; 	$dy = 0.40;
		}
		else if ($display_marker=='tr') {
			$dx = 0.40; 	$dy = 0.40;
		}
		else if ($display_marker=='dl') {
			$dx = -0.40; 	$dy = -0.40;
		}
		else if ($display_marker=='dr') {
			$dx = 0.40; 	$dy = -0.40;
		}

		$locX = $locX/256;
		$locY = $locY/256;
		$MarkerCoordX = $locX + $dx;
		$MarkerCoordY = $locY + $dy;

		$server = '';
		if ($serverURI!='') {
    		$dec = explode(':', $serverURI);
    		if (!strncasecmp($dec[0], 'http', 4)) $server = $dec[0].':'.$dec[1];
		}   
		if ($server=='') {
    		$server = 'http://'.$serverIP;
		}
		$server = $server.':'.$serverHttpPort;

		$uuid = str_replace('-', '', $uuid);
	  	$imageURL = $server.'/index.php?method=regionImage'.$uuid;
		$windowTitle = 'Region Name: '.$regionName.'<br /><br />Coordinates: '.$locX.','.$locY.'<br /><br />Owner: '.$firstN.' '.$lastN;
?>
	  	var tmp_region_image = new Img("<?php echo $imageURL?>", <?php echo $size?>, <?php echo $size?>);
		var region_loc = new Icon(tmp_region_image);
		var all_images = [region_loc, region_loc, region_loc, region_loc, region_loc, region_loc];
		var marker = new Marker(all_images, new XYPoint(<?php echo $locX?>, <?php echo $locY?>));
		mapInstance.addMarker(marker);
	
		var map_marker_img = new Img("images/info.gif", <?php echo $infosize?>, <?php echo $infosize?>);
		var map_marker_icon = new Icon(map_marker_img);
		var mapWindow = new MapWindow("<?php echo $windowTitle?>", {closeOnMove: true});
		var all_images = [map_marker_icon, map_marker_icon, map_marker_icon, map_marker_icon, map_marker_icon, map_marker_icon];
		var marker = new Marker(all_images, new XYPoint(<?php echo $MarkerCoordX?>, <?php echo $MarkerCoordY?>));
		mapInstance.addMarker(marker, mapWindow);
<?php
	}
	$DbLink->close();
?>
}


function setZoom(size) {
	var cord = mapInstance.getMapCenter();
	window.location.href = "<?php echo $world_map_url?>?size="+size+"&ctX="+cord.x+"&ctY="+cord.y+"";
}


