# WTG API

Base URL: `http://wtg.localhost`

---
## POST /api/imports
### Request example
```bash
curl -X POST http://wtg.localhost/api/imports \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d '{
    "supplier": "supplier-a",
    "external_id": "import-2026-09-01-001",
    "sent_at": "2026-09-01T10:00:00Z",
    "offers": [
        {
            "external_id": "offer-a-10001",
            "property": {
                "code": "BCN-0001",
                "name": "Apartment near Sagrada Familia",
                "city": "Barcelona"
            },
            "check_in": "2026-10-10",
            "check_out": "2026-10-15",
            "max_guests": 4,
            "price": 72500,
            "currency": "EUR",
            "available_units": 2,
            "expires_at": "2026-12-10T23:59:59Z"
        }
    ]
}'
```
### Response `202 Accepted`
```json
{
    "data": {
        "id": 2,
        "status": "Created"
    }
}
```

### Errors
`422 Unprocessable Content` — supplier not exist or validation errors:

```json
{
    "message": "The selected supplier is invalid. (and 1 more error)",
    "errors": {
        "supplier": ["The selected supplier is invalid."],
        "offers": ["The offers field is required."]
    }
}
```

---

## GET /api/imports/{import}
```bash
curl http://wtg.localhost/api/imports/2 -H 'Accept: application/json'
```

### Response `200 OK`
```json
{
    "data": {
        "id": 2,
        "supplier": {
            "name": "supplier-a",
            "external_id": "supplier-a"
        },
        "external_id": "import-2026-09-01-002",
        "sent_at": "2026-09-01T10:00:00.000000Z",
        "status": "Completed",
        "total_offers": 2,
        "total_imported": 2,
        "error": null,
        "created_at": "2026-09-05T11:32:39.000000Z",
        "completed_at": "2026-09-05T11:32:39.000000Z"
    }
}
```

### Errors
`404 Not Found` — import not exist
---

## GET /api/properties
### Request example
```bash
curl -G http://wtg.localhost/api/properties \
  -H 'Accept: application/json' \
  -d city=Barcelona \
  -d check_in=2026-10-10 \
  -d check_out=2026-10-15 \
  -d guests=2 \
  -d page=1
```

### Response `200 OK`
```json
{
    "data": [
        {
            "code": "BCN-0001",
            "name": "Apartment near Sagrada Familia",
            "city": "Barcelona",
            "best_offer": {
                "id": 2,
                "supplier": {
                    "name": "supplier-a",
                    "external_id": "supplier-a"
                },
                "price": 68000,
                "currency": "EUR",
                "available_units": 1,
                "expires_at": "2026-12-10T23:59:59.000000Z"
            }
        }
    ],
    "links": {
        "first": "http://wtg.localhost/api/properties?page=1",
        "last": "http://wtg.localhost/api/properties?page=1",
        "prev": null,
        "next": null
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 1,
        "path": "http://wtg.localhost/api/properties",
        "per_page": 10,
        "to": 1,
        "total": 1
    }
}
```

### Errors

`422 Unprocessable Content`:
```json
{
    "message": "The check out field must be a date after or equal to check in. (and 1 more error)",
    "errors": {
        "check_out": ["The check out field must be a date after or equal to check in."],
        "guests": ["The guests field must be an integer."]
    }
}
```

---

## POST /api/offers/{offer}/reservations

### Requset example
```bash
curl -X POST http://wtg.localhost/api/offers/2/reservations \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d '{
    "client_reference": "web-order-9f782b1c",
    "customer_name": "John Smith",
    "customer_email": "john@example.com"
}'
```

### Response `201 Created`
```json
{
    "data": {
        "id": 1,
        "client_reference": "web-order-9f782b1c",
        "customer_name": "John Smith",
        "customer_email": "john@example.com",
        "created_at": "2026-09-05T11:33:05.000000Z",
        "check_in": "2026-10-10T00:00:00.000000Z",
        "check_out": "2026-10-15T00:00:00.000000Z",
        "property": {
            "code": "BCN-0001",
            "name": "Apartment near Sagrada Familia",
            "city": "Barcelona"
        }
    }
}
```

### Errors
`409 Conflict` — The offer has no available units left:
```json
{
    "message": "The offer has no available units left."
}
```

`422 Unprocessable Content` — validation errors:
```json
{
    "message": "The customer email field must be a valid email address.",
    "errors": {
        "customer_email": ["The customer email field must be a valid email address."]
    }
}
```

`404 Not Found` — Offer not found.
---
