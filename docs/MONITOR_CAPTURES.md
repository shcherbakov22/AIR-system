# Monitor Captures

## Purpose

Monitor captures are the screen and camera images shown on the mentor dashboard.

They are stored in the rewrite app and treated as part of the live monitor surface.

## Capture Types

- screen
- camera

## Upload Paths

### Modern API

- `POST /api/student-monitor-captures/screen`
- `POST /api/student-monitor-captures/camera`

### Legacy-Compatible Paths

- `GET|POST /ss/upl1.php`
- `POST /ss/uplcam.php`
- `POST /ss/uplscr.php`

## Expected Metadata

Captured records can include:

- student identity
- capture kind
- capture time
- upload time
- task/activity snapshot
- source label

## Dashboard Behavior

On the mentor dashboard:

- each student card shows the latest available screen and camera preview
- clicking a tile opens the fullscreen modal
- the modal supports same-day history browsing
- wheel scrolling can move through older/newer captures

## Retention

Old captures are purged daily on the production server.

This keeps the dashboard operational rather than turning it into a long-term media archive.

