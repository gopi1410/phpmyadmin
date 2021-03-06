<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * library for displaying table with results from all sort of select queries
 *
 * @package PhpMyAdmin
 */
if (! defined('PHPMYADMIN')) {
    exit;
}

/**
 *
 */
require_once './libraries/Index.class.php';

/**
 * Defines the display mode to use for the results of a SQL query
 *
 * It uses a synthetic string that contains all the required informations.
 * In this string:
 *   - the first two characters stand for the action to do while
 *     clicking on the "edit" link (e.g. 'ur' for update a row, 'nn' for no
 *     edit link...);
 *   - the next two characters stand for the action to do while
 *     clicking on the "delete" link (e.g. 'kp' for kill a process, 'nn' for
 *     no delete link...);
 *   - the next characters are boolean values (1/0) and respectively stand
 *     for sorting links, navigation bar, "insert a new row" link, the
 *     bookmark feature, the expand/collapse text/blob fields button and
 *     the "display printable view" option.
 *     Of course '0'/'1' means the feature won't/will be enabled.
 *
 * @param string  &$the_disp_mode the synthetic value for display_mode (see a few
 *                                lines above for explanations)
 * @param integer &$the_total     the total number of rows returned by the SQL query
 *                                without any programmatically appended LIMIT clause
 *                                (just a copy of $unlim_num_rows if it exists, else
 *                                computed inside this function)
 *
 * @return array    an array with explicit indexes for all the display
 *                   elements
 *
 * @global string   the database name
 * @global string   the table name
 * @global integer  the total number of rows returned by the SQL query
 *                   without any programmatically appended "LIMIT" clause
 * @global array    the properties of the fields returned by the query
 * @global string   the URL to return to in case of error in a SQL
 *                   statement
 *
 * @access  private
 *
 * @see     PMA_getTable()
 */
function PMA_setDisplayMode(&$the_disp_mode, &$the_total)
{

    global $db, $table;
    global $unlim_num_rows, $fields_meta;
    global $err_url;

    // 1. Initializes the $do_display array
    $do_display              = array();
    $do_display['edit_lnk']  = $the_disp_mode[0] . $the_disp_mode[1];
    $do_display['del_lnk']   = $the_disp_mode[2] . $the_disp_mode[3];
    $do_display['sort_lnk']  = (string) $the_disp_mode[4];
    $do_display['nav_bar']   = (string) $the_disp_mode[5];
    $do_display['ins_row']   = (string) $the_disp_mode[6];
    $do_display['bkm_form']  = (string) $the_disp_mode[7];
    $do_display['text_btn']  = (string) $the_disp_mode[8];
    $do_display['pview_lnk'] = (string) $the_disp_mode[9];

    // 2. Display mode is not "false for all elements" -> updates the
    // display mode
    if ($the_disp_mode != 'nnnn000000') {
        if (isset($GLOBALS['printview']) && $GLOBALS['printview'] == '1') {
            // 2.0 Print view -> set all elements to false!
            $do_display['edit_lnk']  = 'nn'; // no edit link
            $do_display['del_lnk']   = 'nn'; // no delete link
            $do_display['sort_lnk']  = (string) '0';
            $do_display['nav_bar']   = (string) '0';
            $do_display['ins_row']   = (string) '0';
            $do_display['bkm_form']  = (string) '0';
            $do_display['text_btn']  = (string) '0';
            $do_display['pview_lnk'] = (string) '0';
        } elseif ($GLOBALS['is_count'] || $GLOBALS['is_analyse']
            || $GLOBALS['is_maint'] || $GLOBALS['is_explain']
        ) {
            // 2.1 Statement is a "SELECT COUNT", a
            //     "CHECK/ANALYZE/REPAIR/OPTIMIZE", an "EXPLAIN" one or
            //     contains a "PROC ANALYSE" part
            $do_display['edit_lnk']  = 'nn'; // no edit link
            $do_display['del_lnk']   = 'nn'; // no delete link
            $do_display['sort_lnk']  = (string) '0';
            $do_display['nav_bar']   = (string) '0';
            $do_display['ins_row']   = (string) '0';
            $do_display['bkm_form']  = (string) '1';
            if ($GLOBALS['is_maint']) {
                $do_display['text_btn']  = (string) '1';
            } else {
                $do_display['text_btn']  = (string) '0';
            }
            $do_display['pview_lnk'] = (string) '1';

        } elseif ($GLOBALS['is_show']) {
            // 2.2 Statement is a "SHOW..."
            /**
             * 2.2.1
             * @todo defines edit/delete links depending on show statement
             */
            $tmp = preg_match(
                '@^SHOW[[:space:]]+(VARIABLES|(FULL[[:space:]]+)?'
                . 'PROCESSLIST|STATUS|TABLE|GRANTS|CREATE|LOGS|DATABASES|FIELDS)@i',
                $GLOBALS['sql_query'], $which
            );
            if (isset($which[1])
                && (strpos(' ' . strtoupper($which[1]), 'PROCESSLIST') > 0)
            ) {
                $do_display['edit_lnk'] = 'nn'; // no edit link
                $do_display['del_lnk']  = 'kp'; // "kill process" type edit link
            } else {
                // Default case -> no links
                $do_display['edit_lnk'] = 'nn'; // no edit link
                $do_display['del_lnk']  = 'nn'; // no delete link
            }
            // 2.2.2 Other settings
            $do_display['sort_lnk']  = (string) '0';
            $do_display['nav_bar']   = (string) '0';
            $do_display['ins_row']   = (string) '0';
            $do_display['bkm_form']  = (string) '1';
            $do_display['text_btn']  = (string) '1';
            $do_display['pview_lnk'] = (string) '1';
        } else {
            // 2.3 Other statements (ie "SELECT" ones) -> updates
            //     $do_display['edit_lnk'], $do_display['del_lnk'] and
            //     $do_display['text_btn'] (keeps other default values)
            $prev_table = $fields_meta[0]->table;
            $do_display['text_btn']  = (string) '1';
            for ($i = 0; $i < $GLOBALS['fields_cnt']; $i++) {
                $is_link = ($do_display['edit_lnk'] != 'nn')
                    || ($do_display['del_lnk'] != 'nn')
                    || ($do_display['sort_lnk'] != '0')
                    || ($do_display['ins_row'] != '0');
                // 2.3.2 Displays edit/delete/sort/insert links?
                if ($is_link
                    && (($fields_meta[$i]->table == '')
                    || ($fields_meta[$i]->table != $prev_table))
                ) {
                    $do_display['edit_lnk'] = 'nn'; // don't display links
                    $do_display['del_lnk']  = 'nn';
                    /**
                     * @todo May be problematic with same field names
                     * in two joined table.
                     */
                    // $do_display['sort_lnk'] = (string) '0';
                    $do_display['ins_row']  = (string) '0';
                    if ($do_display['text_btn'] == '1') {
                        break;
                    }
                } // end if (2.3.2)
                // 2.3.3 Always display print view link
                $do_display['pview_lnk']    = (string) '1';
                $prev_table = $fields_meta[$i]->table;
            } // end for
        } // end if..elseif...else (2.1 -> 2.3)
    } // end if (2)

    // 3. Gets the total number of rows if it is unknown
    if (isset($unlim_num_rows) && $unlim_num_rows != '') {
        $the_total = $unlim_num_rows;
    } elseif ((($do_display['nav_bar'] == '1') || ($do_display['sort_lnk'] == '1'))
             && (strlen($db) && !empty($table))
    ) {
        $the_total   = PMA_Table::countRecords($db, $table);
    }

    // 4. If navigation bar or sorting fields names URLs should be
    //    displayed but there is only one row, change these settings to
    //    false
    if ($do_display['nav_bar'] == '1' || $do_display['sort_lnk'] == '1') {

        // - Do not display sort links if less than 2 rows.
        // - For a VIEW we (probably) did not count the number of rows
        //   so don't test this number here, it would remove the possibility
        //   of sorting VIEW results.
        if (isset($unlim_num_rows)
            && $unlim_num_rows < 2
            && ! PMA_Table::isView($db, $table)
        ) {
            // force display of navbar for vertical/horizontal display-choice.
            // $do_display['nav_bar']  = (string) '0';
            $do_display['sort_lnk'] = (string) '0';
        }
    } // end if (3)

    // 5. Updates the synthetic var
    $the_disp_mode = join('', $do_display);

    return $do_display;

} // end of the 'PMA_setDisplayMode()' function


/**
 * Return true if we are executing a query in the form of
 * "SELECT * FROM <a table> ..."
 *
 * @return boolean
 */
function PMA_isSelect()
{
    // global variables set from sql.php
    global $is_count, $is_export, $is_func, $is_analyse;
    global $analyzed_sql;

    return ! ($is_count || $is_export || $is_func || $is_analyse)
        && (count($analyzed_sql[0]['select_expr']) == 0)
        && isset($analyzed_sql[0]['queryflags']['select_from'])
        && (count($analyzed_sql[0]['table_ref']) == 1);
}


/**
 * Get a navigation button
 *
 * @param string  $caption            iconic caption for button
 * @param string  $title              text for button
 * @param integer $pos                position for next query
 * @param string  $html_sql_query     query ready for display
 * @param string  $onsubmit           optional onsubmit clause
 * @param string  $input_for_real_end optional hidden field for special treatment
 * @param string  $onclick            optional onclick clause
 *
 * @return string                     html content
 *
 * @global string   $db             the database name
 * @global string   $table          the table name
 * @global string   $goto           the URL to go back in case of errors
 *
 * @access private
 *
 * @see     PMA_getTableNavigation()
 */
function PMA_getTableNavigationButton($caption, $title, $pos, $html_sql_query,
    $onsubmit = '', $input_for_real_end = '', $onclick = ''
) {

    global $db, $table, $goto;

    $caption_output = '';
    // for true or 'both'
    if ($GLOBALS['cfg']['NavigationBarIconic']) {
        $caption_output .= $caption;
    }

    // for false or 'both'
    if (($GLOBALS['cfg']['NavigationBarIconic'] === false)
        || ($GLOBALS['cfg']['NavigationBarIconic'] === 'both')
    ) {
        $caption_output .= '&nbsp;' . $title;
    }
    $title_output = ' title="' . $title . '"';

    return '<td>'
        . '<form action="sql.php" method="post" ' . $onsubmit . '>'
        . PMA_generate_common_hidden_inputs($db, $table)
        . '<input type="hidden" name="sql_query" value="' . $html_sql_query . '" />'
        . '<input type="hidden" name="pos" value="' . $pos . '" />'
        . '<input type="hidden" name="goto" value="' . $goto . '" />'
        . $input_for_real_end
        . '<input type="submit" name="navig"'
        . ($GLOBALS['cfg']['AjaxEnable'] ? ' class="ajax" ' : '' )
        . 'value="' . $caption_output . '" ' . $title_output . $onclick . ' />'
        . '</form>'
        . '</td>';

} // end function PMA_getTableNavigationButton()


/**
 * Get a navigation bar to browse among the results of a SQL query
 *
 * @param integer $pos_next                  the offset for the "next" page
 * @param integer $pos_prev                  the offset for the "previous" page
 * @param string  $sql_query                 the URL-encoded query
 * @param string  $id_for_direction_dropdown the id for the direction dropdown
 *
 * @return string                            html content
 *
 * @global string   $db             the database name
 * @global string   $table          the table name
 * @global string   $goto           the URL to go back in case of errors
 * @global integer  $num_rows       the total number of rows returned by the
 *                                   SQL query
 * @global integer  $unlim_num_rows the total number of rows returned by the
 *                                   SQL any programmatically appended "LIMIT" clause
 * @global boolean  $is_innodb      whether its InnoDB or not
 * @global array    $showtable      table definitions
 *
 * @access  private
 *
 * @see     PMA_getTable()
 */
function PMA_getTableNavigation($pos_next, $pos_prev, $sql_query,
    $id_for_direction_dropdown
) {

    global $db, $table, $goto;
    global $num_rows, $unlim_num_rows;
    global $is_innodb;
    global $showtable;

    $table_navigation_html = '';

    // here, using htmlentities() would cause problems if the query
    // contains accented characters
    $html_sql_query = htmlspecialchars($sql_query);

    /**
     * @todo move this to a central place
     * @todo for other future table types
     */
    $is_innodb = (isset($showtable['Type']) && $showtable['Type'] == 'InnoDB');

    // Navigation bar
    $table_navigation_html .= '<table class="navigation nospacing nopadding">'
        . '<tr>'
        . '<td class="navigation_separator"></td>';

    // Move to the beginning or to the previous page
    if ($_SESSION['tmp_user_values']['pos']
        && ($_SESSION['tmp_user_values']['max_rows'] != 'all')
    ) {

        $table_navigation_html .= PMA_getMoveBackwardButtonsForTableNavigation(
            $html_sql_query, $pos_prev
        );

    } // end move back

    $nbTotalPage = 1;
    //page redirection
    // (unless we are showing all records)
    if ($_SESSION['tmp_user_values']['max_rows'] != 'all') { //if1

        $pageNow = @floor(
            $_SESSION['tmp_user_values']['pos']
            / $_SESSION['tmp_user_values']['max_rows']
        ) + 1;

        $nbTotalPage = @ceil(
            $unlim_num_rows
            / $_SESSION['tmp_user_values']['max_rows']
        );

        if ($nbTotalPage > 1) { //if2

            $table_navigation_html .= '<td>';
            $_url_params = array(
                'db'        => $db,
                'table'     => $table,
                'sql_query' => $sql_query,
                'goto'      => $goto,
            );

            //<form> to keep the form alignment of button < and <<
            // and also to know what to execute when the selector changes
            $table_navigation_html .= '<form action="sql.php'
                . PMA_generate_common_url($_url_params)
                . '" method="post">';

            $table_navigation_html .= PMA_pageselector(
                $_SESSION['tmp_user_values']['max_rows'],
                $pageNow, $nbTotalPage, 200, 5, 5, 20, 10
            );

            $table_navigation_html .= '</form>'
                . '</td>';
        } //_if2
    } //_if1

    // Display the "Show all" button if allowed
    if (($num_rows < $unlim_num_rows)
        && ($GLOBALS['cfg']['ShowAll']
        || ($GLOBALS['cfg']['MaxRows'] * 5 >= $unlim_num_rows))
    ) {

        $table_navigation_html .= PMA_getShowAllButtonForTableNavigation(
            $db, $table, $html_sql_query, $goto
        );

    } // end show all

    // Move to the next page or to the last one
    $endpos = $_SESSION['tmp_user_values']['pos']
        + $_SESSION['tmp_user_values']['max_rows'];

    if (($endpos < $unlim_num_rows)
        && ($num_rows >= $_SESSION['tmp_user_values']['max_rows'])
        && ($_SESSION['tmp_user_values']['max_rows'] != 'all')
    ) {

        $table_navigation_html .= PMA_getMoveForwardButtonsForTableNavigation(
            $html_sql_query, $pos_next, $is_innodb,
            $unlim_num_rows, $num_rows
        );

    } // end move toward

    // show separator if pagination happen
    if ($nbTotalPage > 1) {
        $table_navigation_html
            .= '<td><div class="navigation_separator">|</div></td>';
    }

    $table_navigation_html .= '<td>'
        . '<div class="save_edited hide">'
        . '<input type="submit" value="' . __('Save edited data') . '" />'
        . '<div class="navigation_separator">|</div>'
        . '</div>'
        . '</td>'
        . '<td>'
        . '<div class="restore_column hide">'
        . '<input type="submit" value="' . __('Restore column order') . '" />'
        . '<div class="navigation_separator">|</div>'
        . '</div>'
        . '</td>';

    // if displaying a VIEW, $unlim_num_rows could be zero because
    // of $cfg['MaxExactCountViews']; in this case, avoid passing
    // the 5th parameter to checkFormElementInRange()
    // (this means we can't validate the upper limit
    $table_navigation_html .= '<td class="navigation_goto">';

    $table_navigation_html .= '<form action="sql.php" method="post" '
        . 'onsubmit="return '
            . '(checkFormElementInRange('
                . 'this, '
                . '\'session_max_rows\', '
                . '\'' . str_replace('\'', '\\\'', __('%d is not valid row number.'))
                . '\', '
                . '1)'
            . ' &amp;&amp; '
            . 'checkFormElementInRange('
                . 'this, '
                . '\'pos\', '
                . '\'' . str_replace('\'', '\\\'', __('%d is not valid row number.'))
                . '\', '
                . '0'
                . ($unlim_num_rows > 0 ? ', ' . ($unlim_num_rows - 1) : '') . ')'
            . ')'
        .'">';

    $table_navigation_html .= PMA_generate_common_hidden_inputs($db, $table);

    $table_navigation_html .= PMA_getAdditionalFieldsForTableNavigation(
        $html_sql_query, $goto, $pos_next,
        $unlim_num_rows, $id_for_direction_dropdown
    );

    $table_navigation_html .= '</form>'
        . '</td>'
        . '<td class="navigation_separator"></td>'
        . '</tr>'
        . '</table>';

    return $table_navigation_html;

} // end of the 'PMA_getTableNavigation()' function


/**
 * Prepare move backward buttons - previous and first
 *
 * @param string  $html_sql_query the sql encoded by html special characters
 * @param integer $pos_prev       the offset for the "previous" page
 *
 * @return  string                  html content
 *
 * @see     PMA_getTableNavigation()
 */
function PMA_getMoveBackwardButtonsForTableNavigation($html_sql_query, $pos_prev)
{
    return PMA_getTableNavigationButton(
        '&lt;&lt;', _pgettext('First page', 'Begin'), 0, $html_sql_query
    )
    . PMA_getTableNavigationButton(
        '&lt;', _pgettext('Previous page', 'Previous'), $pos_prev,
        $html_sql_query
    );
}


/**
 * Prepare Show All button for table navigation
 *
 * @param string $db             the database name
 * @param string $table          the table name
 * @param string $html_sql_query the sql encoded by html special characters
 * @param string $goto           the URL to go back in case of errors
 *
 * @return  string                          html content
 *
 * @see     PMA_getTableNavigation()
 */
function PMA_getShowAllButtonForTableNavigation($db, $table, $html_sql_query, $goto)
{
    return "\n"
        . '<td>'
        . '<form action="sql.php" method="post">'
        . PMA_generate_common_hidden_inputs($db, $table)
        . '<input type="hidden" name="sql_query" value="' . $html_sql_query . '" />'
        . '<input type="hidden" name="pos" value="0" />'
        . '<input type="hidden" name="session_max_rows" value="all" />'
        . '<input type="hidden" name="goto" value="' . $goto . '" />'
        . '<input type="submit" name="navig" value="' . __('Show all') . '" />'
        . '</form>'
        . '</td>';
}


/**
 * Prepare move farward buttons - next and last
 *
 * @param string  $html_sql_query the sql encoded by html special characters
 * @param integer $pos_next       the offset for the "next" page
 * @param boolean $is_innodb      whether its InnoDB or not
 * @param integer $unlim_num_rows the total number of rows returned by the
 * @param integer $num_rows       the total number of rows returned by the
 *
 * @return  string  $buttons_html   html content
 *
 * @see     PMA_getTableNavigation()
 */
