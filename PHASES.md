# LeadBreave: Build Phases (Laravel)

Port of the **LeadMap** WordPress plugin (`~/Sites/gmap-scrapper/leadmap`, v0.7.0) to a standalone Laravel application. We keep what works, fix the known weaknesses, and build the parts of the workflow that were never finished.

> **Status:** Draft. Review and correct the steps before building starts.

---

## The workflow we are building (from `requiments.md`)

1. Search Google Maps by industry and location (ZIP).
2. Collect for every result: business name, phone, website, email, all social links and YouTube. If there is no website or email, allow manual entry.
3. Manual verification and audit: confirm we can reach the business, then note its main problem (Design, Speed, SEO, Backlinks). PageSpeed runs from a **manual button**.
4. Write a message from the audit, with the **major issues first** and the minor issues below.
5. Send the email.
6. Send an **automatic follow-up after 2 business days** if there is no reply.
7. Propose a meeting (Zoom, Google Meet or a booking link).

## What LeadMap already gives us vs. what is missing

| Area | LeadMap today | In LeadBreave |
| --- | --- | --- |
| Google Maps search | Places API (New) Text Search, 1 page per cron job | Keep. Add grid/ZIP tiling to get past the 60-result cap. Use real queues. |
| Fields | name, phone, website, address, rating, reviews, category, maps URL | Same, **plus socials and YouTube** |
| Email crawl | Good extractor (mailto, JSON-LD, Cloudflare decode, confidence score) | Port the logic as-is, run it in a queue |
| Manual entry / CSV import | None | **New** |
| PageSpeed | Exists, but the browser worker is buggy and JSON writes can be lost | Rebuild as a queued job behind a manual button |
| SEO and tech checks, scoring | Exists | Port it, storing results in columns/tables instead of one JSON blob |
| Audit (tags, primary issue, notes) | Exists. Backlinks is a manual tag only. | Keep. Add **major/minor severity** per issue. |
| Templates and merge tags | Plain text, one template per issue/step | Keep. Add the major/minor issue blocks. |
| Sending | `wp_mail`, manual, with compliance checks | Laravel Mail plus a provider API, queued, Message-ID stored |
| Follow-ups | **Not built** | **New:** business-day sequence engine |
| Reply / bounce detection | **Not built** | **New:** webhooks or IMAP |
| Meetings | One link in the settings | Booking link, plus optional Zoom/Meet/Calendly integration |
| Dashboard / pipeline | Status counts only. Most statuses are never set. | **New:** Kanban pipeline and stats |

---

## Decisions to confirm before Phase 0

These are my recommendations. Change any of them and I will adjust the phases.

| Decision | Recommendation | Why |
| --- | --- | --- |
| Laravel version | Latest stable (13.x), PHP 8.3+ | Long support window |
| Admin UI | **Filament 4** (Livewire) | Fastest way to build an internal tool: tables, filters, bulk actions and forms are built in. The alternative is Inertia + Vue, which means more custom code. |
| Database | MySQL 8 (or Postgres 16) | Same as the WP data, so migration is simple |
| Queue / cache | **Redis + Laravel Horizon** | Real queues, atomic locks and rate limiters. This fixes LeadMap's biggest problems. |
| Mail provider | Postmark or Amazon SES (on a separate sending domain) | API sending, Message-ID, and bounce/inbound webhooks |
| Reply detection | Provider inbound webhook, with IMAP polling as a fallback | Works without needing a provider that supports inbound mail |
| Users | Multi-user with roles (admin, researcher, sender) | `owner_id` already exists in LeadMap but was never used |
| Hosting | VPS with Forge/Ploi, or Docker | Horizon and the scheduler need a long-running process |

---

## Phase 0: Foundation

**Goal:** an empty app that runs, with good tooling and the infrastructure in place.

- [ ] Create the Laravel project, `.env.example`, Sail/Docker (app, MySQL, Redis, Mailpit).

- [ ] Install Filament, Horizon, Pest, Larastan (level 6+), Pint, Laravel Telescope (dev only).

- [ ] Auth, users and roles/permissions (spatie/laravel-permission): `admin`, `researcher`, `sender`.

- [ ] A settings store (`spatie/laravel-settings` or a `settings` table). API keys are encrypted with Laravel's `encrypted` cast and never sent to the browser.

