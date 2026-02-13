# Ozon Draft Wizard UX Behavior

## Overview
Schema-driven multi-step wizard for creating Ozon product drafts.

## Language Support
- RU/EN via `?lang=ru|en` query parameter (default: ru)
- All UI labels and validation messages respect locale
- Language switcher in header

## Wizard Flow

### Steps
1. **Images** - Upload product images (min 1, max 10)
2. **Main** - Product title (3-200 chars) and brand selection

### Navigation
- **Next** button: Validates current step locally, blocks if required fields missing or constraints violated
- **Previous** button: Returns to previous step without validation
- **Save** button: Manually saves draft (PATCH request)
- **Publish** button (last step): Validates via API, then publishes if valid

## Autosave
- Debounced autosave (800ms) after any field change
- Shows "Saving..." status, then "Saved" (disappears after 2s)
- Errors shown in non-blocking banner, local state preserved

## Validation

### Client-side (inline)
- Required fields marked with *
- Errors shown under fields immediately
- Next button disabled if current step has errors

### Server-side
- `/validate` endpoint called before publish
- If invalid: field errors displayed, wizard jumps to first step with error
- Publish endpoint also validates; returns `ok:false` with `VALIDATION_FAILED` if invalid

## Images

### Upload Methods
1. **File upload**: Drag & drop or click to select
2. **URL**: Enter URL and click "Load from URL"

### Display Rules
- If `image.url` present: display image
- If `image.url` is null: show placeholder with `imageId` text
- After upload/from-url, if no URL provided, placeholder shown

### Actions
- Delete button per image
- Per-action status (uploading/error) shown globally in MVP

## Error Handling
- All API errors use envelope format
- Inline field errors for validation
- Non-blocking error banners for network/API errors
- Draft state preserved on errors

## Persistence
- Drafts saved to `storage/marketplace/ozon/tmp/{draftId}.json`
- GET after POST returns saved data (survives reload)
- Version incremented on each save
