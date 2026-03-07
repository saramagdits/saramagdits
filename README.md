# saramagdits.com

Personal portfolio and distributed web project built on Drupal 10.

**Stack**: Drupal 10.4, PHP 8.2, MySQL 8.0, Composer 2, Drush 12

## Prerequisites

- [OrbStack](https://orbstack.dev/) — Docker runtime for macOS (replaces Docker Desktop)
- [DDEV](https://ddev.readthedocs.io/en/stable/) — local development environment

Install with Homebrew:

```bash
brew install orbstack
brew install ddev/ddev/ddev
```

## Local setup

1. Clone the repo:

    ```bash
    git clone https://github.com/saramagdits/saramagdits.git
    cd saramagdits
    ```

2. Start DDEV:

    ```bash
    ddev start
    ```

3. Install dependencies:

    ```bash
    ddev composer install
    ```

4. Install Drupal (fresh install) or import a database dump:

    ```bash
    # Fresh install
    ddev drush site-install standard --account-name=admin --account-pass=admin -y

    # Or import a dump
    ddev import-db --file=path/to/dump.sql.gz
    ```

5. Run any pending updates and clear caches:

    ```bash
    ddev drush updb -y
    ddev drush cr
    ```

6. Open the site:

    ```bash
    ddev launch
    ```

    Or navigate to `https://saramagdits.ddev.site`

## Common commands

```bash
ddev drush <command>       # Run Drush commands
ddev composer <command>    # Run Composer commands
ddev describe              # Show project info and URLs
ddev stop                  # Stop the environment
ddev poweroff              # Stop all DDEV projects
```

## Project structure

```
web/                    Drupal docroot
web/modules/custom/     Custom modules
web/themes/custom/      Custom themes
web/sites/default/      Site configuration and settings
config/                 Exported Drupal configuration (sync)
drush/                  Drush configuration
infrastructure/         AWS CDK infrastructure code
```

## Configuration

Drupal configuration is exported to `config/` and managed via `drush config-export/import`. After importing a database, sync configuration:

```bash
ddev drush cim -y
ddev drush cr
```
