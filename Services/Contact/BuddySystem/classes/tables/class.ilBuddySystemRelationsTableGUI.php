<?php
/* Copyright (c) 1998-2015 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Services/Table/classes/class.ilTable2GUI.php';
require_once 'Services/Contact/BuddySystem/classes/class.ilBuddyList.php';
require_once 'Services/Contact/BuddySystem/classes/class.ilBuddySystemLinkButton.php';

/**
 * Class ilBuddyList
 * @author Michael Jansen <mjansen@databay.de>
 */
class ilBuddySystemRelationsTableGUI extends ilTable2GUI
{
	
	/**
	 * 
	 */
	const APPLY_FILTER_CMD = 'applyContactsTableFilter';
	
	/**
	 * 
	 */
	const RESET_FILTER_CMD = 'resetContactsTableFilter';

	/**
	 * @var ilCtrl
	 */
	protected $ctrl;

	/**
	 * @var ilTemplate
	 */
	protected $container_tpl;

	/**
	 * @var bool
	 */
	protected $access_to_mail_system = false;

	/**
	 * @var bool
	 */
	protected $chat_enabled = false;

	/**
	 * @param        $a_parent_obj
	 * @param string $a_parent_cmd
	 */
	public function __construct($a_parent_obj, $a_parent_cmd)
	{
		/**
		 * @var $ilCtrl      ilCtrl
		 * @var $tpl         ilTemplate
		 * @var $rbacsystem  ilRbacSystem
		 */
		global $ilCtrl, $tpl, $rbacsystem;

		$this->ctrl           = $ilCtrl;
		$this->container_tpl  = $tpl;

		$this->setId('buddy_system_tbl');
		parent::__construct($a_parent_obj, $a_parent_cmd);

		$this->lng->loadLanguageModule('buddysystem');

		$this->access_to_mail_system = $rbacsystem->checkAccess('internal_mail', ilMailGlobalServices::getMailObjectRefId());

		$chatSettings = new ilSetting('chatroom');
		$this->chat_enabled = $chatSettings->get("chat_enabled", false);

		$this->setDefaultOrderDirection('ASC');
		$this->setDefaultOrderField('public_name');

		$this->setTitle($this->lng->txt('buddy_tbl_title_relations'));

		if($this->access_to_mail_system || $this->chat_enabled)
		{
			$this->addColumn('', 'chb', '1%', true);
			$this->setSelectAllCheckbox('usr_id');
			if($this->access_to_mail_system)
			{
				$this->addMultiCommand('mailToUsers', $this->lng->txt('send_mail_to'));
				$this->addMultiCommand('inviteToChat', $this->lng->txt('invite_to_chat'));
			}
		}

		$this->addColumn($this->lng->txt('name'), 'public_name');
		$this->addColumn('', '');

		$this->setRowTemplate('tpl.buddy_system_relation_table_row.html', 'Services/Contact/BuddySystem');
		$this->setFormAction($this->ctrl->getFormAction($a_parent_obj, $a_parent_cmd));

		$this->setFilterCommand(self::APPLY_FILTER_CMD);
		$this->setResetCommand(self::RESET_FILTER_CMD);

		$this->initFilter();
	}

	/**
	 * {@inheritdoc}
	 */
	public function initFilter()
	{
		$this->filters = array();
		$this->filter  = array();

		require_once 'Services/Contact/BuddySystem/classes/states/class.ilBuddySystemRelationStateFactory.php';

		require_once'Services/Form/classes/class.ilSelectInputGUI.php';
		$relations_state_selection = new ilSelectInputGUI($this->lng->txt('buddy_tbl_filter_state'), 'relation_state_type');

		$options = array();
		$state = ilBuddySystemRelationStateFactory::getInstance()->getStatesAsOptionArray(false);
		foreach($state as $key => $option)
		{
			$options[$key] = $option;
		}
		$relations_state_selection->setOptions(array('' => $this->lng->txt('please_choose')) + $options);
		$this->addFilterItem($relations_state_selection);
		$relations_state_selection->readFromSession();
		$this->filter['relation_state_type'] = $relations_state_selection->getValue();

		require_once 'Services/Form/classes/class.ilTextInputGUI.php';
		$public_name = new ilTextInputGUI($this->lng->txt('name'), 'public_name');
		$this->addFilterItem($public_name);
		$public_name->readFromSession();
		$this->filter['public_name'] = $public_name->getValue();
	}

	/**
	 * 
	 */
	public function populate()
	{
		$this->setExternalSorting(false);
		$this->setExternalSegmentation(false);

		$data = array();

		$relations = ilBuddyList::getInstanceByGlobalUser()->getRelations();

		$state_filter = $this->filter['relation_state_type'];
		$relations = $relations->filter(function(ilBuddySystemRelation $relation) use ($state_filter) {
			return !strlen($state_filter) || strtolower(get_class($relation->getState())) == strtolower($state_filter);
		});

		require_once 'Services/User/classes/class.ilUserUtil.php';
		$public_names = ilUserUtil::getNamePresentation($relations->getKeys(), false, false, '', false, false, false);

		$public_name = $this->filter['public_name'];
		$relations = $relations->filter(function(ilBuddySystemRelation $relation) use ($public_name, $relations, $public_names) {
			return !strlen($public_name) || strpos(strtolower($public_names[$relations->getKey($relation)]), strtolower($public_name)) !== false;
		});

		foreach($relations->toArray() as $usr_id => $relation)
		{
			$data[] = array(
				'usr_id'        => $usr_id,
				'public_name'   => $public_names[$usr_id]
			);
		}

		$this->setData($data);
	}

	/**
	 * Standard Version of Fill Row. Most likely to
	 * be overwritten by derived class.
	 * @param    array $a_set data array
	 */
	protected function fillRow($a_set)
	{
		/**
		 * @var $ilUser ilObjUser
		 */
		global $ilUser;
		
		if($this->access_to_mail_system)
		{
			$a_set['chb'] = ilUtil::formCheckbox(0, 'usr_id[]', $a_set['usr_id']);
		}

		$public_profile = ilObjUser::_lookupPref($a_set['usr_id'], 'public_profile');
		if(!$ilUser->isAnonymous() && $public_profile == 'y' || $public_profile == 'g')
		{
			$this->ctrl->setParameterByClass('ilpublicuserprofilegui', 'user', $a_set['usr_id']);
			$profile_target = $this->ctrl->getLinkTargetByClass('ilpublicuserprofilegui', 'getHTML');
			$a_set['profile_link']       = $profile_target;
			$a_set['linked_public_name'] = $a_set['public_name'];
		}
		else
		{
			$a_set['unlinked_public_name'] = $a_set['public_name'];
		}

		$a_set['contact_actions'] = ilBuddySystemLinkButton::getInstanceByUserId($a_set['usr_id'])->getHtml();
		parent::fillRow($a_set);
	}
}