function PMA_getMoveForwardButtonsForTableNavigation(
    $html_sql_query, $pos_next, $is_innodb, $unlim_num_rows, $num_rows
) {

    // display the Next button
    $buttons_html = PMA_getTableNavigationButton(
        '&gt;',
        _pgettext('Next page', 'Next'),
        $pos_next,
        $html_sql_query
    );

    // prepare some options for the End button
    if ($is_innodb && $unlim_num_rows > $GLOBALS['cfg']['MaxExactCount']) {
        $input_for_real_end = '<input id="real_end_input" type="hidden" '
            . 'name="find_real_end" value="1" />';
        // no backquote around this message
        $onclick = '';
    } else {
        $input_for_real_end = $onclick = '';
    }

    $onsubmit = 'onsubmit="return '
        . ($_SESSION['tmp_user_values']['pos']
                + $_SESSION['tmp_user_values']['max_rows'] < $unlim_num_rows
            && $num_rows >= $_SESSION['tmp_user_values']['max_rows'])
        ? 'true'
        : 'false' . '"';

    // display the End button
    $buttons_html .= PMA_getTableNavigationButton(
        '&gt;&gt;',
        _pgettext('Last page', 'End'),
        @((ceil($unlim_num_rows / $_SESSION['tmp_user_values']['max_rows'])- 1)
        * $_SESSION['tmp_user_values']['max_rows']),
        $html_sql_query, $onsubmit, $input_for_real_end, $onclick
    );

    return $buttons_html;

}


/**
 * Prepare feilds followed by Show button for table navigation
 * Start row, Number of rows, Headers every
 *
 * @param string  $html_sql_query            the sql encoded by html special
 *                                           characters
 * @param string  $goto                      the URL to go back in case of errors
 * @param integer $pos_next                  the offset for the "next" page
 * @param integer $unlim_num_rows            the total number of rows returned by the
 * @param string  $id_for_direction_dropdown the id for the direction dropdown
 *
 * @return  string  $additional_fields_html html content
 *
 * @see     PMA_getTableNavigation()
 */
function PMA_getAdditionalFieldsForTableNavigation(
    $html_sql_query, $goto, $pos_next,
    $unlim_num_rows, $id_for_direction_dropdown
) {

    $additional_fields_html = '';

    $additional_fields_html .= '<input type="hidden" name="sql_query" '
        . 'value="' . $html_sql_query . '" />'
        . '<input type="hidden" name="goto" value="' . $goto . '" />'
        . '<input type="submit" name="navig"'
        . ($GLOBALS['cfg']['AjaxEnable'] ? ' class="ajax"' : '')
        . ' value="' . __('Show') . ' :" />'
        . __('Start row') . ': ' . "\n"
        . '<input type="text" name="pos" size="3" value="'
        . (($pos_next >= $unlim_num_rows) ? 0 : $pos_next)
        . '" class="textfield" onfocus="this.select()" />'
        . __('Number of rows') . ': ' . "\n"
        . '<input type="text" name="session_max_rows" size="3" value="'
        . (($_SESSION['tmp_user_values']['max_rows'] != 'all')
            ? $_SESSION['tmp_user_values']['max_rows']
            : $GLOBALS['cfg']['MaxRows'])
        . '" class="textfield" onfocus="this.select()" />';

    if ($GLOBALS['cfg']['ShowDisplayDirection']) {
        // Display mode (horizontal/vertical and repeat headers)
        $additional_fields_html .= __('Mode') . ': ' . "\n";
        $choices = array(
                'horizontal'        => __('horizontal'),
                'horizontalflipped' => __('horizontal (rotated headers)'),
                'vertical'          => __('vertical')
            );

        $additional_fields_html .= PMA_getDropdown(
            'disp_direction', $choices,
            $_SESSION['tmp_user_values']['disp_direction'],
            $id_for_direction_dropdown
        );
        unset($choices);
    }

    $additional_fields_html .= sprintf(
        __('Headers every %s rows'),
        '<input type="text" size="3" name="repeat_cells" value="'
        . $_SESSION['tmp_user_values']['repeat_cells']
        . '" class="textfield" /> '
    );

    return $additional_fields_html;

}


/**
 * Get the headers of the results table
 *
 * @param array   &$is_display                 which elements to display
 * @param array   &$fields_meta                the list of fields properties
 * @param integer $fields_cnt                  the total number of fields returned by
 *                                             the SQL query
 * @param array   $analyzed_sql                the analyzed query
 * @param string  $sort_expression             sort expression
 * @param string  $sort_expression_nodirection sort expression without direction
 * @param string  $sort_direction              sort direction
 *
 * @return string                      html content
 *
 * @global string   $db               the database name
 * @global string   $table            the table name
 * @global string   $goto             the URL to go back in case of errors
 * @global string   $sql_query        the SQL query
 * @global integer  $num_rows         the total number of rows returned by the
 *                                     SQL query
 * @global array    $vertical_display informations used with vertical display
 *                                     mode
 *
 * @access  private
 *
 * @see     PMA_getTable()
 */
function PMA_getTableHeaders(&$is_display, &$fields_meta, $fields_cnt = 0,
    $analyzed_sql = '', $sort_expression = '', $sort_expression_nodirection = '',
    $sort_direction = ''
) {

    global $db, $table, $goto;
    global $sql_query, $num_rows;
    global $vertical_display, $highlight_columns;

    $table_headers_html = '';

    // required to generate sort links that will remember whether the
    // "Show all" button has been clicked
    $sql_md5 = md5($GLOBALS['sql_query']);
    $session_max_rows = $_SESSION['tmp_user_values']['query'][$sql_md5]['max_rows'];

    $direction = isset($_SESSION['tmp_user_values']['disp_direction'])
        ? $_SESSION['tmp_user_values']['disp_direction']
        : '';

    if ($analyzed_sql == '') {
        $analyzed_sql = array();
    }

    $directionCondition = ($direction == 'horizontal')
        || ($direction == 'horizontalflipped');

    // can the result be sorted?
    if ($is_display['sort_lnk'] == '1') {

        // Just as fallback
        $unsorted_sql_query     = $sql_query;
        if (isset($analyzed_sql[0]['unsorted_query'])) {
            $unsorted_sql_query = $analyzed_sql[0]['unsorted_query'];
        }
        // Handles the case of multiple clicks on a column's header
        // which would add many spaces before "ORDER BY" in the
        // generated query.
        $unsorted_sql_query = trim($unsorted_sql_query);

        // sorting by indexes, only if it makes sense (only one table ref)
        if (isset($analyzed_sql)
            && isset($analyzed_sql[0])
            && isset($analyzed_sql[0]['querytype'])
            && ($analyzed_sql[0]['querytype'] == 'SELECT')
            && isset($analyzed_sql[0]['table_ref'])
            && (count($analyzed_sql[0]['table_ref']) == 1)
        ) {

            // grab indexes data:
            $indexes = PMA_Index::getFromTable($table, $db);

            // do we have any index?
            if ($indexes) {
                $table_headers_html .= PMA_getSortByKeyDropDown(
                    $db, $table, $indexes, $sort_expression,
                    $unsorted_sql_query
                );
            }
        }
    }

    // Output data needed for grid editing
    $table_headers_html .= '<input id="save_cells_at_once" type="hidden" value="'
        . $GLOBALS['cfg']['SaveCellsAtOnce'] . '" />'
        . '<div class="common_hidden_inputs">'
        . PMA_generate_common_hidden_inputs($db, $table)
        . '</div>';

    // Output data needed for column reordering and show/hide column
    if (PMA_isSelect()) {
        $table_headers_html .= PMA_getDataForResettingColumnOrder();
    }

    $vertical_display['emptypre']   = 0;
    $vertical_display['emptyafter'] = 0;
    $vertical_display['textbtn']    = '';

    // Display options (if we are not in print view)
    if (! (isset($GLOBALS['printview']) && ($GLOBALS['printview'] == '1'))) {

        $table_headers_html .= PMA_getOptionsBlock($db, $table, $sql_query, $goto);

        // prepare full/partial text button or link
        $full_or_partial_text_link = PMA_getFullOrPartialTextButtonOrLink(
            $db, $table, $sql_query, $goto
        );
    }

    // Start of form for multi-rows edit/delete/export
    $table_headers_html .= PMA_getFormForMultiRowOperations(
        $db, $table, $is_display['del_lnk']
    );

    // 1. Displays the full/partial text button (part 1)...
    if ($directionCondition) {

        $table_headers_html .= '<thead><tr>' . "\n";

        $colspan = (($is_display['edit_lnk'] != 'nn')
            && ($is_display['del_lnk'] != 'nn'))
            ? ' colspan="4"'
            : '';

    } else {
        $rowspan = (($is_display['edit_lnk'] != 'nn')
            && ($is_display['del_lnk'] != 'nn'))
            ? ' rowspan="4"'
            : '';
    }

    //     ... before the result table
    if ((($is_display['edit_lnk'] == 'nn') && ($is_display['del_lnk'] == 'nn'))
        && ($is_display['text_btn'] == '1')
    ) {

        $vertical_display['emptypre'] = (($is_display['edit_lnk'] != 'nn')
            && ($is_display['del_lnk'] != 'nn')) ? 4 : 0;

        if ($directionCondition) {

            $table_headers_html .= '<th colspan="' . $fields_cnt . '"></th>'
                . '</tr>'
                . '<tr>';

            // end horizontal/horizontalflipped mode
        } else {

            $span = $num_rows + 1 + floor(
                $num_rows / $_SESSION['tmp_user_values']['repeat_cells']
            );
            $table_headers_html .= '<tr><th colspan="' . $span . '"></th></tr>';

        } // end vertical mode

    } elseif ((($GLOBALS['cfg']['RowActionLinks'] == 'left')
        || ($GLOBALS['cfg']['RowActionLinks'] == 'both'))
        && ($is_display['text_btn'] == '1')
    ) {
        //     ... at the left column of the result table header if possible
        //     and required

        $vertical_display['emptypre'] = (($is_display['edit_lnk'] != 'nn')
            && ($is_display['del_lnk'] != 'nn')) ? 4 : 0;

        if ($directionCondition) {

            $table_headers_html .= '<th ' . $colspan . '>'
                . $full_or_partial_text_link . '</th>';
            // end horizontal/horizontalflipped mode

        } else {

            $vertical_display['textbtn']
                = '    <th ' . $rowspan . ' class="vmiddle">' . "\n"
                . '        ' . "\n"
                . '    </th>' . "\n";
        } // end vertical mode

    } elseif ((($GLOBALS['cfg']['RowActionLinks'] == 'left')
        || ($GLOBALS['cfg']['RowActionLinks'] == 'both'))
        && (($is_display['edit_lnk'] != 'nn') || ($is_display['del_lnk'] != 'nn'))
    ) {
        //     ... elseif no button, displays empty(ies) col(s) if required

        $vertical_display['emptypre'] = (($is_display['edit_lnk'] != 'nn')
            && ($is_display['del_lnk'] != 'nn')) ? 4 : 0;

        if ($directionCondition) {

            $table_headers_html .= '<td ' . $colspan . '></td>';

            // end horizontal/horizontalfipped mode
        } else {
            $vertical_display['textbtn'] = '    <td' . $rowspan . '></td>' . "\n";
        } // end vertical mode

    } elseif (($GLOBALS['cfg']['RowActionLinks'] == 'none')
        && ($directionCondition)
    ) {
        // ... elseif display an empty column if the actions links are disabled to
        //     match the rest of the table
        $table_headers_html .= '<th></th>';
    }

    // 2. Displays the fields' name
    // 2.0 If sorting links should be used, checks if the query is a "JOIN"
    //     statement (see 2.1.3)

    // 2.0.1 Prepare Display column comments if enabled
    //       ($GLOBALS['cfg']['ShowBrowseComments']).
    //       Do not show comments, if using horizontalflipped mode,
    //       because of space usage
    if ($GLOBALS['cfg']['ShowBrowseComments']
        && ($direction != 'horizontalflipped')
    ) {
        $comments_map = array();
        if (isset($analyzed_sql[0]) && is_array($analyzed_sql[0])) {
            foreach ($analyzed_sql[0]['table_ref'] as $tbl) {
                $tb = $tbl['table_true_name'];
                $comments_map[$tb] = PMA_getComments($db, $tb);
                unset($tb);
            }
        }
    }

    if ($GLOBALS['cfgRelation']['commwork']
        && $GLOBALS['cfgRelation']['mimework']
        && $GLOBALS['cfg']['BrowseMIME']
        && ! $_SESSION['tmp_user_values']['hide_transformation']
    ) {
        include_once './libraries/transformations.lib.php';
        $GLOBALS['mime_map'] = PMA_getMIME($db, $table);
    }

    // See if we have to highlight any header fields of a WHERE query.
    // Uses SQL-Parser results.
    $highlight_columns = array();
    if (isset($analyzed_sql) && isset($analyzed_sql[0])
        && isset($analyzed_sql[0]['where_clause_identifiers'])
    ) {

        $wi = 0;
        if (isset($analyzed_sql[0]['where_clause_identifiers'])
            && is_array($analyzed_sql[0]['where_clause_identifiers'])
        ) {
            foreach ($analyzed_sql[0]['where_clause_identifiers']
                as $wci_nr => $wci
            ) {
                $highlight_columns[$wci] = 'true';
            }
        }
    }

    list($col_order, $col_visib) = PMA_getColumnParams();

    for ($j = 0; $j < $fields_cnt; $j++) {

        // assign $i with appropriate column order
        $i = $col_order ? $col_order[$j] : $j;

        //  See if this column should get highlight because it's used in the
        //  where-query.
        $condition_field = (isset($highlight_columns[$fields_meta[$i]->name])
            || isset($highlight_columns[PMA_backquote($fields_meta[$i]->name)]))
            ? true
            : false;

        // 2.0 Prepare comment-HTML-wrappers for each row, if defined/enabled.
        $comments = PMA_getCommentForRow($comments_map, $fields_meta[$i]);

        if ($is_display['sort_lnk'] == '1') {
            // 2.1 Results can be sorted

            // 2.1.1 Checks if the table name is required; it's the case
            //       for a query with a "JOIN" statement and if the column
            //       isn't aliased, or in queries like
            //       SELECT `1`.`master_field` , `2`.`master_field`
            //       FROM `PMA_relation` AS `1` , `PMA_relation` AS `2`

            $sort_tbl = (isset($fields_meta[$i]->table)
                && strlen($fields_meta[$i]->table))
                ? PMA_backquote($fields_meta[$i]->table) . '.'
                : '';

            // 2.1.2 Checks if the current column is used to sort the
            //       results
            // the orgname member does not exist for all MySQL versions
            // but if found, it's the one on which to sort
            $name_to_use_in_sort = $fields_meta[$i]->name;
            $is_orgname = false;
            if (isset($fields_meta[$i]->orgname)
                && strlen($fields_meta[$i]->orgname)
            ) {
                $name_to_use_in_sort = $fields_meta[$i]->orgname;
                $is_orgname = true;
            }

            // $name_to_use_in_sort might contain a space due to
            // formatting of function expressions like "COUNT(name )"
            // so we remove the space in this situation
            $name_to_use_in_sort = str_replace(' )', ')', $name_to_use_in_sort);

            $is_in_sort = PMA_isInSorted(
                $sort_expression, $sort_expression_nodirection,
                $sort_tbl, $name_to_use_in_sort
            );

            // 2.1.3 Check the field name for a bracket.
            //       If it contains one, it's probably a function column
            //       like 'COUNT(`field`)'
            //       It still might be a column name of a view. See bug #3383711
            //       Check is_orgname.
            if ((strpos($name_to_use_in_sort, '(') !== false) && ! $is_orgname) {
                $sort_order = "\n" . 'ORDER BY ' . $name_to_use_in_sort . ' ';
            } else {
                $sort_order = "\n" . 'ORDER BY ' . $sort_tbl
                    . PMA_backquote($name_to_use_in_sort) . ' ';
            }
            unset($name_to_use_in_sort);
            unset($is_orgname);

            // 2.1.4 Do define the sorting URL

            list($sort_order, $order_img) = PMA_getSortingUrlParams(
                $is_in_sort, $sort_direction, $fields_meta[$i],
                $sort_order, $i
            );

            if (preg_match(
                '@(.*)([[:space:]](LIMIT (.*)|PROCEDURE (.*)|FOR UPDATE|'
                . 'LOCK IN SHARE MODE))@is',
                $unsorted_sql_query, $regs3
            )) {
                $sorted_sql_query = $regs3[1] . $sort_order . $regs3[2];
            } else {
                $sorted_sql_query = $unsorted_sql_query . $sort_order;
            }

            $_url_params = array(
                'db'                => $db,
                'table'             => $table,
                'sql_query'         => $sorted_sql_query,
                'session_max_rows'  => $session_max_rows
            );
            $order_url  = 'sql.php' . PMA_generate_common_url($_url_params);

            // 2.1.5 Displays the sorting URL
            // enable sort order swapping for image
            $order_link = PMA_getSortOrderLink(
                $order_img, $i, $direction, $fields_meta[$i], $order_url
            );

            if ($directionCondition) {
                $table_headers_html .= PMA_getDraggableClassForSortableColumns(
                    $col_visib, $col_visib[$j], $condition_field,
                    $direction, $fields_meta[$i], $order_link, $comments
                );
            }

            $vertical_display['desc'][] = '    <th '
                . 'class="draggable'
                . ($condition_field ? ' condition' : '')
                . '" data-column="' . htmlspecialchars($fields_meta[$i]->name)
                . '">' . "\n" . $order_link . $comments . '    </th>' . "\n";
        } else {
            // 2.2 Results can't be sorted

            if ($directionCondition) {
                $table_headers_html .= PMA_getDraggableClassForNonSortableColumns(
                    $col_visib, $col_visib[$j], $condition_field,
                    $direction, $fields_meta[$i], $comments
                );
            }

            $vertical_display['desc'][] = '    <th '
                . 'class="draggable'
                . ($condition_field ? ' condition"' : '')
                . '" data-column="' . htmlspecialchars($fields_meta[$i]->name)
                . '">' . "\n" . '        ' . htmlspecialchars($fields_meta[$i]->name)
                . "\n" . $comments . '    </th>';
        } // end else (2.2)
    } // end for

    // 3. Displays the needed checkboxes at the right
    //    column of the result table header if possible and required...
    if ((($GLOBALS['cfg']['RowActionLinks'] == 'right')
        || ($GLOBALS['cfg']['RowActionLinks'] == 'both'))
        && (($is_display['edit_lnk'] != 'nn') || ($is_display['del_lnk'] != 'nn'))
        && ($is_display['text_btn'] == '1')
    ) {

        $vertical_display['emptyafter'] = (($is_display['edit_lnk'] != 'nn')
            && ($is_display['del_lnk'] != 'nn')) ? 4 : 1;

        if ($directionCondition) {
            $table_headers_html .= "\n"
                . '<th ' . $colspan . '>' . $full_or_partial_text_link
                . '</th>';

            // end horizontal/horizontalflipped mode
        } else {
            $vertical_display['textbtn'] = '    <th ' . $rowspan
                . ' class="vmiddle">' . "\n"
                . '        ' . "\n"
                . '    </th>' . "\n";
        } // end vertical mode
    } elseif ((($GLOBALS['cfg']['RowActionLinks'] == 'left')
        || ($GLOBALS['cfg']['RowActionLinks'] == 'both'))
        && (($is_display['edit_lnk'] == 'nn') && ($is_display['del_lnk'] == 'nn'))
        && (! isset($GLOBALS['is_header_sent']) || ! $GLOBALS['is_header_sent'])
    ) {
        //     ... elseif no button, displays empty columns if required
        // (unless coming from Browse mode print view)

        $vertical_display['emptyafter'] = (($is_display['edit_lnk'] != 'nn')
            && ($is_display['del_lnk'] != 'nn')) ? 4 : 1;

        if ($directionCondition) {
            $table_headers_html .= "\n"
                . '<td ' . $colspan . '></td>';

            // end horizontal/horizontalflipped mode
        } else {
            $vertical_display['textbtn'] = '    <td' . $rowspan . '></td>' . "\n";
        } // end vertical mode
    }

    if ($directionCondition) {
        $table_headers_html .= '</tr>'
            . '</thead>';
    }

    return $table_headers_html;

} // end of the 'PMA_getTableHeaders()' function


