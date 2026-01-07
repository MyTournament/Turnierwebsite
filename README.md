# Tournament Website

This project is a configurable tournament website that can host multiple tournaments
and automatically adapts its pages and content based on the active tournament data.
It is designed to be reused for different events (not tied to a single tournament),
with the website pulling the current tournament, test tournaments, and history from
the database and rendering the appropriate views.

## What it does
- Publishes a public-facing tournament website (home, rules, schedule, brackets).
- Handles team registration and login for match entry and result updates.
- Supports admin/backstage tooling for organizers.
- Generates printable views and certificates.
- Provides a lightweight API for certain actions.
- Includes optional captcha protections for public forms.

## How it works
The website reads tournament metadata from MySQL and treats the latest tournament
as the "current" event. Pages such as the schedule, brackets, and standings are
rendered dynamically based on database queries. The same codebase can power
different tournaments by changing the database content (and configuration).

The application is mostly server-rendered PHP with HTML/CSS/JS for UI and
interactive elements. There are additional helper scripts for data updates,
printing, and maintenance tasks.

## Tech stack
- PHP (server-rendered pages and backend logic)
- MySQL / MariaDB (tournament data, teams, matches, settings)
- HTML/CSS/JavaScript (frontend rendering and interactions)
- Apache/Nginx (typical deployment targets)
- cURL (outbound requests for mail providers or captcha verification)

## Main components
- `index.php` and page templates: public website rendering
- `database/`: database connection + utilities
- `website_functionalities/`: core logic (email, captcha, helpers)
- `website_datachange/`: data modification flows (forms, admin actions)
- `website_print_functions/`: printing/export helpers
- `api/`: simple endpoints for selected features

## Configuration
Local and environment-specific configuration lives outside the repo to avoid
committing secrets. See `SETUP.md` for local development guidance and use
`local_secrets/*.local.php` for your private values.

## License
See repository details or add a license file if you plan to open-source this project.
