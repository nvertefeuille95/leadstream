# Development Setup

## Prerequisites

Installed via Scoop on Windows:

- PHP 7.4+ (`scoop install php`)
- Composer (`scoop install composer`)
- Git (`scoop install git`)
- GitHub CLI (`scoop install gh`)

## Local WordPress site

1. Install [Local by Flywheel](https://localwp.com/).
2. Create a new site:
   - **Site name:** LeadStream
   - **Local domain:** `leadstream.local`
   - **Environment:** Preferred (PHP 8.1+, nginx, MySQL 8)
   - **WordPress admin:** your choice
3. Enable debug flags in `wp-config.php` under the site's `app/public/`:
   ```php
   define( 'WP_DEBUG', true );
   define( 'WP_DEBUG_LOG', true );
   define( 'WP_DEBUG_DISPLAY', false );
   define( 'SAVEQUERIES', true );
   define( 'SCRIPT_DEBUG', true );
   ```

## Link the plugin into the Local site

From Git Bash or PowerShell:

```bash
# Windows path to Local's plugins dir (adjust the site folder name as needed)
ln -s /c/Users/User/Projects/leadstream/plugin "/c/Users/User/Local Sites/leadstream/app/public/wp-content/plugins/leadstream"
```

If symlinks are blocked, copy the folder instead (and re-copy after every change), or enable Developer Mode in Windows Settings.

## Install PHP dependencies

```bash
cd /c/Users/User/Projects/leadstream/plugin
composer install
```

## Run tests and lint

```bash
vendor/bin/phpunit
vendor/bin/phpcs
```

## Activate

1. Open the Local site's WP admin.
2. **Plugins** → activate **LeadStream by Timberbrook Marketing**.
3. Tail `app/public/wp-content/debug.log` for any warnings.
