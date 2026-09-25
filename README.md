# marketplaceLibrary-API

## Marketplace listing creation

Apply the non-destructive listing migration to an existing database:

```powershell
C:\xampp\php\php.exe migrations\20260925_add_listing_support.php
```

`POST /books` requires `Authorization: Bearer <JWT>` and
`multipart/form-data`. Accepted text fields are `title`, `author`, `isbn`,
`category` (the current frontend name for the separately stored book genre),
`condition`, `listing_type`, `price`, `description`, and `city`. Images use the
`photos[]` field. `genre` and `book_condition` are also accepted as API-native
aliases for `category` and `condition`.

The accepted listing values are `Sell`, `Exchange`, and `Sell or Exchange`
(or their normalized forms `SELL`, `EXCHANGE`, and `SELL_OR_EXCHANGE`). Sell
and sell-or-exchange listings require a price greater than zero. Exchange
listings always store `0.00`, regardless of an omitted or submitted price.

The frontend genre is stored in `books.genre`; it is not inserted into the
marketplace `categories` table. New book listings retain `category_id` for the
existing API by using the parent marketplace category whose slug is `books`.
The authenticated JWT `user_id` is always used as `owner_id`; request-provided
ownership values are ignored.

Uploads accept 1-5 JPEG, PNG, or WebP images, at most 5 MB each. They are stored
under `public/uploads/books` with generated filenames. The first image is the
cover and is also copied to the legacy `books.cover_image` field for backwards
compatibility.
