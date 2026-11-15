# Marswalk API

Laravel API for location heartbeats, Mars hazard alerts, and AI-generated Marswalk route options.

## Local setup

1. Install PHP and Composer dependencies:

   ```sh
   composer install
   ```

2. Copy `.env.example` to `.env`, generate the Laravel key, set `APP_DEBUG=false` outside local development, configure `MARS_DATA_INGEST_TOKEN` and (outside local development) `FRONTEND_ALLOWED_ORIGINS`, and add the AI provider key as `GEMINI_API_KEY`. Keep provider secrets on the backend only.
3. Configure Reverb with `php artisan reverb:install`. Outside local development, set `REVERB_ALLOWED_ORIGINS` to the frontend host.
4. Apply migrations, seed the server-owned region catalog, and start the API and Reverb server in separate terminals:

   ```sh
   php artisan migrate
   php artisan db:seed
   php artisan serve --host=0.0.0.0
   php artisan reverb:start
   ```

An authenticated client may send `POST /api/heartbeats` with coordinates in latitude `[-90, 90]` and longitude `[-180, 180]`. Coordinates are user-reported; the API cannot obtain or verify a user's physical location. Heartbeats default to the Mars coordinate system; terrestrial tracking must explicitly send `coordinate_system: "earth"` and is kept out of Mars hazard calculations.

## API

