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
 * Displays a workflow in a nice visual form.
 *
 * @package tool_lifecycle
 * @copyright  2025 Thomas Niedermaier University Münster
 * @copyright  2021 Nina Herrmann and Justus Dieckmann, WWU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();

define('PAGESIZE', 20);

use tool_lifecycle\action;
use tool_lifecycle\local\manager\delayed_courses_manager;
use tool_lifecycle\local\manager\process_manager;
use tool_lifecycle\local\manager\step_manager;
use tool_lifecycle\local\manager\trigger_manager;
use tool_lifecycle\local\manager\workflow_manager;
use tool_lifecycle\local\table\courses_in_step_table;
use tool_lifecycle\local\table\triggered_courses_table;
use tool_lifecycle\processor;
use tool_lifecycle\settings_type;
use tool_lifecycle\tabs;
use tool_lifecycle\urls;

require_login();

$workflowid = required_param('wf', PARAM_INT);
$stepid = optional_param('step', null, PARAM_INT);
$triggerid = optional_param('trigger', null, PARAM_INT);
$excluded = optional_param('excluded', null, PARAM_INT);
$delayed = optional_param('delayed', null, PARAM_INT);
$used = optional_param('used', null, PARAM_INT);
$search = optional_param('search', null, PARAM_RAW);
$showdetails = optional_param('showdetails', 0, PARAM_INT);

$workflow = workflow_manager::get_workflow($workflowid);
$iseditable = workflow_manager::is_editable($workflow->id);
$isactive = workflow_manager::is_active($workflow->id);
$isdeactivated = workflow_manager::is_deactivated($workflow->id);

$params = ['wf' => $workflow->id];
if ($stepid) {
    $params['step'] = $stepid;
} else if ($triggerid) {
    $params['trigger'] = $triggerid;
} else if ($delayed) {
    $params['delayed'] = $delayed;
} else if ($excluded) {
    $params['excluded'] = $excluded;
}

$syscontext = context_system::instance();
$PAGE->set_url(new \moodle_url(urls::WORKFLOW_DETAILS, $params));
$PAGE->set_context($syscontext);
$PAGE->set_title($workflow->title);

// Link to open the popup with the course list.
$popuplink = new moodle_url(urls::WORKFLOW_DETAILS, ['wf' => $workflow->id]);
// Link for loading the page with no popupwindow.
$nosteplink = new moodle_url(urls::WORKFLOW_DETAILS, ['wf' => $workflowid]);
// Link for changing the extended details view mode.
$showdetailslink = new moodle_url(urls::WORKFLOW_DETAILS, $params);

$action = optional_param('action', null, PARAM_TEXT);
if ($action) {
    if ($action == 'deletedelay') {
        $cid = required_param('cid', PARAM_INT);
        $DB->delete_records('tool_lifecycle_delayed_workf', ['courseid' => $cid, 'workflowid' => $workflow->id]);

    } else {
        step_manager::handle_action($action, optional_param('actionstep', null, PARAM_INT), $workflow->id);
        trigger_manager::handle_action($action, optional_param('actiontrigger', null, PARAM_INT), $workflow->id);
        $processid = optional_param('processid', null, PARAM_INT);
        if ($processid) {
            $process = process_manager::get_process_by_id($processid);
            if ($action === 'rollback') {
                process_manager::rollback_process($process);
                delayed_courses_manager::set_course_delayed_for_workflow($process->courseid, true, $workflow);
            } else if ($action === 'proceed') {
                process_manager::proceed_process($process);
                delayed_courses_manager::set_course_delayed_for_workflow($process->courseid, false, $workflow);
            } else {
                throw new coding_exception('processid was specified but action was neither "rollback" nor "proceed"!');
            }
        }
        redirect($PAGE->url);
    }
}

$PAGE->set_pagetype('admin-setting-' . 'tool_lifecycle');
$PAGE->set_pagelayout('admin');

$renderer = $PAGE->get_renderer('tool_lifecycle');

$heading = get_string('pluginname', 'tool_lifecycle')." / ".
    get_string('workflowoverview', 'tool_lifecycle').": ". $workflow->title;
echo $renderer->header($heading);
$activelink = false;
$deactivatedlink = false;
$draftlink = false;
if ($isactive) {  // Active workflow.
    $id = 'activeworkflows';
    $activelink = true;
    $classdetails = "bg-primary text-white";
} else {
    if ($isdeactivated) { // Deactivated workflow.
        $id = 'deactivatedworkflows';
        $deactivatedlink = true;
        $classdetails = "bg-dark text-white";
    } else { // Draft.
        $id = 'workflowdrafts';
        $draftlink = true;
        $classdetails = "bg-light";
    }
}
$tabrow = tabs::get_tabrow($activelink, $deactivatedlink, $draftlink);
$renderer->tabs($tabrow, $id);

