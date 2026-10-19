# WP OG Meta

Lightweight Open Graph + Twitter Card tags so links to your site render with a proper title, description and image on Facebook, X, WhatsApp, LinkedIn and Telegram. Per-post overrides included; no schema.org (pair with a JSON-LD plugin like wp-schema-jsonld).

## How it picks values

| Context | Title | Description | Image |
|---|---|---|---|
| Single post/page | Post title (or override) | Override → excerpt → first ~155 chars of content | Override → featured image → site default |
| Home / archives | Site name / term title | Site default / term description | Site default |
| Search / 404 | "Search results for …" / "Page not found" | Site default | Site default |

Also outputs `article:published_time` / `article:modified_time` on singular views, `og:image:alt`, and a fallback `<meta name="description">`.

## Install

1. Copy `wp-og-meta.php` and `uninstall.php` into `wp-content/plugins/wp-og-meta/`
2. Activate the plugin
3. Go to **Settings → OG Meta**: set a default description and a 1200×630 fallback image
4. Per post/page: use the **OG / Twitter Preview** box in the editor sidebar to override title, description or image

## Developer hooks

| Hook | Type | What it does |
|---|---|---|
| `wogm_meta` | filter `(array $data)` | Override the final `title`, `description`, `image`, `url`, `type` before output |

## Testing

Paste any URL into the [Facebook Sharing Debugger](https://developers.facebook.com/tools/debug/) or the [X Card Validator](https://cards-dev.twitter.com/validator) to preview.

## Files

- `wp-og-meta.php` — the plugin
- `uninstall.php` — removes settings and per-post overrides

MIT licensed.
