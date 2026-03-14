# Legacy Interop

## Purpose

The rewrite is not a line-by-line clone of the old system, but it does interoperate with legacy data sources and legacy capture clients.

## Legacy Database Usage

The main live legacy database source used during migration work is on:

- `192.168.11.66`

That source has been used for:

- active student list checks
- schedule imports
- rule and violation extraction
- task timing extraction

Legacy migration notes now live in this documentation suite rather than in a separate handoff file.

## Legacy Uploader Compatibility

The rewrite exposes old-style endpoints so older screenshot/camera clients can still work:

- `GET|POST /ss/upl1.php`
- `POST /ss/uplcam.php`
- `POST /ss/uplscr.php`

Compatibility expectations:

- `upl1.php` behaves like the legacy delay/check endpoint
- old clients can keep using `username` / `pass`
- HTTP remains available for compatibility

## Monitor Capture Flow

Legacy uploads end up in the rewrite’s monitor capture storage and then surface on the mentor dashboard just like modern uploads.

That means the rewrite is the system of record for:

- current capture display
- capture history browsing
- daily capture cleanup

## What Not To Assume

- do not assume legacy table shapes should define the rewrite schema
- do not assume every old feature should be rebuilt
- do not assume legacy naming is semantically correct
