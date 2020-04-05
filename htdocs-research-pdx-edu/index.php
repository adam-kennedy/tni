<?php

// Title:  Trans-Nino Index Calculator
// Created:  Lawrence Duncan [lawrence@orionimaging.com], 2/25/2006, for Adam Kennedy [kennaster@gmail.com]
	
	@ $serverVars = array($_COOKIE['tni'], $_SERVER['REMOTE_ADDR'], $_SERVER['REMOTE_PORT'], $_SERVER['HTTP_HOST'], $_SERVER['HTTP_USER_AGENT'],
					   $_SERVER['QUERY_STRING'], $_SERVER['HTTP_REFERER'], $_SERVER['DOCUMENT_ROOT'], $_SERVER['SCRIPT_FILENAME']);

	require('php_common.php');    require('php_common.html.php');    require('php_calculations.php');    require('php_mimic.live.php');
	//*Note:  head() comes later...
	
   //get input parameters and set defaults...
	if (isset($_GET['params_output'])) $params_output = $_GET['params_output'];  else $params_output = 'intro';
	if (isset($_GET['params_stdBegin'])) $params_stdBegin = $_GET['params_stdBegin'];  else $params_stdBegin = 1950;
	if (isset($_GET['params_stdEnd'])) $params_stdEnd = $_GET['params_stdEnd'];  else $params_stdEnd = 2004;
	if (isset($_GET['verbose'])) $verbose = $_GET['verbose'];  else $verbose = false; //set to 'verbose' for displaying multi-month output in verbose mode...
	
	$multiMonthSelect = false; //initialize state of multi-month selections...
	if (isset($_GET['per_1']) || isset($_GET['per_2']) || isset($_GET['per_3']) || isset($_GET['per_4']) || isset($_GET['per_5']) || isset($_GET['per_6'])
		|| isset($_GET['per_7']) || isset($_GET['per_8']) || isset($_GET['per_9']) || isset($_GET['per_10']) || isset($_GET['per_11']) || isset($_GET['per_12']))
	{ //at least one multi-month get var is set, so must be calling multi-month TNI calculator...
		$multiMonthSelect = true; //must be calling multi-month TNI calculator, so set true...
		for ($i=1; $i<13; ++$i)
		{ //check all 12 periods for multi-select gets [months 1~12]...
			eval('$per_'.$i.' = array();'); //initialize period holders, then search through each period, months 1~12...
			$count = 0; //initialize [$count counts how many months for a given period are selected]...
			for ($j=1; $j<25; ++$j)
			{ //check all months for a given period [months 1~12, but for both the i'th year & the i+1'th year]...
				$perMonth = multi_get($serverVars, 'per_'.$i, $j);  //echo '<br>$perMonth [per_'.$i.'] == '.$perMonth;  //if ($perMonth==false) die('<p>really, truly false'); //**debug...
				if ($perMonth!=false)
				{ //there exists a multi-select for this period $i, selected month is $j [and also result of multi_get($serverVars, 'per_'.$i, $j)]...
					eval('$per_'.$i.'['.$count.'] = \''.$perMonth.'\';');
					++$count;
				} //end if: there exists a multi-select for this period, selected month is $j;
			} //end for: check all months for a given period;
		} //end for: check all 12 periods for multi-select gets [months 1~12];
		$periods = array($per_1, $per_2, $per_3, $per_4, $per_5, $per_6, $per_7, $per_8, $per_9, $per_10, $per_11, $per_12); //ready array of periods f/ passing to calculator...
		//$hmm = '';  for ($i=0; $i<count($per_1); ++$i) $hmm .= '$per_1['.$i.'] == '.$per_1[$i].'<br>';  echo('<p>'.$hmm); //**debug...
	} //end if: at least one multi-month get var is set, so must be calling multi-month TNI calculator;
	else $periods = array();

	if ($params_output=='rawText')
	{ //outputs tab-delimited text...
		header('Content-type: text/plain');
		calculate_tni($serverVars, $params_output, $params_stdBegin, $params_stdEnd, $periods);
		return;
	} //end if: outputs tab-delimited text;
	else head($serverVars);
?>

