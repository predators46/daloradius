<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Copyright (C) 2007 - Liran Tal <liran@enginx.com> All Rights Reserved.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *********************************************************************************************************
 *
 * Authors:    Liran Tal <liran@enginx.com>
 *             Filippo Maria Del Prete <filippo.delprete@gmail.com>
 *             Filippo Lauria <filippo.lauria@iit.cnr.it>
 *
 *********************************************************************************************************
 */

    include("library/checklogin.php");
    $operator = $_SESSION['operator_user'];

    include('library/check_operator_perm.php');
    
    $date_regex = '/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/';

    // we validate starting and ending dates
    $startdate = (array_key_exists('startdate', $_GET) && isset($_GET['startdate']) &&
                  preg_match($date_regex, $_GET['startdate'], $m) !== false &&
                  checkdate($m[2], $m[3], $m[1]))
               ? $_GET['startdate'] : "";

    $enddate = (array_key_exists('enddate', $_GET) && isset($_GET['enddate']) &&
                preg_match($date_regex, $_GET['enddate'], $m) !== false &&
                checkdate($m[2], $m[3], $m[1]))
             ? $_GET['enddate'] : "";

    include_once('library/config_read.php');
    
    include_once("lang/main.php");

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="<?= $langCode ?>" lang="<?= $langCode ?>">
<head>
    <title>daloRADIUS</title>
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    
    <link rel="stylesheet" href="css/1.css" media="screen">
    <link rel="stylesheet" href="css/form-field-tooltip.css" media="screen">
    <link rel="stylesheet" href="library/js_date/datechooser.css">
    <!--[if lte IE 6.5]>
    <link rel="stylesheet" href="library/js_date/select-free.css">
    <![endif]-->
    
    <script src="library/javascript/pages_common.js"></script>
    <script src="library/javascript/rounded-corners.js"></script>
    <script src="library/javascript/form-field-tooltip.js"></script>
</head>

<?php
    include_once("library/tabber/tab-layout.php");
    include("menu-reports.php");
    
    // the array $cols has multiple purposes:
    // - its keys (when non-numerical) can be used
    //   - for validating user input
    //   - for table ordering purpose
    // - its value can be used for table headings presentation
    $cols = array(
                    "month" => t('all','Month'),
                    "users" => t('all','Users')
                 );
    $colspan = count($cols);
    $half_colspan = intdiv($colspan, 2);
                 
    $param_cols = array();
    foreach ($cols as $k => $v) { if (!is_int($k)) { $param_cols[$k] = $v; } }

    // validating user passed parameters

    // whenever possible we use a whitelist approach
    $orderBy = (array_key_exists('orderBy', $_GET) && isset($_GET['orderBy']) &&
                in_array($_GET['orderBy'], array_keys($param_cols)))
             ? $_GET['orderBy'] : array_keys($param_cols)[0];

    $orderType = (array_key_exists('orderType', $_GET) && isset($_GET['orderType']) &&
                  in_array(strtolower($_GET['orderType']), array( "desc", "asc" )))
               ? strtolower($_GET['orderType']) : "asc";
?>

        <div id="contentnorightbar">
            <h2 id="Intro">
                <a href="#" onclick="javascript:toggleShowDiv('helpPage')"><?= t('Intro','repnewusers.php') ?>
                    <h144>&#x2754;</h144>
                </a>
            </h2>
                                
            <div id="helpPage" style="display:none;visibility:visible"><?= t('helpPage','repnewusers'); ?><br></div>
            <br>

