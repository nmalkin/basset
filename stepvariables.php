<?php
require_once('uservariables.php');

//TODO: StepVariables are, more accurately, RoundVariables
class StepVariables extends UserVariables {
    /** A reference to the session for which these variables are being saved. */
    protected $session;
    /** The key under which the StepVariables are saved in Session->data. */
    protected $key;
    
    public function __construct(Session $session, Round $round) {
        $this->session = $session;
        $this->key = self::makeKey($round);

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
    
    /** Returns a key, unique to this round, under which we will save the StepVariables in Session->data. */
    protected static function makeKey(Round $round) {
        return $round->label() . '_uservariables';
    }
}