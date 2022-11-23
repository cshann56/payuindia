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
 * Plugin administration pages are defined here.
 *
 * @package     paygw_payuindia
 * @category    admin
 * @copyright   2022 Christopher Shannon <cshannon108@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_payuindia\external;

defined('MOODLE_INTERNAL') || die();

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use core_payment\helper;
use moodle_exception;

use \paygw_payuindia\gatewayconfig;
use \paygw_payuindia\payuhelper;

require_once($CFG->libdir."/.."."/config.php");
require_once($CFG->libdir.'/externallib.php'); // Gets the core external library classes and functions.
require_once($CFG->libdir.'/setuplib.php');    // Gets the core library classes and functions for exceptions.
require_once($CFG->dirroot.'/payment/gateway/payuindia/lib.php'); // Loads settings that all accounts will use.


class webservices extends external_api {

    public static function get_hash_parameters() {
        return new external_function_parameters(
            array(
                'inputparams' => new external_multiple_structure(
                    new external_single_structure(
                        array(
"component"     => new external_value(PARAM_RAW, "Component value", true), // This and the next two fields required for getting PayU account info.
"paymentarea"   => new external_value(PARAM_RAW, "Payment area value", true),
"itemid"        => new external_value(PARAM_TEXT, "Item ID", true),
"productinfo"   => new external_value(PARAM_TEXT, "Description of the item", true), // parameter is "productinfo" for external call to PayU and is required for hash.
"amount"        => new external_value(PARAM_FLOAT, "Amount to be payed.", true), // This field and the next two are also required for making a hash.
"firstname"     => new external_value(PARAM_TEXT, "First name of person", true),
"email"         => new external_value(PARAM_EMAIL, "Email address of payer", true),
"phone"         => new external_value(PARAM_TEXT, "Phone number of payer", true),
"additional_charges" => new external_value(PARAM_FLOAT, "Additional charges, if any. (optional)", false), // Optional field, but if present required for making hash.
"lastname"      => new external_value(PARAM_TEXT, "Last name of person", true),
"address1"      => new external_value(PARAM_TEXT, "First line of address.", true),
"address2"      => new external_value(PARAM_TEXT, "2nd line of address.", false),
"city"          => new external_value(PARAM_TEXT, "City.", true),
"state"         => new external_value(PARAM_TEXT, "State.", true),
"country"       => new external_value(PARAM_TEXT, "Country.", true),
"zipcode"       => new external_value(PARAM_TEXT, "Zipcode/Region code. (Numeric only!)", true),
"udf1"          => new external_value(PARAM_RAW,  "Moodle session token.", true),
                        )
                    )
                )
            )
        );
    }

    public static function get_hash_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
"key"           => new external_value(PARAM_RAW,    "The external key for the remote PayU account"),
"txnid"         => new external_value(PARAM_RAW,    "The transaction ID generated by the database"),
"hash"          => new external_value(PARAM_RAW,    "The hash generated from the input parameters.")
                )
            )
        );
    }

    public static function get_hash($inputparams) {

        global $CFG, $DB, $USER; 
 
        //Parameters validation.
        $params = self::validate_parameters(self::get_hash_parameters(),
                array('inputparams' => $inputparams));

        $params = $params["inputparams"][0];

        //Note: don't forget to validate the context and check capabilities
        
        // Get core configuration for this account.
        /* $config = (object) helper::get_gateway_configuration(
            $params['component'], $params['paymentarea'],
            $params['itemid'], 'payuindia'); */
        $config = new gatewayconfig($params['component'], $params['paymentarea'], $params['itemid']);

        $mytxnid_and_time = payuhelper::get_transaction_id($config);
        $params['txnid'] = $mytxnid_and_time['txnid']; // The txnid is only named id in the default table.
        $params['datetime'] = $mytxnid_and_time['datetime'];


        $myhash = payuhelper::generate_hash($config, $params);

        // Add the record to the submitinfo table. Here, we do want to include
        // additional_charge. It should be understood that you subtract additional
        // charge from amount then you get the original amount charged. This final
        // amount was already calculated in pay.php prior to constructing the form.
        $record2 = new \stdClass;
        $record2->datetime      = $params['datetime']; // No reason to generate a new time.
        $record2->component     = $params['component'];
        $record2->paymentarea   = $params['paymentarea'];
        $record2->itemid        = $params['itemid'];
        $record2->payukey       = $config->remotekey;
        $record2->txnid         = $params['txnid'];
        $record2->productinfo   = $params['productinfo'];
        $record2->amount        = $params['amount'];
        $record2->additional_charges = $params['additional_charges'];
        $record2->udf           = null; // Fields udf1 - 5 not yet used. These are user-defined values.
        $record2->firstname     = $params['firstname'];
        $record2->email         = $params['email'];
        $record2->phone         = $params['phone'];
        $record2->lastname      = $params['lastname'];
        $record2->address1      = $params['address1'];
        $record2->address2      = $params['address2'];
        $record2->city          = $params['city'];
        $record2->state         = $params['state'];
        $record2->zipcode       = $params['zipcode'];
        $record2->country       = $params['country'];
        $record2->hash          = $myhash;

        $DB->insert_record('paygw_payuindia_submitinfo', $record2);

        $returnval = array(
            array("key" => $config->remotekey,
            "txnid" => $params['txnid'],
            "hash" => $myhash));

        return $returnval;
    }

    public static function get_stateregioninfo_parameters() {
        return new external_function_parameters(
            array(
                    "country"   => new external_value(PARAM_TEXT, "ISO3 country code.", true)
            )
        );
    }

    public static function get_stateregioninfo_returns() {
        return new external_single_structure(
                array(
"records" => new external_value(PARAM_RAW, "All the values for the state/region associated with ISO3 country code.")
                )
            );
    }

    // Return a json record of all states.
    public static function get_stateregioninfo($inputparams) {

        global $DB;
 
        $params = self::validate_parameters(self::get_stateregioninfo_parameters(),
                array('country' => $inputparams));

        // throw new moodle_exception(666, null, null, var_export($params, true));

        $country = $params["country"]; // ISO3 country code
        $rec1 = $DB->get_record('paygw_payuindia_countries', ['iso3' => $country]);
        $records = $DB->get_records_select(
            'paygw_payuindia_states',
            'countryid = :countryid',
            ['countryid' => $rec1->countryid],
            'name ASC', 'state_code, name');

        // throw new moodle_exception(666, null, null, var_export($records, true));

        // Each record should be a single key-value pair enclosed in
        // an array, since there is no guarantee that the keys
        // would be unique.
        $finalrecs = [';[Select STATE]'];
        foreach($records as $k => $v) {
            array_push($finalrecs, $v->state_code.';'.$v->name);
        }
        $retval = join("|", $finalrecs);

        return ['records' => $retval];
    }

}