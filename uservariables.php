<?php
/**
 * Class for custom user data specified through an object interface.
 * i.e., users are able to assign and retrieve variables by referencing them as [class]->variable
 * Used to get and store step, session, and group data.
 * It is abstract so that specific implementations can decide where and how to save the data.
 *
 * This class relies on the PHP language feature, property overloading.
 * see: http://php.net/manual/en/language.oop5.overloading.php
 */
abstract class UserVariables {
    protected $data;

    public function __construct() {
        $this->data = array();
    }

    public function __set($name, $value) {
        $this->data[$name] = $value;
    }

    public function __get($name) {
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        } else {print_r($this->data);
            throw new Exception("requesting undefined property $name");
        }

//        $trace = debug_backtrace();
//        trigger_error(
//            'Undefined property via __get(): ' . $name .
//            ' in ' . $trace[0]['file'] .
//            ' on line ' . $trace[0]['line'],
//            E_USER_NOTICE);
//        return null;
    }

    public function __isset($name) {
        return isset($this->data[$name]);
    }

    public function __unset($name) {
        unset($this->data[$name]);
    }

    /**
     * Save the user data.
     */
    abstract public function save();
}