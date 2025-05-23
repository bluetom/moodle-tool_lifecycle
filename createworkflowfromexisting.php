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
 * Displays form for copying a new workflow from a existing one.
 *
 * @package tool_lifecycle
 * @copyright  2025 Thomas Niedermaier University Münster
 * @copyright  2022 Justus Dieckmann WWU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_lifecycle\local\form\form_workflow_instance;
use tool_lifecycle\local\manager\workflow_manager;
use tool_lifecycle\local\table\select_workflow_table;
use tool_lifecycle\tabs;
use tool_lifecycle\urls;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();

$workflowid = optional_param('wf', null, PARAM_INT);

$syscontext = context_system::instance();
$PAGE->set_context($syscontext);

$renderer = $PAGE->get_renderer('tool_lifecycle');

$PAGE->set_pagetype('admin-setting-' . 'tool_lifecycle');
$PAGE->set_pagelayout('admin');
if ($workflowid) {
    $PAGE->set_url(new \moodle_url(urls::CREATE_FROM_EXISTING), ['wf' => $workflowid]);
} else {
    $PAGE->set_url(new \moodle_url(urls::CREATE_FROM_EXISTING));
}
$heading = get_string('pluginname', 'tool_lifecycle')." / ".
    get_string('create_workflow_from_existing', 'tool_lifecycle');

if ($workflowid) {
    $workflow = workflow_manager::get_workflow($workflowid);
    $workflow->title = get_string('workflow_duplicate_title', 'tool_lifecycle', $workflow->title);
    $form = new form_workflow_instance($PAGE->url, $workflow);
    if ($form->is_cancelled()) {
        // Cancelled, redirect back to workflow drafts.
        redirect(new moodle_url(urls::WORKFLOW_DRAFTS));
    }
    if ($data = $form->get_data()) {
        $newworkflow = workflow_manager::duplicate_workflow($workflow->id);
        $newworkflow->title = $data->title;
        $newworkflow->displaytitle = $data->displaytitle;
        $newworkflow->rollbackdelay = $data->rollbackdelay;
        $newworkflow->finishdelay = $data->finishdelay;
        $newworkflow->delayforallworkflows = $data->delayforallworkflows ?? 0;
        $workflow->includedelayedcourses = $data->includedelayedcourses;
        $workflow->includesitecourse = $data->includesitecourse;
        workflow_manager::insert_or_update($newworkflow);

        // Workflow created, redirect to workflow detail page.
        redirect(new moodle_url(urls::WORKFLOW_DETAILS, ['wf' => $newworkflow->id]));
    }
    echo $renderer->header($heading);
    $tabrow = tabs::get_tabrow();
    $renderer->tabs($tabrow, '');
    $form->display();
    echo $renderer->footer();
} else {
    echo $renderer->header($heading);
    $tabrow = tabs::get_tabrow();
    $renderer->tabs($tabrow, '');
    $table = new select_workflow_table('tool_lifecycle-select-workflow');
    $table->out();
    echo $renderer->footer();
}
