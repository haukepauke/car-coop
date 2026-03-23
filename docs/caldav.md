# CalDAV Calendar Sync

Car Coop exposes a CalDAV server that allows you to sync bookings with any compatible calendar app — DAVx⁵ on Android, Apple Calendar on iOS/macOS, Thunderbird, and others.

## Service discovery

Clients that support RFC 6764 auto-discovery (DAVx⁵, Apple Calendar) need only the server's base URL and your login credentials. Point the client at `https://<your-server>` and it will discover the CalDAV endpoint automatically via the `/.well-known/caldav` redirect.

## Manual setup

If your client requires a specific URL, use:

```
https://<your-server>/caldav/
```

Authenticate with your Car Coop email address and password.

## What syncs

Each car you are a member of appears as a separate calendar. All bookings for that car are visible, regardless of who created them. The following fields are synchronised:

| Car Coop field | Calendar field |
|---|---|
| Booking title | Event summary (falls back to member name if blank) |
| Start date | DTSTART (all-day) |
| End date | DTEND (all-day, inclusive) |
| Status (`fixed` / `maybe`) | STATUS (`CONFIRMED` / `TENTATIVE`) |
| Booking owner | ORGANIZER |

Creating or deleting an event in your calendar app creates or deletes the corresponding booking in Car Coop. Editing the title, dates, or status of an event updates the booking.

## Notes

- Events created via a CalDAV client are assigned a canonical URI by the server (`booking-{id}.ics`), which may differ from the URI the client used for the PUT request. Clients that follow RFC 4918 (such as DAVx⁵) handle this transparently by re-syncing after creation.
- The CalDAV endpoint does not support creating new calendars. Calendars are managed through the Car Coop web interface.

## Debugging

A command-line test script is included that steps through all CalDAV operations and reports the HTTP status of each:

```bash
php bin/caldav-test.php <email> <password> [base-url]

# Example against a local dev server
php bin/caldav-test.php user@example.com secret http://localhost:8080

# Example against production
php bin/caldav-test.php user@example.com secret https://app.car-coop.net
```

The script tests service discovery, authentication, calendar listing, incremental sync (`sync-collection` REPORT), full sync (`calendar-query` REPORT), and a full create–verify–delete cycle.
