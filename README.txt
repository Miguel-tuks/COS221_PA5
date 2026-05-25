Tripistry:

Tripistry is a travel package marketplace built with plain PHP, vanilla HTML/CSS/JavaScript, and a MySQL/MariaDB backend. There are two user roles: **Travellers** who browse and book packages, and **Travel Agencies** who create and manage them. No frameworks, no build step, no Composer dependencies.

Setup:

1. Copy the project:

Drop the project folder into your web root:

C:\xampp\htdocs\tripistry\

2. Configure the database connection:

Open `config/database.php` and update the four constants at the top if your setup differs from the defaults:

define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'TravelDB');

3. Import the database

Use the SQLDump or create a new database to populate from scratch.

4. Open in browser

http://localhost/tripistry/

Sample Logins

All sample accounts use the password `Password1`.

| Role          | Username      |
|---------------|---------------|
| Traveller     | alice_smith   |
| Traveller     | bob_jones     |
| Travel Agency | sunset_travel |
| Travel Agency | globetrek     |
| Travel Agency | safari_co     |


Features:

Traveller

-Browse packages — filter by keyword, destination, price range, minimum duration, and group-trip availability; sort by newest, price (low/high), duration, or average rating. Results show a thumbnail, destinations, price, agency name, and star rating at a glance.
-Package detail — full itinerary view including an image gallery, all included flights (airline, flight number, departure/arrival times), accommodations (name, type, star rating, address), tourist attractions (category, entrance fee), nearby restaurants sourced from the package's destinations, agency info with website link, and all existing traveller reviews.
-Compare — tick up to three packages from the browse page and view them side-by-side. Columns span price, duration, destinations, guide contact, and ratings.
-Book — one-step booking form for a selected package. The app checks for an existing booking before showing the form; duplicate bookings are blocked at both the UI and database level (composite PK).
-My bookings — full booking history with status badges (pending / confirmed / completed / cancelled). Completed bookings show an inline review form for a 1–5 star rating and written comment; re-submitting updates the existing review via `INSERT ... ON DUPLICATE KEY UPDATE`.
-Group trips — browse all upcoming group departures across all packages, filtered to available spots. Join a trip or leave one you have already joined.
-Catalogue browse — read-only catalogue tabs (destinations, flights, accommodations, attractions) so travellers can explore what is available before diving into packages.
-Profile — update first and last name; add or remove phone numbers (stored as a separate `USER_PHONE` table).

Travel Agency

-Package management — create new packages with name, description, price, duration, max capacity, and a named guide with contact number. Attach any combination of destinations, flights, accommodations, and attractions from the shared catalogue via multi-select. Add image URLs one per line; the app rebuilds the `PACKAGE_IMAGE` rows on every save. Packages can be toggled active/inactive or deleted.
-Catalogue management — add and edit the shared catalogue through a single `item_form.php` that adapts to five item types: destination, flight, accommodation, tourist attraction, and restaurant. Items are global — not agency-owned — so all agencies see them.
-Group trips — schedule group departures for any of your own packages. Set a departure date and maximum group size. Edit or delete existing trips.
-Bookings — view all incoming bookings for your packages. Update booking status through the workflow: `pending → confirmed → completed` or `cancelled`.
-Reviews — see an aggregate average rating per package alongside individual traveller reviews (reviewer name, rating, comment, booking date).
-Profile — update agency name, website, licence number, and description; manage phone numbers.

Project Structure:

tripistry/
|__ index.php               Public landing page
|__ login.php               Login by username + password
|__ register.php            Register as Traveller or Travel Agency
|__ logout.php              Destroy session and redirect
|
|__ config/
|   |__ database.php        PDO connection; constants at top of file
|
|__ includes/
|   |__ auth.php            Session helpers: requireRole(), currentUserId(), etc.
|   |__ functions.php       Shared utilities: h(), setFlash(), renderStars(), destLabel()
|   |__ header.php          Shared HTML header and navigation
|   |__ footer.php          Shared HTML footer
|
|__ css/
|   |__ style.css           Single stylesheet for the entire app
|
|__ js/
|   |__ validation.js       Client-side form validation (registration, booking forms)
|
|__ sql/
|   |__ schema.sql          DROP + CREATE DATABASE, all CREATE TABLE statements
|   |__ sample_data.sql     Seed data including bcrypt-hashed passwords
|
|__ traveller/              Pages restricted to role = Traveller
|   |__ dashboard.php       Stats summary, recent bookings, top-rated packages
|   |__ packages.php        Browse, filter, sort, and select packages to compare
|   |__ package_detail.php  Full package view: images, itinerary, reviews, group trips
|   |__ browse.php          Catalogue tabs: destinations, flights, accommodations, attractions
|   |__ compare.php         Side-by-side comparison of up to 3 packages
|   |__ book.php            Booking form for a selected package
|   |__ bookings.php        My bookings: view, review, cancel
|   |__ group_trips.php     Browse and join/leave group departures
|   |__ profile.php         Edit name and manage phone numbers
|
|__ agency/                 Pages restricted to role = Travel Agency
    |__ dashboard.php       Overview: package count, booking stats, recent activity
    |__ packages.php        List, activate/deactivate, and delete own packages
    |__ package_form.php    Create/edit packages with M:N item selections and images
    |__ items.php           Browse shared catalogue items across all agencies
    |__ item_form.php       Create/edit catalogue items (5 types, one form)
    |__ group_trips.php     List and manage group trips for own packages
    |__ group_trip_form.php Create/edit group departures
    |__ bookings.php        View and update status of incoming bookings
    |__ reviews.php         Aggregate and per-package review breakdown
    |__ profile.php         Edit agency profile and phone numbers


