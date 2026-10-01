# Marketplace orders

Run the additive migration with PHP:

    php migrations/20261001_orders.php

It can be run again safely. Do not use the legacy migrate.php: that script rebuilds the database.
Existing listings default to ACTIVE. Catalog and category counts include only ACTIVE listings.

All order endpoints require Authorization: Bearer <JWT>.

- POST /orders: JSON book_ids (1–100 positive integer IDs), full_name, email, address, city, postal_code.
  Repeated IDs are deduplicated. The buyer is taken from the JWT. Extra price/ownership fields are ignored.
  Returns 201 with {data: order}, including its items. All books are locked in ascending ID order
  in one transaction; only ACTIVE SELL or SELL_OR_EXCHANGE listings with a positive price can be bought.
  Each item's price, seller and title are database snapshots. All items start PENDING; books become RESERVED.
- GET /orders/mine: {data: orders[]} for the JWT buyer only, including items and seller names.
- GET /orders/seller: {data: items[]} for the JWT seller only. Includes buyer name, city and cover_url.
  Address/postal_code are available only while ACCEPTED or IN_PROGRESS; buyer email is never returned.
- PATCH /orders/items/{id}/status: {status: "ACCEPTED"}, etc. Returns {data: item}.
  Seller identity comes exclusively from the JWT. Missing item: 404; other seller: 403;
  invalid transition or already reserved book: 409; invalid checkout data: 422.

Allowed transitions: PENDING -> ACCEPTED or DECLINED; ACCEPTED -> IN_PROGRESS;
IN_PROGRESS -> DELIVERED. DECLINED and DELIVERED are terminal.
Decline releases the listing to ACTIVE; delivery marks it SOLD.
No buyer cancellation or payment collection is implemented.

Foreign keys retain listings/users referenced by order history. Listing deletion returns 409
for an ordered listing, after the existing ownership check. Ordinary unreferenced listing deletion is unchanged.
Order errors include a stable code for frontend translation.

Verification: frontend tests/commerce.cjs creates three isolated users and four listings,
tests the API and browser flow, then cleans up only its own orders/users/listings/images.
