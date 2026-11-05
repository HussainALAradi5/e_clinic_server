# e-Clinic — API (Laravel)

The REST API of **e-Clinic**, a clinic management system: patients and appointments, treatments, lab / imaging
orders and results, prescriptions and the pharmacy, billing with insurance claims, cash sessions, HR and payroll,
double-entry accounting, staff chat and support tickets — for a staff app and a patient portal on the web
([e_clinic_client](https://github.com/HussainALAradi5/e_clinic_client)) and one Flutter app for phones
([e_clinic_mobile](https://github.com/HussainALAradi5/e_clinic_mobile)).

## Live demo

| | Address |
|---|---|
| Web app (staff and patient portal) | https://e-clinic-web.onrender.com |
| API | https://e-clinic-api.onrender.com |

Free Render instances sleep when idle: the first visit can take about a minute to wake the API. Demo accounts
(one per role: patient, receptionist, doctor, nurse, pharmacist, cashier, accountant, HR, admin) are available on
request.

## What it does

| Area | Highlights |
|---|---|
| **Appointments** | Free-slot engine per provider and room (no double bookings), check-in by signed QR code, no-shows, rescheduling, reminders 3 days and 1 day before, timing (coming / today / past / missed) as a filter |
| **Clinical** | Visits and medical records with ICD-10 diagnoses, treatment plans and sessions, X-ray / lab / ECG orders with results and abnormal flags, consents, sick leave certificates, emergency access with audit |
| **Pharmacy** | Prescriptions, dispensing first-expiry-first-out with barcode checks and witnesses for controlled drugs, stock and expiry alerts |
| **Billing** | One draft invoice per patient collects every charge; issuing numbers it gap-free and posts revenue; split payments (cash, card, BenefitPay …), refunds, insurance claims, payment state and paid-by filters |
| **Finance & accounting** | Cash sessions with Z-reports, expenses with approvals, chart of accounts, journal entries, fiscal years and periods |
| **HR** | Shifts, clock in / out (geofenced), leave with balances, staff requests, payroll with deductions and payslips |
| **Communication** | Staff chat (Reverb websockets) with reactions and replies, support tickets, notifications in-app, by email / SMS, Web Push to browsers and to the phone app, with an on / off switch |
| **Platform** | Roles and permissions with ownership rules, two-factor sign-in, encrypted medical fields, audit log, idempotent payments, saved list views, Excel import / export, background tasks |

## Stack

| | |
|---|---|
| Framework | Laravel 12 (PHP 8.2+), Sanctum tokens, spatie/laravel-permission, Laravel Reverb |
| Database | SQLite locally, PostgreSQL in production |
| Structure | Layer first: `app/Models`, `Services`, `Http/Controllers`, `Http/Requests`, `Http/Resources`, `Enums`, `Dtos` …, each with module folders; `app/Modules/<Module>` holds the service provider and routes |
| Quality | 250+ Pest tests, PHPStan level 8 (Larastan), Laravel Pint |

## Run it locally

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed          # demo clinic: staff of every role, patients, a year of visits, invoices …
php artisan webpush:keys            # optional: browser notifications (put the keys in .env)
php artisan serve                   # API on http://127.0.0.1:8000
php artisan queue:work              # notifications, reminders, exports
php artisan reverb:start --port=8080   # live chat and tickets
```

## Tests

```bash
vendor/bin/pest                     # 250+ feature and unit tests
vendor/bin/phpstan analyse          # static analysis
vendor/bin/pint --test              # code style
```

## Related

- Web client (Angular): [e_clinic_client](https://github.com/HussainALAradi5/e_clinic_client)
- Phone app (Flutter): [e_clinic_mobile](https://github.com/HussainALAradi5/e_clinic_mobile)
- Technical documentation (every endpoint, service, table and enum): available on request
