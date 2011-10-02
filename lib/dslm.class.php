<?php
/**
 * Drupal DSLM
 * A PHP library to handle a central Drupal symlink structure
 */
class Dslm {
  /**
   * The base folder
   *
   * @var string
   */
  protected $base = FALSE;

  /**
   * The last error produced
   *
   * @var string
   */
  protected $last_error = '';

  /**
   * Set to skip a dir check
   *
   * @var string
   */
  protected $skip_dir_check = FALSE;

  /**
   * Define a core parsing regular expression
   *
   * @var string
   */
  protected $core_regex = '/(.+)\-([\d\.x]+\-*[dev|alph|beta|rc|pl]*[\d]*)$/i';

  /**
   * Define a profile parsing regular expression
   *
   * @var string
   */
  protected $profile_regex = '/^([A-Z0-9_]+)\-((\d+)\.x\-([\d\.x]+\-*[dev|alph|beta|rc|pl]*[\d]*))$/i';

  /**
   * DSLM constructor
   *
   * @param $base
   *  The base path containing the profiles and cores
   */
  public function __construct($base) {
    // Validate the base
    if ($valid_base = $this->validateBase($base)) {
      $this->base = $valid_base;
    } else {
      $this->last_error = sprintf("The base dir \"%s\" is invalid. Please see the dslm README for more information on the dslm-base directory.", $base);
    }
  }

  /**
   * Set the skip_dir_check attribute
   *
   * @param boolean $in
   *  The boolean value for the local set_skip_dir attribute
   */
  public function setSkipDirCheck($in = TRUE) {
    $this->skip_dir_check = (boolean) $in;
  }

  /**
   * Get the Drupal cores
   *
   * @return array
   *  Returns an array or cores
   */
  public function getCores() {
    $all = array();
    $pre_releases = array();
    $releases = array();

    foreach ($this->filesInDir($this->getBase() . "/cores/") as $core) {
      if ($this->isCoreString($core)) {
        $all[] = $core;

        // Test for pre-releases
        if (preg_match('/[dev|alph|beta|rc|pl]+[\.\d]*$/i', $core)) {
          $pre_releases[] = $core;
        }
        else {
          $releases[] = $core;
        }
      }
    }

    $out = array(
      'all' => $this->orderByVersion('core', $all),
      'dev' => $this->orderByVersion('core', $pre_releases),
      'release' => $this->orderByVersion('core', $releases),
    );

    return $out;
  }

  /**
   * Get the profiles
   *
   * @return array
   *  Returns an associative array of profiles grouped by profile name
   */
  public function getProfiles() {
    $profiles = array();

    // Reusable regex for determining if the profile strin is dev or release
    $dev_regex = '/[dev|alph|beta|rc|pl]+[\.\d]*$/i';

    // Iterate through and get the profiles into named groups
    foreach ($this->filesInDir($this->getBase() . "/profiles/") as $profile) {
      if ($matches = $this->isProfileString($profile)) {

        $profiles[$matches[1]]['all'][] = $matches[2];
        // Test for pre-releases
        if (preg_match($dev_regex, $profile)) {
          $profiles[$matches[1]]['dev'][] = $matches[2];
        }
        else {
          $profiles[$matches[1]]['release'][] = $matches[2];
        }
      }
    }

    // Normalize and sort the named group sub-arrays
    foreach ($profiles as $name => $value) {
      foreach (array('all', 'dev', 'release') as $class) {
        if (isset($value[$class])) {
          usort($profiles[$name][$class], 'version_compare');
        }
        else {
          // We probably want at least an empty array for each class
          $profiles[$name][$class] = array();
        }
      }
    }

    // Return the profiles array
    return $profiles;
  }

