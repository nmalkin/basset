<?php
require_once('uservariables.php');

class StepVariables extends UserVariables {
    /** A reference to the session for which these variables are being saved. */
    protected $session;
    /** The key under which the StepVariables are saved in Session->data. */
    protected $key;
    
    public function __construct(Session $session, Step $step, $repetition = NULL) {
        $this->session = $session;
        $this->key = self::makeKey($step, $repetition);

        if(array_key_exists($this->key, $this->session->data) &&
            is_array($this->session->data[$this->key]))
        {
            $this->data = $this->session->data[$this->key];
        } else { // no step vars set yet
            $this->data = array();
        }
    }

    public function save() {
        $this->session->data[$this->key] = $this->data;
        $this->session->save();
    }
    
    /** Returns a unique key under which we will save the StepVariables in Session->data. */
    protected static function makeKey(Step $step, $repetition) {
//        $repetition_string = is_null($repetition) ? '' : '_repetition_' . $repetition;
//        return 'step_' . $step->order() . $repetition_string . '_vars';
        return 'step_uservariables';
    }
}