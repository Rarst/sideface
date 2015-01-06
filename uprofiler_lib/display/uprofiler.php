<?php
//  Copyright (c) 2009 Facebook
//
//  Licensed under the Apache License, Version 2.0 (the "License");
//  you may not use this file except in compliance with the License.
//  You may obtain a copy of the License at
//
//      http://www.apache.org/licenses/LICENSE-2.0
//
//  Unless required by applicable law or agreed to in writing, software
//  distributed under the License is distributed on an "AS IS" BASIS,
//  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
//  See the License for the specific language governing permissions and
//  limitations under the License.
//

//
// uprofiler: A Hierarchical Profiler for PHP
//
// uprofiler has two components:
//
//  * This module is the UI/reporting component, used
//    for viewing results of uprofiler runs from a browser.
//
//  * Data collection component: This is implemented
//    as a PHP extension (uprofiler).
//
// @author Kannan Muthukkaruppan
//

if (! isset( $GLOBALS['UPROFILER_LIB_ROOT'] )) {
    // by default, the parent directory is uprofiler lib root
    $GLOBALS['UPROFILER_LIB_ROOT'] = realpath(dirname(__FILE__) . '/..');
}

require_once $GLOBALS['UPROFILER_LIB_ROOT'] . '/utils/uprofiler_lib.php';
require_once $GLOBALS['UPROFILER_LIB_ROOT'] . '/utils/callgraph_utils.php';
require_once $GLOBALS['UPROFILER_LIB_ROOT'] . '/utils/uprofiler_runs.php';


/**
 * Our coding convention disallows relative paths in hrefs.
 * Get the base URL path from the SCRIPT_NAME.
 */
$base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

// default column to sort on -- wall time
$sort_col = "wt";

// default is "single run" report
$diff_mode = false;

// call count data present?
$display_calls = true;

// The following column headers are sortable
$sortable_columns = [
    "fn"           => 1,
    "ct"           => 1,
    "wt"           => 1,
    "excl_wt"      => 1,
    "ut"           => 1,
    "excl_ut"      => 1,
    "st"           => 1,
    "excl_st"      => 1,
    "mu"           => 1,
    "excl_mu"      => 1,
    "pmu"          => 1,
    "excl_pmu"     => 1,
    "cpu"          => 1,
    "excl_cpu"     => 1,
    "samples"      => 1,
    "excl_samples" => 1
];

// Textual descriptions for column headers in "single run" mode
$descriptions = [
    "fn"           => "Function Name",
    "ct"           => "Calls",
    "Calls%"       => "Calls%",
    "wt"           => "Incl. Wall Time<br>(microsec)",
    "IWall%"       => "IWall%",
    "excl_wt"      => "Excl. Wall Time<br>(microsec)",
    "EWall%"       => "EWall%",
    "ut"           => "Incl. User<br>(microsecs)",
    "IUser%"       => "IUser%",
    "excl_ut"      => "Excl. User<br>(microsec)",
    "EUser%"       => "EUser%",
    "st"           => "Incl. Sys <br>(microsec)",
    "ISys%"        => "ISys%",
    "excl_st"      => "Excl. Sys <br>(microsec)",
    "ESys%"        => "ESys%",
    "cpu"          => "Incl. CPU<br>(microsecs)",
    "ICpu%"        => "ICpu%",
    "excl_cpu"     => "Excl. CPU<br>(microsec)",
    "ECpu%"        => "ECPU%",
    "mu"           => "Incl.<br>MemUse<br>(bytes)",
    "IMUse%"       => "IMemUse%",
    "excl_mu"      => "Excl.<br>MemUse<br>(bytes)",
    "EMUse%"       => "EMemUse%",
    "pmu"          => "Incl.<br> PeakMemUse<br>(bytes)",
    "IPMUse%"      => "IPeakMemUse%",
    "excl_pmu"     => "Excl.<br>PeakMemUse<br>(bytes)",
    "EPMUse%"      => "EPeakMemUse%",
    "samples"      => "Incl. Samples",
    "ISamples%"    => "ISamples%",
    "excl_samples" => "Excl. Samples",
    "ESamples%"    => "ESamples%",
];