Database Design


User hierarchy (ISA)

`USER` is the parent table holding `userID`, `username`, `password` (bcrypt hash), the `title` discriminator (`'Traveller'` or `'Travel Agency'`), and `createdAt`. The two subtypes extend it:

- `TRAVELLER(userID, firstName, lastName)` — PK is a FK back to `USER`.
- `TRAVEL_AGENCY(userID, agencyName, website, licenseNo, description)` — PK is a FK back to `USER`.

Both subtypes cascade-delete from `USER`, so removing a user cleans up the subtype row automatically.

Core tables

| Table                | Key columns                                                                               | Notes                                        |
|----------------------|-------------------------------------------------------------------------------------------|----------------------------------------------|
| `DESTINATION`        | `destinationID`, `continent`, `country`, `city`                                           | Shared; not agency-owned                     |
| `ACCOMMODATION`      | `accommodationID`, `accName`, `type`, `rating`, `destinationID`                           | Linked to a destination                      |
| `FLIGHT`             | `flightID`, `flightNumber`, `airline`, `departure`, `arrival`, `destinationID`            | Destination is the arrival point             |
| `TOURIST_ATTRACTION` | `attractionID`, `name`, `category`, `entranceFee`                                         | No destination FK; globally shared           |
| `RESTAURANT` 	       | `restaurantID`, `name`, `cuisine`, `priceRange`, `destinationID`                          | Reached via destination                      |
| `PACKAGE` 	       | `packageID`, `pkgName`, `price`, `duration`, `maxPeople`, `guide`, `guideNum`, `agencyID` | `isActive` flag for soft-toggling visibility |

Link and weak-entity tables

- M:N link tables — `PACKAGE_DESTINATION`, `PACKAGE_FLIGHT`, `PACKAGE_ACCOMMODATION`, and `PACKAGE_ATTRACTION` connect packages to catalogue items. All cascade-delete when the parent package is removed.
- `PACKAGE_IMAGE(packageID, image)` — composite PK; stores one row per image URL. Rebuilt in full on every package save.
- `USER_PHONE(userID, phone)` — composite PK; models a multivalued phone-number attribute for both user types.
- `BOOKING(travellerID, packageID)` — composite PK prevents a traveller booking the same package twice. Carries a `status` column (`pending` / `confirmed` / `completed` / `cancelled`) and `bookingDate`.
- `REVIEW(travellerID, packageID)` — composite PK; linked to `BOOKING`. Stores `rating` (1–5) and `comment`.
- `GROUP_TRIP(tripID, packageID)` — `tripID` is an auto-increment discriminator within a package; composite PK `(tripID, packageID)`. Stores `departureDate` and `maxGroupSize`.
- `JOINS(travellerID, tripID, packageID)` — ternary association linking a traveller to a specific group trip.

Restaurants and the indirect join

There is no `PACKAGE_RESTAURANT` table. Restaurants belong to destinations, and destinations belong to packages via `PACKAGE_DESTINATION`. The package detail page retrieves nearby restaurants by joining `PACKAGE_DESTINATION -> DESTINATION -> RESTAURANT`, which keeps the schema normalised without a redundant link table.


Security

| Concern          | Approach                                                                                                                           |
|------------------|------------------------------------------------------------------------------------------------------------------------------------|
| Passwords        | Hashed with `password_hash(..., PASSWORD_BCRYPT)`, verified with `password_verify`                                                 |
| SQL injection    | All queries use PDO prepared statements with named placeholders; `ATTR_EMULATE_PREPARES = false`                                   |
| XSS              | All user-supplied output passes through `h()` (`htmlspecialchars` with `ENT_QUOTES`)                                               |
| Authorisation    | Every protected page calls `requireRole()`; agency update/delete queries include `AND agencyID = :u` to prevent cross-agency edits |
| Session fixation | `logoutUser()` destroys the session and clears the cookie                                                                          |


Tech Stack

| Layer    | Technology                                    |
|----------|-----------------------------------------------|
| Language | PHP                                           |
| Database | MySQL / MariaDB via PDO                       |
| Frontend | Vanilla HTML, CSS, JavaScript (no build step) |
| Auth     | PHP sessions + bcrypt                         |
| Server   | Apache (XAMPP recommended for local dev)      |

Known Limitations

-Image upload — images are added by URL only; there is no file upload.
-Shared catalogue ownership — any agency can edit any catalogue item. Fixing this would require an `addedBy` column on each item table.
-Cancellation — cancelling a booking sets `status = 'cancelled'` rather than deleting the row. This prevents the traveller from immediately re-booking the same package (which the composite PK would otherwise allow).
-Bookings and group trips are independent — the schema does not link them. A traveller may book a package solo and also join one of its group trips simultaneously.