/**
 * Prepare sort by key dropdown - html code segment
 *
 * @param string $db                 the database name
 * @param string $table              the table name
 * @param array  $indexes            the indexes of the table for sort criteria
 * @param string $sort_expression    the sort expression
 * @param string $unsorted_sql_query the unsorted sql query
 *
 * @return  string  $drop_down_html         html content
 *
 * @see     PMA_getTableHeaders()
 */
function PMA_getSortByKeyDropDown(
    $db, $table, $indexes, $sort_expression, $unsorted_sql_query
) {

    $drop_down_html = '';

    $drop_down_html .= '<form action="sql.php" method="post">' . "\n"
        . PMA_generate_common_hidden_inputs($db, $table)
        . __('Sort by key')
        . ': <select name="sql_query" class="autosubmit">' . "\n";

    $used_index = false;
    $local_order = (isset($sort_expression) ? $sort_expression : '');

    foreach ($indexes as $index) {

        $asc_sort = '`'
            . implode('` ASC, `', array_keys($index->getColumns()))
            . '` ASC';

        $desc_sort = '`'
            . implode('` DESC, `', array_keys($index->getColumns()))
            . '` DESC';

        $used_index = $used_index
            || ($local_order == $asc_sort)
            || ($local_order == $desc_sort);

        if (preg_match(
            '@(.*)([[:space:]](LIMIT (.*)|PROCEDURE (.*)|'
            . 'FOR UPDATE|LOCK IN SHARE MODE))@is',
            $unsorted_sql_query, $my_reg
        )) {
            $unsorted_sql_query_first_part = $my_reg[1];
            $unsorted_sql_query_second_part = $my_reg[2];
        } else {
            $unsorted_sql_query_first_part = $unsorted_sql_query;
            $unsorted_sql_query_second_part = '';
        }

        $drop_down_html .= '<option value="'
            . htmlspecialchars(
                $unsorted_sql_query_first_part  . "\n"
                . ' ORDER BY ' . $asc_sort
                . $unsorted_sql_query_second_part
            )
            . '"' . ($local_order == $asc_sort
                ? ' selected="selected"'
                : '')
            . '>' . htmlspecialchars($index->getName()) . ' ('
            . __('Ascending') . ')</option>';

        $drop_down_html .= '<option value="'
            . htmlspecialchars(
                $unsorted_sql_query_first_part . "\n"
                . ' ORDER BY ' . $desc_sort
                . $unsorted_sql_query_second_part
            )
            . '"' . ($local_order == $desc_sort
                ? ' selected="selected"'
                : '')
            . '>' . htmlspecialchars($index->getName()) . ' ('
            . __('Descending') . ')</option>';
    }

    $drop_down_html .= '<option value="' . htmlspecialchars($unsorted_sql_query)
        . '"' . ($used_index ? '' : ' selected="selected"') . '>' . __('None')
        . '</option>'
        . '</select>' . "\n"
        . '</form>' . "\n";

    return $drop_down_html;

}


/**
 * Prepare data for column restoring and show/hide
 *
 * @return  string  $data_html      html content
 *
 * @see     PMA_getTableHeaders()
 */
function PMA_getDataForResettingColumnOrder()
{

    $data_html = '';

    // generate the column order, if it is set
    $pmatable = new PMA_Table($GLOBALS['table'], $GLOBALS['db']);
    $col_order = $pmatable->getUiProp(PMA_Table::PROP_COLUMN_ORDER);

    if ($col_order) {
        $data_html .= '<input id="col_order" type="hidden" value="'
            . implode(',', $col_order) . '" />';
    }

    $col_visib = $pmatable->getUiProp(PMA_Table::PROP_COLUMN_VISIB);

    if ($col_visib) {
        $data_html .= '<input id="col_visib" type="hidden" value="'
            . implode(',', $col_visib) . '" />';
    }

    // generate table create time
    if (! PMA_Table::isView($GLOBALS['table'], $GLOBALS['db'])) {
        $data_html .= '<input id="table_create_time" type="hidden" value="'
            . PMA_Table::sGetStatusInfo(
                $GLOBALS['db'], $GLOBALS['table'], 'Create_time'
            ) . '" />';
    }

    return $data_html;

}


/**
 * Prepare option fields block
 *
 * @param string $db        the database name
 * @param string $table     the table name
 * @param string $sql_query the SQL query
 * @param string $goto      the URL to go back in case of errors
 *
 * @return  string  $options_html   html content
 *
 * @see     PMA_getTableHeaders()
 */
function PMA_getOptionsBlock($db, $table, $sql_query, $goto)
{

    $options_html = '';

    $options_html .= '<form method="post" action="sql.php" '
        . 'name="displayOptionsForm" '
        . 'id="displayOptionsForm"';

    if ($GLOBALS['cfg']['AjaxEnable']) {
        $options_html .= ' class="ajax" ';
    }

    $options_html .= '>';
    $url_params = array(
        'db' => $db,
        'table' => $table,
        'sql_query' => $sql_query,
        'goto' => $goto,
        'display_options_form' => 1
    );

    $options_html .= PMA_generate_common_hidden_inputs($url_params)
        . '<br />'
        . PMA_getDivForSliderEffect('displayoptions', __('Options'))
        . '<fieldset>';

    $options_html .= '<div class="formelement">';
    $choices = array(
        'P'   => __('Partial texts'),
        'F'   => __('Full texts')
    );

    $options_html .= PMA_getRadioFields(
        'display_text', $choices,
        $_SESSION['tmp_user_values']['display_text']
    )
    . '</div>';

    if ($GLOBALS['cfgRelation']['relwork']
        && $GLOBALS['cfgRelation']['displaywork']
    ) {
        $options_html .= '<div class="formelement">';
        $choices = array(
            'K'   => __('Relational key'),
            'D'   => __('Relational display column')
        );

        $options_html .= PMA_getRadioFields(
            'relational_display', $choices,
            $_SESSION['tmp_user_values']['relational_display']
        )
        . '</div>';
    }

    $options_html .= '<div class="formelement">'
        . PMA_getCheckbox(
            'display_binary', __('Show binary contents'),
            ! empty($_SESSION['tmp_user_values']['display_binary']), false
        )
        . '<br />'
        . PMA_getCheckbox(
            'display_blob', __('Show BLOB contents'),
            ! empty($_SESSION['tmp_user_values']['display_blob']), false
        )
        . '<br />'
        . PMA_getCheckbox(
            'display_binary_as_hex', __('Show binary contents as HEX'),
            ! empty($_SESSION['tmp_user_values']['display_binary_as_hex']), false
        )
        . '</div>';

    // I would have preferred to name this "display_transformation".
    // This is the only way I found to be able to keep this setting sticky
    // per SQL query, and at the same time have a default that displays
    // the transformations.
    $options_html .= '<div class="formelement">'
        . PMA_getCheckbox(
            'hide_transformation', __('Hide browser transformation'),
            ! empty($_SESSION['tmp_user_values']['hide_transformation']), false
        )
        . '</div>';

    if (! PMA_DRIZZLE) {
        $options_html .= '<div class="formelement">';
        $choices = array(
            'GEOM'  => __('Geometry'),
            'WKT'   => __('Well Known Text'),
            'WKB'   => __('Well Known Binary')
        );

        $options_html .= PMA_getRadioFields(
            'geometry_display', $choices,
            $_SESSION['tmp_user_values']['geometry_display']
        )
            . '</div>';
    }

    $options_html .= '<div class="clearfloat"></div>'
        . '</fieldset>';

    $options_html .= '<fieldset class="tblFooters">'
        . '<input type="submit" value="' . __('Go') . '" />'
        . '</fieldset>'
        . '</div>'
        . '</form>';

    return $options_html;

}


/**
 * Get full/partial text button or link
 *
 * @param string $db        the database name
 * @param string $table     the table name
 * @param string $sql_query the SQL query
 * @param string $goto      the URL to go back in case of errors
 *
 * @return string html content
 *
 * @see     PMA_getTableHeaders()
 */
function PMA_getFullOrPartialTextButtonOrLink($db, $table, $sql_query, $goto)
{

    $url_params_full_text = array(
        'db' => $db,
        'table' => $table,
        'sql_query' => $sql_query,
        'goto' => $goto,
        'full_text_button' => 1
    );

    if ($_SESSION['tmp_user_values']['display_text'] == 'F') {
        // currently in fulltext mode so show the opposite link
        $tmp_image_file = $GLOBALS['pmaThemeImage'] . 's_partialtext.png';
        $tmp_txt = __('Partial texts');
        $url_params_full_text['display_text'] = 'P';
    } else {
        $tmp_image_file = $GLOBALS['pmaThemeImage'] . 's_fulltext.png';
        $tmp_txt = __('Full texts');
        $url_params_full_text['display_text'] = 'F';
    }

    $tmp_image = '<img class="fulltext" src="' . $tmp_image_file . '" alt="'
                 . $tmp_txt . '" title="' . $tmp_txt . '" />';
    $tmp_url = 'sql.php' . PMA_generate_common_url($url_params_full_text);

    return PMA_linkOrButton($tmp_url, $tmp_image, array(), false);

}


/**
 * Prepare html form for multi row operations
 *
 * @param string $db      the database name
 * @param string $table   the table name
 * @param string $del_lnk the delete link of current row
 *
 * @return  string  $form_html          html content
 *
 * @see     PMA_getTableHeaders()
 */
function PMA_getFormForMultiRowOperations($db, $table, $del_lnk)
{

    $form_html = '';

    if (($del_lnk == 'dr') || ($del_lnk == 'kp')) {

        $form_html .= '<form method="post" action="tbl_row_action.php" '
            . 'name="resultsForm" id="resultsForm"';

        if ($GLOBALS['cfg']['AjaxEnable']) {
            $form_html .= ' class="ajax" ';
        }

        $form_html .= '>' . "\n"
            . PMA_generate_common_hidden_inputs($db, $table, 1)
            . '<input type="hidden" name="goto" value="sql.php" />' . "\n";
    }

    $form_html .= '<table id="table_results" class="data';
    if ($GLOBALS['cfg']['AjaxEnable']) {
        $form_html .= ' ajax';
    }
    $form_html .= '">' . "\n";

    return $form_html;

}


/**
 * Get comment for row
 *
 * @param array $comments_map comments array
 * @param array $fields_meta  set of field properties
 *
 * @return  string  $comment        html content
 *
 * @see     PMA_getTableHeaders()
 */
function PMA_getCommentForRow($comments_map, $fields_meta)
{
    $comments = '';
    if (isset($comments_map)
        && isset($comments_map[$fields_meta->table])
        && isset($comments_map[$fields_meta->table][$fields_meta->name])
    ) {
        $comments = '<span class="tblcomment">'
            . htmlspecialchars(
                $comments_map[$fields_meta->table][$fields_meta->name]
            )
            . '</span>';
    }
    return $comments;
}


/**
 * Check whether the column is sorted
 *
 * @param string $sort_expression             sort expression
 * @param string $sort_expression_nodirection sort expression without direction
 * @param string $sort_tbl                    the table name
 * @param string $name_to_use_in_sort         the sorting column name
 *
 * @return boolean $is_in_sort                   the column sorted or not
 *
 * @see    PMA_getTableHeaders()
 */
function PMA_isInSorted(
    $sort_expression, $sort_expression_nodirection, $sort_tbl,
    $name_to_use_in_sort
) {

    if (empty($sort_expression)) {
        $is_in_sort = false;
    } else {
        // Field name may be preceded by a space, or any number
        // of characters followed by a dot (tablename.fieldname)
        // so do a direct comparison for the sort expression;
        // this avoids problems with queries like
        // "SELECT id, count(id)..." and clicking to sort
        // on id or on count(id).
        // Another query to test this:
        // SELECT p.*, FROM_UNIXTIME(p.temps) FROM mytable AS p
        // (and try clicking on each column's header twice)
        if (! empty($sort_tbl)
            && (strpos($sort_expression_nodirection, $sort_tbl) === false)
            && (strpos($sort_expression_nodirection, '(') === false)
        ) {
            $new_sort_expression_nodirection = $sort_tbl
                . $sort_expression_nodirection;
        } else {
            $new_sort_expression_nodirection = $sort_expression_nodirection;
        }

        $is_in_sort = false;
        $sort_name = str_replace('`', '', $sort_tbl) . $name_to_use_in_sort;

        if (($sort_name == str_replace('`', '', $new_sort_expression_nodirection))
            || ($sort_name == str_replace('`', '', $sort_expression_nodirection))
        ) {
            $is_in_sort = true;
        }
    }

    return $is_in_sort;

}


/**
 * Get sort url paramaeters - sort order and order image
 *
 * @param boolean $is_in_sort     the column sorted or not
 * @param string  $sort_direction the sort direction
 * @param array   $fields_meta    set of field properties
 * @param string  $sort_order     the sorting order
 * @param integer $column_index   the index of the column
 *
 * @return  array                       2 element array - $sort_order, $order_img
 *
 * @see     PMA_getTableHeaders()
 */
function PMA_getSortingUrlParams(
    $is_in_sort, $sort_direction, $fields_meta, $sort_order, $column_index
) {

    if (! $is_in_sort) {

        // patch #455484 ("Smart" order)
        $GLOBALS['cfg']['Order'] = strtoupper($GLOBALS['cfg']['Order']);

        if ($GLOBALS['cfg']['Order'] === 'SMART') {
            $sort_order .= (preg_match(
                '@time|date@i',
                $fields_meta->type
            )) ? 'DESC' : 'ASC';
        } else {
            $sort_order .= $GLOBALS['cfg']['Order'];
        }
        $order_img   = '';

    } elseif ($sort_direction == 'DESC') {

        $sort_order .= ' ASC';
        $order_img   = ' ' . PMA_getImage(
            's_desc.png', __('Descending'),
            array('class' => "soimg$column_index", 'title' => '')
        );

        $order_img  .= ' ' . PMA_getImage(
            's_asc.png', __('Ascending'),
            array('class' => "soimg$column_index hide", 'title' => '')
        );

    } else {

        $sort_order .= ' DESC';
        $order_img   = ' ' . PMA_getImage(
            's_asc.png', __('Ascending'),
            array('class' => "soimg$column_index", 'title' => '')
        );

        $order_img  .= ' ' . PMA_getImage(
            's_desc.png', __('Descending'),
            array('class' => "soimg$column_index hide", 'title' => '')
        );
    }

    return array($sort_order, $order_img);

}


/**
 * Get sort order link
 *
 * @param string  $order_img   the sort order image
 * @param integer $col_index   the index of the column
 * @param string  $direction   the display direction
 * @param array   $fields_meta set of field properties
 * @param string  $order_url   the url for sort
 *
 * @return  string                      the sort order link
 *
 * @see     PMA_getTableHeaders()
 */
function PMA_getSortOrderLink(
    $order_img, $col_index, $direction, $fields_meta, $order_url
) {

    $order_link_params = array();

    if (isset($order_img) && ($order_img != '')) {
        if (strstr($order_img, 'asc')) {
            $order_link_params['onmouseover'] = "$('.soimg$col_index').toggle()";
            $order_link_params['onmouseout']  = "$('.soimg$col_index').toggle()";
        } elseif (strstr($order_img, 'desc')) {
            $order_link_params['onmouseover'] = "$('.soimg$col_index').toggle()";
            $order_link_params['onmouseout']  = "$('.soimg$col_index').toggle()";
        }
    }

    if ($GLOBALS['cfg']['HeaderFlipType'] == 'auto') {

        $GLOBALS['cfg']['HeaderFlipType']
            = (PMA_USR_BROWSER_AGENT == 'IE') ? 'css' : 'fake';
    }

    if ($direction == 'horizontalflipped'
        && $GLOBALS['cfg']['HeaderFlipType'] == 'css'
    ) {
        $order_link_params['style'] = 'direction: ltr; writing-mode: tb-rl;';
    }

    $order_link_params['title'] = __('Sort');

    $order_link_content = (($direction == 'horizontalflipped')
        && ($GLOBALS['cfg']['HeaderFlipType'] == 'fake'))
        ? PMA_flipstring(
            htmlspecialchars($fields_meta->name),
            "<br />\n"
        )
        : htmlspecialchars($fields_meta->name);

    return PMA_linkOrButton(
        $order_url, $order_link_content . $order_img,
        $order_link_params, false, true
    );

}


/**
 * Prepare columns to draggable effect for sortable columns
 *
 * @param boolean $col_visib       the column is visible (false)
 *        array                    the column is not visible (string array)
 * @param string  $col_visib_j     element of $col_visib array
 * @param boolean $condition_field whether to add CSS class condition
 * @param string  $direction       the display direction
 * @param array   $fields_meta     set of field properties
 * @param string  $order_link      the order link
 * @param string  $comments        the comment for the column
 *
 * @return  string  $draggable_html     html content
 *
 * @see     PMA_getTableHeaders()
 */
function PMA_getDraggableClassForSortableColumns(
    $col_visib, $col_visib_j, $condition_field, $direction, $fields_meta,
    $order_link, $comments
) {

    $draggable_html = '<th';
    $th_class = array();
    $th_class[] = 'draggable';

    if ($col_visib && !$col_visib_j) {
        $th_class[] = 'hide';
    }

    if ($condition_field) {
        $th_class[] = 'condition';
    }

    $th_class[] = 'column_heading';
    if ($GLOBALS['cfg']['BrowsePointerEnable'] == true) {
        $th_class[] = 'pointer';
    }

    if ($GLOBALS['cfg']['BrowseMarkerEnable'] == true) {
        $th_class[] = 'marker';
    }

    $draggable_html .= ' class="' . implode(' ', $th_class);

    if ($direction == 'horizontalflipped') {
        $draggable_html .= ' vbottom';
    }

    $draggable_html .= '" data-column="' . htmlspecialchars($fields_meta->name)
        . '">' . $order_link . $comments . '</th>';

    return $draggable_html;

}


/**
 * Prepare columns to draggable effect for non sortable columns
 *
 * @param boolean $col_visib       the column is visible (false)
 *        array                    the column is not visible (string array)
 * @param string  $col_visib_j     element of $col_visib array
 * @param boolean $condition_field whether to add CSS class condition
 * @param string  $direction       the display direction
 * @param array   $fields_meta     set of field properties
 * @param string  $comments        the comment for the column
 *
 * @return  string  $draggable_html         html content
 *
 * @see     PMA_getTableHeaders()
 */
function PMA_getDraggableClassForNonSortableColumns(
    $col_visib, $col_visib_j, $condition_field,
    $direction, $fields_meta, $comments
) {

    $draggable_html = '<th';
    $th_class = array();
    $th_class[] = 'draggable';

    if ($col_visib && !$col_visib_j) {
        $th_class[] = 'hide';
    }

    if ($condition_field) {
        $th_class[] = 'condition';
    }

    $draggable_html .= ' class="' . implode(' ', $th_class);
    if ($direction == 'horizontalflipped') {
        $draggable_html .= ' vbottom';
    }

    $draggable_html .= '"';
    if (($direction == 'horizontalflipped')
        && ($GLOBALS['cfg']['HeaderFlipType'] == 'css')
    ) {
        $draggable_html .= ' style="direction: ltr; writing-mode: tb-rl;"';
    }

    $draggable_html .= ' data-column="'
        . htmlspecialchars($fields_meta->name) . '">';

    if (($direction == 'horizontalflipped')
        && ($GLOBALS['cfg']['HeaderFlipType'] == 'fake')
    ) {

        $draggable_html .= PMA_flipstring(
            htmlspecialchars($fields_meta->name), '<br />'
        );

    } else {
        $draggable_html .= htmlspecialchars($fields_meta->name);
    }

    $draggable_html .= "\n" . $comments . '</th>';

    return $draggable_html;

}


