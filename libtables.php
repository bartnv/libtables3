<?php

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * Libtables3: framework for building web-applications on relational databases *
 * Version 3.0.0 / Copyright (C) 2023  Bart Noordervliet, MMVI                 *
 *                                                                             *
 * This program is free software: you can redistribute it and/or modify        *
 * it under the terms of the GNU Affero General Public License as              *
 * published by the Free Software Foundation, either version 3 of the          *
 * License, or (at your option) any later version.                             *
 *                                                                             *
 * This program is distributed in the hope that it will be useful,             *
 * but WITHOUT ANY WARRANTY; without even the implied warranty of              *
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the               *
 * GNU Affero General Public License for more details.                         *
 *                                                                             *
 * You should have received a copy of the GNU Affero General Public License    *
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.       *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

if (session_status() !== PHP_SESSION_ACTIVE) {
  if (headers_sent()) error_log('Libtables error: need to start session but headers already sent for request "' . $_SERVER['REQUEST_URI'] . '?' . $_SERVER['QUERY_STRING'] . '"');
  else session_start();
}
require('config.php');
if (is_file(dirname(__FILE__) . '/local.php')) {
  if (is_readable(dirname(__FILE__) . '/local.php')) include(dirname(__FILE__) . '/local.php');
  else error_log("Libtables error: local.php exists but is not readable for the PHP user");
}

$tables = [];

function lt_setvar($name, $value, $key = null) {
  if ($key !== null) {
    if (!is_array($value)) throw new Exception('Libtables error: lt_setvar used with third parameter without an array as second');
    if (!isset($value[$key])) return;
    $value = $value[$key];
  }
  if ($value === null) unset($_SESSION[$name]);
  else $_SESSION[$name] = $value;
}
function lt_getvar($name, $default = null) {
  if (isset($_SESSION[$name])) return $_SESSION[$name];
  if ($default === null) throw new Exception("Undefined libtables variable '$name' used in block");
  return $default;
}
function lt_isvar($name) {
  if (isset($_SESSION[$name])) return true;
  return false;
}

function lt_search($tag, $options) {
  global $tables;
  global $basename;

  if (!$basename) { // run from data.php
    $table = [];
    $table['tag'] = $tag;
    $table['options'] = $options;
    $tables[] = $table;
    return;
  }

  if (empty($options['target'])) {
    error_log('Libtables error: block ' . $basename . ' search ' . $tag . ' has no target defined');
    return;
  }

  $divstr = ' <div id="' . $tag . '" class="lt-search" data-source="' . $basename . ':' . $tag . '"';
  $divstr .= ' data-options="' . base64_encode(json_encode($options)) . '"></div>';
  print $divstr;
}

function lt_control($tag, $options) {
  global $tables;
  global $basename;

  if (!$basename) { // run from data.php
    $table = [];
    $table['tag'] = $tag;
    $table['options'] = $options;
    $tables[] = $table;
    return;
  }

  $divstr = ' <div id="' . $tag . '" class="lt-control" data-source="' . $basename . ':' . $tag . '"';
  $divstr .= ' data-options="' . base64_encode(json_encode($options)) . '"></div>';
  print $divstr;
}

function lt_text($tag, $query, $format, $options = array()) {
  global $tables;
  global $basename;

  if (!$basename) {
    $table = array();
    $table['tag'] = $tag;
    $table['query'] = $query;
    $table['options'] = $options;
    $table['format'] = $format;
    $tables[] = $table;
    return;
  }

  if (!empty($options['classes']['div'])) $divclasses = 'lt-div-text ' . $options['classes']['div'];
  else $divclasses = 'lt-div-text';

  $divstr = '<div id="' . $tag . '" class="' . $divclasses . '" data-source="' . $basename . ':' . $tag . '"';
  if (!empty($options['embed'])) $divstr .= ' data-embedded="' . base64_encode(lt_query_to_string($query, $format)) . '"';
  print $divstr . "></div>\n";
}