$steps = step_manager::get_step_instances($workflow->id);
$triggers = trigger_manager::get_triggers_for_workflow($workflow->id);

$str = [
    'edit' => get_string('edit'),
    'delete' => get_string('delete'),
    'move_up' => get_string('move_up', 'tool_lifecycle'),
    'move_down' => get_string('move_down', 'tool_lifecycle'),
];

if ($showdetails) {
    /*
        On moodle instances with many courses the following call can be fatal, because each trigger
        check function will be called for every single course of the instance to determine how many
        courses will be triggered by the workflow/the specific trigger. This count is only being
        used to show the admin how many courses will be triggered, it has no functional aspect.
    */
    $amounts = (new processor())->get_count_of_courses_to_trigger_for_workflow($workflow);
    $displaytotaltriggered = !empty($triggers);
}

$displaytriggers = [];
$displaysteps = [];

foreach ($triggers as $trigger) {
    // The array from the DB Function uses ids as keys.
    // Mustache cannot handle arrays which have other keys therefore a new array is build.
    // FUTURE: Nice to have Icon for each subplugin.
    $trigger = (object)(array) $trigger; // Cast to normal object to be able to set dynamic properties.
    $actionmenu = new action_menu([
        new action_menu_link_secondary(
            new moodle_url(urls::EDIT_ELEMENT, ['type' => settings_type::TRIGGER, 'elementid' => $trigger->id]),
            new pix_icon('i/edit', $str['edit']), $str['edit']),
    ]);
    if ($iseditable) {
        $actionmenu->add(new action_menu_link_secondary(
            new moodle_url($PAGE->url,
                ['action' => action::TRIGGER_INSTANCE_DELETE, 'sesskey' => sesskey(), 'actiontrigger' => $trigger->id]),
            new pix_icon('t/delete', $str['delete']), $str['delete'])
        );
    }
    $trigger->actionmenu = $OUTPUT->render($actionmenu);
    if ($showdetails) {
        if ($trigger->automatic = $amounts[$trigger->sortindex]->automatic) {
            $sqlresult = trigger_manager::get_trigger_sqlresult($trigger);
            if ($sqlresult == "false") {
                $trigger->classfires = "border-danger";
                $trigger->additionalinfo = $amounts[$trigger->sortindex]->additionalinfo ?? "-";
            } else {
                $sumtrigger = $amounts[$trigger->sortindex]->triggered - $amounts[$trigger->sortindex]->excluded;
                if ($sumtrigger > 0) {
                    $trigger->classfires = "border-success";
                } else if ($sumtrigger == 0) {
                    $trigger->classfires = "border-secondary";
                } else {
                    $trigger->classfires = "border-danger";
                }
                $trigger->excludedcourses = $amounts[$trigger->sortindex]->excluded;
                $trigger->triggeredcourses = $amounts[$trigger->sortindex]->triggered;
            }
        }
        $displaytotaltriggered &= $trigger->automatic;
    }
    $displaytriggers[] = $trigger;
}

foreach ($steps as $step) {
    $step = (object)(array) $step; // Cast to normal object to be able to set dynamic properties.
    $ncourses = $DB->count_records('tool_lifecycle_process',
        ['stepindex' => $step->sortindex, 'workflowid' => $workflowid]);
    $step->numberofcourses = $ncourses;
    if ($step->id == $stepid) {
        $step->selected = true;
    }
    $actionmenu = new action_menu([
        new action_menu_link_secondary(
            new moodle_url(urls::EDIT_ELEMENT, ['type' => settings_type::STEP, 'elementid' => $step->id]),
            new pix_icon('i/edit', $str['edit']), $str['edit']),
    ]);
    if ($iseditable) {
        $actionmenu->add(new action_menu_link_secondary(
            new moodle_url($PAGE->url,
                ['action' => action::STEP_INSTANCE_DELETE, 'sesskey' => sesskey(), 'actionstep' => $step->id]),
            new pix_icon('t/delete', $str['delete']), $str['delete'])
        );
        if ($step->sortindex > 1) {
            $actionmenu->add(new action_menu_link_secondary(
                new moodle_url($PAGE->url,
                    ['action' => action::UP_STEP, 'sesskey' => sesskey(), 'actionstep' => $step->id]),
                new pix_icon('t/up', $str['move_up']), $str['move_up'])
            );
        }
        if ($step->sortindex < count($steps)) {
            $actionmenu->add(new action_menu_link_secondary(
                    new moodle_url($PAGE->url,
                        ['action' => action::DOWN_STEP, 'sesskey' => sesskey(), 'actionstep' => $step->id]),
                    new pix_icon('t/down', $str['move_down']), $str['move_down'])
            );
        }
    }
    $step->actionmenu = $OUTPUT->render($actionmenu);
    $displaysteps[] = $step;
}

