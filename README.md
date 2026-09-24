# Farsky Ples Event Reservation

This is a simple reservation system for the purposes of the Parish Ball in Cierna Voda, Slovakia.
Under the hood it's a Laravel app with Vue.js frontend via Inertia.js.

## Requirements
- PHP 8.4
- Composer
- Node.js 22+
- NPM
- MySQL

## Installation
- Clone the repository
- Run `composer install` to install PHP dependencies.
- Run `npm install` to install JavaScript dependencies.
- Copy `.env.example` to `.env` and configure your database and other settings.
- Run `php artisan key:generate` to generate the application key.
- Run `php artisan migrate:fresh` to create the database schema.
- Run `npm run dev` to start the development server.
- Run `php artisan serve` to start the Laravel server.
- Access the application at `http://localhost:8000`.

Optionally, you may want to create a new user via `php artisan make:admin <name>`

## Deployment
Every push to `master` (or a manual run in the Actions tab) is deployed by
[.github/workflows/deploy.yml](.github/workflows/deploy.yml): the frontend is built on a
GitHub-hosted runner, then a self-hosted runner on the server updates the app in place
(`git pull`, `composer install`, built assets, `migrate`, caches).

### One-time server setup
The app directory defaults to `/var/www/listky.farskyplesciernavoda.sk`; set the
`DEPLOY_APP_DIR` repository variable (Settings → Secrets and variables → Actions → Variables)
if it lives elsewhere. Run the following as the deploy user (the one the runner will run as).

1. **Clone the app** and configure it:
   ```bash
   git clone https://github.com/mibarnas/farsky-ples-event-reservation.git /var/www/listky.farskyplesciernavoda.sk
   cd /var/www/listky.farskyplesciernavoda.sk
   cp .env.example .env        # then set APP_ENV=production, APP_DEBUG=false, APP_URL, DB, mail
   composer install --no-dev --optimize-autoloader
   php artisan key:generate
   php artisan storage:link
   php artisan migrate --force
   sudo chown -R www-data:www-data storage bootstrap/cache
   ```
   Point Apache's `DocumentRoot` at `.../public`.

2. **Let the deploy user run artisan as `www-data`** without a password
   (`sudo visudo -f /etc/sudoers.d/farskyples-deploy`; the runner runs as `farskyples`):
   ```
   farskyples ALL=(www-data) NOPASSWD: /usr/bin/php
   ```
   The checkout must be owned by `farskyples`, except `storage/` and `bootstrap/cache/` (owned by
   `www-data`); `.env` should be `farskyples:www-data` with mode `640`.

3. **Register the self-hosted runner** for this repository. Runners on a personal account belong
   to one repository, so the camp-planner runner will not pick up these jobs — install a second one
   in its own directory:
   - On GitHub open **Settings → Actions → Runners → New self-hosted runner**, choose **Linux / x64**.
     The page shows the download commands and a one-time registration token.
   - On the server:
     ```bash
     mkdir ~/actions-runner-farsky && cd ~/actions-runner-farsky
     # paste the curl + tar commands from the GitHub page, then:
     ./config.sh --url https://github.com/mibarnas/farsky-ples-event-reservation \
       --token <TOKEN-FROM-GITHUB> --name farsky-prod --labels farsky --unattended
     sudo ./svc.sh install "$USER"   # run as a systemd service, starts on boot
     sudo ./svc.sh start
     ```
   - The runner should now show as **Idle** on the Runners page. The `farsky` label makes sure the
     deploy job only runs on this runner.

4. Push to `master` or run the **deploy** workflow manually.

## Features
- Administration dashboard for logged-in users for event management
- Multi-user support with simple roles and permissions
- Email notifications for reservation confirmations/cancellations etc.
- Responsive design for mobile and desktop
- Comprehensive design features such as banner, logo, overline title and markdown description of events
- Customizable reservation limits and settings per event
- Support for custom SVG seat maps
- Payment pairing either manually or via an CSV file import
- Printable seat map
- QR Codes for check-in at the event

## Ideas for future improvements
- Better implementation of "plugins" or "modules" to allow easier extension of the system
- Mass mailing system to all participants of an event
- Integrating with a paywall/payment gateway for automatic payment processing

## Contributing
Contributions are welcome! Please fork the repository and create a pull request with your changes.
In case of issues feel free to open an issue as well.

## License
This project is licensed under the GPLv3 License. See the LICENSE file for details.
The author is not responsible for any damages caused by the use of this software.

Developed by Michal Barnáš in 2025.