function lt_insert($tag, $title, $colnames, $options = []) {
  global $basename;

  if (!is_array($colnames)) {
    print "<p>lt_insert() in block $basename has invalid colnames</p>";
    return;
  }
  if (!is_array($options)) {
    print "<p>lt_insert() in block $basename has invalid options</p>";
    return;
  }
  lt_table($tag, $title, $colnames, $options);
}

function lt_table($tag, $title, $query, $options = array()) {
  global $lt_settings;
  global $basename; // Set by lt_print_block()
  global $block_options; // Set by lt_print_block()

  $table = [];
  $table['block'] = $basename;
  $table['tag'] = $tag;
  $table['title'] = $title;
  $table['query'] = $query;
  $table['options'] = $options;

  if (!$basename) { // lt_table run from data.php
    global $tables;
    $tables[] = $table;
    return;
  }

  if (empty($tag)) {
    print "<p>Table in block $basename has no tag specified</p>";
    return;
  }
  if (!is_string($tag)) {
    print "<p>Table in block $basename has an invalid tag specified (is not a string)</p>";
    return;
  }
  if (empty($title)) {
    print "<p>Table $tag in block $basename has no title specified</p>";
    return;
  }
  if (!is_string($title)) {
    print "<p>Table in block $basename has an invalid title specified (is not a string)</p>";
    return;
  }

  if (!empty($options['classes']['div'])) $divclasses = 'lt-div ' . $options['classes']['div'];
  else $divclasses = 'lt-div';

  $divstr = ' <div id="' . $tag . '" class="' . $divclasses . '" data-source="' . $basename . ':' . $tag . '"';

  if (!empty($options['embed'])) {
    $data = prepare_table($table);
    if (!empty($data['error'])) {
      print '<p>Error: ' . $data['error'] . '</p>';
      return;
    }
    $divstr .= ' data-embedded="' . "\n" . chunk_split(base64_encode(json_encode($data)), 79, "\n") . '"';
  }

  if (!empty($block_options['active'])) $divstr .= ' data-active="' . $block_options['active'] . '"';

  print $divstr . '>Loading table ' . $title . "...</div>\n";
}

function prepare_table($table) {
  $ret = [];
  if (is_array($table['query'])) {
    $ret['headers'] = $table['query'];
    $ret['rowcount'] = -1;
  }
  elseif (isset($table['options']['export']['nopreview']) && $table['options']['export']['nopreview']) {
    $ret = lt_query($table['query'] . ' LIMIT 0');
    $ret['rowcount'] = lt_query_single('SELECT COUNT(*) FROM (' . $table['query'] . ') AS tmp');
  }
  elseif (strpos($table['query'], 'WHERE FALSE') && !empty($_SESSION['search_' . $table['block'] . '_' . $table['tag'] . '_where'])) {
    if (empty($_SESSION['search_' . $table['block'] . '_limit'])) $limit = '';
    else $limit = ' LIMIT ' . $_SESSION['search_' . $table['block'] . '_limit'];
    $ret = lt_query(
      str_replace(
        'WHERE FALSE',
        'WHERE ' . $_SESSION['search_' . $table['block'] . '_' . $table['tag'] . '_where'], $table['query']
      ) . $limit,
      0,
      $_SESSION['search_' . $table['block'] . '_' . $table['tag'] . '_values']
    );
    if ($limit && !empty($ret['rows']) && count($ret['rows']) == $_SESSION['search_' . $table['block'] . '_limit']) {
      $ret['total'] = lt_query_single("SELECT count(*) FROM (" .
        str_replace(
          'WHERE FALSE',
          'WHERE ' . $_SESSION['search_' . $table['block'] . '_' . $table['tag'] . '_where'], $table['query']
        ) .
      ") as sub", $_SESSION['search_' . $table['block'] . '_' . $table['tag'] . '_values']);
    }
  }
  else $ret = lt_query($table['query']);
  if (isset($ret['error'])) {
    $ret['error'] = 'Query for table ' . $table['title'] . ' in block ' . $table['block'] . " returned error: " . $ret['error'];
    return $ret;
  }
  $ret['block'] = $table['block'];
  $ret['tag'] = $table['tag'];
  if (!empty($table['options']['titlequery'])) $ret['title'] = lt_query_single($table['options']['titlequery']);
  else $ret['title'] = $table['title'];
  $ret['options'] = prepare_options($table['options']);
  if (empty($lt_settings['checksum']) || ($lt_settings['checksum'] == 'php')) $ret['crc'] = crc32(json_encode($ret['rows'] ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PARTIAL_OUTPUT_ON_ERROR));
  elseif ($lt_settings['checksum'] == 'psql') {
    $ret['crc'] = lt_query_single("SELECT md5(string_agg(q::text, '')) FROM (" . $table['query'] . ") AS q)");
    if (strpos($ret['crc'], 'Error:') === 0) {
      $ret['error'] = '<p>Checksum query for table ' . $table['title'] . ' in block ' . $table['block'] . ' returned error: ' . substr($ret['crc'], 6);
    }
  }
  return $ret;
}