$arrayofcourses = [];

// Popup courses list.
$out = null;
$ncourses = 0;
$courseids = [];
$hiddenfieldssearch = [];
$hiddenfieldssearch[] = ['name' => 'wf', 'value' => $workflowid];
$hiddenfieldssearch[] = ['name' => 'showdetails', 'value' => $showdetails];
if ($stepid) { // Display courses table with courses of this step.
    $step = step_manager::get_step_instance($stepid);
    $ncourses = $DB->count_records('tool_lifecycle_process',
        ['stepindex' => $step->sortindex, 'workflowid' => $workflowid]);
    $table = new courses_in_step_table($step,
        optional_param('courseid', null, PARAM_INT), $ncourses, $search);
    ob_start();
    $table->out(PAGESIZE, false);
    $out = ob_get_contents();
    ob_end_clean();
    $hiddenfieldssearch[] = ['name' => 'step', 'value' => $stepid];
} else if ($triggerid) { // Display courses table with triggered courses of this trigger.
    $trigger = trigger_manager::get_instance($triggerid);
    if ($courseids = (new processor())->get_courses_to_trigger_for_trigger($trigger, $workflowid)) {
        $table = new triggered_courses_table($courseids, 'triggered', $trigger->instancename,
            null, null, $search);
        ob_start();
        $table->out(PAGESIZE, false);
        $out = ob_get_contents();
        ob_end_clean();
        $hiddenfieldssearch[] = ['name' => 'trigger', 'value' => $triggerid];
    }
} else if ($excluded) { // Display courses table with excluded courses of this trigger.
    $trigger = trigger_manager::get_instance($excluded);
    if ($courseids = (new processor())->get_courses_to_exclude_for_trigger($trigger, $workflowid)) {
        $table = new triggered_courses_table($courseids, 'exclude', $trigger->instancename, null, null, $search);
        ob_start();
        $table->out(PAGESIZE, false);
        $out = ob_get_contents();
        ob_end_clean();
        $hiddenfieldssearch[] = ['name' => 'excluded', 'value' => $excluded];
    }
} else if ($delayed) { // Display courses table with courses delayed for this workflow.
    if ($courseids = (new processor())->get_courses_delayed_for_workflow($workflowid)) {
        $table = new triggered_courses_table( $courseids, 'delayed',
            null, $workflow->title, $workflowid, $search);
        ob_start();
        $table->out(PAGESIZE, false);
        $out = ob_get_contents();
        ob_end_clean();
        $hiddenfieldssearch[] = ['name' => 'delayed', 'value' => $delayed];
    }
} else if ($used) { // Display courses triggered by this workflow but involved in other processes already.
    if ($courseids = $amounts['all']->used ?? null) {
        $table = new triggered_courses_table( $courseids, 'used',
            null, $workflow->title, $workflowid, $search);
        ob_start();
        $table->out(PAGESIZE, false);
        $out = ob_get_contents();
        ob_end_clean();
        $hiddenfieldssearch[] = ['name' => 'used', 'value' => '1'];
    }
}
// Search box for courses list.
$searchhtml = '';
if ((intval($courseids) + intval($ncourses)) > PAGESIZE ) {
    $searchhtml = $renderer->render_from_template('tool_lifecycle/search_input', [
        'action' => (new moodle_url(urls::WORKFLOW_DETAILS))->out(false),
        'uniqid' => 'tool_lifecycle-search-courses',
        'inputname' => 'search',
        'extraclasses' => 'ml-3 mt-3',
        'inform' => false,
        'searchstring' => get_string('searchcourses', 'tool_lifecycle'),
        'query' => $search,
        'hiddenfields' => $hiddenfieldssearch,
    ]);
}

