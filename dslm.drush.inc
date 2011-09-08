<?php
/**
 * @file dslm drush commands
 */

DEFINE('DSLM_PATH', dirname(__FILE__));

require_once DSLM_PATH . '/lib/dslm.class.php';

/**
 * Implementation of hook_drush_help().
 */
function dslm_drush_help($section) {
  switch ($section) {
    case 'meta:dslm:title':
      return dt('DSLM (Drupl Symlink Manager)');
    case 'meta:dslm:summary':
      return dt('Drupal symlink management Drush commands.');
  }
}

/**
 * Implementation of hook_drush_command().
 */
function dslm_drush_command() {
  $items = array();

  // Global dslm arguments
  $arguments = array(
    'dslm-base' => 'Change the dslm base directory'
  );

  $items['dslm-info'] = array(
    'description' => 'DSLM site information',
    'arguments' => $arguments,
    'aliases' => array('si'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_ROOT,
  );

  $items['dslm-cores'] = array(
    'description' => 'DSLM Show Cores',
    'arguments' => $arguments,
    'aliases' => array('sc'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
  );

  $items['dslm-dists'] = array(
    'description' => 'DSLM  Show Cores',
    'arguments' => $arguments,
    'aliases' => array('sd'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
  );

  $items['dslm-new'] = array(
    'description' => 'DSLM  New Site',
    'arguments' => $arguments,
    // @TODO Wire up a --skip-sites-default option as well
    'options' => array('latest' => 'Install the latest core and dist.'),
    'aliases' => array('sn'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
  );

  $items['dslm-switch'] = array(
    'description' => 'DSLM  Switch Core and Dist',
    'arguments' => $arguments,
    'aliases' => array('ss'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_ROOT,
  );

  $items['dslm-switch-core'] = array(
    'description' => 'DSLM  Switch Core',
    'arguments' => $arguments,
    'aliases' => array('ssc'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_ROOT,
  );

  $items['dslm-switch-dist'] = array(
    'description' => 'DSLM  Switch Dist',
    'arguments' => $arguments,
    'aliases' => array('ssd'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_ROOT,
  );

  return $items;
}

/**
 * Command callback. Displays the dslm information for a site.
 */
function drush_dslm_info() {
  // Bootstrap dslm, this grabs the instantiated and configured DSLM library object
  if (!$dslm = _dslm_bootstrap())
    return FALSE;
  // Iterate through the cores array and print them out
  if (!$info = $dslm->siteInfo()) {
    return drush_set_error($dslm->lastError());
  }
  print "Core: {$info['core']} (Dist: {$info['dist']})\n";
  return TRUE;
}

/**
 * Command callback. Displays a list of available cores.
 */
function drush_dslm_cores() {
  // Bootstrap dslm, this grabs the instantiated and configured dslm library object
  if (!$dslm = _dslm_bootstrap()) {
    return FALSE;
  }

  // Pull cores
  $cores = $dslm->getCores();


  // Iterate through the cores array and print them out
  foreach ($cores['all'] as $key => $core) {
    print $core . "\n";
  }
  return TRUE;
}

/**
 * Command callback. Displays a list of available dists.
 */
function drush_dslm_dists() {
  // Bootstrap dslm, this grabs the instantiated and configured dslm library object
  if (!$dslm = _dslm_bootstrap()) {
    return FALSE;
  }

  // Pull the dists
  $dists = $dslm->getDists();


  // Iterate through all the dists
  foreach ($dists['all'] as $key => $dist) {
    print $dist . "\n";
  }
  return TRUE;
}

/**
 * Command callback. Creates a new dslm Drupal site.
 */
function drush_dslm_new() {
  // Pull the args
  $args = drush_get_arguments();
  if (!isset($args[1])) {
    return drush_set_error('DSLM: Please provide a site destination');
  }
  else {
    $site_dest = $args[1];
  }

  // Bootstrap dslm, this grabs the instantiated and configured Dslm library object
  if (!$dslm = _dslm_bootstrap()) {
    return FALSE;
  }

  // Pull the latest option
  $use_latest = drush_get_option('latest', FALSE);

  // Set the dist and core, either supplied on the CLI, set with --latest, or will prompt
  if ($use_latest) {
    $latest = $dslm->latest();
    $core = $latest['core'];
    $dist = $latest['dist'];
  }
  else {
    $core = isset($args[2]) ? $args[2] : FALSE;
    $dist = isset($args[3]) ? $args[3] : FALSE;
  }

  if (!$res = $dslm->newSite($site_dest, $core, $dist)) {
    return drush_set_error($dslm->lastError());
  }
  else {
    return TRUE;
  }
}

/**
 * Command callback. Prompts the user to switch the dslm dist and core.
 */
function drush_dslm_switch() {
  $args = drush_get_arguments();
  $core = isset($args[1]) ? $args[1] : FALSE;
  $dist = isset($args[2]) ? $args[2] : FALSE;

  // Bootstrap dslm, this grabs the instantiated and configured Dslm library object
  if (!$dslm = _dslm_bootstrap()) {
    return FALSE;
  }

  $run = $dslm->newSite(getcwd(), $core, $dist, TRUE);
  if (!$run) {
    return drush_set_error($dslm->lastError());
  }
  else {
    return TRUE;
  }
}

/**
 * Command callback. Prompts the user to switch the dslm core.
 */
function drush_dslm_switch_core() {
  $args = drush_get_arguments();
  $core = isset($args[1]) ? $args[1] : FALSE;

  // Bootstrap dslm, this grabs the instantiated and configured Dslm library object
  if (!$dslm = _dslm_bootstrap()) {
    return FALSE;
  }
  $run = $dslm->switchCore(getcwd(), $core);
  if (!$run) {
    return drush_set_error($dslm->lastError());
  }
  else {
    return TRUE;
  }
}

/**
 * Command callback. Prompts the user to switch the dslm dist.
 */
function drush_dslm_switch_dist() {
  $args = drush_get_arguments();
  $dist = isset($args[1]) ? $args[1] : FALSE;

  // Bootstrap dslm, this grabs the instantiated and configured Dslm library object
  if (!$dslm = _dslm_bootstrap()) {
    return FALSE;
  }

  // Pull the current site info for core filtering
  $site_info = $dslm->siteInfo(getcwd());
  // Run the switch
  $run = $dslm->switchDist(getcwd(), $dist, FALSE, $site_info['core']);
  if (!$run) {
    return drush_set_error($dslm->lastError());
  }
  else {
    return TRUE;
  }
}

/**
 * Internal bootstrap function
 * @return object
 *  Returns an instantiated Dslm object
 */
function _dslm_bootstrap() {
  if (!$base = _dslm_get_base()) {
    return drush_set_error('Please set a dslm drupal base');
  }

  // Instantiate the dslm object
  $dslm = new Dslm($base);

  // If the base is bad on construct an error will be set
  if ($last_error = $dslm->lastError()) {
    return drush_set_error($last_error);
  }

  return $dslm;
}

/**
 * Internal helper function to get the dslm drupal base folder
 * Reads the following locations in order:
 * - The cli switch --dslm-base=base
 * - The drushrc.php file $conf['dslm_base'] = base;
 * - The dslm_BASE system environment variable
 * @return string
 *  Returns a base path or FALSE
 */
function _dslm_get_base() {
  // Initialize an empty base.
  $base = '';

  // Try to get a base from one of the appropriate sources
  if ($base_check = drush_get_option('dslm-base', FALSE)) {
    // We have this wet with --drupal-base on the cli
    $base = $base_check;
  }
  elseif ($base_check = drush_get_option('dslm_base', FALSE)) {
    // We have this set in the drushrc.php file
    $base = $base_check;
  }
  else {
    // Last ditch is tp ise the environment variable if it's set.
    if (isset($_ENV['DSLM_BASE'])) {
      $base = $_ENV['DSLM_BASE'];
    } else {
      // If we got here and there wasn't an environment variable set, bail
      return FALSE;
    }
  }

  // PHP doesn't resolve ~ as the home directory.
  if (isset($_SERVER['HOME'])) {
    $base = preg_replace('/^\~/', $_SERVER['HOME'], $base);
  }

  return $base;
}
