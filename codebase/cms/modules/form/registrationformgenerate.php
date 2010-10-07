<?php
/**
 * @package pragyan
 * @copyright (c) 2008 Pragyan Team
 * @license http://www.gnu.org/licenses/ GNU Public License
 * For more details, see README
 */

	/**
	 * The actual registration form!!! For which we made all this preparation...
	 * @param $action is the form action
	 *
	 * In case it is blank, it means this function was called by actionView
	 * in that case, action is "."
	 *
	 * In case it is ./+editregistrants&subaction=editregistrant&useremail=<useremail>, it means
	 * this function was called by edit registrants
	 *
	 * @uses getFormElementInputField to get the input fields
	 *
	 * TODO : If the form is associated with a group, the form HAS to give the user the option to unregister from it.
	 */
	function generateRegistrationForm($moduleCompId,$userId,$action="", $disableCaptcha = false) {
		if($action != '')
			$userId = getUserIdFromEmail(escape($_GET['useremail']));
		else
			$action = './+view';

		if(!isset($userId)) {
			displayerror('Could not load information for user with e-mail address ' . safe_html(escape($_GET['useremail'])));
			return '';
		}

		/// Initialize the form body
		global $cmsFolder;
		global $moduleFolder;
		global $urlRequestRoot;
		$jsPath2 = "$urlRequestRoot/$cmsFolder/$moduleFolder/form/validation.js";//validation.js
		$jsPath = "$urlRequestRoot/$cmsFolder/templates/common/scripts/formValidator.js";//validation.js
		$calpath = "$urlRequestRoot/$cmsFolder/$moduleFolder";
		$jsPathMooTools = "$urlRequestRoot/$cmsFolder/templates/common/scripts/mootools-1.11-allCompressed.js";
		$body = '<script language="javascript" type="text/javascript" src="'.$jsPath2.'"></script>';


		/// Get HTML for all the fields for the form
		$jsValidationFunctions = array();
		$containsFileUploadFields = false;
		$formElements = getFormElementsHtmlAsArray($moduleCompId, $userId, $jsValidationFunctions, $containsFileUploadFields);

		$jsValidationFunctions = join($jsValidationFunctions, ' && ');

		$body .= '<link rel="stylesheet" type="text/css" media="all" href="'.$calpath.'/form/calendar/calendar.css" title="Aqua" />' .
						 '<script type="text/javascript" src="'.$calpath.'/form/calendar/calendar.js"></script>';
		$body .= '<br /><br /><div class="registrationform"><form class="fValidator-form" action="'.$action.'" method="post"';
		if($containsFileUploadFields)
			$body .= ' enctype="multipart/form-data"';
		$body .= '>';

		/// SELECT form details
		$formQuery = 'SELECT `form_heading`, `form_headertext`, `form_footertext`, `form_usecaptcha` FROM `form_desc` WHERE ' .
								 "`page_modulecomponentid` = $moduleCompId";
		$formResult = mysql_query($formQuery);
		if(!$formResult)	{ displayerror('E52 : Invalid query: ' . mysql_error()); 	return false; }
		if($formRow = mysql_fetch_assoc($formResult)) {
			$body .= '<fieldset><legend>' . $formRow['form_heading'] . '</legend><br />' . $formRow['form_headertext'] . '<br />';
		}
		else {
			displayerror('Could not load form data.');
			return '';
		}

		$body .= "\n<table cellspacing=\"8px\"><tr>";
		$body .= join($formElements, "</tr>\n<tr>") . '</tr>';

		if(!$disableCaptcha && $formRow['form_usecaptcha'] == 1)
			$body .= getCaptchaHtml();

		$body .= '<tr>'.
				'<td colspan="2">* - Required Fields&nbsp;</td>'.
			'</tr></table></fieldset>' .
							'<br /><input type="submit" name="submitreg_form_'.$moduleCompId.'" value="Submit" />' .
							'<br /><br />' . $formRow['form_footertext'] .
							'</form></div>';

		$body .= <<<SCRIPT
			<script language="javascript" type="text/javascript">
			<!--
				function validate_form(thisform) {
					return ($jsValidationFunctions);
				}
			-->
			</script>
SCRIPT;

		return $body;
	}

	function getCaptchaHtml() {
			global $uploadFolder, $sourceFolder, $moduleFolder, $cmsFolder, $urlRequestRoot;
			require_once("$sourceFolder/$moduleFolder/form/captcha/class/captcha.class.php");
			$captcha = new captcha($sourceFolder, $moduleFolder, $uploadFolder, $urlRequestRoot,$cmsFolder,6);
			$_SESSION['CAPTCHAString'] = $captcha->getCaptchaString();

			$body = '<tr><td>Enter the text as shown in the image :</td><td>' .
					'<img style="border:1px solid;padding:0px" src="' . $captcha->getCaptchaUrl() . '" alt="CAPTCHA" border="1"/><br/>' .
					'<input type="text" class="required" name="txtCaptcha" /><td></tr>';
			return $body;
	}

	function getFormElementsHtmlAsArray($moduleCompId, $userId, &$jsValidationFunctions, &$containsFileUploadFields) {
		/// Check if the user has already registered to this form,
		/// If yes, load default values for each field.
		/// We'll keep this as an associative array, relating element id to value
		$containsFileUploadFields = false;
		$formValues = array();
		if(verifyUserRegistered($moduleCompId,$userId)) {
			$dataQuery = 'SELECT `form_elementid`, `form_elementdata` FROM `form_elementdata` WHERE ' .
									 "`page_modulecomponentid` = $moduleCompId AND `user_id` = $userId";
			$dataResult = mysql_query($dataQuery);
			
			if(!$dataResult)	{ displayerror('E35 : Invalid query: ' . mysql_error()); 	return false; }
			while($dataRow = mysql_fetch_assoc($dataResult)) {
			
				$formValues[$dataRow['form_elementid']] = $dataRow['form_elementdata'];
			}
		}
		else {
			$dataQuery = 'SELECT `form_elementid`, `form_elementdefaultvalue` FROM `form_elementdesc` WHERE ' .
									 "`page_modulecomponentid` = $moduleCompId";
			$dataResult = mysql_query($dataQuery);
			
			if(!$dataResult)	{ displayerror('E132 : Invalid query: ' . mysql_error()); 	return false; }
			while($dataRow = mysql_fetch_assoc($dataResult)) {
			
				$formValues[$dataRow['form_elementid']] = $dataRow['form_elementdefaultvalue'];
			}
		}
	
		$elementQuery = 'SELECT `form_elementid`, `form_elementtype` FROM `form_elementdesc` WHERE ' .
										"`page_modulecomponentid` = $moduleCompId ORDER BY `form_elementrank`";
		$elementResult = mysql_query($elementQuery);
		$formElements = array();
		$jsValidationFunctions = array();

		while($elementRow = mysql_fetch_row($elementResult)) {
			$jsOutput = '';
			if($elementRow[1] == 'file') {
				$containsFileUploadFields = true;
			}
			
			$formElements[] =
						getFormElementInputField
						(
							$moduleCompId, $elementRow[0],
							isset($formValues[$elementRow[0]]) ? $formValues[$elementRow[0]] : '', $jsOutput
						);
			if($jsOutput != '') {
				$jsValidationFunctions[] = $jsOutput;
			}
		}

		return $formElements;
	}

