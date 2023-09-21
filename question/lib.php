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
 * Question related functions.
 *
 * This file was created just because Fragment API expects callbacks to be defined on lib.php
 *
 * Please, do not add new functions to this file.
 *
 * @package   core_question
 * @copyright 2018 Simey Lameze <simey@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/editlib.php');

/**
 * Question tags fragment callback.
 *
 * @param array $args Arguments to the form.
 * @return null|string The rendered form.
 * @deprecated since Moodle 4.0
 * @see /question/bank/qbank_tagquestion/lib.php
 * @todo Final deprecation on Moodle 4.4 MDL-72438
 */
function core_question_output_fragment_tags_form($args) {
    debugging('Function core_question_output_fragment_tags_form() is deprecated,
         please use core_question_output_fragment_tags_form() from qbank_tagquestion instead.', DEBUG_DEVELOPER);

    if (!empty($args['id'])) {
        global $CFG, $DB;
        require_once($CFG->libdir . '/questionlib.php');
        $id = clean_param($args['id'], PARAM_INT);
        $editingcontext = $args['context'];

        // Load the question and some related information.
        $question = $DB->get_record('question', ['id' => $id]);

        if ($coursecontext = $editingcontext->get_course_context(false)) {
            $course = $DB->get_record('course', ['id' => $coursecontext->instanceid]);
            $filtercourses = [$course];
        } else {
            $filtercourses = null;
        }

        $sql = "SELECT qc.*
                  FROM {question} q
                  JOIN {question_versions} qv ON qv.questionid = q.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                  JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                 WHERE q.id = :id";
        $category = $DB->get_record_sql($sql, ['id' => $question->id]);
        $questioncontext = \context::instance_by_id($category->contextid);
        $contexts = new \core_question\local\bank\question_edit_contexts($editingcontext);

        // Load the question tags and filter the course tags by the current course.
        if (core_tag_tag::is_enabled('core_question', 'question')) {
            $tagobjectsbyquestion = core_tag_tag::get_items_tags('core_question', 'question', [$question->id]);
            if (!empty($tagobjectsbyquestion[$question->id])) {
                $tagobjects = $tagobjectsbyquestion[$question->id];
                $sortedtagobjects = question_sort_tags($tagobjects,
                        context::instance_by_id($category->contextid), $filtercourses);
            }
        }
        $formoptions = [
            'editingcontext' => $editingcontext,
            'questioncontext' => $questioncontext,
            'contexts' => $contexts->all()
        ];
        $data = [
            'id' => $question->id,
            'questioncategory' => $category->name,
            'questionname' => $question->name,
            'categoryid' => $category->id,
            'contextid' => $category->contextid,
            'context' => $questioncontext->get_context_name(),
            'tags' => $sortedtagobjects->tags ?? [],
            'coursetags' => $sortedtagobjects->coursetags ?? [],
        ];

        $cantag = question_has_capability_on($question, 'tag');
        $mform = new \qbank_tagquestion\form\tags_form(null, $formoptions, 'post', '', null, $cantag, $data);
        $mform->set_data($data);

        return $mform->render();
    }
}

/**
 * Question data fragment to get the question html via ajax call.
 *
 * @param array $args Arguments for rendering the fragment. Expected keys:
 *  * view - the view class
 *  * cmid - if in an activity, the course module ID.
 *  * filterquery - the current filters encoded as a URL parameter.
 *  * lastchanged - the ID of the last edited question.
 *  * sortdata - Array of sorted columns.
 *  * filtercondition - the current filters encoded as an object.
 *  * extraparams - additional parameters required for a particular view class.
 *
 * @return array|string
 */
function core_question_output_fragment_question_data(array $args): string {
    global $PAGE;
    if (empty($args)) {
        return '';
    }
    [$params, $extraparams] = \core_question\local\bank\filter_condition_manager::extract_parameters_from_fragment_args($args);
    $thiscontext = \context_course::instance($params['courseid']);
    $contexts = new \core_question\local\bank\question_edit_contexts($thiscontext);
    $contexts->require_one_edit_tab_cap($params['tabname']);
    $course = get_course($params['courseid']);

    $viewclass = empty($args['view']) ? \core_question\local\bank\view::class : clean_param($args['view'], PARAM_NOTAGS);
    $cmid = clean_param($args['cmid'], PARAM_INT);
    [, $cm] = empty($cmid) ? [null, null] : get_module_from_cmid($cmid);

    $nodeparent = $PAGE->settingsnav->find('questionbank', \navigation_node::TYPE_CONTAINER);
    $thispageurl = new \moodle_url($nodeparent->action);
    if ($cm) {
        $thispageurl->param('cmid', $cm->id);
    } else {
        $thispageurl->param('courseid', $params['courseid']);
    }
    if (!empty($args['filterquery'])) {
        $thispageurl->param('filter', $args['filterquery']);
    }
    if (!empty($args['lastchanged'])) {
        $thispageurl->param('lastchanged', $args['lastchanged']);
    }
    if (!empty($params['sortdata'])) {
        foreach ($params['sortdata'] as $sortname => $sortorder) {
            $thispageurl->param('sortdata[' . $sortname . ']', $sortorder);
        }
    }
    $questionbank = new $viewclass($contexts, $thispageurl, $course, $cm, $params, $extraparams);
    return $questionbank->display_questions_table();
}
