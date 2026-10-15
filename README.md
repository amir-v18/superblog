# Superblog

Superblog is a Laravel application for managing multiple content websites. The shared local environment uses DDEV, MySQL, and Vite.

## Prerequisites

Install these on your computer:

- Git
- Docker Desktop, running before you start DDEV
- [DDEV](https://ddev.com/get-started/)

DDEV provides PHP 8.5, Composer 2, Node.js 24, and MySQL 8.4 for this project. You do not need to install these versions separately on your computer.

## Set up a new local copy

Clone the repository, then enter its directory:

```sh
git clone <repository-url> superblog
cd superblog
```

Start the project environment:

```sh
ddev start
```

For the Laravel project type, DDEV creates or updates the ignored local `.env` with the connection settings for its database. The tracked `.env.example` remains unchanged. Do not copy local credentials into tracked files.

Install PHP and JavaScript dependencies from their lockfiles:

```sh
ddev composer install
ddev npm ci
```

For a new local copy, generate an application key and initialize its empty DDEV database:

```sh
ddev artisan key:generate
ddev artisan migrate
```

`migrate` applies outstanding migrations and preserves existing tables and data. Before running it against a DDEV database that already contains data, inspect that database and its migration status. Do not use `migrate:fresh` to set up the project; it drops existing tables.

Build the frontend assets:

```sh
ddev npm run build
```

Open the site:

```sh
ddev launch
```

DDEV also shows the project URL with:

```sh
ddev describe
```

## Developer accounts and super-admin access

The application has no registration page, account-creation command, or account-creating seeder. The database seeder intentionally creates no users. Ask the project administrator to provision an individual account through an approved process; do not share accounts or passwords.

Once that account exists in the local database, promote it explicitly:

```sh
ddev artisan superblog:make-super-admin developer@example.com
```

The command asks for confirmation, then approves that existing account and grants super-admin access. It cannot create the account. **First-account provisioning is currently a project setup gap:** there is no documented, supported way in this repository to create the initial user. Resolve that gap with the project owner before onboarding developers; this README does not invent a credential or account-creation workaround.

## Stop DDEV

Stop the project containers while keeping their database volume:

```sh
ddev stop
```

Stopping DDEV does not delete the database. Avoid DDEV delete/reset commands unless you have intentionally backed up and reviewed the data they affect.

## Troubleshooting

- **DDEV cannot connect to Docker:** start Docker Desktop and confirm it is available to your user, then retry `ddev start`.
- **The site does not open:** check `ddev describe` for the project URL and service state.
- **Dependencies report a platform mismatch:** run `ddev composer check-platform-reqs`; Composer and Node should be run through DDEV so their configured versions are used.
- **Database connection errors:** confirm DDEV is running and review your ignored local `.env`. Do not replace it with shared credentials or change `.env.example` to fix a local connection.
- **A port is already in use:** inspect DDEV's output and `ddev describe` for a port conflict before changing configuration.
- **An existing local database is present:** inspect it and back it up before applying migrations. Setup instructions never require wiping it.
