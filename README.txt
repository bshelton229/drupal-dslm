DESCRIPTION
-----------
DSLM is a set of Drush commands for managing symlinking Drupal 
sites back to a central set of Drupal cores and distributions.


REQUIREMENTS
------------
* Drush


DSLM-BASE
---------
The DSLM base folder must contain both a cores and dists sub-folder.
The cores and dists folders contain Drupal cores and sites/all
distributions compatible with the cores. Like this:

    -- dslm_base
        -- cores
            -- drupal-6.18
            -- drupal-6.20
            -- drupal-7.0
        -- dists
            -- 6.x-1.0
            -- 6.x-1.1
            -- 7.x-1.0
            -- 7.x-1.1
                -- modules
                -- themes
                -- libraries

Once your base is set up as described above, you'll need to pass it to
drush dslm in order to run most commands. There are three ways to set the
location of your base.

The base can be set using any of the options below. It will first look for
the cli switch, then in your drushrc.php and finally for an enviro var.
 - The cli switch --dslm-base=base
 - The drushrc.php file $conf['dslm_base'] = base;
 - The dslm_BASE system environment variable


COMMANDS
--------
drush dslm-new [site-directory]
Will create a new site prompting you to choose which core and distribution it should be linked to. If you pass the --latest flag, the latest core and distribution will automatically be chosen

drush dslm-info
Will display the current core and distribution linked to the directory you're in.

drush dslm-switch [core] [dist]
Will prompt you to switch the core and distribution. If you specify a valid core and dist on the command line, they will be used, otherwise, you will be prompted.

drush dslm-switch-core [core]
Will prompt you to switch the core. If you specify a valid core on the command line, it will be used, otherwise, you will be prompted.

drush dslm-switch-dist [dist]
Will prompt you to switch the dist. If you specify a valid dist, it will be used, otherwise, you will be prompted.