/**
 * Prepares the display for a value
 *
 * @param string $class           class of table cell
 * @param bool   $condition_field whether to add CSS class condition
 * @param string $value           value to display
 *
 * @return string  the td
 */
function PMA_buildValueDisplay($class, $condition_field, $value)
{
    return '<td class="left ' . $class . ($condition_field ? ' condition' : '')
        . '">' . $value . '</td>';
}


/**
 * Prepares the display for a null value
 *
 * @param string $class           class of table cell
 * @param bool   $condition_field whether to add CSS class condition
 * @param object $meta            the meta-information about this field
 * @param string $align           cell allignment
 *
 * @return string  the td
 */
function PMA_buildNullDisplay($class, $condition_field, $meta, $align = '')
{
    // the null class is needed for grid editing
    return '<td ' . $align . ' class="'
        . PMA_addClass(
            $class, $condition_field, $meta, ''
        )
        . ' null"><i>NULL</i></td>';
}


/**
 * Prepares the display for an empty value
 *
 * @param string $class           class of table cell
 * @param bool   $condition_field whether to add CSS class condition
 * @param object $meta            the meta-information about this field
 * @param string $align           cell allignment
 *
 * @return string  the td
 */
function PMA_buildEmptyDisplay($class, $condition_field, $meta, $align = '')
{
    return '<td ' . $align . ' class="'
        . PMA_addClass(
            $class, $condition_field, $meta, ' nowrap'
        )
        . '"></td>';
}


/**
 * Adds the relavant classes.
 *
 * @param string $class              class of table cell
 * @param bool   $condition_field    whether to add CSS class condition
 * @param object $meta               the meta-information about this field
 * @param string $nowrap             avoid wrapping
 * @param bool   $is_field_truncated is field truncated (display ...)
 * @param string $transform_function transformation function
 * @param string $default_function   default transformation function
 *
 * @return string the list of classes
 */
function PMA_addClass($class, $condition_field, $meta, $nowrap,
    $is_field_truncated = false, $transform_function = '', $default_function = ''
) {

    // Define classes to be added to this data field based on the type of data
    $enum_class = '';
    if (strpos($meta->flags, 'enum') !== false) {
        $enum_class = ' enum';
    }

    $set_class = '';
    if (strpos($meta->flags, 'set') !== false) {
        $set_class = ' set';
    }

    $bit_class = '';
    if (strpos($meta->type, 'bit') !== false) {
        $bit_class = ' bit';
    }

    $mime_type_class = '';
    if (isset($meta->mimetype)) {
        $mime_type_class = ' ' . preg_replace('/\//', '_', $meta->mimetype);
    }

    return $class . ($condition_field ? ' condition' : '') . $nowrap
        . ' ' . ($is_field_truncated ? ' truncated' : '')
        . ($transform_function != $default_function ? ' transformed' : '')
        . $enum_class . $set_class . $bit_class . $mime_type_class;

}


/**
 * Prepare the body of the results table
 *
 * @param integer &$dt_result   the link id associated to the query
 *                              which results have to be displayed
 * @param array   &$is_display  which elements to display
 * @param array   $map          the list of relations
 * @param array   $analyzed_sql the analyzed query
 *
 * @return string $table_body_html   html content
 *
 * @global string  $db                the database name
 * @global string  $table             the table name
 * @global string  $goto              the URL to go back in case of errors
 * @global string  $sql_query         the SQL query
 * @global array   $fields_meta       the list of fields properties
 * @global integer $fields_cnt        the total number of fields returned by
 *                                    the SQL query
 * @global array   $vertical_display  informations used with vertical display
 *                                    mode
 * @global array   $highlight_columns column names to highlight
 * @global array   $row               current row data
 *
 * @access private
 *
 * @see     PMA_getTable()
 */
function PMA_getTableBody(&$dt_result, &$is_display, $map, $analyzed_sql)
{

    global $db, $table, $goto;
    global $sql_query, $fields_meta, $fields_cnt;
    global $vertical_display, $highlight_columns;
    global $row; // mostly because of browser transformations,
                 // to make the row-data accessible in a plugin

    $table_body_html = '';

    // query without conditions to shorten URLs when needed, 200 is just
    // guess, it should depend on remaining URL length
    $url_sql_query = PMA_getUrlSqlQuery($analyzed_sql, $sql_query);

    if (! is_array($map)) {
        $map = array();
    }

    $row_no                         = 0;
    $vertical_display['edit']       = array();
    $vertical_display['copy']       = array();
    $vertical_display['delete']     = array();
    $vertical_display['data']       = array();
    $vertical_display['row_delete'] = array();
    // name of the class added to all grid editable elements
    $grid_edit_class = 'grid_edit';

    // prepare to get the column order, if available
    list($col_order, $col_visib) = PMA_getColumnParams();

    // Correction University of Virginia 19991216 in the while below
    // Previous code assumed that all tables have keys, specifically that
    // the phpMyAdmin GUI should support row delete/edit only for such
    // tables.
    // Although always using keys is arguably the prescribed way of
    // defining a relational table, it is not required. This will in
    // particular be violated by the novice.
    // We want to encourage phpMyAdmin usage by such novices. So the code
    // below has been changed to conditionally work as before when the
    // table being displayed has one or more keys; but to display
    // delete/edit options correctly for tables without keys.

    $odd_row = true;
    $directionCondition
        = ($_SESSION['tmp_user_values']['disp_direction'] == 'horizontal')
        || ($_SESSION['tmp_user_values']['disp_direction'] == 'horizontalflipped');

    while ($row = PMA_DBI_fetch_row($dt_result)) {

        // "vertical display" mode stuff
        $table_body_html .= PMA_getVerticalDisplaySupportSegments(
            $vertical_display, $row_no, $directionCondition
        );

        $alternating_color_class = ($odd_row ? 'odd' : 'even');
        $odd_row = ! $odd_row;

        if ($directionCondition) {
            // pointer code part
            $table_body_html .= '<tr class="' . $alternating_color_class . '">';
        }

        // 1. Prepares the row
        // 1.1 Results from a "SELECT" statement -> builds the
        //     WHERE clause to use in links (a unique key if possible)
        /**
         * @todo $where_clause could be empty, for example a table
         *       with only one field and it's a BLOB; in this case,
         *       avoid to display the delete and edit links
         */
        list($where_clause, $clause_is_unique, $condition_array)
            = PMA_getUniqueCondition($dt_result, $fields_cnt, $fields_meta, $row);
        $where_clause_html = urlencode($where_clause);

        // 1.2 Defines the URLs for the modify/delete link(s)

        if (($is_display['edit_lnk'] != 'nn') || ($is_display['del_lnk'] != 'nn')) {
            // We need to copy the value
            // or else the == 'both' check will always return true

            if ($GLOBALS['cfg']['PropertiesIconic'] === 'both') {
                $iconic_spacer = '<div class="nowrap">';
            } else {
                $iconic_spacer = '';
            }

            // 1.2.1 Modify link(s)
            if ($is_display['edit_lnk'] == 'ur') { // update row case

                list($edit_url, $copy_url, $edit_str, $copy_str, $edit_anchor_class)
                    = PMA_getModifiedLinks(
                        $db, $table, $where_clause,
                        $clause_is_unique, $url_sql_query
                    );

            } // end if (1.2.1)

            // 1.2.2 Delete/Kill link(s)
            if (($is_display['del_lnk'] == 'dr')
                || ($is_display['del_lnk'] == 'kp')
            ) {

                list($del_query, $del_url, $del_str, $js_conf)
                    = PMA_getDeleteAndKillLinks(
                        $db, $table, $where_clause, $clause_is_unique,
                        $url_sql_query, $goto, $is_display['del_lnk']
                    );

            } // end if (1.2.2)

            // 1.3 Displays the links at left if required
            if ((($GLOBALS['cfg']['RowActionLinks'] == 'left')
                || ($GLOBALS['cfg']['RowActionLinks'] == 'both'))
                && $directionCondition
            ) {

                $table_body_html .= PMA_getPlacedLinks(
                    'left', $del_url, $is_display, $row_no, $where_clause,
                    $where_clause_html, $condition_array, $del_query, 'l',
                    $edit_url, $copy_url, $edit_anchor_class, $edit_str,
                    $copy_str, $del_str, $js_conf
                );

            } elseif (($GLOBALS['cfg']['RowActionLinks'] == 'none')
                && $directionCondition
            ) {

                $table_body_html .= PMA_getPlacedLinks(
                    'none', $del_url, $is_display, $row_no, $where_clause,
                    $where_clause_html, $condition_array, $del_query, 'l',
                    $edit_url, $copy_url, $edit_anchor_class, $edit_str,
                    $copy_str, $del_str, $js_conf
                );

            } // end if (1.3)
        } // end if (1)

        // 2. Displays the rows' values

        for ($j = 0; $j < $fields_cnt; ++$j) {

            // assign $i with appropriate column order
            $i = $col_order ? $col_order[$j] : $j;

            $meta    = $fields_meta[$i];
            $not_null_class = $meta->not_null ? 'not_null' : '';
            $relation_class = isset($map[$meta->name]) ? 'relation' : '';
            $hide_class = ($col_visib && !$col_visib[$j]
                // hide per <td> only if the display dir is not vertical
                && ($_SESSION['tmp_user_values']['disp_direction'] != 'vertical'))
                ? 'hide'
                : '';

            // handle datetime-related class, for grid editing
            $field_type_class = PMA_getClassForDateTimeRelatedFields($meta->type);

            $pointer = $i;
            $is_field_truncated = false;
            //If the previous column had blob data, we need to reset the class
            // to $inline_edit_class
            $class = PMA_getResettedClassForInlineEdit(
                $grid_edit_class, $not_null_class, $relation_class,
                $hide_class, $field_type_class, $row_no
            );

            //  See if this column should get highlight because it's used in the
            //  where-query.
            $condition_field = (isset($highlight_columns)
                && (isset($highlight_columns[$meta->name])
                || isset($highlight_columns[PMA_backquote($meta->name)])))
                ? true
                : false;

            // Wrap MIME-transformations. [MIME]
            $default_function = 'PMA_mimeDefaultFunction'; // default_function
            $transform_function = $default_function;
            $transform_options = array();

            if ($GLOBALS['cfgRelation']['mimework']
                && $GLOBALS['cfg']['BrowseMIME']
            ) {

                if (isset($GLOBALS['mime_map'][$meta->name]['mimetype'])
                    && isset($GLOBALS['mime_map'][$meta->name]['transformation'])
                    && !empty($GLOBALS['mime_map'][$meta->name]['transformation'])
                ) {

                    $include_file = './libraries/transformations/' . PMA_securePath(
                        $GLOBALS['mime_map'][$meta->name]['transformation']
                    );

                    if (file_exists($include_file)) {

                        $transformfunction_name = 'PMA_transformation_'
                        . str_replace(
                            '.inc.php', '',
                            $GLOBALS['mime_map'][$meta->name]['transformation']
                        );

                        include_once $include_file;

                        if (function_exists($transformfunction_name)) {

                            $transform_function = $transformfunction_name;

                            $transform_options  = PMA_transformation_getOptions(
                                isset($GLOBALS['mime_map'][$meta->name]
                                    ['transformation_options']
                                )
                                ? $GLOBALS['mime_map'][$meta->name]
                                ['transformation_options']
                                : ''
                            );

                            $meta->mimetype = str_replace(
                                '_', '/',
                                $GLOBALS['mime_map'][$meta->name]['mimetype']
                            );
                        }
                    } // end if file_exists
                } // end if transformation is set
            } // end if mime/transformation works.

            $_url_params = array(
                'db'            => $db,
                'table'         => $table,
                'where_clause'  => $where_clause,
                'transform_key' => $meta->name,
            );

            if (! empty($sql_query)) {
                $_url_params['sql_query'] = $url_sql_query;
            }

            $transform_options['wrapper_link']
                = PMA_generate_common_url($_url_params);

            if ($meta->numeric == 1) {
                // n u m e r i c

                // if two fields have the same name (this is possible
                //       with self-join queries, for example), using $meta->name
                //       will show both fields NULL even if only one is NULL,
                //       so use the $pointer

                $vertical_display['data'][$row_no][$i]
                    = PMA_getDataCellForNumericFeilds(
                        $row[$i], $class, $condition_field, $meta,
                        $analyzed_sql, $transform_function, $map,
                        $default_function, $transform_options, $is_field_truncated
                    );

            } elseif (stristr($meta->type, 'BLOB')) {
                //  b l o b

                // PMA_mysql_fetch_fields returns BLOB in place of
                // TEXT fields type so we have to ensure it's really a BLOB
                $field_flags = PMA_DBI_field_flags($dt_result, $i);

                $vertical_display['data'][$row_no][$i]
                    = PMA_getDataCellForBlobField(
                        $row[$i], $class, $meta, $_url_params, $field_flags,
                        $transform_function, $default_function,
                        $transform_options, $condition_field, $is_field_truncated
                    );

            } elseif ($meta->type == 'geometry') {
                // g e o m e t r y

                // Remove 'grid_edit' from $class as we do not allow to inline-edit
                // geometry data.
                $class = str_replace('grid_edit', '', $class);

                $vertical_display['data'][$row_no][$i]
                    = PMA_getDataCellForGeometryFields(
                        $row[$i], $class, $meta, $map, $_url_params,
                        $condition_field, $transform_function, $default_function,
                        $transform_options, $is_field_truncated, $analyzed_sql
                    );

            } else {
                // n o t   n u m e r i c   a n d   n o t   B L O B

                $vertical_display['data'][$row_no][$i]
                    = PMA_getDataCellForNonNumericAndNonBlobFields(
                        $row[$i], $class, $meta, $map, $_url_params,
                        $condition_field, $transform_function, $default_function,
                        $transform_options, $is_field_truncated, $analyzed_sql,
                        $dt_result, $i
                    );

            }

            // output stored cell
            if ($directionCondition) {
                $table_body_html .= $vertical_display['data'][$row_no][$i];
            }

            if (isset($vertical_display['rowdata'][$i][$row_no])) {
                $vertical_display['rowdata'][$i][$row_no]
                    .= $vertical_display['data'][$row_no][$i];
            } else {
                $vertical_display['rowdata'][$i][$row_no]
                    = $vertical_display['data'][$row_no][$i];
            }
        } // end for (2)

        // 3. Displays the modify/delete links on the right if required
        if ((($GLOBALS['cfg']['RowActionLinks'] == 'right')
            || ($GLOBALS['cfg']['RowActionLinks'] == 'both'))
            && $directionCondition
        ) {

            $table_body_html .= PMA_getPlacedLinks(
                'right', $del_url, $is_display, $row_no, $where_clause,
                $where_clause_html, $condition_array, $del_query, 'r',
                $edit_url, $copy_url, $edit_anchor_class, $edit_str,
                $copy_str, $del_str, $js_conf
            );

        } // end if (3)

        if ($directionCondition) {
            $table_body_html .= '</tr>';
        } // end if

        // 4. Gather links of del_urls and edit_urls in an array for later
        //    output
        if (! isset($vertical_display['edit'][$row_no])) {
            $vertical_display['edit'][$row_no]       = '';
            $vertical_display['copy'][$row_no]       = '';
            $vertical_display['delete'][$row_no]     = '';
            $vertical_display['row_delete'][$row_no] = '';
        }

        $vertical_class = ' row_' . $row_no;
        if ($GLOBALS['cfg']['BrowsePointerEnable'] == true) {
            $vertical_class .= ' vpointer';
        }

        if ($GLOBALS['cfg']['BrowseMarkerEnable'] == true) {
            $vertical_class .= ' vmarker';
        }

        if (!empty($del_url) && ($is_display['del_lnk'] != 'kp')) {

            $vertical_display['row_delete'][$row_no]
                .= PMA_getCheckboxForMultiRowSubmissions(
                    $del_url, $is_display, $row_no, $where_clause_html,
                    $condition_array, $del_query, '[%_PMA_CHECKBOX_DIR_%]',
                    $alternating_color_class . $vertical_class
                );

        } else {
            unset($vertical_display['row_delete'][$row_no]);
        }

        if (isset($edit_url)) {

            $vertical_display['edit'][$row_no] .= PMA_getEditLink(
                $edit_url,
                $alternating_color_class . ' ' . $edit_anchor_class
                . $vertical_class, $edit_str,
                $where_clause,
                $where_clause_html
            );

        } else {
            unset($vertical_display['edit'][$row_no]);
        }

        if (isset($copy_url)) {

            $vertical_display['copy'][$row_no] .= PMA_getCopyLink(
                $copy_url, $copy_str, $where_clause, $where_clause_html,
                $alternating_color_class . $vertical_class
            );

        } else {
            unset($vertical_display['copy'][$row_no]);
        }

        if (isset($del_url)) {

            if (! isset($js_conf)) {
                $js_conf = '';
            }

            $vertical_display['delete'][$row_no] .= PMA_getDeleteLink(
                $del_url, $del_str, $js_conf,
                $alternating_color_class . $vertical_class
            );

        } else {
            unset($vertical_display['delete'][$row_no]);
        }

        $table_body_html .= $directionCondition ? "\n" : '';
        $row_no++;

    } // end while

    return $table_body_html;

} // end of the 'PMA_getTableBody()' function


/**
 * Get url sql query without conditions to shorten URLs
 *
 * @param array  $analyzed_sql analyzed query
 * @param string $sql_query    the SQL query
 *
 * @return  string  $url_sql        analyzed sql query
 *
 * @see     PMA_getTableBody()
 */
function PMA_getUrlSqlQuery($analyzed_sql, $sql_query)
{

    if (isset($analyzed_sql)
        && isset($analyzed_sql[0])
        && isset($analyzed_sql[0]['querytype'])
        && ($analyzed_sql[0]['querytype'] == 'SELECT')
        && (strlen($sql_query) > 200)
    ) {

        $url_sql_query = 'SELECT ';
        if (isset($analyzed_sql[0]['queryflags']['distinct'])) {
            $url_sql_query .= ' DISTINCT ';
        }

        $url_sql_query .= $analyzed_sql[0]['select_expr_clause'];
        if (!empty($analyzed_sql[0]['from_clause'])) {
            $url_sql_query .= ' FROM ' . $analyzed_sql[0]['from_clause'];
        }

        return $url_sql_query;
    }

    return $sql_query;

}


/**
 * Get column order and column visibility
 *
 * @return  array           2 element array - $col_order, $col_visib
 *
 * @see     PMA_getTableBody()
 */
function PMA_getColumnParams()
{
    if (PMA_isSelect()) {
        $pmatable = new PMA_Table($GLOBALS['table'], $GLOBALS['db']);
        $col_order = $pmatable->getUiProp(PMA_Table::PROP_COLUMN_ORDER);
        $col_visib = $pmatable->getUiProp(PMA_Table::PROP_COLUMN_VISIB);
    } else {
        $col_order = false;
        $col_visib = false;
    }

    return array($col_order, $col_visib);
}


/**
 * Prepare vertical display mode necessay HTML stuff
 *
 * @param array   $vertical_display   informations used with vertical
 *                                    display mode
 * @param integer $row_no             the index of current row
 * @param boolean $directionCondition the directional condition
 *
 * @return  string  $vertical_disp_html     html content
 *
 * @see     PMA_getTableBody()
 */
