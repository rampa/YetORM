<?php

/**
 * This file is part of the YetORM library
 *
 * Copyright (c) 2013 Petr Kessler (http://kesspess.1991.cz)
 *
 * @license  MIT
 * @link     https://github.com/uestla/YetORM
 */

namespace YetORM;

use Nette;
use Nette\Database\Table\ActiveRow as NActiveRow;


abstract class Entity extends Nette\Object
{

	/** @var Row */
	protected $row;

	/** @var array */
	private static $reflections = array();



	/** @param  NActiveRow */
	function __construct(NActiveRow $row = NULL)
	{
		$this->row = new Row($row);
	}



	/**
	 * @param  string
	 * @param  string
	 * @param  string
	 * @param  string
	 * @return EntityCollection
	 */
	protected function getMany($entity, $relTable, $entityTable, $throughColumn = NULL)
	{
		return new EntityCollection($this->row->related($relTable), $entity, $entityTable, $throughColumn);
	}



	/** @return Row */
	final function toRow()
	{
		return $this->row;
	}



	/**
	 * Looks for all public get* methods and @property[-read] annotations
	 * and returns associative array with corresponding values
	 *
	 * @return array
	 */
	function toArray()
	{
		$ref = static::getReflection();
		$values = array();

		foreach ($ref->getEntityProperties() as $name => $property) {
			$value = $this->$name;
			if (!($value instanceof EntityCollection || $value instanceof Entity)) {
				$values[$name] = $value;
			}
		}

		return $values;
	}



	/**
	 * @param  string
	 * @param  array
	 * @return mixed
	 */
	final function __call($name, $args)
	{
		try {
			return parent::__call($name, $args);

		} catch (Nette\MemberAccessException $e) {
			if (strlen($name) > 3) {
				$prefix = substr($name, 0, 3);
				if ($prefix === 'set') { // set<Property>
					$this->__set(lcfirst(substr($name, 3)), reset($args));
					return $this;

				} elseif ($prefix === 'get') { // get<Property>
					return $this->__get(lcfirst(substr($name, 3)));
				}
			}

			throw new Exception\MemberAccessException($e->getMessage(), $e->getCode(), $e->getPrevious());
		}
	}



	/**
	 * @param  string
	 * @return mixed
	 */
	final function & __get($name)
	{
		try {
			return parent::__get($name);

		} catch (Nette\MemberAccessException $e) {
			if ($prop = static::getReflection()->getEntityProperty($name)) {
				$value = $prop->setType($this->row->{$prop->column});
				return $value;
			}

			throw new Exception\MemberAccessException($e->getMessage(), $e->getCode(), $e->getPrevious());
		}
	}



	/**
	 * @param  string
	 * @param  mixed
	 * @return void
	 */
	final function __set($name, $value)
	{
		try {
			return parent::__set($name, $value);

		} catch (Nette\MemberAccessException $e) {
			$prop = static::getReflection()->getEntityProperty($name);
			if ($prop && !$prop->readonly) {
				$prop->checkType($value);
				$this->row->{$prop->column} = $value;
				return ;
			}

			throw new Exception\MemberAccessException($e->getMessage(), $e->getCode(), $e->getPrevious());
		}
	}



	/**
	 * @param  string
	 * @return bool
	 */
	final function __isset($name)
	{
		return parent::__isset($name) || static::getReflection()->hasEntityProperty($name);
	}



	/**
	 * @param  string
	 * @return void
	 * @throws E\NotSupportedException
	 */
	final function __unset($name)
	{
		throw new Exception\NotSupportedException;
	}



	/** @return Reflection\EntityType */
	final static function getReflection()
	{
		$class = get_called_class();
		if (!isset(self::$reflections[$class])) {
			self::$reflections[$class] = new Reflection\EntityType($class);
		}

		return self::$reflections[$class];
	}



	/** @throws E\NotSupportedException */
	final static function extensionMethod($name, $callback = NULL)
	{
		throw new Exception\NotSupportedException;
	}

}
