<?php

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once "Services/Table/classes/class.ilTable2GUI.php";

/**
 * Class ilExcCriteriaCatalogueTableGUI
 *
 * @author Jörg Lützenkirchen <luetzenkirchen@leifos.com>
 * @ingroup ModulesExercise
 */
class ilExcCriteriaCatalogueTableGUI extends ilTable2GUI
{
	protected $exc_id; // [int]

	public function __construct($a_parent_obj, $a_parent_cmd, $a_exc_id)
	{
		global $ilCtrl, $lng;
		
		$this->exc_id = $a_exc_id;
		$this->setId("exccritcat".$this->exc_id);
		
		parent::__construct($a_parent_obj, $a_parent_cmd);
		
		$this->setLimit(9999); // because of manual order
	
		$this->setTitle($lng->txt("exc_criteria_catalogues"));
		
		$this->addColumn("", "", "1", true);
		$this->addColumn($this->lng->txt("position"), "pos", "10%");	
		$this->addColumn($this->lng->txt("title"), "title");	
		$this->addColumn($this->lng->txt("exc_criterias"));
		$this->addColumn($this->lng->txt("actions"));
		
		$this->setDefaultOrderField("pos");
		$this->setDefaultOrderDirection("asc");
	
		$this->setFormAction($ilCtrl->getFormAction($a_parent_obj));
		$this->setRowTemplate("tpl.exc_crit_cat_row.html", "Modules/Exercise");	
		$this->setSelectAllCheckbox("id");

		$this->addMultiCommand("confirmDeletion", $lng->txt("delete"));	
		
		if($this->getItems())
		{
			$this->addCommandButton("saveOrder", $lng->txt("exc_save_order"));
		}		
	}
	
	protected function getItems()
	{
		$data = array();
		
		include_once "Modules/Exercise/classes/class.ilExcCriteria.php";
		
		$pos = 0;
		foreach(ilExcCriteriaCatalogue::getInstancesByParentId($this->exc_id) as $item)
		{
			$pos += 10;
			
			$crits = array();
			foreach(ilExcCriteria::getInstancesByParentId($item->getId()) as $crit)
			{
				$crits[] = array($crit->getTranslatedType(), $crit->getTitle());
			}
			
			$data[] = array(
				"id" => $item->getId()
				,"pos" => $pos
				,"title" => $item->getTitle()
				,"criterias" => $crits
			);
		}
		
		$this->setData($data);
		
		return (bool)sizeof($data);
	}
	
	public function numericOrdering($a_field)
	{
		return in_array($a_field, array("pos"));
	}
	
	protected function fillRow($a_set)
	{
		global $lng, $ilCtrl;

		$this->tpl->setVariable("ID", $a_set["id"]);
		$this->tpl->setVariable("POS", $a_set["pos"]);
		$this->tpl->setVariable("TITLE", $a_set["title"]);
		
		$ilCtrl->setParameter($this->getParentObject(), "cat_id", $a_set["id"]);
		$url = $ilCtrl->getLinkTarget($this->getParentObject(), "edit");
		
		if(sizeof($a_set["criterias"]))
		{
			$this->tpl->setCurrentBlock("crit_bl");
			foreach($a_set["criterias"] as $crit)
			{
				$this->tpl->setVariable("CRIT_TYPE", $crit[0]);
				$this->tpl->setVariable("CRIT_TITLE", $crit[1]);
				$this->tpl->parseCurrentBlock();
			}
		}
				
		$this->tpl->setCurrentBlock("action_bl");
		$this->tpl->setVariable("ACTION_URL", $url);
		$this->tpl->setVariable("ACTION_TXT", $lng->txt("edit"));
		$this->tpl->parseCurrentBlock();
				
		$url = $ilCtrl->getLinkTargetByClass("ilExcCriteriaGUI", "");
		
		$this->tpl->setCurrentBlock("action_bl");
		$this->tpl->setVariable("ACTION_URL", $url);
		$this->tpl->setVariable("ACTION_TXT", $lng->txt("exc_edit_criterias"));
		$this->tpl->parseCurrentBlock();
		
		$ilCtrl->setParameter($this->getParentObject(), "cat_id", "");
		
	}
}