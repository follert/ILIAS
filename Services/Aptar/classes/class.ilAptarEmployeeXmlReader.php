<?php
// aptar-patch: begin
/* Copyright (c) 1998-2016 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Services/Xml/classes/class.ilSaxParser.php';
require_once 'Services/User/classes/class.ilObjUser.php';
require_once 'Services/User/classes/class.ilUserDefinedFields.php';
require_once 'Services/OrgUnit/classes/class.ilOrgRoleHelper.php';
require_once 'Services/OrgUnit/classes/class.ilOrgUnit.php';
require_once 'Services/User/classes/class.ilUserDefinedFields.php';
require_once 'Services/Aptar/classes/class.ilAptarLog.php';

/**
 * Class ilAptarEmployeeXmlReader
 */
class ilAptarEmployeeXmlReader extends ilSaxParser
{
	/**
	 * @var string
	 */
	const UDF_NAME_SEGMENT = 'Segment/Corporate';

	/**
	 * @var string
	 */
	const UDF_NAME_APTER_SITE = 'Aptar Site';
	
	/**
	 * @var string
	 */
	const DEFAULT_ORG_ROLE_NAME = '04 Employees';

	/**
	 * @var int
	 */
	const MODE_VALIDATION = 1;

	/**
	 * @var int
	 */
	const MODE_PROCESS = 2;

	/**
	 * @var int[]
	 */
	protected static $udf_ids_by_name = array();

	/**
	 * @var int
	 */
	protected $mode = self::MODE_PROCESS;

	/**
	 *
	 */
	const XSD_FILE = './Services/Aptar/xml/EmployeeData.xsd';

	/**
	 *
	 */
	const NAME_SPACE_URI = 'http://www.ilias.de/Services/Aptar/employee/4_3';

	/**
	 * @var int
	 */
	const DOM_MISSING_NS_CODE = 1845;

	/**
	 * @var string
	 */
	protected $xml = '';

	/**
	 * @var string
	 */
	protected $schema = '';

	/**
	 * @var ilAptarLog
	 */
	protected $log;

	/**
	 * @var array
	 */
	protected $errors = array();

	/**
	 * @var string
	 */
	protected $cdata = '';

	/**
	 * @var ilObjUser|null
	 */
	protected $current_user = null;

	/**
	 * @var array
	 */
	protected $org_roles = array();

	/**
	 * @var bool
	 */
	protected $in_basic_data = false;

	/**
	 * {@inheritdoc}
	 */
	public function __construct($a_xml_file = '', $throwException = false)
	{
		parent::__construct($a_xml_file, $throwException);

		$this->schema = self::XSD_FILE;
		$this->log    = ilAptarLog::getInstance();

		$response        = ilOrgRoleHelper::getListOfRoleTemplates();
		$this->org_roles = (array)$response->entries;
	}

	/**
	 * 
	 */
	public function __destruct()
	{
		unset($this->log);
	}

	/**
	 *
	 */
	protected function removeAllAutoGeneratedOrgUnitAssignments()
	{
		/**
		 * @var $ilDB ilDB
		 */
		global $ilDB;

		$ilDB->manipulateF(
			"DELETE FROM org_unit_assignments WHERE oa_usr_id = %s AND oa_owner_id = %s",
			array('integer', 'integer'),
			array($this->current_user->getId(), ROLE_FOLDER_ID)
		);
	}

	/**
	 * @param string $a_mode
	 */
	public function setMode($a_mode)
	{
		$this->mode = $a_mode;
	}

	/**
	 * @return int
	 */
	public function getMode()
	{
		return $this->mode;
	}

	/**
	 * @param string $a_xml
	 */
	public function setXml($a_xml)
	{
		$this->xml = $a_xml;
	}

	/**
	 * @return string
	 */
	public function getXml()
	{
		return $this->xml;
	}

	/**
	 * @return string
	 */
	public function getSchema()
	{
		return $this->schema;
	}

	/**
	 * @return string
	 */
	public function errorToString()
	{
		return implode("\n", $this->errors);
	}

