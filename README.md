# VetSmart — veterinary farm records (PHP + MySQL + Bootstrap 5)

## Quick start (XAMPP / WAMP / Laragon / any Apache+PHP 8.0+)
1. Copy the `vetsmart` folder into `htdocs` (or `www`).
2. Import `database/vetsmart.sql` in phpMyAdmin (it creates the `vetsmart` database).
3. Edit `config/config.php` if your MySQL user or password differs from `root` / empty.
4. Make sure PHP has `pdo_mysql`, `zip` and `gd`-free image support (`getimagesize`) enabled (all default in XAMPP).
5. Open http://localhost/vetsmart/

**Default admin:** `admin@vetsmart.lk` / `password` — change it right away (Admin > Manage users > Edit).
Create veterinarian accounts from Admin > Create user, then sign in with role "User".

## Google Maps
Put your key in `GOOGLE_MAPS_API_KEY` (config/config.php). Enable Maps JavaScript, Places and Geocoding APIs.
With no key the picker falls back to OpenStreetMap, which needs no account. Typing the location by hand always works.

## Folder structure
```
vetsmart/
├─ index.php, login.php, logout.php
├─ config/config.php           DB, map key, upload limits
├─ database/vetsmart.sql       schema + default admin
├─ includes/                   bootstrap (DB, auth, helpers), header, footer, nav, map picker, uploads
├─ admin/                      dashboard, create_user, manage_users
├─ user/                       dashboard, farm_register, location_add, farms, batch_add, batches,
│                              add_records (broiler + layer), records, record_view,
│                              prescription, prescriptions, notifications
├─ api/                        locations, batches, notifications (JSON, used by the live UI)
├─ download/                   docx.php (Word export), image.php (secure image viewer)
├─ lib/DocxBuilder.php         dependency-free .docx writer
├─ assets/css/style.css        "Meadow & Tide" theme
├─ assets/js/                  app.js (dropdowns, live alerts), map-picker.js
└─ uploads/postmortem/         broiler/ and layer/ image folders
```

## How things work
* **Dropdowns**: farms are listed A→Z; picking a farm loads its locations (A→Z), then its batches.
* **Post-mortem images** are renamed `FarmName_FarmID_Location_RecordNo_01.jpg` and stored in `uploads/postmortem/<broiler|layer>/`. They are served through `download/image.php`, which checks who owns the record.
* **Word exports**: farms, batches, broiler records, layer records (with their images) and prescriptions, one record or all/filtered/selected.
* **Realtime alerts**: every save writes to `activity_log`; browsers poll `api/notifications.php` every 5 s (`NOTIF_POLL_MS`) and show a toast, bell badge and live feed. Admins see everyone's activity; users see their own history.
* Users only see their own farms and records; admins see all of it (read-only on the Field data pages).

## Notes
* Bootstrap, Bootstrap Icons, Chart.js, Google Fonts and Leaflet load from CDNs, so the browser needs internet access.
* To use your own logo, replace the egg icon in `includes/header.php` and `login.php` with an `<img>` tag.
* Production: use HTTPS and change the DB credentials.
