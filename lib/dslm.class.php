<?php
/**
 * Drupal DSLM
 * A PHP library to handle a central Drupal symlink structure
 */ 
class Dslm {
  // Attributes
  protected $base = FALSE;
  protected $last_error = '';
  protected $skip_dir_check = FALSE;
  
  /**
   * DSLM constructor
   * @param $base
   *  The base path containing the dists and cores
   */
  public function __construct($base) {
    // @TODO: add base validation
    $this->base = $base;
    return TRUE;
  }
  
  /**
   * Set the skip_dir_check attribute
   */
  public function setSkipDirCheck($in = TRUE) {
    $this->skip_dir_check = (boolean) $in;
  }

  /**
   * Get the Drupal cores
   */
  public function get_cores($major = FALSE) {
    $out = array();
    foreach($this->files_in_dir($this->get_base() . "/cores/") as $core) {
      if($this->is_core_string($core))
        $out[] = $core;
    }
    return $this->order_by_version('core', $out);
  }
  
  /**
   * Get the Drupal dists
   */
  public function get_dists($major=FALSE) {
    $out = array();
    foreach($this->files_in_dir($this->get_base() . "/dists/") as $dist) {
      if($this->is_dist_string($dist))
        $out[] = $dist;
    }
    return $this->order_by_version('dist', $out);
  }
  
  /**
   * Return the latest version core and dist
   */
  public function latest() {
    $core = $this->order_by_version('core', $this->get_cores());
    $dist = $this->order_by_version('dist', $this->get_dists());
    return array(
      'core' => $core[count($core)-1],
      'dist' => $dist[count($dist)-1],
    );
  }

  /**
   * Check core
   */
  public function is_valid_core($core) {
    return in_array($core, $this->get_cores());
  }
  
  /**
   * Check dist
   */
  public function is_valid_dist($dist) {
    return in_array($dist, $this->get_dists());
  }
  
  /**
   * Fucntion to return site info
   */
  public function siteInfo($d=FALSE) {
    if(!$d) { $d = getcwd(); }
    if(!$this->is_drupal_dir($d)) {
      $this->last_error = 'This directory isn\'t a Drupal dir';
      return FALSE;      
    }
    $core = $this->first_link_dirname($d);
    $dist = $this->first_link_basename($d.'/sites');
    if(!$core || !$dist) {
      $this->last_error = 'Invalid symlinked site';
      return FALSE;
    }
    $out = array(
      'core' => $core,
      'dist' => $dist,
    );
    return $out;
  }
  
  /**
   * Create a new drupal site
   */
  public function new_site($dest_dir, $core=FALSE, $dist=FALSE, $force=FALSE) {    
    // Load the base
    $base = $this->get_base();
    
    // Dest Directory creation and validation
    // TODO: Much more validation needed here, wire in checking for empty, etc.
    if(file_exists($dest_dir) && !$force) {
      $this->last_error = "The directory already exists";
      return FALSE;
    }

    // Run the dist and core switches
    $core = $this->switch_core($dest_dir, $core, TRUE);
    $dist = $this->switch_dist($dest_dir, $dist, TRUE, $core);
    
    // Create sites/default structure
    $dest_sites_default = "$dest_dir/sites/default";
    if(!file_exists($dest_sites_default)) {
      mkdir($dest_sites_default);
      mkdir("$dest_sites_default/files");
      copy(
        "$base/cores/$core/sites/default/default.settings.php", 
        "$dest_sites_default/default.settings.php"
      );
    }

    // Break here for testing right now
    return TRUE;
  }
  
  public function switch_core($dest_dir=FALSE, $core=FALSE, $force=FALSE) {
    // Pull the base
    $base = $this->get_base();
    
    // Handle destination directory
    if(!$dest_dir) {  
      $dest_dir = getcwd(); 
    }
    elseif(file_exists($dest_dir)) {
      $dest_dir = realpath($dest_dir);
    }
    
    // Make sure this is a drupal base
    if(!$this->is_drupal_dir($dest_dir) && !$force) {
      $this->last_error = 'Invalid Drupal Directory';
      return FALSE;
    }
    // Get the core if it wasn't specified on the CLI
    if(!$core || !in_array($core, $this->get_cores())) {
      $core = $this->choose_core();
    }
    
    // They've had the option to cancel when choosing a core
    // If at this point the dest_dir doesn't exit and we're forcing,
    // let's try to create it
    if(!file_exists($dest_dir) && $force) { 
      mkdir($dest_dir);
      $dest_dir = realpath($dest_dir); 
    }
      
    $source_dir = "$base/cores/$core";
    $this->remove_all_links($dest_dir);
    foreach($this->files_in_dir($source_dir) as $f) {
      // Never link sites
      if($f == "sites")
        continue;
      $relpath = $this->relpath($source_dir, $dest_dir);
      symlink("$relpath/$f", "$dest_dir/$f");
    }
    return $core;
  }
  