  /**
   * Return the latest versions of core and profile
   * broken up by core/profile, dev_core/dev_profile, and release_core/release_profile
   *
   * @return array
   *   Returns an array of the various latest versions
   */
  public function latest() {
    $cores = $this->getCores();
    $profiles = $this->getProfiles();

    return array(
      'core' => $cores['all'][count($cores['all'])-1],
      'profile' => $profiles['all'][count($profiles['all'])-1],
      'dev_core' => $cores['dev'][count($cores['dev'])-1],
      'dev_profile' => $profiles['dev'][count($profiles['dev'])-1],
      'release_core' => $cores['release'][count($cores['release'])-1],
      'release_profile' => $profiles['release'][count($profiles['release'])-1],
    );
  }

  /**
   * Check core
   *
   * @param string $core
   *  The core to check
   * @return boolean
   *  Returns a boolean for whether the core is valid or not
   */
  public function isValidCore($core) {
    $cores = $this->getCores();
    return in_array($core, $cores['all']);
  }

  /**
   * Check profile
   *
   * @param string $profile
   *  The profile to check
   * @return boolean
   *  Returns a boolean for whether the profile is valid or not
   */
  public function isValidProfile($name, $version) {
    $profiles = $this->getProfiles();
    if (isset($profiles[$name]) && in_array($version, $profiles[$name]['all'])) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Returns site information
   *
   * @param boolean $d
   *  The directory to use as the base, this will default to getcwd()
   * @return array
   *  Returns an array containing the current profile and core or FALSE
   */
  public function siteInfo($d = FALSE) {
    if (!$d) {
      $d = getcwd();
    }
    if (!$this->isDrupalDir($d)) {
      $this->last_error = 'This directory isn\'t a Drupal dir';
      return FALSE;
    }
    $core = $this->firstLinkDirname($d);
    $profile = $this->firstLinkDirname($d.'/profiles');
    if (!$core || !$profile) {
      $this->last_error = 'Invalid symlinked site';
      return FALSE;
    }
    $out = array(
      'core' => $core,
      'profile' => $profile,
    );
    return $out;
  }

  /**
   * Create a new drupal site
   *
   * @param string $dest_dir
   *  The destination directory for the new site
   * @param string $core
   *  The core to use
   * @param string $profile
   *  The profile to use
   * @param boolean $force
   *  Whether or not to force the site creation
   *
   * @return boolean
   *  Returns boolean
   */
  public function newSite($dest_dir, $core = FALSE, $profile = FALSE, $force = FALSE) {
    // Load the base
    $base = $this->getBase();

    // Dest Directory creation and validation
    // TODO: Much more validation needed here, wire in checking for empty, etc.
    if (file_exists($dest_dir) && !$force) {
      $this->last_error = "The directory already exists";
      return FALSE;
    }

    // Run the profile and core switches
    $core = $this->switchCore($dest_dir, $core, TRUE);
    //$profile = $this->switchprofile($dest_dir, $profile, TRUE, $core);

    // Break here for testing right now
    return TRUE;
  }


  /**
   * Switch the core
   *
   * @param string $dest_dir
   *  The destination dir to switch the cor for, default to getcwd()
   * @param string $core
   *  The core to switch to.
   * @param boolean $force
   *  Whether or not to force the switch
   *
   * @return string
   *  Returns the core it switched to.
   */
  public function switchCore($dest_dir = FALSE, $core = FALSE, $force = FALSE) {
    // Pull the base
    $base = $this->getBase();

    // Handle destination directory
    if (!$dest_dir) {
      $dest_dir = getcwd();
    }
    elseif (file_exists($dest_dir)) {
      $dest_dir = realpath($dest_dir);
    }

    // Make sure this is a drupal base
    if (!$this->isDrupalDir($dest_dir) && !$force) {
      $this->last_error = 'Invalid Drupal Directory';
      return FALSE;
    }

    // Get the core if it wasn't specified on the CLI
    if (!$core || !$this->isValidCore($core)) {
      $core = $this->chooseCore();
    }

    // They've had the option to cancel when choosing a core
    // If at this point the dest_dir doesn't exit and we're forcing,
    // let's try to create it
    if (!file_exists($dest_dir) && !is_link($dest_dir) && $force) {
      mkdir($dest_dir);
      $dest_dir = realpath($dest_dir);
    }

    $source_dir = "$base/cores/$core";

    // Remove any existing symlinks in the dest dir that link back to a core folder
    $this->removeCoreLinks($dest_dir);

    // Iterate through the source files and start linking
    foreach ($this->filesInDir($source_dir) as $f) {
      // We do not want to link the the sites or profiles directories
      // We'll add slugs later
      if($f == "sites" || $f == "profiles") { continue; }
      $relpath = $this->relpath($source_dir, $dest_dir);
      symlink("$relpath/$f", "$dest_dir/$f");
    }

    // See if we need to create a profiles dir or sites dir tree
    if(!file_exists("$dest_dir/sites")) {
      mkdir("$dest_dir/sites");
      mkdir("$dest_dir/sites/all");
      mkdir("$dest_dir/sites/default");
      mkdir("$dest_dir/sites/default/files");
    }
    // Copy over the default.settings.php file
    copy(
      "$source_dir/sites/default/default.settings.php",
      "$dest_dir/sites/default/default.settings.php"
    );

    // Link in the Drupal stock profiles
    if(!file_exists("$dest_dir/profiles")) {
      mkdir("$dest_dir/profiles");
    }

    // Try to link the existing Drupal profiles
    foreach($this->filesInDir("$source_dir/profiles") as $f) {
      if(is_link("$dest_dir/profiles/$f")) {
        unlink("$dest_dir/profiles/$f");
      }
      if(!file_exists("$dest_dir/profiles/$f")) {
        $relpath = $this->relpath("$source_dir/profiles", "$dest_dir/profiles");
        symlink("$relpath/$f", "$dest_dir/profiles/$f");
      }
    }

    // Return the core we just linked to
    return $core;
  }

  /**
   * Manage profiles
   *
   * @param string $dest_dir
   *  The destination dir to switch the profile for.
   * @param string $profile
   *  The profile to switch to.
   * @param boolean $force
   *  Whether or not to force the switch
   * @param string $filter
   *  The major core version to filter for
   *
   * @return string
   *  Returns the core it switched to.
   */
  // public function switchprofile($dest_dir = FALSE, $profile = FALSE, $force = FALSE, $filter = FALSE) {
  //   // Pull the base
  //   $base = $this->getBase();
  //   // Handle destination directory
  //   if (!$dest_dir) {
  //     $dest_dir = getcwd();
  //   }
  //   else {
  //     $dest_dir = realpath($dest_dir);
  //   }
  //   // Make sure this is a drupal base
  //   if (!$this->isDrupalDir($dest_dir) && !$force) {
  //     $this->last_error = 'Invalid Drupal Directory';
  //     return FALSE;
  //   }
  //
  //   // Get the profile if it wasn't specified on the CLI
  //   if (!$profile || !$this->isValidProfile($profile)) {
  //     $profile = $this->chooseProfile($filter);
  //   }
  //
  //   $source_profile_dir = $this->getBase() . "/profiles/" . $profile;
  //   $profiles_dir = $dest_dir . '/profiles/';
  //
  //   // Link it up
  //   if (is_dir($profiles_dir)) {
  //     // @TODO-matt: name of $profile.
  //     $profile_dir = $profiles_dir . '/' . $profile;
  //
  //     // Remove the current profiles/$profile directory if it's a link
  //     if (is_link($profile_dir)) {
  //       if ($this->isWindows()) {
  //         $target = readlink($profile_dir);
  //         if (is_dir($target)) {
  //           rmdir($profile_dir);
  //         }
  //         else {
  //           unlink($profile_dir);
  //         }
  //       }
  //       else {
  //         // We're a sane operating system, just remove the link
  //         unlink($profile_dir);
  //       }
  //     }
  //     else {
  //       // If there is a profiles/$profile directory which isn't a symlink we're going to be safe and error out
  //       if (file_exists($profile_dir)) {
  //         $this->last_error = 'The profile directory already exists and is not a symlink';
  //         return FALSE;
  //       }
  //     }
  //
  //     // Create our new symlink to the correct profile
  //     $profile_link_path = $this->relpath($source_profile_dir, $profiles_dir);
  //     symlink($profile_link_path, $profile_dir);
  //   }
  //   return $profile;
  // }

  /**
   * Manage a single profile
   */
  public function manageProfile($name, $version, $dir, $upgrade = FALSE) {
    // Bail if the profile isn't valid
    if (!$this->isValidProfile($name, $version)) {
      return FALSE;
    }

    // Set some path variables to make things easier
    $base = $this->base;
    $dest_profiles_dir = "$dir/profiles";
    $source_profile_dir = "$base/profiles/$name-$version";

    // See if the profile is already linked
    if(file_exists("$dir/profiles/$name") && !$upgrade) {
      // TODO add a method that checks to see if this is a link
      // and it's a link to our profiles base, if so, see if we're
      // in the upgrade business based on method options
      $this->last_error = "The profile '$name' is already linked to this site.";
      return FALSE;
    }

    // Relative path between the two profiles folders
    $relpath = $this->relpath("$base/profiles", "$dir/profiles");

    // Working symlink
    symlink("$relpath/$name-$version", "$dir/profiles/$name");

    return TRUE;
  }

  /**
   * Get the last error
   * @return
   *  Returns the last error message from $this->last_error
   */
  public function lastError() {
    return $this->last_error;
  }

  /**
   * Returns the dslm-base from $this->base
   *
   * @return string
   *  Return $this->base
   */
  protected function getBase() {
    // @todo replace all calls to $this->getBase() with a reference to the $this->base attribute
    // Base is now validated on instantiation, this is here for backward compatibility
    return $this->base;
  }

  /**
   * Takes an array of core or profile versions and sorts them by version number
   *
   * @param string $type
   *  Should be core or profile to determine which we're sorting. Defaults to core
   * @param array $v
   *  An array containing the versions to sort
   *
   * @return array
   *  Returns a sorted array by version
   */
  public function orderByVersion($type = 'core', $v) {
    // Initialize an array to hold keys for what to sort and values for the initial values
    $for_sorting = array();

    // Decide what we're sorting to get the sortable string as the key of $for_sorting
    if ($type == 'core') {
      foreach ($v as $version) {
        if (preg_match($this->core_regex, $version, $parsed)) {
          $for_sorting[strtolower($parsed[2])] = $version;
        }
      }
    }
    else {
      foreach ($v as $version) {
        $parsed = str_replace('.x-', '.', $version);
        $for_sorting[strtolower($parsed)] = $version;
      }
    }

    // Sort by the keys we put in place using the version_compare function
    uksort($for_sorting, 'version_compare');

    // Initialize the out array to restore an array of just original strings in the new order for return
    $out = array();
    foreach ($for_sorting as $k => $value) {
      $out[] = $value;
    }
    return $out;
  }

  /**
   * Core validation
   *
   * @param string $s
   *  The core string to validate
   *
   * @return array
   *  Returns an array from preg_match or FALSE
   */
  public function isCoreString($s) {
    if (preg_match($this->core_regex, $s, $matches)) {
      return $matches;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Profile validation
   *
   * @param string $s
   *  The profile string to validate
   *
   * @return array
   *  Returns an array from preg_match or FALSE
   */
  public function isProfileString($s) {
    if (preg_match($this->profile_regex, $s, $matches)) {
      return $matches;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Quick sanity check on the dslm base
   *
   * @param $base
   *  A string containing the base directory to check
   * @return FALSE or valid base
   *  Return FALSE or a validated base
   */
  protected function validateBase($base) {
    if (is_dir($base)) {
      $contents = $this->filesInDir($base);
      $check_for = array('profiles', 'cores');
      foreach ($check_for as $check) {
        if (!in_array($check, $contents)) {
          return FALSE;
        }
      }
      // If the checks didn't return FALSE, return TRUE here.
      return realpath($base);
    }

    // Default to return FALSE
    return FALSE;
  }

  /**
   * Internal function to remove symlinks back to a core
   * @param $d
   *  Directory to remove links from
   * @return
   *  Returns TRUE or FALSE
   */
  protected function removeCoreLinks($d) {
    // Iterate through the dir and try readlink, if we get a match, unlink
    $delim = $this->isWindows() ? "\\" : "/";
    if (!$d=realpath($d)) {
      return FALSE;
    }
    foreach ($this->filesInDir($d) as $f) {
      $full = realpath($d) . $delim . $f;
      if (is_link($full)) {
        // Read the target
        $target = readlink($full);
        // Pull the dirname
        $dirname = basename(dirname($target));
        // Check to make sure the dirname matches a core regex
        if ($this->isCoreString($dirname)) {
          if ($this->isWindows()) {
            $target = readlink($full);
            // Windows needs rmdir if it's a link to a directory
            if (is_dir($target)) {
              rmdir($full);
            }
            else {
              unlink($full);
            }
          }
          else {
            // We're a sane operating system, just remove the link
            unlink($full);
          }
        }
      }
    }
    return TRUE;
  }

  /**
   * Helper method to return the basename of the first symlink in a given directory
   *
   * @param string $d
   *  The directory to work in
   *
   * @return string or FALSE
   *  Returns the basename or FALSE
   */
  protected function firstLinkBasename($d) {
    if (!file_exists($d)) { return FALSE; }
    $d = realpath($d);
    foreach ($this->filesInDir($d) as $f) {
      $full = "$d/$f";
      if (is_link($full)) {
        $target = readlink($full);
        //$resolved = realpath("$d/$target");
        return basename($target);
      }
    }
    return FALSE;
  }

  /**
   * Helper method to return the dirname of the first symlink in a given directory
   *
   * @param string $d
   *  The directory to work in
   *
   * @return string or FALSE
   *  Returns the dirname or FALSE
   */
  protected function firstLinkDirname($d) {
    if (!file_exists($d)) {
      return FALSE;
    }
    $d = realpath($d);
    foreach ($this->filesInDir($d) as $f) {
      $full = "$d/$f";
      if (is_link($full)) {
        $target = readlink($full);
        //$resolved = realpath("$d/$target");
        return basename(dirname($target));
      }
    }
    return FALSE;
  }

  /**
   * Return an array of the files in a directory
   *
   * @param string $path
   *  The directory to search
   *
   * @return array
   *  Returns an array of the filenames in the given directory
   */
  protected function filesInDir($path) {
    $d = dir($path);
    $out = array();
    while (FALSE !== ($entry = $d->read())) {
      // Exclude . and ..
      if ($entry == '.' || $entry == '..') {
        continue;
      }
      $out[] = $entry;
    }
    $d->close();
    return $out;
  }

  /**
   * Internal function to get the core through interactive input
   *
   * @return string
   *  Returns the use chosen core
   */
  protected function chooseCore() {
    // Pull our cores
    $get_cores = $this->getCores();
    $cores = $get_cores['all'];

    // Present the cores to the user
    foreach ($cores as $k => $core) {
      print $k+1 . ". $core\n";
    }
    // Get the users's choice
    fwrite(STDOUT, "Choose a core: ");
    $core_choice = fgets(STDIN);

    // Return the chosen core
    return $cores[$core_choice-1];
  }

  /**
   * Internal function to get the profile through interactive input
   *
   * @param string $version_check
   *  Which major version to filter the choices by
   *
   * @return string
   *  Returns the user chosen profile
   */
  protected function chooseProfile($version_check = FALSE) {
    // Pull our profiles
    $get_profiles = $this->getProfiles();
    $profiles = $get_profiles['all'];

    // Version filtering
    if ($version_check) {
      preg_match('/-(\d+)\./', $version_check, $version_match);
      if (isset($version_match[1])) {
        $filtered_profiles = array();
        $version_filter = $version_match[1];
        // Now clean the profiles array
        foreach ($profiles as $k => $profile) {
          if (preg_match("/^$version_filter/", $profile)) {
            //unset($profiles[$k]);
            $filtered_profiles[] = $profile;
          }
        // This re-keys the array so the keys are sequential after the unset
        $profiles = $filtered_profiles;
        }
      }
    }

    // Print the list that has already been filtered if necessary
    foreach ($profiles as $k => $profile) {
      print $k+1 . ". $profile\n";
    }
    // Get user input
    fwrite(STDOUT, "Choose a profile: ");
    $profile_choice = fgets(STDIN);

    // Return the chosen profile
    return $profiles[$profile_choice-1];
  }

  /**
   * Internal function to verify a directory is a drupal base
   *
   * @param string $d
   *  The directory to check
   *
   * @return boolean
   *  Returns boolean for whether the directory is a valid drupal dir or not
   */
  protected function isDrupalDir($d) {
    if (!file_exists($d)) {
      return FALSE;
    }
    $d = realpath($d);
    $files = $this->filesInDir($d);
    $checks = array(
      'install.php',
      'update.php',
      'cron.php',
    );
    foreach ($checks as $check) {
      if (!in_array($check,$files)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Fetch the relative path between two absolute paths
   * NOTE: Relative paths aren't supported by symlink() in PHP on Windows
   *
   * @param string $dest
   *  The destination absolute path
   * @param string $root
   *  The root absolute path
   * @param string $dir_sep
   *  The directory separator, defaults to unix /
   *
   * @return string
   *  Returns the relative path
   */
  protected function relpath($dest, $root = '', $dir_sep = '/') {

    // Relative paths aren't supported by symlink() in Windows right now
    // If we're windows, just return the realpath of $path
    // This is only a limitation of the PHP symlink, I don't want to do an exec to mklink
    // because that breaks in the mysysgit shell, which is possibly where many Drush
    // users will be working on Windows.
    if ($this->isWindows()) {
      return realpath($dest);
    }

    $root = explode($dir_sep, $root);
    $dest = explode($dir_sep, $dest);
    $path = '.';
    $fix = '';
    $diff = 0;
    for ($i = -1; ++$i < max(($rC = count($root)), ($dC = count($dest)));) {
      if( isset($root[$i]) and isset($dest[$i])) {
        if ($diff) {
          $path .= $dir_sep. '..';
          $fix .= $dir_sep. $dest[$i];
          continue;
        }
        if ($root[$i] != $dest[$i]) {
          $diff = 1;
          $path .= $dir_sep. '..';
          $fix .= $dir_sep. $dest[$i];
          continue;
        }
      }
      elseif (!isset($root[$i]) and isset($dest[$i])) {
        for ($j = $i-1; ++$j < $dC;) {
          $fix .= $dir_sep. $dest[$j];
        }
        break;
      }
      elseif (isset($root[$i]) and !isset($dest[$i])) {
        for ($j = $i-1; ++$j < $rC;) {
          $fix = $dir_sep. '..'. $fix;
        }
        break;
      }
    }
    return $path. $fix;
  }

  /**
   * Determine if we're MS Windows
   *
   * I was able to resist the urge not to name this method isBrokenOs()
   * but not the urge to put the idea in this comment
   *
   * @return boolean
   *  For whether we're windows or not.
   */
  protected function isWindows() {
    return preg_match('/^win/i',PHP_OS);
  }
}
