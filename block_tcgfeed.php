<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file contains the parent class for moodle blocks, block_base.
 *
 * @package    tcgfeed
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

class block_tcgfeed extends block_base {

    /// Class Functions

    /*
      return array of job opportunities from feed.
      Caches feed for 10minutes
     */
    static function readfeed()
    {
        $header=array();
        $header[]='Accept: application/json';
        $header[]='Authorization: Basic c2NyX3N5bmRpY2F0aW9uX2RhdGFfbHNodG06eWU5Wmt9NDZKUClXKFowWW5PcXA=';
        $header[]='User-Agent: Moodle';

        $lastfeedread=get_config('block_tcgfeed','feedtimestamp');
        if(0 and time()-$lastfeedread>600)
        {
            $crl=curl_init();
            curl_setopt($crl, CURLOPT_HTTPHEADER,$header);
            curl_setopt($crl, CURLOPT_RETURNTRANSFER,true);
            curl_setopt($crl, CURLOPT_URL, 'https://link-vacancies-test.targetconnect.net/london/vacancy/london-school-hygene-tropical-medicine');
            $rawcontent = trim(curl_exec($crl));
            curl_close($crl);
            $t=json_decode($rawcontent);
            if($t and json_last_error() === JSON_ERROR_NONE)
            {
                set_config('feedcache',serialize($t),'block_tcgfeed');
                set_config('feedtimestamp',time(),'block_tcgfeed');
            }
        }
        $r=(!empty($t) ? $t :unserialize(get_config('block_tcgfeed','feedcache')));

        return $r->content;
    }

    static function buildcontents()
    {
        $content='';
        foreach(static::readfeed()  as $j)
        {
            $content.=static::convert_job($j);
        }
        return $content;
    }

    static function filterfeed()
    {
        return static::readfeed();
    }

    /**
     * Takes a job object and returns html
     *
     */
    static function convert_job($job)
    {
        $i=0;
        $template=file_get_contents(__DIR__.'/job.html');
        foreach(array('jobid'=>"job$i",
                      'jobname'=>$job->vacancy->title,
                      'employername'=>$job->organization->name,
                      'location'=>$job->vacancy->location[0]->region,
                      'summary'=>$job->vacancy->summary,
                      'sector'=>$job->organization->primaryBusinessArea,
                      'type'=>$job->vacancy->type[0]
        ) as $field=>$replacement)
        {
            $i++;
            $template=str_replace("<<$field>>",$replacement,$template);
        }


        return $template;
    }

    /**
     * Fake constructor to keep PHP5 happy
     *
     */
    function __construct() {
        $this->init();
    }

    /**
     * Function that can be overridden to do extra cleanup before
     * the database tables are deleted. (Called once per block, not per instance!)
     */
    function before_delete() {
    }

    function init()
    {
        $this->title=get_string('pluginname','block_tcgfeed');
    }

    /**
     * Returns the block name, as present in the class name,
     * the database, the block directory, etc etc.
     *
     * @return string
     */
    function name() {
        // Returns the block name, as present in the class name,
        // the database, the block directory, etc etc.
        static $myname;
        if ($myname === NULL) {
            $myname = strtolower(get_class($this));
            $myname = substr($myname, strpos($myname, '_') + 1);
        }
        return $myname;
    }

