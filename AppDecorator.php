<?php
class AppDecorator implements IteratorAggregate, ArrayAccess, Countable /*, JsonSerializable /* PHP 5.4 */ {
    public $decorate = array(); // List of associations to decorate on instantiation
    public $children = array(); // Child nodes from threaded find results

    protected $attributes = array();
    protected $associations = array();
    public $serializableAttributes = null;
    public $serializableAssociations = null;

    private $modelName = null;
    private $isCollection = false;

    private static $_HtmlHelper = null;

    public function __construct($_attributes = null, $modelName = null) {
        if ($modelName) {
            $this->modelName = $modelName;
        }

        if ($_attributes) {
            $this->set($_attributes);
        }
    }

    public function count() {
        if ($this->isCollection) {
            return count($this->attributes);
        } else {
            return empty($this->attributes) ? 0 : 1;
        }
    }

    /**
     * @implements IteratorAggregate
     * @return ArrayIterator
     */
    public function getIterator() {
        return new ArrayIterator($this->attributes);
    }

    /**
     * @implements ArrayAccess
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value) {
        if (is_null($offset)) {
            $this->attributes[] = $value;
        } else {
            $this->attributes[$offset] = $value;
        }
    }

    /**
     * @implements ArrayAccess
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset) {
        return isset($this->attributes[$offset]);
    }

    /**
     * @implements ArrayAccess
     * @param mixed $offset
     */
    public function offsetUnset($offset) {
        unset($this->attributes[$offset]);
    }

    /**
     * @implements ArrayAccess
     * @param mixed $offset
     * @return null
     */
    public function offsetGet($offset) {
        return isset($this->attributes[$offset]) ? $this->attributes[$offset] : null;
    }

    public function set($_attributes) {
        if (is_array($_attributes)) {
            if (array_key_exists($this->getModelName(), $_attributes)) {
                $modelAttributes = $_attributes[$this->getModelName()];
                unset($_attributes[$this->getModelName()]);

                // What remains must all be associations or 'children' (threaded results)
                $this->associations = $_attributes;
                $_attributes = $modelAttributes;
            } else {
                $decoratorClass = $this->getModelName() . 'Decorator';
                foreach ($_attributes as $key => $data) {
                    if (is_int($key) && is_array($data)) {
                        $this->isCollection = true;

                        if ( ! class_exists($decoratorClass)) {
                            App::uses($decoratorClass, 'Decorator');
                        }

                        if (class_exists($decoratorClass)) {
                            $_attributes[$key] = new $decoratorClass($data);
                        }
                    }
                }
            }
        }

        $this->attributes = $_attributes;

        foreach ($this->associations as $associationName => $values) {
            // Instantiate decorator for associated data
            if ( ! $this->decorateAssociation($associationName, false)) {
                // leave as array if no decorator is defined
                $this->$associationName = $values;
            }
        }

        // Decorate the association as requested by the parent decorator class
        foreach ($this->decorate as $associationName) {
            if ( ! isset($this->associations[$associationName])
                && isset($this->attributes[$associationName])) {
                $this->associations[$associationName] = $this->attributes[$associationName];
            }
            $this->decorateAssociation($associationName, false);
        }
    }

    public function getModelName() {
        if ($this->modelName) {
            return $this->modelName;
        }

        if (get_class($this) == 'AppDecorator' && ! empty($this->attributes)) {
            $this->modelName = key(current($this->attributes));
        } else {
            $this->modelName = str_replace('Decorator', '', get_class($this));
        }

        return $this->modelName;
    }

    public function getAttribute($attribute) {
        if (array_key_exists($attribute, $this->attributes)) {
            return $this->attributes[$attribute];
        }
        return null;
    }

    public function getAttributes() {
        return $this->attributes;
    }

    public function __get($attribute) {
        if (method_exists($this, $attribute)) {
            return $this->$attribute();
        }

        if (array_key_exists($attribute, $this->attributes)) {
            // Note: isset() test is inappropriate as NULL is an acceptable value
            return $this->attributes[$attribute];
        }

        if (isset($this->associations[$attribute])) {
            return $this->associations[$attribute];
        }

        if ($attribute == 'Html') {
            if (is_null(self::$_HtmlHelper)) {

                App::uses('HtmlHelper', 'View/Helper');

                if (class_exists('HtmlHelper')) {
                    self::$_HtmlHelper = new HtmlHelper(new View());
                }
            }

            return self::$_HtmlHelper;
        }

        show_error("$attribute is not defined in {$this->getModelName()}", E_USER_NOTICE);

        return null;
    }

    public function __isset($attribute) {
        if (method_exists($this, $attribute)) {
            return true;
        }
        return isset($this->attributes[$attribute]);
    }

