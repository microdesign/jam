<?php defined('SYSPATH') OR die('No direct script access.');
/**
 * Handles has one to relationships
 *
 * @package    Jam
 * @category   Associations
 * @author     Ivan Kerin
 * @copyright  (c) 2010-2011 OpenBuildings
 * @license    http://www.opensource.org/licenses/isc-license.txt
 */
abstract class Kohana_Jam_Association_HasOne extends Jam_Association {

	public $as = NULL;

	public $foreign_default = 0;

	/**
	 * Automatically sets foreign to sensible defaults.
	 *
	 * @param   string  $model
	 * @param   string  $name
	 * @return  void
	 */
	public function initialize(Jam_Meta $meta, $model, $name)
	{
		// Empty? The model defaults to the the singularized name
		// of this field, and the field defaults to this field's model's foreign key
		if (empty($this->foreign))
		{
			$this->foreign = inflector::singular($name).'.'.Jam::meta($model)->foreign_key();
		}
		// We have a model? Default the field to this field's model's foreign key
		elseif (FALSE === strpos($this->foreign, '.'))
		{
			$this->foreign = $this->foreign.'.'.Jam::meta($model)->foreign_key();
		}

		parent::initialize($meta, $model, $name);

		if ($this->as)
		{
			if ( ! is_string($this->as))
			{
				$this->as = $this->model;
			}
			$this->foreign['as'] = $this->as.'_model';
			$this->foreign['field'] = $this->as.'_id';
		}
	}

	/**
	 * See if the association is polymorphic
	 * @return boolean 
	 */
	public function is_polymorphic()
	{
		return (bool) $this->as;
	}


	public function join(Jam_Builder $builder, $alias = NULL, $type = NULL)
	{
		$join = parent::join($builder, $alias, $type)
			->join($this->foreign(NULL, $alias), $type)
			->on($this->foreign('field', $alias), '=', "{$this->model}.:primary_key");

		if ($this->is_polymorphic())
		{
			$join->on($this->foreign('as', $alias), '=', DB::expr('"'.$this->model.'"'));
		}

		return $join;
	}

	public function builder(Jam_Model $model)
	{
		$model->loaded_insist();
		$builder = parent::builder($model)
			->limit(1)
			->where($this->foreign('field'), '=', $model->id());

		if ($this->as)
		{
			$builder->where($this->foreign('as'), '=', $model->meta()->model());
		}

		return $builder;
	}

	public function get(Jam_Model $model)
	{
		return $model->loaded() ? $this->builder($model)->select() : Jam::factory($this->foreign());
	}

	public function set(Jam_Model $model, $value)
	{
		$item = $this->model_from_array($value);
		return $this->assign_relation($model, $item);
	}

	public function assign_relation(Jam_Model $model, $item)
	{
		$item = parent::assign_relation($model, $item);

		$item->set($this->foreign['field'], $model->id());

		if ($this->is_polymorphic())
		{
			$item->set($this->foreign['as'], $model->meta()->model());
		}

		return $item;
	}

	public function delete(Jam_Model $model, $key)
	{
		switch ($this->dependent) 
		{
			case Jam_Association::DELETE:
				$this->get($model)->delete();
			break;
			case Jam_Association::ERASE:
				$this->builder($model)->delete();
			break;
			case Jam_Association::NULLIFY:
				$this->nullify_builder($model)->update();
			break;
		}
	}

	public function nullify_builder(Jam_Model $model)
	{
		$builder = $this->builder($model)
			->value($this->foreign('field'), $this->foreign_default);

		if ($this->is_polymorphic())
		{
			$builder->value($this->foreign('as'), NULL);
		}

		return $builder;
	}

	public function after_check(Jam_Model $model, Jam_Validation $validation, $new_item)
	{
		if ($this->required)
		{
			if ( ! (($new_item AND $new_item instanceof Jam_Model AND $new_item->check())))
			{
				$validation->error($this->name, 'required');
			}
		}
		
		parent::after_check($model, $validation, $new_item);
	}

	public function after_save(Jam_Model $model, $new_item, $is_changed)
	{
		if ($is_changed)
		{
			$nullify = $this->nullify_builder($model);

			if ($new_item)
			{
				$new_item->set($this->foreign['field'], $model->id());

				if ($this->is_polymorphic())
				{
					$new_item->set($this->foreign['as'], $model->meta()->model());
				}

				$new_item->save();
				
				$nullify->where($this->foreign().':primary_key', '!=', $new_item->id());
			}

			$nullify->update();
		}
	}
} // End Kohana_Jam_Association_HasOne