$data = [
    'editsettingslink' => (new moodle_url(urls::EDIT_WORKFLOW, ['wf' => $workflow->id]))->out(false),
    'title' => $workflow->title,
    'rollbackdelay' => format_time($workflow->rollbackdelay),
    'finishdelay' => format_time($workflow->finishdelay),
    'delayglobally' => $workflow->delayforallworkflows,
    'trigger' => $displaytriggers,
    'counttriggers' => count($displaytriggers),
    'showcoursecounts' => $showdetails,
    'steps' => $displaysteps,
    'listofcourses' => $arrayofcourses,
    'popuplink' => $popuplink,
    'nosteplink' => $nosteplink,
    'table' => $out,
    'workflowid' => $workflowid,
    'search' => $searchhtml,
    'classdetails' => $classdetails,
    'includedelayedcourses' => $workflow->includedelayedcourses,
    'includesitecourse' => $workflow->includesitecourse,
    'showdetails' => $showdetails,
    'showdetailslink' => $showdetailslink,
    'isactive' => $isactive || $isdeactivated,
];
if ($showdetails) {
    // The triggers total box.
    $data['automatic'] = $displaytotaltriggered;
    $triggered = $amounts['all']->triggered ?? 0;
    $triggeredhtml = $triggered > 0 ? html_writer::span($triggered, 'text-success font-weight-bold') : 0;
    $data['coursestriggered'] = $triggeredhtml;
    if ($triggered) {
        // Excluded: removed from mustache at the moment.
        $excluded = $amounts['all']->excluded;
        $excludedhtml = $excluded > 0 ? html_writer::span($excluded, 'text-danger font-weight-bold') : 0;
        // Count delayed total, displayed in mustache only if there are any.
        $delayed = $amounts['all']->delayed ?? 0;
        $delayedlink = new moodle_url($popuplink, ['delayed' => $workflowid]);
        $delayedhtml = $delayed > 0 ? html_writer::link($delayedlink, $delayed,
            ['class' => 'text-warning  btn btn-outline-warning']) : 0;
        // Count used total, displayed in mustache only if there are any.
        $used = count($amounts['all']->used) ?? 0;
        $usedlink = new moodle_url($popuplink, ['used' => "1"]);
        $usedhtml = $used > 0 ? html_writer::link($usedlink, $used,
            ['class' => 'btn btn-outline-secondary']) : 0;
        $data['coursesexcluded'] = $excludedhtml;
        $data['coursesdelayed'] = $delayedhtml;
        $data['coursesused'] = $usedhtml;
        $data['coursesetsize'] = $amounts['all']->coursesetsize;
    }
}

// Box to add triggers or steps to workflow by use of select fields.
if (workflow_manager::is_editable($workflow->id)) {
    $addinstance = '';
    $triggertypes = trigger_manager::get_chooseable_trigger_types();
    $selectabletriggers = [];
    foreach ($triggertypes as $triggertype => $triggername) {
        foreach ($triggers as $workflowtrigger) {
            if ($triggertype == $workflowtrigger->subpluginname) {
                continue 2;
            }
        }
        $selectabletriggers[$triggertype] = $triggername;
    }

    $addinstance .= $OUTPUT->single_select(new \moodle_url(urls::EDIT_ELEMENT,
        ['type' => settings_type::TRIGGER, 'wf' => $workflow->id]),
        'subplugin', $selectabletriggers, '', ['' => get_string('add_new_trigger_instance', 'tool_lifecycle')],
        null, ['id' => 'tool_lifecycle-choose-trigger']);

    $steps = step_manager::get_step_types();
    $addinstance .= '<span class="ml-1"></span>';
    $addinstance .= $OUTPUT->single_select(new \moodle_url(urls::EDIT_ELEMENT,
        ['type' => settings_type::STEP, 'wf' => $workflow->id]),
        'subplugin', $steps, '', ['' => get_string('add_new_step_instance', 'tool_lifecycle')],
        null, ['id' => 'tool_lifecycle-choose-step']);

    if ($id == 'workflowdrafts') {
        $addinstance .= '<span class="ml-2"></span>';
        if (workflow_manager::is_valid($workflow->id)) {
            $addinstance .= $OUTPUT->single_button(new \moodle_url(urls::ACTIVE_WORKFLOWS,
                ['action' => action::WORKFLOW_ACTIVATE,
                    'sesskey' => sesskey(),
                    'workflowid' => $workflow->id,
                    'backtooverview' => '1',
                    ]),
                get_string('activateworkflow', 'tool_lifecycle'));
        } else {
            $addinstance .= $OUTPUT->pix_icon('i/circleinfo', get_string('invalid_workflow_details', 'tool_lifecycle')) .
                get_string('invalid_workflow', 'tool_lifecycle');
        }
    }

    $data['addinstance'] = $addinstance;
}

echo $OUTPUT->render_from_template('tool_lifecycle/workflowoverview', $data);

echo $renderer->footer();
