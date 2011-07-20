<?php
require_once('groupvariables.php');

/**
 * GroupRoundVariables are an extension of GroupVariables;
 * in addition to the storage and access of group-level user variables (provided by GroupVariables),
 * it provides an API for accessing the results of other members of the group.
 */
class GroupRoundVariables extends GroupVariables {
    /** A reference to the session that created this instance.
     * This is needed so that it's possible to distinguish the partners from the player.
     * @var Session $session */
    protected $session;
    
    /** The current Round. (@see Round)
     * @var Round $round*/
    protected $round;

    /** The key under which we will save the SessionVariables in Session->data. */
    const group_vars_key = 'uservariables';

    /** Constructs an instance of GroupVariables, storing the variables in the given Group. */
    public function __construct(Group $group, Session $session, Round $round) {
        parent::__construct($group);
        $this->session = $session;
        $this->round = $round;
    }
    
    public function __get($name) {
        if($name == 'partners') { // return an array with each partner's submission data
            // select just the partners
            $partners = $this->group->partners($this->session);
            
            // get each partner's submission data, as an object
            $round = $this->round;
            $partner_submissions = array_map(function($session) use($round) {
                return (object) $session->getRoundData($round);
            }, $partners);
            
            return $partner_submissions;
        } else {
            return parent::__get($name);
        }
    }
}