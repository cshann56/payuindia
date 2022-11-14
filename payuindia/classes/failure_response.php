<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Abstract class for handling responses from redirect response or
 * or server to server response from payment gateway. This class
 * implements a template design pattern.
 *
 * @package     paygw_payuindia
 * @copyright   2022 Christopher Shannon <cshannon108@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_payuindia;

use core_payment\helper;
use paygw_payuindia\payuhelper;
use paygw_payuindia\gatewayconfig;

require_once('../../../config.php');
require_once('./lib.php'); // Loads settings that all accounts will use.

class failure_response extends abstract_response_template {


    protected function is_response_received_action($isrecorded): bool {

        if ($isrecorded) {
            return false;
        }

        return true;
    } 
    
    protected function record_response_action($excep = null): bool {

        if ($excep) {
            // An exception was thrown.
            throw $excep;
            return false;
        }

        return false;

    }

    protected function compare_hashes_action(bool $hashes_match): bool {
        return false;
    }


    protected function compare_charges_action(bool $unequal): bool {
        return false;
    }

    protected function verify_response_action(bool $response_verified): bool {
        return false;
    }


    protected function deliver_order_action($excep = null): bool {
        return false;
    }



}