    public function jsonSerialize($attributes = null, $associations = null) {
        $return = array();

        if ($this->isCollection) {
            foreach ($this->attributes as $item) {
                $return[] = $item->jsonSerialize($attributes, $associations);
            }

            return $return;
        }

        if ($attributes === false || $this->serializableAttributes === false) {
            // do nothing, we are not returning any attributes or methods
        } else if (func_num_args() > 0 || $this->serializableAttributes !== null) {
            # passed attribute takes precedence over the class property
            $keys = func_num_args() > 0 ? $attributes : $this->serializableAttributes;

            # null or true will return all attributes
            if ($keys === true || $keys === null) {
                $return = $this->attributes;
            } else if (is_array($keys)) {
                # only return the requested methods and attributes
                foreach ($keys as $key) {
                    if (method_exists($this, $key)) {
                        $return[$key] = $this->$key;
                    } else if (isset($this->attributes[$key])) {
                        $return[$key] = $this->attributes[$key];
                    }
                }
            }

        } else {
            $return = $this->attributes;
        }

        if ($associations === false || $this->serializableAssociations === false) {
            $keys = array();
        } else if ( ! empty($associations) || ! empty($this->serializableAssociations)) {
            $keys = empty($associations) ? $this->serializableAssociations : $associations;
        } else {
            $keys = array_keys($this->associations);
        }

        foreach (array_intersect_key($this->associations, array_flip($keys)) as $name => $association) {
            if (is_array($association)) {
                foreach ($association as $i => $item) {
                    $return[$name][$i] = $item->jsonSerialize();
                }
            } else {
                $return[$name] = $association->jsonSerialize();
            }
        }

        return $return;
    }

    public function setAssociation($name, $data, $decorate = false) {
        if (is_subclass_of($data, 'AppDecorator')) {
            $this->associations[$name] = $data->attributes;
            $this->$name = $data;
        } else {
            $this->associations[$name] = $data;
            if ($decorate) {
                $this->decorateAssociation($name);
            }
        }
    }

    public function decorateAssociation($name, $showErrors = true) {
        if ($name == 'children'
            && isset($this->associations['children']) && is_array($this->associations['children'])
        ) {
            // This data row contains children from a find('threaded') query
            if ( ! empty($this->associations['children'])) {
                $modelName = key(current($this->associations['children']));
                $this->children = new AppDecorator($this->associations['children'], $modelName);
            }

            unset($this->associations['children']);

            return true;
        }

        if (isset($this->associations[$name])
            && is_subclass_of($this->associations[$name], 'AppDecorator')
            || (isset($this->associations[$name])
                && is_array($this->associations[$name])
                && isset($this->associations[$name][0])
                && is_subclass_of($this->associations[$name][0], 'AppDecorator'))
        ) {
            if ($showErrors) trigger_error("Association $name already decorated", E_USER_NOTICE);
            return false;
        }

        if ( ! array_key_exists($name, $this->associations)) {
            if ($showErrors) trigger_error("Association $name does not exist", E_USER_ERROR);
            return false;
        }


        // Instantiate current model to figure out the association class name needed
        $model = new $this->getModelName();
        $className = $name;

        if (isset($model->binds[$name]['className'])) {
            $decoratorClass = $model->binds[$name]['className'] . 'Decorator';
            $className = $model->binds[$name]['className'];
        } else {
            // Must be a temporary bind, try the array key as the class name
            $decoratorClass = $name . 'Decorator';
        }

        if ( ! class_exists($decoratorClass)) {
            App::uses($decoratorClass, 'Decorator');
        }

        if ( ! class_exists($decoratorClass)) {
            $decoratorClass = 'AppDecorator';
        } else if ( ! is_subclass_of($decoratorClass, 'AppDecorator')) {
            if ($showErrors) trigger_error("$decoratorClass is not a subclass of AppDecorator", E_USER_ERROR);
            return false;
        }

        // Check if we need to decorate hasMany/HABTM or hasOne/belongsTo
        if (isset($model->binds[$name])
            && in_array($model->binds[$name]['bindType'], array('hasMany', 'hasAndBelongsToMany'))
            && is_array($this->associations[$name])
            && count(array_filter(array_keys($this->associations[$name]), 'is_string')) == 0 // Make sure we only have associative keys
        ) {
            $collection = array();
            foreach ($this->associations[$name] as $index => $values) {
                $this->associations[$name][$index] = new $decoratorClass($this->associations[$name][$index], $className);
                $collection[] = $this->associations[$name][$index];
            }

            $this->$name = $collection;
        } else {
            $this->associations[$name] = new $decoratorClass($this->associations[$name], $className);
            $this->$name = $this->associations[$name];
        }

        return true;
    }
}
