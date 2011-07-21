<?php
/**
 * A Round is a specific repetition of a Step in a game.
 */
class Round {
    /**
     * @var Step $step the step in the game
     */
    public $step;
    /**
     *
     * @var int $repetition the repetition of the step in the game
     */
    public $repetition;
    
    /**
     * Constructs a new Round.
     * 
     * @param Step $step
     * @param int $repetition NULL values will be treated as repetition 0.
     */
    public function __construct(Step $step, $repetition) {
        $this->step = $step;
        $this->repetition =  is_null($repetition) ? 0 : $repetition;
    }
    
    /**
     * Returns the next round in the game.
     * If this is a repeated step, then the next round is the next repetition of this step
     * (unless this is the last repetition).
     * Otherwise, it is the 0-th repetition of the next step.
     * 
     * @see Step::nextStep()
     * @throws DoesNotExistException if there is no next step
     * @return Round the next round
     */
    public function nextRound() {
        if($this->repetition < $this->step->repeat) {
            return new Round($this->step, $this->repetition + 1);
        } else {
            return new Round($this->step->nextStep(), 0);
        }
    }
    
    /**
     * Returns the previous round in the game.
     * If this is a repeated step, then this is the previous repetition of this step
     * (unless this is the first repetition).
     * Otherwise, it is the 0-th repetition of the previous step.
     * 
     * @throws DoesNotExistException if there is no previous step
     * @return Round the previous round
     */
    public function previousRound() {
        if($this->repetition == 0) {
            $previous = $this->step->order() - 1;
            
            if(isset($this->step->game->steps[$previous])) {
                return new Round($this->step->game->steps[$previous], 0);
            } else {
                throw new DoesNotExistException('this is the first step');
            }
        } else {
            if($this->step->isRepeated()) {
                return new Round($this->step, $this->repetition - 1);
            } else {
                throw new Exception('internal error: this is not a repeated step, so the only legal value for repetition is 0 ' .
                        "(current value is $this->repetition)");
            }
        }
    }
    
    /** 
     * Returns a label identifying this round in the game.
     * The label is unique to steps in this game.
     * 
     * Currently, the format for the label is 'step_X[_repetition_Y]',
     * where X is the order of this step.
     * 
     * @throws InvalidArgumentException if this step is repeated, but no repetition was supplied; or if this is an invalid repetition
     * @return string unique label for this step and repetition
     */
    public function label() {
        $step_identifier = $this->step->order(); //TODO: a better idea might be to use the step code rather than its order
        
        if($this->step->isRepeated()) {
            // validate given repetition
            if(is_null($this->repetition) || (! is_int($this->repetition)) || $this->repetition > $this->step->repeat) {
                throw new InvalidArgumentException("invalid value supplied for repetition ($this->repetition)");
            } else {
                return 'step_' . $step_identifier . '_repetition_' . $this->repetition;
            }
        } else {
            return 'step_' . $step_identifier;
        }
    }
    
    /**
     * Compares this object with the specified object for order. 
     * Returns a negative integer, zero, or a positive integer 
     * as this object is less than, equal to, or greater than the specified object.
     * 
     * @param Round $round the round to compare to this one
     * @return int negative if this round comes before the given one; positive if it comes after; zero if they come at the same time (are the same)
     */
    public function compareTo(Round $round) {
        $my_order = $this->step->order();
        $their_order = $round->step->order();
        
        if($my_order == $their_order) {
            return ($this->repetition - $round->repetition);
        } else {
            return $my_order - $their_order;
        }
    }
    
    
    public function __toString() {
        return $this->label();
    }
}