// Formatting Callback Functions...
$format_cbk = [
    "fn"           => "",
    "ct"           => "uprofiler_count_format",
    "Calls%"       => "uprofiler_percent_format",
    "wt"           => "number_format",
    "IWall%"       => "uprofiler_percent_format",
    "excl_wt"      => "number_format",
    "EWall%"       => "uprofiler_percent_format",
    "ut"           => "number_format",
    "IUser%"       => "uprofiler_percent_format",
    "excl_ut"      => "number_format",
    "EUser%"       => "uprofiler_percent_format",
    "st"           => "number_format",
    "ISys%"        => "uprofiler_percent_format",
    "excl_st"      => "number_format",
    "ESys%"        => "uprofiler_percent_format",
    "cpu"          => "number_format",
    "ICpu%"        => "uprofiler_percent_format",
    "excl_cpu"     => "number_format",
    "ECpu%"        => "uprofiler_percent_format",
    "mu"           => "number_format",
    "IMUse%"       => "uprofiler_percent_format",
    "excl_mu"      => "number_format",
    "EMUse%"       => "uprofiler_percent_format",
    "pmu"          => "number_format",
    "IPMUse%"      => "uprofiler_percent_format",
    "excl_pmu"     => "number_format",
    "EPMUse%"      => "uprofiler_percent_format",
    "samples"      => "number_format",
    "ISamples%"    => "uprofiler_percent_format",
    "excl_samples" => "number_format",
    "ESamples%"    => "uprofiler_percent_format",
];


// Textual descriptions for column headers in "diff" mode
$diff_descriptions = [
    "fn"           => "Function Name",
    "ct"           => "Calls Diff",
    "Calls%"       => "Calls<br>Diff%",
    "wt"           => "Incl. Wall<br>Diff<br>(microsec)",
    "IWall%"       => "IWall<br> Diff%",
    "excl_wt"      => "Excl. Wall<br>Diff<br>(microsec)",
    "EWall%"       => "EWall<br>Diff%",
    "ut"           => "Incl. User Diff<br>(microsec)",
    "IUser%"       => "IUser<br>Diff%",
    "excl_ut"      => "Excl. User<br>Diff<br>(microsec)",
    "EUser%"       => "EUser<br>Diff%",
    "cpu"          => "Incl. CPU Diff<br>(microsec)",
    "ICpu%"        => "ICpu<br>Diff%",
    "excl_cpu"     => "Excl. CPU<br>Diff<br>(microsec)",
    "ECpu%"        => "ECpu<br>Diff%",
    "st"           => "Incl. Sys Diff<br>(microsec)",
    "ISys%"        => "ISys<br>Diff%",
    "excl_st"      => "Excl. Sys Diff<br>(microsec)",
    "ESys%"        => "ESys<br>Diff%",
    "mu"           => "Incl.<br>MemUse<br>Diff<br>(bytes)",
    "IMUse%"       => "IMemUse<br>Diff%",
    "excl_mu"      => "Excl.<br>MemUse<br>Diff<br>(bytes)",
    "EMUse%"       => "EMemUse<br>Diff%",
    "pmu"          => "Incl.<br> PeakMemUse<br>Diff<br>(bytes)",
    "IPMUse%"      => "IPeakMemUse<br>Diff%",
    "excl_pmu"     => "Excl.<br>PeakMemUse<br>Diff<br>(bytes)",
    "EPMUse%"      => "EPeakMemUse<br>Diff%",
    "samples"      => "Incl. Samples Diff",
    "ISamples%"    => "ISamples Diff%",
    "excl_samples" => "Excl. Samples Diff",
    "ESamples%"    => "ESamples Diff%",
];

// columns that'll be displayed in a top-level report
$stats = [ ];

// columns that'll be displayed in a function's parent/child report
$pc_stats = [ ];

// Various total counts
$totals   = 0;
$totals_1 = 0;
$totals_2 = 0;

/*
 * The subset of $possible_metrics that is present in the raw profile data.
 */
$metrics = null;