<script language="JavaScript" type="text/javascript">
 //<![CDATA[
	var next_color = 0;  var browser = browse_check();

	if (document.images)
	{	rolls = new Array();
	
		rolls.header_u = new Image();		rolls.header_u.src = "kitr_headerGraphic3flt1-wb1.jpg";
		rolls.header_x = new Image();		rolls.header_x.src = "kitr_headerGraphic2_h2flt1-wb1.jpg";
	}
 //]]>
</script>
</head>
<body class="main">
<span onMouseOver="img_swap('header','header_x');" onMouseOut="img_swap('header','header_u');" onClick="location.href='http://westernclimate.com';"> 
 <img id="header" alt="The Kennedy Institute of Teaching &amp; Research" src="kitr_headerGraphic3flt1-wb1.jpg" width="800" height="159" border="0" /></span> 
<table align="center" width="100%" cellpadding="0" cellspacing="0" border="0">
  <tr>
	<td width="50%">&nbsp;</td>
	<td class="left" style="font-size: 1.2em; font-weight: 100; white-space: nowrap;" valign="top"> &nbsp;&nbsp;
	  <span id="main" onMouseOver="lowLightMild('main');" onMouseOut="unLowLightMild('main');" onClick="location.href='index.php';"
	   >Trans-Ni&ntilde;o Index Calculator:</span> &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;
	  <!--<span id="kitr" class="mono" onmouseover="lowLightMild('kitr');" onmouseout="unLowLightMild('kitr');"
	    onclick="openWindow('http://web.pdx.edu/~kenna/tni/','kitr',0.85,0.85);"
	   >The Kennedy Institute for Teaching &amp; Research</span>--></td>
	<td width="50%">&nbsp;</td></tr>
  <tr>
    <td colspan="3" style="font-size: 0.5em;">&nbsp;</td></tr>
  <tr>
    <td colspan="3"><hr size="1" style="color: steelblue;" /></td></tr>
  <tr>
    <td colspan="3">
	  <table align="center" width="79%" cellpadding="0" cellspacing="0" border="0"><!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Begin: parameters inner table -->
	   <? echo '<form action="index.php" method="get">'; ?>
	    <tr>
		  <td width="9%">&nbsp;</td>
		  <td style="font-size: 0.9em; font-weight: 700;" width="23%">Calculator Output:<br />
		    <select name="params_output">
			  <option value="intro" <? if ($params_output=='intro') echo ' selected'; ?>>&nbsp;Introduction&nbsp;</option>
			  <option value="summary" <? if ($params_output=='summary') echo ' selected'; ?>>&nbsp;Ni&ntilde;o Summary Statistics&nbsp;</option>
			  <option value="tni" <? if ($params_output=='tni') echo ' selected'; ?>>&nbsp;Monthly Trans-Ni&ntilde;o Index &nbsp;</option>
			  <option value="tniMulti" <? if ($params_output=='tniMulti') echo ' selected'; ?>>&nbsp;Multi-Month Trans-Ni&ntilde;o &nbsp;</option>
			  <option value="rawHtml" <? if ($params_output=='rawHtml') echo ' selected'; ?>>&nbsp;Raw Data (html-formatted)&nbsp;</option>
			  <option value="rawText" <? if ($params_output=='rawText') echo ' selected'; ?>>&nbsp;Raw Data (tab-delimited)&nbsp;</option>
			</select></td>
		  <td style="font-size: 0.9em; font-weight: 700;" width="23%">Standardization:<br /> Beginning Year:
		    <select name="params_stdBegin">
			  <? for ($i=1950; $i<date('Y'); ++$i)  { echo '<option value="'.$i.'"';  if ($params_stdBegin==$i) echo ' selected';  echo '>&nbsp;'.$i.'&nbsp;</option>'; } ?>
			</select></td>
		  <td style="font-size: 0.9em; font-weight: 700;" width="23%">Standardization:<br /> Ending Year:
		    <select name="params_stdEnd">
			  <? for ($i=1950; $i<date('Y'); ++$i)  { echo '<option value="'.$i.'"';  if ($params_stdEnd==$i) echo ' selected';  echo '>&nbsp;'.$i.'&nbsp;</option>'; } ?>
			</select></td>
		  <td width="9%" style="font-size: 0.9em; font-weight: 700;">
		    <? if ($params_output!='tniMulti' || $multiMonthSelect) echo '<input type="submit" value="{Go!}"></input>';  else echo '&nbsp;'; ?></td>
		  <td>&nbsp;</td>
		</tr>
	   <? if ($params_output!='tniMulti') echo '</form>'; ?>
	  </table><!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ End: parameters inner table -->
	</td></tr>
  <tr>
    <td colspan="3"><hr size="1" style="color: steelblue;" /></td></tr>
