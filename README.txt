DSLM - Drupal Symlink Manager
=============================
DSLM is a set of Drush commands for managing symlinking Drupal sites back to a central set of Drupal cores and installation profiles.


Dependencies
============
 - Drush: http://drupal.org/project/drush


Configuration and Installation
==============================
The first thing you'll want to do is set up your DSLM base folder. The DSLM base folder must contain, at the very least,  a "cores" directory which contains direct checkouts of drupal core. You may also add a "profiles" directory to hold any shared installation profiles you might want to use. Profiles and Cores must be suffixed with a version number, like this:

-- dslm_base
  -- cores
    -- drupal-6.18
    -- drupal-6.20
    -- drupal-7.12
  -- profiles
    -- myInstallProfile-6.x-1.0
    -- myInstallProfile-6.x-1.1
    -- myInstallProfile-7.x-1.0
    -- myInstallProfile-7.x-1.1
      -- myInstallProfile.profile
      -- modules
      -- themes
      -- libraries

Once your base is set up as described above, you'll need to pass it to drush dslm in order to run commands. There are three ways to set the location of your base.

The base can be set using any of the options below. It will first look for the cli switch, then in your drushrc.php and finally for an enviro var.

- The cli switch --dslm-base=/path/to/dslm_base
- The drushrc.php file $conf['dslm_base'] = /path/to/dslm_base;
- The DSLM_BASE system environment variable

DSLM Commands
=============
drush dslm-new [site-directory] [core]
Will create a new site prompting you to choose which core it should be linked to. If you pass the --latest flag, the latest core will automatically be chosen. You may optionally pass a valid core on the command line to run non-interactively (ie "drush dslm-new newSite drupal-7.12")

drush dslm-info
Will display the current core and any managed profiles linked to the directory you're in.

drush dslm-switch-core
Will prompt you to switch the core. If you specify a valid core on the command line, it will be used, otherwise, you will be prompted.

drush dslm-add-profile
Will prompt you to add a managed installation profile. If you specify a valid profile on the command line, it will be used, otherwise, you will be prompted.