    /**
     * Parent class version of this function simply returns NULL
     * This should be implemented by the derived class to return
     * the content object.
     *
     * @return stdObject
     */
    function get_content()
    {
        global $DB, $USER;
        $context  = context_system::instance();

        if ($this->content !== NULL)
        {
            return $this->content;
        }

        if (empty($this->instance))
        {
            $this->content = '';
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->text = static::buildcontents();
    }

    /**
     * Returns the class $title var value.
     *
     * Intentionally doesn't check if a title is set.
     * This is already done in {@link _self_test()}
     *
     * @return string $this->title
     */
    function get_title()
    {
        // Intentionally doesn't check if a title is set. This is already done in _self_test()
        return $this->title;
    }


    /**
     * Tests if this block has been implemented correctly.
     * Also, $errors isn't used right now
     *
     * @return boolean
     */

    function _self_test() {
        // Tests if this block has been implemented correctly.
        // Also, $errors isn't used right now
        $errors = array();

        $correct = true;
        if ($this->get_title() === NULL) {
            $errors[] = 'title_not_set';
            $correct = false;
        }
        if (!in_array($this->get_content_type(), array(BLOCK_TYPE_LIST, BLOCK_TYPE_TEXT, BLOCK_TYPE_TREE))) {
            $errors[] = 'invalid_content_type';
            $correct = false;
        }
        //following selftest was not working when roles&capabilities were used from block
        /*        if ($this->get_content() === NULL) {
           $errors[] = 'content_not_set';
           $correct = false;
           }*/
        $formats = $this->applicable_formats();
        if (empty($formats) || array_sum($formats) === 0) {
            $errors[] = 'no_formats';
            $correct = false;
        }

        return $correct;
    }

    /**
     * Subclasses should override this and return true if the
     * subclass block has a settings.php file.
     *
     * @return boolean
     */
    function has_config() {
        return true;
    }

    /**
     * Default behavior: save all variables as $CFG properties
     * You don't need to override this if you 're satisfied with the above
     *
     * @deprecated since Moodle 2.9 MDL-49385 - Please use Admin Settings functionality to save block configuration.
     */
    function config_save($data) {
        throw new coding_exception('config_save() can not be used any more, use Admin Settings functionality to save block configuration.');
    }

    /**
     * Which page types this block may appear on.
     *
     * The information returned here is processed by the
     * {@link blocks_name_allowed_in_format()} function. Look there if you need
     * to know exactly how this works.
     *
     * Default case: everything except mod and tag.
     *
     * @return array page-type prefix => true/false.
     */
    function applicable_formats() {
        // Default case: the block can be used in courses and site index, but not in activities
        return array('all' => true, 'mod' => false, 'tag' => false);
    }


    /**
     * Default return is false - header will be shown
     * @return boolean
     */
    function hide_header() {
        return false;
    }

    /**
     * Return any HTML attributes that you want added to the outer <div> that
     * of the block when it is output.
     *
     * Because of the way certain JS events are wired it is a good idea to ensure
     * that the default values here still get set.
     * I found the easiest way to do this and still set anything you want is to
     * override it within your block in the following way
     *
     * <code php>
     * function html_attributes() {
     *    $attributes = parent::html_attributes();
     *    $attributes['class'] .= ' mynewclass';
     *    return $attributes;
     * }
     * </code>
     *
     * @return array attribute name => value.
     */
    function html_attributes() {
        $attributes = array(
            'id' => 'inst' . $this->instance->id,
            'class' => 'block_' . $this->name(). '  block',
            'role' => $this->get_aria_role()
        );
        if ($this->hide_header()) {
            $attributes['class'] .= ' no-header';
        }
        if ($this->instance_can_be_docked() && get_user_preferences('docked_block_instance_'.$this->instance->id, 0)) {
            $attributes['class'] .= ' dock_on_load';
        }
        return $attributes;
    }

    /**
     * Set up a particular instance of this class given data from the block_insances
     * table and the current page. (See {@link block_manager::load_blocks()}.)
     *
     * @param stdClass $instance data from block_insances, block_positions, etc.
     * @param moodle_page $the page this block is on.
     */
    function _load_instance($instance, $page) {
        if (!empty($instance->configdata)) {
            $this->config = unserialize(base64_decode($instance->configdata));
        }
        $this->instance = $instance;
        $this->context = context_block::instance($instance->id);
        $this->page = $page;
        $this->specialization();
    }

    /**
     * Allows the block to load any JS it requires into the page.
     *
     * By default this function simply permits the user to dock the block if it is dockable.
     */
    function get_required_javascript() {
        if ($this->instance_can_be_docked() && !$this->hide_header()) {
            user_preference_allow_ajax_update('docked_block_instance_'.$this->instance->id, PARAM_INT);
        }
    }

    /**
     * This function is called on your subclass right after an instance is loaded
     * Use this function to act on instance data just after it's loaded and before anything else is done
     * For instance: if your block will have different title's depending on location (site, course, blog, etc)
     */
    function specialization() {
        // Just to make sure that this method exists.
    }

    /**
     * Is each block of this type going to have instance-specific configuration?
     * Normally, this setting is controlled by {@link instance_allow_multiple()}: if multiple
     * instances are allowed, then each will surely need its own configuration. However, in some
     * cases it may be necessary to provide instance configuration to blocks that do not want to
     * allow multiple instances. In that case, make this function return true.
     * I stress again that this makes a difference ONLY if {@link instance_allow_multiple()} returns false.
     * @return boolean
     */
    function instance_allow_config() {
        return false;
    }

    /**
     * Are you going to allow multiple instances of each block?
     * If yes, then it is assumed that the block WILL USE per-instance configuration
     * @return boolean
     */
    function instance_allow_multiple() {
        // Are you going to allow multiple instances of each block?
        // If yes, then it is assumed that the block WILL USE per-instance configuration
        return false;
    }

    /**
     * Serialize and store config data
     */
    function instance_config_save($data, $nolongerused = false) {
        global $DB;
        $DB->set_field('block_instances', 'configdata', base64_encode(serialize($data)),
                       array('id' => $this->instance->id));
    }

    /**
     * Replace the instance's configuration data with those currently in $this->config;
     */
    function instance_config_commit($nolongerused = false) {
        global $DB;
        $this->instance_config_save($this->config);
    }

    /**
     * Do any additional initialization you may need at the time a new block instance is created
     * @return boolean
     */
    function instance_create() {
        return true;
    }

    /**
     * Copy any block-specific data when copying to a new block instance.
     * @param int $fromid the id number of the block instance to copy from
     * @return boolean
     */
    public function instance_copy($fromid) {
        return true;
    }

    /**
     * Delete everything related to this instance if you have been using persistent storage other than the configdata field.
     * @return boolean
     */
    function instance_delete() {
        return true;
    }

    /**
     * Allows the block class to have a say in the user's ability to edit (i.e., configure) blocks of this type.
     * The framework has first say in whether this will be allowed (e.g., no editing allowed unless in edit mode)
     * but if the framework does allow it, the block can still decide to refuse.
     * @return boolean
     */
    function user_can_edit() {
        global $USER;

        if (has_capability('moodle/block:edit', $this->context)) {
            return true;
        }

        // The blocks in My Moodle are a special case.  We want them to inherit from the user context.
        if (!empty($USER->id)
            && $this->instance->parentcontextid == $this->page->context->id   // Block belongs to this page
            && $this->page->context->contextlevel == CONTEXT_USER             // Page belongs to a user
            && $this->page->context->instanceid == $USER->id) {               // Page belongs to this user
            return has_capability('moodle/my:manageblocks', $this->page->context);
        }

        return false;
    }

    /**
     * Allows the block class to have a say in the user's ability to create new instances of this block.
     * The framework has first say in whether this will be allowed (e.g., no adding allowed unless in edit mode)
     * but if the framework does allow it, the block can still decide to refuse.
     * This function has access to the complete page object, the creation related to which is being determined.
     *
     * @param moodle_page $page
     * @return boolean
     */
    function user_can_addto($page) {
        global $USER;

        // The blocks in My Moodle are a special case and use a different capability.
        if (!empty($USER->id)
            && $page->context->contextlevel == CONTEXT_USER // Page belongs to a user
            && $page->context->instanceid == $USER->id // Page belongs to this user
            && $page->pagetype == 'my-index') { // Ensure we are on the My Moodle page

            // If the block cannot be displayed on /my it is ok if the myaddinstance capability is not defined.
            $formats = $this->applicable_formats();
            // Is 'my' explicitly forbidden?
            // If 'all' has not been allowed, has 'my' been explicitly allowed?
            if ((isset($formats['my']) && $formats['my'] == false) || (empty($formats['all']) && empty($formats['my']))) {
                // Block cannot be added to /my regardless of capabilities.
                return false;
            } else {
                $capability = 'block/' . $this->name() . ':myaddinstance';
                return $this->has_add_block_capability($page, $capability)
                    && has_capability('moodle/my:manageblocks', $page->context);
            }
        }

        $capability = 'block/' . $this->name() . ':addinstance';
        if ($this->has_add_block_capability($page, $capability)
            && has_capability('moodle/block:edit', $page->context)) {
            return true;
        }

        return false;
    }

    /**
     * Returns true if the user can add a block to a page.
     *
     * @param moodle_page $page
     * @param string $capability the capability to check
     * @return boolean true if user can add a block, false otherwise.
     */
    private function has_add_block_capability($page, $capability) {
        // Check if the capability exists.
        if (!get_capability_info($capability)) {
            // Debug warning that the capability does not exist, but no more than once per page.
            static $warned = array();
            if (!isset($warned[$this->name()])) {
                debugging('The block ' .$this->name() . ' does not define the standard capability ' .
                          $capability , DEBUG_DEVELOPER);
                $warned[$this->name()] = 1;
            }
            // If the capability does not exist, the block can always be added.
            return true;
        } else {
            return has_capability($capability, $page->context);
        }
    }

    static function get_extra_capabilities() {
        return array('moodle/block:view', 'moodle/block:edit');
    }

    /**
     * Can be overridden by the block to prevent the block from being dockable.
     *
     * @return bool
     */
    public function instance_can_be_docked() {
        global $CFG;
        return (!empty($CFG->allowblockstodock) && $this->page->theme->enable_dock);
    }

    /**
     * If overridden and set to false by the block it will not be hidable when
     * editing is turned on.
     *
     * @return bool
     */
    public function instance_can_be_hidden() {
        return true;
    }

    /**
     * If overridden and set to false by the block it will not be collapsible.
     *
     * @return bool
     */
    public function instance_can_be_collapsed()
    {
        return true;
    }

    /** @callback callback functions for comments api */
    public static function comment_template($options) {
        $ret = <<<EOD
<div class="comment-userpicture">___picture___</div>
<div class="comment-content">
    ___name___ - <span>___time___</span>
    <div>___content___</div>
</div>
EOD;
        return $ret;
    }
    public static function comment_permissions($options) {
        return array('view'=>true, 'post'=>true);
    }
    public static function comment_url($options) {
        return null;
    }
    public static function comment_display($comments, $options) {
        return $comments;
    }
    public static function comment_add(&$comments, $options) {
        return true;
    }

    /**
     * Returns the aria role attribute that best describes this block.
     *
     * Region is the default, but this should be overridden by a block is there is a region child, or even better
     * a landmark child.
     *
     * Options are as follows:
     *    - landmark
     *      - application
     *      - banner
     *      - complementary
     *      - contentinfo
     *      - form
     *      - main
     *      - navigation
     *      - search
     *
     * @return string
     */
    public function get_aria_role() {
        return 'complementary';
    }
}
