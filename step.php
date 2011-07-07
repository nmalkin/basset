<?php
class Step {
    public $game;
    public $repeat;
    public $time_limit; // in seconds
    protected $html_file;
    public $on_complete;
    public $controls;
    protected $group;
    public $group_size;
    protected $group_strangers;
    
    /**
     * Creates a new Step.
     * 
     * @param array $step_data an array of data about this step, including 'step', 'repeat', and 'controls' -- an array
     * @param Game $game the game that this step is a part of
     */
    public function __construct($step_data, Game $game) {
        $this->game = $game;
        //$this->code = $step_data->{'step'};// commented out b/c we may not need this

        // how many times will this step repeat?
        $this->repeat = isset($step_data->{'repeat'}) ? 
        intval($step_data->{'repeat'}) : 0;
        // intval will make any bad values will become 0

        // time limit for step
        $this->time_limit = isset($step_data->{'time_limit'}) ? 
        intval($step_data->{'time_limit'}) : 0;
        // any bad values, as well as the acceptable value false, will become 0
        // 0 = no time limit

        // the html file to be displayed at this step
        if(isset($step_data->{'html'})) {
            $this->html_file = $step_data->{'html'};
        } else {
            throw new ConfigurationSyntaxException('step is missing HTML file');
        }
        
        // group settings
        $this->group = isset($step_data->{'group'}) ? $step_data->{'group'} : FALSE; // TODO: check for allowed values
        $this->group_size = isset($step_data->{'group_size'}) ? intval($step_data->{'group_size'}) : 1; // TODO: check >= 1
        $this->group_strangers = isset($step_data->{'group_strangers'}) ? $step_data->{'group_strangers'} : FALSE; // TODO: check for allowed values

        // callback function to execute on step 
        $this->on_complete = isset($step_data->{'on_complete'}) ? $step_data->{'on_complete'} : FALSE;

        // set up controls
        $this->controls = array();
        foreach($step_data->{'controls'} as $control_data) {
            $this->controls[] = new Control($control_data);
        }
    }
    
    /**
     * Returns the contents of the HTML file for this step.
     */
    public function getHTML() {
        $html_filename = $this->game->directory . $this->html_file;
        
        if(! file_exists($html_filename)) {
            throw new Exception("HTML file $html_filename does not exist");
        }
        
        return file_get_contents($html_filename);
    }

    /**
     * Returns the contents of the HTML file for this step.
     */
    public function getHTMLFilename() {
        $html_filename = $this->game->directory . $this->html_file;

        if(! file_exists($html_filename)) {
            throw new Exception("HTML file $html_filename does not exist");
        }

        return $html_filename;
    }
    
    /**
     * Returns the ID and behavior of each control in this step as an array.
     */
    public function getControls() {
        $controls = array();
        foreach($this->controls as $control) {
            $controls[] = $control->asArray();
        }
        
        return $controls;
    }
    
    /** 
     * Returns the order of this step in the game.
     * (1st? 4th? 7th?)
     * The order is 0-indexed.
     */
    public function order() {
        for($i = 0; $i < $this->game->numberOfSteps(); $i++) {
            if($this === $this->game->steps[$i]) { // note that we're comparing by instance (===) not value
                return $i;
            }
        }
        
        throw new Exception('inconsistent data: this step was not found to be one of the steps in its game');
    }
    
    /**
     * Returns the next step in this game.
     * 
     * @throws DoesNotExistException if this is last step
     */
    public function nextStep() {
        $next = $this->order() + 1;
        if(isset($this->game->steps[$next])) {
            return $this->game->steps[$next];
        } else {
            throw new DoesNotExistException('this is the last step');
        }
    }
    
    /** Does this step need to be repeated? (Will it be run more than once?) */
    public function isRepeated() {
        return $this->repeat > 0;
    }
    
    /** Does this step require partners? */
    public function requiresGroup() {
        return $this->group != FALSE;
    }
    
    /** 
     * Returns a label identifying this step and (if applicable) repetition.
     * The label is unique to steps in this game.
     * 
     * Currently, the format for the label is 'step_X[_repetition_Y]',
     * where X is the order of this step.
     * 
     * @param int the repetition number to include in the label
     * @throws InvalidArgumentException if this step is repeated, but no repetition was supplied; or if this is an invalid repetition
     * @return string unique label for this step and repetition
     */
    public function stepLabel($repetition = NULL) {
        $step_identifier = $this->order(); //TODO: a better idea might be to use the step code rather than its order
        
        if($this->isRepeated()) {
            // validate given repetition
            if(is_null($repetition) || (! is_int($repetition)) || $repetition > $this->repeat) {
                throw new InvalidArgumentException("invalid value supplied for repetition ($repetition)");
            } else {
                return 'step_' . $step_identifier . '_repetition_' . $repetition;
            }
        } else {
            return 'step_' . $step_identifier;
        }
    }
}