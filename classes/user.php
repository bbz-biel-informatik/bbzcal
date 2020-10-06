<?php

namespace local_bbzcal;

require_once($CFG->dirroot.'/user/profile/lib.php');

defined('MOODLE_INTERNAL') || die();

class user {
  private $user_id;

  public function __construct($user_id) {
    $this->user_id = $user_id;
  }

  public function get_property_value() {
    $user = profile_user_record($this->user_id, false);
    $propname = $this->get_property_name();
    return $user->$propname;
  }

  private function get_property_name() {
    $config = get_config('local_bbzcal');
    return $config->propertyname;
  }

  public function get_admin_course_ids($DB) {
    $sql = "SELECT c.instanceid AS courseid
            FROM {context} c
            INNER JOIN {role_assignments} ra ON c.id = ra.contextid
            INNER JOIN {role} r ON r.id = ra.roleid
            WHERE r.shortname = 'editingteacher' AND ra.userid = :userid;";
    $courses = $DB->get_records_sql($sql, array('userid' => $this->user_id));
    return array_map(function ($c) {
      return $c->courseid;
    }, $courses);
  }
}
