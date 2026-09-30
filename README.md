# Superblog

A WordPress magazine project with a custom block theme and a plugin for RSS and AI publishing features.

## Project structure

- `.ddev/` — shared local development environment configuration.
- `web/wp-content/themes/superblog-theme/` — site presentation and block theme.
- `web/wp-content/plugins/superblog-engine/` — RSS, AI pipeline, and publishing functionality.
- `web/` — local WordPress document root. WordPress core files are installed here but are not committed to Git.

## Local setup

Requirements: Git, Docker Desktop, and DDEV.

From the project folder:

1. Start the local environment:

   ddev start
   

2. Download the project’s pinned WordPress version:
   
   ddev wp core download --version=7.1.2


3. Open the local site and complete WordPress’s setup in your browser:
   
   ddev launch
   
   The local site URL is http://superblog.ddev.site. Choose your own local administrator username and password.


4. In the WordPress dashboard, activate Superblog Theme under Appearance → Themes and Superblog Engine under Plugins → Installed Plugins.
WordPress core, the local database, and local credentials stay on each developer’s computer. Do not commit API keys or other secrets.



Git workflow

- main is the stable branch.
- dev is the shared integration branch.
- Create feature branches from dev and merge changes through reviewed pull requests.