<?php

    include('library/opendb.php');
    include('include/management/pages_common.php');
    include('include/management/pages_numbering.php');    // must be included after opendb because it needs to read
                                                          // the CONFIG_IFACE_TABLES_LISTING variable from the config file
                                                          
    // month is used as a "shadow" parameter for non-lexicographic ordering purpose
    // period and users are used for presentation purpose
    $sql = "SELECT CONCAT(MONTHNAME(CreationDate), ' ', YEAR(CreationDate)) AS period, COUNT(*) As users,
                   CAST(CONCAT(YEAR(CreationDate), '-', MONTH(CreationDate), '-01') AS DATE) AS month
              FROM %s
             WHERE CreationDate BETWEEN '%s' AND '%s'
             GROUP BY month";
    $sql = sprintf($sql, $configValues['CONFIG_DB_TBL_DALOUSERINFO'],
                         $dbSocket->escapeSimple($startdate),
                         $dbSocket->escapeSimple($enddate));
                                                
    $res = $dbSocket->query($sql);
    $numrows = $res->numRows();

    if ($numrows > 0) {
        $sql .= sprintf(" ORDER BY %s %s LIMIT %s, %s", $orderBy, $orderType, $offset, $rowsPerPage);
        
        $res = $dbSocket->query($sql);
        $logDebugSQL = "$sql;\n";
        
        /* START - Related to pages_numbering.php */
        $maxPage = ceil($numrows/$rowsPerPage);
        /* END */
        
        $per_page_numrows = $res->numRows();
        
        // the partial query is built starting from user input
        // and for being passed to setupNumbering and setupLinks functions
        $partial_query_params = array();
        if (!empty($startdate)) {
            $partial_query_params[] = sprintf("startdate=%s", $startdate);
        }
        if (!empty($enddate)) {
            $partial_query_params[] = sprintf("enddate=%s", $enddate);
        }
        
        $partial_query_string = ((count($partial_query_params) > 0) ? "&" . implode("&", $partial_query_params)  : "");
?>

            <div class="tabber">
                <div class="tabbertab" title="Statistics">

                    <form name="usersonline" method="GET" style="margin-top: 50px">
                        <table border="0" class="table1">
                            <thead>
                                <tr style="background-color: white">
<?php
        // page numbers are shown only if there is more than one page
        if ($maxPage > 1) {
            printf('<td style="text-align: left" colspan="%s">go to page: ', $colspan);
            setupNumbering($numrows, $rowsPerPage, $pageNum, $orderBy, $orderType, $partial_query_string);
            echo '</td>';
        }
?>
                                </tr>

                                <tr>
<?php

        // a standard way of creating table headings
        foreach ($cols as $param => $caption) {
            
            if (is_int($param)) {
                $ordering_controls = "";
            } else {
                $title_format = "order by %s, sort %s";
                $title_asc = sprintf($title_format, strip_tags($caption), "ascending");
                $title_desc = sprintf($title_format, strip_tags($caption), "descending");

                $href_format = "?orderBy=%s&orderType=%s" . $partial_query_string;
                $href_asc = sprintf($href_format, $param, "asc");
                $href_desc = sprintf($href_format, $param, "desc");

                $img_format = '<img src="%s" alt="%s">';
                $img_asc = sprintf($img_format, 'images/icons/arrow_up.png', '^');
                $img_desc = sprintf($img_format, 'images/icons/arrow_down.png', 'v');

                $enabled_a_format = '<a title="%s" class="novisit" href="%s">%s</a>';
                $disabled_a_format = '<a title="%s" role="link" aria-disabled="true">%s</a>';

                if ($orderBy == $param) {
                    if ($orderType == "asc") {
                        $link_asc = sprintf($disabled_a_format, $title_asc, $img_asc);
                        $link_desc = sprintf($enabled_a_format, $title_asc, $href_desc, $img_desc);
                    } else {
                        $link_asc = sprintf($enabled_a_format, $title_asc, $href_asc, $img_asc);
                        $link_desc = sprintf($disabled_a_format, $title_desc, $img_desc);
                    }
                } else {
                    $link_asc = sprintf($enabled_a_format, $title_asc, $href_asc, $img_asc);
                    $link_desc = sprintf($enabled_a_format, $title_asc, $href_desc, $img_desc);
                }
                
                $ordering_controls = $link_asc . $link_desc;
            }
            
            echo "<th>" . $caption . $ordering_controls . "</th>";
        }
?>
                                </tr>
                            </thead>
                            
                            <tbody>
<?php
        while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
            $users = intval($row['users']);
            $period = htmlspecialchars($row['period'], ENT_QUOTES, 'UTF-8');
?>
                                <tr>
                                    <td><?= $period ?></td>
                                    <td><?= $users ?></td>
                                </tr>
<?php
        }
?>
                            </tbody>
                            
                            <tfoot>
                                <tr>
                                    <th scope="col" colspan="<?= $colspan ?>">
<?php
                    echo "displayed <strong>$per_page_numrows</strong> record(s)";
                    if ($maxPage > 1) {
                        echo " out of <strong>$numrows</strong>";
                    }
?>
                                    </th>
                                </tr>

<?php
        // page navigation controls are shown only if there is more than one page
        if ($maxPage > 1) {
?>
                                <tr>
                                    <th scope="col" colspan="<?= $colspan ?>" style="background-color: white; text-align: center">
                                        <?= setupLinks($pageNum, $maxPage, $orderBy, $orderType, $partial_query_string) ?>
                                    </th>
                                </tr>
<?php
        }
?>
                            </tfoot>
                            
                        </table>
                    </form>

                </div><!-- .tabbertab -->
                
                <div class="tabbertab" title="Graph">
                    <div style="text-align: center; margin-top: 50px">
<?php
                        $src = sprintf('library/graphs-reports-new-users.php?startdate=%s&enddate=%s', $startdate, $enddate);
                        $alt = "monthly number of new users";
?>
                        <img src="<?= $src ?>" alt="<?= $alt ?>">
                    </div>
                </div><!-- .tabbertab -->
            </div><!-- .tabber -->

<?php
    } else {
        $failureMsg = "Nothing to display";
        include_once("include/management/actionMessages.php");
    }
    
    include('library/closedb.php');
?>

        </div><!-- #contentnorightbar -->
                
        <div id="footer">
                
<?php
    $log = "visited page: ";
    $logQuery = "performed query for listing of records on page: ";

    include('include/config/logging.php');
    include('page-footer.php');
?>
        </div><!-- #footer -->
    </div>
</div>

<script>
    var tooltipObj = new DHTMLgoodies_formTooltip();
    tooltipObj.setTooltipPosition('right');
    tooltipObj.setPageBgColor('#EEEEEE');
    tooltipObj.setTooltipCornerSize(15);
    tooltipObj.initFormFieldTooltip();
</script>

</body>
</html>
