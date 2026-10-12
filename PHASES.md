# LeadBreave: Build Phases (Laravel)

Port of the **LeadMap** WordPress plugin (`~/Sites/gmap-scrapper/leadmap`, v0.7.0) to a standalone Laravel application. We keep what works, fix the known weaknesses, and build the parts of the workflow that were never finished.

> **Status:** Phases 0–2 are done. Phases 3 onward are a plan: review and correct them before each one starts.

---

## The workflow we are building (from `requiments.md`)

1. Search Google Maps by industry and location (ZIP).
2. Collect for every result: business name, phone, website, email, all social links and YouTube. If there is no website or email, allow manual entry.
3. Manual verification and audit: confirm we can reach the business, then note its main problem (Design, Speed, SEO, Backlinks). PageSpeed runs from a **manual button**.
4. Write a message from the audit, with the **major issues first** and the minor issues below.
5. Send the email.
6. Send an **automatic follow-up after 2 business days** if there is no reply.
7. Propose a meeting (Zoom, Google Meet or a booking link).

## Phase map

| # | Phase | Workflow step | What it delivers | Size | Status |
| --- | --- | --- | --- | --- | --- |
| 0 | [Foundation](#phase-0-foundation) | – | Auth, roles, settings, queues, CI | S | ✅ Done |
| 1 | [App shell and design system](#phase-1-app-shell-and-design-system) | – | Aurora theme, login, dashboard shell | S | ✅ Done |
| 2 | [Core data model](#phase-2-core-data-model) | – | Leads, searches, emails, socials, events | M | ✅ Done |
| 3 | [Search](#phase-3-search-google-maps-discovery) | 1 | Google Maps search with grid tiling | L | Next |
| 4 | [Leads workspace](#phase-4-leads-workspace) | 2 | Lead table, lead page, manual entry, CSV import | M | |
| 5 | [Website check](#phase-5-website-check) | 2 | Crawl: emails, socials, YouTube, tech, SEO | L | |
| 6 | [PageSpeed](#phase-6-pagespeed) | 3 | On-demand mobile/desktop Lighthouse runs | M | |
| 7 | [Scoring](#phase-7-scoring) | 3 | Opportunity score and fit score | S | |
| 8 | [Triage](#phase-8-triage) | 3 | Fast visual first pass with verdicts | S | |
| 9 | [Audit](#phase-9-audit) | 3 | Verification, major/minor issues, problem note | L | |
| 10 | [Templates](#phase-10-templates) | 4 | Template library, merge tags, issue blocks | M | |
| 11 | [Outreach](#phase-11-outreach) | 4–5 | Composer, compliance, sending, outbox | L | |
| 12 | [Follow-ups](#phase-12-follow-ups) | 6 | Business-day sequences, threaded replies | M | |
| 13 | [Replies and deliverability](#phase-13-replies-and-deliverability) | 6 | Reply detection, bounces, suppression, domain health | M | |
| 14 | [Meetings](#phase-14-meetings) | 7 | Booking link, Calendly/Cal.com, Zoom/Meet | M | |
| 15 | [Pipeline and activity](#phase-15-pipeline-and-activity) | all | Kanban board, timeline, tasks, ownership | M | |
| 16 | [Dashboard and reports](#phase-16-dashboard-and-reports) | all | Live stats, reports, exports | S | |
| 17 | [LeadMap import](#phase-17-leadmap-import) | – | Bring existing WordPress data across | M | |
| 18 | [Hardening and launch](#phase-18-hardening-and-launch) | – | Performance, security, ops, production | M | |

```
0 ─ 1 ─ 2 ─ 3 ─ 4 ─ 5 ─┬─ 6 ─┐
                       │     ├─ 7 ─ 8 ─ 9 ─ 10 ─ 11 ─┬─ 12 ─ 13 ─┐
                       └─────┘                       └─ 14 ──────┴─ 15 ─ 16 ─ 17 ─ 18
```

Phases 6 and 7 can run in parallel with late Phase 5 work. Phase 14 can start as soon as 11 is done.

## Rules for every phase

- **Each phase brings its own tables.** Phase 2 creates the core schema only. Later phases add their own migrations, so a phase can be reviewed and shipped alone.
- **Slow work runs in a job, never in a web request.** Each job goes on its named queue (`search`, `enrich`, `pagespeed`, `mail`, `default`).
- **Each job writes its own rows.** No shared JSON blob that two jobs can overwrite.
- **Every external call is logged** in `api_calls` (provider, endpoint, cost units, status, duration), so spend and failures can be reported.
- **Every state change on a lead writes a `lead_events` row** (through `Lead::transitionTo()`), so the timeline in Phase 15 needs no backfill.
- **External APIs are faked in tests** (`Http::fake()`), and no test touches the network.
- **The dashboard is wired up as you go:** when a phase makes a pipeline stage real, it replaces that stage's `0` in `PipelineOverview::stages()` with a real count.
- **Done means demoed:** each phase ends with working screens, passing tests (Pest, Larastan, Pint) and a short demo.

---

## Phase 0: Foundation

**Status:** ✅ Done (2026-10-12)

**Goal:** an empty app that runs, with good tooling and the infrastructure in place.

- [x] Create the Laravel project, `.env.example`, Sail/Docker (app, MySQL, Redis, Mailpit).
- [x] Install Filament, Horizon, Pest, Larastan (level 6+), Pint, Laravel Telescope (dev only).
- [x] Auth, users and roles/permissions (spatie/laravel-permission): `admin`, `researcher`, `sender`.
- [x] A settings store (`spatie/laravel-settings`). API keys are encrypted and never sent to the browser.
- [x] Queues: `search`, `enrich`, `pagespeed`, `mail`, `default`, each with its own concurrency in Horizon.
- [x] CI with GitHub Actions: Pint, Larastan, Pest (plus an asset build).

> Notes: Filament **5** was installed (it replaced 4 as the current major). Redis uses `predis`, so no PHP extension is needed. Telescope only loads in `local`. API keys are managed on a write-only page (Settings > API keys). One Google key covers Places API (New), Geocoding and PageSpeed Insights. Horizon needs Redis, so run it through Sail.

## Phase 1: App shell and design system

**Status:** ✅ Done (2026-10-12)

**Goal:** a branded, finished-looking shell that every later screen fits into.

- [x] "Aurora" theme: aurora background, frosted-glass surfaces, grain texture, violet primary, Plus Jakarta Sans. Animations stop when the device is set to reduce motion.
- [x] Custom login: product pitch on the left, frosted sign-in card on the right, mobile layout.
- [x] Sidebar layout: no topbar, full-width content, user menu in the sidebar footer.
- [x] Dashboard: a hero with greeting and KPIs, a pipeline funnel, a setup checklist (admins) and system status (admins).
- [x] Brand mark, logo and favicon.

**Carry forward:** new screens use Filament components and the theme tokens in `resources/css/filament/admin/theme.css`. No new colours or one-off styles without a reason.

---

## Phase 2: Core data model

**Status:** ✅ Done (2026-10-12)

**Goal:** the shared schema that search, enrichment and the lead screens all build on.

> Notes: the activity table is `lead_events` (model `LeadEvent`), to avoid confusion with Laravel's own events. Emails and socials share one `DataSource` enum (`crawl`, `manual`, `csv`). `status` is not mass assignable, so it can only change through `Lead::transitionTo()`, which re-reads the status under a row lock. `search_tiles` gained `parent_id` and `depth` for splitting crowded cells in Phase 3. The dashboard's **Found** count is live already (planned for Phase 3). Demo data: `php artisan db:seed --class=DemoSeeder` (about 3 s, also run by `migrate:fresh --seed` in `local`).

**Tables**

| Table | Main columns |
| --- | --- |
| `searches` | `user_id`, `industry`, `location`, `zip`, `country`, `center_lat/lng`, `radius_m`, `tiling` (`single`, `grid`), `status`, `tiles_total/done`, `results_found`, `leads_created`, `leads_linked`, `api_cost_units`, `stop_reason`, `started_at`, `completed_at` |
| `search_tiles` | `search_id`, `lat`, `lng`, `radius_m`, `page`, `next_page_token`, `status`, `results`, `error`, `attempts` |
| `leads` | `place_id` (unique, nullable), `name`, `phone`, `phone_e164`, `website`, `domain`, `street`, `city`, `region`, `postal_code`, `country`, `lat/lng`, `timezone`, `category`, `rating`, `review_count`, `maps_url`, `status`, `owner_id`, `source` (`places`, `manual`, `csv`), `opportunity_score`, `fit_score`, `next_action_at`, `last_contacted_at` |
| `lead_search` | pivot: `lead_id`, `search_id`, `rank`, `was_new` |
| `lead_emails` | `lead_id`, `email`, `source` (`crawl`, `manual`, `csv`), `found_on_url`, `confidence` 0–100, `is_role`, `is_primary`, `verified_status` |
| `lead_socials` | `lead_id`, `platform`, `url`, `handle`, `source` |
| `lead_events` | `lead_id`, `user_id`, `type`, `data` (JSON), `created_at` |
| `api_calls` | `provider`, `endpoint`, `cost_units`, `status`, `duration_ms`, `related_type/id`, `created_at` |

**Enums (PHP backed)**

- `LeadStatus`: `new`, `enriching`, `enriched`, `needs_info`, `triaged`, `audited`, `not_a_fit`, `ready`, `contacted`, `followed_up`, `replied`, `meeting_booked`, `won`, `lost`, `bounced`, `unsubscribed`
- `LeadSource`, `SearchStatus`, `TileStatus`, `SocialPlatform` (facebook, instagram, linkedin, x, tiktok, youtube, pinterest, yelp, nextdoor, google_business)

**Work**

- [x] Migrations, models with casts and relationships, factories with realistic states (`withWebsite()`, `withoutWebsite()`, `withEmail()`, `withSocials()`, `inCategory()`, `near()`, `audited()`).
- [x] A `LeadStatus` state machine: the allowed transitions live in one place, and every transition writes a `lead_events` row.
- [x] Indexes for the real queries: (`status`, `next_action_at`), `domain`, `phone_e164`, (`owner_id`, `status`), `fit_score`, and a FULLTEXT index on `name` and `city` (MySQL), with a `LIKE` fallback for SQLite in tests.
- [x] Seeder: 1,000 realistic leads across 5 searches, for demos and performance checks.
- [x] `Model::preventLazyLoading()` stays on outside production (via `Model::shouldBeStrict()`).

**Done when:** `migrate:fresh --seed` builds 1,000 leads, the state-machine tests pass, and illegal transitions throw.

---

## Phase 3: Search (Google Maps discovery)

**Goal:** workflow step 1. Find every business of a type in an area, fast, without paying twice.

**Backend**

- [ ] `PlacesClient`: Places API (New) `searchText` with a strict field mask (only the fields we store), retries with backoff on 429/5xx, and the shared Google key (`ApiSettings::$google_api_key`) sent in the `X-Goog-Api-Key` header. Ported from `Google_Places_Provider.php`.
- [ ] Geocoding (Geocoding API, same Google key): ZIP/location to a centre point and bounds, cached for 30 days. **Its cost is logged** in `api_calls`. This API only accepts the key as a `key` query parameter, so request URLs are redacted before they are logged or reported.
- [ ] Grid tiling: split the bounds into cells sized for the industry's density (dense for "restaurant", coarse for "roofer"). One `search_tiles` row per cell. Cells that hit the 60-result cap are split again, up to a depth limit.
- [ ] `RunSearchTile` job on the `search` queue: one page per run, a `Cache::lock` per tile and page (so a page is never fetched or billed twice), and the next page as a new job.
- [ ] `IngestPlaces`: dedupe each page in **one batched query** (place_id, then domain, then E.164 phone). New leads are created; existing ones are linked through `lead_search` and their Places data refreshed.
- [ ] Phone normalization with `libphonenumber` (`propaganistas/laravel-phone`), and the timezone derived from lat/lng.
- [ ] Cost guard: a per-search cap and a monthly cap, counted from `api_calls`. A search that hits a cap stops cleanly with `stop_reason`.
- [ ] Re-run: resets the counters and tiles, and keeps the existing lead links.
- [ ] Each new lead dispatches the website check (Phase 5) when it has a website.

**UI**

- [ ] **New search** form: industry (with suggestions), ZIP or city, radius, and tiling mode, plus an estimated cost shown before running.
- [ ] Map preview (Leaflet) with the search area and the tile grid.
- [ ] **Searches** list: status, progress bar, results, new vs linked leads, cost, and duration.
- [ ] **Search detail** page: a live progress map (tiles colour in as they finish), polled with Livewire, and the leads table filtered to this search.

**Tests:** tile splitting, dedupe order, lock prevents double fetch, the cost cap stops a run, re-run resets counters.

**Done when:** "plumber 10001" returns more than 60 deduped leads, no page is billed twice, and progress is visible live. (Dashboard: **Found** is already live from Phase 2.)

---

## Phase 4: Leads workspace

**Goal:** workflow step 2 (manual part). A fast place to browse, fix and add leads.

- [ ] **Leads table** (Filament): name, category, city, website, best email, socials icons, rating, scores, status, and owner. Filters: search, status, has website, has email, needs info, score range, owner. Saved views.
- [ ] Bulk actions: assign owner, change status, re-run website check, run PageSpeed, export, delete.
- [ ] **Lead page**: a header with name, status and quick actions. Tabs for Overview, Emails and socials, Website, Audit, Outreach and Activity. Each tab fills in as its phase ships.
- [ ] Manual edit: website, emails (add, set primary, delete) and socials. Manual values are marked `source=manual` and are never overwritten by a crawl.
- [ ] **Needs info** queue: leads with no website or no email, worked one by one with keyboard shortcuts.
- [ ] **Manual lead creation** with the same dedupe as search (warns and offers to open the existing lead).
- [ ] **CSV import**: upload, column mapping, a dry-run preview (new / duplicate / invalid), then a queued import. Uses the same dedupe.
- [ ] Duplicate merge: pick a winner, move emails, socials, events and search links, and keep an audit trail.

**Done when:** a researcher can find any lead in under a second, fill in missing details, import a CSV of 500 rows, and merge two duplicates.

---

## Phase 5: Website check

**Goal:** workflow step 2 (automatic part). Everything we can learn from the website without a human.

**Table:** `website_checks` (`lead_id`, `type`: `crawl`, `seo`, `tech`, `ssl`, `result` JSON, `status`, `error`, `checked_at`). Each check writes its own row, so a re-run never loses another check's data.

**Crawler**

- [ ] Port `Website_Crawler`, `Http_Fetcher`, `Robots` and `Url_Guard` to Laravel's HTTP client: SSRF guard (blocks private IPs, re-checked after every redirect), robots.txt, a 2 MB cap, a 10-second timeout, and a per-host delay.
- [ ] Page discovery: home page plus up to four contact, about and team pages.
- [ ] `CheckWebsite` job on the `enrich` queue, with a per-domain rate limiter.
- [ ] Broken SSL: retry without verification, record "SSL broken" as a finding, and do not mark the site unreachable.
- [ ] Parked and dead domains are detected and flagged, not crawled further.

**Extractors**

- [ ] **Emails** (port `Email_Extractor`): mailto links, visible text, JSON-LD, Cloudflare-obfuscated addresses. Each gets a confidence score (own domain, named person vs role inbox, where it was found) and the best one becomes primary.
- [ ] **Socials (new):** `<a href>`, JSON-LD `sameAs`, header and footer, for Facebook, Instagram, LinkedIn, X, TikTok, YouTube (channel, @handle or video), Pinterest, Yelp and Nextdoor. URLs are normalized and share or intent links are skipped.
- [ ] **Tech** (port `Tech_Detector`): CMS (WordPress, Wix, Squarespace, Shopify, Webflow, GoDaddy, Duda…), builders (Elementor, Divi, WPBakery), libraries, server, and dated markers (jQuery 1.x, Flash, framesets, table layouts, old WordPress).
- [ ] **SEO** (port `Seo_Analyzer`): HTTPS, viewport, title and its length, meta description, H1, canonical, schema, Open Graph, favicon, image alt coverage, mixed content, and the footer copyright year.

**UI**

- [ ] Lead page **Website** tab: an overview card per check, each finding with a pass/warn/fail mark, the raw values, and "re-check" per type.
- [ ] Emails and socials tab shows each email with its confidence and source page, and each social with its platform icon.

**Done when:** a new lead with a website gets emails (with confidence), socials, YouTube, tech and SEO findings within a minute, and a re-check of one type leaves the others untouched. Dashboard: **Enriched** becomes real.

---

## Phase 6: PageSpeed

**Goal:** workflow step 3's "manual PageSpeed button", done properly.

**Table:** `pagespeed_runs` (`lead_id`, `strategy`: `mobile`/`desktop`, `status`, `performance`, `accessibility`, `best_practices`, `seo`, `lcp_ms`, `fcp_ms`, `tbt_ms`, `cls`, `si_ms`, `screenshot_path`, `error`, `requested_by`, `ran_at`).

- [ ] **Run PageSpeed** button on the lead page and as a bulk action. It queues mobile and desktop runs on the `pagespeed` queue; **no Lighthouse call runs inside the web request**.
- [ ] Calls use the shared Google key (`google_api_key`), the same one as search and geocoding.
- [ ] One Redis rate limiter for the PSI API (for example 4 per minute), shared by all workers. A queued run waits for its slot; it never fails because of the limit.
- [ ] The final screenshot from the PSI response is stored on a **private disk** and served through signed, expiring URLs.
- [ ] UI: a pending state while queued or running, then PSI-style gauges, Core Web Vitals with Google's pass/fail colours, the screenshot, and run history with trends.
- [ ] Settings: a toggle to run PageSpeed automatically after a website check (off by default, since it is the slow, rate-limited step).

**Done when:** an auditor clicks the button, sees "Running…", and the scores and screenshot appear without a page reload; 50 bulk runs drain at the rate limit with none failing.

---

## Phase 7: Scoring

**Goal:** sort the queue so the best prospects come first.

- [ ] **Opportunity score** (port `Staleness_Scorer`): how much is wrong with the website, 0–100. Weighted categories: speed 30, mobile 20, tech age 20, SEO 15, reliability 15. A category with no data is dropped and the weights renormalized, and a `confidence` value says how much data was used.
- [ ] **Fit score** (port `Fit_Scorer`): how good a prospect the business is, 0–100. Factors: opportunity 40, reachability 30 (email quality), viability 20 (reviews, rating, activity), independence 10 (not a chain or franchise).
- [ ] Both scores are stored on `leads` with a breakdown, and recalculated by an event listener when any check, run or email changes.
- [ ] UI: a score badge with a popover that explains it ("Slow on mobile (LCP 6.2 s), no meta description, WordPress 4.x").

**Done when:** every lead with data has both scores, the table sorts by fit, and the breakdown explains each number.

---

## Phase 8: Triage

**Goal:** a fast human first pass, so the slower audit only sees leads worth the time.

- [ ] Triage queue: one lead at a time with the site screenshot, scores and key findings. Keyboard driven.
- [ ] Verdicts (port `Verdicts`): flags `outdated`, `broken`, `not_mobile`, `slow`, `no_ssl`, `poor_seo`, `weak_listing` (multi-select), or a terminal `skip`, `no_website` or `unsure`.
- [ ] Suggested flags from the automated checks are **shown, never pre-selected**.
- [ ] Optimistic saving: the next lead loads immediately, the save settles behind it, and a failure puts the lead back.
- [ ] Optional auto-triage for unambiguous cases only (for example no SSL and PSI below 30). Off by default, logged and reversible.

**Done when:** a researcher can triage 100 leads in about 10 minutes, and verdicts carry through to the audit form.

---

## Phase 9: Audit

**Goal:** workflow step 3. Confirm we can reach the business and write down what is wrong.

**Tables:** `audits` (`lead_id`, `auditor_id`, `is_reachable`, `contact_name`, `contact_role`, `preferred_channel`, `primary_issue`, `problem_note`, `outcome`: `ready`/`not_a_fit`/`revisit`, `completed_at`) and `audit_issues` (`audit_id`, `tag`, `severity`: `major`/`minor`, `note`, `evidence` JSON).

- [ ] Audit screen: site screenshot, PageSpeed and website findings, triage flags, and scores on the left; the form on the right.
- [ ] Verification: "Can we reach them?" (yes/no), contact name and role, preferred channel.
- [ ] **Issue picker**: Design, Speed, Mobile, SEO, Backlinks, Security, Broken, Listing, Content, Conversion. Each issue is marked **Major or Minor** and gets a short note. Issues are seeded from triage flags and evidence (port `Issue_Tags::evidenced()`), with evidence attached ("LCP 6.2 s on mobile").
- [ ] **Primary issue** (required) picks the template in Phase 10.
- [ ] **Problem note** (required, in your own words). Throwaway notes are refused, as in LeadMap.
- [ ] Optional **Draft with AI** (Claude, key sent in a header): writes a first problem note from the findings. The auditor always edits and saves it.
- [ ] Optional backlinks data from an external API (DataForSEO, Moz or Ahrefs), behind a setting and off by default.
- [ ] Audit queue: next/skip, number keys toggle issues, `Ctrl+Enter` saves and loads the next lead. "Not a fit" needs no write-up.

**Done when:** an auditor can work through the queue, run PageSpeed on demand, and save a complete audit with major and minor issues. Dashboard: **Audited** becomes real.

---

## Phase 10: Templates

**Goal:** workflow step 4. Messages that write themselves from the audit, with major issues first.

**Tables:** `templates` (`name`, `primary_issue`, `step`: `initial`/`follow_up_1`/`follow_up_2`/`meeting`, `subject`, `body`, `format`: `text`/`html`, `is_active`) and `template_versions`.

- [ ] Template library in Filament, grouped by primary issue and step, with versions kept on every save.
- [ ] Merge tags (ported list): `{{first_name}}`, `{{business_name}}`, `{{city}}`, `{{website}}`, `{{domain}}`, `{{review_count}}`, `{{primary_issue}}`, `{{problem}}`, `{{psi_mobile}}`, `{{psi_desktop}}`, `{{lcp_seconds}}`, `{{sender_name}}`, `{{sender_company}}`, `{{meeting_link}}`.
- [ ] New blocks: `{{major_issues}}` renders as a highlighted list at the top, `{{minor_issues}}` as a shorter list below, and `{{social_links}}`.
- [ ] Fallbacks per tag (`{{first_name|there}}`) and a check that refuses to save a template with unknown tags.
- [ ] Live preview against a real audited lead, plus a "missing data" warning (for example no first name).
- [ ] Seeded from the 7 LeadMap step-1 templates, plus follow-up and meeting templates.

**Done when:** opening an audited lead gives a ready-to-send email with the issues in the right order, and a template with a typo in a tag cannot be saved.

---

## Phase 11: Outreach

**Goal:** workflow step 5. Deliverable, compliant, and impossible to double-send.

**Tables:** `outreach_messages` (`lead_id`, `template_id`, `sequence_step_id`, `to_email`, `subject`, `body`, `status`, `provider`, `provider_message_id`, `message_id_header`, `scheduled_for`, `sent_at`, `opened_at`, `replied_at`, `bounced_at`, `error`) and `suppressions` (`email`, `domain`, `reason`, `source`).

- [ ] **Composer**: pre-filled from the template and the audit, editable per lead, with a live preview of the exact final text including the footer.
- [ ] Laravel Mail with Postmark or SES on a separate sending domain. Each send is a `SendOutreachMessage` job on the `mail` queue.
- [ ] Store the provider message ID and our own `Message-ID` header (needed for threading and reply matching).
- [ ] Pre-send checks (port `Compliance` and `Send_Service`), run **inside a DB transaction with a row lock**: sender identity and postal address set, valid email, region rules (EU/UK/CA), suppression list (checked at send time, not queue time), not already sent for this step, daily cap.
- [ ] Sending window (for example 9:00–17:00 in the lead's timezone, weekdays) and random spacing between sends.
- [ ] Compliance footer, `List-Unsubscribe` and `List-Unsubscribe-Post` (one click), and a signed unsubscribe route keyed to the **message**, not the current address.
- [ ] **Outbox**: scheduled, sending, sent and failed, with a snapshot of each message as sent, plus cancel and retry.
- [ ] Bulk "send to selected" for audited leads, which queues them within the window and cap.

**Done when:** emails arrive in Mailpit (dev) and a real inbox (staging), two concurrent sends cannot exceed the cap or double-send, and unsubscribing adds the address to suppressions. Dashboard: **Contacted** becomes real.

---

## Phase 12: Follow-ups

**Goal:** workflow step 6. The follow-up goes out on its own after 2 business days.

**Tables:** `sequences` (`name`, `is_default`), `sequence_steps` (`sequence_id`, `position`, `template_id`, `delay_business_days`), and `sequence_enrollments` (`lead_id`, `sequence_id`, `current_step`, `status`: `active`/`paused`/`stopped`/`completed`, `stop_reason`, `next_run_at`).

- [ ] Default sequence: step 1 initial email, step 2 after **2 business days**, optional step 3 after 4 more. Delays are configurable.
- [ ] `BusinessDays` calculator: skips weekends and a configurable holiday list (per country), and lands inside the sending window in the lead's timezone.
- [ ] The scheduler (every minute) picks up due enrollments and dispatches `SendOutreachMessage`, with a lock so a step never sends twice.
- [ ] Follow-ups are sent **as replies in the same thread** (`In-Reply-To` and `References` headers, `Re:` subject).
- [ ] Enrollment stops on reply, bounce, unsubscribe, meeting booked or a manual stop.
- [ ] UI: sequence editor, enrollment status on the lead page ("Follow-up in 1 business day"), and pause, resume and stop actions.

**Done when:** a lead with no reply gets the follow-up 2 business days later, in the same thread, inside the sending window. Dashboard: **Followed up** becomes real.

---

## Phase 13: Replies and deliverability

**Goal:** know when someone answers, and keep the sending domain healthy.

**Tables:** `inbound_messages` (`outreach_message_id`, `from`, `subject`, `body_text`, `received_at`, `classification`) and `webhook_calls` (raw payloads for replay).

- [ ] Inbound webhook from the provider (Postmark inbound or SES + SNS), with signature verification. IMAP polling as a fallback for setups without inbound.
- [ ] Reply matching by `In-Reply-To`/`References`, then by sender address. A reply sets the lead to `replied`, **stops the sequence** and notifies the owner.
- [ ] Auto-reply and out-of-office detection (headers and phrases), which do **not** stop the sequence.
- [ ] Bounce and spam-complaint webhooks: hard bounce or complaint marks the lead `bounced`, adds a suppression and stops the sequence.
- [ ] Deliverability panel: sent, bounce rate and complaint rate per day, with an automatic pause if the bounce rate passes a threshold (for example 5%).
- [ ] Setup checks for the sending domain: SPF, DKIM and DMARC lookups, shown on the System card.
- [ ] Manual "mark replied" and "stop sequence" actions.

**Done when:** a real reply stops the follow-up within a minute, an out-of-office does not, and a hard bounce suppresses the address. Dashboard: **Replied** and the reply rate become real.

---

## Phase 14: Meetings

**Goal:** workflow step 7. Turn a reply into a booked call.

**Table:** `meetings` (`lead_id`, `provider`, `external_id`, `starts_at`, `ends_at`, `join_url`, `status`, `notes`).

- [ ] "Propose meeting" action: a meeting template with the booking link (Calendly, Cal.com, Google Calendar appointment page or Zoom Scheduler), sent in the reply thread.
- [ ] Booking webhooks (Calendly, Cal.com): the lead moves to `meeting_booked`, a `meetings` row is created, and any sequence stops.
- [ ] Optional integrations: create a Google Meet event (Google Calendar API) or a Zoom meeting (Zoom API) directly from the lead, and send the invite.
- [ ] Meetings list and an upcoming-meetings card on the dashboard. Outcome after the call: won, lost or follow up.

**Done when:** a booking through the link shows on the lead automatically, and the dashboard lists upcoming calls. Dashboard: **Meeting booked** becomes real.

---

## Phase 15: Pipeline and activity

**Goal:** see every lead's stage at a glance and never lose track of the next step.

- [ ] **Pipeline board** (Kanban): New, Enriched, Audited, Contacted, Replied, Meeting booked, Won and Lost. Drag to change status (through the state machine), with counts and filters per column.
- [ ] **Activity timeline** on the lead page, built from `events`: search found, checks run, audit saved, emails sent, replies, meetings, and status changes.
- [ ] Tasks and reminders: "call back Tuesday", shown on the lead and in a My tasks view, with a daily digest email.
- [ ] Ownership: assign leads to users, and researchers and senders see their own work first.
- [ ] Notes and @mentions on a lead, with database notifications.

**Done when:** the board shows every stage, dragging a card logs an event, and a reminder arrives on the right day.

---

## Phase 16: Dashboard and reports

**Goal:** know what is working.

- [ ] Every dashboard number is live (each earlier phase wires its own stage; this phase checks none are left at `0`).
- [ ] Reports: leads per search and industry, enrichment success rate, audits per day per auditor, sent, replied and booked per week, reply rate per template and primary issue, and API spend this month against the cap.
- [ ] Date range and owner filters on the dashboard.
- [ ] CSV export of leads and outreach, streamed in chunks, with formula-injection escaping (`= + - @` at the start of a cell).
- [ ] Saved filters (industry, ZIP, score, status) shared across the team.

**Done when:** an admin can answer "which template books the most calls?" from the dashboard alone.

---

## Phase 17: LeadMap import

**Goal:** bring the existing WordPress data across without losing anything.

- [ ] An artisan command `leadmap:import` that reads the WP `wp_leadmap_*` tables through a second DB connection.
- [ ] Map to the new schema: unpack `enrichment_json` into `website_checks`, `pagespeed_runs`, `lead_emails` and `lead_socials`; convert the CSV `issue_tags` into `audit_issues` rows; carry triage verdicts, audits, templates, the outbox and suppressions.
- [ ] Copy screenshots to the private disk.
- [ ] Dry-run mode with a report (rows read, created, skipped, and why), and idempotent re-runs.

**Done when:** a full import of the production LeadMap data runs twice with the same result and a matching row-count report.

---

## Phase 18: Hardening and launch

**Goal:** fast, safe and observable in production.

- [ ] Performance: N+1 check (`preventLazyLoading` in staging), missing indexes from slow-query logs, Horizon tuning, Octane only if it is needed.
- [ ] Security: rate-limit public routes (unsubscribe, webhooks), verify every webhook signature, CSP and security headers, `composer audit` and `npm audit` in CI, a review of the SSRF guard.
- [ ] Ops: daily database backups with a tested restore, error tracking (Sentry or Flare), uptime checks, a Horizon long-wait alert, and queue failure alerts.
- [ ] Deploy: Forge or Ploi (or Laravel Cloud), zero-downtime deploys, a Horizon supervisor, and the scheduler cron.
- [ ] Runbook: how to pause all sending, rotate API keys and replay failed webhooks.

**Done when:** production is live, a restore has been tested, and an alert fires when Horizon stops.

---

## How we fix LeadMap's known problems

| LeadMap problem | Fix in LeadBreave | Phase |
| --- | --- | --- |
| WP-Cron runs long crawls and Lighthouse calls inside page loads | Every slow task is a Horizon job on its own queue | 0, 5, 6 |
| The browser PageSpeed worker always reschedules itself (double rate-limit claim) | One queued job and one Redis rate limiter | 6 |
| Lost updates on `enrichment_json` / `log_json` | Separate rows per check, and a `search_tiles` row per cell | 3, 5 |
| Non-atomic locks/rate limits in `wp_options` | `Cache::lock` and `RateLimiter` on Redis | 3, 6 |
| A page token can be fetched twice (double billing) | A lock per tile and page | 3 |
| 60-result cap shown silently | Grid tiling, and the UI shows the real coverage | 3 |
| Dedupe is N+1, and duplicates are dropped instead of linked | One batched query per page, plus the `lead_search` pivot | 3 |
| Geocode cost not counted; spend counted by search creation date | An `api_calls` ledger with a timestamp per call | 2, 3 |
| Most lead statuses are never set | A state machine with an event per transition (done) | 2 |
| CSV formula injection | Escape `= + - @` at the start of cells | 16 |
| Public, predictable screenshot URLs | Private disk with signed URLs | 6 |
| API key in the URL query string | Sent in a header where the API allows it; redacted from logs where it does not (Geocoding) | 3, 6, 9 |
| Cap/duplicate checks can race | Transaction with a row lock | 11 |
| Unsubscribe breaks if the email changes | Token tied to the message | 11 |
| Follow-ups, replies, socials, CSV import, pipeline never built | New phases | 4, 5, 12, 13, 15 |

## Decisions

| Decision | Choice | Status |
| --- | --- | --- |
| Laravel version | 13.x, PHP 8.3+ | ✅ Decided |
| Admin UI | Filament 5 (Livewire) | ✅ Decided |
| Database | MySQL 8.4 (Sail); SQLite in tests | ✅ Decided |
| Queue / cache | Redis + Horizon, `predis` client | ✅ Decided |
| Users | Multi-user with roles: admin, researcher, sender | ✅ Decided |
| Google APIs | One Google Cloud key for Places API (New), Geocoding API and PageSpeed Insights API | ✅ Decided |
| Mail provider | Postmark or Amazon SES, on a separate sending domain | Decide before Phase 11 |
| Reply detection | Provider inbound webhook, IMAP as fallback | Decide before Phase 13 |
| Booking tool | Calendly or Cal.com (webhook support) | Decide before Phase 14 |
| AI drafting | Claude (Anthropic API), optional | Decide before Phase 9 |
| Backlinks data | DataForSEO, Moz or Ahrefs, optional | Decide before Phase 9 |
| Hosting | VPS with Forge/Ploi, or Laravel Cloud | Decide before Phase 18 |