Register with `POST /api/auth/register` using `name`, `email`, `password`, and `password_confirmation`; or sign in with `POST /api/auth/login`. Both return a Sanctum bearer token. Send it as `Authorization: Bearer <token>` on authenticated routes.

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/api/data/sources` | NASA/USGS source catalog and coverage notes |
| `GET` | `/api/data/catalog` | Mission types, risk tolerances, equipment, and route speed |
| `GET` | `/api/data/regions` | Seeded region catalog used by the frontend |
| `GET` | `/api/data/hazards` | List unexpired imported hazard records |
| `POST` | `/api/data/hazards` | Ingest up to 500 NASA/USGS hazard records |
| `GET` | `/api/data/terrain?min_lat=...&max_lat=...&min_lon=...&max_lon=...` | Read terrain cells in a bounding box |
| `POST` | `/api/data/terrain` | Ingest up to 500 terrain cells |
| `POST` | `/api/heartbeats` | Save location, evaluate weather alerts, update route progress |
| `GET` | `/api/heartbeats/latest` | Read the caller's latest location |
| `GET` | `/api/heartbeats` | Read up to 100 of the caller's locations |
| `GET` | `/api/alerts` | Read the caller's active proximity alerts |
| `POST` | `/api/assistant/chat` | Send a message to the authenticated, persistent Mars assistant |
| `GET` | `/api/assistant/conversation?conversation_id=...` | Read the caller's conversation history |
| `DELETE` | `/api/assistant/conversations/{id}` | Delete the caller's assistant conversation |
| `GET` | `/api/missions` | List the caller's persisted missions |
| `POST` | `/api/missions` | Create a mission and calculate its route metrics |
| `PUT` | `/api/missions/{id}` | Update a caller-owned mission |
| `DELETE` | `/api/missions/{id}` | Delete a caller-owned mission |
| `PATCH` | `/api/missions/{id}/status` | Update a mission's status |
| `PATCH` | `/api/missions/{id}/checklist/{itemId}` | Update a caller-owned checklist item |
| `POST` | `/api/routes/plan` | Create ranked route candidates |
| `POST` | `/api/routes/{id}/select` | Select a candidate; mapped hazard intersections require `acknowledge_hazards: true` |
| `GET` | `/api/routes/active` | Read the caller's active route |
| `POST` | `/api/routes/{id}/complete` | Complete an active route |
| `POST` | `/api/routes/{id}/abort` | Cancel an active route |
| `GET` | `/api/earth-trips` | List the caller's terrestrial road trips |
| `POST` | `/api/earth-trips` | Create a terrestrial trip and calculate its road route |
| `PATCH` | `/api/earth-trips/{id}/status` | Start or finish a terrestrial trip |
| `DELETE` | `/api/earth-trips/{id}` | Delete a terrestrial trip |
| `GET` | `/api/notifications/vapid-public-key` | Read the public Web Push VAPID key |
| `POST` | `/api/push-subscriptions` | Register a browser push subscription |
| `DELETE` | `/api/push-subscriptions` | Remove the current browser push subscription |

Route planning accepts:

```json
{
  "start": { "latitude": -4.5, "longitude": 137.4 },
  "destination": { "latitude": -4.6, "longitude": 137.6 }
}
```

Subscribe to the authenticated private Reverb channel `mars.user.{user_id}`. Listen for `.mars.alert` and `.mars.route.progress`.

The region catalog is populated by `MarsRegionSeeder` through `php artisan db:seed`; there is no demo account or frontend region fallback. Mission CRUD and operational alert/heartbeat data require Sanctum authentication.

## Terrestrial road routing

Configure `ROUTING_OSRM_URL` to point to a self-hosted OSRM-compatible API that has the required regional road graph, and set `ROUTING_OSRM_PROFILE` (normally `driving`). For example:

```dotenv
ROUTING_OSRM_URL=http://localhost:5000
ROUTING_OSRM_PROFILE=driving
```

The backend requests full GeoJSON road geometry and turn-by-turn steps from `/route/v1/{profile}/...`. GPS coordinates are sent only to this project's backend; the backend then contacts the configured routing service. No public routing service or straight-line fallback is used. If routing is unavailable or no road route exists, the API returns an error instead of creating an inaccurate trip. Run `php artisan migrate` after pulling the change.

## Browser push notifications

Generate one VAPID keypair and keep the private key only in the backend environment:

```powershell
php artisan tinker --execute="dump(\Minishlink\WebPush\VAPID::createVapidKeys());"
```

Set `WEBPUSH_VAPID_PUBLIC_KEY`, `WEBPUSH_VAPID_PRIVATE_KEY`, and `WEBPUSH_VAPID_SUBJECT` in the backend `.env`. Use the same keypair across deployments and serve the frontend over HTTPS (or localhost). Users then enable notifications in **Definições → Notificações**. The browser's push relay receives only generic notification text; no GPS coordinates, trip names, or destinations are included in push payloads. The app sends notifications for Mars risk-alert changes and trip lifecycle events; expired subscriptions are removed automatically.

## Frontend connection

The vanilla JavaScript frontend is in `../frontend`. Configure its `.env` from `../frontend/.env.example`:

```dotenv
VITE_API_BASE_URL=http://localhost:8000/api
VITE_REVERB_APP_KEY=<copy the public REVERB_APP_KEY value>
VITE_REVERB_HOST=127.0.0.1
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
```

When `APP_ENV=local`, the API and Reverb accept connections from any origin, regardless of the frontend IP or port. API CORS credentials remain disabled; authenticated requests use bearer tokens. In deployed environments, set `FRONTEND_ALLOWED_ORIGINS` to the exact frontend origin(s) and `REVERB_ALLOWED_ORIGINS` to the allowed host(s). Restart the API and Reverb after changing these settings. Reverb sends warning, alarm, clear, and route-progress events. Chat turns are stored by Laravel AI SDK in the database and are scoped to their owning user; the authenticated delete endpoint removes both conversation and message rows.

## NASA/USGS data and limitations

Send ingestion requests with `X-Mars-Data-Token: <MARS_DATA_INGEST_TOKEN>`. Source URLs are restricted to the configured NASA and USGS hosts. Hazard records have a source ID, category, coordinates, radius, observation/expiry times, and citation URL. Terrain records include elevation, slope, roughness, and cell resolution.

Route planning sends exact endpoints and nearby imported NASA/USGS terrain and hazard records to the `App\Ai\Agents\AIRouterPlanner` agent through Laravel AI SDK. The agent generates up to three route geometries, ordered by suitability, with science value, rationale, and uncertainties. The default model is `gemini-3.5-flash-lite`; configure the provider/model with `MARS_AI_PROVIDER`, `MARS_AI_MODEL`, `MARS_AI_TIMEOUT`, and its provider API key. AI requests send the supplied route coordinates and imported evidence to the configured external model provider. Invalid routes and routes with mismatched endpoints fail explicitly; there is no silent deterministic fallback. Route corridors over 200 hazard records or 300 terrain cells are rejected rather than silently truncating potentially important data.

The MVP does not automatically download Mars Trek or USGS map layers, so those datasets must be processed and ingested before they influence a route. The AI can only use records in a 50 km corridor around the direct route and does not have a full digital elevation model; candidate paths are advisory, not a certified safe route. A candidate that intersects an imported hazard requires an explicit confirmation before selection. Server-side spherical-distance calculations, hazard intersections, the 15/50 km alert thresholds, endpoint checks, and route-progress projection remain deterministic and do not depend on probabilistic AI.

Weather warnings and alarms only use unexpired ingested records categorized as `weather`: within 50 km is a warning, and within 15 km is an alarm. NASA's InSight weather data is archival, not a live forecast; there is no real-time Mars weather feed configured. NASA Worldview observes Earth and is not used for Mars hazards. The Perseverance gallery and other supplied links are reference sources; this MVP does not scrape or proxy imagery.
