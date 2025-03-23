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
 * Step definition for life cycle.
 *
 * @package    lifecyclestep_adminapprove
 * @category   test
 * @copyright  2018 Tobias Reischmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace lifecyclestep_adminapprove\behat;

use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;
use behat_base;

require_once(__DIR__ . '/../../../../../../../lib/behat/behat_base.php');

/**
 * Step definition for life cycle.
 *
 * @package    lifecyclestep_adminapprove
 * @category   test
 * @copyright  2018 Tobias Reischmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_tool_lifecyclestep_adminapprove extends behat_base {
    /**
     * Open the workflowdrafts page.
     *
     * @Given /^I am on workflowdrafts page$/
     */
    public function i_am_on_workflowdrafts_page() {
        $this->execute('behat_general::i_visit', ['/admin/tool/lifecycle/workflowdrafts.php']);
    }

    /**
     * Open the activeworkflows page.
     *
     * @Given /^I am on activeworkflows page$/
     */
    public function i_am_on_activeworkflows_page() {
        $this->execute('behat_general::i_visit', ['/admin/tool/lifecycle/activeworkflows.php']);
    }

    /**
     * Open the coursebackups page.
     *
     * @Given /^I am on coursebackups page$/
     */
    public function i_am_on_coursebackups_page() {
        $this->execute('behat_general::i_visit', ['/admin/tool/lifecycle/coursebackups.php']);
    }
}
