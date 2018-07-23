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
 * Point of View block
 *
 *
 * @package    block_point_view
 * @copyright  2018 Quentin Fombaron
 * @author     Quentin Fombaron <quentin.fombaron1@etu.univ-grenoble-alpes.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/blocks/point_view/lib.php');

try {
    require_login();
} catch (coding_exception $e) {
    echo 'Exception [coding_exception] (blocks/point_view/block_point_view.php -> require_login()) : ',
    $e->getMessage(), "\n";
} catch (require_login_exception $e) {
    echo 'Exception [require_login_exception] (blocks/point_view/block_point_view.php -> require_login()) : ',
    $e->getMessage(), "\n";
} catch (moodle_exception $e) {
    echo 'Exception [moodle_exception] (blocks/point_view/block_point_view.php -> require_login()) : ',
    $e->getMessage(), "\n";
}

/**
 * block_point_view Class
 *
 *
 * @package    block_point_view
 * @copyright  2018 Quentin Fombaron
 * @author     Quentin Fombaron <quentin.fombaron1@etu.univ-grenoble-alpes.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_point_view extends block_base
{
    /**
     * Block initializations
     *
     * @throws coding_exception
     */
    public function init() {

        $this->title = get_string('pluginname', 'block_point_view');

    }

    /**
     * We have global config/settings data.
     * @return bool
     */
    public function has_config() {

        return true;

    }

    /**
     * Content of Point of View block
     *
     * @return Object
     * @throws dml_exception
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function get_content() {
        global $PAGE, $USER, $CFG, $DB, $COURSE;

        if (get_config('block_point_view', 'enable_point_views_admin')) {

            /* CSS import */
            $this->page->requires->css(new moodle_url($CFG->wwwroot . '/blocks/point_view/styles.css'));

            if ($this->content !== null) {

                return $this->content;

            }

            if (has_capability('block/point_view:view', $this->context)) {

                if (isset($this->config) && isset($this->config->text)) {

                    $this->content = new stdClass();

                    $this->content->text = $this->config->text;

                } else {

                    $this->content = new stdClass();

                    $this->content->text = get_string('defaulttextcontent', 'block_point_view');

                }

                $parameters = [
                    'instanceid' => $this->instance->id,
                    'contextid' => $this->context->id,
                    'courseid' => $COURSE->id,
                    'sesskey' => sesskey(),
                    'enablepix' => (isset($this->config->enable_pix_checkbox)) ? $this->config->enable_pix_checkbox : 0
                ];

                $url = new moodle_url('/blocks/point_view/menu.php', $parameters);

                $this->content->text .= html_writer::start_tag('div', array('class' => 'menu_point_view'));

                $this->content->text .= html_writer::link(
                    $url,
                    '<img src="' . $CFG->wwwroot . '/blocks/point_view/pix/overview.png" id="menu_point_view_img"/>'
                );

                $this->content->text .= html_writer::end_tag('div');

            } else {

                $this->content->text = '';

            }

            if (!$PAGE->user_is_editing()) {

                $enablepointviewscheckbox = (isset($this->config->enable_point_views_checkbox)) ?
                    $this->config->enable_point_views_checkbox :
                    0;

                if ($enablepointviewscheckbox) {

                    $sql = 'SELECT cmid,
                    IFNULL(COUNT(cmid), 0) AS total,
                    IFNULL(TableTypeOne.TotalTypeOne, 0) AS typeone,
                    IFNULL(TableTypeTwo.TotalTypeTwo, 0) AS typetwo,
                    IFNULL(TableTypeThree.TotalTypethree, 0) AS typethree,
                    IFNULL(TableUser.UserVote, 0) AS uservote
                  FROM {block_point_view}
                    NATURAL LEFT JOIN (SELECT cmid, COUNT(vote) AS TotalTypeOne FROM {block_point_view}
                      WHERE vote = 1 GROUP BY cmid) AS TableTypeOne
                    NATURAL LEFT JOIN (SELECT cmid, COUNT(vote) AS TotalTypeTwo FROM {block_point_view}
                      WHERE vote = 2 GROUP BY cmid) AS TableTypeTwo
                    NATURAL LEFT JOIN (SELECT cmid, COUNT(vote) AS TotalTypethree FROM {block_point_view}
                      WHERE vote = 3 GROUP BY cmid) AS TableTypeThree
                    NATURAL LEFT JOIN (SELECT cmid, vote AS UserVote FROM {block_point_view} WHERE userid = :userid) AS TableUser
                    WHERE courseid = :courseid
                  GROUP BY cmid;';

                    $params = array('userid' => $USER->id, 'courseid' => $COURSE->id);

                    $result = $DB->get_records_sql($sql, $params);

                    /* Parameters for the Javascript */
                    $pointviews = (!empty($result)) ? array_values($result) : array();

                } else {

                    $pointviews = null;

                }

                $sqlid = $DB->get_records('course_modules', array('course' => $COURSE->id), null, 'id');

                $moduleselect = array();

                $difficultylevels = array();

                foreach ($sqlid as $row) {

                    if (isset($this->config->{'moduleselectm' . $row->id})) {

                        if ($this->config->{'moduleselectm' . $row->id} != 0 && $this->config->enable_point_views_checkbox) {

                            array_push($moduleselect, $row->id);

                        }

                        if ($this->config->enable_difficulties_checkbox) {

                            $difficultylevels[$row->id] = $this->config->{'difficulty_' . $row->id};

                        }
                    }
                }

                $pixparam = array(
                    'easy' => $CFG->wwwroot . '/blocks/point_view/pix/easy.png',
                    'easytxt' => (isset($this->config->text_easy)) ?
                        $this->config->text_easy
                        : get_string('defaulttexteasy', 'block_point_view'),
                    'better' => $CFG->wwwroot . '/blocks/point_view/pix/better.png',
                    'bettertxt' => (isset($this->config->text_better)) ?
                        $this->config->text_better
                        : get_string('defaulttextbetter', 'block_point_view'),
                    'hard' => $CFG->wwwroot . '/blocks/point_view/pix/hard.png',
                    'hardtxt' => (isset($this->config->text_hard)) ?
                        $this->config->text_hard
                        : get_string('defaulttexthard', 'block_point_view'),
                    'group_' => $CFG->wwwroot . '/blocks/point_view/pix/group_.png',
                    'group_E' => $CFG->wwwroot . '/blocks/point_view/pix/group_E.png',
                    'group_B' => $CFG->wwwroot . '/blocks/point_view/pix/group_B.png',
                    'group_H' => $CFG->wwwroot . '/blocks/point_view/pix/group_H.png',
                    'group_EB' => $CFG->wwwroot . '/blocks/point_view/pix/group_EB.png',
                    'group_EH' => $CFG->wwwroot . '/blocks/point_view/pix/group_EH.png',
                    'group_BH' => $CFG->wwwroot . '/blocks/point_view/pix/group_BH.png',
                    'group_EBH' => $CFG->wwwroot . '/blocks/point_view/pix/group_EBH.png',
                );

                $pixfiles = array(
                    'easy',
                    'better',
                    'hard',
                    'group_',
                    'group_E',
                    'group_B',
                    'group_H',
                    'group_EB',
                    'group_EH',
                    'group_BH',
                    'group_EBH'
                );

                $fs = get_file_storage();

                if (get_config('block_point_view', 'enable_pix_admin')) {

                    foreach ($pixfiles as $file) {

                        if ($fs->file_exists(1, 'block_point_view', 'point_views_pix_admin', 0, '/', $file . '.png')) {

                            $pixparam[$file] = block_point_view_pix_url(1, 'point_views_pix_admin', $file);

                        }
                    }
                } else {

                    $fs->delete_area_files(1, 'block_point_view');

                }

                if (isset($this->config->enable_pix_checkbox) && $this->config->enable_pix_checkbox) {

                    foreach ($pixfiles as $file) {

                        if ($fs->file_exists($this->context->id, 'block_point_view', 'point_views_pix', 0, '/', $file . '.png')) {

                            $pixparam[$file] = block_point_view_pix_url($this->context->id, 'point_views_pix', $file);

                        }
                    }
                } else {

                    $fs->delete_area_files($this->context->id, 'block_point_view');

                }

                $envconf = array(
                    'greentrack' => get_config('block_point_view', 'green_track_color_admin'),
                    'bluetrack' => get_config('block_point_view', 'blue_track_color_admin'),
                    'redtrack' => get_config('block_point_view', 'red_track_color_admin'),
                    'blacktrack' => get_config('block_point_view', 'black_track_color_admin'),
                    'userid' => $USER->id,
                    'courseid' => $COURSE->id
                );

                $paramsamd = array($pointviews, $moduleselect, $difficultylevels, $pixparam, $envconf);

                $this->page->requires->js_call_amd('block_point_view/script_point_view', 'init', $paramsamd);
            }
        } else if (!get_config(
            'block_point_view',
            'enable_point_views_admin')
            && has_capability('block/point_view:view', $this->context)) {

            $this->content->text = get_string('blockdisabled', 'block_point_view');

        }

        return $this->content;
    }

    /**
     * Save data from filemanager when user is saving configuration.
     * Delete file storage if user disable custom emojis.
     *
     * @param mixed $data
     * @param mixed $nolongerused
     */
    public function instance_config_save($data, $nolongerused = false) {

        $fs = get_file_storage();

        $config = clone $data;

        if ($config->enable_pix_checkbox) {

            $config->point_views_pix = file_save_draft_area_files(
                $data->point_views_pix,
                $this->context->id,
                'block_point_view',
                'point_views_pix',
                0
            );

        } else {

            $fs->delete_area_files($this->context->id, 'block_point_view');

        }

        parent::instance_config_save($config, $nolongerused);

    }

    /**
     * Delete file storage.
     *
     * @return bool
     */
    public function instance_delete() {

        $fs = get_file_storage();

        $fs->delete_area_files($this->context->id, 'block_point_view');

        return true;

    }

    /**
     * Copy any block-specific data when copying to a new block instance.
     *
     * @param int $fromid the id number of the block instance to copy from
     * @return boolean
     */
    public function instance_copy($fromid) {

        $fromcontext = context_block::instance($fromid);

        $fs = get_file_storage();

        if (!$fs->is_area_empty($fromcontext->id, 'block_point_view', 'point_views_pix', 0, false)) {

            $draftitemid = 0;

            file_prepare_draft_area(
                $draftitemid,
                $fromcontext->id,
                'block_point_view',
                'point_views_pix',
                0,
                array('subdirs' => true)
            );

            file_save_draft_area_files(
                $draftitemid,
                $this->context->id,
                'block_point_view',
                'point_views_pix',
                0,
                array('subdirs' => true)
            );

        }

        return true;

    }
}