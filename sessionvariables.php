<?php
require_once('uservariables.php');

class SessionVariables extends UserVariables {
    /** A reference to the session for which these variables are being saved. */
    protected $session;

    /** The key under which we will save the SessionVariables in Session->data. */
    const session_vars_key = 'session_uservariables';

    /** Constructs this SessionVariables instance, being tied to the given session. */
    public function __construct(Session $session) {
        $this->session = $session;

        if(array_key_exists(self::session_vars_key, $this->session->data) &&
            is_array($this->session->data[self::session_vars_key]))
        {
            $this->data = $this->session->data[self::session_vars_key];
        } else { // no session vars set yet
            $this->data = array();
        }
    }

    public function save() {
        $this->session->data[self::session_vars_key] = $this->data;
        $this->session->save();
    }
}