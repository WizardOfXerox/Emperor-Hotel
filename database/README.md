# Database Notes

Database name: `emperors_hotel_db`

## Files

- `schema.sql`: use this for a fresh database. It creates the database, creates all five tables, and inserts the initial 36 room records.
- `seed_rooms.sql`: use this for an existing database. It normalizes the room type enum and inserts or updates the 36 room records without dropping tables.

## Tables

- `users`: login accounts and roles
- `guests`: guest contact profiles
- `rooms`: room inventory, room status, capacity, and dynamic nightly price
- `reservations`: bookings connected to guests, users, and rooms
- `payments`: payment records connected to reservations

## Room Inventory

The current default inventory has three room types:

- `Imperial Deluxe`: 12 rooms, rooms `101` to `112`
- `Royal Executive`: 12 rooms, rooms `201` to `212`
- `Emperor Presidential`: 12 rooms, rooms `301` to `312`

Default seed prices:

- `Imperial Deluxe`: `4500.00`
- `Royal Executive`: `7500.00`
- `Emperor Presidential`: `12500.00`

Prices are stored in `rooms.price_per_night`. The admin Rooms page can update all rooms of one type through the bulk price form.

## Import

Fresh setup:

```sql
SOURCE database/schema.sql;
```

Existing setup:

```sql
SOURCE database/seed_rooms.sql;
```

If importing through phpMyAdmin, open the target file and run/import it from the SQL tab.
