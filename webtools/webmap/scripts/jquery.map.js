/*
 * (c) 2010 Careminster Limited and Melanie Thielker
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of Careminster Limited nor the
 *       names of its officers, employees or agents may be used to endorse or
 *       promote products derived from this software without specific prior
 *       written permission.
 *     * Any entity using this software for commercial purposes, or on
 *       behalf of any such entity, excluding non-profit corporations,
 *       is required to send any improvements made to this software to the
 *       author at gridmap@careminster.co.uk
 *
 * THIS SOFTWARE IS PROVIDED BY THE DEVELOPERS ``AS IS'' AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

(function($) {
	var GridMap = function(element, o)
	{
		this.getOpt = function(opt)
		{
			if (opt == "position")
			{
				var simx = (this.globalX / 256) | 0;
				var simy = this.opts.sizey - ((this.globalY / 256) | 0);

				return {x: simx, y: simy};
			}
			if (opt == "overlays")
			{
				return this.opts.overlays;
			}
			if (opt == "showgrid")
			{
				return this.opts.showgrid;
			}
			if (opt == "tooltips")
			{
				return this.opts.tooltips;
			}
			return "";
		}

		this.setOpt = function(opt, val)
		{
			if (opt == "position" && typeof val == "object")
			{
				this.globalX = val.x * 256 + 128;
				this.globalY = (this.opts.sizey - val.y) * 256 + 128;

				if (this.globalX < 128)
					this.globalX = 128;
				if (this.globalY < 128)
					this.globalY = 128;
				if (this.globalX > this.opts.sizex * 256 - 128)
					this.globalX = this.opts.sizex * 256 - 128;
				if (this.globalY > this.opts.sizey * 256 - 128)
					this.globalY = this.opts.sizey * 256 - 128;
				
				if (this.updater != undefined)
					clearTimeout(this.updater); 

				thisObject = this; this.updater = setTimeout(function() { thisObject.render(); }, 500, this);
			}
			if (opt == "overlays")
			{
				this.opts.overlays = val;

				if (this.updater != undefined)
					clearTimeout(this.updater);

				thisObject = this; this.updater = setTimeout(function() { thisObject.render(); }, 500, this);
			}
			if (opt == "showgrid")
			{
				this.opts.showgrid = val;
				if (val)
					$(this.surface).children(".map-surface > div").css("border", "solid 1px #7070ff");
				else
					$(this.surface).children(".map-surface > div").css("border", "none");
			}
			if (opt == "tooltips")
			{
				this.opts.tooltips = val;
				this.surface.html("");

				if (this.updater != undefined)
					clearTimeout(this.updater);

				thisObject = this; this.updater = setTimeout(function() { thisObject.render(); }, 500, this);
			}
		}

		this.setPos = function()
		{
			this.surface.css("left", this.x);
			this.surface.css("top", this.y);
		};

		this.stopDrag = function()
		{
			var offX = this.x - (-this.inner.width());
			var offY = this.y - (-this.inner.height());

			if (this.zoom == 7)
			{
				var s = this.inner.width();
				if (this.inner.height() > s)
				s = this.inner.height();

				var size = Math.pow(2,((Math.log(s)/Math.log(2))) | 0)<<1

				offX = this.x - (-size / 2 + this.inner.width() / 2);
				offY = this.y - (-size / 2 + this.inner.height() / 2);

				this.globalX -= offX * 32;
				this.globalY -= offY * 32;
			}
			else
			{
				this.globalX -= offX * this.zoom;
				this.globalY -= offY * this.zoom;
			}

			if (this.globalX < 128)
				this.globalX = 128;
			if (this.globalY < 128)
				this.globalY = 128;
			if (this.globalX > this.opts.sizex * 256 - 128)
				this.globalX = this.opts.sizex * 256 - 128;
			if (this.globalY > this.opts.sizey * 256 - 128)
				this.globalY = this.opts.sizey * 256 - 128;
			
			if (this.updater != undefined)
			       clearTimeout(this.updater);

			thisObject = this; this.updater = setTimeout(function() { thisObject.render(); }, 500, this);
		};

		this.simload = function()
		{
			this.simloader = undefined;

			var coords = "x" + this.simx + "x" + this.simy + "x";
			$.get(this.opts.overview + "?coords=" + coords, "", function(data, status, xd) {
				var obj = $(".map-outerframe");
				var map = obj.data("map");

				map.currentsim = data;

				map.surface.updateTooltip();
			});
		}

		this.render = function()
		{
			if (this.zoom == 7)
			{
				if (this.opts.overview == undefined)
					return;

				var s = this.inner.width();
				if (this.inner.height() > s)
					s = this.inner.height();

				var size = Math.pow(2,((Math.log(s)/Math.log(2))) | 0)<<1

				var coords = "x" + ((this.globalX / 256) | 0) + "x" + (this.opts.sizey - ((this.globalY / 256) | 0)) + "x";
				this.surface.children("img").addClass("map-discard");
				var img = "<img src=\"" + this.opts.overview + "?coords=" + coords + "&size="+size+"\" onLoad="
					$(".map-discard").remove();
				" />";
				this.x = -(size/2) + (this.inner.width() / 2);
				this.y = -(size/2) + (this.inner.height() / 2);
				$(this.surface).children().addClass("map-reuse");
				if ($(".map-discard").length > 0)
					$(img).insertAfter(".map-discard");
				else
					$(this.surface).html(img + $(this.surface).html());
				$(this.surface).css("top", this.y + "px")
				$(this.surface).css("left", this.x + "px");
				if (this.opts.tooltips)
					$(this.surface).find("img").tooltip({track: true, showURL: false, bodyHandler: function() {
						var obj = $(this).parents(".map-outerframe");
						var map = obj.data("map");

						var boundsleft = map.inner.offset().left;
						var boundstop = map.inner.offset().top;
						var boundsright = boundsleft + map.inner.width() - 1;
						var boundsbottom = boundstop + map.inner.height() - 1;

						var mousex = map.mousex;
						var mousey = map.mousey;

						if (mousex < boundsleft || mousex >= boundsright || mousey < boundstop || mousey >= boundsbottom)
							return;

						mousex -= boundsleft;
						mousey -= boundstop;

						var imgx = -(map.x) + mousex;
						var imgy = -(map.y) + mousey;

						var s = map.inner.width();
						if (map.inner.height() > s)
						s = map.inner.height();

						var size = Math.pow(2,((Math.log(s)/Math.log(2))) | 0)<<1

						var sims = size / 16;

						var simx = Math.floor(((map.globalX / 256) | 0) - sims + imgx / 8);
						var simy = Math.round(map.opts.sizey - (((map.globalY / 256) | 0) - sims + imgy / 8) + 0.5);
						if (map.simx != simx || map.simy != simy)
						{
							map.simx = simx;
							map.simy = simy;
							map.currentsim = "";

							if (map.simloader != undefined)
								clearTimeout(map.simloader);
							map.simloader = setTimeout(function(thisObject) { thisObject.simload(); }, 500, map);
							return "(" + simx + "x" + simy + ")";
						}
						if (map.currentsim != "")
						{
							return map.currentsim + "(" + simx + "x" + simy + ")";
						}
						return "(" + simx + "x" + simy + ")";

					}});

				this.surface.mouseup(function(event) {
					var obj = $(this).parents(".map-outerframe");
					var map = obj.data("map");

					if (map.zoom != 7)
						return;

					if (map.dragged)
						return;

					map.zoom = 3;
					map.zoomtext.html(7 - map.zoom);
					map.slider.slider("option", "value", 7 - map.zoom + map.minzoom); 
					map.globalX = map.simx * 256 + 128;
					map.globalY = (map.opts.sizey - map.simy) * 256 + 128;

					if (map.globalX < 128)
						map.globalX = 128;
					if (map.globalY < 128)
						map.globalY = 128;
					if (map.globalX > map.opts.sizex * 256 - 128)
						map.globalX = map.opts.sizex * 256 - 128;
					if (map.globalY > map.opts.sizey * 256 - 128)
						map.globalY = map.opts.sizey * 256 - 128;
					
					if (map.updater != undefined)
						clearTimeout(map.updater);

					thisObject = map; map.updater = setTimeout(function() { thisObject.render(); }, 500, map);
				});
				return;
			}

			this.surface.children("img").remove();

			this.simx = -1;
			this.simy = -1;

			this.updater = undefined; 

			this.x = -$(element).width();
			this.y = -$(element).height();

			this.setPos();

			var size = (256 / this.zoom) | 0;
			if (256 % this.zoom)
				size++;


			var simx = (this.globalX / 256) | 0;
			var simy = this.opts.sizey - ((this.globalY / 256) | 0);

			var offsetx = (this.globalX % 256) / this.zoom;
			var offsety = (this.globalY % 256) / this.zoom;

			this.surface.css("background-position", "-" + offsetx+"px -"+offsety+"px");

			var centerx = this.surface.width() / 2;
			var centery = this.surface.height() / 2;

			var spacetoleft = ((this.surface.width() / 6 + size - 1) / size) | 0;
			var spaceuptop = ((this.surface.height() / 6 + size - 1) / size) | 0;

			var minx = simx - spacetoleft;
			var miny = simy - spaceuptop;

			var horz = (((this.inner.width() + size - 1 ) / size) | 0) + 1;
			var vert = (((this.inner.height() + size - 1 ) / size) | 0) + 1;

			var maxx = minx + horz;
			var maxy = miny + vert;

			this.surface.children().each(function(index, element) {
				var classes = $(element).attr("class");
				var pieces = classes.split("x");
				var x = pieces[1];
				var y = pieces[2];
				
				if (x < minx || x > maxx || y < miny || y >= maxy)
					$(element).attr("class", "map-reuse");
			});

			for (j = -spaceuptop ; j < vert - spaceuptop + 1 ; j ++)
			{
				for (i = -spacetoleft ; i < horz - spacetoleft + 1 ; i ++)
				{
					var classname = "x"+(i+simx)+"x"+(simy-j)+"x";
					var n = this.surface.children("."+classname);
					if ((i+simx) < 0 || (i+simx) > this.opts.sizex)
					{
						if (n != undefined)
							n.addClass("map-reuse");
						continue;
					}
					if ((simy-j) < 0 || (simy-j) > this.opts.sizey)
					{
						if (n != undefined)
							n.addClass("map-reuse");
						continue;
					}

					if (n.length == 0)
					{
						n = this.surface.children(".map-reuse:first");
						if (n.length == 0)
						{
							var newdiv = document.createElement("div");
							newdiv.setAttribute("class", classname);
							this.surface.append(newdiv);
							var n = this.surface.children("."+classname);
							n.css("position", "absolute");
							n.css("overflow", "hidden");
							if (this.opts.showgrid)
								n.css("border", "solid 1px #7070ff");
							n.css("width", size);
							n.css("height", size);
						}
						else
						{
							n.css("background-image", "none");
							n.html("");
							n.attr("class", classname);
						}
						var tooltips = this.opts.tooltips;
						n.rightClick(function(event) {
							var obj = $(this).parents(".map-outerframe");
							var map = obj.data("map");

							map.opts.rclick(event, this);
						});
						n.load(this.opts.tileurl + "?coords="+classname+"&size="+size+"&scopeid="+this.opts.scopeid+"&overlays="+this.opts.overlays+"&user="+this.opts.user+" .data", "", function(resp, stat, xhr)
						{
							var ct = resp.split("\n");
							if (tooltips)
								$(this).tooltip({track: true, showURL: false, bodyHandler: function() { return ct[0]; }});
							if (ct[1] != "")
							{
								$(this).css("background-image", "url("+ct[1]+")");
							}
						});
					}
					n.css("top", centery + j * size - offsety);
					n.css("left", centerx + i * size - offsetx);
				}
			}

			this.surface.children(".map-reuse").css("top", "-100px");
		};

		var elem = $(element);
		var obj = this;
		this.opts = o;
		// Override it. Never worked anyway
		this.opts.maxzoom = 6;

		elem.html('<div class="map-innerframe"><div class="map-surface"></div><div class="map-controls" style="margin: 0px">Zoom<br><span class="map-zoomtext"></span><div class="map-zoom"></div></div></div>');

		$(element).addClass("map-outerframe");
		$(element).css("margin", "0px");

		this.minzoom = 0;

		if (o.overview != undefined)
			this.minzoom = 1;

		$(element).find(".map-zoom").slider({
				min: 1,
				max: o.maxzoom+this.minzoom,
				value: 4+this.minzoom,
				orientation: "vertical",
				stop: function(event, ui) {
					var obj = $(this).parents(".map-outerframe");
					var map = obj.data("map");
					
					var value = 7 - ui.value + map.minzoom;
					map.zoomtext.html(ui.value-map.minzoom);
					if (map.zoom == value)
						return;

					map.zoom = value;

					map.surface.html("");

					if (map.updater != undefined)
						clearTimeout(map.updater);

					thisObject = map; map.updater = setTimeout(function() { thisObject.render(); }, 500, map);					

				},
				slide: function(event, ui) {
					var obj = $(this).parents(".map-outerframe");
					var map = obj.data("map");
					
					map.zoomtext.html(ui.value-map.minzoom);
				}
		});
		this.slider = $(element).find(".map-zoom");
		this.zoomtext = $(element).find(".map-zoomtext");
		this.zoomtext.html("4");
		this.surface = $(element).find(".map-surface");
		this.inner = $(element).find(".map-innerframe");
		this.inner.height($(element).innerHeight());
		this.inner.width($(element).innerWidth());

		this.surface.width(this.inner.width() * 3);
		this.surface.height(this.inner.height() * 3);
		this.surface.css("margin", "0px");
		this.inner.css("margin", "0px");

		this.surface.css("position", "relative");

		this.x = -(this.inner.width());
		this.y = -(this.inner.height());

		this.globalX = this.opts.sizex * 256 / 2;
		this.globalY = this.opts.sizey * 256 / 2;

		this.mousex = 0;
		this.mousey = 0;

		if (this.opts.posx != 0)
			this.globalX = this.opts.posx * 256 + 128;
		if (this.opts.posy != 0)
			this.globalY = (this.opts.sizey - this.opts.posy) * 256 + 128;

		this.zoom = 3;

		this.dragging = false;

		this.setPos();
		this.render();

		this.inner.mousewheel(function(event, delta) {
			var obj = $(this).parents(".map-outerframe");
			var map = obj.data("map");

            event.preventDefault();
			delta =- delta;

			if (map.zoom <= 1 && delta < 0)
				return;
			if (map.zoom >= map.opts.maxzoom + map.minzoom && delta > 0)
				return;

			map.zoom += delta;

			map.slider.slider("option", "value", 7 - map.zoom + map.minzoom); 
			map.zoomtext.html(7 - map.zoom);

			map.surface.html("");

			if (map.updater != undefined)
				clearTimeout(map.updater);

			thisObject = map; map.updater = setTimeout(function() { thisObject.render(); }, 500, map);

		});

		this.surface.mousedown(function(event) {
			if(event.button == 2)
				return;

			var obj = $(this).parents(".map-outerframe");
			var map = obj.data("map");

			map.mousebasex = event.pageX;
			map.mousebasey = event.pageY;
			map.mousedownx = event.pageX;
			map.mousedownx = event.pageY;

			map.dragging = true;
			map.dragged = false;

			if (map.updater != undefined)
				clearTimeout(map.updater);
			map.updater = undefined;

			event.preventDefault();
		});

		this.surface.mouseup(function(event) {
			var obj = $(this).parents(".map-outerframe");
			var map = obj.data("map");

			if (!map.dragging)
				return;

			map.dragging = false;

			map.stopDrag();

			event.preventDefault();
		});

		this.surface.mouseleave(function(event) {
			var obj = $(this).parents(".map-outerframe");
			var map = obj.data("map");

			if (!map.dragging)
				return;

			map.dragging = false;

			map.stopDrag();

			event.preventDefault();
		});

		this.surface.mousemove(function(event) {
			var obj = $(this).parents(".map-outerframe");
			var map = obj.data("map");
			
			map.mousex = event.pageX;
			map.mousey = event.pageY;

			if (!map.dragging)
				return;

			map.dragged = true;
			event.preventDefault();

			var mousex = event.pageX;
			var mousey = event.pageY;

			var boundsleft = map.inner.offset().left;
			var boundstop = map.inner.offset().top;
			var boundsright = boundsleft + map.inner.width() - 1;
			var boundsbottom = boundstop + map.inner.height() - 1;

			if (mousex < boundsleft || mousex > boundsright || mousey < boundstop || mousey > boundsbottom)
				return;
			
			diffx = mousex - map.mousebasex;
			diffy = mousey - map.mousebasey;

			map.mousebasex = mousex;
			map.mousebasey = mousey;

			map.x += diffx;
			map.y += diffy;

			map.setPos();
		});
	};

	$.fn.gridmap = function(options, arg) {
		if (typeof options == 'string')
		{
			if (arg == undefined)
			{
				var element = $(this).filter(":first");
				if (element.data("map"))
				{
					var map = element.data("map");
					return map.getOpt(options);
				}
				return "";
			}

			return this.each(function() {
				var element = $(this);
				if (element.data("map"))
				{
					var map = element.data("map");
					map.setOpt(options, arg);
				}
			});
		}

		var defaults = {
			sizex: '2048',
			sizey: '2048',
			posx: '0',
			posy: '0',
			maxzoom: 6,
			tileurl: "maptile.php",
			scopeid: "00000000-0000-0000-0000-000000000000",
			overlays: 0,
			showgrid: false,
			user: "00000000-0000-0000-0000-000000000000",
			tooltips: false,
			overview: "quickmap.php",
			rclick: function() { }
		};

		var opts = jQuery.extend(defaults, options);

		return this.each(function() {
			var element = $(this);

			if (element.data('map'))
				return;

			var map = new GridMap(this, opts);

			element.data('map', map);
		});
	};
})(jQuery);