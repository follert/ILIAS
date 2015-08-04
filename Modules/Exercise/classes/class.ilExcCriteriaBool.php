<?php

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once "Modules/Exercise/classes/class.ilExcCriteria.php";

/**
 * Class ilExcCriteriaBool
 *
 * @author Jörg Lützenkirchen <luetzenkirchen@leifos.com>
 * @ingroup ModulesExercise
 */
class ilExcCriteriaBool extends ilExcCriteria
{
	public function getType()
	{
		return "bool";
	}
	
	
	// PEER REVIEW
	
	public function addToPeerReviewForm($a_value = null)
	{
		global $lng;
		
		if(!$this->isRequired())
		{
			$input = new ilCheckboxInputGUI($this->getTitle(), "prccc_bool_".$this->getId());
			$input->setInfo($this->getDescription());
			$input->setRequired($this->isRequired());
			$input->setChecked($a_value > 0);
		}
		else
		{
			$input = new ilSelectInputGUI($this->getTitle(), "prccc_bool_".$this->getId());
			$input->setInfo($this->getDescription());
			$input->setRequired($this->isRequired());
			$input->setValue($a_value);
			$options = array();
			if(!$a_value)
			{
				$options[""] = $lng->txt("please_select");
			}
			$options[1] = $lng->txt("yes");
			$options[-1] = $lng->txt("no");
			$input->setOptions($options);
		}
		$this->form->addItem($input);
	}
	
	public function importFromPeerReviewForm()
	{
		return (int)$this->form->getInput("prccc_bool_".$this->getId());	
	}
	
	public function hasValue($a_value)
	{
		return (int)$a_value;
	}
	
	public function addToInfo(ilInfoScreenGUI $a_info, $a_value)
	{
		global $lng;
		
		$caption = "&nbsp;";
		if($a_value <= 0 && $this->isRequired())
		{
			$caption = $lng->txt("no");
		}
		else if($a_value == 1)
		{
			$caption = $lng->txt("yes");
		}
		$a_info->addProperty($this->getTitle(), $caption);
	}
	
	public function addToAccordion(array &$a_acc, $a_value)
	{
		$title = $this->getTitle()
			? $this->getTitle().": "
			: "";
		
		$caption = "&nbsp;";
		if($a_value <= 0 && $this->isRequired())
		{
			$caption = $lng->txt("no");
		}
		else if($a_value == 1)
		{
			$caption = $lng->txt("yes");
		}
		
		$a_acc[] = $title.$caption;
	}
}