function getFormElementInputField($moduleComponentId, $elementId, $value="", &$javascriptCheckFunctions) {
	$elementQuery = "SELECT * FROM `form_elementdesc` WHERE `page_modulecomponentid` = " .
	                "$moduleComponentId AND `form_elementid` = $elementId";
	$elementResult = mysql_query($elementQuery);

	if($elementResult && $elementRow = mysql_fetch_assoc($elementResult)) {
		$htmlOutput = '<td>' . $elementRow['form_elementdisplaytext'];
		$jsOutput = array();

		$elementHelpName = $elementRow['form_elementname'];
		$elementType = $elementRow['form_elementtype'];
		$elementTooltip = htmlentities($elementRow['form_elementtooltiptext']);
		$elementSize = $elementRow['form_elementsize'];
		$elementTypeOptions = $elementRow['form_elementtypeoptions'];
		$elementMoreThan = $elementRow['form_elementmorethan'];
		$elementLessThan = $elementRow['form_elementlessthan'];
		$isRequired = $elementRow['form_elementisrequired'];
		$elementCheckInt = ($elementRow['form_elementcheckint'])==1?true:false;

		$elementName = 'form_' . $moduleComponentId . '_element_' . $elementId;

		if($elementRow['form_elementisrequired'] != 0 && ucfirst(strtolower($elementType)) != "Checkbox") {
			$jsOutput[] = "isFilled('$elementName', '$elementHelpName')";
		}

		if($isRequired)
			$htmlOutput .= '*';
		$htmlOutput .='</td><td>';
		$functionName = "getFormElement".ucfirst(strtolower($elementType));
		if($functionName($elementName,$value,$isRequired,$elementHelpName,$elementTooltip,$elementSize,$elementTypeOptions,$elementMoreThan,$elementLessThan,$elementCheckInt,$jsOutput,$htmlOutput)==false)
			displayerror("Unable to run function ".$functionName);
	}

	$jsOutput = join($jsOutput, ' && ');
	$javascriptCheckFunctions = $jsOutput;

	return $htmlOutput . "</td>\n";
}


		/// TEXTAREA
		function getFormElementTextarea($elementName,$value,$isRequired,$elementHelpName,$elementTooltip,$elementSize,$elementTypeOptions,$elementMoreThan,$elementLessThan,$elementCheckInt,&$jsOutput,&$htmlOutput){
			$validCheck='';
			if($isRequired)
				$validCheck=" class=\"required\"";

			if(is_numeric($elementSize) && $elementSize > 0) {
				$i = $elementSize/5;
				$cols = (($i>20)?($i<34?$i:30):20);
				$rows = (($cols==20)?5:($cols>27?7:6));
				$sizeText = 'rows="'.$rows.'" cols="'.$cols.'"';
			}
			else
				$sizeText = 'rows="5" cols="20"';
			$htmlOutput .= '<textarea style="width:100%" '.$sizeText.' name="'.$elementName.'" id="'.$elementName.'" title="'.$elementTooltip."\"".$validCheck.'>' . $value . '</textarea>';
			return true;
		}
		/// PASSWORD
		function getFormElementPassword($elementName,$value,$isRequired,$elementHelpName,$elementTooltip,$elementSize,$elementTypeOptions,$elementMoreThan,$elementLessThan,$elementCheckInt,&$jsOutput,&$htmlOutput){

			$htmlOutput .= '<input type="password" id="'.$elementName.'" title="'.$elementTooltip.'" />' . $value ;
			return true;
		}
		/// SELECTBOX
		function getFormElementSelect($elementName,$value,$isRequired,$elementHelpName,$elementTooltip,$elementSize,$elementTypeOptions,$elementMoreThan,$elementLessThan,$elementCheckInt,&$jsOutput,&$htmlOutput)
		{
			if($isRequired)
			$validCheck=" class=\"required\"";
			else $validCheck="";

			$options = split('\|', $elementTypeOptions);
			$optionsHtml = '';
	
			for($i = 0; $i < count($options); $i++) {
				if($options[$i] == $value) {
					$optionsHtml .= '<option value="'.$i.'" selected="selected" >' . $options[$i] . "</option>\n";
				}
				else {
					$optionsHtml .= '<option value="'.$i.'" >' . $options[$i] . "</option>\n";
				}
			}

			$htmlOutput .= '<select name="'.$elementName.'" id="'.$elementName.'" title="'.$elementTooltip."\"".$validCheck.'>' . $optionsHtml . '</select>';
			return true;
		}

		/// RADIO BUTTONS
		function getFormElementRadio($elementName,$value,$isRequired,$elementHelpName,$elementTooltip,$elementSize,$elementTypeOptions,$elementMoreThan,$elementLessThan,$elementCheckInt,&$jsOutput,&$htmlOutput)
		{
			if($isRequired)
			$validCheck=" class=\"required\"";
			else $validCheck="";
			$options = split('\|', $elementTypeOptions);
			$optionsHtml = '';

			for($i = 0; $i < count($options); $i++) {
				$optionsHtml .= '<label><input type="radio" id="'.$elementName.'" name="'.$elementName.'" value="'.
												$i.'"';

				if($options[$i] == $value) {
					$optionsHtml .= ' checked="checked"';
				}

				$optionsHtml .= $validCheck.'/>'.$options[$i].'</label>&nbsp;&nbsp;';
			}

			$htmlOutput .= $optionsHtml;
			return true;
		}

		/// CHECKBOXES
		function getFormElementCheckbox($elementName,$value,$isRequired,$elementHelpName,$elementTooltip,$elementSize,$elementTypeOptions,$elementMoreThan,$elementLessThan,$elementCheckInt,&$jsOutput,&$htmlOutput)
		 {
		 	$options = split('\|', $elementTypeOptions);

		 	$validCheck = "";
		 	if($isRequired)
				$jsOutput[] = "isChecked('$elementName','$elementHelpName',".count($options).")";


			$optionsHtml = '';
			$values=explode("|",$value);
			for($i = 0; $i < count($options); $i++) {
				$optionsHtml .= '<label><input type="checkbox" id="'.$elementName.'_'.$i.'" name="'.$elementName.'_'.$i.'" value="'.
												htmlentities($options[$i]).'"';

				if(array_search($options[$i],$values)!==FALSE) {
					$optionsHtml .= ' checked="checked"';
				}

				$optionsHtml .= $validCheck.' />'.$options[$i].'</label>&nbsp;&nbsp;';
			}

			$htmlOutput .= $optionsHtml;
			return true;
		}

		/// FILE UPLOAD FIELD
		function getFormElementFile($elementName,$value,$isRequired,$elementHelpName,$elementTooltip,$elementSize,$elementTypeOptions,$elementMoreThan,$elementLessThan,$elementCheckInt,&$jsOutput,&$htmlOutput)
		 {
		 	if($isRequired && $value == '')	/// Check $value == '', because if it isn't, there's no point making the user upload the same file again
				$validCheck=" class=\"required\"";
			else
				$validCheck="";

			global $sourceFolder;
			require_once("$sourceFolder/upload.lib.php");
			$htmlOutput .= getFileUploadField($elementName,"form", 2*1024*1024, $validCheck);

			global $uploadFolder;
			if($value != '') {
				$htmlOutput .= '<br />(Leave blank to keep current file : <a href="./' . $value . '">'.$value.'</a>)';
			}

			return true;
		}

		/// TEXTBOXES
		function getFormElementText($elementName,$value,$isRequired,$elementHelpName,$elementTooltip,$elementSize,$elementTypeOptions,$elementMoreThan,$elementLessThan,$elementCheckInt,&$jsOutput,&$htmlOutput)
		 {
		 	if($isRequired)
			$validCheck=" class=\"required\"";
			else $validCheck="";
			if($elementCheckInt)
			{
				if($validCheck!="")
				$validCheck=" class=\"required numeric\"";
				else
				$validCheck=" class=\"numeric\"";

				if(!is_null($elementMoreThan) && $elementMoreThan!="")
					$validCheck .= ' min="' . ($elementMoreThan + 1) . '"';
				if(!is_null($elementLessThan) && $elementLessThan!="")
					$validCheck .= ' max="' . ($elementLessThan - 1) . '"';
			}
			if(is_numeric($elementSize) && $elementSize > 0) {
				$maxlength = $elementSize;
				if($elementSize > 30)
					$elementSize = 30;
				$sizeText = 'maxlength="'.$maxlength.'" size="'.$elementSize.'"';
			}
			else
				$sizeText = "";
			$htmlOutput .= '<input type="text" '.$sizeText.' name="'.$elementName.'" id="'.$elementName.'" value="'.$value.'" ' .
										 'title="'.$elementTooltip."\"".$validCheck.' />';
										 return true;
		}

		/// DATE AND DATETIME CONTROLS
		function getFormElementDate($elementName,$value,$isRequired,$elementHelpName,$elementTooltip,$elementSize,$elementTypeOptions,$elementMoreThan,$elementLessThan,$elementCheckInt,&$jsOutput,&$htmlOutput)
		{
			return getFormElementDatetime($elementName,$value,$isRequired,$elementHelpName,$elementTooltip,$elementSize,$elementTypeOptions,$elementMoreThan,$elementLessThan,$elementCheckInt,$jsOutput,$htmlOutput,"date");
		}
		function getFormElementDatetime($elementName,$value,$isRequired,$elementHelpName,$elementTooltip,$elementSize,$elementTypeOptions,$elementMoreThan,$elementLessThan,$elementCheckInt,&$jsOutput,&$htmlOutput,$type="datetime")
		{
			$datetimeFormat = ($type == 'date' ? "'%Y-%m-%d'" : "'%Y-%m-%d %H:%M'");

			if($isRequired)
				$validCheck=' class="required"';
			else
				$validCheck="";

			$validCheck .= ' dateformat="' . ($type == 'date' ? 'YY-MM-DD' : 'YY-MM-DD hh:mm') . '"';

			if(!is_null($elementMoreThan) && $elementMoreThan != '') {
				$jsOutput[] = "checkLBDate('$elementName', '$elementMoreThan', $datetimeFormat, '$elementHelpName')";
			}
			if(!is_null($elementLessThan) && $elementLessThan != '') {
				$jsOutput[] = "checkUBDate('$elementName', '$elementLessThan', $datetimeFormat, '$elementHelpName')";
			}

			$htmlOutput .= '<input type="text" '. $validCheck . ' name="'.$elementName.'" value="' . $value . '" id="'.$elementName.'" /><input name="cal'.$elementName.'" type="reset" value=" ... " onclick="return showCalendar(\'' . $elementName . '\', '.$datetimeFormat.', \'24\', true);" />';
			return true;
		}