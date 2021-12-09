<?
ini_set("auto_prepend_file", "phpinclude/prepend.php");
ini_set("display_errors", "1");
require_once("phpinclude/prepend.php");

$db = GetDB("Grid");

$user = "00000000-0000-0000-0000-000000000000";
$scope_id = "00000000-0000-0000-0000-000000000000";

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
<head>
<title>Web Map</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"></meta>
<link rel="stylesheet" type="text/css" href="css/styles.css"></link>
<link rel="stylesheet" type="text/css" href="css/map.css"></link>
<link rel="stylesheet" type="text/css" href="css/jquery.tooltip.css"></link>
<link rel="stylesheet" type="text/css" href="css/jquery.contextMenu.css"></link>
<link rel="stylesheet" type="text/css" href="css/overcast/jquery-ui-1.7.3.custom.css"></link>
</head >
<body>
<script type="text/javascript" src="scripts/jquery-1.4.4.min.js">
</script>
<script type="text/javascript" src="scripts/jquery-ui-1.8.10.custom.min.js">
</script>
<script type="text/javascript" src="scripts/jquery.corner.js">
</script>
<script type="text/javascript" src="scripts/jquery.tools.min.js">
</script>
<script type="text/javascript" src="scripts/jquery.bgiframe.js">
</script>
<script type="text/javascript" src="scripts/jquery.delegate.js">
</script>
<script type="text/javascript" src="scripts/jquery.tooltip.js">
</script>
<script type="text/javascript" src="scripts/jQuery_mousewheel_plugin.js">
</script>
<script type="text/javascript" src="scripts/jquery.rightClick.js">
</script>
<script type="text/javascript" src="scripts/jquery.contextMenu.js">
</script>
<script type="text/javascript" src="scripts/jquery.map.js">
</script>
<script type="text/javascript">
$(document).ready(function() {

	$("#infodialog").dialog({
			closeOnEscape: true,
			modal: true,
			autoOpen: false,
			width: 400,
			draggable: false,
			title: "Region Details",
			buttons: {
					"Close": function() {
							$(this).dialog("close");
					}
			}
	});

	$("#searchbycoords").dialog({
			closeOnEscape: true,
			modal: true,
			autoOpen: false,
			width: 400,
			draggable: false,
			title: "Zoom to coordinates",
			buttons: {
					"Cancel": function() {
							$(this).dialog("close");
					},
					"OK": function() {
							$(this).dialog("close");
							var x = $("#xcoord").val() | 0;
							var y = $("#ycoord").val() | 0;

							$("#map1").gridmap("position", {x: x, y: y});
					}
			}
	});

	$("#searchbyname").dialog({
			closeOnEscape: true,
			modal: true,
			autoOpen: false,
			width: 400,
			draggable: false,
			title: "Zoom to region"
	});

	$("#map1").gridmap({
			tooltips: true,
			posx: 8000,
			posy: 8000,
			sizex: 20000,
			sizey: 20000,
			overlays: 0,
			showgrid: true,
			scopeid: "<?php echo $scope_id ?>",
			user: "<?php echo $user ?>",
			rclick: function(event, ui) {
				var canbuy = $(ui).children("input").val();
				if (canbuy == "no")
				{
					$(ui).contextMenu({});
					$(ui).showMenu(event,
							{menu: "infomenu"},
							function(action, el, pos) {
								var coords = $(el).attr("class");
								$("#infodialog").load("regioninfo.php?coords=" + coords + "&user=<?php echo $user ?>&scopeid=<?php echo $scope_id ?>", "", function() {
									$("#infodialog").dialog("open");
								});
							}
					);
				}
			}
	});

	$("#searchcoords").click(function() {
		$("#xcoord").val("8000");
		$("#ycoord").val("8000");
		$("#searchbycoords").dialog("open");
	});

	function setsearch() {
		$("#searchbyname").dialog("option", "buttons", {
				"Cancel": function() {
					$(this).dialog("close");
				},
				"OK": function() {
					var selected = $(".list-selected");
					if (selected.length < 1)
						return;
					var x = selected.attr("xcoord");
					var y = selected.attr("ycoord");

					$("#map1").gridmap("position", {x: x, y: y});
					$(this).dialog("close");
				},
				"Back" : function() {
					$("#searchbyname").load("searchname1.php",
							"",
							function(data, status, xs) {
								setnameentry();
							}
					);
				}
		});

		$(".list-selectable").click(function(event, ui) {
			$(".list-selectable").removeClass("list-selected");
			$(this).addClass("list-selected");
			var button = $(".ui-dialog-buttonpane").find("button:contains(OK)");
			button.removeClass("ui-state-disabled");
			button.attr("disabled", "");
		});

		var button = $(".ui-dialog-buttonpane").find("button:contains(OK)");
		button.addClass("ui-state-disabled");
		button.attr("disabled", "true");
	}

	function setnameentry() {
		$("#searchbyname").dialog("option", "buttons", {
				"Cancel": function() {
					$(this).dialog("close");
				},
				"Search": function() {
					var name = escape($("#name").val());
					$("#searchbyname").load("searchname2.php?scope_id=<?php echo $scope_id; ?>&name="+name,
							"",
							function(data, status, xs) {
								setsearch();
							}
					);
				}
		});
	}

	$("#searchname").click(function() {
		$("#searchbyname").load("searchname1.php",
				"",
				function(data, status, xs) {
					setnameentry();
					$("#searchbyname").dialog("open");
				}
		);
	});
});
</script>
<div id="map1" style="width: 100%; height: 560px; "></div>
Search <a id="searchcoords">By coords</a> or <a id="searchname">By Name</a>
<p>&nbsp;</p>
<ul id="infomenu" class="contextMenu">
<li id="info" class="info"><a href="#info">Details</a></li>
</ul>
<div id="infodialog"></div>
<div id="searchbycoords" style="display: none;">
<div style="width: 40%; float: left; padding-left: 20px;">X Coordinate</div>
<div style="width: 40%; float: left; padding-left: 20px;"><input style="width: 98%; border: solid 1px black; margin: 2px;" type="text" id="xcoord" /></div>
<div style="width: 40%; float: left; padding-left: 20px; clear: both;">Y Coordinate</div>
<div style="width: 40%; float: left; padding-left: 20px;"><input style="width: 98%; border: solid 1px black; margin: 2px;" type="text" id="ycoord" /></div>
<div style="clear: both">&nbsp;</div>
</div>
<div id="searchbyname"></div>
</body>
</html>
