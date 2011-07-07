<?php
class Control {
    /** The ID of this control in the HTML. */
    public $id;
    /** The behavior (type) of this control. Must be one of the known $behaviors below. */
    public $behavior;
    /** The subtype of this control, for behaviors that have multiple types. */
    protected $type;
    /** Can this control be empty? */
    protected $not_empty;
    /** For numeric controls, the minimum allowed value. */
    protected $min;
    /** For numeric controls, the maximum allowed value. */
    protected $max;
    /** For numeric, float-value controls, the precision (number of digits after the decimal). */
    protected $precision;
    
    // Behaviors:
    const button = 'submit';
    const input = 'input';
    
    protected static $behaviors = array(self::button, self::input);
    
    // Types of inputs
    const text = 'text';
    const int = 'int';
    const float = 'float';
    
    protected static $types = array(self::text, self::int, self::float);
    
    /** Value for min and max when they are not set. */
    const NA = 'n/a';
    
    /**
     * Constructs a Control object based on an array of data.
     */
    public function __construct($control_data) {
        if(isset($control_data->{'id'})) {
            $this->id = $control_data->{'id'};
        } else {
            throw new ConfigurationSyntaxException('control: missing property id');
        }
        
        if(isset($control_data->{'behavior'}) && in_array($control_data->{'behavior'}, self::$behaviors)) {
            $this->behavior = $control_data->{'behavior'};
        } else {
            throw new ConfigurationSyntaxException('control: missing or unrecognized property behavior');
        }
        
        if($this->behavior == self::input) {
            if(isset($control_data->{'type'}) && in_array($control_data->{'type'}, self::$types)) {
                $this->type = $control_data->{'type'};
            } else {
                throw new ConfigurationSyntaxException('control: missing or unrecognized property type');
            }
            
            if(isset($control_data->{'not_empty'}) && is_bool($control_data->{'not_empty'})) {
                $this->not_empty = $control_data->{'not_empty'};
            } else {
                $this->not_empty = True; // by default, all values are required
            }
            
            if($this->type == self::int || $this->type == self::float) {
                if(isset($control_data->{'min'}) && is_numeric($control_data->{'min'})) {
                    $this->min = $control_data->{'min'};
                } else {
                    $this->min = self::NA;
                }
                
                if(isset($control_data->{'max'}) && is_numeric($control_data->{'max'})) {
                    $this->max = $control_data->{'max'};
                } else {
                    $this->max = self::NA;
                }
            }
            
            if($this->type == self::float) {
                if(isset($control_data->{'precision'}) && is_int($control_data->{'precision'})) {
                    $this->precision = $control_data->{'precision'};
                } else {
                    $this->precision = self::NA;
                }
            }
        }
    }
    
    /** 
     * Returns the ID and behavior of this control as an array.
     * Note that all other properties will not be returned.
     */
    public function asArray() {
        return array('id' => $this->id, 'behavior' => $this->behavior);
    }
    
    /**
     * Checks if the given value is valid input to this control.
     * 
     * @return true if this is valid input, false otherwise
     */
    public function validate($value) {
        if($this->behavior == self::input) {
            if($this->type == self::text) {
                if(empty($value)) {
                    return (! $this->not_empty);
                } else {
                    return True;
                }
            } elseif($this->type == self::int) {
                return
                    is_int($value) &&
                    ($this->min == self::NA || $value >= $this->min) &&
                    ($this->max == self::NA || $value <= $this->max);
            } elseif($this->type == self::float) {
                return
                    is_numeric($value) &&
                    ($this->min == self::NA || $value >= $this->min) &&
                    ($this->max == self::NA || $value <= $this->max) &&
                    round($value, $this->precision) == $value; // checks that the value is properly rounded
            }
        } elseif($this->behavior == self::button) {
            // input to a button isn't really well-defined,
            // but we'll say that valid input is one that equals this button's id
            return ($value == $this->id);
        } else {
            throw new Exception('this control does not have a known behavior');
        }
    }
}
?>