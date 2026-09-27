# Campus SkillX — Skill Exchange Platform

A web platform where students trade skills: teach what you know, learn what you want. Students list skills they **offer** and skills they **want**, get ranked partner recommendations from a two-way matching engine, then move through the whole cycle:

**Discover → Match → Request → Communicate → Schedule → Exchange → Review**

Built with HTML5, CSS3, vanilla JavaScript, PHP 8 (PDO) and MySQL/MariaDB. No framework and no build step.

---

## Features

**Students**
- Registration (name, student ID, email, phone, programme, faculty, year), login by email *or* student ID, logout, change password
- Dashboard: stats, recommended partners, pending requests, upcoming sessions, recent messages and activity, profile-completeness prompt
- **Find Skills**: search + filters (category, level, programme, year, availability day, format, minimum rating) with a match % on every card
- **Intelligent matching** with a plain-language explanation of *why* two students fit
- **My Skills**: add / edit / remove skills with level, description, experience and availability; new skills can be created on the fly
- **Requests**: send with preferred method/date/time; accept, decline, cancel; accepting creates a match
- **My Matches**: active / completed / cancelled, mark completed, end match
- **Messages**: chat with matched partners, unread badges, auto-refresh every few seconds
- **Learning Sessions**: schedule with date, time, location and meeting link; mark completed or cancelled (past scheduled sessions become *missed* automatically)
- **Reviews**: 1–5 stars plus comment, one review per person per exchange
- **Notifications**: bell dropdown, unread count, full notification page
- **Profile** (with availability, reviews, exchange stats), **Settings** (privacy, learning preferences, weekly availability, notification preferences, password, sign out other sessions), **Reports** (report a student)

**Administrators** (separate interface under `/admin`)
- Dashboard with key numbers and alerts
- Students: search, filter, view/edit, activate, suspend, delete
- Skills and Categories: add, rename, deactivate, delete
- Monitoring: requests, matches, messages, sessions
- Reviews: hide, restore or delete abusive reviews
- Reports: investigate, change status, record notes, optionally suspend the reported student
- Announcements: published to every active student's notification centre
- Analytics: charts (Chart.js) for registrations, completed exchanges, popular and requested skills, categories, platform activity
- Admin Accounts (super administrator only) and Settings (registration on/off, account verification on/off, site notice)
- Every admin action is written to an audit log (`admin_actions`)

**Roles:** `student`, `admin`, `super_admin`.
There is intentionally **no "Forgot password"** feature.

---

## Requirements

- XAMPP (or any stack) with **PHP 8.1+**, Apache and **MySQL/MariaDB**
- PHP extensions: `pdo_mysql`, `mbstring` (both enabled in default XAMPP)

## Setup (XAMPP on Windows)

1. Put this folder in `C:\xampp\htdocs\` (it already is if you are reading this there).
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Create the database and tables. Either:
   - **Command line:** `C:\xampp\mysql\bin\mysql.exe -u root < database\campus_skill_exchange.sql`
   - **phpMyAdmin:** open <http://localhost/phpmyadmin>, go to **Import**, choose `database/campus_skill_exchange.sql`, click **Go**.
4. Open <http://localhost/Campus%20Skill%20Exchange%20Platform/>.

The defaults (`root` user, empty password, database `campus_skill_exchange`) match a fresh XAMPP install. To change them, set environment variables `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, or edit [includes/config.php](includes/config.php).

### Create your first administrator

No default admin account ships with the app. Create one from the command line:

```
C:\xampp\php\php.exe database\create-admin.php "Your Name" you@campus.edu super
```

You will be prompted for a password. Omit `super` to create a regular administrator. Then log in at `/login.php` — administrators are taken to the admin interface automatically.

### Optional demo data

To explore with realistic data (fake students, skills, requests, a match, a session, reviews):

```
C:\xampp\php\php.exe database\seed-demo.php --yes
```

Demo logins: `trust.mwendabai@demo.skillx` / `Demo1234` (students), `admin@demo.skillx` and `super@demo.skillx` / `Admin1234`.
**Development only.** Delete these accounts (or use a separate database via `DB_NAME`) before any real deployment.

---

## How matching works

`includes/matching.php` scores each candidate from 0–100:

| Weight | Signal |
|-------:|--------|
| 35 | They teach skills **you** want |
| 25 | You teach skills **they** want |
| 15 | Overlapping weekly availability |
| 10 | Category affinity (related fields even without an exact skill match) |
| 5 | Skill-level fit (teacher's level ≥ learner's level) |
| 5 | Compatible learning format |
| 5 | Track record (average rating and completed exchanges) |

Two-way matches are ranked first, and each result includes an explanation such as *"You want to learn Graphic Design, which Mary offers, while they want to learn Python, which you offer."*

## Security notes

- Passwords hashed with `password_hash` (bcrypt); rehashed automatically when needed
- All queries use PDO prepared statements; all output is HTML-escaped
- CSRF token on every state-changing request (forms and the JSON API); logout is POST-only
- Session ID regenerated on login; cookies are `HttpOnly` and `SameSite=Lax`
- Brute-force protection: 5 failed logins per account (or 20 per IP) in 15 minutes locks login for that window
- Changing your password, or using **Log out from other sessions**, invalidates every other session
- Ownership is checked server-side on every action (you cannot accept someone else's request, review a match you're not in, etc.)
- Privacy settings are enforced: private profiles/skills are hidden from search, and messaging requires a match unless the recipient allows anyone
- Administrators cannot read private messages by default; message text appears only when a user in that conversation has an open report

## Project structure

```
index.php  login.php  register.php  logout.php       Public pages
dashboard.php  find-skills.php  skills.php            Student pages
matches.php  requests.php  messages.php  sessions.php
reviews.php  notifications.php  profile.php
edit-profile.php  settings.php  reports.php
admin/        Administrator interface (dashboard, users, skills, categories,
              requests, matches, messages, sessions, reviews, reports,
              announcements, analytics, admins, settings)
api/          JSON endpoints (notifications, messages, search-skills, matching, requests)
includes/     bootstrap, config, db, auth, functions, matching, exchange (business logic),
              header/sidebar/footer layouts
assets/       css (style.css landing + tokens, app.css application UI), js
database/     campus_skill_exchange.sql, create-admin.php, seed-demo.php
```

## Known limitations

- **No email sending.** "Account verification" means an administrator activates new accounts (enable it in Admin → Settings). It is off by default.
- **Profile photos are not implemented**; avatars show initials.
- Chat refreshes by polling every few seconds rather than WebSockets.
- The site follows the visitor's light/dark system theme.