  /**
   * Switch the distribution
   * @param $dest_dir
   *  Option specify the destination directory
   */
  public function switch_dist($dest_dir = FALSE, $dist = FALSE, $force = FALSE, $filter = FALSE) {
    // Pull the base
    $base = $this->get_base();
    // Handle destination directory
    if(!$dest_dir) { 
      $dest_dir = getcwd(); 
    }
    else {
      $dest_dir = realpath($dest_dir);
    }
    // Make sure this is a drupal base
    if(!$this->is_drupal_dir($dest_dir) && !$force) {
      $this->last_error = 'Invalid Drupal Directory';
      return FALSE;  
    }
    // Get the core if it wasn't specified on the CLI
    if(!$dist || !in_array($dist, $this->get_dists())) {
      $dist = $this->choose_dist($filter);
    }
      
    $source_dist_dir = $this->get_base() . "/dists/$dist";
    $sites_dir = $dest_dir . '/sites';
    
    // If the sites dir doesn't exist, create it
    if(!file_exists($sites_dir)) { mkdir($sites_dir); }

    // Link it up
    if(is_dir($sites_dir)) {
      // Define the sites/all directory
      $sites_all_dir = $sites_dir . "/" . "all";

      // Remove the current sites/all directory if it's a link
      if(file_exists($sites_all_dir)) {
        if(is_link($sites_all_dir)) {
          if($this->isWindows()) {
            $target = readlink($sites_all_dir);
            if(is_dir($target)) {
              rmdir($sites_all_dir);
            }
            else {
              unlink($sites_all_dir);
            }
          }
          else {
            // We're a sane operating system, just remove the link
            unlink($sites_all_dir);
          }
        }
        else {
          // If there is a sites/all directory which isn't a symlink we're going to be safe and error out
          $this->last_error = 'The sites/all directory already exists and is not a symlink';
          return FALSE;
        }
      }
      
      // Create our new symlink to the correct dist
      $dist_link_path = $this->relpath($source_dist_dir, $sites_dir);
      symlink($dist_link_path, $sites_all_dir);
    }
    return $dist;
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
   * Internal Functions
   */
  
  /**
   * Clean up the $base variable and return it or FALSE
   */
  protected function get_base() {
    // NOTE: Evaluate ^~ to $_SERVER['HOME'] if it's defined
    $base = $this->base;
    // PHP doesn't resolve ~ as the home directory
    if(isset($_SERVER['HOME'])) {
      $base = preg_replace('/^\~/', '/Users/bshelton', $base);
    }
    // Eventually we'll put validation here
    return realpath($base);
  }
  
  /**
   * Internal function to remove all symlinks from a directory
   */
  protected function remove_all_links($d) {
    // Iterate through the dir and try readlink, if we get a match, unlink
    $delim = $this->isWindows() ? "\\" : "/";
    if(!$d=realpath($d)) { return FALSE; }
    foreach($this->files_in_dir($d) as $f) {
      $full = realpath($d) . $delim . $f;
      if(is_link($full)) { 
        if($this->isWindows()) {
          $target = readlink($full);
          if(is_dir($target)) {
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
    return TRUE;
  }
  
  // Helper function to return the first dslm needed name from a file
  protected function first_link_basename($d) {
    if(!file_exists($d)) { return FALSE; }
    $d = realpath($d);
    foreach($this->files_in_dir($d) as $f) {
      $full = "$d/$f";
      if(is_link($full)) {
        $target = readlink($full);
        //$resolved = realpath("$d/$target");
        return basename($target);
      }
    }
    return FALSE;
  }
  
  // Helper function to return the first dslm needed name from a file
  protected function first_link_dirname($d) {
    if(!file_exists($d)) { return FALSE; }
    $d = realpath($d);
    foreach($this->files_in_dir($d) as $f) {
      $full = "$d/$f";
      if(is_link($full)) {
        $target = readlink($full);
        //$resolved = realpath("$d/$target");
        return basename(dirname($target));
      }
    }
    return FALSE;
  }

  /**
   * Return an array of the files in a directory
   */
  protected function files_in_dir($path) {
    $d = dir($path);
    $out = array();
    while(FALSE !== ($entry = $d->read())) {
      // Exclude . and ..
      if($entry == '.' || $entry == '..') { continue; }
      $out[] = $entry;
    }
    $d->close();
    return $out;
  }
  
  /**
   * Internal function to get the core through CLI input
   */
  protected function choose_core() {
    // Pull our cores
    $cores = $this->get_cores();
    // Present the cores to the user
    foreach($cores as $k => $core) {
      print $k+1 . ". $core\n";
    }
    // Get the users's choice
    fwrite(STDOUT, "Choose a core: ");
    $core_choice = fgets(STDIN);

    // Return the chosen core
    return $cores[$core_choice-1];
  }
  
  /**
   * Internal function to get the distribution through CLI input
   */
  protected function choose_dist($version_check=FALSE) {
    // Pull our distributions
    $dists = $this->get_dists();
    // Version filtering
    if($version_check) {
      preg_match('/-(\d+)\./', $version_check, $version_match);
      if(isset($version_match[1])) { 
        $filtered_dists = array();
        $version_filter = $version_match[1];
        // Now clean the dists array
        foreach($dists as $k => $dist) {
          if(preg_match("/^$version_filter/", $dist)) {
            //unset($dists[$k]);
            $filtered_dists[] = $dist;
          }
        // This re-keys the array so the keys are sequential after the unset
        $dists = $filtered_dists;
        }
      }
    }
    
    // Print the list that has already been filtered if necessary
    foreach($dists as $k => $dist) {
      print $k+1 . ". $dist\n";
    }
    // Get user input
    fwrite(STDOUT, "Choose a dist: ");
    $dist_choice = fgets(STDIN);

    // Return the chosen dist
    return $dists[$dist_choice-1];
  }
  
  /**
   * Internal function to verify a directory is a drupal base
   */
  protected function is_drupal_dir($d) {
    if(!file_exists($d)) { return FALSE; }
    $d = realpath($d);
    $files = $this->files_in_dir($d);
    $checks = array(
      '.htaccess',
      'CHANGELOG.txt',
      'COPYRIGHT.txt',
      'install.php',
      'update.php',
      'cron.php',
    );
    foreach($checks as $check) { if(!in_array($check,$files)) { return FALSE; } }
    return TRUE;
  }

  /**
   * Fetch the relative path between two paths
   * Relative paths aren't supported by symlink() in PHP on Windows
   */
  protected function relpath( $path, $compareTo ) {
    
    // Relative paths aren't supported by symlink() in Windows right now
    // If we're windows, just return the realpath of $path
    // This is only a limitation of the PHP symlink, I don't want to do an exec to mklink
    // because that breaks in the mysysgit shell, which is possibly where many Drush
    // users will be working on Windows.
    if($this->isWindows()) {
      return realpath($path);
    }

    // clean arguments by removing trailing and prefixing slashes
    if ( substr( $path, -1 ) == '/' ) {
      $path = substr( $path, 0, -1 );
    }
    if ( substr( $path, 0, 1 ) == '/' ) {
      $path = substr( $path, 1 );
    }

    if ( substr( $compareTo, -1 ) == '/' ) {
      $compareTo = substr( $compareTo, 0, -1 );
    }
    if ( substr( $compareTo, 0, 1 ) == '/' ) {
      $compareTo = substr( $compareTo, 1 );
    }

    // simple case: $compareTo is in $path
    if ( strpos( $path, $compareTo ) === 0 ) {
      $offset = strlen( $compareTo ) + 1;
      return substr( $path, $offset );
    }

    $relative  = array(  );
    $pathParts = explode( '/', $path );
    $compareToParts = explode( '/', $compareTo );

    foreach( $compareToParts as $index => $part ) {
      if ( isset( $pathParts[$index] ) && $pathParts[$index] == $part ) {
        continue;
      }
      $relative[] = '..';
    }

    foreach( $pathParts as $index => $part ) {
      if ( isset( $compareToParts[$index] ) && $compareToParts[$index] == $part ) {
        continue;
      }
      $relative[] = $part;
    }

    return implode( '/', $relative );
  }

  /**
   * Returns boolean, are we windows?
   */
  protected function isWindows() {
    return preg_match('/^win/i',PHP_OS);
  }

  /**
   * Do a version compare and return the latest
   * $type = dist or core, defaults to core
   */
  protected function order_by_version($type='core', $v) {
    // The core_sort function
    if(!function_exists("core_sort")) {
      function core_sort($a,$b) {
        preg_match('/-([\d|\.]+)/', $a, $a_match);
        preg_match('/-([\d|\.]+)/', $b, $b_match);
        // If we don't have two matches, return 0
        if(!$a_match && !$b_match) {
          return 0;
        }
        // Version compare the two matches we have from the Drupal verisons
        return version_compare($a_match[1],$b_match[1]);
      }      
    }

    // The dist sort function
    if(!function_exists("dist_sort")) {
      function dist_sort($a,$b) {
        $a_match = str_replace('.x-', '.', $a);
        $b_match = str_replace('.x-', '.', $b);
        // If we don't have two matches, return 0
        if(!$a_match && !$b_match) {
          return 0;
        }
        // Version compare the two matches we have from the Drupal verisons
        return version_compare($a_match,$b_match);
      }      
    }
    
    // Sort the version array with our custom compare function
    $sort_function = ($type=='core') ? "core_sort" : "dist_sort";
    usort($v, $sort_function);
    return $v;
  }
  
  // Check the core against a regular expression
  protected function is_core_string($s) {
    return preg_match('/(.+)\-[\d+]\./', $s);
  }
  
  // Check the dist against a regular expression
  protected function is_dist_string($s) {
    return preg_match('/([\d+])\.x\-[\d+]/', $s);
  }
}
