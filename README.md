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

If this is an existing database and the original template slug was changed before the stable-key migration, complete the adoption workflow below before running the template seeder.

Add the initial public layout template to a local database:

```sh
ddev artisan db:seed --class='Database\Seeders\TemplateSeeder'
```

This command tracks its initial template with an internal stable identifier, so later edits to its name or slug are preserved on reruns. The migration adds that identifier to existing databases and adopts the original `editorial-default` template when it is present. Template records are not created automatically during application startup.

If you already had the initial template and changed its slug before applying the stable-key migration, identify and adopt that row before running the seeder. This is a CLI-only local developer operation, not a template-admin UI action. Inspect the rows in Tinker:

```sh
ddev artisan tinker
```

At the Tinker prompt, list IDs and current fields, then use your project history and the Superblogs that use the template to identify the intended legacy row. Do not choose a row based only on its editable name or slug:

```php
\App\Models\Template::query()->orderBy('id')->get(['id', 'name', 'slug', 'layout', 'seed_key'])->toArray();
```

Run the adoption command with that row's database ID. It displays the selected row, asks for confirmation, and fails if the ID does not exist or the key is already assigned elsewhere:

```sh
ddev artisan superblog:adopt-initial-template 42
```

Replace `42` with the ID you verified. Confirm the displayed row, then verify it has `seed_key` equal to `initial-editorial` in Tinker before running `ddev artisan db:seed --class='Database\Seeders\TemplateSeeder'`. The adoption command is restricted to `local` and `testing` environments. If the key is already assigned to a different row, stop and resolve that local database state deliberately; the command will not move or overwrite it.

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

After migrating a fresh local database, create your own initial account with the interactive, CLI-only command:

```sh
ddev artisan superblog:create-super-admin
```

Enter your own name, email, and password at the prompts. The password prompts do not echo input, require confirmation, and the account is stored with a hashed password. The command approves this first account as a super admin and refuses to create an account if any super admin already exists. Each developer should use a separate local database and their own account; do not share accounts or passwords.

The existing `superblog:make-super-admin {email}` command remains available to approve and promote an already existing account when appropriate. There is no password-based public registration. A first-time verified Google identity may explicitly request a normal pending staff account; it receives no admin access until approved.

## Google sign-in and existing accounts

The login page uses Google sign-in. Configure a Google OAuth **Web application** client and add the exact callback URL for this app, for example `https://<your-ddev-host>/auth/google/callback`. Add these values to the ignored local `.env` or your deployment secret store; never commit real values:

```dotenv
GOOGLE_CLIENT_ID=your-client-id
GOOGLE_CLIENT_SECRET=your-client-secret
GOOGLE_REDIRECT_URI=https://your-ddev-host/auth/google/callback
```

Google login identifies accounts by the stable Google OpenID Connect subject (`sub`), never by email alone. To map the existing bootstrap super-admin or a seeded staff account, first sign in with the intended Google account. If it is not yet linked, the setup page shows its verified email and subject. Verify that identity with the account owner through a trusted channel. The subject is an identifier, not a password or token. Find the intended existing user's database ID with Tinker, checking the account's role and approval state:

```sh
ddev artisan tinker
```

```php
\App\Models\User::query()->orderBy('id')->get(['id', 'name', 'email', 'approval_status', 'is_super_admin', 'google_subject'])->toArray();
```

Map the initial super-admin using that exact user ID and the verified subject from the setup page. The command confirms the selected record without displaying the subject, and requires the target to be approved and the only super-admin account:

```sh
ddev artisan superblog:map-google-identity 42 'replace-with-verified-subject' --initial-super-admin
```

Replace `42` and the quoted example subject with the actual verified values; the example is not a real account.

For an existing staff account, use its verified database ID and subject without the super-admin option:

```sh
ddev artisan superblog:map-google-identity 17 'replace-with-verified-subject'
```

Replace both example values with the intended staff record ID and verified subject.

The command refuses missing users, existing mappings, duplicate subjects, and incorrect super-admin targets. It changes only the Google subject field; it does not alter approval, roles, flags, password hashes, or assignments. If a new Google identity is not linked and does not conflict with an existing email, the user can explicitly create a pending staff account from the setup page. An email conflict requires administrator mapping; matching email never performs an automatic link.

Email/password hashes and the password-login handler are retained. The password-login route is registered consistently so route caching cannot capture the wrong environment; request-time middleware allows it only in `local` and `testing`, and returns 404 in other environments. Production users see Google sign-in only. Google login does not approve accounts: pending users still need super-admin approval, and staff still need Superblog assignments.

## Test staff approval locally

In a local DDEV database, create the development-only pending staff account separately from the template seed:

```sh
ddev artisan db:seed --class='Database\Seeders\StaffTestSeeder'
```

For a newly created account, log in with:

- Email: `staff@example.com`
- Password: `Superblog-Staff-Local-2026!`

The seeder is allowed only in `local` and `testing` environments. It fails in production and other environments. It creates the account as pending, with no super-admin or all-Superblogs privileges. If `staff@example.com` already exists, the seeder leaves its name, password, status, and permissions unchanged. The password above is public development-only test data; never use it outside a disposable local environment.

To exercise the workflow:

1. Log in with your own super-admin account and open **Staff Management**. Approve `staff@example.com`.
2. Log out and log in as the test staff account. Approval allows entry to the admin area, but does not grant access to any Superblog by itself. Staff see only Superblogs assigned to them.
3. To assign one existing test Superblog, use `ddev artisan tinker` and run the following, replacing the slug with a Superblog you are comfortable using for local testing:

   ```php
   $staff = \App\Models\User::where('email', 'staff@example.com')->firstOrFail();
   $blog = \App\Models\Superblog::where('slug', 'your-test-superblog')->firstOrFail();
   $blog->staff()->syncWithoutDetaching([$staff->id]);
   ```

   The staff dashboard should show that assigned Superblog only; an unassigned Superblog's edit or delete URL remains forbidden. The current admin UI does not manage assignments, so this targeted Tinker step is needed for a positive assignment test.
4. From **Staff Management**, reject the test account. Its next protected admin request should receive the access-revoked response. Approve it again when you want to resume other tests.

For a fresh approval cycle without clearing the database, intentionally reset only this test account in Tinker. This restores the documented test password, returns it to pending, removes global privileges, and removes only its Superblog assignments:

```php
$staff = \App\Models\User::where('email', 'staff@example.com')->firstOrFail();
$staff->password = \Illuminate\Support\Facades\Hash::make('Superblog-Staff-Local-2026!');
$staff->approval_status = 'pending';
$staff->is_super_admin = false;
$staff->has_all_superblogs_access = false;
$staff->save();
$staff->assignedSuperblogs()->detach();
```

Do not use `migrate:fresh` or delete the DDEV database to reset this test account. The seed command is intentionally non-destructive and will not reset an existing account.

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