function get_selectoptions($query) {
  global $dbh;

  $ret = [];
  if (!($res = $dbh->query($query))) $ret['error'] = "SQL error: " . $dbh->errorInfo()[2];
  else {
    $ret['items'] = $res->fetchAll(\PDO::FETCH_NUM);
    $ret['crc'] = crc32(json_encode($ret['items'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PARTIAL_OUTPUT_ON_ERROR));
  }
  return $ret;
}

function prepare_options($options) {
  $tables = [];

  if (!empty($options['edit'])) {
    foreach ($options['edit'] as $idx => &$edit) {
      if (!is_numeric($idx)) continue;
      if (is_string($edit)) $edit = [ 'target' => $edit ];
      else {
        if (isset($edit[0])) { $edit['target'] = $edit[0]; unset($edit[0]); }
        if (isset($edit[1])) { $edit['query'] = $edit[1]; unset($edit[1]); }
        unset($edit['sqlfunction']);
        unset($edit['phpfunction']);
        if (isset($edit['query'])) {
          $ret = get_selectoptions($edit['query']);
          if (!empty($ret['error'])) error_log('Libtables error: ' . $ret['error']);
          else $edit['list'] = $ret;
        }
      }
      if (!isset($edit['required'])) {
        list($table, $column) = explode('.', $edit['target']);
        if (empty($table) || empty($column)) error_log('Libtables error: invalid target specification: ' . json_encode($edit));
        if (!isset($tables[$table])) $tables[$table] = lt_col_nullable($table);
        if (!isset($tables[$table][$column])) error_log("Libtables error: table $table column $column not found in nullable query");
        if (!$tables[$table][$column]) $edit['required'] = true;
      }
    }
  }
  if (!empty($options['insert'])) {
    foreach ($options['insert'] as $idx => &$insert) {
      if (!is_numeric($idx)) continue;
      if (is_bool($insert)) continue; // An insert can be set to FALSE to negate a setting included from edit
      if (is_string($insert)) $insert = [ 'target' => $insert ];
      else {
        if (isset($insert[0])) { $insert['target'] = $insert[0]; unset($insert[0]); }
        if (isset($insert[1])) { $insert['query'] = $insert[1]; unset($insert[1]); }
        unset($insert['sqlfunction']);
        unset($insert['phpfunction']);
        if (isset($insert['query'])) {
          $ret = get_selectoptions($insert['query']);
          if (!empty($ret['error'])) error_log('Libtables error: ' . $ret['error']);
          else $insert['list'] = $ret;
        }
      }
      if (!isset($insert['required'])) {
        list($table, $column) = explode('.', $insert['target']);
        if (empty($table) || empty($column)) error_log('Libtables error: invalid target specification: ' . json_encode($insert));
        if (!isset($tables[$table])) $tables[$table] = lt_col_nullable($table);
        if (!isset($tables[$table][$column])) error_log("Libtables error: table $table column $column not found in nullable query");
        if (!$tables[$table][$column]) $insert['required'] = true;
      }
    }
  }
  if (!empty($options['tableaction']['sqlcondition'])) $options['tableaction']['sqlcondition'] = (lt_query_count($options['tableaction']['sqlcondition']) > 0);
  if (!empty($options['selectany'])) {
    $sa = $options['selectany'];
    if (!empty($sa['id'])) $tmp = lt_query('SELECT ' . $sa['fields'][1] . ' FROM ' . $sa['linktable'] . ' WHERE ' . $sa['fields'][0] . ' = ' . $sa['id']);
    else $tmp = lt_query('SELECT ' . $sa['fields'][1] . ' FROM ' . $sa['linktable'] . ' WHERE ' . $sa['fields'][0] . ' = ?');
    $options['selectany']['links'] = array_column($tmp['rows'] ?? [], 0);
  }

  return $options;
}

function lt_col_nullable($table) {
  global $dbh;
  $result = [];

  if (!($dbtype = $dbh->getAttribute(\PDO::ATTR_DRIVER_NAME))) error_log('Libtables error: unable to query SQL server type');
  if ($dbtype == 'mysql') {
    if (!($res = $dbh->query("DESC $table"))) error_log('Libtables error: SQL-error: ' . $dbh->errorInfo()[2]);
    foreach ($res as $row) {
      $result[$row['Field']] = $row['Null']=="YES" ? true : ($row['Default']===null?false:true);
    }
  }
  elseif ($dbtype == 'sqlite') {
    if (!($res = $dbh->query("PRAGMA table_info($table)"))) error_log('Libtables error: SQL-error: ' . $dbh->errorInfo()[2]);
    foreach ($res as $row) {
      $result[$row['name']] = $row['notnull']?false:true;
    }
  }
  elseif ($dbtype == 'pgsql') {
    if (!($res = $dbh->query("SELECT column_name, is_nullable, column_default FROM information_schema.columns WHERE table_name = '$table'"))) error_log('Libtables error: SQL-error: ' . $dbh->errorInfo()[2]);
    foreach ($res as $row) {
      $result[$row['column_name']] = $row['is_nullable']=='YES'? true : (empty($row['column_default'])?false:true);
    }
  }
  if (empty($result)) error_log("Libtables error: lt_col_nullable('$table') failed to produce results");
  return $result;
}

function lt_calendar($tag, $queries, $options = array()) {
  global $lt_settings;
  global $tables;
  global $basename;

  if (!$basename) { // run from data.php
    $table = array();
    $table['tag'] = $tag;
    $table['queries'] = $queries;
    $table['options'] = $options;
    $tables[] = $table;
  }
}
function lt_gantt($tag, $queries, $options = array()) {
  global $lt_settings;
  global $tables;
  global $basename;

  if (!$basename) { // run from data.php
    $table = array();
    $table['tag'] = $tag;
    $table['queries'] = $queries;
    $table['options'] = $options;
    $tables[] = $table;
  }
}

function lt_print_block($block, $options = array()) {
  global $lt_settings;
  global $basename;
  global $block_options;
  global $mch; // May be used in block definitions

  $basename_prev = $basename;
  $basename = $block;
  $block_options = $options;
  if (!isset($block_options['nowrapper'])) $block_options['nowrapper'] = false;

  // if ($lt_settings['security'] == 'php') {
  //   if (empty($lt_settings['allowed_blocks_query'])) {
  //     print "Configuration sets security to 'php' but no allowed_blocks_query defined";
  //     return;
  //   }
  //   if (!($res = $dbh->query($lt_settings['allowed_blocks_query']))) {
  //     $err = $dbh->errorInfo();
  //     print "Allowed-blocks query returned error: " . $err[2];
  //     return;
  //   }
  //   $allowed_blocks = $res->fetchAll(PDO::FETCH_COLUMN, 0);
  //   if (!in_array($basename, $allowed_blocks)) {
  //     print "Block $basename is not in our list of allowed blocks";
  //     return;
  //   }
  // }

  if (is_array($lt_settings['blocks_dir'])) $dirs = $lt_settings['blocks_dir'];
  else $dirs[] = $lt_settings['blocks_dir'];

  if (!$block_options['nowrapper']) {
    print '<div id="block_' . $basename . '" class="lt-block';
    if (!empty($block_options['class'])) print ' ' . $block_options['class'];
    print "\">\n";
  }

  foreach($dirs as $dir) {
    if (file_exists($dir . $basename . '.html')) {
      readfile($dir . $basename . '.html');
      if (!$block_options['nowrapper']) print "</div>\n";
      $basename = $basename_prev;
      return;
    }
    if (file_exists($dir . $basename . '.yml')) {
      if (!function_exists('yaml_parse_file')) {
        print "YAML block found but the PHP YAML parser is not installed";
        $basename = $basename_prev;
        return;
      }
      $yaml = yaml_parse_file($dir . $basename . '.yml', -1);
      if ($yaml === false) print("YAML syntax error in block $basename");
      else {
        foreach ($yaml as $table) {
          lt_table($table[0], $table[1], $table[2], isset($table[3])?$table[3]:array());
        }
      }
      if (!$block_options['nowrapper']) print "</div>\n";
      $basename = $basename_prev;
      return;
    }
    if (file_exists($dir . $basename . '.php')) {
      try {
        $ret = include $dir . $basename . '.php';
      } catch (Exception $e) {
        print "PHP error in block $basename: " . $e->getMessage();
      }
      if (!$block_options['nowrapper']) print "</div>\n";
      $basename = $basename_prev;
      return $ret;
    }
  }

  print "Block $basename not found in blocks_dir " . implode(", ", $dirs) . " (CWD: " . getcwd() . ")";
  $basename = $basename_prev;
}

function lt_bind_params($stmt, $query, $params = []) {
  if (!preg_match_all("/[ (,]:([a-z_]+)/", $query, $matches)) return;
  foreach ($matches[1] as $param) {
    if (isset($params[$param])) $value = $params[$param];
    elseif (isset($_SESSION[$param])) $value = $_SESSION[$param];
    else throw new Exception("Undefined libtables variable '$param' used in query");
    if (is_bool($value)) $stmt->bindValue(":$param", $value, PDO::PARAM_BOOL);
    elseif (is_null($value)) $stmt->bindValue(":$param", $value, PDO::PARAM_NULL);
    elseif (is_int($value)) $stmt->bindValue(":$param", $value, PDO::PARAM_INT);
    else $stmt->bindValue(":$param", $value);
  }
}

function lt_query($query, $id = 0, $params = []) {
  global $dbh;
  $ret = array();

  $start = microtime(TRUE);

  if (!($res = $dbh->prepare($query))) {
    $ret['error'] = $dbh->errorInfo()[2];
    return $ret;
  }
  try { lt_bind_params($res, $query, $params); } catch (Exception $e) {
    $ret['error'] = $e->getMessage();
    return $ret;
  }
  if (!$res->execute()) {
    $ret['error'] = $res->errorInfo()[2];
    return $ret;
  }

  $ret['querytime'] = intval((microtime(TRUE)-$start)*1000);

  if ($id) {
    while ($row = $res->fetch(PDO::FETCH_NUM)) {
      if ($row[0] == $id) {
        $ret['rows'][0] = $row;
        break;
      }
    }
    if (empty($ret['rows'][0])) $ret['error'] = 'row id ' . $id . ' not found';
  }
  else {
    $ret['headers'] = array();
    $ret['types'] = array();
    for ($i = 0; $i < $res->columnCount(); $i++) {
      $col = $res->getColumnMeta($i);
      $ret['headers'][] = $col['name'];
      $ret['types'][] = $col['native_type'];
    }
    $ret['rows'] = $res->fetchAll(PDO::FETCH_NUM);
    $ret['tables'] = lt_tables_from_query($query);

    // Do datatype correction because PHP PDO is dumb about floating point values
    for ($i = 0; $i < $res->columnCount(); $i++) {
      if (substr($ret['types'][$i], 0, 5) == 'float') {
        foreach ($ret['rows'] as &$row) $row[$i] = floatval($row[$i]);
      }
    }
  }

  return $ret;
}

function lt_query_to_string($query, $format, $params = []) {
  global $dbh;
  global $block_options; // Set by lt_print_block()

  if (!($res = $dbh->prepare($query))) {
    return "SQL-error: " . $dbh->errorInfo()[2];
  }
  try { lt_bind_params($res, $query, $params); } catch (Exception $e) {
    return "SQL parameter error: " . $e->getMessage();
  }
  if (!$res->execute()) {
    return "SQL-error: " . $res->errorInfo()[2];
  }

  if (!$res->rowCount()) return "Query for lt_query_to_string() did not return any rows";
  if (!$res->columnCount()) return "Query for lt_query_to_string() did not return any columns";

  $n = 0;
  $ret = "";
  while ($row = $res->fetch(PDO::FETCH_NUM)) {
    $str = $format;
    $n++;
    for ($i = $res->columnCount()-1; $i >= 0; $i--) {
      $str = str_replace('#'.$i, $row[$i], $str);
    }
    $str = str_replace('##', $n, $str);
    $ret .= $str;
  }
  return $ret;
}

function lt_query_single($query, $params = []) {
  global $dbh;

  if (!($res = $dbh->prepare($query))) {
    error_log("Libtables error: query prepare failed: " . $dbh->errorInfo()[2]);
    return null;
  }
  try { lt_bind_params($res, $query, $params); } catch (Exception $e) {
    error_log("Libtables error: " . $e->getMessage());
    return null;
  }
  if (!$res->execute()) {
    error_log("Libtables error: query execute failed: " . $res->errorInfo()[2]);
    return null;
  }
  if (!($row = $res->fetch(PDO::FETCH_NUM))) return null;
  return $row[0];
}

function lt_query_row($query, $params = []) {
  global $dbh;

  if (!($res = $dbh->prepare($query))) {
    error_log("Libtables error: query prepare failed: " . $dbh->errorInfo()[2]);
    return null;
  }
  try { lt_bind_params($res, $query, $params); } catch (Exception $e) {
    error_log("Libtables error: " . $e->getMessage());
    return null;
  }
  if (!$res->execute()) {
    error_log("Error: query execute failed: " . $res->errorInfo()[2]);
    return null;
  }
  if (!($row = $res->fetch())) return null;
  return $row;
}

function lt_query_foreach_row($query, $function, $params = []) {
  global $dbh;

  if (!($res = $dbh->prepare($query))) {
    error_log("Libtables error: query prepare failed: " . $dbh->errorInfo()[2]);
    return null;
  }
  try { lt_bind_params($res, $query, $params); } catch (Exception $e) {
    error_log("Libtables error: " . $e->getMessage());
    return null;
  }
  if (!$res->execute()) {
    error_log("Error: query execute failed: " . $res->errorInfo()[2]);
    return null;
  }
  while ($row = $res->fetch()) $function($row);
}

function lt_tables_from_query($query) {
  if (!preg_match_all('/(?:from|join)\s+([^(\s]+)/i', $query, $matches)) {
    error_log('lt_tables_from_query() failed');
    return;
  }
  return array_keys(array_flip($matches[1]));
}

function lt_query_check($query, $params = []) {
  global $dbh;

  if (!($res = $dbh->prepare($query))) {
    error_log("Libtables error: query prepare failed: " . $dbh->errorInfo()[2]);
    return false;
  }
  try { lt_bind_params($res, $query, $params); } catch (Exception $e) {
    error_log("Libtables error: " . $e->getMessage());
    return false;
  }
  if (!$res->execute()) {
    error_log("Libtables error: query execute failed: " . $res->errorInfo()[2]);
    return false;
  }
  if (!($row = $res->fetch(PDO::FETCH_NUM))) return false;

  return true;
}

function lt_query_count($query, $params = []) {
  global $dbh;

  $query = 'SELECT COUNT(*) FROM (' . $query . ') AS tmp';
  if (!($res = $dbh->prepare($query))) {
    error_log("Libtables error: query prepare failed: " . $dbh->errorInfo()[2]);
    return -1;
  }
  try { lt_bind_params($res, $query, $params); } catch (Exception $e) {
    error_log("Libtables error: " . $e->getMessage());
    return -1;
  }
  if (!$res->execute()) {
    error_log("Libtables error: query execute failed: " . $res->errorInfo()[2]);
    return -1;
  }
  if (!($row = $res->fetch())) return -1;
  if (!is_numeric($row[0])) return -1;
  return $row[0]+0;
}

function lt_update_count($query, $params = []) {
  global $dbh;

    if (!($res = $dbh->prepare($query))) {
      error_log("Libtables error: query prepare failed: " . $dbh->errorInfo()[2]);
      return -1;
    }
    try { lt_bind_params($res, $query, $params); } catch (Exception $e) {
      error_log("Libtables error: " . $e->getMessage());
      return -1;
    }
    if (!$res->execute()) {
      error_log("Libtables error: query execute failed: " . $res->errorInfo()[2]);
      return -1;
    }
    return $res->rowCount();
}

function lt_buttongrid($tag, $queries, $options) {
  print '<div class="buttongrid"><p>This\'ll be the buttongrid...</p></div>';
}

function lt_numpad($tag, $title) {
  print '<div class="numpad">' . $title . '<br>';
  print '<span class="numpad_row">';
  print '<input id="numpad_button_7" class="numpad_button" type="button" value="7" onclick="numpad_click(\'' . $tag . '\', \'7\');">';
  print '<input id="numpad_button_8" class="numpad_button" type="button" value="8" onclick="numpad_click(\'' . $tag . '\', \'8\');">';
  print '<input id="numpad_button_9" class="numpad_button" type="button" value="9" onclick="numpad_click(\'' . $tag . '\', \'9\');">';
  print '</span><br>';
  print '<span class="numpad_row">';
  print '<input id="numpad_button_4" class="numpad_button" type="button" value="4" onclick="numpad_click(\'' . $tag . '\', \'4\');">';
  print '<input id="numpad_button_5" class="numpad_button" type="button" value="5" onclick="numpad_click(\'' . $tag . '\', \'5\');">';
  print '<input id="numpad_button_6" class="numpad_button" type="button" value="6" onclick="numpad_click(\'' . $tag . '\', \'6\');">';
  print '</span><br>';
  print '<span class="numpad_row">';
  print '<input id="numpad_button_1" class="numpad_button" type="button" value="1" onclick="numpad_click(\'' . $tag . '\', \'1\');">';
  print '<input id="numpad_button_2" class="numpad_button" type="button" value="2" onclick="numpad_click(\'' . $tag . '\', \'2\');">';
  print '<input id="numpad_button_3" class="numpad_button" type="button" value="3" onclick="numpad_click(\'' . $tag . '\', \'3\');">';
  print '</span><br>';
  print '<span class="numpad_row">';
  print '<input id="numpad_button_0" class="numpad_button" type="button" value="0" onclick="numpad_click(\'' . $tag . '\', \'0\');">';
  print '<div id="numpad_display"></div>';
  print '<input id="numpad_button_c" class="numpad_button" type="button" value="C" onclick="numpad_click(\'' . $tag . '\', null);">';
  print '</span><br>';
  print '</div>';
}

function lt_sqlrun() {
  global $basename; // Set by lt_print_block()

  if (!$basename) { // lt_table run from data.php
    return;
  }

  print <<<END
<p>
  <form action="data.php" method="post">
    <input type="hidden" name="mode" value="sqlrun">
    <textarea id="sqlrun" name="sql" oninput="check_sql(this)" autofocus="autofocus"></textarea><br>
    <input type="button" value="Run" onclick="run_sql(this.parentNode)">
  </form>
</p>
<p>
  <table id="sqlrun:table" class="lt-table"></table>
</p>
END;
}
