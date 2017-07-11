<?php
## alexexit
/*
	+-----------------------------------------------------------------------------+
	| ILIAS open source                                                           |
	+-----------------------------------------------------------------------------+
	| Copyright (c) 1998-2005 ILIAS open source, University of Cologne            |
	|                                                                             |
	| This program is free software; you can redistribute it and/or               |
	| modify it under the terms of the GNU General Public License                 |
	| as published by the Free Software Foundation; either version 2              |
	| of the License, or (at your option) any later version.                      |
	|                                                                             |
	| This program is distributed in the hope that it will be useful,             |
	| but WITHOUT ANY WARRANTY; without even the implied warranty of              |
	| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the               |
	| GNU General Public License for more details.                                |
	|                                                                             |
	| You should have received a copy of the GNU General Public License           |
	| along with this program; if not, write to the Free Software                 |
	| Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA. |
	+-----------------------------------------------------------------------------+
*/


/**
* Helper class based on iluseragreement
*  
* original: @author	Alex Killing <alex.killing@gmx.de>
* @version $Id: class.ilUserAgreement.php 14579 2007-08-23 12:06:19Z akill $
* 
*/
class ilRegistrationHelp
{
	/**
	* ilias object
	* @var object Ilias
	* @access public
	*/
	var $ilias;


	/**
	* Constructor
	* @access	public
	*/
	function ilRegistrationHelp()
	{
		global $ilias;

		$this->ilias = &$ilias;
	}

	/**
	* get user registrationhelp text  (static)
	*
	* @access	public
	*/
	function _getText()
	{
		global $lng, $ilias, $ilLog;
	
		
		// 1st try: client specific / user language registrationhelp
		$registrationhelp = "./Customizing/clients/".CLIENT_ID."/registrationhelp/".
			"registrationhelp_".$lng->lang_key.".html";
	
		// 2nd try: client specific / default language
		if (!file_exists($registrationhelp))
		{
			$registrationhelp = "./Customizing/clients/".CLIENT_ID."/registrationhelp/".
				"registrationhelp_".$lng->lang_default.".html";
		}
	
		// 3rd try: client specific / english
		if (!file_exists($registrationhelp))
		{
			$registrationhelp = "./Customizing/clients/".CLIENT_ID."/registrationhelp/".
				"registrationhelp_de.html";
		}
		
		// 4th try: global / user language
		if (!file_exists($registrationhelp))
		{
			$registrationhelp = "./Customizing/global/registrationhelp/".
				"registrationhelp_".$lng->lang_key.".html";
		}
	
		// 5th try: global / default language
		if (!file_exists($registrationhelp))
		{
			$registrationhelp = "./Customizing/global/registrationhelp/".
				"registrationhelp_".$lng->lang_default.".html";
		}
	
		// last try: global / english
		if (!file_exists($registrationhelp))
		{
			
			$registrationhelp = "./Customizing/global/registrationhelp/".
				"registrationhelp_de.html";
		}
		

		if (file_exists($registrationhelp))
		{
			if ($content = file($registrationhelp))
			{
				foreach ($content as $key => $val)
				{
					$text .= trim(nl2br($val));
				}
				return $text;
			}
		}
		
		
	}
}
?>