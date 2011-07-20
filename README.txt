
DESCRIPTION
-----------
DSLM is a set of Drush commands for managing symlinking Drupal 
sites back to a central set of Drupal cores and distributions.

The DSLM base folder must contain both a cores and dists sub-folder. The cores and dists folders contain Drupal cores and sites/all distributions compatible with the cores. Like this:

    -- dslm_base
        -- cores
            -- drupal-6.18
            -- drupal-6.20
            -- drupal-7.0
        -- dists
            -- 6.x-1.0
            -- 6.x-1.1
            -- 7.x-1.0
            -- 7.x-1


REQUIREMENTS
------------
* Drush


