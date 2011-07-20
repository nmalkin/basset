<?php
require_once('sessionvariables.php');
require_once('stepvariables.php');
require_once('grouproundvariables.php');

/**
 * Class containing a reference to each kind of UserVariable that is accessible to BASSET users.
 */
class BassetVariables {
    // the actual variables
    protected $session_variables;
    protected $step_variables;
    protected $group_variables;
    
    public function __construct(Session $session) {
        $this->session_variables = new SessionVariables($session);
        
        /*
         * Basset variables are set at the end of one round,
         * then accessed when the next round has already started.
         * However, for the user writing the template, the "current" round
         * should actually still be the last round.
         * We therefore use the status of the current session to determine 
         * where in the process we are.
         */
        if($session->getStatus() == Session::finished_step) { 
            // we are at the end of a step, where the variables are set.
            // we use the current round in the session
            $round = $session->currentRound();
        } elseif ($session->getStatus() == Session::awaiting_user_input) {
            // we are at the beginning of a step, where variables are read.
            // we want to use the previous step/repetition
            try {
                $round = $session->currentRound()->previousRound();
            } catch(DoesNotExistException $e) {
                // A DoesNotExistException thrown by previousRound means this is the first step.
                // There cannot be any step variables.
                // So we set them to NULL and exit.
                $this->step_variables = NULL;
                return;
                //TODO: create a blank instance of UserVariables, instead of NULL?
            }
        } else {
            throw new Exception('unknown use case for BassetVariables (created with status ' . $session->getStatus() . ')');
        }
        
        $this->step_variables = new StepVariables($session, $round);
        
        try {
            $group = $session->getGroup($round);
            $this->group_variables = new GroupRoundVariables($group, $session, $round);
        } catch(DoesNotExistException $e) {
            // there is no group for this step
            $this->group_variables = NULL;
        }
        
    }
    
    public function __get($name) {
        switch($name) {
            case 'session':
                return $this->session_variables;
                break;
            case 'step':
                return $this->step_variables;
                break;
            case 'group':
                return $this->group_variables;
                break;
            default:
                throw new Exception('Undefined property: ' . get_class($this) . '::' . $name); //TODO: different exception
        }
    }
    
    public function save() {
        $this->session_variables->save();
        $this->step_variables->save();
        $this->group_variables->save();
    }
}