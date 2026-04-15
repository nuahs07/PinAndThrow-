# Pin and Throw 📍🗑️

**Pin and Throw** is a community-based digital waste-reporting system designed to bridge the communication gap between local residents and barangay officials (specifically targeting Brgy. Pio Del Pilar, Makati City). 

The platform empowers residents to report unmanaged garbage or hazardous waste by pinning exact locations on an interactive map and uploading photographic evidence. Barangay officers can then use a centralized dashboard to assess, verify, update, and track cleanup operations.

## ✨ Features

### 👤 Resident / User Features
* **Waste Reporting:** Submit detailed reports including name, exact address, description, and type of waste (Hazardous, Pharmaceutical, Electrical, Non-segregated).
* **Interactive Mapping:** Pinpoint the exact location of the waste using an integrated interactive map (powered by Leaflet.js).
* **Evidence Upload:** Upload up to 5 photos per report to ensure accountability.
* **Resident Dashboard (`user_dashboard.php`):** Track personal report history, view statistics of submitted reports, and see real-time status updates (Pending, Verified, In-Progress, Resolved, Rejected).
* **Live Notifications:** Receive updates from the barangay as soon as the status of a report changes.

### 🛡️ Admin / Officer Features
* **Officer Command Center (`admin_dashboard.php`):** A comprehensive dashboard for managing all submitted waste reports.
* **Status Management:** Review incoming reports, assign statuses, and dispatch cleanup crews. Updating a status automatically sends a notification to the resident.
* **Priority Alerts:** Automatically flags reports that are older than 3 days or contain keywords like "hazard" to ensure urgent issues are addressed.
* **Analytics & Visualization:** * Bar charts displaying reports by location.
  * Donut charts showing the split of report statuses.
  * Visual waste density heatmap.
  * Timeline of recent administrative activity.

## 🛠️ Tech Stack

* **Frontend:** HTML5, CSS3, Vanilla JavaScript
* **Mapping Library:** Leaflet.js (OpenStreetMap)
* **Backend:** PHP 8+ (PDO for secure database interactions)
* **Database:** MySQL / MariaDB

## 📂 Project Structure

```text
├── index.html               # Main landing page and guest reporting portal
├── login.php                # Authentication page (implied)
├── register.php             # User registration (implied)
├── user_dashboard.php       # Dashboard for authenticated residents
├── admin_dashboard.php      # Command center for barangay officers
├── pin_and_throw.sql        # MySQL database schema setup file
├── styles.css               # Global stylesheets
└── resources/               # Directory for logos and image assets