- [ ] Queues: `search`, `enrich`, `pagespeed`, `mail`, `default`, each with its own concurrency in Horizon.

- [ ] CI with GitHub Actions: Pint, Larastan, Pest.

**Done when:** you can log in, Horizon is green, and CI passes.

## Phase 1: Data model

**Goal:** a normalized schema. No more one large `enrichment_json` blob that several jobs overwrite.

Tables (main columns only):

- [ ] 

- `searches`: industry, location, zip, radius_m, status, tiling mode, counts, api_cost, stop_reason, started/completed_at
- `search_tiles`: search_id, lat, lng, radius, next_page_token, status (one row per grid cell)
- `leads`: place_id (unique), name, phone, phone_e164, website, domain, address parts, lat/lng, category, rating, review_count, maps_url, status, owner_id, source (`places` / `manual` / `csv`), timestamps
- `lead_search` (pivot): a lead found again by a new search is **linked** to it, not skipped
- `lead_emails`: email, source, confidence, is_role, is_primary, verified_status
- `lead_socials`: platform (facebook, instagram, linkedin, x, tiktok, youtube, pinterest, yelp…), url
- `website_checks`: lead_id, type (`crawl`, `seo`, `tech`), result JSON, checked_at. Each job writes its **own row**, so writes cannot be lost.
- `pagespeed_runs`: lead_id, strategy (mobile/desktop), score, lcp, fcp, tbt, cls, si, screenshot_path, status, error, ran_at
- `audits`: lead_id, verified_by, is_reachable, primary_issue, notes, fit_score, outcome
- `audit_issues`: audit_id, tag (design/speed/seo/backlinks/mobile/security/…), **severity (major/minor)**, note
- `templates`, `sequences`, `sequence_steps` (delay in business days, template_id)
- `outreach_messages`: lead_id, sequence_step_id, to, subject, body, status, provider_message_id, message_id header, scheduled_for, sent_at, opened_at, replied_at, bounced_at
- `suppressions`, `events` (activity log), `meetings`

- [ ] Migrations, models, enums for the statuses (PHP backed enums), factories, seeders.

- [ ] Indexes are planned for the real queries: (status, next_action_at), domain, phone_e164, and a FULLTEXT index on name/address for search (instead of `LIKE '%q%'`).

**Done when:** migrations run, factories seed 1,000 realistic leads, and model tests pass.

## Phase 2: Lead discovery (Google Maps search)

**Goal:** step 1 of the workflow, fast and with full coverage.

- [ ] A `PlacesClient` service: Places API (New) `searchText` with a field mask and HTTP retries/backoff. Port from `Google_Places_Provider.php`.

- [ ] Geocode the ZIP/location (cached for 30 days, **and the cost counted**).

- [ ] **Grid tiling:** split the area into cells so each search can go beyond 60 results. One `search_tiles` row per cell.

- [ ] A `RunSearchTile` job per page, holding a `Cache::lock` per tile so a page is never fetched (and billed) twice.

- [ ] Dedupe in **one batched query per page** (place_id, then domain, then phone). Existing leads are linked to the search and their Places data is refreshed.

- [ ] Phone normalization with `libphonenumber` (proper E.164 for any country).

- [ ] Cost guard: a per-search cap and a monthly cap, counted from an `api_calls` ledger (not from `searches.created_at`).

- [ ] UI: a New Search form with a map preview (Leaflet), and live progress through Livewire polling or Reverb broadcasting.

- [ ] Re-run fully resets the counters.

**Done when:** a search for "plumber 10001" returns more than 60 deduped leads, with no double billing, and progress is visible live.

## Phase 3: Enrichment (emails, socials, YouTube) and manual entry

**Goal:** step 2 of the workflow.

- [ ] Port `Website_Crawler`, `Email_Extractor` and `Url_Guard` (SSRF guard) to Laravel's HTTP client: robots.txt, contact/about page discovery, 2 MB cap, redirect re-validation, a delay per host.

- [ ] **New:** a social link extractor that reads `<a href>`, JSON-LD `sameAs` and the footer/header for Facebook, Instagram, LinkedIn, X, TikTok, YouTube (channel, @handle or video), Pinterest and Yelp. Normalize the URLs and skip share links.