	/**
	 * @param string $a_name
	 * @return int
	 */
	protected function getUdfIdByName($a_name)
	{
		if(!array_key_exists($a_name, self::$udf_ids_by_name))
		{
			self::$udf_ids_by_name[$a_name] = ilUserDefinedFields::_getInstance()->fetchFieldIdFromName($a_name);
		}

		return self::$udf_ids_by_name[$a_name];
	}

	/**
	 * @param string $a_xml
	 * @param bool   $a_add_default_namespace
	 * @return bool
	 */
	protected function validateXml($a_xml, $a_add_default_namespace = false)
	{
		libxml_use_internal_errors(true);
		$dom = new DOMDocument();
		$dom->loadXML($a_xml);
		$dom->schemaValidate($this->getSchema());

		foreach(libxml_get_errors() as $error)
		{
			$this->log->info('XML schema validation failed: ' . $error->line . ': ' . $error->message);

			/* @var $error LibXMLError */
			if($error->code == self::DOM_MISSING_NS_CODE)
			{
				if(!$a_add_default_namespace)
				{
					$xml = simplexml_load_string($a_xml);
					$xml->addAttribute('xmlns', self::NAME_SPACE_URI);
					$xml->saveXml();
					$this->log->info('Starting second validation attempt with default namespace');
					libxml_clear_errors();
					return $this->validateXml($xml->asXML(), true);
				}
			}
			else
			{
				$this->errors[] = $error->line . ': ' . $error->message;
			}
		}
		libxml_clear_errors();

		if(count($this->errors))
		{
			return false;
		}

		return true;

	}

	/**
	 * @return bool
	 */
	public function validate()
	{
		$this->log->info("Started validation of passed XML document ...");
		$this->log->info($this->getXml());

		if(!$this->validateXml($this->getXml(), false))
		{
			return false;
		}

		$this->setMode(self::MODE_VALIDATION);
		$this->setXMLContent($this->getXml());
		$this->startParsing();

		// If we should return an error document to the client (HTTP Response), we can logically validate
		// the XML document and pass false here if we found any errors 

		return true;
	}

	/**
	 *
	 */
	public function import()
	{
		$this->setMode(self::MODE_PROCESS);
		$this->setXMLContent($this->getXml());
		$this->startParsing();

		// If we should return an error document to the client (HTTP Response), we can logically validate
		// the XML document and pass false here if we found any errors. Currently we skip invalid records.

		return true;
	}

	/**
	 * @param $a_xml_parser
	 */
	public function setHandlers($a_xml_parser)
	{
		xml_set_object($a_xml_parser,$this);
		xml_set_element_handler($a_xml_parser, 'handlerBeginTag', 'handlerEndTag');
		xml_set_character_data_handler($a_xml_parser, 'handlerCharacterData');
	}

	/**
	 * @param        $a_xml_parser
	 * @param string $a_name
	 * @param array  $a_attribs
	 */
	public function handlerBeginTag($a_xml_parser, $a_name, array $a_attribs)
	{
		switch($this->getMode())
		{
			case self::MODE_VALIDATION:
				$this->validateBeginTag($a_xml_parser, $a_name, $a_attribs);
				break;

			case self::MODE_PROCESS:
			default:
				$this->importBeginTag($a_xml_parser, $a_name, $a_attribs);
				break;
		}
	}

	/**
	 * @param $a_xml_parser
	 * @param string $a_name
	 */
	public function handlerEndTag($a_xml_parser, $a_name)
	{
		switch($this->getMode())
		{
			case self::MODE_VALIDATION:
				$this->validateEndTag($a_xml_parser, $a_name);
				break;

			case self::MODE_PROCESS:
			default:
				$this->importEndTag($a_xml_parser, $a_name);
				break;
		}
	}

	/**
	 * @param        $a_xml_parser
	 * @param string $a_name
	 * @param array  $a_attribs
	 */
	protected function validateBeginTag($a_xml_parser, $a_name, array $a_attribs)
	{
	}

	/**
	 * @param        $a_xml_parser
	 * @param string $a_name
	 */
	protected function validateEndTag($a_xml_parser, $a_name)
	{
	}

