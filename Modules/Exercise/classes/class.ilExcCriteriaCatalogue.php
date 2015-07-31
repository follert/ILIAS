<?php

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class ilExcCriteriaCatalogue
 *
 * @author Jörg Lützenkirchen <luetzenkirchen@leifos.com>
 * @ingroup ModulesExercise
 */
class ilExcCriteriaCatalogue
{
	protected $id; // [int]
	protected $parent; // [int]
	protected $title; // [string]
	protected $pos; // [int]
	
	public function __construct($a_id = null)
	{	
		$this->read($a_id);		
	}
	
	public static function getInstancesByParentId($a_parent_id)
	{
		global $ilDB;
		
		$res = array();
		
		$set = $ilDB->query("SELECT *".
			" FROM exc_crit_cat".
			" WHERE parent = ".$ilDB->quote($a_parent_id, "integer").
			" ORDER BY pos");
		while($row = $ilDB->fetchAssoc($set))
		{
			$obj = new self();
			$obj->importFromDB($row);
			$res[$obj->getId()] = $obj;
		}	
		
		return $res;
	}
	
	
	//
	// properties
	// 
	
	public function getId()
	{
		return $this->id;
	}
	
	protected function setId($a_id)
	{
		$this->id = (int)$a_id;
	}
	
	public function setParent($a_value)
	{
		$this->parent = ($a_value !== null)
			? (int)$a_value
			: null;
	}
	
	public function getParent()
	{
		return $this->parent;
	}
	
	public function setTitle($a_value)
	{
		$this->title = ($a_value !== null)
			? trim($a_value)
			: null;
	}
	
	public function getTitle()
	{
		return $this->title;
	}

	public function setPosition($a_value)
	{
		$this->pos = (int)$a_value;
	}
	
	public function getPosition()
	{
		return $this->pos;
	}
	
	
	//
	// CRUD
	//
	
	protected function importFromDB(array $a_row)
	{		
		$this->setId($a_row["id"]);
		$this->setParent($a_row["parent"]);
		$this->setTitle($a_row["title"]);
		$this->setPosition($a_row["pos"]);
	}
	
	protected function getDBProperties()
	{
		return array(
			"title" => array("text", $this->getTitle())
			,"pos" => array("integer", $this->getPosition())
		);		
	}
	protected function getLastPosition()
	{
		global $ilDB;
		
		if(!$this->getParent())
		{
			return;
		}
		
		$set = $ilDB->query("SELECT MAX(pos) pos".
			" FROM exc_crit_cat".
			" WHERE parent = ".$ilDB->quote($this->getParent(), "integer"));
		$row = $ilDB->fetchAssoc($set);		
		return (int)$row["pos"];
	}
	
	protected function read($a_id)
	{
		global $ilDB;
		
		$a_id = (int)$a_id;		
		if($a_id)
		{			
			$set = $ilDB->query("SELECT *".
				" FROM exc_crit_cat".
				" WHERE id = ".$ilDB->quote($a_id, "integer"));
			if($ilDB->numRows($set))
			{
				$row = $ilDB->fetchAssoc($set);	
				$this->importFromDB($row);								
			}		
		}
	}
	
	public function save()
	{
		global $ilDB;
		
		if($this->id)
		{
			return $this->update();
		}
		
		$this->id = $ilDB->nextId("exc_crit_cat");
		
		$fields = $this->getDBProperties();
		$fields["parent"] = array("integer", $this->getParent());
		$fields["pos"] = array("integer", $this->getLastPosition()+10);
		$fields["id"] = array("integer", $this->id);
		
		$ilDB->insert("exc_crit_cat", $fields);
	}
	
	public function update()
	{
		global $ilDB;
		
		if(!$this->id)
		{
			return $this->save();
		}
		
		$primary = array("id"=>array("integer", $this->id));		
		$ilDB->update("exc_crit_cat", $this->getDBProperties(), $primary);
	}
	
	public function delete()
	{
		global $ilDB;
		
		if(!$this->id)
		{
			return;
		}
				
		$ilDB->manipulate("DELETE FROM exc_crit_cat".
			" WHERE id = ".$ilDB->quote($this->id, "integer"));
	}
	
	public function deleteByParent()
	{
		global $ilDB;
		
		if(!$this->getParent())
		{
			return;
		}
		
		$ilDB->manipulate("DELETE FROM exc_crit_cat".
			" WHERE parent = ".$ilDB->quote($this->getParent(), "integer"));	
	}
}