- [ ] Tech detection and SEO checks (ported), each written to its own `website_checks` row.

- [ ] Run `EnrichLead` automatically after a lead is created (on the `enrich` queue, with a per-domain rate limiter). Bulk "re-enrich" from the table.

- [ ] Handle broken SSL: retry without verification, and record "SSL broken" as an issue instead of marking the site unreachable.

- [ ] **Manual entry:** edit the website, emails and socials on a lead (marked `source=manual`). Leads with no website or email go into a "Needs manual info" queue/filter.

- [ ] **Manual lead creation and CSV import** (with column mapping and the same dedupe).

**Done when:** each lead shows its emails (with confidence), socials and YouTube, and the missing ones can be filled in by hand.

## Phase 4: Audit and verification

**Goal:** step 3 of the workflow.

- [ ] An audit screen per lead: site screenshot, the automated findings, and the issue suggestions (port `Issue_Tags::evidenced()`).

- [ ] A **"Run PageSpeed" button** (mobile and desktop) that queues a job on the `pagespeed` queue (Redis rate limit, e.g. 4/min). The UI shows a pending state and updates when it finishes. **No Lighthouse call inside the web request.**

- [ ] Store screenshots on a private disk, served through signed URLs.

- [ ] Verification fields: "Reachable?" (yes/no), contact person, preferred channel.

- [ ] Issue picker: Design / Speed / SEO / Backlinks / Mobile / Security / Content / Conversion. Each issue is marked **Major or Minor** and gets a short note.

- [ ] Primary problem notes (required). Optional "Draft with AI" (Claude or Gemini, with the key sent in a header, never in the URL).

- [ ] Optional backlinks data from an external API (DataForSEO / Moz / Ahrefs). Kept behind a setting and off by default.

- [ ] A keyboard-driven audit queue (next/skip), ported from the LeadMap Audit screen.

- [ ] Fit score (ported `Fit_Scorer`).

**Done when:** an auditor can work through the queue, run PageSpeed on demand and save a complete audit with major and minor issues.

## Phase 5: Message builder

**Goal:** step 4 of the workflow.

- [ ] Templates per primary issue, with merge tags (port the LeadMap list and add `{{major_issues}}`, `{{minor_issues}}`, `{{social_links}}`, `{{meeting_link}}`).

- [ ] `{{major_issues}}` renders as a highlighted list at the top and `{{minor_issues}}` as a shorter list below.

- [ ] A composer that pre-fills from the template and the audit, can be edited per lead, and shows a live preview. Plain text by default, with a light HTML option.

- [ ] Seed the templates from the 7 LeadMap step-1 templates.

**Done when:** opening an audited lead gives a ready-to-send email with the issues in the right order.

## Phase 6: Sending

**Goal:** step 5 of the workflow, deliverable and compliant.

- [ ] Laravel Mail with Postmark/SES. Each send is a `SendOutreachMessage` job on the `mail` queue.

- [ ] Store the provider message ID and our own `Message-ID` header (needed for reply threading).

- [ ] Port the checks from `Send_Service`: sender identity and postal address set, valid email, region rules (EU/UK/CA), suppression list, not already sent for this step, daily cap. These are done **inside a DB transaction with a lock**, so concurrent sends cannot go over the cap or double-send.

