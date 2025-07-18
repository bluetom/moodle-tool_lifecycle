@tool @tool_lifecycle
Feature: Add a workflow with an email step and test the interaction as a teacher

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
    And the following "categories" exist:
      | name   | category  | idnumber |
      | Cat1   | 0         | 1        |
      | Cat2   | 0         | 2        |
      | Cat02  | 2         | 02       |
      | Cat01  | 1         | 01       |
      | Cat001 | 01        | 001      |
      | Cat002 | 01        | 002      |
    And the following "courses" exist:
      | fullname | shortname | category | startdate       |
      | Course 1 | C1        | 0        | ##2 days ago##  |
      | Course 2 | C2        | 001      | ##4 days ago##  |
      | Course 3 | C3        | 02       | ##4 days ago##  |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher1 | C2     | editingteacher |
      | teacher1 | C3     | editingteacher |
    And I log in as "admin"
    And I am on workflowdrafts page
    And I click on "Create new workflow" "link"
    And I set the following fields to these values:
      | Title                      | My Workflow                               |
      | Displayed workflow title   | Teachers view on workflow                 |
    And I press "Save changes"
    And I select "Start date delay trigger" from the "tool_lifecycle-choose-trigger" singleselect
    And I set the following fields to these values:
      | Instance name    | My Trigger                 |
      | delay[number]    | 3                          |
      | delay[timeunit]  | days                       |
    And I press "Save changes"
    And I select "Email step" from the "tool_lifecycle-choose-step" singleselect
    And I set the following fields to these values:
      | Instance name              | Email step                  |
      | responsetimeout[number]    | 8                           |
      | responsetimeout[timeunit]  | seconds                     |
      | Subject template           | Subject                     |
      | Content plain text template           | Content                     |
      | Content HTML Template      | Content HTML                |
    And I press "Save changes"
    And I select "Delete course step" from the "tool_lifecycle-choose-step" singleselect
    And I set the field "Instance name" to "Delete Course 2"
    And I press "Save changes"
    And I am on workflowdrafts page
    And I press "Activate"
    And I log out

  Scenario Outline: Test interaction of email step
    Given the following config values are set as admin:
      | config                 | value     | plugin         |
      | enablecategoryhierachy | <config>  | tool_lifecycle |
      | coursecategorydepth    | <config1> | tool_lifecycle |
    And I log in as "teacher1"
    When I am on lifecycle view
    Then I should see "Course 1" in the "tool_lifecycle_remaining" "table"
    And I should see "Course 2" in the "tool_lifecycle_remaining" "table"
    And I should see "Course 3" in the "tool_lifecycle_remaining" "table"
    And I log out
    And I log in as "admin"
    When I run the scheduled task "tool_lifecycle\task\lifecycle_task"
    And I log out
    And I log in as "teacher1"
    And I am on lifecycle view
    Then I should see "Course 1" in the "tool_lifecycle_remaining" "table"
    And I should see "Course 2" in the "tool_lifecycle_interaction" "table"
    And I should see "Course 3" in the "tool_lifecycle_interaction" "table"
    And I should see the tool "Keep course" in the "Course 2" row of the "tool_lifecycle_interaction" table
    And I should see the tool "Keep course" in the "Course 3" row of the "tool_lifecycle_interaction" table
    And I should see "<category1>" in the "tool_lifecycle_interaction" "table"
    And I should see "<category2>" in the "tool_lifecycle_interaction" "table"
    When I click on the tool "Keep course" in the "Course 2" row of the "tool_lifecycle_interaction" table
    Then I should see "Course 1" in the "tool_lifecycle_remaining" "table"
    And I should see "Course 2" in the "tool_lifecycle_remaining" "table"
    And I should see "Course 3" in the "tool_lifecycle_interaction" "table"
    When I wait "10" seconds
    And I log out
    And I log in as "admin"
    When I run the scheduled task "tool_lifecycle\task\lifecycle_task"
    And I log out
    And I log in as "teacher1"
    And I am on lifecycle view
    Then I should see "Course 1" in the "tool_lifecycle_remaining" "table"
    And I should see "Course 2" in the "tool_lifecycle_remaining" "table"
    And I should see "<category1>" in the "tool_lifecycle_remaining" "table"
    And I should not see "Course 3"
    Examples:
      | config | config1 | category1 | category2 |
      | 0      | 0       | Cat001    | Cat02     |
      | 1      | 0       | Cat1      | Cat2      |
      | 1      | 2       | Cat001    | Cat02     |
