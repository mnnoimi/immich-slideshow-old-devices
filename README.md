# Immich Slideshow for Old Devices

A lightweight PHP slideshow application for [Immich](https://immich.app) photo albums, built to run on older devices and browsers — iPad 2, old Android tablets, Fully Kiosk Browser, ECMAScript 2009 environments. Displays photos full-screen with overlays, navigation, and Ken Burns motion.

> [!NOTE]
> If your device supports [Immich Kiosk](https://github.com/damongolding/immich-kiosk), consider using it — it has more features. This project exists for devices too old to run Kiosk.
>
> [!IMPORTANT]
> **This project is not affiliated with [Immich](https://github.com/immich-app/immich)**

## Features

- Full-screen slideshow from one or more Immich albums
- Overlay: live digital clock, date, photo date/time, recognized people, location
- Left/right tap or click zones for manual navigation
- Ken Burns subtle zoom/pan motion between slides
- Progress bar showing time remaining on current photo
- Photo counter (e.g. `5 / 79`)
- Pause/resume by tapping the center of the screen
- Album content refresh without page reload (checks every 5 minutes and at end of each cycle)
- Random or sequential order
- Filter by orientation (landscape / portrait / all)
- Crop to fill screen or fit with letterbox bars
- Arabic and English locale for date/day text
- Browser fullscreen mode
- WebP → JPEG conversion for old browser compatibility
- All options configurable via `.env` **or** URL parameters
- Management UI for selecting albums (`/management.php`)
- Remote control keyboard support

## Requirements

- Docker
- Access to an Immich server
- Immich API key

## Quick Start

```bash
git clone https://github.com/yourusername/immich-slideshow-old-devices.git
cd immich-slideshow-old-devices
cp .env.example .env
# Edit .env — set IMMICH_URL and IMMICH_API_KEY at minimum
```

### Run with Docker

```bash
# Build and run
docker build -t immich-slideshow .
docker run -d \
  --name immich-slideshow \
  -p 8080:80 \
  --env-file .env \
  -v ./public:/var/www/html \
  immich-slideshow
```

The slideshow is available at `http://localhost:8080`

### Run with Docker Compose (if available)

```bash
docker compose up -d --build
```

## Configuration

All settings can be set in `.env` **or** passed as URL query parameters. URL parameters always take precedence.

Only `IMMICH_URL` and `IMMICH_API_KEY` are required — all other settings have defaults.

### Environment Variables & URL Parameters

| Variable | URL param | Description | Default | Required |
| --- | --- | --- | --- | --- |
| `IMMICH_URL` | — | Immich server base URL | — | **Yes** |
| `IMMICH_API_KEY` | — | Immich API key | — | **Yes** |
| `ALBUM_ID` | `album_id` | Album ID(s) to display, comma-separated | — | No* |
| `CAROUSEL_DURATION` | `duration` | Seconds each photo is shown | `5` | No |
| `CSS_BACKGROUND_COLOR` | `background` | Background colour (name or hex) | `#000000` | No |
| `RANDOM_ORDER` | `random` | Shuffle photos randomly | `false` | No |
| `STATUS_BAR_STYLE` | `status_bar` | iOS status bar style (`default` / `black` / `black-translucent`) | `black-translucent` | No |
| `IMAGES_ORIENTATION` | `orientation` | Filter by orientation (`all` / `landscape` / `portrait`) | `all` | No |
| `CROP_TO_SCREEN` | `crop` | Crop to fill (`true`) or fit with bars (`false`) | `true` | No |
| `KEN_BURNS` | `ken_burns` | Subtle zoom/pan motion on each photo | `true` | No |
| `LOCALE` | `locale` | Date language (`en` / `ar`) | `en` | No |

> \* `ALBUM_ID` is optional in `.env` if you always supply `?album_id=` in the URL.  
> `IMMICH_URL` and `IMMICH_API_KEY` must be set in `.env` — they are never accepted as URL parameters for security.

### Accepted values

| Param | Valid values |
| --- | --- |
| `random` | `true` / `false` |
| `orientation` | `all` / `landscape` / `portrait` |
| `status_bar` | `default` / `black` / `black-translucent` |
| `crop` | `true` / `false` |
| `ken_burns` | `true` / `false` |
| `locale` | `en` / `ar` |

## Examples

### Minimal — just open the slideshow

Set `IMMICH_URL`, `IMMICH_API_KEY`, and `ALBUM_ID` in `.env`, then visit:

```text
http://localhost:8080/
```

---

### Album ID via URL (no ALBUM_ID in .env needed)

```text
http://localhost:8080/?album_id=7adeee1d-c6f3-42b9-97a1-1ca5b29db35d
```

---

### Multiple albums, random order, 10-second slides

```text
http://localhost:8080/?album_id=abc123,def456&random=true&duration=10
```

---

### Landscape photos only, cropped to fill screen, Ken Burns off

```text
http://localhost:8080/?orientation=landscape&crop=true&ken_burns=false
```

---

### Arabic locale, portrait photos, letterbox fit

```text
http://localhost:8080/?locale=ar&orientation=portrait&crop=false
```

---

### Two iPads, same server — different albums and durations

iPad in the living room:

```text
http://localhost:8080/?album_id=living-room-album-id&duration=7&crop=true
```

iPad in the bedroom:

```text
http://localhost:8080/?album_id=bedroom-album-id&duration=12&orientation=portrait
```

## Management UI

Browse and select albums visually at:

```text
http://localhost:8080/management.php
```

## Fullscreen on iOS (iPad / iPhone)

The browser Fullscreen API is not supported on iOS Safari. To get true full-screen on an iPad:

1. Open the slideshow URL in Safari
2. Tap the Share button → **Add to Home Screen**
3. Launch from the home screen icon — it opens as a standalone full-screen app

## Keyboard / Remote Control

| Key | Action |
| --- | --- |
| `→` / `↓` | Next photo |
| `←` | Previous photo |
| `↑` | Reload page |
| `Enter` | Pause / Resume |

## Docker Compose Reference

```yaml
services:
  immich-slideshow:
    build: .
    ports:
      - "8080:80"
    env_file: .env
    volumes:
      - ./public:/var/www/html
```

## License

[MIT License](LICENSE)