	/**
	 * @param        $a_xml_parser
	 * @param string $a_name
	 * @param array  $a_attribs
	 */
	protected function importBeginTag($a_xml_parser, $a_name, array $a_attribs)
	{
		switch($a_name)
		{
			case 'Employee':
				$this->current_user = new ilObjUser();
				break;

			case 'BasicData':
				$this->in_basic_data = true;
				break;
		}

		$this->cdata = '';
	}

	/**
	 * @param        $a_xml_parser
	 * @param string $a_name
	 */
	protected function importEndTag($a_xml_parser, $a_name)
	{
		switch($a_name)
		{
			case 'Employee':

				if(!strlen($this->current_user->getLogin()))
				{
					$this->log->warn("Did not find a USRID, skipped record.");
					break;
				}

				$usr_id = ilObjUser::_lookupId($this->current_user->getLogin());
				if($usr_id > 0)
				{
					$user = new ilObjUser($usr_id);
					$user->setActive($this->current_user->getActive());
					$user->setEmail($this->current_user->getEmail());
					$user->setFirstname($this->current_user->getFirstname());
					$user->setLastname($this->current_user->getLastname());
					$user->setMatriculation($this->current_user->getMatriculation());
					$user->setZipcode($this->current_user->getZipcode());
					$user->setStreet($this->current_user->getStreet());
					$user->setCity($this->current_user->getCity());
					$user->setCountry($this->current_user->getCountry());
					$user->setSelectedCountry($this->current_user->getSelectedCountry());
					$user->setPhoneOffice($this->current_user->getPhoneOffice());
					$user->setGender($this->current_user->getGender());

					$language_code = $this->current_user->getPref('language');
					$user->setPref('language', $language_code ? $language_code : 'en');
					
					$user->setTitle($user->getFullname());
					$user->setDescription($user->getEmail()); 

					$user->setUserDefinedData(array_merge(
						array_map(function($val) {
							return substr($val, 2);
						}, $user->getUserDefinedData()),
						array_map(function($val) {
							return substr($val, 2);
						}, $this->current_user->getUserDefinedData())
					));

					$user->update();

					$this->current_user = $user;

					$this->removeAllAutoGeneratedOrgUnitAssignments();
				}
				else
				{
					$this->current_user->setApproveDate(date('Y-m-d H:i:s'));
					$this->current_user->setTimeLimitOwner(USER_FOLDER_ID);
					$this->current_user->setTimeLimitUnlimited(1);
					$this->current_user->setTimeLimitMessage(0);
					$this->current_user->setTitle($this->current_user->getFullname());
					$this->current_user->setDescription($this->current_user->getEmail());

					$password = current(ilUtil::generatePasswords(1));
					$this->current_user->setPasswd(md5($password), IL_PASSWD_MD5);

					$this->current_user->create();
					$this->current_user->saveAsNew(false);

					$this->current_user->writePrefs();
				}

				$this->log->info(
					sprintf(
						"Successfully imported user %s/%s",
						$this->current_user->getLogin(), $this->current_user->getId()
					),
					array('import_success' => $this->current_user->getId())
				);

				$udf_id = $this->getUdfIdByName(self::UDF_NAME_APTER_SITE);
				if($udf_id < 1)
				{
					$this->log->crit(sprintf("Could not find a valid UDF for name '%s'.", self::UDF_NAME_APTER_SITE));
					break;
				}

				$udf_data = $this->current_user->getUserDefinedData();
				$import_id = $udf_data['f_' . $udf_id];

				if(!strlen($import_id))
				{
					$this->log->warn(sprintf(
						"Skipped organisational unit assignment. Could not find a value for UDF with id '%s' in context of user %s/%s.",
						$udf_id, $this->current_user->getLogin(), $this->current_user->getId()
					));
					break;
				}

				$org_unit_id = ilOrgUnit::lookupIdByImportId($import_id);
				if($org_unit_id < 1)
				{
					$this->log->warn(sprintf(
						"Skipped organisational unit assignment. Could not find an organisational unit with import_id %s in context of user %s/%s.",
						$import_id, $this->current_user->getLogin(), $this->current_user->getId()
					));
					break;
				}

				$org_role_id   = 0;
				foreach($this->org_roles as $template)
				{
					if(self::DEFAULT_ORG_ROLE_NAME == $template['template_name'])
					{
						$org_role_id = $template['id'];
						break;
					}
				}

				if($org_role_id < 1)
				{
					$this->log->crit(sprintf(
						"Skipped organisational unit assignment. Could not find an organisational unit role with name %s in context of user %s/%s.",
						self::DEFAULT_ORG_ROLE_NAME, $this->current_user->getLogin(), $this->current_user->getId()
					));
					break;
				}

				$unit = ilOrgUnit::getInstanceById($org_unit_id, true);
				$unit->ensureAssignsInitialised();
				if(!$unit->isUserAssigned($this->current_user->getId()))
				{
					$assignment = new ilOrgUnitAssignment();
					$assignment->setOrgUnitId($unit->getId());
					$assignment->setUserId($this->current_user->getId());
					$assignment->setOwnerId(ROLE_FOLDER_ID);
					$unit->getAssignmentList()->addAssignment($assignment);

					ilOrgRoleHelper::setOrgRoleDataForAssignmentByNamedImporter(
						$this->current_user->getId(), $unit->getId(), $org_role_id, ROLE_FOLDER_ID
					);
				}
				break;

			case 'BasicData':
				$this->in_basic_data = false;
				break;

			case 'PERNR':
				if($this->in_basic_data)
				{
					$this->current_user->setMatriculation($this->cdata);
				}
				break;
			
			case 'USRID':
				$this->current_user->setLogin($this->cdata);
				break;

			case 'STAT2':
				if(3 == $this->cdata)
				{
					$this->current_user->setActive(1);
				}
				else if(in_array($this->cdata, array(0, 1, 2)))
				{
					$this->current_user->setActive(0);
				}
				else
				{
					$this->log->warn(sprintf("Find an undefined value for STAT2: %s.", $this->cdata));
				}
				break;
			
			case 'USRID_LONG':
				$this->current_user->setEmail($this->cdata);
				break;
			
			case 'VORNA':
				$this->current_user->setFirstname($this->cdata);
				break;
			
			case 'NACHN':
				$this->current_user->setLastname($this->cdata);
				break;
			
			case 'WERKS':
				$udf_id = $this->getUdfIdByName(self::UDF_NAME_APTER_SITE);
				if($udf_id > 0)
				{
					$this->current_user->setUserDefinedData(array(
						$udf_id => $this->cdata
					));
				}
				break;

			case 'AREAC':
				$this->current_user->setZipcode($this->cdata);
				break;
			
			case 'STRAS_40':
				$this->current_user->setStreet($this->cdata);
				break;

			case 'ORT01_40':
				$this->current_user->setCity($this->cdata);
				break;
			
			case 'TELNR':
				$this->current_user->setPhoneOffice($this->cdata);
				break;
			
			case 'LAND1':
				$this->current_user->setSelectedCountry(strtolower($this->cdata));
				$this->current_user->setCountry($this->cdata);
				break;
			
			case 'NATIO':
				$this->current_user->setPref('language', strtolower($this->cdata));
				break;
			
			case 'GESCH':
				switch($this->cdata)
				{
					case 'm':
					case 1:
						$this->current_user->setGender('m');
						break;

					case 'f':
					case 2:
					default:
						$this->current_user->setGender('f');
						break;
				}

				break;

			case 'ZSEGM':
				$udf_id = $this->getUdfIdByName(self::UDF_NAME_SEGMENT);
				if($udf_id > 0)
				{
					$this->current_user->setUserDefinedData(array(
						$udf_id => $this->cdata
					));
				}
				break;
		}
	}

	/**
	 * @param $a_xml_parser
	 * @param string $a_data
	 */
	public function handlerCharacterData($a_xml_parser, $a_data)
	{
		if($a_data != "\n")
		{
			$a_data = preg_replace("/\t+/"," ",$a_data);
		}

		if(strlen($a_data) > 0)
		{
			$this->cdata .= $a_data;
		}
	}
}
// aptar-patch: end