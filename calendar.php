<?php

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot.'/user/profile/lib.php');

global $PAGE, $DB, $CFG;

date_default_timezone_set("UTC");

$events = [];
$course_id = optional_param('courseid', null, PARAM_INT);
$date = optional_param('date', null, PARAM_TEXT);
if(!$date) {
  $today = new \DateTime();
  $today->setTimezone(new \DateTimeZone('UTC'));
  $today->setTime(0, 0, 0);
  $date = $today->format('Y-m-d');
}

// course context
$title = get_string('course_nav_item', 'local_bbzcal');
$course = $DB->get_record('course', array('id' => $course_id));
require_login($course);
$PAGE->set_context(\context_course::instance($course_id));
$PAGE->set_pagelayout('incourse');
$PAGE->set_course($course);

$u = new local_bbzcal\user($USER->id);

function get_course_students(int $course_id): array {
  $context = context_course::instance($course_id);
  $user_list = get_enrolled_users($context, null, null, 'u.id');
  $ids = [];
  foreach($user_list as &$user) {
    array_push($ids, $user->id);
  }
  return $ids;
}

function get_student_classes(array $user_ids): array {
  $klasses = [];
  foreach($user_ids as &$user_id) {
    $profile = profile_user_record($user_id);
    $profile_klasses = $profile->canonicalclassnames;
    $klasses = array_merge($klasses, explode(", ", $profile_klasses));
  }
  $klasses = array_unique(array_filter($klasses));
  asort($klasses);
  return $klasses;
}

function get_classes_courses($DB, array $classes): array {
  /* Schema:
   * Course    Customdata      Customfield
   *           id
   *           fieldid ------- id
   * id ------ instanceid      name
   *           value
   */
  $conditions = [];
  foreach ($classes as &$klass) {
    $conditions[] = "value LIKE '%$klass%'";
  }
  $where = implode(" OR ", $conditions);

  $sql = "SELECT course.id, customdata.value
          FROM mdl_course course
          INNER JOIN mdl_customfield_data customdata ON customdata.instanceid = course.id
          INNER JOIN mdl_customfield_field customfield ON customfield.id = customdata.fieldid
          WHERE customfield.name = 'canonicalclassnames' AND ($where)";
  $courses = $DB->get_records_sql($sql);
  $ids = [];
  foreach($courses as &$course) {
    array_push($ids, $course->id);
  }
  return $ids;
}

function get_courses_events($DB, array $course_ids): array {
  $course_id_list = implode(", ", $course_ids);
  $sql = "SELECT cal.*, course.shortname
          FROM mdl_local_bbzcal cal
          INNER JOIN mdl_course course ON cal.course_id = course.id
          WHERE course_id IN ($course_id_list)";
  $events = $DB->get_records_sql($sql);
  return $events;
}

function get_course_classes($DB, int $course_id): array {
  $sql = "SELECT course.id, customdata.value
          FROM mdl_course course
          INNER JOIN mdl_customfield_data customdata ON customdata.instanceid = course.id
          INNER JOIN mdl_customfield_field customfield ON customfield.id = customdata.fieldid
          WHERE customfield.name = 'canonicalclassnames' AND course.id = $course_id";
  $courses = $DB->get_record_sql($sql);
  return explode(", ", $courses->value);
}

if($u->is_teacher($DB)) {
  // get course, all student's classes, then courses, and show their events
  $klasslist = get_course_classes($DB, $course_id);
  $students = get_course_students($course_id);
  $classes = get_student_classes($students);
  $courses = get_classes_courses($DB, $classes);
  $events = get_courses_events($DB, $courses);
} else {
  // get users classes, then courses, and show their events
  $klasslist = [];
  $classes = get_student_classes([$USER->id]);
  $courses = get_classes_courses($DB, $classes);
  $events = get_courses_events($DB, $courses);
}

foreach($events as &$event) {
  $event->shortname = explode(' - ', $event->shortname)[1];
}

$renderer = new local_bbzcal\renderer($OUTPUT, 'course', $course_id, $date, $klasslist);

$usr = new local_bbzcal\user($USER->id);
$admin_course_ids = $usr->get_teacher_course_ids($DB);

$PAGE->set_heading($title);
$PAGE->set_title($title);
$PAGE->set_url('/local/bbzcal/calendar.php');

$PAGE->navbar->add($title);

$renderer->header();
$renderer->calendar($events, $admin_course_ids);
$renderer->modal();
$renderer->js();
$renderer->footer();
