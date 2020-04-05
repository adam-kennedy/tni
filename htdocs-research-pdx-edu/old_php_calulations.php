<?
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Title:  php_calculations.php
// Use:  defines algorithms for calculating the trans-nino index [TNI]
// Created:  Lawrence Duncan [lawrence@orionimaging.com], 2/25/2006, for Adam Kennedy [kennaster@gmail.com]

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Title:  get_tni_data()
// Use:  gets the raw tran-nino data from ncep site via curl
// In:  $serverVars are environment vars, $params_output is type of output to display [straight-up text if == 'rawText']
// Out:  nice TNI data...
function get_tni_data(&$serverVars, $params_output)
{
	$dataSite = 'http://www.cpc.ncep.noaa.gov/data/indices/sstoi.indices';

	if (local($serverVars[8]))
	{ //use non-live backup data for localally-running script...
		if ($params_output=='rawText') echo '**Error: Could not initialize cURL session.'."\n".'[running locally] Using non-live backup data...'."\n\n";
		else echo error('Could not initialize cURL session.<br />[running locally]<br />Using non-live backup data...');
		return mimic_live_tni();
	} //end if: use non-live backup data for localally-running script;
	else
	{ //running live, so initiate curl...
		$ch = curl_init($dataSite); //initiate cURL w/ protocol & URL of remote host...
		
		if (!$ch)
		{ //cURL failure, so use mimicked non-live data...
			if ($params_output=='rawText') echo '**Error: Could not initialize cURL session.'."\n".' ['.curl_error($ch).']'."\n".' Using non-live backup data...'."\n\n";
			else echo error('Could not initialize cURL session.<br />['.curl_error($ch).']<br />Using non-live backup data...');
			return mimic_live_tni();
		} //end if: cURL failure, so use mimicked non-live data;
		  
		  curl_setopt($ch, CURLOPT_HEADER, 0); //ignore http header...
		  curl_setopt($ch, CURLOPT_REFERER, $serverVars[6]); //set referer to send...
		  curl_setopt($ch, CURLOPT_USERAGENT, $serverVars[4]); //set user agent to send...
		  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //sets response to return directly, not printing it out...
		  $data = curl_exec($ch); //trap response...
		
		if (!$data || empty($data))
		{ //cURL failure, so use mimicked non-live data...
			if ($params_output=='rawText') echo '**Error: Could not execute cURL session.'."\n".' ['.curl_error($ch).']'."\n".' Using non-live backup data...'."\n\n";
			else echo error('Could not execute cURL session.<br />['.curl_error($ch).']<br />Using non-live backup data...');
			return mimic_live_tni();
		} //end if: cURL failure, so use mimicked non-live data;
		curl_close($ch); //close cURL session...
	} //end else: running live, so initiate curl;
  return $data;
} //end function: get_tni_data();

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Title:  calculate_tni()
// Use:  makes appropriate calculations for forming the tni
// In:  $serverVars are environment vars, $params_output is type of output to display,
//		$params_stdBegin is beginning year of standardization period, $params_stdEnd is ending year of standardization period,
//		$periods is array of periods arrays for multi-month TNI average calculations
// Out:  nice calculated TNI...
//  *Note: format of raw data is:
//	  [ID (1st) line]:= "YR MON  NINO1+2   ANOM   NINO3    ANOM   NINO4    ANOM NINO3.4    ANOM          "   *Note: quotes are added by me to delimit the line...
//	  [date lines]:= "1950   1   23.49   -1.00   23.97   -1.64   27.41   -0.73   25.01   -1.50"              *Note: datum delimiters are 2 OR 3 OR 4 adjacent spaces!!...
//																											 *Note: the last line is blank!! [so go until $i<count(*)-1]...
//*Raw Dataset:~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
//  Then, build $data array of associative arrays [each element representing one line of raw data], where the associative keys are:
//	  'year', 'month', 'nino_1_2', 'anom_1_2', 'nino_3', 'anom_3', 'nino_4', 'anom_4', 'nino_3_4', 'anom_3_4'
//  Then, build $dataStatsMon array of associative arrays [each element representing one month over all years in standard period], where the associative keys are:
//	  'month', 'nino_1_2_sum', 'nino_1_2_N', 'nino_1_2_ave', 'nino_1_2_stdev', 'anom_1_2_sum', 'anom_1_2_N', 'anom_1_2_ave', 'anom_1_2_stdev',
//			    'nino_3_sum', 'nino_3_N', 'nino_3_ave', 'nino_3_stdev', 'anom_3_sum', 'anom_3_N', 'anom_3_ave', 'anom_3_stdev',
//			    'nino_4_sum', 'nino_4_N', 'nino_4_ave', 'nino_4_stdev', 'anom_4_sum', 'anom_4_N', 'anom_4_ave', 'anom_4_stdev',
//			    'nino_3_4_sum', 'nino_3_4_N', 'nino_3_4_ave', 'nino_3_4_stdev', 'anom_3_4_sum', 'anom_3_4_N', 'anom_3_4_ave', 'anom_3_4_stdev'
//  Then, build $dataStatsCum associative array [each element representing one cumulative statistic], where the associative keys are:
//	  'nino_1_2_sum', 'nino_1_2_N', 'nino_1_2_ave', 'nino_1_2_stdev', 'anom_1_2_sum', 'anom_1_2_N', 'anom_1_2_ave', 'anom_1_2_stdev',
//	   'nino_3_sum', 'nino_3_N', 'nino_3_ave', 'nino_3_stdev', 'anom_3_sum', 'anom_3_N', 'anom_3_ave', 'anom_3_stdev',
//	   'nino_4_sum', 'nino_4_N', 'nino_4_ave', 'nino_4_stdev', 'anom_4_sum', 'anom_4_N', 'anom_4_ave', 'anom_4_stdev',
//	   'nino_3_4_sum', 'nino_3_4_N', 'nino_3_4_ave', 'nino_3_4_stdev', 'anom_3_4_sum', 'anom_3_4_N', 'anom_3_4_ave', 'anom_3_4_stdev'
//*Standardization Dataset:~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
//  Then, build $dataStdStatsMon array of associative arrays [each element representing one month over all years in standard period], where the associative keys are:
//	  'month', 'nino_1_2_sum', 'nino_1_2_N', 'nino_1_2_ave', 'nino_1_2_stdev', 'anom_1_2_sum', 'anom_1_2_N', 'anom_1_2_ave', 'anom_1_2_stdev',
//			    'nino_3_sum', 'nino_3_N', 'nino_3_ave', 'nino_3_stdev', 'anom_3_sum', 'anom_3_N', 'anom_3_ave', 'anom_3_stdev',
//			    'nino_4_sum', 'nino_4_N', 'nino_4_ave', 'nino_4_stdev', 'anom_4_sum', 'anom_4_N', 'anom_4_ave', 'anom_4_stdev',
//			    'nino_3_4_sum', 'nino_3_4_N', 'nino_3_4_ave', 'nino_3_4_stdev', 'anom_3_4_sum', 'anom_3_4_N', 'anom_3_4_ave', 'anom_3_4_stdev'
//  Then, build $dataStdStatsCum associative array [each element representing one cumulative statistic over all data in standard period], where the associative keys are:
//	  'nino_1_2_sum', 'nino_1_2_N', 'nino_1_2_ave', 'nino_1_2_stdev', 'anom_1_2_sum', 'anom_1_2_N', 'anom_1_2_ave', 'anom_1_2_stdev',
//	   'nino_3_sum', 'nino_3_N', 'nino_3_ave', 'nino_3_stdev', 'anom_3_sum', 'anom_3_N', 'anom_3_ave', 'anom_3_stdev',
//	   'nino_4_sum', 'nino_4_N', 'nino_4_ave', 'nino_4_stdev', 'anom_4_sum', 'anom_4_N', 'anom_4_ave', 'anom_4_stdev',
//	   'nino_3_4_sum', 'nino_3_4_N', 'nino_3_4_ave', 'nino_3_4_stdev', 'anom_3_4_sum', 'anom_3_4_N', 'anom_3_4_ave', 'anom_3_4_stdev'
//*Normalized Dataset:~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
//  Then, build $dataNorm array of associative arrays [each element representing one line of raw data], where the associative keys are:
//	  'year', 'month', 'nino_1_2', 'anom_1_2', 'nino_3', 'anom_3', 'nino_4', 'anom_4', 'nino_3_4', 'anom_3_4'
//  Then, build $dataNormStatsMon array of associative arrays [each element representing one month over all years in standard period], where the associative keys are:
//	  'month', 'nino_1_2_sum', 'nino_1_2_N', 'nino_1_2_ave', 'nino_1_2_stdev', 'anom_1_2_sum', 'anom_1_2_N', 'anom_1_2_ave', 'anom_1_2_stdev',
//			    'nino_3_sum', 'nino_3_N', 'nino_3_ave', 'nino_3_stdev', 'anom_3_sum', 'anom_3_N', 'anom_3_ave', 'anom_3_stdev',
//			    'nino_4_sum', 'nino_4_N', 'nino_4_ave', 'nino_4_stdev', 'anom_4_sum', 'anom_4_N', 'anom_4_ave', 'anom_4_stdev',
//			    'nino_3_4_sum', 'nino_3_4_N', 'nino_3_4_ave', 'nino_3_4_stdev', 'anom_3_4_sum', 'anom_3_4_N', 'anom_3_4_ave', 'anom_3_4_stdev'
//  Then, build $dataNormStatsCum associative array [each element representing one cumulative statistic], where the associative keys are:
//	  'nino_1_2_sum', 'nino_1_2_N', 'nino_1_2_ave', 'nino_1_2_stdev', 'anom_1_2_sum', 'anom_1_2_N', 'anom_1_2_ave', 'anom_1_2_stdev',
//	   'nino_3_sum', 'nino_3_N', 'nino_3_ave', 'nino_3_stdev', 'anom_3_sum', 'anom_3_N', 'anom_3_ave', 'anom_3_stdev',
//	   'nino_4_sum', 'nino_4_N', 'nino_4_ave', 'nino_4_stdev', 'anom_4_sum', 'anom_4_N', 'anom_4_ave', 'anom_4_stdev',
//	   'nino_3_4_sum', 'nino_3_4_N', 'nino_3_4_ave', 'nino_3_4_stdev', 'anom_3_4_sum', 'anom_3_4_N', 'anom_3_4_ave', 'anom_3_4_stdev'
function calculate_tni(&$serverVars, $params_output, $params_stdBegin, $params_stdEnd, &$periods, $verbose='')
{
	//ini_set('error_reporting', E_ALL); //**debug [for reporting errors]...
	//ini_set('display_errors', On); //**debug [for reporting errors]...

   //first, do some parameter error checking...
	if ($verbose=='verbose') $verbose = true;  else $verbose = false; //optional arg for outputting multi-month tni in verbose mode...
	$error = ''; //initialize...
	if ($params_stdBegin>=$params_stdEnd) //check for standardization year begin/end mismatch...
	 $error = 'Invalid beginning and/or ending standardizaion years:<br />&nbsp; Begin Year == '.$params_stdBegin.'<br />&nbsp; End Year == '.$params_stdEnd.'<br />&nbsp;';
	
	if (!empty($error)) die(error($error.'<br />&nbsp;<br /><span id="beginAgain" style="font-weight: 700;"
	 onmouseover="lowLightMild(\'beginAgain\');" onmouseout="unLowLightMild(\'beginAgain\');" onclick="location.href=\'index.php\';">[Begin Again...]</span><br />&nbsp;'));

   //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Begin: initialize...
	$data = array();  $dataStatsMon = array();  $dataStdStatsMon = array();  $dataNorm = array();  $dataNormStatsMon = array();  
	$dataStatsCum = array('nino_1_2_sum'=>0, 'nino_1_2_N'=>0, 'nino_1_2_ave'=>0, 'nino_1_2_stdev'=>0,
						  'nino_3_sum'=>0, 'nino_3_N'=>0, 'nino_3_ave'=>0, 'nino_3_stdev'=>0,
						  'nino_4_sum'=>0, 'nino_4_N'=>0, 'nino_4_ave'=>0, 'nino_4_stdev'=>0,
						  'nino_3_4_sum'=>0, 'nino_3_4_N'=>0, 'nino_3_4_ave'=>0, 'nino_3_4_stdev'=>0,
						  'anom_1_2_sum'=>0, 'anom_1_2_N'=>0, 'anom_1_2_ave'=>0, 'anom_1_2_stdev'=>0,
						  'anom_3_sum'=>0, 'anom_3_N'=>0, 'anom_3_ave'=>0, 'anom_3_stdev'=>0,
						  'anom_4_sum'=>0, 'anom_4_N'=>0, 'anom_4_ave'=>0, 'anom_4_stdev'=>0,
						  'anom_3_4_sum'=>0, 'anom_3_4_N'=>0, 'anom_3_4_ave'=>0, 'anom_3_4_stdev'=>0);
	$dataStdStatsCum = array('nino_1_2_sum'=>0, 'nino_1_2_N'=>0, 'nino_1_2_ave'=>0, 'nino_1_2_stdev'=>0,
							 'nino_3_sum'=>0, 'nino_3_N'=>0, 'nino_3_ave'=>0, 'nino_3_stdev'=>0,
							 'nino_4_sum'=>0, 'nino_4_N'=>0, 'nino_4_ave'=>0, 'nino_4_stdev'=>0,
							 'nino_3_4_sum'=>0, 'nino_3_4_N'=>0, 'nino_3_4_ave'=>0, 'nino_3_4_stdev'=>0,
							 'anom_1_2_sum'=>0, 'anom_1_2_N'=>0, 'anom_1_2_ave'=>0, 'anom_1_2_stdev'=>0,
							 'anom_3_sum'=>0, 'anom_3_N'=>0, 'anom_3_ave'=>0, 'anom_3_stdev'=>0,
							 'anom_4_sum'=>0, 'anom_4_N'=>0, 'anom_4_ave'=>0, 'anom_4_stdev'=>0,
							 'anom_3_4_sum'=>0, 'anom_3_4_N'=>0, 'anom_3_4_ave'=>0, 'anom_3_4_stdev'=>0);
	$dataNormStatsCum = array('nino_1_2_sum'=>0, 'nino_1_2_N'=>0, 'nino_1_2_ave'=>0, 'nino_1_2_stdev'=>0,
							  'nino_3_sum'=>0, 'nino_3_N'=>0, 'nino_3_ave'=>0, 'nino_3_stdev'=>0,
							  'nino_4_sum'=>0, 'nino_4_N'=>0, 'nino_4_ave'=>0, 'nino_4_stdev'=>0,
							  'nino_3_4_sum'=>0, 'nino_3_4_N'=>0, 'nino_3_4_ave'=>0, 'nino_3_4_stdev'=>0,
							  'anom_1_2_sum'=>0, 'anom_1_2_N'=>0, 'anom_1_2_ave'=>0, 'anom_1_2_stdev'=>0,
							  'anom_3_sum'=>0, 'anom_3_N'=>0, 'anom_3_ave'=>0, 'anom_3_stdev'=>0,
							  'anom_4_sum'=>0, 'anom_4_N'=>0, 'anom_4_ave'=>0, 'anom_4_stdev'=>0,
							  'anom_3_4_sum'=>0, 'anom_3_4_N'=>0, 'anom_3_4_ave'=>0, 'anom_3_4_stdev'=>0);

	
	for ($i=0; $i<12; ++$i)
	{ //put in initial zero values for $dataStatsMon array of arrays, and for $dataStdStatsMon array of arrays...
		$dataStatsMon[$i]['month']=0;
		$dataStatsMon[$i]['nino_1_2_sum']=0;  $dataStatsMon[$i]['nino_1_2_N']=0;  $dataStatsMon[$i]['nino_1_2_ave']=0;  $dataStatsMon[$i]['nino_1_2_stdev']=0;
		$dataStatsMon[$i]['anom_1_2_sum']=0;  $dataStatsMon[$i]['anom_1_2_N']=0;  $dataStatsMon[$i]['anom_1_2_ave']=0;  $dataStatsMon[$i]['anom_1_2_stdev']=0;
		
		$dataStatsMon[$i]['nino_3_sum']=0;  $dataStatsMon[$i]['nino_3_N']=0;  $dataStatsMon[$i]['nino_3_ave']=0;  $dataStatsMon[$i]['nino_3_stdev']=0;
		$dataStatsMon[$i]['anom_3_sum']=0;  $dataStatsMon[$i]['anom_3_N']=0;  $dataStatsMon[$i]['anom_3_ave']=0;  $dataStatsMon[$i]['anom_3_stdev']=0;
		
		$dataStatsMon[$i]['nino_4_sum']=0;  $dataStatsMon[$i]['nino_4_N']=0;  $dataStatsMon[$i]['nino_4_ave']=0;  $dataStatsMon[$i]['nino_4_stdev']=0;
		$dataStatsMon[$i]['anom_4_sum']=0;  $dataStatsMon[$i]['anom_4_N']=0;  $dataStatsMon[$i]['anom_4_ave']=0;  $dataStatsMon[$i]['anom_4_stdev']=0;

		$dataStatsMon[$i]['nino_3_4_sum']=0;  $dataStatsMon[$i]['nino_3_4_N']=0;  $dataStatsMon[$i]['nino_3_4_ave']=0;  $dataStatsMon[$i]['nino_3_4_stdev']=0;
		$dataStatsMon[$i]['anom_3_4_sum']=0;  $dataStatsMon[$i]['anom_3_4_N']=0;  $dataStatsMon[$i]['anom_3_4_ave']=0;  $dataStatsMon[$i]['anom_3_4_stdev']=0;

		$dataStdStatsMon[$i]['month']=0;
		$dataStdStatsMon[$i]['nino_1_2_sum']=0;  $dataStdStatsMon[$i]['nino_1_2_N']=0;  $dataStdStatsMon[$i]['nino_1_2_ave']=0;  $dataStdStatsMon[$i]['nino_1_2_stdev']=0;
		$dataStdStatsMon[$i]['anom_1_2_sum']=0;  $dataStdStatsMon[$i]['anom_1_2_N']=0;  $dataStdStatsMon[$i]['anom_1_2_ave']=0;  $dataStdStatsMon[$i]['anom_1_2_stdev']=0;
		
		$dataStdStatsMon[$i]['nino_3_sum']=0;  $dataStdStatsMon[$i]['nino_3_N']=0;  $dataStdStatsMon[$i]['nino_3_ave']=0;  $dataStdStatsMon[$i]['nino_3_stdev']=0;
		$dataStdStatsMon[$i]['anom_3_sum']=0;  $dataStdStatsMon[$i]['anom_3_N']=0;  $dataStdStatsMon[$i]['anom_3_ave']=0;  $dataStdStatsMon[$i]['anom_3_stdev']=0;
		
		$dataStdStatsMon[$i]['nino_4_sum']=0;  $dataStdStatsMon[$i]['nino_4_N']=0;  $dataStdStatsMon[$i]['nino_4_ave']=0;  $dataStdStatsMon[$i]['nino_4_stdev']=0;
		$dataStdStatsMon[$i]['anom_4_sum']=0;  $dataStdStatsMon[$i]['anom_4_N']=0;  $dataStdStatsMon[$i]['anom_4_ave']=0;  $dataStdStatsMon[$i]['anom_4_stdev']=0;

		$dataStdStatsMon[$i]['nino_3_4_sum']=0;  $dataStdStatsMon[$i]['nino_3_4_N']=0;  $dataStdStatsMon[$i]['nino_3_4_ave']=0;  $dataStdStatsMon[$i]['nino_3_4_stdev']=0;
		$dataStdStatsMon[$i]['anom_3_4_sum']=0;  $dataStdStatsMon[$i]['anom_3_4_N']=0;  $dataStdStatsMon[$i]['anom_3_4_ave']=0;  $dataStdStatsMon[$i]['anom_3_4_stdev']=0;

		$dataNormStatsMon[$i]['month']=0;
		$dataNormStatsMon[$i]['nino_1_2_sum']=0;  $dataNormStatsMon[$i]['nino_1_2_N']=0;  $dataNormStatsMon[$i]['nino_1_2_ave']=0;  $dataNormStatsMon[$i]['nino_1_2_stdev']=0;
		$dataNormStatsMon[$i]['anom_1_2_sum']=0;  $dataNormStatsMon[$i]['anom_1_2_N']=0;  $dataNormStatsMon[$i]['anom_1_2_ave']=0;  $dataNormStatsMon[$i]['anom_1_2_stdev']=0;
		
		$dataNormStatsMon[$i]['nino_3_sum']=0;  $dataNormStatsMon[$i]['nino_3_N']=0;  $dataNormStatsMon[$i]['nino_3_ave']=0;  $dataNormStatsMon[$i]['nino_3_stdev']=0;
		$dataNormStatsMon[$i]['anom_3_sum']=0;  $dataNormStatsMon[$i]['anom_3_N']=0;  $dataNormStatsMon[$i]['anom_3_ave']=0;  $dataNormStatsMon[$i]['anom_3_stdev']=0;
		
		$dataNormStatsMon[$i]['nino_4_sum']=0;  $dataNormStatsMon[$i]['nino_4_N']=0;  $dataNormStatsMon[$i]['nino_4_ave']=0;  $dataNormStatsMon[$i]['nino_4_stdev']=0;
		$dataNormStatsMon[$i]['anom_4_sum']=0;  $dataNormStatsMon[$i]['anom_4_N']=0;  $dataNormStatsMon[$i]['anom_4_ave']=0;  $dataNormStatsMon[$i]['anom_4_stdev']=0;

		$dataNormStatsMon[$i]['nino_3_4_sum']=0;  $dataNormStatsMon[$i]['nino_3_4_N']=0;  $dataNormStatsMon[$i]['nino_3_4_ave']=0;  $dataNormStatsMon[$i]['nino_3_4_stdev']=0;
		$dataNormStatsMon[$i]['anom_3_4_sum']=0;  $dataNormStatsMon[$i]['anom_3_4_N']=0;  $dataNormStatsMon[$i]['anom_3_4_ave']=0;  $dataNormStatsMon[$i]['anom_3_4_stdev']=0;
	} //end for: put in initial zero values for $dataStatsMon array of arrays, and for $dataStdStatsMon array of arrays;
   //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ End: initialize...

	$data = explode("\n", get_tni_data($serverVars, $params_output)); //get the data & form into an array [exploded about the newline]...
	
   //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Begin: calculations...
	for ($i=1; $i<count($data)-1; ++$i)
	{ //replace each line of the $data array with an associative array, then build $dataStatsMon as you go [*remember $data[0] is column labels]...
		$data[$i] = str_replace('    ', ';', $data[$i]);  $data[$i] = str_replace('   ', ';', $data[$i]); //replace conjoined spaces w/ a semi-colon [for later explosion]...
		$data[$i] = str_replace('  ', ';', $data[$i]);  $data[$i] = str_replace(' ', ';', $data[$i]);
		//echo '$i == '.$i.' &nbsp; &nbsp; &nbsp; $data[$i] == '.$data[$i].'<br />'; //**debug...
		
		$dataTemp = array();  $dataTemp = explode(';', $data[$i]);
		$data[$i] = array('year'=>$dataTemp[0], 'month'=>$dataTemp[1], 'nino_1_2'=>$dataTemp[2], 'anom_1_2'=>$dataTemp[3], 'nino_3'=>$dataTemp[4],
						  'anom_3'=>$dataTemp[5], 'nino_4'=>$dataTemp[6], 'anom_4'=>$dataTemp[7], 'nino_3_4'=>$dataTemp[8], 'anom_3_4'=>$dataTemp[9]);
		
	   //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Begin: error checking...
		if ($data[$i]['year']<1950 || $data[$i]['year']>date('Y')) //make sure year value is in bounds...
		 die(error('Invalid raw data...<br />year == '.$data[$i]['year'].', raw data line # '.($i+1)));
		elseif ($data[$i]['month']<1 || $data[$i]['month']>12) //make sure month value is in bounds...
		 die(error('Invalid raw data...<br />month == '.$data[$i]['month'].' for year == '.$data[$i]['year'].', raw data line # '.($i+1)));
	   //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ End: error checking...

	   //keep running sums & total N's for calculation of monthly means...
		$dataStatsMon[$data[$i]['month']-1]['month'] = $data[$i]['month'];
		$dataStatsMon[$data[$i]['month']-1]['nino_1_2_sum'] += $data[$i]['nino_1_2'];  ++$dataStatsMon[$data[$i]['month']-1]['nino_1_2_N'];
		$dataStatsMon[$data[$i]['month']-1]['nino_3_sum'] += $data[$i]['nino_3'];  ++$dataStatsMon[$data[$i]['month']-1]['nino_3_N'];
		$dataStatsMon[$data[$i]['month']-1]['nino_4_sum'] += $data[$i]['nino_4'];  ++$dataStatsMon[$data[$i]['month']-1]['nino_4_N'];
		$dataStatsMon[$data[$i]['month']-1]['nino_3_4_sum'] += $data[$i]['nino_3_4'];  ++$dataStatsMon[$data[$i]['month']-1]['nino_3_4_N'];

		$dataStatsCum['nino_1_2_sum'] += $data[$i]['nino_1_2'];  ++$dataStatsCum['nino_1_2_N'];
		$dataStatsCum['nino_3_sum'] += $data[$i]['nino_3'];  ++$dataStatsCum['nino_3_N'];
		$dataStatsCum['nino_4_sum'] += $data[$i]['nino_4'];  ++$dataStatsCum['nino_4_N'];
		$dataStatsCum['nino_3_4_sum'] += $data[$i]['nino_3_4'];  ++$dataStatsCum['nino_3_4_N'];

		if ($params_output=='raw')
		{ //only do this if building raw dataset...
			$dataStatsMon[$data[$i]['month']-1]['anom_1_2_sum'] += $data[$i]['anom_1_2'];  ++$dataStatsMon[$data[$i]['month']-1]['anom_1_2_N'];
			$dataStatsMon[$data[$i]['month']-1]['anom_3_sum'] += $data[$i]['anom_3'];  ++$dataStatsMon[$data[$i]['month']-1]['anom_3_N'];
			$dataStatsMon[$data[$i]['month']-1]['anom_4_sum'] += $data[$i]['anom_4'];  ++$dataStatsMon[$data[$i]['month']-1]['anom_4_N'];
			$dataStatsMon[$data[$i]['month']-1]['anom_3_4_sum'] += $data[$i]['anom_3_4'];  ++$dataStatsMon[$data[$i]['month']-1]['anom_3_4_N'];

			$dataStatsCum['anom_1_2_sum'] += $data[$i]['anom_1_2'];  ++$dataStatsCum['anom_1_2_N'];
			$dataStatsCum['anom_3_sum'] += $data[$i]['anom_3'];  ++$dataStatsCum['anom_3_N'];
			$dataStatsCum['anom_4_sum'] += $data[$i]['anom_4'];  ++$dataStatsCum['anom_4_N'];
			$dataStatsCum['anom_3_4_sum'] += $data[$i]['anom_3_4'];  ++$dataStatsCum['anom_3_4_N'];
		} //end if: only do this if building raw dataset;
	} //end for: replace each line of the $data array with an associative array, then build $dataStatsMon as you go;
	
	unset($data[count($data)-1]); //delete the last dataLine, since it is empty...
	unset($dataTemp); //free some memory...
	
   //form monthly means of entire dataset...
	for ($i=0; $i<12; ++$i)
	{ //calculate & insert averages by month...
		//echo '$nino_1_2_totalN == '.$nino_1_2_totalN.' &nbsp; &nbsp; &nbsp; $nino_4_totalN == '.$nino_4_totalN.'<br />'; //**debug...
		$dataStatsMon[$i]['nino_1_2_ave'] = $dataStatsMon[$i]['nino_1_2_sum'] / $dataStatsMon[$i]['nino_1_2_N'];
		$dataStatsMon[$i]['nino_3_ave'] = $dataStatsMon[$i]['nino_3_sum'] / $dataStatsMon[$i]['nino_3_N'];
		$dataStatsMon[$i]['nino_4_ave'] = $dataStatsMon[$i]['nino_4_sum'] / $dataStatsMon[$i]['nino_4_N'];
		$dataStatsMon[$i]['nino_3_4_ave'] = $dataStatsMon[$i]['nino_3_4_sum'] / $dataStatsMon[$i]['nino_3_4_N'];
		if ($params_output=='raw')
		{ //only calculate anom stat's if building raw dataset...
			$dataStatsMon[$i]['anom_1_2_ave'] = $dataStatsMon[$i]['anom_1_2_sum'] / $dataStatsMon[$i]['anom_1_2_N'];
			$dataStatsMon[$i]['anom_3_ave'] = $dataStatsMon[$i]['anom_3_sum'] / $dataStatsMon[$i]['anom_3_N'];
			$dataStatsMon[$i]['anom_4_ave'] = $dataStatsMon[$i]['anom_4_sum'] / $dataStatsMon[$i]['anom_4_N'];
			$dataStatsMon[$i]['anom_3_4_ave'] = $dataStatsMon[$i]['anom_3_4_sum'] / $dataStatsMon[$i]['anom_3_4_N'];
		} //end if: only calculate anom stat's if building raw dataset;
	} //end for: calculate & insert averages by month;

   //form cumulative means of entire dataset...
	$dataStatsCum['nino_1_2_ave'] = $dataStatsCum['nino_1_2_sum'] / $dataStatsCum['nino_1_2_N'];
	$dataStatsCum['nino_3_ave'] = $dataStatsCum['nino_3_sum'] / $dataStatsCum['nino_3_N'];
	$dataStatsCum['nino_4_ave'] = $dataStatsCum['nino_4_sum'] / $dataStatsCum['nino_4_N'];
	$dataStatsCum['nino_3_4_ave'] = $dataStatsCum['nino_3_4_sum'] / $dataStatsCum['nino_3_4_N'];
	if ($params_output=='raw')
	{ //only calculate anom stat's if building raw dataset...
		$dataStatsCum['anom_3_4_ave'] = $dataStatsCum['anom_3_4_sum'] / $dataStatsCum['anom_3_4_N'];
		$dataStatsCum['anom_4_ave'] = $dataStatsCum['anom_4_sum'] / $dataStatsCum['anom_4_N'];
		$dataStatsCum['anom_3_ave'] = $dataStatsCum['anom_3_sum'] / $dataStatsCum['anom_3_N'];
		$dataStatsCum['anom_1_2_ave'] = $dataStatsCum['anom_1_2_sum'] / $dataStatsCum['anom_1_2_N'];
	} //only calculate anom stat's if building raw dataset;

   //form monthly & cumulative standard deviations of entire dataset...  *Note:  s := sqrt( sum((x_i - x_ave)^2) / (n-1) )
	for ($i=1; $i<count($data); ++$i)
	{ //form partial monthly & totalStdev's over all data rows [*remember $data[0] is column labels]...
		$dataStatsMon[$data[$i]['month']-1]['nino_1_2_stdev'] += pow($data[$i]['nino_1_2'] - $dataStatsMon[$data[$i]['month']-1]['nino_1_2_ave'], 2);
		$dataStatsMon[$data[$i]['month']-1]['nino_3_stdev'] += pow($data[$i]['nino_3'] - $dataStatsMon[$data[$i]['month']-1]['nino_3_ave'], 2);
		$dataStatsMon[$data[$i]['month']-1]['nino_4_stdev'] += pow($data[$i]['nino_4'] - $dataStatsMon[$data[$i]['month']-1]['nino_4_ave'], 2);
		$dataStatsMon[$data[$i]['month']-1]['nino_3_4_stdev'] += pow($data[$i]['nino_3_4'] - $dataStatsMon[$data[$i]['month']-1]['nino_3_4_ave'], 2);
		if ($params_output=='raw')
		{ //only calculate anom stat's if building raw dataset...
			$dataStatsMon[$data[$i]['month']-1]['anom_1_2_stdev'] += pow($data[$i]['anom_1_2'] - $dataStatsMon[$data[$i]['month']-1]['anom_1_2_ave'], 2);
			$dataStatsMon[$data[$i]['month']-1]['anom_3_stdev'] += pow($data[$i]['anom_3'] - $dataStatsMon[$data[$i]['month']-1]['anom_3_ave'], 2);
			$dataStatsMon[$data[$i]['month']-1]['anom_4_stdev'] += pow($data[$i]['anom_4'] - $dataStatsMon[$data[$i]['month']-1]['anom_4_ave'], 2);
			$dataStatsMon[$data[$i]['month']-1]['anom_3_4_stdev'] += pow($data[$i]['anom_3_4'] - $dataStatsMon[$data[$i]['month']-1]['anom_3_4_ave'], 2);
		} //end if: only calculate anom stat's if building raw dataset;

		$dataStatsCum['nino_1_2_stdev'] += pow($data[$i]['nino_1_2'] - $dataStatsCum['nino_1_2_ave'], 2);
		$dataStatsCum['nino_3_stdev'] += pow($data[$i]['nino_3'] - $dataStatsCum['nino_3_ave'], 2);
		$dataStatsCum['nino_4_stdev'] += pow($data[$i]['nino_4'] - $dataStatsCum['nino_4_ave'], 2);
		$dataStatsCum['nino_3_4_stdev'] += pow($data[$i]['nino_3_4'] - $dataStatsCum['nino_3_4_ave'], 2);
		if ($params_output=='raw')
		{ //only calculate anom stat's if building raw dataset...
			$dataStatsCum['anom_1_2_stdev'] += pow($data[$i]['anom_1_2'] - $dataStatsCum['anom_1_2_ave'], 2);
			$dataStatsCum['anom_3_stdev'] += pow($data[$i]['anom_3'] - $dataStatsCum['anom_3_ave'], 2);
			$dataStatsCum['anom_4_stdev'] += pow($data[$i]['anom_4'] - $dataStatsCum['anom_4_ave'], 2);
			$dataStatsCum['anom_3_4_stdev'] += pow($data[$i]['anom_3_4'] - $dataStatsCum['anom_3_4_ave'], 2);
		} //end if: only calculate anom stat's if building raw dataset;
	} //end for: form partial monthly & totalStdev's over all data rows;

	for ($i=0; $i<12; ++$i)
	{ //finish forming monthly stdev's of entire dataset...
		$dataStatsMon[$i]['nino_1_2_stdev'] = pow($dataStatsMon[$i]['nino_1_2_stdev'] / ($dataStatsMon[$i]['nino_1_2_N']-1), 0.5);
		$dataStatsMon[$i]['nino_3_stdev'] = pow($dataStatsMon[$i]['nino_3_stdev'] / ($dataStatsMon[$i]['nino_3_N']-1), 0.5);
		$dataStatsMon[$i]['nino_4_stdev'] = pow($dataStatsMon[$i]['nino_4_stdev'] / ($dataStatsMon[$i]['nino_4_N']-1), 0.5);
		$dataStatsMon[$i]['nino_3_4_stdev'] = pow($dataStatsMon[$i]['nino_3_4_stdev'] / ($dataStatsMon[$i]['nino_3_4_N']-1), 0.5);
		if ($params_output=='raw')
		{ //only calculate anom stat's if building raw dataset...
			$dataStatsMon[$i]['anom_1_2_stdev'] = pow($dataStatsMon[$i]['anom_1_2_stdev'] / ($dataStatsMon[$i]['anom_1_2_N']-1), 0.5);
			$dataStatsMon[$i]['anom_3_stdev'] = pow($dataStatsMon[$i]['anom_3_stdev'] / ($dataStatsMon[$i]['anom_3_N']-1), 0.5);
			$dataStatsMon[$i]['anom_4_stdev'] = pow($dataStatsMon[$i]['anom_4_stdev'] / ($dataStatsMon[$i]['anom_4_N']-1), 0.5);
			$dataStatsMon[$i]['anom_3_4_stdev'] = pow($dataStatsMon[$i]['anom_3_4_stdev'] / ($dataStatsMon[$i]['anom_3_4_N']-1), 0.5);
		} //end if: only calculate anom stat's if building raw dataset;
	} //end for: finish forming monthly stdev's of entire dataset;

	$dataStatsCum['nino_1_2_stdev'] = pow($dataStatsCum['nino_1_2_stdev'] / ($dataStatsCum['nino_1_2_N']-1), 0.5); //finish forming cumulative stdev's...
	$dataStatsCum['nino_3_stdev'] = pow($dataStatsCum['nino_3_stdev'] / ($dataStatsCum['nino_3_N']-1), 0.5);
	$dataStatsCum['nino_4_stdev'] = pow($dataStatsCum['nino_4_stdev'] / ($dataStatsCum['nino_4_N']-1), 0.5);
	$dataStatsCum['nino_3_4_stdev'] = pow($dataStatsCum['nino_3_4_stdev'] / ($dataStatsCum['nino_3_4_N']-1), 0.5);
	if ($params_output=='raw')
	{ //only calculate anom stat's if building raw dataset...
		$dataStatsCum['anom_1_2_stdev'] = pow($dataStatsCum['anom_1_2_stdev'] / ($dataStatsCum['anom_1_2_N']-1), 0.5);
		$dataStatsCum['anom_3_stdev'] = pow($dataStatsCum['anom_3_stdev'] / ($dataStatsCum['anom_3_N']-1), 0.5);
		$dataStatsCum['anom_4_stdev'] = pow($dataStatsCum['anom_4_stdev'] / ($dataStatsCum['anom_4_N']-1), 0.5);
		$dataStatsCum['anom_3_4_stdev'] = pow($dataStatsCum['anom_3_4_stdev'] / ($dataStatsCum['anom_3_4_N']-1), 0.5);
	} //only calculate anom stat's if building raw dataset;

   //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Begin: form standardized data set...
	if ($params_output!='raw')
	{ //you only need to do this if you're not outputting the raw data...
	   //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Begin: calculate anom's for desired standard period & start standardization...
		for ($i=1; $i<count($data); ++$i)
		{ //reform monthly & cumulative stats for desired period of standardization...
			if ($data[$i]['year']>$params_stdBegin-1 && $data[$i]['year']<$params_stdEnd+1)
			{ //only do this if data are within the chosen period of standardization...
				$dataStdStatsMon[$data[$i]['month']-1]['month'] = $data[$i]['month'];
				$dataStdStatsMon[$data[$i]['month']-1]['nino_1_2_sum'] += $data[$i]['nino_1_2'];  ++$dataStdStatsMon[$data[$i]['month']-1]['nino_1_2_N'];
				$dataStdStatsMon[$data[$i]['month']-1]['nino_3_sum'] += $data[$i]['nino_3'];  ++$dataStdStatsMon[$data[$i]['month']-1]['nino_3_N'];
				$dataStdStatsMon[$data[$i]['month']-1]['nino_4_sum'] += $data[$i]['nino_4'];  ++$dataStdStatsMon[$data[$i]['month']-1]['nino_4_N'];
				$dataStdStatsMon[$data[$i]['month']-1]['nino_3_4_sum'] += $data[$i]['nino_3_4'];  ++$dataStdStatsMon[$data[$i]['month']-1]['nino_3_4_N'];
			} //end if: only do this if data are within the chosen period of standardization;
		} //end for: reform monthly & cumulative stats for desired period of standardization;

	   //form monthly means of desired period of standardization...
		for ($i=0; $i<12; ++$i)
		{ //calculate & insert averages by month for desired period of standardization [wait for anom's]...
			$dataStdStatsMon[$i]['nino_1_2_ave'] = $dataStdStatsMon[$i]['nino_1_2_sum'] / $dataStdStatsMon[$i]['nino_1_2_N'];
			$dataStdStatsMon[$i]['nino_3_ave'] = $dataStdStatsMon[$i]['nino_3_sum'] / $dataStdStatsMon[$i]['nino_3_N'];
			$dataStdStatsMon[$i]['nino_4_ave'] = $dataStdStatsMon[$i]['nino_4_sum'] / $dataStdStatsMon[$i]['nino_4_N'];
			$dataStdStatsMon[$i]['nino_3_4_ave'] = $dataStdStatsMon[$i]['nino_3_4_sum'] / $dataStdStatsMon[$i]['nino_3_4_N'];
			//die('<p>$dataStatsMon[$i][\'nino_1_2_ave\'] == '.$dataStatsMon[$i]['nino_1_2_ave'].' &nbsp; &nbsp; 
			//		$dataStdStatsMon[$i][\'nino_1_2_ave\'] == '.$dataStdStatsMon[$i]['nino_1_2_ave']); //**debug...
		} //end for: calculate & insert averages by month for desired period of standardization [wait for anom's];

		for ($i=1; $i<count($data); ++$i)
		{ //form our own anom's for all rows of data, based on desired period of standardization, also keep running sums & N's over entire dataset...
			$data[$i]['anom_1_2'] = $data[$i]['nino_1_2'] - $dataStdStatsMon[$data[$i]['month']-1]['nino_1_2_ave'];
			$data[$i]['anom_3'] = $data[$i]['nino_3'] - $dataStdStatsMon[$data[$i]['month']-1]['nino_3_ave'];
			$data[$i]['anom_4'] = $data[$i]['nino_4'] - $dataStdStatsMon[$data[$i]['month']-1]['nino_4_ave'];
			$data[$i]['anom_3_4'] = $data[$i]['nino_3_4'] - $dataStdStatsMon[$data[$i]['month']-1]['nino_3_4_ave'];
			//echo '<br />$dataStdStatsMon[month=='.($data[$i]['month']-1).'][\'nino_1_2_ave\'] == '.$dataStdStatsMon[$data[$i]['month']-1]['nino_1_2_ave'];
			//echo ('<br />$data[year=='.$data[$i]['year'].'][month=='.$data[$i]['month'].'][\'anom_1_2\'] == '.$data[$i]['anom_1_2']); //**debug...

			$dataStatsMon[$data[$i]['month']-1]['anom_1_2_sum'] += $data[$i]['anom_1_2'];  ++$dataStatsMon[$data[$i]['month']-1]['anom_1_2_N'];
			$dataStatsMon[$data[$i]['month']-1]['anom_3_sum'] += $data[$i]['anom_3'];  ++$dataStatsMon[$data[$i]['month']-1]['anom_3_N'];
			$dataStatsMon[$data[$i]['month']-1]['anom_4_sum'] += $data[$i]['anom_4'];  ++$dataStatsMon[$data[$i]['month']-1]['anom_4_N'];
			$dataStatsMon[$data[$i]['month']-1]['anom_3_4_sum'] += $data[$i]['anom_3_4'];  ++$dataStatsMon[$data[$i]['month']-1]['anom_3_4_N'];

			$dataStatsCum['anom_1_2_sum'] += $data[$i]['anom_1_2'];  ++$dataStatsCum['anom_1_2_N'];
			$dataStatsCum['anom_3_sum'] += $data[$i]['anom_3'];  ++$dataStatsCum['anom_3_N'];
			$dataStatsCum['anom_4_sum'] += $data[$i]['anom_4'];  ++$dataStatsCum['anom_4_N'];
			$dataStatsCum['anom_3_4_sum'] += $data[$i]['anom_3_4'];  ++$dataStatsCum['anom_3_4_N'];

			if ($data[$i]['year']>$params_stdBegin-1 && $data[$i]['year']<$params_stdEnd+1)
			{ //only do this if data are within the chosen period of standardization...
				$dataStdStatsMon[$data[$i]['month']-1]['anom_1_2_sum'] += $data[$i]['anom_1_2'];  ++$dataStdStatsMon[$data[$i]['month']-1]['anom_1_2_N'];
				$dataStdStatsMon[$data[$i]['month']-1]['anom_3_sum'] += $data[$i]['anom_3'];  ++$dataStdStatsMon[$data[$i]['month']-1]['anom_3_N'];
				$dataStdStatsMon[$data[$i]['month']-1]['anom_4_sum'] += $data[$i]['anom_4'];  ++$dataStdStatsMon[$data[$i]['month']-1]['anom_4_N'];
				$dataStdStatsMon[$data[$i]['month']-1]['anom_3_4_sum'] += $data[$i]['anom_3_4'];  ++$dataStdStatsMon[$data[$i]['month']-1]['anom_3_4_N'];

				$dataStdStatsCum['nino_1_2_sum'] += $data[$i]['nino_1_2'];  ++$dataStdStatsCum['nino_1_2_N'];
				$dataStdStatsCum['nino_3_sum'] += $data[$i]['nino_3'];  ++$dataStdStatsCum['nino_3_N'];
				$dataStdStatsCum['nino_4_sum'] += $data[$i]['nino_4'];  ++$dataStdStatsCum['nino_4_N'];
				$dataStdStatsCum['nino_3_4_sum'] += $data[$i]['nino_3_4'];  ++$dataStdStatsCum['nino_3_4_N'];

				$dataStdStatsCum['anom_1_2_sum'] += $data[$i]['anom_1_2'];  ++$dataStdStatsCum['anom_1_2_N'];
				$dataStdStatsCum['anom_3_sum'] += $data[$i]['anom_3'];  ++$dataStdStatsCum['anom_3_N'];
				$dataStdStatsCum['anom_4_sum'] += $data[$i]['anom_4'];  ++$dataStdStatsCum['anom_4_N'];
				$dataStdStatsCum['anom_3_4_sum'] += $data[$i]['anom_3_4'];  ++$dataStdStatsCum['anom_3_4_N'];
			} //end if: only do this if data are within the chosen period of standardization;
		} //end for: form our own anom's for all rows of data, based on desired period of standardization, also keep running sums & N's over entire dataset;
	   //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ End: calculate anom's for desired standard period & start standardization...

	   //form monthly anom means of desired period of standardization...
		for ($i=0; $i<12; ++$i)
		{ //calculate & insert anom averages by month for desired period of standardization, also calc monthly anom avg's for entire dataset...
			$dataStatsMon[$i]['anom_1_2_ave'] = $dataStatsMon[$i]['anom_1_2_sum'] / $dataStatsMon[$i]['anom_1_2_N'];
			$dataStatsMon[$i]['anom_3_ave'] = $dataStatsMon[$i]['anom_3_sum'] / $dataStatsMon[$i]['anom_3_N'];
			$dataStatsMon[$i]['anom_4_ave'] = $dataStatsMon[$i]['anom_4_sum'] / $dataStatsMon[$i]['anom_4_N'];
			$dataStatsMon[$i]['anom_3_4_ave'] = $dataStatsMon[$i]['anom_3_4_sum'] / $dataStatsMon[$i]['anom_3_4_N'];

			$dataStdStatsMon[$i]['anom_1_2_ave'] = $dataStdStatsMon[$i]['anom_1_2_sum'] / $dataStdStatsMon[$i]['anom_1_2_N'];
			$dataStdStatsMon[$i]['anom_3_ave'] = $dataStdStatsMon[$i]['anom_3_sum'] / $dataStdStatsMon[$i]['anom_3_N'];
			$dataStdStatsMon[$i]['anom_4_ave'] = $dataStdStatsMon[$i]['anom_4_sum'] / $dataStdStatsMon[$i]['anom_4_N'];
			$dataStdStatsMon[$i]['anom_3_4_ave'] = $dataStdStatsMon[$i]['anom_3_4_sum'] / $dataStdStatsMon[$i]['anom_3_4_N'];
		} //end for: calculate & insert anom averages by month for desired period of standardization, also calc monthly anom avg's for entire dataset;
	
	   //form cumulative means of desired period of standardization, also calc anom avg's for entire dataset...
		$dataStatsCum['anom_1_2_ave'] = $dataStatsCum['anom_1_2_sum'] / $dataStatsCum['anom_1_2_N'];
		$dataStatsCum['anom_3_ave'] = $dataStatsCum['anom_3_sum'] / $dataStatsCum['anom_3_N'];
		$dataStatsCum['anom_4_ave'] = $dataStatsCum['anom_4_sum'] / $dataStatsCum['anom_4_N'];
		$dataStatsCum['anom_3_4_ave'] = $dataStatsCum['anom_3_4_sum'] / $dataStatsCum['anom_3_4_N'];

		$dataStdStatsCum['nino_1_2_ave'] = $dataStdStatsCum['nino_1_2_sum'] / $dataStdStatsCum['nino_1_2_N'];
		 $dataStdStatsCum['anom_1_2_ave'] = $dataStdStatsCum['anom_1_2_sum'] / $dataStdStatsCum['anom_1_2_N'];
		$dataStdStatsCum['nino_3_ave'] = $dataStdStatsCum['nino_3_sum'] / $dataStdStatsCum['nino_3_N'];
		 $dataStdStatsCum['anom_3_ave'] = $dataStdStatsCum['anom_3_sum'] / $dataStdStatsCum['anom_3_N'];
		$dataStdStatsCum['nino_4_ave'] = $dataStdStatsCum['nino_4_sum'] / $dataStdStatsCum['nino_4_N'];
		 $dataStdStatsCum['anom_4_ave'] = $dataStdStatsCum['anom_4_sum'] / $dataStdStatsCum['anom_4_N'];
		$dataStdStatsCum['nino_3_4_ave'] = $dataStdStatsCum['nino_3_4_sum'] / $dataStdStatsCum['nino_3_4_N'];
		 $dataStdStatsCum['anom_3_4_ave'] = $dataStdStatsCum['anom_3_4_sum'] / $dataStdStatsCum['anom_3_4_N'];
	
	   //form monthly stdev's of desired period of standardization...  *Note:  s := sqrt( sum((x_i - x_ave)^2) / (n-1) )
		for ($i=1; $i<count($data); ++$i)
		{ //form partial totalStdev's over each month, desired period of standardization, also calc monthly anom stdev's for entire dataset...
			$dataStatsMon[$data[$i]['month']-1]['anom_1_2_stdev'] += pow($data[$i]['anom_1_2'] - $dataStatsMon[$data[$i]['month']-1]['anom_1_2_ave'], 2);
			$dataStatsMon[$data[$i]['month']-1]['anom_3_stdev'] += pow($data[$i]['anom_3'] - $dataStatsMon[$data[$i]['month']-1]['anom_3_ave'], 2);
			$dataStatsMon[$data[$i]['month']-1]['anom_4_stdev'] += pow($data[$i]['anom_4'] - $dataStatsMon[$data[$i]['month']-1]['anom_4_ave'], 2);
			$dataStatsMon[$data[$i]['month']-1]['anom_3_4_stdev'] += pow($data[$i]['anom_3_4'] - $dataStatsMon[$data[$i]['month']-1]['anom_3_4_ave'], 2);

			if ($data[$i]['year']>$params_stdBegin-1 && $data[$i]['year']<$params_stdEnd+1)
			{ //only do this if data are within the chosen period of standardization...
				$dataStdStatsMon[$data[$i]['month']-1]['nino_1_2_stdev'] += pow($data[$i]['nino_1_2'] - $dataStdStatsMon[$data[$i]['month']-1]['nino_1_2_ave'], 2);
				 $dataStdStatsMon[$data[$i]['month']-1]['anom_1_2_stdev'] += pow($data[$i]['anom_1_2'] - $dataStdStatsMon[$data[$i]['month']-1]['anom_1_2_ave'], 2);
				$dataStdStatsMon[$data[$i]['month']-1]['nino_3_stdev'] += pow($data[$i]['nino_3'] - $dataStdStatsMon[$data[$i]['month']-1]['nino_3_ave'], 2);
				 $dataStdStatsMon[$data[$i]['month']-1]['anom_3_stdev'] += pow($data[$i]['anom_3'] - $dataStdStatsMon[$data[$i]['month']-1]['anom_3_ave'], 2);
				$dataStdStatsMon[$data[$i]['month']-1]['nino_4_stdev'] += pow($data[$i]['nino_4'] - $dataStdStatsMon[$data[$i]['month']-1]['nino_4_ave'], 2);
				 $dataStdStatsMon[$data[$i]['month']-1]['anom_4_stdev'] += pow($data[$i]['anom_4'] - $dataStdStatsMon[$data[$i]['month']-1]['anom_4_ave'], 2);
				$dataStdStatsMon[$data[$i]['month']-1]['nino_3_4_stdev'] += pow($data[$i]['nino_3_4'] - $dataStdStatsMon[$data[$i]['month']-1]['nino_3_4_ave'], 2);
				 $dataStdStatsMon[$data[$i]['month']-1]['anom_3_4_stdev'] += pow($data[$i]['anom_3_4'] - $dataStdStatsMon[$data[$i]['month']-1]['anom_3_4_ave'], 2);
			} //only do this if data are within the chosen period of standardization;
		} //end for: form partial totalStdev's over each month, desired period of standardization, also calc monthly anom stdev's for entire dataset;
		for ($i=0; $i<12; ++$i)
		{ //finish forming monthly stdev's of desired period of standardization, also calc monthly anom stdev's for entire dataset...
			$dataStatsMon[$i]['anom_1_2_stdev'] = pow($dataStatsMon[$i]['anom_1_2_stdev'] / ($dataStatsMon[$i]['anom_1_2_N']-1), 0.5);
			$dataStatsMon[$i]['anom_3_stdev'] = pow($dataStatsMon[$i]['anom_3_stdev'] / ($dataStatsMon[$i]['anom_3_N']-1), 0.5);
			$dataStatsMon[$i]['anom_4_stdev'] = pow($dataStatsMon[$i]['anom_4_stdev'] / ($dataStatsMon[$i]['anom_4_N']-1), 0.5);
			$dataStatsMon[$i]['anom_3_4_stdev'] = pow($dataStatsMon[$i]['anom_3_4_stdev'] / ($dataStatsMon[$i]['anom_3_4_N']-1), 0.5);

			$dataStdStatsMon[$i]['nino_1_2_stdev'] = pow($dataStdStatsMon[$i]['nino_1_2_stdev'] / ($dataStdStatsMon[$i]['nino_1_2_N']-1), 0.5);
			 $dataStdStatsMon[$i]['anom_1_2_stdev'] = pow($dataStdStatsMon[$i]['anom_1_2_stdev'] / ($dataStdStatsMon[$i]['anom_1_2_N']-1), 0.5);
			$dataStdStatsMon[$i]['nino_3_stdev'] = pow($dataStdStatsMon[$i]['nino_3_stdev'] / ($dataStdStatsMon[$i]['nino_3_N']-1), 0.5);
			 $dataStdStatsMon[$i]['anom_3_stdev'] = pow($dataStdStatsMon[$i]['anom_3_stdev'] / ($dataStdStatsMon[$i]['anom_3_N']-1), 0.5);
			$dataStdStatsMon[$i]['nino_4_stdev'] = pow($dataStdStatsMon[$i]['nino_4_stdev'] / ($dataStdStatsMon[$i]['nino_4_N']-1), 0.5);
			 $dataStdStatsMon[$i]['anom_4_stdev'] = pow($dataStdStatsMon[$i]['anom_4_stdev'] / ($dataStdStatsMon[$i]['anom_4_N']-1), 0.5);
			$dataStdStatsMon[$i]['nino_3_4_stdev'] = pow($dataStdStatsMon[$i]['nino_3_4_stdev'] / ($dataStdStatsMon[$i]['nino_3_4_N']-1), 0.5);
			 $dataStdStatsMon[$i]['anom_3_4_stdev'] = pow($dataStdStatsMon[$i]['anom_3_4_stdev'] / ($dataStdStatsMon[$i]['anom_3_4_N']-1), 0.5);
		} //end for: finish forming monthly stdev's of desired period of standardization, also calc monthly anom stdev's for entire dataset;
	
	   //form cumulative standard deviations of desired period of standardization...  *Note:  s := sqrt( sum((x_i - x_ave)^2) / (n-1) )
		for ($i=1; $i<count($data); ++$i)
		{ //form partial totalStdev's over desired period of standardization, also calc anom stdev's for entire dataset [*remember $data[0] is column labels]...
			$dataStatsCum['nino_1_2_stdev'] += pow($data[$i]['nino_1_2'] - $dataStatsCum['nino_1_2_ave'], 2);
			 $dataStatsCum['anom_1_2_stdev'] += pow($data[$i]['anom_1_2'] - $dataStatsCum['anom_1_2_ave'], 2);
			$dataStatsCum['nino_3_stdev'] += pow($data[$i]['nino_3'] - $dataStatsCum['nino_3_ave'], 2);
			 $dataStatsCum['anom_3_stdev'] += pow($data[$i]['anom_3'] - $dataStatsCum['anom_3_ave'], 2);
			$dataStatsCum['nino_4_stdev'] += pow($data[$i]['nino_4'] - $dataStatsCum['nino_4_ave'], 2);
			 $dataStatsCum['anom_4_stdev'] += pow($data[$i]['anom_4'] - $dataStatsCum['anom_4_ave'], 2);
			$dataStatsCum['nino_3_4_stdev'] += pow($data[$i]['nino_3_4'] - $dataStatsCum['nino_3_4_ave'], 2);
			 $dataStatsCum['anom_3_4_stdev'] += pow($data[$i]['anom_3_4'] - $dataStatsCum['anom_3_4_ave'], 2);

			if ($data[$i]['year']>$params_stdBegin-1 && $data[$i]['year']<$params_stdEnd+1)
			{ //only do this if data are within the chosen period of standardization...
				$dataStdStatsCum['nino_1_2_stdev'] += pow($data[$i]['nino_1_2'] - $dataStdStatsCum['nino_1_2_ave'], 2);
				 $dataStdStatsCum['anom_1_2_stdev'] += pow($data[$i]['anom_1_2'] - $dataStdStatsCum['anom_1_2_ave'], 2);
				$dataStdStatsCum['nino_3_stdev'] += pow($data[$i]['nino_3'] - $dataStdStatsCum['nino_3_ave'], 2);
				 $dataStdStatsCum['anom_3_stdev'] += pow($data[$i]['anom_3'] - $dataStdStatsCum['anom_3_ave'], 2);
				$dataStdStatsCum['nino_4_stdev'] += pow($data[$i]['nino_4'] - $dataStdStatsCum['nino_4_ave'], 2);
				 $dataStdStatsCum['anom_4_stdev'] += pow($data[$i]['anom_4'] - $dataStdStatsCum['anom_4_ave'], 2);
				$dataStdStatsCum['nino_3_4_stdev'] += pow($data[$i]['nino_3_4'] - $dataStdStatsCum['nino_3_4_ave'], 2);
				 $dataStdStatsCum['anom_3_4_stdev'] += pow($data[$i]['anom_3_4'] - $dataStdStatsCum['anom_3_4_ave'], 2);
			} //end if: only do this if data are within the chosen period of standardization;
		} //end for: form partial totalStdev's over desired period of standardization, also calc anom stdev's for entire dataset;
		
		$dataStatsCum['nino_1_2_stdev'] = pow($dataStatsCum['nino_1_2_stdev'] / ($dataStatsCum['nino_1_2_N']-1), 0.5); //finish forming cumulative stdev's...
		 $dataStatsCum['anom_1_2_stdev'] = pow($dataStatsCum['anom_1_2_stdev'] / ($dataStatsCum['anom_1_2_N']-1), 0.5);
		$dataStatsCum['nino_3_stdev'] = pow($dataStatsCum['nino_3_stdev'] / ($dataStatsCum['nino_3_N']-1), 0.5);
		 $dataStatsCum['anom_3_stdev'] = pow($dataStatsCum['anom_3_stdev'] / ($dataStatsCum['anom_3_N']-1), 0.5);
		$dataStatsCum['nino_4_stdev'] = pow($dataStatsCum['nino_4_stdev'] / ($dataStatsCum['nino_4_N']-1), 0.5);
		 $dataStatsCum['anom_4_stdev'] = pow($dataStatsCum['anom_4_stdev'] / ($dataStatsCum['anom_4_N']-1), 0.5);
		$dataStatsCum['nino_3_4_stdev'] = pow($dataStatsCum['nino_3_4_stdev'] / ($dataStatsCum['nino_3_4_N']-1), 0.5);
		 $dataStatsCum['anom_3_4_stdev'] = pow($dataStatsCum['anom_3_4_stdev'] / ($dataStatsCum['anom_3_4_N']-1), 0.5);

		$dataStdStatsCum['nino_1_2_stdev'] = pow($dataStdStatsCum['nino_1_2_stdev'] / ($dataStdStatsCum['nino_1_2_N']-1), 0.5); //finish forming cumulative stdev's...
		 $dataStdStatsCum['anom_1_2_stdev'] = pow($dataStdStatsCum['anom_1_2_stdev'] / ($dataStdStatsCum['anom_1_2_N']-1), 0.5);
		$dataStdStatsCum['nino_3_stdev'] = pow($dataStdStatsCum['nino_3_stdev'] / ($dataStdStatsCum['nino_3_N']-1), 0.5);
		 $dataStdStatsCum['anom_3_stdev'] = pow($dataStdStatsCum['anom_3_stdev'] / ($dataStdStatsCum['anom_3_N']-1), 0.5);
		$dataStdStatsCum['nino_4_stdev'] = pow($dataStdStatsCum['nino_4_stdev'] / ($dataStdStatsCum['nino_4_N']-1), 0.5);
		 $dataStdStatsCum['anom_4_stdev'] = pow($dataStdStatsCum['anom_4_stdev'] / ($dataStdStatsCum['anom_4_N']-1), 0.5);
		$dataStdStatsCum['nino_3_4_stdev'] = pow($dataStdStatsCum['nino_3_4_stdev'] / ($dataStdStatsCum['nino_3_4_N']-1), 0.5);
		 $dataStdStatsCum['anom_3_4_stdev'] = pow($dataStdStatsCum['anom_3_4_stdev'] / ($dataStdStatsCum['anom_3_4_N']-1), 0.5);
		
		//die('$dataStatsMon[0][\'nino_1_2_ave\'] == '.$dataStatsMon[0]['nino_1_2_ave']); //**debug...
		//die('$dataStdStatsCum[\'nino_1_2_stdev\'] == '.$dataStdStatsCum['nino_1_2_stdev']); //**debug...
		//die('$dataStdStatsCum[\'anom_1_2_N\'] == '.$dataStdStatsCum['anom_1_2_N']); //**debug...
		//die('$dataStdStatsCum[\'anom_1_2_ave\'] == '.$dataStdStatsCum['anom_1_2_ave']); //**debug...
		//die('$dataStdStatsCum[\'anom_1_2_stdev\'] == '.$dataStdStatsCum['anom_1_2_stdev']); //**debug...

   //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ End: form standardized data set...

   //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Begin: form normalized data set...

		//die('<p>$dataStdStatsCum[\'anom_1_2_stdev\'] == '.$dataStdStatsCum['anom_1_2_stdev']); //**debug...
		for ($i=1; $i<count($data); ++$i)
		{ //normalize each $data datum with standardization values thereby creating $dataNorm array of arrays [*remember $data[0] is column labels]...
			for ($j=0; $j<12; ++$j)
			{ //find appropriate monthly mean...
				if ($dataStatsMon[$j]['month']==$data[$i]['month'])
				{ //found appropriate monthly average, so calculate standardized value & break out...
					$dataNorm[$i-1] = array('year' => $data[$i]['year'], 'month' => $data[$i]['month'],
										    'nino_1_2' => ($data[$i]['nino_1_2'] - $dataStatsMon[$j]['nino_1_2_ave']) / $dataStdStatsCum['anom_1_2_stdev'],
										    'nino_3' => ($data[$i]['nino_3'] - $dataStatsMon[$j]['nino_3_ave']) / $dataStdStatsCum['anom_3_stdev'],
										    'nino_4' => ($data[$i]['nino_4'] - $dataStatsMon[$j]['nino_4_ave']) / $dataStdStatsCum['anom_4_stdev'],
										    'nino_3_4' => ($data[$i]['nino_3_4'] - $dataStatsMon[$j]['nino_3_4_ave']) / $dataStdStatsCum['anom_3_4_stdev']);
					//echo '$dataNorm['.($i-1).'][\'month\'] == ';  echo $dataNorm[$i-1]['month'].'<br>';  //print_r($dataStd[$i-1]);  echo '<br />'; //**debug...
					break;
				} //end if: found appropriate monthly average, so calculate standardized value & break out;
			} //end for: find appropriate monthly mean;
		} //end for: normalize each $data datum with standardization values thereby creating $dataNorm array of arrays;

		for ($i=0; $i<count($dataNorm); ++$i)
		{ //replace each line of the $data array with an associative array, then build $dataNormStatsMon as you go [**remember $dataNorm[0] is actual data**]...
		   //keep running sums & total N's for calculation of means...
			$dataNormStatsMon[$dataNorm[$i]['month']-1]['month'] = $dataNorm[$i]['month'];
			$dataNormStatsMon[$dataNorm[$i]['month']-1]['nino_1_2_sum'] += $dataNorm[$i]['nino_1_2'];  ++$dataNormStatsMon[$dataNorm[$i]['month']-1]['nino_1_2_N'];
			$dataNormStatsMon[$dataNorm[$i]['month']-1]['nino_3_sum'] += $dataNorm[$i]['nino_3'];  ++$dataNormStatsMon[$dataNorm[$i]['month']-1]['nino_3_N'];
			$dataNormStatsMon[$dataNorm[$i]['month']-1]['nino_4_sum'] += $dataNorm[$i]['nino_4'];  ++$dataNormStatsMon[$dataNorm[$i]['month']-1]['nino_4_N'];
			$dataNormStatsMon[$dataNorm[$i]['month']-1]['nino_3_4_sum'] += $dataNorm[$i]['nino_3_4'];  ++$dataNormStatsMon[$dataNorm[$i]['month']-1]['nino_3_4_N'];
	
			$dataNormStatsCum['nino_1_2_sum'] += $dataNorm[$i]['nino_1_2'];  ++$dataNormStatsCum['nino_1_2_N'];
			$dataNormStatsCum['nino_3_sum'] += $dataNorm[$i]['nino_3'];  ++$dataNormStatsCum['nino_3_N'];
			$dataNormStatsCum['nino_4_sum'] += $dataNorm[$i]['nino_4'];  ++$dataNormStatsCum['nino_4_N'];
			$dataNormStatsCum['nino_3_4_sum'] += $dataNorm[$i]['nino_3_4'];  ++$dataNormStatsCum['nino_3_4_N'];
	
			/*$dataNormStatsMon[$dataNorm[$i]['month']-1]['anom_1_2_sum'] += $dataNorm[$i]['anom_1_2'];  ++$dataNormStatsMon[$data[$i]['month']-1]['anom_1_2_N'];
			$dataNormStatsMon[$dataNorm[$i]['month']-1]['anom_3_sum'] += $dataNorm[$i]['anom_3'];  ++$dataNormStatsMon[$data[$i]['month']-1]['anom_3_N'];
			$dataNormStatsMon[$dataNorm[$i]['month']-1]['anom_4_sum'] += $dataNorm[$i]['anom_4'];  ++$dataNormStatsMon[$data[$i]['month']-1]['anom_4_N'];
			$dataNormStatsMon[$dataNorm[$i]['month']-1]['anom_3_4_sum'] += $dataNorm[$i]['anom_3_4'];  ++$dataNormStatsMon[$data[$i]['month']-1]['anom_3_4_N'];

			$dataNormStatsCum['anom_1_2_sum'] += $dataNorm[$i]['anom_1_2'];  ++$dataNormStatsCum['anom_1_2_N'];
			$dataNormStatsCum['anom_3_sum'] += $dataNorm[$i]['anom_3'];  ++$dataNormStatsCum['anom_3_N'];
			$dataNormStatsCum['anom_4_sum'] += $dataNorm[$i]['anom_4'];  ++$dataNormStatsCum['anom_4_N'];
			$dataNormStatsCum['anom_3_4_sum'] += $dataNorm[$i]['anom_3_4'];  ++$dataNormStatsCum['anom_3_4_N'];*/
		} //end for: replace each line of the $data array with an associative array, then build $dataStatsMon as you go;
		
	   //form monthly means of entire dataset...
		for ($i=0; $i<12; ++$i)
		{ //calculate & insert averages by month...
			//echo '$nino_1_2_totalN == '.$nino_1_2_totalN.' &nbsp; &nbsp; &nbsp; $nino_4_totalN == '.$nino_4_totalN.'<br />'; //**debug...
			$dataNormStatsMon[$i]['nino_1_2_ave'] = $dataNormStatsMon[$i]['nino_1_2_sum'] / $dataNormStatsMon[$i]['nino_1_2_N'];
			$dataNormStatsMon[$i]['nino_3_ave'] = $dataNormStatsMon[$i]['nino_3_sum'] / $dataNormStatsMon[$i]['nino_3_N'];
			$dataNormStatsMon[$i]['nino_4_ave'] = $dataNormStatsMon[$i]['nino_4_sum'] / $dataNormStatsMon[$i]['nino_4_N'];
			$dataNormStatsMon[$i]['nino_3_4_ave'] = $dataNormStatsMon[$i]['nino_3_4_sum'] / $dataNormStatsMon[$i]['nino_3_4_N'];

			/*$dataNormStatsMon[$i]['anom_1_2_ave'] = $dataNormStatsMon[$i]['anom_1_2_sum'] / $dataNormStatsMon[$i]['anom_1_2_N'];
			$dataNormStatsMon[$i]['anom_3_ave'] = $dataNormStatsMon[$i]['anom_3_sum'] / $dataNormStatsMon[$i]['anom_3_N'];
			$dataNormStatsMon[$i]['anom_4_ave'] = $dataNormStatsMon[$i]['anom_4_sum'] / $dataNormStatsMon[$i]['anom_4_N'];
			$dataNormStatsMon[$i]['anom_3_4_ave'] = $dataNormStatsMon[$i]['anom_3_4_sum'] / $dataNormStatsMon[$i]['anom_3_4_N'];*/
		} //end for: calculate & insert averages by month;
	
	   //form cumulative means of entire dataset...
		$dataNormStatsCum['nino_1_2'] = $dataNormStatsCum['nino_1_2_sum'] / $dataNormStatsCum['nino_1_2_N'];
		$dataNormStatsCum['nino_3'] = $dataNormStatsCum['nino_3_sum'] / $dataNormStatsCum['nino_3_N'];
		$dataNormStatsCum['nino_4'] = $dataNormStatsCum['nino_4_sum'] / $dataNormStatsCum['nino_4_N'];
		$dataNormStatsCum['nino_3_4'] = $dataNormStatsCum['nino_3_4_sum'] / $dataNormStatsCum['nino_3_4_N'];

		/*$dataNormStatsCum['anom_3_4'] = $dataNormStatsCum['anom_3_4_sum'] / $dataNormStatsCum['anom_3_4_N'];
		$dataNormStatsCum['anom_4'] = $dataNormStatsCum['anom_4_sum'] / $dataNormStatsCum['anom_4_N'];
		$dataNormStatsCum['anom_3'] = $dataNormStatsCum['anom_3_sum'] / $dataNormStatsCum['anom_3_N'];
		$dataNormStatsCum['anom_1_2'] = $dataNormStatsCum['anom_1_2_sum'] / $dataNormStatsCum['anom_1_2_N'];*/
	
	   //form monthly stdev's of entire dataset...  *Note:  s := sqrt( sum((x_i - x_ave)^2) / (n-1) )
		for ($i=0; $i<count($dataNorm); ++$i)
		{ //form partial totalStdev's over each month, all data rows...
			//echo '$data[$i+1][\'month\']-1 == '.($data[$i+1]['month']-1).'<br>'; //**debug...
			//echo '$dataNorm[$i][\'month\']-1 == '.($dataNorm[$i]['month']-1).'<br>'; //**debug...
			$dataNormStatsMon[$dataNorm[$i]['month']-1]['nino_1_2_stdev'] += pow($dataNorm[$i]['nino_1_2'] - $dataNormStatsMon[$dataNorm[$i]['month']-1]['nino_1_2_ave'], 2);
			$dataNormStatsMon[$dataNorm[$i]['month']-1]['nino_3_stdev'] += pow($dataNorm[$i]['nino_3'] - $dataNormStatsMon[$dataNorm[$i]['month']-1]['nino_3_ave'], 2);
			$dataNormStatsMon[$dataNorm[$i]['month']-1]['nino_4_stdev'] += pow($dataNorm[$i]['nino_4'] - $dataNormStatsMon[$dataNorm[$i]['month']-1]['nino_4_ave'], 2);
			$dataNormStatsMon[$dataNorm[$i]['month']-1]['nino_3_4_stdev'] += pow($dataNorm[$i]['nino_3_4'] - $dataNormStatsMon[$dataNorm[$i]['month']-1]['nino_3_4_ave'], 2);

			/*$dataNormStatsMon[$i]['anom_1_2_stdev'] += pow($dataNormStatsMon[$i]['anom_1_2'] - $dataNormStatsMon[$i]['anom_1_2_ave'], 2);
			$dataNormStatsMon[$i]['anom_3_stdev'] += pow($dataNormStatsMon[$i]['anom_3'] - $dataNormStatsMon[$i]['anom_3_ave'], 2);
			$dataNormStatsMon[$i]['anom_4_stdev'] += pow($dataNormStatsMon[$i]['anom_4'] - $dataNormStatsMon[$i]['anom_4_ave'], 2);
			$dataNormStatsMon[$i]['anom_3_4_stdev'] += pow($dataNormStatsMon[$i]['anom_3_4'] - $dataNormStatsMon[$i]['anom_3_4_ave'], 2);*/

			$dataNormStatsCum['nino_1_2_stdev'] += pow($dataNorm[$i]['nino_1_2'] - $dataNormStatsCum['nino_1_2_ave'], 2);
			$dataNormStatsCum['nino_3_stdev'] += pow($dataNorm[$i]['nino_3'] - $dataNormStatsCum['nino_3_ave'], 2);
			$dataNormStatsCum['nino_4_stdev'] += pow($dataNorm[$i]['nino_4'] - $dataNormStatsCum['nino_4_ave'], 2);
			$dataNormStatsCum['nino_3_4_stdev'] += pow($dataNorm[$i]['nino_3_4'] - $dataNormStatsCum['nino_3_4_ave'], 2);

			/*$dataNormStatsCum['anom_1_2_stdev'] += pow($dataNorm[$i]['anom_1_2'] - $dataNormStatsCum['anom_1_2_ave'], 2);
			$dataNormStatsCum['anom_3_stdev'] += pow($dataNorm[$i]['anom_3'] - $dataNormStatsCum['anom_3_ave'], 2);
			$dataNormStatsCum['anom_4_stdev'] += pow($dataNorm[$i]['anom_4'] - $dataNormStatsCum['anom_4_ave'], 2);
			$dataNormStatsCum['anom_3_4_stdev'] += pow($dataNorm[$i]['anom_3_4'] - $dataNormStatsCum['anom_3_4_ave'], 2);*/
		} //end for: form partial totalStdev's over each month, all data rows;

		for ($i=0; $i<12; ++$i)
		{ //finish forming monthly stdev's of entire dataset...
			$dataNormStatsMon[$i]['nino_1_2_stdev'] = pow($dataNormStatsMon[$i]['nino_1_2_stdev'] / ($dataNormStatsMon[$i]['nino_1_2_N']-1), 0.5);
			$dataNormStatsMon[$i]['nino_3_stdev'] = pow($dataNormStatsMon[$i]['nino_3_stdev'] / ($dataNormStatsMon[$i]['nino_3_N']-1), 0.5);
			$dataNormStatsMon[$i]['nino_4_stdev'] = pow($dataNormStatsMon[$i]['nino_4_stdev'] / ($dataNormStatsMon[$i]['nino_4_N']-1), 0.5);
			$dataNormStatsMon[$i]['nino_3_4_stdev'] = pow($dataNormStatsMon[$i]['nino_3_4_stdev'] / ($dataNormStatsMon[$i]['nino_3_4_N']-1), 0.5);

			/*$dataNormStatsMon[$i]['anom_1_2_stdev'] = pow($dataNormStatsMon[$i]['anom_1_2_stdev'] / ($dataNormStatsMon[$i]['anom_1_2_N']-1), 0.5);
			$dataNormStatsMon[$i]['anom_3_stdev'] = pow($dataNormStatsMon[$i]['anom_3_stdev'] / ($dataNormStatsMon[$i]['anom_3_N']-1), 0.5);
			$dataNormStatsMon[$i]['anom_4_stdev'] = pow($dataNormStatsMon[$i]['anom_4_stdev'] / ($dataNormStatsMon[$i]['anom_4_N']-1), 0.5);
			$dataNormStatsMon[$i]['anom_3_4_stdev'] = pow($dataNormStatsMon[$i]['anom_3_4_stdev'] / ($dataNormStatsMon[$i]['anom_3_4_N']-1), 0.5);*/
		} //end for: finish forming monthly stdev's of entire dataset;
	
		$dataNormStatsCum['nino_1_2_stdev'] = pow($dataNormStatsCum['nino_1_2_stdev'] / ($dataNormStatsCum['nino_1_2_N']-1), 0.5); //finish forming cumulative stdev's...
		$dataNormStatsCum['nino_3_stdev'] = pow($dataNormStatsCum['nino_3_stdev'] / ($dataNormStatsCum['nino_3_N']-1), 0.5);
		$dataNormStatsCum['nino_4_stdev'] = pow($dataNormStatsCum['nino_4_stdev'] / ($dataNormStatsCum['nino_4_N']-1), 0.5);
		$dataNormStatsCum['nino_3_4_stdev'] = pow($dataNormStatsCum['nino_3_4_stdev'] / ($dataNormStatsCum['nino_3_4_N']-1), 0.5);

		/*$dataNormStatsCum['anom_1_2_stdev'] = pow($dataNormStatsCum['anom_1_2_stdev'] / ($dataNormStatsCum['anom_1_2_N']-1), 0.5);
		$dataNormStatsCum['anom_3_stdev'] = pow($dataNormStatsCum['anom_3_stdev'] / ($dataNormStatsCum['anom_3_N']-1), 0.5);
		$dataNormStatsCum['anom_4_stdev'] = pow($dataNormStatsCum['anom_4_stdev'] / ($dataNormStatsCum['anom_4_N']-1), 0.5);
		$dataNormStatsCum['anom_3_4_stdev'] = pow($dataNormStatsCum['anom_3_4_stdev'] / ($dataNormStatsCum['anom_3_4_N']-1), 0.5);*/
	} //end if: you only need to do this if you're not outputting the raw data;

   //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ End: form normalized data set...

   //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Begin: form trans-nino index...

	if ($params_output=='tni' || $params_output=='tniMulti')
	   //                                     *Note:  $tni is array of associative arrays, each element being an associative array of year & months with numeric keys...
	{ //form the 'raw' trans-nino index...       eg:  $tni[0] := array('year'=>1950, 1=>tniJanuary, 2=>tniFebruary, 3=>tniMarch,... , 11=>tniNovember, 12=>tniDecember);
		$tni = array();  $rowCount = 0;  $yearCheck = $dataNorm[0]['year']; //initialize...
		for ($i=0; $i<count($dataNorm); ++$i)
		{ //form the tni over all rows of normalized dataset...
			if ($dataNorm[$i]['year']!=$yearCheck)  { $yearCheck = $dataNorm[$i]['year'];  ++$rowCount; }
			$tni[$rowCount]['year'] = $dataNorm[$i]['year'];
			$tni[$rowCount][$dataNorm[$i]['month']] = $dataNorm[$i]['nino_1_2'] - $dataNorm[$i]['nino_4'];
			//echo '<br />$dataNorm[year=='.$dataNorm[$i]['year'].'][month=='.$dataNorm[$i]['month'].'][\'nino_1_2\'] == '.$dataNorm[$i]['nino_1_2'].' &nbsp; &nbsp; 
			//			$dataNorm[year=='.$dataNorm[$i]['year'].'][month=='.$dataNorm[$i]['month'].'][\'nino_4\'] == '.$dataNorm[$i]['nino_4']; //**debug...
		} //form the tni over all rows of normalized dataset;
	} //end if: [$params_output=='tni'] form the 'raw' trans-nino index...

   //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ End: calculations...

	if ($params_output=='summary')
	{ //output summary of calculations...
		$padTot = 5;
		echo '<p class="strong" style="color: purple; font-size: 1.2em;">Summary Statistics of Ni&ntilde;o Datasets:<br />&nbsp;</p>
			  
			  <table align="center" cellpadding="0" cellspacing="0" border="0">'/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Summary Statistics~~~~~~~~~~~~~*/.'
				<tr><td colspan="4" class="strong_left"  style="white-space: nowrap;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
				 <span style="text-decoration: underline;">Raw Dataset</span>:<hr size="1" color="dimgray" /></td></tr>
				<tr>
				  <td>&nbsp;</td>
				  <td class="strong" style="font-size: 0.9em; white-space: nowrap;">';  for ($i=0; $i<12; ++$i) echo '&nbsp;';  echo 'N';  for ($i=0; $i<12; ++$i) echo '&nbsp;';  echo '</td>
				  <td class="strong" style="font-size: 0.9em; white-space: nowrap;">';  for ($i=0; $i<10; ++$i) echo '&nbsp;';  echo 'Mean';  for ($i=0; $i<10; ++$i) echo '&nbsp;';  echo '</td>
				  <td class="strong" style="font-size: 0.9em; white-space: nowrap;">';  for ($i=0; $i<10; ++$i) echo '&nbsp;';  echo 'St.Dev.';  for ($i=0; $i<10; ++$i) echo '&nbsp;';  echo '</td></tr>
				 <tr><td colspan="4" style="font-size: 0.5em;">&nbsp;</td></tr>
				<tr>
				  <td class="strong_right" style="font-size: 0.9em; white-space: nowrap;" valign="top">&nbsp;&nbsp;&nbsp;&nbsp;Ni&ntilde;o 1_2 :&nbsp;</td>
				  <td class="mono">'.$dataStatsCum['nino_1_2_N'].'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.$padTot.'.2f',round($dataStatsCum['nino_1_2_ave'],2))).'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.($padTot-2).'.2f',round($dataStatsCum['nino_1_2_stdev'],2))).'</td></tr>
				 <tr><td colspan="4" style="font-size: 0.2em;">&nbsp;</td></tr>
				<tr>
				  <td class="strong_right" style="font-size: 0.9em; white-space: nowrap;" valign="top">&nbsp;&nbsp;&nbsp;&nbsp;Anom 1_2 :&nbsp;</td>
				  <td class="mono">'.$dataStatsCum['anom_1_2_N'].'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.$padTot.'.2f',round($dataStatsCum['anom_1_2_ave'],2))).'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.($padTot-2).'.2f',round($dataStatsCum['anom_1_2_stdev'],2))).'</td></tr>
				 <tr><td colspan="4" style="font-size: 0.5em;">&nbsp;</td></tr>

				<tr>
				  <td class="strong_right" style="font-size: 0.9em; white-space: nowrap;" valign="top">&nbsp;&nbsp;&nbsp;&nbsp;Ni&ntilde;o 3 :&nbsp;</td>
				  <td class="mono">'.$dataStatsCum['nino_3_N'].'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.$padTot.'.2f',round($dataStatsCum['nino_3_ave'],2))).'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.($padTot-2).'.2f',round($dataStatsCum['nino_3_stdev'],2))).'</td></tr>
				 <tr><td colspan="4" style="font-size: 0.2em;">&nbsp;</td></tr>
				<tr>
				  <td class="strong_right" style="font-size: 0.9em; white-space: nowrap;" valign="top">&nbsp;&nbsp;&nbsp;&nbsp;Anom 3 :&nbsp;</td>
				  <td class="mono">'.$dataStatsCum['anom_3_N'].'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.$padTot.'.2f',round($dataStatsCum['anom_3_ave'],2))).'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.($padTot-2).'.2f',round($dataStatsCum['anom_3_stdev'],2))).'</td></tr>
				 <tr><td colspan="4" style="font-size: 0.5em;">&nbsp;</td></tr>

				<tr>
				  <td class="strong_right" style="font-size: 0.9em; white-space: nowrap;" valign="top">&nbsp;&nbsp;&nbsp;&nbsp;Ni&ntilde;o 4 :&nbsp;</td>
				  <td class="mono">'.$dataStatsCum['nino_4_N'].'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.$padTot.'.2f',round($dataStatsCum['nino_4_ave'],2))).'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.($padTot-2).'.2f',round($dataStatsCum['nino_4_stdev'],2))).'</td></tr>
				 <tr><td colspan="4" style="font-size: 0.2em;">&nbsp;</td></tr>
				<tr>
				  <td class="strong_right" style="font-size: 0.9em; white-space: nowrap;" valign="top">&nbsp;&nbsp;&nbsp;&nbsp;Anom 4 :&nbsp;</td>
				  <td class="mono">'.$dataStatsCum['anom_4_N'].'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.$padTot.'.2f',round($dataStatsCum['anom_4_ave'],2))).'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.($padTot-2).'.2f',round($dataStatsCum['anom_4_stdev'],2))).'</td></tr>
				 <tr><td colspan="4" style="font-size: 0.5em;">&nbsp;</td></tr>

				<tr>
				  <td class="strong_right" style="font-size: 0.9em; white-space: nowrap;" valign="top">&nbsp;&nbsp;&nbsp;&nbsp;Ni&ntilde;o 3_4 :&nbsp;</td>
				  <td class="mono">'.$dataStatsCum['nino_3_4_N'].'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.$padTot.'.2f',round($dataStatsCum['nino_3_4_ave'],2))).'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.($padTot-2).'.2f',round($dataStatsCum['nino_3_4_stdev'],2))).'</td></tr>
				 <tr><td colspan="4" style="font-size: 0.2em;">&nbsp;</td></tr>
				<tr>
				  <td class="strong_right" style="font-size: 0.9em; white-space: nowrap;" valign="top">&nbsp;&nbsp;&nbsp;&nbsp;Anom 3_4 :&nbsp;</td>
				  <td class="mono">'.$dataStatsCum['anom_3_4_N'].'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.$padTot.'.2f',round($dataStatsCum['anom_3_4_ave'],2))).'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.($padTot-2).'.2f',round($dataStatsCum['anom_3_4_stdev'],2))).'</td></tr>
				 <tr><td colspan="4" style="font-size: 0.5em;">&nbsp;</td></tr>
			   </table><br />&nbsp;

			  <table align="center" cellpadding="0" cellspacing="0" border="0">'/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Standardization Data Summary ~~~~~~~~~~~~~*/.'
				<tr><td colspan="4" class="strong_left" style="white-space: nowrap;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
				 <span style="text-decoration: underline;">Standardization Dataset</span>: &nbsp; ['.$params_stdBegin.' ~ '.$params_stdEnd.']<hr size="1" color="dimgray" /></td></tr>
				<tr>
				  <td>&nbsp;</td>
				  <td class="strong" style="font-size: 0.9em; white-space: nowrap;">';  for ($i=0; $i<12; ++$i) echo '&nbsp;';  echo 'N';  for ($i=0; $i<12; ++$i) echo '&nbsp;';  echo '</td>
				  <td class="strong" style="font-size: 0.9em; white-space: nowrap;">';  for ($i=0; $i<10; ++$i) echo '&nbsp;';  echo 'Mean';  for ($i=0; $i<10; ++$i) echo '&nbsp;';  echo '</td>
				  <td class="strong" style="font-size: 0.9em; white-space: nowrap;">';  for ($i=0; $i<10; ++$i) echo '&nbsp;';  echo 'St.Dev.';  for ($i=0; $i<10; ++$i) echo '&nbsp;';  echo '</td></tr>
				 <tr><td colspan="4" style="font-size: 0.5em;">&nbsp;</td></tr>
				<tr>
				  <td class="strong_right" style="font-size: 0.9em; white-space: nowrap;" valign="top">&nbsp;&nbsp;&nbsp;&nbsp;Ni&ntilde;o 1_2 :&nbsp;</td>
				  <td class="mono">'.$dataStdStatsCum['nino_1_2_N'].'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.$padTot.'.2f',round($dataStdStatsCum['nino_1_2_ave'],2))).'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.($padTot-2).'.2f',round($dataStdStatsCum['nino_1_2_stdev'],2))).'</td></tr>
				 <tr><td colspan="4" style="font-size: 0.2em;">&nbsp;</td></tr>
				<tr>
				  <td class="strong_right" style="font-size: 0.9em; white-space: nowrap;" valign="top">&nbsp;&nbsp;&nbsp;&nbsp;Anom 1_2 :&nbsp;</td>
				  <td class="mono">'.$dataStdStatsCum['anom_1_2_N'].'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.$padTot.'.2f',round($dataStdStatsCum['anom_1_2_ave'],2))).'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.($padTot-2).'.2f',round($dataStdStatsCum['anom_1_2_stdev'],2))).'</td></tr>
				 <tr><td colspan="4" style="font-size: 0.5em;">&nbsp;</td></tr>

				<tr>
				  <td class="strong_right" style="font-size: 0.9em; white-space: nowrap;" valign="top">&nbsp;&nbsp;&nbsp;&nbsp;Ni&ntilde;o 3 :&nbsp;</td>
				  <td class="mono">'.$dataStdStatsCum['nino_3_N'].'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.$padTot.'.2f',round($dataStdStatsCum['nino_3_ave'],2))).'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.($padTot-2).'.2f',round($dataStdStatsCum['nino_3_stdev'],2))).'</td></tr>
				 <tr><td colspan="4" style="font-size: 0.2em;">&nbsp;</td></tr>
				<tr>
				  <td class="strong_right" style="font-size: 0.9em; white-space: nowrap;" valign="top">&nbsp;&nbsp;&nbsp;&nbsp;Anom 3 :&nbsp;</td>
				  <td class="mono">'.$dataStdStatsCum['anom_3_N'].'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.$padTot.'.2f',round($dataStdStatsCum['anom_3_ave'],2))).'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.($padTot-2).'.2f',round($dataStdStatsCum['anom_3_stdev'],2))).'</td></tr>
				 <tr><td colspan="4" style="font-size: 0.5em;">&nbsp;</td></tr>

				<tr>
				  <td class="strong_right" style="font-size: 0.9em; white-space: nowrap;" valign="top">&nbsp;&nbsp;&nbsp;&nbsp;Ni&ntilde;o 4 :&nbsp;</td>
				  <td class="mono">'.$dataStdStatsCum['nino_4_N'].'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.$padTot.'.2f',round($dataStdStatsCum['nino_4_ave'],2))).'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.($padTot-2).'.2f',round($dataStdStatsCum['nino_4_stdev'],2))).'</td></tr>
				 <tr><td colspan="4" style="font-size: 0.2em;">&nbsp;</td></tr>
				<tr>
				  <td class="strong_right" style="font-size: 0.9em; white-space: nowrap;" valign="top">&nbsp;&nbsp;&nbsp;&nbsp;Anom 4 :&nbsp;</td>
				  <td class="mono">'.$dataStdStatsCum['anom_4_N'].'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.$padTot.'.2f',round($dataStdStatsCum['anom_4_ave'],2))).'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.($padTot-2).'.2f',round($dataStdStatsCum['anom_4_stdev'],2))).'</td></tr>
				 <tr><td colspan="4" style="font-size: 0.5em;">&nbsp;</td></tr>

				<tr>
				  <td class="strong_right" style="font-size: 0.9em; white-space: nowrap;" valign="top">&nbsp;&nbsp;&nbsp;&nbsp;Ni&ntilde;o 3_4 :&nbsp;</td>
				  <td class="mono">'.$dataStdStatsCum['nino_3_4_N'].'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.$padTot.'.2f',round($dataStdStatsCum['nino_3_4_ave'],2))).'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.($padTot-2).'.2f',round($dataStdStatsCum['nino_3_4_stdev'],2))).'</td></tr>
				 <tr><td colspan="4" style="font-size: 0.2em;">&nbsp;</td></tr>
				<tr>
				  <td class="strong_right" style="font-size: 0.9em; white-space: nowrap;" valign="top">&nbsp;&nbsp;&nbsp;&nbsp;Anom 3_4 :&nbsp;</td>
				  <td class="mono">'.$dataStdStatsCum['anom_3_4_N'].'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.$padTot.'.2f',round($dataStdStatsCum['anom_3_4_ave'],2))).'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.($padTot-2).'.2f',round($dataStdStatsCum['anom_3_4_stdev'],2))).'</td></tr>
				 <tr><td colspan="4" style="font-size: 0.5em;">&nbsp;</td></tr>
			   </table><br />&nbsp;

			  <table align="center" cellpadding="0" cellspacing="0" border="0">'/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Normalized Data Summary ~~~~~~~~~~~~~*/.'
				<tr><td colspan="4" class="strong_left" style="white-space: nowrap;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
				 <span style="text-decoration: underline;">Normalized Dataset</span>:<hr size="1" color="dimgray" /></td></tr>
				<tr>
				  <td>&nbsp;</td>
				  <td class="strong" style="font-size: 0.9em; white-space: nowrap;">';  for ($i=0; $i<12; ++$i) echo '&nbsp;';  echo 'N';  for ($i=0; $i<12; ++$i) echo '&nbsp;';  echo '</td>
				  <td class="strong" style="font-size: 0.9em; white-space: nowrap;">';  for ($i=0; $i<10; ++$i) echo '&nbsp;';  echo 'Mean';  for ($i=0; $i<10; ++$i) echo '&nbsp;';  echo '</td>
				  <td class="strong" style="font-size: 0.9em; white-space: nowrap;">';  for ($i=0; $i<10; ++$i) echo '&nbsp;';  echo 'St.Dev.';  for ($i=0; $i<10; ++$i) echo '&nbsp;';  echo '</td></tr>
				 <tr><td colspan="4" style="font-size: 0.5em;">&nbsp;</td></tr>
				<tr>
				  <td class="strong_right" style="font-size: 0.9em; white-space: nowrap;" valign="top">&nbsp;&nbsp;&nbsp;&nbsp;Ni&ntilde;o 1_2 :&nbsp;</td>
				  <td class="mono">'.$dataNormStatsCum['nino_1_2_N'].'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.$padTot.'.2f',round($dataNormStatsCum['nino_1_2_ave'],2))).'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.($padTot-2).'.2f',round($dataNormStatsCum['nino_1_2_stdev'],2))).'</td></tr>
				 <tr><td colspan="4" style="font-size: 0.2em;">&nbsp;</td></tr>

				<tr>
				  <td class="strong_right" style="font-size: 0.9em; white-space: nowrap;" valign="top">&nbsp;&nbsp;&nbsp;&nbsp;Ni&ntilde;o 3 :&nbsp;</td>
				  <td class="mono">'.$dataNormStatsCum['nino_3_N'].'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.$padTot.'.2f',round($dataNormStatsCum['nino_3_ave'],2))).'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.($padTot-2).'.2f',round($dataNormStatsCum['nino_3_stdev'],2))).'</td></tr>
				 <tr><td colspan="4" style="font-size: 0.2em;">&nbsp;</td></tr>

				<tr>
				  <td class="strong_right" style="font-size: 0.9em; white-space: nowrap;" valign="top">&nbsp;&nbsp;&nbsp;&nbsp;Ni&ntilde;o 4 :&nbsp;</td>
				  <td class="mono">'.$dataNormStatsCum['nino_4_N'].'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.$padTot.'.2f',round($dataNormStatsCum['nino_4_ave'],2))).'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.($padTot-2).'.2f',round($dataNormStatsCum['nino_4_stdev'],2))).'</td></tr>
				 <tr><td colspan="4" style="font-size: 0.2em;">&nbsp;</td></tr>

				<tr>
				  <td class="strong_right" style="font-size: 0.9em; white-space: nowrap;" valign="top">&nbsp;&nbsp;&nbsp;&nbsp;Ni&ntilde;o 3_4 :&nbsp;</td>
				  <td class="mono">'.$dataNormStatsCum['nino_3_4_N'].'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.$padTot.'.2f',round($dataNormStatsCum['nino_3_4_ave'],2))).'</td>
				  <td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.($padTot-2).'.2f',round($dataNormStatsCum['nino_3_4_stdev'],2))).'</td></tr>
				 <tr><td colspan="4" style="font-size: 0.2em;">&nbsp;</td></tr>
			   </table><br />&nbsp;';
	} //end if: output summary of calculations;

	elseif ($params_output=='tni')
	{ //output formulated tni data...
		$padTot = 5;  $padLabel = 13;
		echo '<p class="strong" style="color: purple; font-size: 1.2em;">Normalized Monthly Trans-Ni&ntilde;o Index:<br />&nbsp;</p>
			  
			  <table align="center" cellpadding="0" cellspacing="1" border="0">'/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Monthly Trans-Nino Index ~~~~~~~~~~~~~*/.'
				<tr>
				  <td>&nbsp;</td>
				  <td class="strong" style="font-size: 0.9em; white-space: nowrap;">';
				    for ($i=0; $i<ceil(($padLabel-strlen('January'))/2); ++$i) echo '&nbsp;';  echo 'January';  for ($i=0; $i<floor(($padLabel-strlen('January'))/2); ++$i) echo '&nbsp;';  echo '</td>
				  <td class="strong" style="font-size: 0.9em; white-space: nowrap;">';
				    for ($i=0; $i<ceil(($padLabel-strlen('February'))/2); ++$i) echo '&nbsp;';  echo 'February';  for ($i=0; $i<floor(($padLabel-strlen('February'))/2); ++$i) echo '&nbsp;';  echo '</td>
				  <td class="strong" style="font-size: 0.9em; white-space: nowrap;">';
				    for ($i=0; $i<ceil(($padLabel-strlen('March'))/2); ++$i) echo '&nbsp;';  echo 'March';  for ($i=0; $i<floor(($padLabel-strlen('March'))/2); ++$i) echo '&nbsp;';  echo '</td>
				  <td class="strong" style="font-size: 0.9em; white-space: nowrap;">';
				    for ($i=0; $i<ceil(($padLabel-strlen('April'))/2); ++$i) echo '&nbsp;';  echo 'April';  for ($i=0; $i<floor(($padLabel-strlen('April'))/2); ++$i) echo '&nbsp;';  echo '</td>
				  <td class="strong" style="font-size: 0.9em; white-space: nowrap;">';
				    for ($i=0; $i<ceil(($padLabel-strlen('May'))/2); ++$i) echo '&nbsp;';  echo 'May';  for ($i=0; $i<floor(($padLabel-strlen('May'))/2); ++$i) echo '&nbsp;';  echo '</td>
				  <td class="strong" style="font-size: 0.9em; white-space: nowrap;">';
				    for ($i=0; $i<ceil(($padLabel-strlen('June'))/2); ++$i) echo '&nbsp;';  echo 'June';  for ($i=0; $i<floor(($padLabel-strlen('June'))/2); ++$i) echo '&nbsp;';  echo '</td>
				  <td class="strong" style="font-size: 0.9em; white-space: nowrap;">';
				    for ($i=0; $i<ceil(($padLabel-strlen('July'))/2); ++$i) echo '&nbsp;';  echo 'July';  for ($i=0; $i<floor(($padLabel-strlen('July'))/2); ++$i) echo '&nbsp;';  echo '</td>
				  <td class="strong" style="font-size: 0.9em; white-space: nowrap;">';
				    for ($i=0; $i<ceil(($padLabel-strlen('August'))/2); ++$i) echo '&nbsp;';  echo 'August';  for ($i=0; $i<floor(($padLabel-strlen('August'))/2); ++$i) echo '&nbsp;';  echo '</td>
				  <td class="strong" style="font-size: 0.9em; white-space: nowrap;">';
				    for ($i=0; $i<ceil(($padLabel-strlen('September'))/2); ++$i) echo '&nbsp;';  echo 'September';  for ($i=0; $i<floor(($padLabel-strlen('September'))/2); ++$i) echo '&nbsp;';  echo '</td>
				  <td class="strong" style="font-size: 0.9em; white-space: nowrap;">';
				    for ($i=0; $i<ceil(($padLabel-strlen('October'))/2); ++$i) echo '&nbsp;';  echo 'October';  for ($i=0; $i<floor(($padLabel-strlen('October'))/2); ++$i) echo '&nbsp;';  echo '</td>
				  <td class="strong" style="font-size: 0.9em; white-space: nowrap;">';
				    for ($i=0; $i<ceil(($padLabel-strlen('November'))/2); ++$i) echo '&nbsp;';  echo 'November';  for ($i=0; $i<floor(($padLabel-strlen('November'))/2); ++$i) echo '&nbsp;';  echo '</td>
				  <td class="strong" style="font-size: 0.9em; white-space: nowrap;">';
				    for ($i=0; $i<ceil(($padLabel-strlen('December'))/2); ++$i) echo '&nbsp;';  echo 'December';  for ($i=0; $i<floor(($padLabel-strlen('December'))/2); ++$i) echo '&nbsp;';  echo '</td>
				 <tr><td colspan="13" style="font-size: 0.5em;">&nbsp;</td></tr>';

			$tniStatsMon = array();  for ($i=1; $i<13; ++$i) $tniStatsMon[$i] = array('N'=>0, 'sum'=>0, 'ave'=>0, 'stdev'=>0); //initialize monthly stats counter...
			for ($i=0; $i<count($tni); ++$i)
			{ //print out the tni data, row by row...
				echo '<tr><td class="strong" style="font-size: 0.9em; white-space: nowrap;">'.$tni[$i]['year'].'&nbsp; &nbsp;&nbsp;</td>';
				for ($j=0; $j<12; ++$j)
				{ //print out the tni data, month by month...
					if (isset($tni[$i][$j+1]))
					{ //echo the monthly tni's and keep running sums and tallies...
						echo '<td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.$padTot.'.2f',round($tni[$i][$j+1],2))).'&nbsp;</td>';
						++$tniStatsMon[$j+1]['N'];  $tniStatsMon[$j+1]['sum'] += $tni[$i][$j+1];
					} //echo the monthly tni's and keep running sums and tallies;
					else echo '<td class="mono">&nbsp;</td>';
				} //print out the tni data, month by month;
				echo '</tr>';
			} //print out the tni data, row by row;
			
			for ($i=1; $i<13; ++$i)
			{ //now form the column-wise averages...
				$tniStatsMon[$i]['ave'] = $tniStatsMon[$i]['sum']/$tniStatsMon[$i]['N'];
			} //end for: now form the column-wise averages;
			
			for ($i=1; $i<13; ++$i)
			{ //now form the column-wise stdev's...  *Note:  s := sqrt( sum((x_i - x_ave)^2) / (n-1) )...
				for ($j=0; $j<count($tni); ++$j)
				{ //form the column-wise stdev's...
					if (isset($tni[$j][$i])) $tniStatsMon[$i]['stdev'] += pow($tni[$j][$i]-$tniStatsMon[$i]['ave'], 2);
				} //form the column-wise stdev's;
			} //end for: now form the column-wise stdev's...
			for ($i=1; $i<13; ++$i)
			{ //finish forming the column-wise stdev's...
				$tniStatsMon[$i]['stdev'] = pow($tniStatsMon[$i]['stdev'] / ($tniStatsMon[$i]['N']-1), 0.5);
			} //finish forming the column-wise stdev's;

			echo '<tr><td colspan="13" style="font-size: 0.7em;">&nbsp;</td></tr>
				  <tr>'/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Monthly Averages & StDev's ~~~~~~~~~~~~~~*/.'
				    <td>&nbsp;</td>';
					for ($i=1; $i<13; ++$i)
					{ //print out the column-wise averages...
						echo '<td class="mono"><span style="font-size: 0.9em; font-weight: 700;">Ave:</span><br />'
								.str_replace(' ','&nbsp;',sprintf('%'.$padTot.'.2f',round($tniStatsMon[$i]['ave'],2))).'&nbsp;<br />&nbsp;<br />
								<span style="font-size: 0.9em; font-weight: 700;">StDev:</span><br />'
								.str_replace(' ','&nbsp;',sprintf('%'.$padTot.'.2f',round($tniStatsMon[$i]['stdev'],2))).'&nbsp;</td>';
					} //end for: print out the column-wise averages;
					echo '
				  </tr>
				</table><br />&nbsp;';
	} //end if: output formulated tni data;
	
	elseif ($params_output=='tniMulti')
	{ //output formulated multi-month tni data...
	 //*Note: if a given period includes December, then must check for this period also including months<12,
	 //  since those months must be taken from the previous year...

		/*for ($i=0; $i<count($periods); ++$i)
		{ //first, re-order each period in a natural-number-sense [for consecutive-month-checking]...
			echo '<p>Period '.($i+1);
			for ($j=0; $j<count($periods[$i]); ++$j)  { echo '<br />&nbsp; '.$periods[$i][$j]; } //for all months in given period...
		} //end for: first, re-order each period in a natural-number-sense [for consecutive-month-checking];*/ //**debug...
		
		$padTot = 5;  $padLabel = 13; //for display padding purposes...
		$periodsEnglish = array(); //initialize English periods holder...
		$periodsPartition = array();  $periodsPartitionCount = 0; //initialize period total array & counter [for checking complete partition]...
		$totalMonthPartition = array(1,2,3,4,5,6,7,8,9,10,11,12); //initialize for checking total partition of months
		$periodsError = ''; //period error checker string...
		for ($i=0; $i<count($periods); ++$i)
		{ //go about converting numeric months in each period into English labels...
			$yearWrapBreak = false;  $nonConsecutiveCheck = false; //initalize state of $yearWrap & non-consecutive warnings [so only output once for a particular period]...
			for ($j=0; $j<count($periods[$i]); ++$j)
			{ //each subarray are numeric months in the period...
				switch ($periods[$i][$j])
				{ //convert numeric months into English labels...
					case '1a':  $periodsEnglish[$i][$j] = 'January (i)';  break;
					case '2a':  $periodsEnglish[$i][$j] = 'February (i)';  break;
					case '3a':  $periodsEnglish[$i][$j] = 'March (i)';  break;
					case '4a':  $periodsEnglish[$i][$j] = 'April (i)';  break;
					case '5a':  $periodsEnglish[$i][$j] = 'May (i)';  break;
					case '6a':  $periodsEnglish[$i][$j] = 'June (i)';  break;
					case '7a':  $periodsEnglish[$i][$j] = 'July (i)';  break;
					case '8a':  $periodsEnglish[$i][$j] = 'August (i)';  break;
					case '9a':  $periodsEnglish[$i][$j] = 'September (i)';  break;
					case '10a':  $periodsEnglish[$i][$j] = 'October (i)';  break;
					case '11a':  $periodsEnglish[$i][$j] = 'November (i)';  break;
					case '12a':  $periodsEnglish[$i][$j] = 'December (i)';  break;
					case '1b':  $periodsEnglish[$i][$j] = 'January (i+1)';  break;
					case '2b':  $periodsEnglish[$i][$j] = 'February (i+1)';  break;
					case '3b':  $periodsEnglish[$i][$j] = 'March (i+1)';  break;
					case '4b':  $periodsEnglish[$i][$j] = 'April (i+1)';  break;
					case '5b':  $periodsEnglish[$i][$j] = 'May (i+1)';  break;
					case '6b':  $periodsEnglish[$i][$j] = 'June (i+1)';  break;
					case '7b':  $periodsEnglish[$i][$j] = 'July (i+1)';  break;
					case '8b':  $periodsEnglish[$i][$j] = 'August (i+1)';  break;
					case '9b':  $periodsEnglish[$i][$j] = 'September (i+1)';  break;
					case '10b':  $periodsEnglish[$i][$j] = 'October (i+1)';  break;
					case '11b':  $periodsEnglish[$i][$j] = 'November (i+1)';  break;
					case '12b':  $periodsEnglish[$i][$j] = 'December (i+1)';  break;
					default: die(error('Invalid numeric month ['.substr($periods[$i][$j],0,-1).'] in period ['.$i.']'));
				} //end switch: convert numeric months into English labels;

			   //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Begin: periods error checking...
				$periodsPartition[$periodsPartitionCount] = substr($periods[$i][$j],0,-1);  ++$periodsPartitionCount;
				if (!isset($totalMonthPartition[substr($periods[$i][$j]-1,0,-1)])) $periodsError = 'Same month found in more than one period.'; //check if this has been removed yet...
				else unset($totalMonthPartition[substr($periods[$i][$j]-1,0,-1)]); //remove specified month from $totalMonthPartition [for checking for total partition of months]...

				//~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Begin: check for consecutivity of months in this period...
				if (!$nonConsecutiveCheck)
				{ //only need to check for non-consecutiveness if not already found in currently processing period...
					if (substr($periods[$i][$j],0,-1)==12)
					{ //consecutive post-December needs to check for January [which should be the first element]...
						if (isset($periods[$i][0]) && count($periods[$i])!=1)
						{ //check for January & update error string accordingly...
						  if (substr($periods[$i][0],0,-1)!=1)
						  { //now check for pre-existing error condition...
							//die('<p>$periods[$i][0] == '.$periods[$i][0]); //**debug...
							if (empty($periodsError)) $periodsError = 'Non-consecutive month partition detected in period '.($i+1).'.'; //no pre-existing error, so just update error string...
							else $periodsError .= '<br />&nbsp; &nbsp; &nbsp; Non-consecutive month partition detected in period '.($i+1).'.'; //pre-existing error, so append error string w/ break...
							$nonConsecutiveCheck = true; //we only need one existing error to show...
						  } //end if: now check for pre-existing error condition;
						} //end if: check for January & update error string accordingly;
					} //end if: consecutive post-December needs to check for January[which should be the first element];
					else
					{ //non-post-December, so regular consecutive month check...
						if (isset($periods[$i][$j+1]))
						{ //check for next consecutive month & update error string accordingly...
						  if (substr($periods[$i][$j+1],0,-1)!=substr($periods[$i][$j]+1,0,-1) && substr($periods[$i][$j+1],0,-1)!=12)
						  { //now check for pre-existing error condition...
							//die('<p>$periods[$i][$j] == '.$periods[$i][$j].' &nbsp; &nbsp; $periods[$i][$j+1] == '.$periods[$i][$j+1]); //**debug...
							if (empty($periodsError)) $periodsError = 'Non-consecutive month partition detected in period '.($i+1).'.'; //no pre-existing error, so just update error string...
							else $periodsError .= '<br />&nbsp; &nbsp; &nbsp; Non-consecutive month partition detected in period '.($i+1).'.'; //pre-existing error, so append error string w/ break...
							$nonConsecutiveCheck = true; //we only need one existing error to show...
						  } //end if: now check for pre-existing error condition;
						} //end if: check for next consecutive month & update error string accordingly;
					} //end else: non-post-December, so regular consecutive month check;
				} //end if: only need to check for non-consecutiveness if not already found in currently processing period...
				//~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ End: check for consecutivity of months in this period...

				/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Begin: check for crazy treat-time-as-linear wrap around effects...
				if ($periods[$i][$j]<4)
				{ //$periods[$i][$j] is in first quarter of year, so check if this period also contains any months from last quarter of year...
					for ($k=0; $k<count($periods[$i]); ++$k)
					{ //check this period of months of later half of year...
						if (!$yearWrapBreak && ($periods[$i][$k]==10 || $periods[$i][$k]==11 || $periods[$i][$k]==12))
						{ //year wrap is go...
							$yearWrap = true;
						  	if (empty($periodsError)) $periodsError = 'Period '.($i+1).' is split between consecutive years:&nbsp; year-wrap in effect.
																		<br />&nbsp; [months 1-9 are considered in the ith year, months 10-12 in the (i-1)th year].'; //no pre-existing error, so just update error string...
							else $periodsError .= '<br />&nbsp; &nbsp; &nbsp; Period '.($i+1).' is split between consecutive years:&nbsp; year-wrap in effect.
																		<br />&nbsp; [months 1-9 are considered in the ith year, months 10-12 in the (i-1)th year].'; //pre-existing error, so append error string w/ break...
							$yearWrapBreak = true;  break; //we only need one existing error to show...
						} //end if: year wrap is go;
						else $yearWrap = false;
					} //end for: check this period of months of later half of year;
				} //end if: $periods[$i][$j] is in first quarter of year, so check if this period also contains any months from last quarter of year;
				//~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ End: check for crazy treat-time-as-linear wrap around effects...*/
			   //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ End: periods error checking...
			} //end for: each subarray are numeric months in the period;
		} //end for: go about converting numeric months in each period into English labels;
		
	   //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Begin: periods error checking [cont...]...
		if (count($totalMonthPartition)!=0 && empty($periodsError)) $periodsError = 'Incomplete partition of months over chosen periods.'; //check for total partition of months...
		elseif (count($totalMonthPartition)!=0) $periodsError .= '<br />&nbsp; &nbsp; &nbsp; Incomplete partition of months over chosen periods.'; //not the first error, so append...
		if (!empty($periodsError)) echo warning($periodsError); //output any errors to the screen...
	   //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ End: periods error checking [cont...]...
		
		echo '<p class="strong" style="color: purple; font-size: 1.2em;">Normalized Multi-Month Trans-Ni&ntilde;o Index:<br />&nbsp;</p>';
		if (!$verbose) echo '
		  <table align="center" cellpadding="0" cellspacing="1" border="0">'/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Multi-Month Trans-Nino Index ~~~~~~~~~~~~~*/.'
			<tr>
			  <td>&nbsp;</td>'; //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ this is the table label/header row...
				$countPeriods = 0;  for ($i=0; $i<count($periods); ++$i)  { if (count($periods[$i])>0) ++$countPeriods; }
				for ($i=0; $i<count($periods); ++$i)
				{ //insert period labels for non-empty periods...
					if (count($periods[$i])>0)
					{ //only do this if the period subArray is non-empty...
						if (!$verbose) echo '<td class="strong" style="font-size: 0.9em; white-space: nowrap;"><span style="text-decoration: underline;">
						  Period '.($i+1).'</span>:<br />&nbsp;<br />';
						$periodLabel = '';  $periodsEnglishCount = 0;
						for ($j=0; $j<count($periodsEnglish[$i]); ++$j)
						{ //create &nbsp;-padded, multi-month label...
							if (isset($periodsEnglish[$i][$j]))
							{ //only output the English month label if it is set...
								for ($k=0; $k<ceil(($padLabel-strlen($periodsEnglish[$i][$j]))/2); ++$k) $periodLabel .= '&nbsp;';
								$periodLabel .= $periodsEnglish[$i][$j].',';
								for ($k=0; $k<floor(($padLabel-strlen($periodsEnglish[$i][$j]))/2); ++$k) $periodLabel .= '&nbsp;';
								$periodLabel .= '<br />';  //die('<p>Hmm == '.$periodLabel); //**debug...
							} //end if: only output the English month label if it is set;
						} //end for: create &nbsp;-padded, multi-month label;
						$periodLabel = substr(rtrim(substr($periodLabel,0,-6)),0,-1); //trim trailing '<br />', then trim right-spaces, then trim comma...
						$periodLabel = explode('<br />', $periodLabel); //seperate label into parts...
						$periodLabel[count($periodLabel)-1] = str_replace(',','',$periodLabel[count($periodLabel)-1]).'&nbsp;'; //remove the nested trailing comma...
						$periodLabel = implode('<br />', $periodLabel); //put the label pieces back together...
						if (!$verbose) echo $periodLabel.'</td>';
					} //only do this if the period subArray is non-empty;
				} //end for: insert period labels for non-empty periods;
		if (!$verbose) echo '</tr><tr><td colspan="'.($countPeriods+1).'" style="font-size: 0.5em;">&nbsp;</td></tr>';
		else echo '<p class="mono_left">';

		$tniMulti = array(); //initialize the multi-month tni holder...
		for ($i=0; $i<count($tni); ++$i)
		{ //as many rows as the normalized tni matrix...
			$tniMulti[$i] = array(); //the multi tni holder is an array of associative arrays...
			for ($j=1; $j<$countPeriods+1; ++$j) $tniMulti[$i][$j] = array('nonNorm'=>0, 'norm'=>0); //initialize multiMonth tni holder...
		} //end for: as many rows as the normalized tni matrix;
		$tniStatsMon = array();  for ($i=1; $i<$countPeriods+1; ++$i) $tniStatsMon[$i] = array('N'=>0, 'sum'=>0, 'ave'=>0, 'stdev'=>0); //initialize monthly stats counter...
		$tniStatsMonNorm = array();  for ($i=1; $i<$countPeriods+1; ++$i) $tniStatsMonNorm[$i] = array('N'=>0, 'sum'=>0, 'ave'=>0, 'stdev'=>0); //initialize normalized monthly stats counter...
		
		for ($i=0; $i<count($tni); ++$i)
		{ //calculate multi-month tni, row by row...
			for ($j=0; $j<$countPeriods; ++$j)
			{ //print out the tni data, month by month, but first calculate the multi-month average...
				//echo '<br /><b><u>Period</u></b> '.$j; //**debug...
				if (count($periods[$j])>0)
				{ //calculate multi-month average, but only do this if the period subArray is non-empty...
					if ($verbose) echo '<br />-- Period '.$j.' ----------------------------------------------'; //**debug...
					$multiMonthN = 0;  $multiMonthSum = 0; //initialize for calculating multi-month average...
					for ($k=0; $k<count($periods[$j]); ++$k)
					{ //average over all months in the period...
						$month = substr($periods[$j][$k],0,-1); //this is the numeric month of the currently-processing month of the currently-processing period...
						$year = substr($periods[$j][$k],-1); //this either 'a' for i'th year, or 'b' for i+1'th year...
						if ($year=='a')
						{ //month from the i'th year...
							if (isset($tni[$i][$month]))
							{ //echo the monthly tni's and keep running sums and tallies...
								++$multiMonthN;  $multiMonthSum += $tni[$i][$month];
								if ($verbose)
								{ //verbose mode output...
									$space = '';  for ($z=0; $z<5-strlen($month); ++$z) $space .= '&nbsp;';
									echo '<br />Year == '.$tni[$i]['year'].', &nbsp; Month == '.$month.','.$space.'Value == '.$tni[$i][$month]; //**debug...
								} //end if: verbose mode output;
							} //echo the monthly tni's and keep running sums and tallies;
						} //end if: month from the i'th year;
						elseif ($year=='b')
						{ //month from the i+1'th year...
							if (isset($tni[$i+1][$month]))
							{ //echo the monthly tni's and keep running sums and tallies...
								++$multiMonthN;  $multiMonthSum += $tni[$i+1][$month];
								if ($verbose)
								{ //verbose mode output...
									$space = '';  for ($z=0; $z<5-strlen($month); ++$z) $space .= '&nbsp;';
									echo '<br />Year == '.$tni[$i+1]['year'].', &nbsp; Month == '.$month.','.$space.'Value == '.$tni[$i+1][$month]; //**debug...
								} //end if: verbose mode output;
							} //echo the monthly tni's and keep running sums and tallies;
						} //end if: month from the i+1'th year;
						else die(error('Invalid year selector [ie: must be i\'th or i+1\'th year]'));
					} //end for: average over all months in the period;
					if ($multiMonthN!=0) $tniMulti[$i][$j+1]['nonNorm'] = $multiMonthSum/$multiMonthN; //only calculate & output average if the countSum (N) is non-zero...
					//echo '<br />Ave == '.$tniMulti[$i][$j+1]['nonNorm']; //**debug...
					++$tniStatsMon[$j+1]['N'];  $tniStatsMon[$j+1]['sum'] += $multiMonthSum;
				} //end if: calculate multi-month average, but only do this if the period subArray is non-empty;
			} //end for: print out the tni data, month by month, but first calculate the multi-month average;
		} //end for: calculate multi-month tni, row by row;

		for ($i=1; $i<$countPeriods+1; ++$i)
		{ //now form the column-wise averages...
			$tniStatsMon[$i]['ave'] = $tniStatsMon[$i]['sum']/$tniStatsMon[$i]['N'];
		} //end for: now form the column-wise averages;
		
		for ($i=1; $i<$countPeriods+1; ++$i)
		{ //now form the column-wise stdev's...  *Note:  s := sqrt( sum((x_i - x_ave)^2) / (n-1) )...
			for ($j=0; $j<count($tniMulti); ++$j)
			{ //form the column-wise stdev's...
				if (isset($tniMulti[$j][$i]['nonNorm'])) $tniStatsMon[$i]['stdev'] += pow($tniMulti[$j][$i]['nonNorm']-$tniStatsMon[$i]['ave'], 2);
			} //form the column-wise stdev's;
		} //end for: now form the column-wise stdev's...
		for ($i=1; $i<$countPeriods+1; ++$i)
		{ //finish forming the column-wise stdev's...
			$tniStatsMon[$i]['stdev'] = pow($tniStatsMon[$i]['stdev'] / ($tniStatsMon[$i]['N']-1), 0.5);
		} //finish forming the column-wise stdev's;

		if ($verbose)
		{ //verbose mode, so now begin output with column labels [after already outputted raw stuff]...
			echo '<br />&nbsp;</p>
			  <table align="center" cellpadding="0" cellspacing="1" border="0">'/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Multi-Month Trans-Nino Index ~~~~~~~~~~~~~*/.'
				<tr>
				  <td>&nbsp;</td>'; //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ this is the table label/header row...
					//$countPeriods = 0;  for ($i=0; $i<count($periods); ++$i)  { if (count($periods[$i])>0) ++$countPeriods; }
					for ($i=0; $i<count($periods); ++$i)
					{ //insert period labels for non-empty periods...
						if (count($periods[$i])>0)
						{ //only do this if the period subArray is non-empty...
							echo '<td class="strong" style="font-size: 0.9em; white-space: nowrap;"><span style="text-decoration: underline;">
							  Period '.($i+1).'</span>:<br />&nbsp;<br />'.$periodLabel.'</td>';
						} //only do this if the period subArray is non-empty;
					} //end for: insert period labels for non-empty periods;
					echo '</tr><tr><td colspan="'.($countPeriods+1).'" style="font-size: 0.5em;">&nbsp;</td></tr>';
		} //end if: verbose mode, so now begin output with column labels [after already outputted raw stuff];
		
		for ($i=0; $i<count($tniMulti); ++$i) //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ output multi-month tni results
		{ //print out the tni data, row by row...
			echo '<tr><td class="strong" style="font-size: 0.9em; white-space: nowrap;">'.$tni[$i]['year'].'&nbsp; &nbsp;&nbsp;</td>';
			for ($j=1; $j<$countPeriods+1; ++$j)
			{ //print out the multi-month tni data, month by month, but first calculate the multi-month average...
				if ($tniMulti[$i][$j]['nonNorm']!=0)
				{ //only calculate & output normalized value if non-normalized is non-zero...
					$tniMulti[$i][$j]['norm'] = $tniMulti[$i][$j]['nonNorm'] / $tniStatsMon[$j]['stdev'];
					//echo '<td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.$padTot.'.2f',round($tniMulti[$i][$j]['nonNorm'],2))).'&nbsp;</td>'; //non-normalized...
					echo '<td class="mono">'.str_replace(' ','&nbsp;',sprintf('%'.$padTot.'.2f',round($tniMulti[$i][$j]['norm'],2))).'&nbsp;</td>'; //normalized...
				} //end if: only calculate & output normalized value if non-normalized is non-zero
				else //the non-normalized value must be zero [ie: non-set], so output blank place holder...
				 echo '<td class="mono">&nbsp;</td>';
			} //end for: print out the multi-month tni data, month by month, but first calculate the multi-month average;
			echo '</tr>';
		} //print out the tni data, row by row;
		
		for ($i=1; $i<$countPeriods+1; ++$i)
		{ //now recalcute column-wise ave's & std's for normalized multi-month dataset...
			for ($j=0; $j<count($tniMulti); ++$j)
			{ //print out the multi-month tni data, month by month, but first calculate the multi-month average...
				if (isset($tniMulti[$j][$i]['norm']))
				{ $tniStatsMonNorm[$i]['sum'] += $tniMulti[$j][$i]['norm'];  ++$tniStatsMonNorm[$i]['N']; }
			} //end for: print out the multi-month tni data, month by month, but first calculate the multi-month average;
		} //now recalcute column-wise ave's & std's for normalized multi-month dataset;
		for ($i=1; $i<$countPeriods+1; ++$i) $tniStatsMonNorm[$i]['ave'] = $tniStatsMonNorm[$i]['sum'] / $tniStatsMonNorm[$i]['N'];

		for ($i=1; $i<$countPeriods+1; ++$i)
		{ //now form the column-wise stdev's...  *Note:  s := sqrt( sum((x_i - x_ave)^2) / (n-1) )...
			for ($j=0; $j<count($tniMulti); ++$j)
			{ //form the column-wise stdev's...
				if (isset($tniMulti[$j][$i]['norm'])) $tniStatsMonNorm[$i]['stdev'] += pow($tniMulti[$j][$i]['norm']-$tniStatsMonNorm[$i]['ave'], 2);
			} //form the column-wise stdev's;
		} //end for: now form the column-wise stdev's...
		for ($i=1; $i<$countPeriods+1; ++$i)
		{ //finish forming the column-wise stdev's...
			$tniStatsMonNorm[$i]['stdev'] = pow($tniStatsMonNorm[$i]['stdev'] / ($tniStatsMonNorm[$i]['N']-1), 0.5);
		} //finish forming the column-wise stdev's;

		echo '<tr><td colspan="'.($countPeriods+1).'" style="font-size: 0.7em;">&nbsp;</td></tr>
			  <tr>'/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Monthly Averages & StDev's ~~~~~~~~~~~~~~*/.'
				<td>&nbsp;</td>';
				for ($i=1; $i<$countPeriods+1; ++$i)
				{ //print out the column-wise averages...
					echo '<td class="mono"><span style="font-size: 0.9em; font-weight: 700;">Ave:</span><br />'
							.str_replace(' ','&nbsp;',sprintf('%'.$padTot.'.2f',round($tniStatsMonNorm[$i]['ave'],2))).'&nbsp;<br />&nbsp;<br />
							<span style="font-size: 0.9em; font-weight: 700;">StDev:</span><br />'
							.str_replace(' ','&nbsp;',sprintf('%'.$padTot.'.2f',round($tniStatsMonNorm[$i]['stdev'],2))).'&nbsp;</td>';
				} //end for: print out the column-wise averages;
				echo '
			  </tr>
			</table><br />&nbsp;';
	} //end elseif: output formulated multi-month tni data;
	
	elseif ($params_output=='rawHtml')
	{ //output raw html tni data...
		echo '<p class="strong" style="color: purple; font-size: 1.2em;">Raw TNI Data: (HTML)<br />
				<span style="color: dimgray; font-size: 0.8em; font-weight: 200;">[Data available at: http://www.cpc.ncep.noaa.gov/data/indices/sstoi.indices]</span><br />&nbsp;</p>
			  <table align="center" cellpadding="0" cellspacing="2" border="0">
				<tr>
				  <td class="strong" style="font-decoration: underline;">';  for ($i=0; $i<4; ++$i) echo '&nbsp;';  echo 'Year';  for ($i=0; $i<4; ++$i) echo '&nbsp;';  echo '</td>
				  <td class="strong" style="font-decoration: underline;">';  for ($i=0; $i<4; ++$i) echo '&nbsp;';  echo 'Month';  for ($i=0; $i<4; ++$i) echo '&nbsp;';  echo '</td>
				  <td class="strong" style="font-decoration: underline;">';  for ($i=0; $i<6; ++$i) echo '&nbsp;';  echo 'Ni&ntilde;o 1_2';  for ($i=0; $i<6; ++$i) echo '&nbsp;';  echo '</td>
				  <td class="strong" style="font-decoration: underline;">';  for ($i=0; $i<6; ++$i) echo '&nbsp;';  echo 'Ni&ntilde;o 3';  for ($i=0; $i<6; ++$i) echo '&nbsp;';  echo '</td>
				  <td class="strong" style="font-decoration: underline;">';  for ($i=0; $i<6; ++$i) echo '&nbsp;';  echo 'Ni&ntilde;o 4';  for ($i=0; $i<6; ++$i) echo '&nbsp;';  echo '</td>
				  <td class="strong" style="font-decoration: underline;">';  for ($i=0; $i<6; ++$i) echo '&nbsp;';  echo 'Ni&ntilde;o 3_4';  for ($i=0; $i<6; ++$i) echo '&nbsp;';  echo '</td>
				</tr>
				<tr><td colspan="6" style="font-size: 0.4em;"><hr size="1" color="dimgray" /></td>'."\n"; //**debug [**temporary outpur of standardized nino's""]...
		for ($i=1; $i<count($data); ++$i)
		{ //output all rows of raw data...
			echo '<tr>
					<td class="mono">'.$data[$i]['year'].'</td>
					<td class="mono">'.$data[$i]['month'].'</td>
					<td class="mono">'.$data[$i]['nino_1_2'].'</td>
					<td class="mono">'.$data[$i]['nino_3'].'</td>
					<td class="mono">'.$data[$i]['nino_4'].'</td>
					<td class="mono">'.$data[$i]['nino_3_4'].'</td>
				  </tr>'."\n"; //**debug [**temporary outpur of standardized nino's""]...
		} //end for: output all rows of raw data;
		echo '</table><br />&nbsp;';
	} //end elseif: output raw html tni data;

	elseif ($params_output=='rawText')
	{ //output raw text tni data...
		echo '[Data available at: http://www.cpc.ncep.noaa.gov/data/indices/sstoi.indices]'."\n"
			 .'Year'."\t".'Month'."\t".'Nino_1_2'."\t".'Nino_3'."\t".'Nino_4'."\t".'Nino_3_4'."\n";
		for ($i=1; $i<count($data); ++$i)
		{ //output all rows of raw data...
			echo $data[$i]['year']."\t".$data[$i]['month']."\t".$data[$i]['nino_1_2']."\t".$data[$i]['nino_3']."\t".$data[$i]['nino_4']."\t".$data[$i]['nino_3_4']."\n";
		} //end for: output all rows of raw data;
	} //end elseif: output raw text tni data;
	
	else
	{ //case of output selector error...
		echo error('Not implemented.<br />&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; [output parameter == '.$params_output.']');
	} //end else: case of output selector error;
	

   //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ End: calculate means & standard deviations of entire freakin dataset [for each respective nino];

   /*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Begin: calculate means & standard deviations of monthly averages...
  //*Note: this calculates the stdev of just the $nino_1_2's & $nino_4's...  ***Note: this is NOT quite correct [but almost, square the difference things & take sqrt]***
	$stdStatsMon_nino_1_2_mean = 0;  $stdStatsMon_nino_4_mean = 0;  $stdStatsMon_nino_1_2_stdev = 0;  $stdStatsMon_nino_4_stdev = 0; //initialize...

	for ($i=0; $i<12; ++$i)
	{ //calculate means of monthly averages...
		$stdStatsMon_nino_1_2_mean += $stdStatsMon[$i]['nino_1_2_ave'];
		$stdStatsMon_nino_4_mean += $stdStatsMon[$i]['nino_4_ave'];
	} //end for: calculate means of monthly averages;
	
	$aveByMonth_nino_1_2_mean = $aveByMonth_nino_1_2_mean / 12;  $aveByMonth_nino_4_mean = $aveByMonth_nino_4_mean / 12;
	
	for ($i=0; $i<12; ++$i)
	{ //calculate standard deviations of monthly averages...
		$aveByMonth_nino_1_2_stdev += $aveByMonth[$i]['nino_1_2_ave'] - $aveByMonth_nino_1_2_mean;
		$aveByMonth_nino_4_stdev += $aveByMonth[$i]['nino_4_ave'] - $aveByMonth_nino_4_mean;
	} //end for: calculate standard deviations of monthly averages;

	$aveByMonth_nino_1_2_stdev = pow($aveByMonth_nino_1_2_stdev,2) / 11;  $aveByMonth_nino_4_stdev = pow($aveByMonth_nino_4_stdev,2) / 11;

	die('<p>$aveByMonth_nino_1_2_mean == '.$aveByMonth_nino_1_2_mean.'<br />$aveByMonth_nino_1_2_stdev == '.$aveByMonth_nino_1_2_stdev.'<br />&nbsp;<br />
			$aveByMonth_nino_4_mean == '.$aveByMonth_nino_4_mean.'<br />$aveByMonth_nino_4_stdev == '.$aveByMonth_nino_4_stdev.'</p>'); //**debug...
	
   //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ End: calculate means & standard deviations of monthly averages;*/
	
  return;
} //end function: calculate_tni();

?>