- [ ] Sending window (e.g. 9:00 to 17:00 in the lead's timezone, weekdays only) and random spacing between sends.

- [ ] Compliance footer, `List-Unsubscribe` with one-click, and a signed unsubscribe route keyed to the **message**, not the current email.

- [ ] Bulk "send to selected" for audited leads.

**Done when:** emails arrive in Mailpit (dev) and the real inbox (staging), and unsubscribing adds the address to the suppression list.

## Phase 7: Follow-ups and reply detection

**Goal:** step 6 of the workflow.

- [ ] Sequences: step 1 is the initial email. Step 2 follows **after 2 business days** (configurable). An optional step 3 follows later.

- [ ] A business-day calculator: skips weekends and a configurable holiday list, and respects the sending window.

- [ ] A scheduler (`schedule:run` every minute) picks up due steps → `SendOutreachMessage`.

- [ ] Follow-ups are sent **as replies in the same thread** (`In-Reply-To` / `References`).

- [ ] Reply detection: the provider's inbound webhook, with IMAP polling as a fallback. A reply sets the lead to `replied` and **stops the sequence**.

- [ ] Bounce and spam-complaint webhooks set the lead to `bounced`, add it to the suppression list and stop the sequence.

- [ ] Manual "stop sequence" and "mark replied" actions.

**Done when:** a lead with no reply automatically gets the follow-up 2 business days later, and a reply stops it.

## Phase 8: Meetings and pipeline

**Goal:** step 7 of the workflow, and closing the loop.

- [ ] A "Propose meeting" template/action that inserts the booking link (Calendly / Cal.com / Google Calendar appointment page / Zoom Scheduler).

- [ ] Optional integration: create a Google Meet event (Google Calendar API) or a Zoom meeting (Zoom API) from the lead and send the invite.

- [ ] A booking webhook (Calendly or Cal.com) sets the lead to `meeting_booked` and creates a `meetings` row.

- [ ] Pipeline board (Kanban): new, enriched, audited, contacted, replied, meeting booked, won/lost. Statuses can be changed by hand.

- [ ] A lead activity timeline built from `events`.

**Done when:** a booked meeting appears on the lead automatically and the pipeline shows every stage.

## Phase 9: Dashboard and reporting

- [ ] Widgets: leads per search, enrichment success rate, audits per day, emails sent / replied / booked, reply rate per template, API spend this month.

- [ ] CSV export with formula-injection escaping, streamed in chunks.

- [ ] Saved filters (industry, ZIP, score, status).

## Phase 10: Migration from LeadMap and launch

- [ ] An artisan command `leadmap:import` that reads the WP `wp_leadmap_*` tables through a second DB connection and maps them to the new schema (unpacking `enrichment_json` into the new tables, and converting the CSV `issue_tags` into `audit_issues` rows).

- [ ] Copy the screenshots to the private disk.

- [ ] Performance pass: check for N+1 queries (Laravel `preventLazyLoading`), add missing indexes, Horizon tuning, use Octane only if it is needed.

- [ ] Security pass: rate-limit the public routes, verify webhook signatures, run a CSP/headers check and dependency audit.

- [ ] Backups, error tracking (Sentry/Flare), uptime check.

- [ ] Deploy to production.

---

## How we fix LeadMap's known problems

| LeadMap problem | Fix in LeadBreave |
| --- | --- |
| WP-Cron runs long crawls and Lighthouse calls inside page loads | Every slow task is a Horizon job on its own queue |
| The browser PageSpeed worker always reschedules itself (double rate-limit claim) | One queued job and one Redis rate limiter |
| Lost updates on `enrichment_json` / `log_json` | Separate tables/rows per check, and a `search_tiles` row per cell |
| Non-atomic locks/rate limits in `wp_options` | `Cache::lock` and `RateLimiter` on Redis |
| A page token can be fetched twice (double billing) | A lock per tile, and the job is unique per tile and page |
| 60-result cap shown silently | Grid tiling, and the UI shows the real coverage |
| Dedupe is N+1, and duplicates are dropped instead of linked | One batched query per page, plus the `lead_search` pivot |
| Geocode cost not counted; spend counted by search creation date | An `api_calls` ledger with a timestamp per call |
| CSV formula injection | Escape `= + - @` at the start of cells |
| Public, predictable screenshot URLs | Private disk with signed URLs |
| API key in the URL query string | Sent in a header |
| Cap/duplicate checks can race | Transaction with a row lock |
| Unsubscribe breaks if the email changes | Token tied to the message ID |
| Follow-ups, replies, socials, CSV import, pipeline never built | Phases 3, 7 and 8 |

## Suggested order and size

| Phase | Depends on | Rough size |
| --- | --- | --- |
| 0 Foundation | – | S |
| 1 Data model | 0 | M |
| 2 Discovery | 1 | M |
| 3 Enrichment | 2 | L |
| 4 Audit | 3 | L |
| 5 Message builder | 4 | S |
| 6 Sending | 5 | M |
| 7 Follow-ups | 6 | M |
| 8 Meetings and pipeline | 6 | M |
| 9 Dashboard | 7, 8 | S |
| 10 Migration and launch | all | M |

Each phase ends with working features, tests and a short demo before the next one starts.