function PMA_getVerticalDisplaySupportSegments(
    $vertical_display, $row_no, $directionCondition
) {

    $support_html = '';

    if ((($row_no != 0) && ($_SESSION['tmp_user_values']['repeat_cells'] != 0))
        && !($row_no % $_SESSION['tmp_user_values']['repeat_cells'])
        && $directionCondition
    ) {

        $support_html .= '<tr>' . "\n";

        if ($vertical_display['emptypre'] > 0) {

            $support_html .= '    <th colspan="'
                . $vertical_display['emptypre'] . '">'
                . "\n".'        &nbsp;</th>' . "\n";

        } else if ($GLOBALS['cfg']['RowActionLinks'] == 'none') {
            $support_html .= '    <th></th>' . "\n";
        }

        foreach ($vertical_display['desc'] as $val) {
            $support_html .= $val;
        }

        if ($vertical_display['emptyafter'] > 0) {
            $support_html .= '    <th colspan="' . $vertical_display['emptyafter']
                . '">'
                . "\n" . '        &nbsp;</th>' . "\n";
        }
        $support_html .= '</tr>' . "\n";
    } // end if

    return $support_html;

}


/**
 * Get modified links
 *
 * @param string  $db               the database name
 * @param string  $table            the table name
 * @param string  $where_clause     the where clause of the sql
 * @param boolean $clause_is_unique the unique condition of clause
 * @param string  $url_sql_query    the analyzed sql query
 *
 * @return  array                       5 element array - $edit_url, $copy_url,
 *                                      $edit_str, $copy_str, $edit_anchor_class
 *
 * @see     PMA_getTableBody()
 */
function PMA_getModifiedLinks(
    $db, $table, $where_clause, $clause_is_unique, $url_sql_query
) {

    $_url_params = array(
            'db'               => $db,
            'table'            => $table,
            'where_clause'     => $where_clause,
            'clause_is_unique' => $clause_is_unique,
            'sql_query'        => $url_sql_query,
            'goto'             => 'sql.php',
        );

    $edit_url = 'tbl_change.php'
        . PMA_generate_common_url(
            $_url_params + array('default_action' => 'update')
        );

    $copy_url = 'tbl_change.php'
        . PMA_generate_common_url(
            $_url_params + array('default_action' => 'insert')
        );

    $edit_str = PMA_getIcon('b_edit.png', __('Edit'));
    $copy_str = PMA_getIcon('b_insrow.png', __('Copy'));

    // Class definitions required for grid editing jQuery scripts
    $edit_anchor_class = "edit_row_anchor";
    if ( $clause_is_unique == 0) {
        $edit_anchor_class .= ' nonunique';
    }

    return array($edit_url, $copy_url, $edit_str, $copy_str, $edit_anchor_class);

}


/**
 * Get delete and kill links
 *
 * @param string  $db               the database name
 * @param string  $table            the table name
 * @param string  $where_clause     the where clause of the sql
 * @param boolean $clause_is_unique the unique condition of clause
 * @param string  $url_sql_query    the analyzed sql query
 * @param string  $goto             the URL to go back in case of errors
 * @param string  $del_lnk          the delete link of current row
 *
 * @return  array                       4 element array - $del_query,
 *                                      $del_url, $del_str, $js_conf
 *
 * @see     PMA_getTableBody()
 */
function PMA_getDeleteAndKillLinks(
    $db, $table, $where_clause, $clause_is_unique, $url_sql_query, $goto, $del_lnk
) {

    if ($del_lnk == 'dr') { // delete row case

        $_url_params = array(
                'db'        => $db,
                'table'     => $table,
                'sql_query' => $url_sql_query,
                'message_to_show' => __('The row has been deleted'),
                'goto'      => (empty($goto) ? 'tbl_sql.php' : $goto),
            );

        $lnk_goto = 'sql.php' . PMA_generate_common_url($_url_params, 'text');

        $del_query = 'DELETE FROM ' . PMA_backquote($db) . '.'
            . PMA_backquote($table)
            . ' WHERE ' . $where_clause .
            ($clause_is_unique ? '' : ' LIMIT 1');

        $_url_params = array(
                'db'        => $db,
                'table'     => $table,
                'sql_query' => $del_query,
                'message_to_show' => __('The row has been deleted'),
                'goto'      => $lnk_goto,
            );
        $del_url  = 'sql.php' . PMA_generate_common_url($_url_params);

        $js_conf  = 'DELETE FROM ' . PMA_jsFormat($db) . '.'
            . PMA_jsFormat($table)
            . ' WHERE ' . PMA_jsFormat($where_clause, false)
            . ($clause_is_unique ? '' : ' LIMIT 1');

        $del_str = PMA_getIcon('b_drop.png', __('Delete'));

    } elseif ($del_lnk == 'kp') { // kill process case

        $_url_params = array(
                'db'        => $db,
                'table'     => $table,
                'sql_query' => $url_sql_query,
                'goto'      => 'main.php',
            );

        $lnk_goto = 'sql.php'
            . PMA_generate_common_url(
                $_url_params, 'text'
            );

        $_url_params = array(
                'db'        => 'mysql',
                'sql_query' => 'KILL ' . $row[0],
                'goto'      => $lnk_goto,
            );

        $del_url  = 'sql.php' . PMA_generate_common_url($_url_params);
        $del_query = 'KILL ' . $row[0];
        $js_conf  = 'KILL ' . $row[0];
        $del_str = PMA_getIcon('b_drop.png', __('Kill'));
    }

    return array($del_query, $del_url, $del_str, $js_conf);

}


/**
 * Prepare placed links
 *
 * @param string  $dir               the direction of links should place
 * @param string  $del_url           the url for delete row
 * @param array   $is_display        which elements to display
 * @param integer $row_no            the index of current row
 * @param string  $where_clause      the where clause of the sql
 * @param string  $where_clause_html the html encoded where clause
 * @param array   $condition_array   array of keys (primary, unique, condition)
 * @param string  $del_query         the query for delete row
 * @param string  $dir_letter        the letter denoted the direction
 * @param string  $edit_url          the url for edit row
 * @param string  $copy_url          the url for copy row
 * @param string  $edit_anchor_class the class for html element for edit
 * @param string  $edit_str          the label for edit row
 * @param string  $copy_str          the label for copy row
 * @param string  $del_str           the label for delete row
 * @param string  $js_conf           text for the JS confirmation
 *
 * @return  string                      html content
 *
 * @see     PMA_getTableBody()
 */
function PMA_getPlacedLinks(
    $dir, $del_url, $is_display, $row_no, $where_clause, $where_clause_html,
    $condition_array, $del_query, $dir_letter, $edit_url, $copy_url,
    $edit_anchor_class, $edit_str, $copy_str, $del_str, $js_conf
) {

    if (! isset($js_conf)) {
        $js_conf = '';
    }

    return PMA_getCheckboxAndLinks(
        $dir, $del_url, $is_display,
        $row_no, $where_clause, $where_clause_html, $condition_array,
        $del_query, 'l', $edit_url, $copy_url, $edit_anchor_class,
        $edit_str, $copy_str, $del_str, $js_conf
    );

}


/**
 * Get resetted class for inline edit columns
 *
 * @param string  $grid_edit_class  the class for all editable columns
 * @param string  $not_null_class   the class for not null columns
 * @param string  $relation_class   the class for relations in a column
 * @param string  $hide_class       the class for visibility of a column
 * @param string  $field_type_class the class related to type of the field
 * @param integer $row_no           the row index
 *
 * @return string $class the resetted class
 *
 * @see     PMA_getTableBody()
 */
function PMA_getResettedClassForInlineEdit(
    $grid_edit_class, $not_null_class, $relation_class,
    $hide_class, $field_type_class, $row_no
) {

    $class = 'data ' . $grid_edit_class . ' ' . $not_null_class . ' '
        . $relation_class . ' ' . $hide_class . ' ' . $field_type_class;

    if (($_SESSION['tmp_user_values']['disp_direction'] == 'vertical')
        && (! isset($GLOBALS['printview']) || ($GLOBALS['printview'] != '1'))
    ) {
        // the row number corresponds to a data row, not HTML table row
        $class .= ' row_' . $row_no;
        if ($GLOBALS['cfg']['BrowsePointerEnable'] == true) {
            $class .= ' vpointer';
        }

        if ($GLOBALS['cfg']['BrowseMarkerEnable'] == true) {
            $class .= ' vmarker';
        }
    }

    return $class;

}


/**
 * Get class for datetime related fields
 *
 * @param string $type the type of the column field
 *
 * @return  string  $field_type_class   the class for the column
 *
 * @see     PMA_getTableBody()
 */
function PMA_getClassForDateTimeRelatedFields($type)
{
    if ((substr($type, 0, 9) == 'timestamp') || ($type == 'datetime')) {
        $field_type_class = 'datetimefield';
    } else if ($type == 'date') {
        $field_type_class = 'datefield';
    } else {
        $field_type_class = '';
    }
    return $field_type_class;
}


/**
 * Prepare data cell for numeric type fields
 *
 * @param string  $column             the relavent column in data row
 * @param string  $class              the html class for column
 * @param boolean $condition_field    the column should highlighted or not
 * @param object  $meta               the meta-information about this field
 * @param array   $map                the list of relations
 * @param boolean $is_field_truncated the condition for blob data replacements
 * @param array   $analyzed_sql       the analyzed query
 * @param string  $transform_function the name of transformation function
 * @param string  $default_function   the default transformation function
 * @param string  $transform_options  the transformation parameters
 *
 * @return  string  $cell               the prepared cell, html content
 *
 * @see     PMA_getTableBody()
 */
function PMA_getDataCellForNumericFeilds(
    $column, $class, $condition_field, $meta, $map, $is_field_truncated,
    $analyzed_sql, $transform_function, $default_function, $transform_options
) {

    if (! isset($column) || is_null($column)) {

        $cell = PMA_buildNullDisplay(
            'right '.$class, $condition_field, $meta, ''
        );

    } elseif ($column != '') {

        $nowrap = ' nowrap';
        $where_comparison = ' = ' . $column;

        $cell = PMA_getRowData(
            'right '.$class, $condition_field,
            $analyzed_sql, $meta, $map, $column,
            $transform_function, $default_function, $nowrap,
            $where_comparison, $transform_options,
            $is_field_truncated
        );
    } else {

        $cell = PMA_buildEmptyDisplay(
            'right '.$class, $condition_field, $meta, ''
        );
    }

    return $cell;

}


/**
 * Get data cell for blob type fields
 *
 * @param string  $column             the relavent column in data row
 * @param string  $class              the html class for column
 * @param object  $meta               the meta-information about this field
 * @param array   $_url_params        the parameters for generate url
 * @param string  $field_flags        field flags for column(blob, primary etc)
 * @param string  $transform_function the name of transformation function
 * @param string  $default_function   the default transformation function
 * @param string  $transform_options  the transformation parameters
 * @param boolean $condition_field    the column should highlighted or not
 * @param boolean $is_field_truncated the condition for blob data replacements
 *
 * @return  string  $cell                   the prepared cell, html content
 *
 * @see     PMA_getTableBody()
 */
function PMA_getDataCellForBlobField(
    $column, $class, $meta, $_url_params, $field_flags, $transform_function,
    $default_function, $transform_options, $condition_field, $is_field_truncated
) {

    if (stristr($field_flags, 'BINARY')) {

        // remove 'grid_edit' from $class as we can't edit binary data.
        $class = str_replace('grid_edit', '', $class);

        if (! isset($column) || is_null($column)) {

            $cell = PMA_buildNullDisplay($class, $condition_field, $meta);

        } else {

            $blobtext = PMA_handleNonPrintableContents(
                'BLOB', (isset($column) ? $column : ''),
                $transform_function, $transform_options,
                $default_function, $meta, $_url_params
            );

            $cell = PMA_buildValueDisplay(
                $class, $condition_field, $blobtext
            );
            unset($blobtext);
        }
    } else {
        // not binary:

        if (! isset($column) || is_null($column)) {

            $cell = PMA_buildNullDisplay($class, $condition_field, $meta);

        } elseif ($column != '') {

            // if a transform function for blob is set, none of these
            // replacements will be made
            if ((PMA_strlen($column) > $GLOBALS['cfg']['LimitChars'])
                && ($_SESSION['tmp_user_values']['display_text'] == 'P')
            ) {
                $column = PMA_substr($column, 0, $GLOBALS['cfg']['LimitChars'])
                    . '...';
                $is_field_truncated = true;
            }

            // displays all space characters, 4 space
            // characters for tabulations and <cr>/<lf>
            $column = ($default_function != $transform_function)
                ? $transform_function($column, $transform_options, $meta)
                : $default_function($column, array(), $meta);

            if ($is_field_truncated) {
                $class .= ' truncated';
            }

            $cell = PMA_buildValueDisplay($class, $condition_field, $column);

        } else {
            $cell = PMA_buildEmptyDisplay($class, $condition_field, $meta);
        }
    }

    return $cell;

}


/**
 * Get data cell for geometry type fields
 *
 * @param string  $column             the relavent column in data row
 * @param string  $class              the html class for column
 * @param object  $meta               the meta-information about this field
 * @param array   $map                the list of relations
 * @param array   $_url_params        the parameters for generate url
 * @param boolean $condition_field    the column should highlighted or not
 * @param string  $transform_function the name of transformation function
 * @param string  $default_function   the default transformation function
 * @param string  $transform_options  the transformation parameters
 * @param boolean $is_field_truncated the condition for blob data replacements
 * @param array   $analyzed_sql       the analyzed query
 *
 * @return  string  $cell               the prepared data cell, html content
 *
 * @see     PMA_getTableBody()
 */
function PMA_getDataCellForGeometryFields(
    $column, $class, $meta, $map, $_url_params, $condition_field,
    $transform_function, $default_function, $transform_options,
    $is_field_truncated, $analyzed_sql
) {

    if (! isset($column) || is_null($column)) {

        $cell = PMA_buildNullDisplay($class, $condition_field, $meta);

    } elseif ($column != '') {

        // Display as [GEOMETRY - (size)]
        if ($_SESSION['tmp_user_values']['geometry_display'] == 'GEOM') {

            $geometry_text = PMA_handleNonPrintableContents(
                'GEOMETRY', (isset($column) ? $column : ''),
                $transform_function, $transform_options,
                $default_function, $meta
            );

            $cell = PMA_buildValueDisplay(
                $class, $condition_field, $geometry_text
            );

        } elseif ($_SESSION['tmp_user_values']['geometry_display'] == 'WKT') {
            // Prepare in Well Known Text(WKT) format.

            $where_comparison = ' = ' . $column;

            // Convert to WKT format
            $wktval = PMA_asWKT($column);

            if ((PMA_strlen($wktval) > $GLOBALS['cfg']['LimitChars'])
                && ($_SESSION['tmp_user_values']['display_text'] == 'P')
            ) {
                $wktval = PMA_substr($wktval, 0, $GLOBALS['cfg']['LimitChars'])
                    . '...';
                $is_field_truncated = true;
            }

            $cell = PMA_getRowData(
                $class, $condition_field, $analyzed_sql, $meta, $map,
                $wktval, $transform_function, $default_function, '',
                $where_comparison, $transform_options,
                $is_field_truncated
            );

        } else {
            // Prepare in  Well Known Binary(WKB) format.

            if ($_SESSION['tmp_user_values']['display_binary']) {

                $where_comparison = ' = ' . $column;

                if ($_SESSION['tmp_user_values']['display_binary_as_hex']
                    && PMA_containsNonPrintableAscii($column)
                ) {
                    $wkbval = PMA_substr(bin2hex($column), 8);
                } else {
                    $wkbval = htmlspecialchars(
                        PMA_replaceBinaryContents($column)
                    );
                }

                if ((PMA_strlen($wkbval) > $GLOBALS['cfg']['LimitChars'])
                    && ($_SESSION['tmp_user_values']['display_text'] == 'P')
                ) {
                    $wkbval = PMA_substr($wkbval, 0, $GLOBALS['cfg']['LimitChars'])
                        . '...';
                    $is_field_truncated = true;
                }

                $cell = PMA_getRowData(
                    $class, $condition_field,
                    $analyzed_sql, $meta, $map, $wkbval,
                    $transform_function, $default_function, '',
                    $where_comparison, $transform_options,
                    $is_field_truncated
                );

            } else {
                $wkbval = PMA_handleNonPrintableContents(
                    'BINARY', $column, $transform_function,
                    $transform_options, $default_function, $meta,
                    $_url_params
                );

                $cell = PMA_buildValueDisplay($class, $condition_field, $wkbval);
            }
        }
    } else {
        $cell = PMA_buildEmptyDisplay($class, $condition_field, $meta);
    }

    return $cell;

}


/**
 * Get data cell for non numeric and non blob type fields
 *
 * @param string  $column             the relavent column in data row
 * @param string  $class              the html class for column
 * @param object  $meta               the meta-information about this field
 * @param array   $map                the list of relations
 * @param array   $_url_params        the parameters for generate url
 * @param boolean $condition_field    the column should highlighted or not
 * @param string  $transform_function the name of transformation function
 * @param string  $default_function   the default transformation function
 * @param string  $transform_options  the transformation parameters
 * @param boolean $is_field_truncated the condition for blob data replacements
 * @param array   $analyzed_sql       the analyzed query
 * @param integer &$dt_result         the link id associated to the query
 *                                      which results have to be displayed
 * @param integer $col_index          the column index
 *
 * @return  string  $cell               the prepared data cell, html content
 *
 * @see     PMA_getTableBody()
 */
function PMA_getDataCellForNonNumericAndNonBlobFields(
    $column, $class, $meta, $map, $_url_params, $condition_field,
    $transform_function, $default_function, $transform_options,
    $is_field_truncated, $analyzed_sql, &$dt_result, $col_index
) {

    if (! isset($column) || is_null($column)) {

        $cell = PMA_buildNullDisplay($class, $condition_field, $meta);

    } elseif ($column != '') {

        // Cut all fields to $GLOBALS['cfg']['LimitChars']
        // (unless it's a link-type transformation)
        if (PMA_strlen($column) > $GLOBALS['cfg']['LimitChars']
            && ($_SESSION['tmp_user_values']['display_text'] == 'P')
            && !strpos($transform_function, 'link') === true
        ) {
            $column = PMA_substr($column, 0, $GLOBALS['cfg']['LimitChars'])
                . '...';
            $is_field_truncated = true;
        }

        // displays special characters from binaries
        $field_flags = PMA_DBI_field_flags($dt_result, $col_index);
        $formatted = false;

        if (isset($meta->_type) && $meta->_type === MYSQLI_TYPE_BIT) {

            $column = PMA_printableBitValue(
                $column, $meta->length
            );

            // some results of PROCEDURE ANALYSE() are reported as
            // being BINARY but they are quite readable,
            // so don't treat them as BINARY
        } elseif (stristr($field_flags, 'BINARY')
            && ($meta->type == 'string')
            && !(isset($GLOBALS['is_analyse']) && $GLOBALS['is_analyse'])
        ) {

            if ($_SESSION['tmp_user_values']['display_binary']) {

                // user asked to see the real contents of BINARY
                // fields
                if ($_SESSION['tmp_user_values']['display_binary_as_hex']
                    && PMA_containsNonPrintableAscii($column)
                ) {
                    $column = bin2hex($column);
                } else {
                    $column = htmlspecialchars(
                        PMA_replaceBinaryContents($column)
                    );
                }

            } else {
                // we show the BINARY message and field's size
                // (or maybe use a transformation)
                $column = PMA_handleNonPrintableContents(
                    'BINARY', $column, $transform_function,
                    $transform_options, $default_function,
                    $meta, $_url_params
                );
                $formatted = true;
            }
        }

        if ($formatted) {

            $cell = PMA_buildValueDisplay(
                $class, $condition_field, $column
            );

        } else {

            // transform functions may enable no-wrapping:
            $function_nowrap = $transform_function . '_nowrap';

            $bool_nowrap = (($default_function != $transform_function)
                && function_exists($function_nowrap))
                ? $function_nowrap($transform_options)
                : false;

            // do not wrap if date field type
            $nowrap = (preg_match('@DATE|TIME@i', $meta->type)
                || $bool_nowrap) ? ' nowrap' : '';

            $where_comparison = ' = \'' . PMA_sqlAddSlashes($column)
                . '\'';

            $cell = PMA_getRowData(
                $class, $condition_field,
                $analyzed_sql, $meta, $map, $column,
                $transform_function, $default_function, $nowrap,
                $where_comparison, $transform_options,
                $is_field_truncated
            );
        }

    } else {
        $cell = PMA_buildEmptyDisplay($class, $condition_field, $meta);
    }

    return $cell;

}