</table>

<?
	if ($params_output=='intro')
	{ //display introductory material...
		echo '
		   <br />
			<table align="center" width="90%" cellpadding="0" cellspacing="0" border="0">
			  <tr>
			    <td class="left" style="line-height: 1.5em;"><span style="font-size: 0.9em; font-weight: 700;">Background Information:</span></td></tr>
			  <tr>
				<td class="left" style="line-height: 1.1em;"> &nbsp; &nbsp; &nbsp;
					This research investigates large-scale climate signals affecting inter-annual hydrologic variability of streams flowing into Upper Klamath Lake,    	                    Oregon, USA. Upper Klamath Lake (UKL) is an arid, mountainous basin located in the rain shadow east of the crest of the Cascade Mountains in the                    northwestern United States.  It is an important reservoir serving the 971 km2 Klamath Irrigation Project, established in 1906 by the US Bureau of                    Reclamation.</td></tr>
			  <tr><td style="line-height: 0.5em;">&nbsp;</td></tr>
			  <tr>
			    <td class="left" style="line-height: 1.1em;"> &nbsp; &nbsp; &nbsp;
					Developing accurate early season streamflow prediction models for UKL is difficult because the basin has a high degree of topographic, geologic, and 					climatologic variability.  Furthermore, peak monthly streamflow is occurring earlier, which may be associated with global climate change.  In an 					                    effort to reduce early season streamflow forecast uncertainty, six large-scale climate indices - the Pacific North American Pattern, Southern 	                    Oscillation Index, Pacific Decadal Oscillation (PDO), Multivariate El Niño Southern Oscillation Index, Niño 3.4, and a revised Trans-Niño Index (			                    TNI) - were evaluated independently for their ability to explain inter-annual variation of the main hydrologic inputs into Upper Klamath Lake.</td>                    </tr>
			  <tr><td style="line-height: 0.5em;">&nbsp;</td></tr>
			  <tr>
			    <td class="left" style="line-height: 1.1em;"> &nbsp; &nbsp; &nbsp;
					The TNI, which characterizes the sea surface temperature (SST) gradient between regions Niño 1+2 and Niño 4, was the only index to show significant correlations during the current warm phase of the PDO.  During the warm PDO phase (1978-present), the averaged October through December TNI was strongly correlated with the subsequent April through September Williamson River discharge (r = 0.73), Sprague River discharge (r = 0.65), net inflow to UKL (r = 0.67), and moderately correlated with observed Crater Lake 1 April snow water equivalent (r = 0.51).  Incorporating the TNI variable into operational statistical streamflow prediction models significantly (p-value < 0.1) reduces the uncertainty (measured by the standard error) of forecasts issued on the first of December, January, and February by 7%, 9%, and 8.3%, respectively, for the Williamson River and by 9.2%, 7.5%, and 10.4%, respectively, for the Sprague River.</td></tr>
			  <tr><td style="line-height: 0.5em;">&nbsp;</td></tr>
			  <tr>
			    <td class="left" style="line-height: 1.1em;"> &nbsp; &nbsp; &nbsp;
					These results suggest that during the current climate regime, SST gradients, as opposed to mean SST or sea-level pressure patterns, explain a significant  portion of hydrologic variability observed in the streams flowing into UKL and are useful in real-time hydrologic applications.</td></tr>
			  <tr><td style="line-height: 0.5em;">&nbsp;</td></tr>
			  <tr>
			    <td class="left" style="font-size: 0.9em; line-height: 1.4em;">&nbsp;
					See also: &nbsp; Trenberth, K. E., and D. P. Stepaniak, 2001.&nbsp; Indices of El Niño Evolution.&nbsp; /J. Climate/, *14*, 1697-1701.</td></tr>
			  <tr><td style="line-height: 0.5em;">&nbsp;</td></tr>
			</table>
		   <br />
			<table align="center" width="90%" cellpadding="0" cellspacing="0" border="0">
			  <tr>
			    <td class="left" style="line-height: 1.5em;"><span style="font-size: 0.9em; font-weight: 700;">Calculations:</span></td></tr>
			</table>
			<img alt="Flowchart for Calculating the Trans-Ni&ntilde;o Index" src="tni_flow_20060310.png"
			  width="'.round(1.0*829).'" height="'.round(1.0*574).'" border="0">
		   <br />';
					  
	} //end if: $params_output=='intro' ==> display introductory material;
	else
	{ //go with calculations...
		if ($params_output=='tniMulti' && !$multiMonthSelect)
		{ //echo the multi-month selector, then return early...
		  echo '
			<table align="center" cellpadding="0" cellspacing="5" border="0">
			 <caption class="strong_left"><br />Please select months to average over:</caption>
			  <tr>
				<td class="strong" style="font-size: 0.9em;">Period 1</td><td class="strong" style="font-size: 0.9em;">Period 2</td>
				<td class="strong" style="font-size: 0.9em;">Period 3</td><td class="strong" style="font-size: 0.9em;">Period 4</td>
				<td class="strong" style="font-size: 0.9em;">Period 5</td><td class="strong" style="font-size: 0.9em;">Period 6</td>
				<td class="strong" style="font-size: 0.9em;">Period 7</td><td class="strong" style="font-size: 0.9em;">Period 8</td>
				<td class="strong" style="font-size: 0.9em;">Period 9</td><td class="strong" style="font-size: 0.9em;">Period 10</td>
				<td class="strong" style="font-size: 0.9em;">Period 11</td><td class="strong" style="font-size: 0.9em;">Period 12</td>	
			  </tr>
			  <tr>
				<td><select name="per_1" multiple="multiple" size="26">
				  <optgroup label="&nbsp;&nbsp; i\'th year">
				   <option value="1a">January</option><option value="2a">February</option><option value="3a">March</option><option value="4a">April</option>
				   <option value="5a">May</option><option value="6a">June</option><option value="7a">July</option><option value="8a">August</option>
				   <option value="9a">September</option><option value="10a">October</option><option value="11a">November</option><option value="12a">December</option></optgroup>
				  <optgroup label="&nbsp;&nbsp; i+1\'th year">
				   <option value="1b">January</option><option value="2b">February</option><option value="3b">March</option><option value="4b">April</option>
				   <option value="5b">May</option><option value="6b">June</option><option value="7b">July</option><option value="8b">August</option>
				   <option value="9b">September</option><option value="10b">October</option><option value="11b">November</option><option value="12b">December</option></optgroup>
				  </select></td>	
				<td><select name="per_2" multiple="multiple" size="26">
				  <optgroup label="&nbsp;&nbsp; i\'th year">
				   <option value="1a">January</option><option value="2a">February</option><option value="3a">March</option><option value="4a">April</option>
				   <option value="5a">May</option><option value="6a">June</option><option value="7a">July</option><option value="8a">August</option>
				   <option value="9a">September</option><option value="10a">October</option><option value="11a">November</option><option value="12a">December</option></optgroup>
				  <optgroup label="&nbsp;&nbsp; i+1\'th year">
				   <option value="1b">January</option><option value="2b">February</option><option value="3b">March</option><option value="4b">April</option>
				   <option value="5b">May</option><option value="6b">June</option><option value="7b">July</option><option value="8b">August</option>
				   <option value="9b">September</option><option value="10b">October</option><option value="11b">November</option><option value="12b">December</option></optgroup>
				  </select></td>	
				<td><select name="per_3" multiple="multiple" size="26">
				  <optgroup label="&nbsp;&nbsp; i\'th year">
				   <option value="1a">January</option><option value="2a">February</option><option value="3a">March</option><option value="4a">April</option>
				   <option value="5a">May</option><option value="6a">June</option><option value="7a">July</option><option value="8a">August</option>
				   <option value="9a">September</option><option value="10a">October</option><option value="11a">November</option><option value="12a">December</option></optgroup>
				  <optgroup label="&nbsp;&nbsp; i+1\'th year">
				   <option value="1b">January</option><option value="2b">February</option><option value="3b">March</option><option value="4b">April</option>
				   <option value="5b">May</option><option value="6b">June</option><option value="7b">July</option><option value="8b">August</option>
				   <option value="9b">September</option><option value="10b">October</option><option value="11b">November</option><option value="12b">December</option></optgroup>
				  </select></td>	
				<td><select name="per_4" multiple="multiple" size="26">
				  <optgroup label="&nbsp;&nbsp; i\'th year">
				   <option value="1a">January</option><option value="2a">February</option><option value="3a">March</option><option value="4a">April</option>
				   <option value="5a">May</option><option value="6a">June</option><option value="7a">July</option><option value="8a">August</option>
				   <option value="9a">September</option><option value="10a">October</option><option value="11a">November</option><option value="12a">December</option></optgroup>
				  <optgroup label="&nbsp;&nbsp; i+1\'th year">
				   <option value="1b">January</option><option value="2b">February</option><option value="3b">March</option><option value="4b">April</option>
				   <option value="5b">May</option><option value="6b">June</option><option value="7b">July</option><option value="8b">August</option>
				   <option value="9b">September</option><option value="10b">October</option><option value="11b">November</option><option value="12b">December</option></optgroup>
				  </select></td>	
				<td><select name="per_5" multiple="multiple" size="26">
				  <optgroup label="&nbsp;&nbsp; i\'th year">
				   <option value="1a">January</option><option value="2a">February</option><option value="3a">March</option><option value="4a">April</option>
				   <option value="5a">May</option><option value="6a">June</option><option value="7a">July</option><option value="8a">August</option>
				   <option value="9a">September</option><option value="10a">October</option><option value="11a">November</option><option value="12a">December</option></optgroup>
				  <optgroup label="&nbsp;&nbsp; i+1\'th year">
				   <option value="1b">January</option><option value="2b">February</option><option value="3b">March</option><option value="4b">April</option>
				   <option value="5b">May</option><option value="6b">June</option><option value="7b">July</option><option value="8b">August</option>
				   <option value="9b">September</option><option value="10b">October</option><option value="11b">November</option><option value="12b">December</option></optgroup>
				  </select></td>	
				<td><select name="per_6" multiple="multiple" size="26">
				  <optgroup label="&nbsp;&nbsp; i\'th year">
				   <option value="1a">January</option><option value="2a">February</option><option value="3a">March</option><option value="4a">April</option>
				   <option value="5a">May</option><option value="6a">June</option><option value="7a">July</option><option value="8a">August</option>
				   <option value="9a">September</option><option value="10a">October</option><option value="11a">November</option><option value="12a">December</option></optgroup>
				  <optgroup label="&nbsp;&nbsp; i+1\'th year">
				   <option value="1b">January</option><option value="2b">February</option><option value="3b">March</option><option value="4b">April</option>
				   <option value="5b">May</option><option value="6b">June</option><option value="7b">July</option><option value="8b">August</option>
				   <option value="9b">September</option><option value="10b">October</option><option value="11b">November</option><option value="12b">December</option></optgroup>
				  </select></td>	
				<td><select name="per_7" multiple="multiple" size="26">
				  <optgroup label="&nbsp;&nbsp; i\'th year">
				   <option value="1a">January</option><option value="2a">February</option><option value="3a">March</option><option value="4a">April</option>
				   <option value="5a">May</option><option value="6a">June</option><option value="7a">July</option><option value="8a">August</option>
				   <option value="9a">September</option><option value="10a">October</option><option value="11a">November</option><option value="12a">December</option></optgroup>
				  <optgroup label="&nbsp;&nbsp; i+1\'th year">
				   <option value="1b">January</option><option value="2b">February</option><option value="3b">March</option><option value="4b">April</option>
				   <option value="5b">May</option><option value="6b">June</option><option value="7b">July</option><option value="8b">August</option>
				   <option value="9b">September</option><option value="10b">October</option><option value="11b">November</option><option value="12b">December</option></optgroup>
				  </select></td>	
				<td><select name="per_8" multiple="multiple" size="26">
				  <optgroup label="&nbsp;&nbsp; i\'th year">
				   <option value="1a">January</option><option value="2a">February</option><option value="3a">March</option><option value="4a">April</option>
				   <option value="5a">May</option><option value="6a">June</option><option value="7a">July</option><option value="8a">August</option>
				   <option value="9a">September</option><option value="10a">October</option><option value="11a">November</option><option value="12a">December</option></optgroup>
				  <optgroup label="&nbsp;&nbsp; i+1\'th year">
				   <option value="1b">January</option><option value="2b">February</option><option value="3b">March</option><option value="4b">April</option>
				   <option value="5b">May</option><option value="6b">June</option><option value="7b">July</option><option value="8b">August</option>
				   <option value="9b">September</option><option value="10b">October</option><option value="11b">November</option><option value="12b">December</option></optgroup>
				  </select></td>	
				<td><select name="per_9" multiple="multiple" size="26">
				  <optgroup label="&nbsp;&nbsp; i\'th year">
				   <option value="1a">January</option><option value="2a">February</option><option value="3a">March</option><option value="4a">April</option>
				   <option value="5a">May</option><option value="6a">June</option><option value="7a">July</option><option value="8a">August</option>
				   <option value="9a">September</option><option value="10a">October</option><option value="11a">November</option><option value="12a">December</option></optgroup>
				  <optgroup label="&nbsp;&nbsp; i+1\'th year">
				   <option value="1b">January</option><option value="2b">February</option><option value="3b">March</option><option value="4b">April</option>
				   <option value="5b">May</option><option value="6b">June</option><option value="7b">July</option><option value="8b">August</option>
				   <option value="9b">September</option><option value="10b">October</option><option value="11b">November</option><option value="12b">December</option></optgroup>
				  </select></td>	
				<td><select name="per_10" multiple="multiple" size="26">
				  <optgroup label="&nbsp;&nbsp; i\'th year">
				   <option value="1a">January</option><option value="2a">February</option><option value="3a">March</option><option value="4a">April</option>
				   <option value="5a">May</option><option value="6a">June</option><option value="7a">July</option><option value="8a">August</option>
				   <option value="9a">September</option><option value="10a">October</option><option value="11a">November</option><option value="12a">December</option></optgroup>
				  <optgroup label="&nbsp;&nbsp; i+1\'th year">
				   <option value="1b">January</option><option value="2b">February</option><option value="3b">March</option><option value="4b">April</option>
				   <option value="5b">May</option><option value="6b">June</option><option value="7b">July</option><option value="8b">August</option>
				   <option value="9b">September</option><option value="10b">October</option><option value="11b">November</option><option value="12b">December</option></optgroup>
				  </select></td>	
				<td><select name="per_11" multiple="multiple" size="26">
				  <optgroup label="&nbsp;&nbsp; i\'th year">
				   <option value="1a">January</option><option value="2a">February</option><option value="3a">March</option><option value="4a">April</option>
				   <option value="5a">May</option><option value="6a">June</option><option value="7a">July</option><option value="8a">August</option>
				   <option value="9a">September</option><option value="10a">October</option><option value="11a">November</option><option value="12a">December</option></optgroup>
				  <optgroup label="&nbsp;&nbsp; i+1\'th year">
				   <option value="1b">January</option><option value="2b">February</option><option value="3b">March</option><option value="4b">April</option>
				   <option value="5b">May</option><option value="6b">June</option><option value="7b">July</option><option value="8b">August</option>
				   <option value="9b">September</option><option value="10b">October</option><option value="11b">November</option><option value="12b">December</option></optgroup>
				  </select></td>	
				<td><select name="per_12" multiple="multiple" size="26">
				  <optgroup label="&nbsp;&nbsp; i\'th year">
				   <option value="1a">January</option><option value="2a">February</option><option value="3a">March</option><option value="4a">April</option>
				   <option value="5a">May</option><option value="6a">June</option><option value="7a">July</option><option value="8a">August</option>
				   <option value="9a">September</option><option value="10a">October</option><option value="11a">November</option><option value="12a">December</option></optgroup>
				  <optgroup label="&nbsp;&nbsp; i+1\'th year">
				   <option value="1b">January</option><option value="2b">February</option><option value="3b">March</option><option value="4b">April</option>
				   <option value="5b">May</option><option value="6b">June</option><option value="7b">July</option><option value="8b">August</option>
				   <option value="9b">September</option><option value="10b">October</option><option value="11b">November</option><option value="12b">December</option></optgroup>
				  </select></td>	
			  </tr>
			</table>
			<p class="smaller">
			  <input type="reset" value="{Reset}"></input> &nbsp; &nbsp; &nbsp; &nbsp; <input type="submit" value="{Go!}"></input> &nbsp; &nbsp;
			  <input name="verbose" type="checkbox" value="verbose"></input> Check here for displaying output in verbose mode.
			</p>
			</form></body></html>';
			return;
		} //end if: echo the multi-month selector, then return early;
	
		calculate_tni($serverVars, $params_output, $params_stdBegin, $params_stdEnd, $periods, $verbose);
	} //end else: $params_output!='intro', so go with calculations;
