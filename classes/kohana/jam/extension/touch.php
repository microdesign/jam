<?php defined('SYSPATH') OR die('No direct script access.');
/**
 * Handles belongs to relationships
 *
 * @package    Jam
 * @category   Associations
 * @author     Ivan Kerin
 * @copyright  (c) 2012 Despark Ltd.
 * @license    http://www.opensource.org/licenses/isc-license.txt
 */
class Kohana_Jam_Extension_Touch extends Jam_Extension {

	public $through;

	public function initialize(Jam_Attribute $attribute)
	{
		if ($attribute instanceof Jam_Association_Collection)
		{
			$attribute->bind('after.after_save', array($this, 'touch_collection'));
		}
		else
		{
			$attribute->bind('after.after_save', array($this, 'touch_model'));	
		}
	}

	public function touch_collection(Jam_Association $association, Jam_Event_Data $data, Jam_Model $model)
	{
		foreach ($model->{$association->name} as $item) 
		{
			if ($this->through)
			{
				$item = $item->{$this->through};
			}
			if ($item AND $item->loaded())
			{
				$item->meta()->events()->trigger('model.touched', $item, array($association));
			}
		}	
	}

	public function touch_model(Jam_Association $association, Jam_Event_Data $data, Jam_Model $model)
	{
		$item = $model->{$association->name};

		if ($item instanceof Jam_Model AND $item->loaded())
		{
			$item->meta()->events()->trigger('model.touched', $item, array($association));
		}
	}
}
