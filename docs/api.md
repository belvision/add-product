# Ozon Draft Wizard API

## Endpoints

### POST /api/ozon/drafts
Create a new draft.

**Query params:** `?lang=ru|en` (optional, default: ru)

**Response:**
```json
{
  "ok": true,
  "data": {
    "draftId": "abc123...",
    "formSchema": { ... },
    "editedJson": { "title": "", "brand": "", "images": [] },
    "images": []
  },
  "meta": { "traceId": "...", "ts": "2024-..." }
}
```

### GET /api/ozon/drafts/{draftId}
Load existing draft.

**Query params:** `?lang=ru|en`

**Response:**
```json
{
  "ok": true,
  "data": {
    "draftId": "...",
    "formSchema": { ... },
    "editedJson": { ... },
    "images": [ ... ]
  }
}
```

### PATCH /api/ozon/drafts/{draftId}
Save draft changes.

**Body:**
```json
{
  "editedJson": { "title": "Product", "brand": "demo_brand_1" }
}
```

**Response:**
```json
{
  "ok": true,
  "data": { "saved": true, "version": 2 }
}
```

### POST /api/ozon/drafts/{draftId}/images:upload
Upload image file (multipart/form-data).

**Form field:** `file`

**Response:**
```json
{
  "ok": true,
  "data": {
    "image": { "imageId": "...", "url": null, "bytes": 12345, ... },
    "images": [ ... ]
  }
}
```

### POST /api/ozon/drafts/{draftId}/images:from-url
Load image from URL.

**Body:**
```json
{
  "url": "https://example.com/image.jpg"
}
```

**Response:**
```json
{
  "ok": true,
  "data": {
    "image": { "imageId": "...", "url": null, "bytes": 12345, ... },
    "images": [ ... ]
  }
}
```

### DELETE /api/ozon/drafts/{draftId}/images/{imageId}
Delete image.

**Response:**
```json
{
  "ok": true,
  "data": { "deleted": true, "images": [ ... ] }
}
```

### POST /api/ozon/drafts/{draftId}/validate
Validate draft.

**Response:**
```json
{
  "ok": true,
  "data": {
    "valid": true,
    "errors": {}
  }
}
```

Or if invalid:
```json
{
  "ok": true,
  "data": {
    "valid": false,
    "errors": {
      "fieldErrors": {
        "title": "Поле обязательно для заполнения"
      }
    }
  }
}
```

### POST /api/ozon/drafts/{draftId}/publish
Publish draft (validates first).

**Response (success):**
```json
{
  "ok": true,
  "data": {
    "published": true,
    "productId": "demo_abc123..."
  }
}
```

**Response (validation failed):**
```json
{
  "ok": false,
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "Validation failed",
    "details": {
      "fieldErrors": { ... }
    }
  }
}
```

## Error Format

All errors follow envelope format:
```json
{
  "ok": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human readable message",
    "details": { ... },
    "meta": { "traceId": "..." }
  }
}
```
