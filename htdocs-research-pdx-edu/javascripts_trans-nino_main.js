////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Title:  oiscript_service.js
// Use:  JavaScript for Trans-Nino Index Calculator
// Created:  Lawrence Duncan [lawrence@orionimaging.com], 2/25/2006, for Adam Kennedy [kennaster@gmail.com]

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//NAME:  browse_check()
//USE:  to check state of browser (behavior vs. version)
//IN:  nothing
//OUT:  "NN", "IE", or "text" [was: char OR{2o3, 3o4, NN4, NN6, NN6+, IE4, IE5, IE5+}]
function browse_check()
{
	var browser = "";
	if (!document.images) browser = "text"; //"2o3";  // NN2 or IE3 or text-based
	else                                    //if (document.images)
	{ //image support true...
		if (document.layers) browser = "NN"; //"NN4";  // NN4
		else
		{ //non-layer-based browser [ie: not NN]...
			if (document.getElementById) browser = "IE"; //"IE5";  // NN6+ or IE5+
			if ((document.getElementById) && (!document.all)) browser = "NN"; //"NN";  // NN6+
			if (browser!="IE" && browser!="NN") browser = "IE";  //mystery browser condition [*Note: defaults to IE]...
		} //end else: non-layer-based browser [ie: not NN];
	} //end else: image support true;
  return browser;
} //end function: browse_check();


////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//NAME:  openWindow(url,name,width,height,['plain'])
//USE:  to jump to Orion Imaging main page
//IN:  url is string for the page to jumpto
//	   name is string for name of new window
//	   width and height are stings for width and height (normalized from 0 to 1)
//	   plain (or any other value) is optional for turning off location, tools, and status bars
//OUT:  null
function openWindow(url,name,width,height)
{
	var buttons = "yes";
	if (arguments.length==5) buttons = "no";
	var browser = browse_check();
	switch (browser)
	{
		case "IE":
			var wide = width * window.screen.availWidth;
			if (buttons=="no") var high = 0.93 * height * window.screen.availHeight;
			 else var high = 0.82 * height * window.screen.availHeight;
			var tip = (window.screen.availHeight - high)/7.4;
			var lift = (window.screen.availWidth - wide)/2.7;
			var catch_opened1 = window.open(url,name,'width='+wide+',height='+high+',top='+tip+',left='+lift+',location='+buttons+',toolbar='+buttons+',menubar=yes,status='+buttons+',scrollbars=yes,resizable=yes');
		  break;
		case "NN":
			var wide = width * window.screen.availWidth;
			if (buttons=="no") var high = 0.93 * height * window.screen.availHeight;
			 else var high = 0.82 * height * window.screen.availHeight;
			var tip = (window.screen.availHeight - high)/7.4;
			var lift = (window.screen.availWidth - wide)/2.7;
			var catch_opened1 = window.open(url,name,'width='+wide+',height='+high+',screenY='+tip+',screenX='+lift+',location='+buttons+',toolbar='+buttons+',menubar=yes,status='+buttons+',scrollbars=yes,resizable=yes');
			break;
		default: location.href = url;
	}
  return;
} //end function: openWindow();
	

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//NAME:  frame_split(page, goto_page)
//USE:  to break out of frames
//IN:  page is string of page to check against
//	   goto_page is string of page to goto
//OUT:  null
function frame_split(page, goto_page)
{
	var frame_check = top.location.href.split('/');
	frame_check = frame_check[frame_check.length-1].split('?');
	if(frame_check[0] != page)
	 top.location.href = goto_page;
  return;
} //end function: frame_split();


//~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~Page Formatting ~~~~~~~~~~~~~~
//~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//NAME:  ccom_begin(browse_type, [browse_type2],[...])
//USE:  conditional comment, begin.
//IN:  browse_type is type of browser NOT affected by the comment tags (as string)
//		ie: wrap content for a specific browser in ccom() tags, passing browser type that the content is for...
//OUT:  null
function ccom_begin(browse_type)
{
	var check = 0;  var args_num = arguments.length;
	for (i=0; i<args_num; i++)
	{ if (browser==arguments[i]) check = 1; }
	if (check==0)  { document.write('<!--');  document.close(); }
  return;
} //end function: ccom_begin();

//NAME:  ccom_end(browse_type, [browse_type2],[...])
//USE:  Conditional comment, end (see above...)
//IN:  browse_type as string...
//OUT:  null
function ccom_end(browse_type)
{
	var check = 0;  var args_num = arguments.length;
	for (i=0; i<args_num; i++)
	{ if (browser==arguments[i]) check = 1; }
	if (check==0) { document.write('-->');  document.close(); }
  return;
} //end function: ccom_end();


////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//NAME:  imageBorder(img_name), imageUnborder(img_name)
//USE:  to put on, and take off borders, respectively
//IN:  img_name is string for image name
//OUT:
function imageBorder(img_name)
{
	if (browser=="IE" || browser=="NN") document.images[img_name].border = 1;
} //end function: imageBorder();
function imageUnborder(img_name)
{
	if (browser=="IE" || browser=="NN") document.images[img_name].border = 0;
} //end function: imageUnborder();