/**
 * Get the resulted table with the vertical direction mode.
 *
 * @return string       html content
 *
 * @global array    $vertical_display the information to display
 *
 * @access  private
 *
 * @see     PMA_getTable()
 */
function PMA_getVerticalTable()
{

    global $vertical_display;

    $vertical_table_html = '';

    // Prepares "multi row delete" link at top if required
    if (($GLOBALS['cfg']['RowActionLinks'] != 'right')
        && is_array($vertical_display['row_delete'])
        && ((count($vertical_display['row_delete']) > 0)
        || !empty($vertical_display['textbtn']))
    ) {

        $vertical_table_html .= '<tr>' . "\n";
        if ($GLOBALS['cfg']['RowActionLinks'] == 'none') {
            // if we are not showing the RowActionLinks, then we need to show
            // the Multi-Row-Action checkboxes
            $vertical_table_html .= '<th></th>' . "\n";
        }

        $vertical_table_html .= $vertical_display['textbtn']
                             . PMA_getCheckBoxesForMultipleRowOperations(
                                 $vertical_display, '_left'
                             )
                             . '</tr>' . "\n";
    } // end if

    // Prepares "edit" link at top if required
    if ((($GLOBALS['cfg']['RowActionLinks'] == 'left')
        || ($GLOBALS['cfg']['RowActionLinks'] == 'both'))
        && is_array($vertical_display['edit'])
        && ((count($vertical_display['edit']) > 0)
        || !empty($vertical_display['textbtn']))
    ) {
        $vertical_table_html .= PMA_getOperationLinksForVerticleTable(
            $vertical_display, 'edit'
        );
    } // end if

    // Prepares "copy" link at top if required
    if ((($GLOBALS['cfg']['RowActionLinks'] == 'left')
        || ($GLOBALS['cfg']['RowActionLinks'] == 'both'))
        && is_array($vertical_display['copy'])
        && ((count($vertical_display['copy']) > 0)
        || !empty($vertical_display['textbtn']))
    ) {
        $vertical_table_html .= PMA_getOperationLinksForVerticleTable(
            $vertical_display, 'copy'
        );
    } // end if

    // Prepares "delete" link at top if required
    if ((($GLOBALS['cfg']['RowActionLinks'] == 'left')
        || ($GLOBALS['cfg']['RowActionLinks'] == 'both'))
        && is_array($vertical_display['delete'])
        && ((count($vertical_display['delete']) > 0)
        || !empty($vertical_display['textbtn']))
    ) {
        $vertical_table_html .= PMA_getOperationLinksForVerticleTable(
            $vertical_display, 'delete'
        );
    } // end if

    list($col_order, $col_visib) = PMA_getColumnParams();

    // Prepares data
    foreach ($vertical_display['desc'] AS $j => $val) {

        // assign appropriate key with current column order
        $key = $col_order ? $col_order[$j] : $j;

        $vertical_table_html .= '<tr'
            . (($col_visib && !$col_visib[$j]) ? ' class="hide"' : '')
            . '>' . "\n"
            . $val;

        $cell_displayed = 0;
        foreach ($vertical_display['rowdata'][$key] as $subval) {

            if (($cell_displayed != 0)
                && ($_SESSION['tmp_user_values']['repeat_cells'] != 0)
                && !($cell_displayed % $_SESSION['tmp_user_values']['repeat_cells'])
            ) {
                $vertical_table_html .= $val;
            }

            $vertical_table_html .= $subval;
            $cell_displayed++;

        } // end while

        $vertical_table_html .= '</tr>' . "\n";
    } // end while

    // Prepares "multi row delete" link at bottom if required
    if ((($GLOBALS['cfg']['RowActionLinks'] == 'right')
        || ($GLOBALS['cfg']['RowActionLinks'] == 'both'))
        && is_array($vertical_display['row_delete'])
        && ((count($vertical_display['row_delete']) > 0)
        || !empty($vertical_display['textbtn']))
    ) {

        $vertical_table_html .= '<tr>' . "\n" . $vertical_display['textbtn']
                             . PMA_getCheckBoxesForMultipleRowOperations(
                                 $vertical_display, '_right'
                             )
                             . '</tr>' . "\n";
    } // end if

    // Prepares "edit" link at bottom if required
    if ((($GLOBALS['cfg']['RowActionLinks'] == 'right')
        || ($GLOBALS['cfg']['RowActionLinks'] == 'both'))
        && is_array($vertical_display['edit'])
        && ((count($vertical_display['edit']) > 0)
        || !empty($vertical_display['textbtn']))
    ) {
        $vertical_table_html .= PMA_getOperationLinksForVerticleTable(
            $vertical_display, 'edit'
        );
    } // end if

    // Prepares "copy" link at bottom if required
    if ((($GLOBALS['cfg']['RowActionLinks'] == 'right')
        || ($GLOBALS['cfg']['RowActionLinks'] == 'both'))
        && is_array($vertical_display['copy'])
        && ((count($vertical_display['copy']) > 0)
        || !empty($vertical_display['textbtn']))
    ) {
        $vertical_table_html .= PMA_getOperationLinksForVerticleTable(
            $vertical_display, 'copy'
        );
    } // end if

    // Prepares "delete" link at bottom if required
    if ((($GLOBALS['cfg']['RowActionLinks'] == 'right')
        || ($GLOBALS['cfg']['RowActionLinks'] == 'both'))
        && is_array($vertical_display['delete'])
        && ((count($vertical_display['delete']) > 0)
        || !empty($vertical_display['textbtn']))
    ) {
        $vertical_table_html .= PMA_getOperationLinksForVerticleTable(
            $vertical_display, 'delete'
        );
    }

    return $vertical_table_html;

} // end of the 'PMA_getVerticalTable' function


/**
 * Prepare edit, copy and delete links for verticle table
 *
 * @param array  $vertical_display the information to display
 * @param string $operation        edit/copy/delete
 *
 * @return  string  $links_html         html content
 *
 * @see     PMA_getVerticalTable()
 */
function PMA_getOperationLinksForVerticleTable($vertical_display, $operation)
{

    $link_html = '<tr>' . "\n";

    if (! is_array($vertical_display['row_delete'])) {

        if (($operation == 'edit') || ($operation == 'copy')) {
            $link_html .= $vertical_display['textbtn'];

        } elseif ($operation == 'delete') {

            if (! is_array($vertical_display['edit'])) {
                $link_html .= $vertical_display['textbtn'];
            }
        }
    }

    foreach ($vertical_display[$operation] as $val) {
        $link_html .= $val;
    } // end while

    $link_html .= '</tr>' . "\n";

    return $link_html;

}


/**
 * Get checkboxes for multiple row data operations
 *
 * @param array  $vertical_display the information to display
 * @param string $dir              _left / _right
 *
 * @return $checkBoxes_html html content
 *
 * @see     PMA_getVerticalTable()
 */
function PMA_getCheckBoxesForMultipleRowOperations($vertical_display, $dir)
{

    $checkBoxes_html = '';
    $cell_displayed = 0;

    foreach ($vertical_display['row_delete'] as $val) {

        if (($cell_displayed != 0)
            && ($_SESSION['tmp_user_values']['repeat_cells'] != 0)
            && !($cell_displayed % $_SESSION['tmp_user_values']['repeat_cells'])
        ) {

            $checkBoxes_html .= '<th'
                . (($is_display['edit_lnk'] != 'nn')
                    && ($is_display['del_lnk'] != 'nn'))
                    ? ' rowspan="4"'
                    : ''
                . '></th>' . "\n";

        }

        $checkBoxes_html .= str_replace('[%_PMA_CHECKBOX_DIR_%]', $dir, $val);
        $cell_displayed++;
    } // end while

    return $checkBoxes_html;

}


/**
 * Checks the posted options for viewing query resutls
 * and sets appropriate values in the session.
 *
 * @todo    make maximum remembered queries configurable
 * @todo    move/split into SQL class!?
 * @todo    currently this is called twice unnecessary
 * @todo    ignore LIMIT and ORDER in query!?
 *
 * @return void
 */
function PMA_setConfigParamsForDisplayTable()
{

    $sql_md5 = md5($GLOBALS['sql_query']);

    $_SESSION['tmp_user_values']['query'][$sql_md5]['sql'] = $GLOBALS['sql_query'];

    $valid_disp_dir = PMA_isValid(
        $_REQUEST['disp_direction'],
        array('horizontal', 'vertical', 'horizontalflipped')
    );

    if ($valid_disp_dir) {
        $_SESSION['tmp_user_values']['query'][$sql_md5]['disp_direction']
            = $_REQUEST['disp_direction'];
        unset($_REQUEST['disp_direction']);
    } elseif (
        empty($_SESSION['tmp_user_values']['query'][$sql_md5]['disp_direction'])
    ) {
        $_SESSION['tmp_user_values']['query'][$sql_md5]['disp_direction']
            = $GLOBALS['cfg']['DefaultDisplay'];
    }

    if (PMA_isValid($_REQUEST['repeat_cells'], 'numeric')) {
        $_SESSION['tmp_user_values']['query'][$sql_md5]['repeat_cells']
            = $_REQUEST['repeat_cells'];
        unset($_REQUEST['repeat_cells']);
    } elseif (
        empty($_SESSION['tmp_user_values']['query'][$sql_md5]['repeat_cells'])
    ) {
        $_SESSION['tmp_user_values']['query'][$sql_md5]['repeat_cells']
            = $GLOBALS['cfg']['RepeatCells'];
    }

    // as this is a form value, the type is always string so we cannot
    // use PMA_isValid($_REQUEST['session_max_rows'], 'integer')
    if ((PMA_isValid($_REQUEST['session_max_rows'], 'numeric')
        && ((int) $_REQUEST['session_max_rows'] == $_REQUEST['session_max_rows']))
        || ($_REQUEST['session_max_rows'] == 'all')
    ) {
        $_SESSION['tmp_user_values']['query'][$sql_md5]['max_rows']
            = $_REQUEST['session_max_rows'];
        unset($_REQUEST['session_max_rows']);
    } elseif (empty($_SESSION['tmp_user_values']['query'][$sql_md5]['max_rows'])) {
        $_SESSION['tmp_user_values']['query'][$sql_md5]['max_rows']
            = $GLOBALS['cfg']['MaxRows'];
    }

    if (PMA_isValid($_REQUEST['pos'], 'numeric')) {
        $_SESSION['tmp_user_values']['query'][$sql_md5]['pos'] = $_REQUEST['pos'];
        unset($_REQUEST['pos']);
    } elseif (empty($_SESSION['tmp_user_values']['query'][$sql_md5]['pos'])) {
        $_SESSION['tmp_user_values']['query'][$sql_md5]['pos'] = 0;
    }

    if (PMA_isValid($_REQUEST['display_text'], array('P', 'F'))) {
        $_SESSION['tmp_user_values']['query'][$sql_md5]['display_text']
            = $_REQUEST['display_text'];
        unset($_REQUEST['display_text']);
    } elseif (
        empty($_SESSION['tmp_user_values']['query'][$sql_md5]['display_text'])
    ) {
        $_SESSION['tmp_user_values']['query'][$sql_md5]['display_text'] = 'P';
    }

    if (PMA_isValid($_REQUEST['relational_display'], array('K', 'D'))) {
        $_SESSION['tmp_user_values']['query'][$sql_md5]['relational_display']
            = $_REQUEST['relational_display'];
        unset($_REQUEST['relational_display']);
    } elseif (
        empty($_SESSION['tmp_user_values']['query'][$sql_md5]['relational_display'])
    ) {
        $_SESSION['tmp_user_values']['query'][$sql_md5]['relational_display'] = 'K';
    }

    if (PMA_isValid($_REQUEST['geometry_display'], array('WKT', 'WKB', 'GEOM'))) {
        $_SESSION['tmp_user_values']['query'][$sql_md5]['geometry_display']
            = $_REQUEST['geometry_display'];
        unset($_REQUEST['geometry_display']);
    } elseif (
        empty($_SESSION['tmp_user_values']['query'][$sql_md5]['geometry_display'])
    ) {
        $_SESSION['tmp_user_values']['query'][$sql_md5]['geometry_display'] = 'GEOM';
    }

    if (isset($_REQUEST['display_binary'])) {
        $_SESSION['tmp_user_values']['query'][$sql_md5]['display_binary'] = true;
        unset($_REQUEST['display_binary']);
    } elseif (isset($_REQUEST['display_options_form'])) {
        // we know that the checkbox was unchecked
        unset($_SESSION['tmp_user_values']['query'][$sql_md5]['display_binary']);
    } elseif (isset($_REQUEST['full_text_button'])) {
        // do nothing to keep the value that is there in the session
    } else {
        // selected by default because some operations like OPTIMIZE TABLE
        // and all queries involving functions return "binary" contents,
        // according to low-level field flags
        $_SESSION['tmp_user_values']['query'][$sql_md5]['display_binary'] = true;
    }

    if (isset($_REQUEST['display_binary_as_hex'])) {
        $_SESSION['tmp_user_values']['query'][$sql_md5]['display_binary_as_hex']
            = true;
        unset($_REQUEST['display_binary_as_hex']);
    } elseif (isset($_REQUEST['display_options_form'])) {
        // we know that the checkbox was unchecked
        unset(
            $_SESSION['tmp_user_values']['query'][$sql_md5]['display_binary_as_hex']
        );
    } elseif (isset($_REQUEST['full_text_button'])) {
        // do nothing to keep the value that is there in the session
    } else {
        // display_binary_as_hex config option
        if (isset($GLOBALS['cfg']['DisplayBinaryAsHex'])
            && ($GLOBALS['cfg']['DisplayBinaryAsHex'] === true)
        ) {
            $_SESSION['tmp_user_values']['query'][$sql_md5]['display_binary_as_hex']
                = true;
        }
    }

    if (isset($_REQUEST['display_blob'])) {
        $_SESSION['tmp_user_values']['query'][$sql_md5]['display_blob'] = true;
        unset($_REQUEST['display_blob']);
    } elseif (isset($_REQUEST['display_options_form'])) {
        // we know that the checkbox was unchecked
        unset($_SESSION['tmp_user_values']['query'][$sql_md5]['display_blob']);
    }

    if (isset($_REQUEST['hide_transformation'])) {
        $_SESSION['tmp_user_values']['query'][$sql_md5]['hide_transformation']
            = true;
        unset($_REQUEST['hide_transformation']);
    } elseif (isset($_REQUEST['display_options_form'])) {
        // we know that the checkbox was unchecked
        unset(
            $_SESSION['tmp_user_values']['query'][$sql_md5]['hide_transformation']
        );
    }

    // move current query to the last position, to be removed last
    // so only least executed query will be removed if maximum remembered queries
    // limit is reached
    $tmp = $_SESSION['tmp_user_values']['query'][$sql_md5];
    unset($_SESSION['tmp_user_values']['query'][$sql_md5]);
    $_SESSION['tmp_user_values']['query'][$sql_md5] = $tmp;

    // do not exceed a maximum number of queries to remember
    if (count($_SESSION['tmp_user_values']['query']) > 10) {
        array_shift($_SESSION['tmp_user_values']['query']);
        //echo 'deleting one element ...';
    }

    // populate query configuration
    $_SESSION['tmp_user_values']['display_text']
        = $_SESSION['tmp_user_values']['query'][$sql_md5]['display_text'];
    $_SESSION['tmp_user_values']['relational_display']
        = $_SESSION['tmp_user_values']['query'][$sql_md5]['relational_display'];
    $_SESSION['tmp_user_values']['geometry_display']
        = $_SESSION['tmp_user_values']['query'][$sql_md5]['geometry_display'];
    $_SESSION['tmp_user_values']['display_binary']
        = isset($_SESSION['tmp_user_values']['query'][$sql_md5]['display_binary'])
        ? true
        : false;
    $_SESSION['tmp_user_values']['display_binary_as_hex']
        = isset(
            $_SESSION['tmp_user_values']['query'][$sql_md5]['display_binary_as_hex']
        )
        ? true
        : false;
    $_SESSION['tmp_user_values']['display_blob']
        = isset($_SESSION['tmp_user_values']['query'][$sql_md5]['display_blob'])
        ? true
        : false;
    $_SESSION['tmp_user_values']['hide_transformation']
        = isset(
            $_SESSION['tmp_user_values']['query'][$sql_md5]['hide_transformation']
        )
        ? true
        : false;
    $_SESSION['tmp_user_values']['pos']
        = $_SESSION['tmp_user_values']['query'][$sql_md5]['pos'];
    $_SESSION['tmp_user_values']['max_rows']
        = $_SESSION['tmp_user_values']['query'][$sql_md5]['max_rows'];
    $_SESSION['tmp_user_values']['repeat_cells']
        = $_SESSION['tmp_user_values']['query'][$sql_md5]['repeat_cells'];
    $_SESSION['tmp_user_values']['disp_direction']
        = $_SESSION['tmp_user_values']['query'][$sql_md5]['disp_direction'];

}


/**
 * Prepare a table of results returned by a SQL query.
 * This function is called by the "sql.php" script.
 *
 * @param integer &$dt_result     the link id associated to the query
 *                                which results have to be displayed
 * @param array   &$the_disp_mode the display mode
 * @param array   $analyzed_sql   the analyzed query
 *
 * @return sting                        Generated HTML content for resulted table
 *
 * @global string   $db                the database name
 * @global string   $table             the table name
 * @global string   $goto              the URL to go back in case of errors
 * @global string   $sql_query         the current SQL query
 * @global integer  $num_rows          the total number of rows returned by the
 *                                      SQL query
 * @global integer  $unlim_num_rows    the total number of rows returned by the
 *                                      SQL query without any programmatically
 *                                      appended "LIMIT" clause
 * @global array    $fields_meta       the list of fields properties
 * @global integer  $fields_cnt        the total number of fields returned by
 *                                      the SQL query
 * @global array    $vertical_display  informations used with vertical display
 *                                      mode
 * @global array    $highlight_columns column names to highlight
 * @global array    $cfgRelation       the relation settings
 * @global array    $showtable         table definitions
 *
 * @access  private
 *
 * @see     PMA_getMessage(), PMA_setDisplayMode(),
 *          PMA_getTableNavigation(), PMA_getTableHeaders(),
 *          PMA_getTableBody(), PMA_getResultsOperations()
 *
 * @return void
 */
