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
 * This file contains the definition for the renderable classes for the booking instance
 *
 * @package   mod_booking
 * @copyright 2021 Georg Maißer {@link http://www.wunderbyte.at}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_booking\output;

defined('MOODLE_INTERNAL') || die();

use mod_booking\booking_option;
use mod_booking\booking_utils;
use mod_booking\utils\db;
use renderer_base;
use renderable;
use templatable;

const DESCRIPTION_WEBSITE = 1;
const DESCRIPTION_CALENDAR = 2;
const DESCRIPTION_ICAL = 3;
const DESCRIPTION_MAIL = 4;

/**
 * This class prepares data for displaying a booking option instance
 *
 * @package mod_booking
 * @copyright 2021 Georg Maißer {@link http://www.wunderbyte.at}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bookingoption_description implements renderable, templatable {

    /** @var string $title the title (column text) as it is saved in db */
    public $title = null;

    /** @var int $modalcounter the title (column text) as it is saved in db */
    public $modalcounter = null;

    /** @var string $description from DB */
    public $description = null;

    /** @var string $statusdescription depending on booking status */
    public $statusdescription = null;

    /** @var string $location as saved in db */
    public $location = null;

    /** @var string $address as saved in db */
    public $address = null;

    /** @var string $institution as saved in db */
    public $institution = null;

    /** @var string $duration as saved in db in minutes */
    public $duration = null;

    /** @var string $booknowbutton as saved in db in minutes */
    public $booknowbutton = null;

    /** @var array $dates as saved in db in minutes */
    public $dates = [];

    /**
     * @var null Bookingutilities to instantiate only once
     */
    private $bu = null;


    /**
     * Constructor.
     * @param $booking
     * @param $bookingoption
     * @param null $bookingevent
     * @param int $descriptionparam
     * @param bool $withcustomfields
     */
    public function __construct($booking,
            $bookingoption,
            $bookingevent = null,
            $descriptionparam = DESCRIPTION_WEBSITE, // Default.
            $withcustomfields = true) {

        global $CFG;

        $this->bu = new booking_utils();
        $bookingoption = new booking_option($booking->cm->id, $bookingoption->id);

        // These fields can be gathered directly from DB.
        $this->title = $bookingoption->option->text;
        $this->location = $bookingoption->option->location;
        $this->address = $bookingoption->option->address;
        $this->institution = $bookingoption->option->institution;

        // There can be more than one modal, therefor we use the id of this record
        $this->modalcounter = $bookingoption->option->id;


        // $this->duration = $bookingoption->option->duration;
        $this->description = format_text($bookingoption->option->description, FORMAT_HTML);

        // For these fields we do need some conversion.
        // For Description we need to know the booking status
        $this->statusdescription = $bookingoption->get_option_text();

        // Every date will be an array of datestring and customfields.
        // But customfields will only be shown if we show booking option information inline.

        $this->dates = $this->bu->return_array_of_sessions($bookingoption, $bookingevent, $descriptionparam, $withcustomfields);

        $baseurl = $CFG->wwwroot;
        $link = new \moodle_url($baseurl . '/mod/booking/view.php', array(
            'id' => $booking->cm->id,
            'optionid' => $bookingoption->optionid,
            'action' => 'showonlyone',
            'whichview' => 'showonlyone'
        ));

        switch ($descriptionparam) {
            case DESCRIPTION_WEBSITE:
                if ($bookingoption->iambooked == 1) {
                    // If iambooked is 1, we show a short info text that the option is already booked.
                    $this->booknowbutton = get_string('infoalreadybooked', 'booking');
                } else if ($bookingoption->onwaitinglist == 1) {
                    // If onwaitinglist is 1, we show a short info text that the user is on the waiting list.
                    $this->booknowbutton = get_string('infowaitinglist', 'booking');
                }
                break;
            case DESCRIPTION_CALENDAR:
                $this->booknowbutton = "<a href=$link class='btn btn-primary'>"
                        . get_string('gotobookingoption', 'booking')
                        . "</a>";
                // TODO: We would need an event tracking status changes between notbooked, iambooked and onwaitinglist...
                // TODO: ...in order to update the event table accordingly.
                /*if ($bookingoption->onwaitinglist == 1) {
                    // If onwaitinglist is 1, we show a short info text that the user is on the waiting list.
                    $this->booknowbutton .= '<br><p>' . get_string('infowaitinglist', 'booking') . '</p>';
                }*/
                break;
            case DESCRIPTION_ICAL:
                $this->booknowbutton = get_string('gotobookingoption', 'booking') . ': ' .  $link->out(false);
                break;
            case DESCRIPTION_MAIL:
                // The link should be clickable in mails (placeholder {bookingdetails}).
                $this->booknowbutton = get_string('gotobookingoption', 'booking') . ': ' .
                    '<a href = "' . $link . '" target = "_blank">' .
                        $link->out(false) .
                    '</a>';
                break;
        }
    }

    public function export_for_template(renderer_base $output) {
        return array(
                'title' => $this->title,
                'modalcounter' => $this->modalcounter,
                'description' => $this->description,
                'statusdescription' => $this->statusdescription,
                'location' => $this->location,
                'address' => $this->address,
                'institution' => $this->institution,
                'duration' => $this->duration,
                'dates' => $this->dates,
                'booknowbutton' => $this->booknowbutton
        );
    }
}