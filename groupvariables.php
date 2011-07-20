<?php
require_once('uservariables.php');

class GroupVariables extends UserVariables {
    /** A reference to the group for which these variables are being saved.
     * @var Group $group */
    protected $group;
    
    /** The key under which we will save the SessionVariables in Session->data. */
    const group_vars_key = 'uservariables';

    /** Constructs an instance of GroupVariables, storing the variables in the given Group. */
    public function __construct(Group $group) {
        $this->group = $group;

        if(array_key_exists(self::group_vars_key, $this->group->data) &&
            is_array($this->group->data[self::group_vars_key]))
        {
            $this->data = $this->group->data[self::group_vars_key];
        } else { // no session vars set yet
            $this->data = array();
        }
    }
    
    public function save() {
        $this->group->data[self::group_vars_key] = $this->data;
        $this->group->saveData();
    }
}