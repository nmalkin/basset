<?php
require_once('sessionvariables.php');
require_once('stepvariables.php');

/**
 * Class containing a reference to each kind of UserVariable that is accessible to BASSET users.
 */
class BassetVariables {
    protected $session;
    protected $step;

    public function __construct(Session $session) {
        $this->session = new SessionVariables($session);
        $this->step = new StepVariables($session, $session->current_step, $session->repetition); // repetition is NULL if step isn't repeated
    }

    /**
     * Rather than allowing direct access to the BASSET variables,
     * we channel the requests for them through this getter.
     * We can then provide the user with a reference to the required class,
     * but prevent them from modifying the reference.
     *
     * Inspired by:
     * http://stackoverflow.com/questions/3600777/read-only-properties-in-php/3600847#3600847
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name) {
        return isset($this->$name) ? $this->$name : NULL;
    }

    /**
     * Make sure everything that needs to be is saved.
     */
    public function save() {
        $this->session->save();
        $this->step->save();
    }
}