////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//NAME:  img_swap(elm_id, to_img1, [to_img2], ...)
//USE:  to highlight (or unhighlight) an image (image swap)
//IN:  elm_id is element id to highlight, to_img is highlighted image name
//OUT:  nive swapped image...
function img_swap(elm_id, to_img)
{
	if (document.images)
	{
		var root = document.images[elm_id];
		root.src = rolls[to_img].src;
		if (browser=="NN")
		{
			if (document[elm_id].style.cursor != "pointer") root.style.cursor = "pointer";
			else root.style.cursor = "auto";
		}
		else
		{
			if (root.style.cursor != "hand") root.style.cursor = "hand";
			else root.style.cursor = "auto";
		}
		if (arguments.length>2)
		{
			for (i=2; i < arguments.length; i++)
			{
				clearTimeout(timer[i]);
				timer[i] = setTimeout(document[elm_id].src = arguments[i], i*100);
			}
		}
	}
  return;
} //end function: image_swap();


////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//NAME:  highLight(elm_id)
//USE:  to highlight text (bolder & different color)
//IN:
//OUT:
function highLight(elm_id)
{
	if (browser=="IE" || browser=="NN")
	{
		var root = document.getElementById(elm_id);
		colors = ["darkmagenta","darkviolet","mediumorchid","slateblue","mediumpurple","blueviolet","lightsteelblue","royalblue","dodgerblue","deepskyblue","cornflowerblue","aqua","mediumturquoise","aquamarine","olivedrab","lawngreen","darkseagreen","wheat","yellow","gold","burlywood","darkorange","orangered","crimson","mediumvioletred"];
		old_color = root.style.color;  //**NOTE: colors and old_color are declared here as global variables
		weight_holder = root.style.fontWeight;  //sets global var to remember weight
		root.style.color = colors[next_color % colors.length];
		//root.style.fontSize = "1.0em";
		root.style.fontWeight = "900";
		if (browser=="NN") root.style.cursor = "pointer";
		else root.style.cursor = "hand";
		next_color++;
	}
  return;
} //end function: highLight();

//NAME:  unhighLight(elm_id)
//USE:  to unhighlight text
//IN:
//OUT:
function unHighLight(elm_id)
{
	if (browser=="IE" || browser=="NN")
	{
		var root = document.getElementById(elm_id);
		root.style.color = old_color; //"whitesmoke";  //old_color; (Note: hard-wired-old_color')
		//root.style.fontSize = "1.0em";
		root.style.fontWeight = weight_holder;
		root.style.cursor = "auto";
	}
  return;
} //end function: unHighLight();


////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//NAME:  lowLight(elm_id)
//USE:  to 'lowlight' text (just bolder [no color change])
//IN:
//OUT:
function lowLight(elm_id)
{
	if (browser=="IE" || browser=="NN")
	{
		var root = document.getElementById(elm_id);
		weight_holder = root.style.fontWeight;  //sets global var to remember weight
		root.style.fontWeight = "900";
		if (browser=="NN") root.style.cursor = "pointer";
		else root.style.cursor = "hand";
	}
  return;
} //end function: lowLight();

//NAME:  lowLightMild(elm_id)
//USE:  to 'lowlight' text    {undelines & changes color to 'whitesmoke' (if not already)
//IN:  elm_id is element id, [color is optional color to highlight to]                 **A milder effect than lowLight()
//OUT:                                                                                **NOTE: use unLowLight() |OR| unLowLightMild() to undo lowLightMild()
function lowLightMild(elm_id)
{
	if (browser=="IE" || browser=="NN")
	{
		var root = document.getElementById(elm_id);
		color_holder = root.style.color;  //sets global var to remember element color
		weight_holder = root.style.fontWeight;
		//root.style.fontWeight = "bold";
		root.style.textDecoration = "underline";
		if (arguments.length==2) root.style.color = arguments[1];
		else root.style.color = "steelblue"; //"whitesmoke";
		if (browser=="NN") root.style.cursor = "pointer"; //pointer
		else root.style.cursor = "hand";
	}
  return;
} //end function: lowLightMild();

//NAME:  unLowLight(elm_id)                **NOTE: undoes underlining AND font-weight
//USE:  to unhighlight text
//IN:  elm_id is element id
//OUT:
function unLowLight(elm_id)
{
	if (browser=="IE" || browser=="NN")
	{
		var root = document.getElementById(elm_id);
		root.style.fontWeight = weight_holder;
		root.style.textDecoration = "none";
		root.style.cursor = "auto";
	}
  return;
} //end function: unLowLight();

//NAME:  unLowLightMild(elm_id)            **NOTE: undoes underlining AND color change, but NOT font-weight
//USE:  to unLowLightMild text
//IN:  elm_id is element id
//OUT:
function unLowLightMild(elm_id)
{
	if (browser=="IE" || browser=="NN")
	{
		var root = document.getElementById(elm_id);
		root.style.textDecoration = "none";
		root.style.color = color_holder;
		root.style.cursor = "auto";
	}
  return;
} //end function: unLowLightMild();

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//NAME:  go(go_page)
//USE:  to send browser to a page
//IN:  go_page is string for page to goto
//OUT:
function goPage(go_page)
{
	top.location.href = go_page;
  return;
} //end function: goPage();