function PMA_getTable(&$dt_result, &$the_disp_mode, $analyzed_sql)
{

    global $db, $table, $goto;
    global $sql_query, $num_rows, $unlim_num_rows, $fields_meta, $fields_cnt;
    global $vertical_display, $highlight_columns;
    global $cfgRelation;
    global $showtable;

    $table_html = '';

    // why was this called here? (already called from sql.php)
    //PMA_setConfigParamsForDisplayTable();

    /**
     * @todo move this to a central place
     * @todo for other future table types
     */
    $is_innodb = (isset($showtable['Type']) && $showtable['Type'] == 'InnoDB');

    if ($is_innodb
        && ! isset($analyzed_sql[0]['queryflags']['union'])
        && ! isset($analyzed_sql[0]['table_ref'][1]['table_name'])
        && (empty($analyzed_sql[0]['where_clause'])
        || ($analyzed_sql[0]['where_clause'] == '1 '))
    ) {
        // "j u s t   b r o w s i n g"
        $pre_count = '~';
        $after_count = PMA_showHint(
            PMA_sanitize(
                __('May be approximate. See [a@./Documentation.html#faq3_11@Documentation]FAQ 3.11[/a]')
            )
        );
    } else {
        $pre_count = '';
        $after_count = '';
    }

    // 1. ----- Prepares the work -----

    // 1.1 Gets the informations about which functionalities should be
    //     displayed
    $total      = '';
    $is_display = PMA_setDisplayMode($the_disp_mode, $total);

    // 1.2 Defines offsets for the next and previous pages
    if ($is_display['nav_bar'] == '1') {
        list($pos_next, $pos_prev) = PMA_getOffsets();
    } // end if

    // 1.3 Find the sort expression
    // we need $sort_expression and $sort_expression_nodirection
    // even if there are many table references
    list($sort_expression, $sort_expression_nodirection, $sort_direction)
        = PMA_getSortParams($analyzed_sql[0]['order_by_clause']);


    // 1.4 Prepares display of first and last value of the sorted column

    $sorted_column_message = PMA_getSortedColumnMessage(
        $dt_result, $fields_meta, $num_rows,
        $sort_expression_nodirection, $table
    );


    // 2. ----- Prepare to display the top of the page -----

    // 2.1 Prepares a messages with position informations
    if (($is_display['nav_bar'] == '1') && isset($pos_next)) {

        $message = PMA_setMessageInformation(
            $db, $table, $sorted_column_message,
            $analyzed_sql[0]['limit_clause'], $unlim_num_rows,
            $total, $pos_next, $pre_count, $after_count
        );

        $table_html .= PMA_getMessage($message, $sql_query, 'success');

    } elseif (! isset($GLOBALS['printview']) || ($GLOBALS['printview'] != '1')) {

        $table_html .= PMA_getMessage(
            __('Your SQL query has been executed successfully'),
            $sql_query, 'success'
        );
    }

    // 2.3 Prepare the navigation bars
    if (! strlen($table)) {

        if (isset($analyzed_sql[0]['query_type'])
            && ($analyzed_sql[0]['query_type'] == 'SELECT')
        ) {
            // table does not always contain a real table name,
            // for example in MySQL 5.0.x, the query SHOW STATUS
            // returns STATUS as a table name
            $table = $fields_meta[0]->table;
        } else {
            $table = '';
        }

    }

    if (($is_display['nav_bar'] == '1') && empty($analyzed_sql[0]['limit_clause'])) {

        $table_html .= PMA_getTableNavigation(
            $pos_next, $pos_prev, $sql_query, 'top_direction_dropdown'
        )
        . "\n";

    } elseif (! isset($GLOBALS['printview']) || ($GLOBALS['printview'] != '1')) {
        $table_html .= "\n" . '<br /><br />' . "\n";
    }

    // 2b ----- Get field references from Database -----
    // (see the 'relation' configuration variable)

    // initialize map
    $map = array();

    // find tables
    $target=array();
    if (isset($analyzed_sql[0]['table_ref'])
        && is_array($analyzed_sql[0]['table_ref'])
    ) {

        foreach ($analyzed_sql[0]['table_ref']
            as $table_ref_position => $table_ref) {
            $target[] = $analyzed_sql[0]['table_ref']
                [$table_ref_position]['table_true_name'];
        }

    }

    $tabs    = '(\'' . join('\',\'', $target) . '\')';

    if (! strlen($table)) {
        $exist_rel = false;
    } else {

        // To be able to later display a link to the related table,
        // we verify both types of relations: either those that are
        // native foreign keys or those defined in the phpMyAdmin
        // configuration storage. If no PMA storage, we won't be able
        // to use the "column to display" notion (for example show
        // the name related to a numeric id).
        $exist_rel = PMA_getForeigners($db, $table, '', 'both');

        if ($exist_rel) {

            foreach ($exist_rel as $master_field => $rel) {

                $display_field = PMA_getDisplayField(
                    $rel['foreign_db'], $rel['foreign_table']
                );

                $map[$master_field] = array(
                        $rel['foreign_table'],
                        $rel['foreign_field'],
                        $display_field,
                        $rel['foreign_db']
                    );
            } // end while
        } // end if
    } // end if
    // end 2b

    // 3. ----- Prepare the results table -----
    $table_html .= PMA_getTableHeaders(
        $is_display, $fields_meta, $fields_cnt, $analyzed_sql,
        $sort_expression, $sort_expression_nodirection, $sort_direction
    )
    . '<tbody>' . "\n";

    $url_query = '';
    $table_html .= PMA_getTableBody(
        $dt_result, $is_display, $map, $analyzed_sql
    );

    // vertical output case
    if ($_SESSION['tmp_user_values']['disp_direction'] == 'vertical') {
        $table_html .= PMA_getVerticalTable();
    } // end if

    unset($vertical_display);
    $table_html .= '</tbody>' . "\n"
        . '</table>';

    // 4. ----- Prepares the link for multi-fields edit and delete

    if ($is_display['del_lnk'] == 'dr' && $is_display['del_lnk'] != 'kp') {

        $table_html .= PMA_getMultiRowOperationLinks(
            $dt_result, $fields_cnt, $fields_meta, $num_rows, $analyzed_sql,
            $db, $table, $sql_query, $goto, $is_display['del_lnk']
        );

    }

    // 5. ----- Get the navigation bar at the bottom if required -----

    if ($is_display['nav_bar'] == '1' && empty($analyzed_sql[0]['limit_clause'])) {

        $table_html .= '<br />' . "\n";
        $table_html .= PMA_getTableNavigation(
            $pos_next, $pos_prev, $sql_query, 'bottom_direction_dropdown'
        );

    } elseif (! isset($GLOBALS['printview']) || $GLOBALS['printview'] != '1') {
        $table_html .= "\n" . '<br /><br />' . "\n";
    }

    // 6. ----- Prepare "Query results operations"
    if (! isset($GLOBALS['printview']) || $GLOBALS['printview'] != '1') {
        $table_html .= PMA_getResultsOperations($the_disp_mode, $analyzed_sql);
    }

    return $table_html;

} // end of the 'PMA_getTable()' function


/**
 * Get offsets for next page and previous page
 *
 * @return  array           array with two elements - $pos_next, $pos_prev
 *
 * @see     PMA_getTable()
 */
function PMA_getOffsets()
{

    if ($_SESSION['tmp_user_values']['max_rows'] == 'all') {
        $pos_next     = 0;
        $pos_prev     = 0;
    } else {

        $pos_next     = $_SESSION['tmp_user_values']['pos']
                        + $_SESSION['tmp_user_values']['max_rows'];

        $pos_prev     = $_SESSION['tmp_user_values']['pos']
                        - $_SESSION['tmp_user_values']['max_rows'];

        if ($pos_prev < 0) {
            $pos_prev = 0;
        }
    }

    return array($pos_next, $pos_prev);

}


/**
 * Get sort parameters
 *
 * @param string $order_by_clause the order by clause of the sql query
 *
 * @return  array                 3 element array: $sort_expression,
 *                                $sort_expression_nodirection, $sort_direction
 *
 * @see     PMA_getTable()
 */
function PMA_getSortParams($order_by_clause)
{

    if (! empty($order_by_clause)) {

        $sort_expression = trim(
            str_replace('  ', ' ', $order_by_clause)
        );
        /**
         * Get rid of ASC|DESC
         */
        preg_match('@(.*)([[:space:]]*(ASC|DESC))@si', $sort_expression, $matches);

        $sort_expression_nodirection = isset($matches[1])
            ? trim($matches[1])
            : $sort_expression;

        $sort_direction = isset($matches[2]) ? trim($matches[2]) : '';
        unset($matches);

    } else {
        $sort_expression = $sort_expression_nodirection = $sort_direction = '';
    }

    return array($sort_expression, $sort_expression_nodirection, $sort_direction);

}


/**
 * Prepare sorted column message
 *
 * @param integer &$dt_result                  the link id associated to the query
 *                                              which results have to be displayed
 * @param array   $fields_meta                 the list of fields properties
 * @param integer $num_rows                    the total number of rows returned
 *                                              by the SQL query
 * @param string  $sort_expression_nodirection sort expression without direction
 * @param string  $table                       the table name
 *
 * @return  string                              html content
 *          null                                if not found sorted column
 *
 * @see     PMA_getTable()
 */
function PMA_getSortedColumnMessage(&$dt_result, $fields_meta,
    $num_rows, $sort_expression_nodirection, $table
) {

    if (! empty($sort_expression_nodirection)) {

        if (strpos($sort_expression_nodirection, '.') === false) {
            $sort_table = $table;
            $sort_column = $sort_expression_nodirection;
        } else {
            list($sort_table, $sort_column)
                = explode('.', $sort_expression_nodirection);
        }

        $sort_table = PMA_unQuote($sort_table);
        $sort_column = PMA_unQuote($sort_column);

        // find the sorted column index in row result
        // (this might be a multi-table query)
        $sorted_column_index = false;

        foreach ($fields_meta as $key => $meta) {
            if (($meta->table == $sort_table) && ($meta->name == $sort_column)) {
                $sorted_column_index = $key;
                break;
            }
        }

        if ($sorted_column_index !== false) {

            // fetch first row of the result set
            $row = PMA_DBI_fetch_row($dt_result);

            // initializing default arguments
            $default_function = 'PMA_mimeDefaultFunction';
            $transform_function = $default_function;
            $transform_options = array();

            // check for non printable sorted row data
            $meta = $fields_meta[$sorted_column_index];

            if (stristr($meta->type, 'BLOB') || ($meta->type == 'geometry')) {

                $column_for_first_row = PMA_handleNonPrintableContents(
                    $meta->type, $row[$sorted_column_index], $transform_function,
                    $transform_options, $default_function, $meta, null
                );

            } else {
                $column_for_first_row = $row[$sorted_column_index];
            }

            $column_for_first_row = strtoupper(
                substr($column_for_first_row, 0, $GLOBALS['cfg']['LimitChars'])
            );

            // fetch last row of the result set
            PMA_DBI_data_seek($dt_result, $num_rows - 1);
            $row = PMA_DBI_fetch_row($dt_result);

            // check for non printable sorted row data
            $meta = $fields_meta[$sorted_column_index];
            if (stristr($meta->type, 'BLOB') || ($meta->type == 'geometry')) {

                $column_for_last_row = PMA_handleNonPrintableContents(
                    $meta->type, $row[$sorted_column_index], $transform_function,
                    $transform_options, $default_function, $meta, null
                );

            } else {
                $column_for_last_row = $row[$sorted_column_index];
            }

            $column_for_last_row = strtoupper(
                substr($column_for_last_row, 0, $GLOBALS['cfg']['LimitChars'])
            );

            // reset to first row for the loop in PMA_getTableBody()
            PMA_DBI_data_seek($dt_result, 0);

            // we could also use here $sort_expression_nodirection
            return ' [' . htmlspecialchars($sort_column)
                . ': <strong>' . htmlspecialchars($column_for_first_row) . ' - '
                . htmlspecialchars($column_for_last_row) . '</strong>]';
        }
    }

    return null;

}


/**
 * Set the content need to be show in message
 *
 * @param string  $db                    the database name
 * @param string  $table                 the table name
 * @param string  $sorted_column_message the message for sorted column
 * @param string  $limit_clause          the limit clause of analyzed query
 * @param integer $unlim_num_rows        the total number of rows returned by
 *                                       the SQL query without any appended
 *                                       "LIMIT" clause programmatically
 * @param integer $total                 the total number of rows returned by
 *                                       the SQL query without any
 *                                       programmatically appended LIMIT clause
 * @param integer $pos_next              the offset for next page
 * @param string  $pre_count             the string renders before row count
 * @param string  $after_count           the string renders after row count
 *
 * @return PMA_Message $message an object of PMA_Message
 *
 * @see     PMA_getTable()
 */
function PMA_setMessageInformation(
    $db, $table, $sorted_column_message, $limit_clause, $unlim_num_rows,
    $total, $pos_next, $pre_count, $after_count
) {

    if (isset($unlim_num_rows) && ($unlim_num_rows != $total)) {
        $selectstring = ', ' . $unlim_num_rows . ' ' . __('in query');
    } else {
        $selectstring = '';
    }

    if (! empty($limit_clause)) {

        $limit_data = PMA_analyzeLimitClause($limit_clause);
        $first_shown_rec = $limit_data['start'];

        if ($limit_data['length'] < $total) {
            $last_shown_rec = $limit_data['start'] + $limit_data['length'] - 1;
        } else {
            $last_shown_rec = $limit_data['start'] + $total - 1;
        }

    } elseif (($_SESSION['tmp_user_values']['max_rows'] == 'all')
        || ($pos_next > $total)
    ) {

        $first_shown_rec = $_SESSION['tmp_user_values']['pos'];
        $last_shown_rec  = $total - 1;

    } else {

        $first_shown_rec = $_SESSION['tmp_user_values']['pos'];
        $last_shown_rec  = $pos_next - 1;

    }

    if (PMA_Table::isView($db, $table)
        && ($total == $GLOBALS['cfg']['MaxExactCountViews'])
    ) {

        $message = PMA_Message::notice(
            __('This view has at least this number of rows. Please refer to %sdocumentation%s.')
        );

        $message->addParam('[a@./Documentation.html#cfg_MaxExactCount@_blank]');
        $message->addParam('[/a]');
        $message_view_warning = PMA_showHint($message);

    } else {
        $message_view_warning = false;
    }

    $message = PMA_Message::success(__('Showing rows'));
    $message->addMessage($first_shown_rec);

    if ($message_view_warning) {

        $message->addMessage('...', ' - ');
        $message->addMessage($message_view_warning);
        $message->addMessage('(');

    } else {

        $message->addMessage($last_shown_rec, ' - ');
        $message->addMessage(' (');
        $message->addMessage($pre_count  . PMA_formatNumber($total, 0));
        $message->addString(__('total'));

        if (!empty($after_count)) {
            $message->addMessage($after_count);
        }

        $message->addMessage($selectstring, '');
        $message->addMessage(', ', '');

    }

    $messagge_qt = PMA_Message::notice(__('Query took %01.4f sec') . ')');
    $messagge_qt->addParam($GLOBALS['querytime']);

    $message->addMessage($messagge_qt, '');
    if (! is_null($sorted_column_message)) {
        $message->addMessage($sorted_column_message, '');
    }

    return $message;

}


/**
 * Prepare multi field edit/delete links
 *
 * @param integer &$dt_result   the link id associated to the query
 *                              which results have to be displayed
 * @param integer $fields_cnt   the total number of fields returned by
 *                              the SQL query
 * @param array   $fields_meta  the list of fields properties
 * @param integer $num_rows     the total number of rows returned
 *                              by the SQL query
 * @param array   $analyzed_sql the analyzed query
 * @param string  $db           the database name
 * @param string  $table        the table name
 * @param string  $sql_query    the current SQL query
 * @param string  $goto         the URL to go back in case of errors
 * @param string  $del_link     the display element - 'del_link'
 *
 * @return string $links_html html content
 *
 * @see     PMA_getTable()
 */
function PMA_getMultiRowOperationLinks(
    &$dt_result, $fields_cnt, $fields_meta, $num_rows, $analyzed_sql,
    $db, $table, $sql_query, $goto, $del_link
) {

    $links_html = '';
    $delete_text = ($del_link == 'dr') ? __('Delete') : __('Kill');

    $_url_params = array(
        'db'        => $db,
        'table'     => $table,
        'sql_query' => $sql_query,
        'goto'      => $goto,
    );
    $uncheckall_url = 'sql.php' . PMA_generate_common_url($_url_params);

    $_url_params['checkall'] = '1';
    $checkall_url = 'sql.php' . PMA_generate_common_url($_url_params);

    if ($_SESSION['tmp_user_values']['disp_direction'] == 'vertical') {

        $checkall_params['onclick']
            = 'if (setCheckboxes(\'resultsForm\', true)) return false;';
        $uncheckall_params['onclick']
            = 'if (setCheckboxes(\'resultsForm\', false)) return false;';

    } else {

        $checkall_params['onclick']
            = 'if (markAllRows(\'resultsForm\')) return false;';
        $uncheckall_params['onclick']
            = 'if (unMarkAllRows(\'resultsForm\')) return false;';

    }

    $checkall_link = PMA_linkOrButton(
        $checkall_url, __('Check All'), $checkall_params, false
    );

    $uncheckall_link = PMA_linkOrButton(
        $uncheckall_url, __('Uncheck All'), $uncheckall_params, false
    );

    if ($_SESSION['tmp_user_values']['disp_direction'] != 'vertical') {

        $links_html .= '<img class="selectallarrow" width="38" height="22"'
            . ' src="' . $GLOBALS['pmaThemeImage'] . 'arrow_'
            . $GLOBALS['text_dir'] . '.png' . '"'
            . ' alt="' . __('With selected:') . '" />';
    }

    $links_html .= $checkall_link . "\n"
        . ' / ' . "\n"
        . $uncheckall_link . "\n"
        . '<i>' . __('With selected:') . '</i>' . "\n";

    $links_html .= PMA_getButtonOrImage(
        'submit_mult', 'mult_submit', 'submit_mult_change',
        __('Change'), 'b_edit.png', 'edit'
    );

    $links_html .= PMA_getButtonOrImage(
        'submit_mult', 'mult_submit', 'submit_mult_delete',
        $delete_text, 'b_drop.png', 'delete'
    );

    if (isset($analyzed_sql[0]) && $analyzed_sql[0]['querytype'] == 'SELECT') {
        $links_html .= PMA_getButtonOrImage(
            'submit_mult', 'mult_submit', 'submit_mult_export',
            __('Export'), 'b_tblexport.png', 'export'
        );
    }

    $links_html .= "\n";

    $links_html .= '<input type="hidden" name="sql_query"'
        .' value="' . htmlspecialchars($sql_query) . '" />' . "\n";

    if (! empty($GLOBALS['url_query'])) {
        $links_html .= '<input type="hidden" name="url_query"'
            .' value="' . $GLOBALS['url_query'] . '" />' . "\n";
    }

    // fetch last row of the result set
    PMA_DBI_data_seek($dt_result, $num_rows - 1);
    $row = PMA_DBI_fetch_row($dt_result);

    // $clause_is_unique is needed by PMA_getTable() to generate the proper param
    // in the multi-edit and multi-delete form
    list($where_clause, $clause_is_unique, $condition_array)
        = PMA_getUniqueCondition($dt_result, $fields_cnt, $fields_meta, $row);

    // reset to first row for the loop in PMA_getTableBody()
    PMA_DBI_data_seek($dt_result, 0);

    $links_html .= '<input type="hidden" name="clause_is_unique"'
        .' value="' . $clause_is_unique . '" />' . "\n";

    $links_html .= '</form>' . "\n";

    return $links_html;

}


/**
 * replace some html-unfriendly stuff
 *
 * @param string $buffer String to process
 *
 * @return Escaped and cleaned up text suitable for html.
 */