?>
<table align="center" width="100%" cellpadding="0" cellspacing="0" border="0">
  <tr>
    <td class="mono_left" style="color: dimgray; font-size: 0.8em; line-height: 1.2em; white-space: nowrap;" valign="bottom" width="50%"><br />
	  This site makes use of data made available<br />&nbsp;by the following agencies:<br />
	  &nbsp;<span id="noaa" style="font-size: 0.9em; font-weight: 700;" onMouseOver="lowLightMild('noaa');" onMouseOut="unLowLightMild('noaa');"
	    onclick="openWindow('http://www.noaa.gov','noaa',0.85,0.85);"
	   >National Oceanic &amp; Atmospheric Administration</span><br />
	  &nbsp;<span id="ncep" style="font-size: 0.9em; font-weight: 700;" onMouseOver="lowLightMild('ncep');" onMouseOut="unLowLightMild('ncep');"
	    onclick="openWindow('http://www.ncep.noaa.gov','noaa',0.85,0.85);"
	   >National Centers for Environmental Prediction</span><br />
	  &nbsp;<span id="nwrfc" style="font-size: 0.9em; font-weight: 700;" onMouseOver="lowLightMild('nwrfc');" onMouseOut="unLowLightMild('nwrfc');"
	    onclick="openWindow('http://www.nwrfc.noaa.gov','noaa',0.85,0.85);"
	   >Northwest River Forecast Center</span></td>
	<td class="smallest" width="40%" style="font-size: 0.5em;" valign="bottom">
	  <a href="http://www.orionimaging.com" style="color: white;">Art-full, Earth-friendly Gifts, &amp; More...</a></td>
    <td class="mono_left" style="color: dimgray; font-size: 0.8em; line-height: 1.2em; white-space: nowrap;" valign="bottom"><br />
	  &nbsp;&nbsp;<span id="Adam" style="font-size: 0.9em; font-weight: 700; letter-spacing: 0.15em;" onMouseOver="lowLightMild('Adam');" onMouseOut="unLowLightMild('Adam');"
	    onclick="location.href='http://westernclimate.com';"
	   >Authored by Adam Kennedy</span><br />
	  &nbsp;<span id="OI" style="font-size: 0.9em; font-weight: 400;" onMouseOver="lowLightMild('OI');" onMouseOut="unLowLightMild('OI');"
	    onclick="openWindow('http://www.orionimaging.com','OI',0.85,0.85);"
	   >Site Development by Orion Imaging</span></td></tr>
  <tr>
    <td colspan="3"><hr size="1" style="color: steelblue;" /></td></tr>
	<!-- Start of StatCounter Code -->
<script type="text/javascript" language="javascript">
var sc_project=1645932; 
var sc_invisible=1; 
var sc_partition=15; 
var sc_security="61d8d440"; 
</script>

<script type="text/javascript" language="javascript" src="http://www.statcounter.com/counter/counter.js"></script><noscript><a href="http://www.statcounter.com/" target="_blank"><img  src="http://c16.statcounter.com/counter.php?sc_project=1645932&java=0&security=61d8d440&invisible=1" alt="free web stats" border="0"></a> </noscript>
<!-- End of StatCounter Code -->
</table>
</body>
</html>