<?php
require_once('step.php');

/**
 * An ExitStep acts and behave like a Step,
 * except that they have their own ordering in the game.
 * Thus, the methods order(), previousStep(), nextStep() return different values.
 * 
 * Note that though the framework for group formation is still there,
 * but it won't work (@see Group::allAlive()).
 */
class ExitStep extends Step {
    
    public function order() {
        for($i = 0; $i < count($this->game->exit_steps); $i++) {
            if($this === $this->game->exit_steps[$i]) { // note that we're comparing by instance (===) not value
                return $i;
            }
        }
        
        throw new Exception('inconsistent data: this step was not found to be one of the steps in its game');
    }
    
    public function nextStep() {
        $next = $this->order() + 1;
        if(isset($this->game->exit_steps[$next])) {
            return $this->game->exit_steps[$next];
        } else {
            throw new DoesNotExistException('this is the last step');
        }
    }
    
    public function previousStep() {
        $previous = $this->order() - 1;
        if(isset($this->game->exit_steps[$previous])) {
            return $this->game->exit_steps[$previous];
        } else {
            throw new DoesNotExistException('this is the first step');
        }
    }
    
    public function __toString() {
        return 'exit_step_' . $this->order(); //TODO: a better idea might be to use the step code rather than its order
    }
}