function PMA_mimeDefaultFunction($buffer)
{
    $buffer = htmlspecialchars($buffer);
    $buffer = str_replace(
        "\011",
        ' &nbsp;&nbsp;&nbsp;',
        str_replace('  ', ' &nbsp;', $buffer)
    );
    $buffer = preg_replace("@((\015\012)|(\015)|(\012))@", '<br />', $buffer);

    return $buffer;
}


/**
 * Get operations that are available on results.
 *
 * @param array $the_disp_mode the display mode
 * @param array $analyzed_sql  the analyzed query
 *
 * @return string                       html content
 *
 * @global string   $db             the database name
 * @global string   $table          the table name
 * @global string   $sql_query      the current SQL query
 * @global integer  $unlim_num_rows the total number of rows returned by the
 *                                   SQL query without any programmatically
 *                                   appended "LIMIT" clause
 *
 * @access  private
 *
 * @see     PMA_getMessage(), PMA_setDisplayMode(),
 *          PMA_getTableNavigation(), PMA_getTableHeaders(),
 *          PMA_getTableBody(), PMA_getResultsOperations()
 *
 * @return void
 */
function PMA_getResultsOperations($the_disp_mode, $analyzed_sql)
{

    global $db, $table, $sql_query, $unlim_num_rows, $fields_meta;

    $results_operations_html = '';
    $header_shown = false;
    $header = '<fieldset><legend>' . __('Query results operations') . '</legend>';

    if (($the_disp_mode[6] == '1') || ($the_disp_mode[9] == '1')) {
        // Displays "printable view" link if required
        if ($the_disp_mode[9] == '1') {

            if (!$header_shown) {
                $results_operations_html .= $header;
                $header_shown = true;
            }

            $_url_params = array(
                'db'        => $db,
                'table'     => $table,
                'printview' => '1',
                'sql_query' => $sql_query,
            );
            $url_query = PMA_generate_common_url($_url_params);

            $results_operations_html .= PMA_linkOrButton(
                'sql.php' . $url_query,
                PMA_getIcon('b_print.png', __('Print view'), true),
                '', true, true, 'print_view'
            )
            . "\n";

            if ($_SESSION['tmp_user_values']['display_text']) {

                $_url_params['display_text'] = 'F';

                $results_operations_html .= PMA_linkOrButton(
                    'sql.php' . PMA_generate_common_url($_url_params),
                    PMA_getIcon(
                        'b_print.png', __('Print view (with full texts)'), true
                    ),
                    '', true, true, 'print_view'
                )
                . "\n";
                unset($_url_params['display_text']);
            }
        } // end displays "printable view"
    }

    // Export link
    // (the url_query has extra parameters that won't be used to export)
    // (the single_table parameter is used in display_export.lib.php
    //  to hide the SQL and the structure export dialogs)
    // If the parser found a PROCEDURE clause
    // (most probably PROCEDURE ANALYSE()) it makes no sense to
    // display the Export link).
    if (isset($analyzed_sql[0])
        && ($analyzed_sql[0]['querytype'] == 'SELECT')
        && ! isset($printview)
        && ! isset($analyzed_sql[0]['queryflags']['procedure'])
    ) {

        if (isset($analyzed_sql[0]['table_ref'][0]['table_true_name'])
            && ! isset($analyzed_sql[0]['table_ref'][1]['table_true_name'])
        ) {
            $_url_params['single_table'] = 'true';
        }

        if (!$header_shown) {
            $results_operations_html .= $header;
            $header_shown = true;
        }

        $_url_params['unlim_num_rows'] = $unlim_num_rows;

        /**
         * At this point we don't know the table name; this can happen
         * for example with a query like
         * SELECT bike_code FROM (SELECT bike_code FROM bikes) tmp
         * As a workaround we set in the table parameter the name of the
         * first table of this database, so that tbl_export.php and
         * the script it calls do not fail
         */
        if (empty($_url_params['table']) && !empty($_url_params['db'])) {
            $_url_params['table'] = PMA_DBI_fetch_value("SHOW TABLES");
            /* No result (probably no database selected) */
            if ($_url_params['table'] === false) {
                unset($_url_params['table']);
            }
        }

        $results_operations_html .= PMA_linkOrButton(
            'tbl_export.php' . PMA_generate_common_url($_url_params),
            PMA_getIcon('b_tblexport.png', __('Export'), true),
            '', true, true, ''
        )
        . "\n";

        // prepare chart
        $results_operations_html .= PMA_linkOrButton(
            'tbl_chart.php' . PMA_generate_common_url($_url_params),
            PMA_getIcon('b_chart.png', __('Display chart'), true),
            '', true, true, ''
        )
        . "\n";

        // prepare GIS chart
        $geometry_found = false;
        // If atleast one geometry field is found
        foreach ($fields_meta as $meta) {
            if ($meta->type == 'geometry') {
                $geometry_found = true;
                break;
            }
        }

        if ($geometry_found) {
            $results_operations_html .= PMA_linkOrButton(
                'tbl_gis_visualization.php' . PMA_generate_common_url($_url_params),
                PMA_getIcon('b_globe.gif', __('Visualize GIS data'), true),
                '', true, true, ''
            )
            . "\n";
        }
    }

    // CREATE VIEW
    /**
     *
     * @todo detect privileges to create a view
     *       (but see 2006-01-19 note in display_create_table.lib.php,
     *        I think we cannot detect db-specific privileges reliably)
     * Note: we don't display a Create view link if we found a PROCEDURE clause
     */
    if (!$header_shown) {
        $results_operations_html .= $header;
        $header_shown = true;
    }

    if (!PMA_DRIZZLE && !isset($analyzed_sql[0]['queryflags']['procedure'])) {

        $ajax_class = $GLOBALS['cfg']['AjaxEnable'] ? ' ajax' : '';

        $results_operations_html .= '<span class="create_view' . $ajax_class . '">'
            . PMA_linkOrButton(
                'view_create.php' . $url_query,
                PMA_getIcon('b_views.png', __('Create view'), true),
                '', true, true, ''
            )
            . '</span>' . "\n";
    }

    if ($header_shown) {
        $results_operations_html .= '</fieldset><br />';
    }

    return $results_operations_html;

}


/**
 * Verifies what to do with non-printable contents (binary or BLOB)
 * in Browse mode.
 *
 * @param string $category           BLOB|BINARY|GEOMETRY
 * @param string $content            the binary content
 * @param string $transform_function transformation function
 * @param string $transform_options  transformation parameters
 * @param string $default_function   default transformation function
 * @param object $meta               the meta-information about this field
 * @param array  $url_params         parameters that should go to the download link
 *
 * @return mixed  string or float
 */
function PMA_handleNonPrintableContents($category, $content, $transform_function,
    $transform_options, $default_function, $meta, $url_params = array()
) {

    $result = '[' . $category;

    if (is_null($content)) {

        $result .= ' - NULL';
        $size = 0;

    } elseif (isset($content)) {

        $size = strlen($content);
        $display_size = PMA_formatByteDown($size, 3, 1);
        $result .= ' - '. $display_size[0] . ' ' . $display_size[1];

    }

    $result .= ']';

    if (strpos($transform_function, 'octetstream')) {
        $result = $content;
    }

    if ($size > 0) {

        if ($default_function != $transform_function) {
            $result = $transform_function($result, $transform_options, $meta);
        } else {

            $result = $default_function($result, array(), $meta);
            if (stristr($meta->type, 'BLOB')
                && $_SESSION['tmp_user_values']['display_blob']
            ) {
                // in this case, restart from the original $content
                $result = htmlspecialchars(PMA_replaceBinaryContents($content));
            }

            /* Create link to download */
            if (count($url_params) > 0) {
                $result = '<a href="tbl_get_field.php'
                    . PMA_generate_common_url($url_params) . '">' . $result . '</a>';
            }
        }
    }

    return($result);

}


/**
 * Prepares the displayable content of a data cell in Browse mode,
 * taking into account foreign key description field and transformations
 *
 * @param string $class              css classes for the td element
 * @param bool   $condition_field    whether the column is a part of the where clause
 * @param string $analyzed_sql       the analyzed query
 * @param object $meta               the meta-information about this field
 * @param array  $map                the list of relations
 * @param string $data               data
 * @param string $transform_function transformation function
 * @param string $default_function   default function
 * @param string $nowrap             'nowrap' if the content should not be wrapped
 * @param string $where_comparison   data for the where cluase
 * @param array  $transform_options  array of options for transformation
 * @param bool   $is_field_truncated whether the field is truncated
 *
 * @return string  formatted data
 */
function PMA_getRowData($class, $condition_field, $analyzed_sql, $meta, $map,
    $data, $transform_function, $default_function, $nowrap, $where_comparison,
    $transform_options, $is_field_truncated
) {

    global $db;

    $result = '<td class="'
        . PMA_addClass(
            $class, $condition_field, $meta, $nowrap,
            $is_field_truncated, $transform_function, $default_function
        )
        . '">';

    if (isset($analyzed_sql[0]['select_expr'])
        && is_array($analyzed_sql[0]['select_expr'])
    ) {

        foreach ($analyzed_sql[0]['select_expr']
            as $select_expr_position => $select_expr
        ) {

            $alias = $analyzed_sql[0]['select_expr'][$select_expr_position]['alias'];

            if (isset($alias) && strlen($alias)) {
                $true_column = $analyzed_sql[0]['select_expr']
                    [$select_expr_position]['column'];

                if ($alias == $meta->name) {
                    // this change in the parameter does not matter
                    // outside of the function
                    $meta->name = $true_column;
                } // end if

            } // end if
        } // end foreach
    } // end if

    if (isset($map[$meta->name])) {

        // Field to display from the foreign table?
        if (isset($map[$meta->name][2]) && strlen($map[$meta->name][2])) {

            $dispsql     = 'SELECT ' . PMA_backquote($map[$meta->name][2])
                . ' FROM ' . PMA_backquote($map[$meta->name][3])
                . '.' . PMA_backquote($map[$meta->name][0])
                . ' WHERE ' . PMA_backquote($map[$meta->name][1])
                . $where_comparison;

            $dispresult  = PMA_DBI_try_query($dispsql, null, PMA_DBI_QUERY_STORE);

            if ($dispresult && PMA_DBI_num_rows($dispresult) > 0) {
                list($dispval) = PMA_DBI_fetch_row($dispresult, 0);
            } else {
                $dispval = __('Link not found');
            }

            @PMA_DBI_free_result($dispresult);

        } else {
            $dispval     = '';
        } // end if... else...

        if (isset($GLOBALS['printview']) && $GLOBALS['printview'] == '1') {

            $result .= ($transform_function != $default_function
                    ? $transform_function($data, $transform_options, $meta)
                    : $transform_function($data, array(), $meta))
                . ' <code>[-&gt;' . $dispval . ']</code>';

        } else {

            if ($_SESSION['tmp_user_values']['relational_display'] == 'K') {

                // user chose "relational key" in the display options, so
                // the title contains the display field
                $title = (! empty($dispval))? ' title="' . htmlspecialchars($dispval)
                    . '"' : '';

            } else {
                $title = ' title="' . htmlspecialchars($data) . '"';
            }

            $_url_params = array(
                'db'    => $map[$meta->name][3],
                'table' => $map[$meta->name][0],
                'pos'   => '0',
                'sql_query' => 'SELECT * FROM '
                    . PMA_backquote($map[$meta->name][3]) . '.'
                    . PMA_backquote($map[$meta->name][0])
                    . ' WHERE ' . PMA_backquote($map[$meta->name][1])
                    . $where_comparison,
            );

            $result .= '<a href="sql.php' . PMA_generate_common_url($_url_params)
                 . '"' . $title . '>';

            if ($transform_function != $default_function) {
                // always apply a transformation on the real data,
                // not on the display field
                $result .= $transform_function($data, $transform_options, $meta);
            } else {

                if ($_SESSION['tmp_user_values']['relational_display'] == 'D') {
                    // user chose "relational display field" in the
                    // display options, so show display field in the cell
                    $result .= $transform_function($dispval, array(), $meta);
                } else {
                    // otherwise display data in the cell
                    $result .= $transform_function($data, array(), $meta);
                }

            }
            $result .= '</a>';
        }

    } else {
        $result .= ($transform_function != $default_function)
            ? $transform_function($data, $transform_options, $meta)
            : $transform_function($data, array(), $meta);
    }

    // create hidden field if results from structure table
    if (isset($_GET['browse_distinct']) && ($_GET['browse_distinct'] == 1)) {

        $where_comparison = " = '" . $data . "'";

        $_url_params_for_show_data_row = array(
                                'db'    => $db,
                                'table' => $meta->orgtable,
                                'pos'   => '0',
                                'sql_query' => 'SELECT * FROM '
                                                    . PMA_backquote($db) . '.'
                                                    . PMA_backquote($meta->orgtable)
                                                    . ' WHERE '
                                                    . PMA_backquote($meta->orgname)
                                                    . $where_comparison,
        );

        $result .= '<input type="hidden" class="data_browse_link" value="'
            . PMA_generate_common_url($_url_params_for_show_data_row). '" />';

    }

    $result .= '</td>' . "\n";

    return $result;

}


/**
 * Prepares a checkbox for multi-row submits
 *
 * @param string $del_url           delete url
 * @param array  $is_display        array with explicit indexes for all
 *                                  the display elements
 * @param string $row_no            the row number
 * @param string $where_clause_html url encoded where cluase
 * @param array  $condition_array   array of conditions in the where cluase
 * @param string $del_query         delete query
 * @param string $id_suffix         suffix for the id
 * @param string $class             css classes for the td element
 *
 * @return string  the generated HTML
 */
function PMA_getCheckboxForMultiRowSubmissions($del_url, $is_display, $row_no,
    $where_clause_html, $condition_array, $del_query, $id_suffix, $class
) {

    $ret = '';

    if (! empty($del_url) && $is_display['del_lnk'] != 'kp') {

        $ret .= '<td ';
        if (! empty($class)) {
            $ret .= 'class="' . $class . '"';
        }

        $ret .= ' class="center">'
            . '<input type="checkbox" id="id_rows_to_delete' . $row_no . $id_suffix
            . '" name="rows_to_delete[' . $row_no . ']"'
            . ' class="multi_checkbox"'
            . ' value="' . $where_clause_html . '" '
            . (isset($GLOBALS['checkall'])
                ? 'checked="checked"'
                : '')
            . ' />'
            . '<input type="hidden" class="condition_array" value="'
            . htmlspecialchars(json_encode($condition_array)) . '" />'
            . '    </td>';
    }

    return $ret;

}


/**
 * Prepares an Edit link
 *
 * @param string $edit_url          edit url
 * @param string $class             css classes for td element
 * @param string $edit_str          text for the edit link
 * @param string $where_clause      where cluase
 * @param string $where_clause_html url encoded where cluase
 *
 * @return string  the generated HTML
 */
function PMA_getEditLink($edit_url, $class, $edit_str, $where_clause,
    $where_clause_html
) {

    $ret = '';
    if (! empty($edit_url)) {

        $ret .= '<td class="' . $class . ' center" ' . ' ><span class="nowrap">'
           . PMA_linkOrButton($edit_url, $edit_str, array(), false);
        /*
         * Where clause for selecting this row uniquely is provided as
         * a hidden input. Used by jQuery scripts for handling grid editing
         */
        if (! empty($where_clause)) {
            $ret .= '<input type="hidden" class="where_clause" value ="'
                . $where_clause_html . '" />';
        }
        $ret .= '</span></td>';
    }

    return $ret;

}


/**
 * Prepares an Copy link
 *
 * @param string $copy_url          copy url
 * @param string $copy_str          text for the copy link
 * @param string $where_clause      where clause
 * @param string $where_clause_html url encoded where cluase
 * @param string $class             css classes for the td element
 *
 * @return string  the generated HTML
 */
function PMA_getCopyLink($copy_url, $copy_str, $where_clause,
    $where_clause_html, $class
) {

    $ret = '';
    if (! empty($copy_url)) {

        $ret .= '<td class="';
        if (! empty($class)) {
            $ret .= $class . ' ';
        }

        $ret .= 'center" ' . ' ><span class="nowrap">'
           . PMA_linkOrButton($copy_url, $copy_str, array(), false);

        /*
         * Where clause for selecting this row uniquely is provided as
         * a hidden input. Used by jQuery scripts for handling grid editing
         */
        if (! empty($where_clause)) {
            $ret .= '<input type="hidden" class="where_clause" value="'
                . $where_clause_html . '" />';
        }
        $ret .= '</span></td>';
    }

    return $ret;

}


/**
 * Prepares a Delete link
 *
 * @param string $del_url delete url
 * @param string $del_str text for the delete link
 * @param string $js_conf text for the JS confirmation
 * @param string $class   css classes for the td element
 *
 * @return string  the generated HTML
 */
function PMA_getDeleteLink($del_url, $del_str, $js_conf, $class)
{

    $ret = '';
    if (! empty($del_url)) {

        $ret .= '<td class="';
        if (! empty($class)) {
            $ret .= $class . ' ';
        }

        $ret .= 'center" ' . ' >'
           . PMA_linkOrButton($del_url, $del_str, $js_conf, false)
           . '</td>';
    }

    return $ret;

}


/**
 * Prepare checkbox and links at some position (left or right)
 * (only called for horizontal mode)
 *
 * @param string $position          the position of the checkbox and links
 * @param string $del_url           delete url
 * @param array  $is_display        array with explicit indexes for all the
 *                                  display elements
 * @param string $row_no            row number
 * @param string $where_clause      where clause
 * @param string $where_clause_html url encoded where cluase
 * @param array  $condition_array   array of conditions in the where cluase
 * @param string $del_query         delete query
 * @param string $id_suffix         suffix for the id
 * @param string $edit_url          edit url
 * @param string $copy_url          copy url
 * @param string $class             css classes for the td elements
 * @param string $edit_str          text for the edit link
 * @param string $copy_str          text for the copy link
 * @param string $del_str           text for the delete link
 * @param string $js_conf           text for the JS confirmation
 *
 * @return string  the generated HTML
 */
function PMA_getCheckboxAndLinks($position, $del_url, $is_display, $row_no,
    $where_clause, $where_clause_html, $condition_array, $del_query, $id_suffix,
    $edit_url, $copy_url, $class, $edit_str, $copy_str, $del_str, $js_conf
) {

    $ret = '';

    if ($position == 'left') {

        $ret .= PMA_getCheckboxForMultiRowSubmissions(
            $del_url, $is_display, $row_no, $where_clause_html, $condition_array,
            $del_query, $id_suffix = '_left', '', '', ''
        );

        $ret .= PMA_getEditLink(
            $edit_url, $class, $edit_str, $where_clause, $where_clause_html, ''
        );

        $ret .= PMA_getCopyLink(
            $copy_url, $copy_str, $where_clause, $where_clause_html, ''
        );

        $ret .= PMA_getDeleteLink($del_url, $del_str, $js_conf, '', '');

    } elseif ($position == 'right') {

        $ret .= PMA_getDeleteLink($del_url, $del_str, $js_conf, '', '');

        $ret .= PMA_getCopyLink(
            $copy_url, $copy_str, $where_clause, $where_clause_html, ''
        );

        $ret .= PMA_getEditLink(
            $edit_url, $class, $edit_str, $where_clause, $where_clause_html, ''
        );

        $ret .= PMA_getCheckboxForMultiRowSubmissions(
            $del_url, $is_display, $row_no, $where_clause_html, $condition_array,
            $del_query, $id_suffix = '_right', '', '', ''
        );

    } else { // $position == 'none'

        $ret .= PMA_getCheckboxForMultiRowSubmissions(
            $del_url, $is_display, $row_no, $where_clause_html, $condition_array,
            $del_query, $id_suffix = '_left', '', '', ''
        );
    }

    return $ret;

}
?>
