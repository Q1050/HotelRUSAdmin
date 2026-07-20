# Changelog

## 2.39.0 — Accounting Exports

- Per-property chart-of-accounts mappings and vendor-neutral integration profiles
- Balanced double-entry journals generated from closed night-audit source records
- Room, service, fee, tax, receivable, payment, refund, deposit, credit, and forfeiture entries
- CSV and structured JSON exports with checksums and immutable batch history
- Validated, posted, locked, reversed, and correction-ready accounting periods
- Server-side financial mutation protection for posted business dates

## 2.38.0 — Unified Notification Center

- One configuration area for dashboard, email, and guest FCM channels
- Per-event enablement, staff recipient roles, templates, and escalation policies
- Immediate, daily-digest, and property-timezone quiet-hour delivery controls
- Guest consent summary backed by the existing mobile preference controls
- Cross-channel history for operational alerts, guest notifications, and financial email
- Notification rules enforced by guest booking/access/service sends and core staff operational alerts

## 2.37.0 — Financial Communications

- Tenant-branded invoice, statement, reminder, and deposit-receipt emails
- Dependency-free PDF attachments generated for each financial document
- Vendor-neutral queued delivery through Laravel's configured mail transport
- Per-property templates, reply-to address, and reminder schedules
- Upcoming-due and recurring overdue reminder automation
- Queued, sending, sent, failed, retried, and opened delivery history
- Signed, tenant-safe open tracking and monitored retry behavior

## 2.36.0 — Deposits & Advance Credits

- Guest and corporate advance-payment accounts with printable receipts
- Optional reservation and group links with deposit-requirement visibility
- Controlled allocation to guest folios, group folios, and corporate invoices
- Partial allocation, refund, and forfeiture workflows with available-balance safeguards
- Daily event ledger that prevents allocation from being counted as new revenue
- Night-audit reconciliation for advance receipts, refunds, and direct corporate payments

## 2.35.0 — Corporate Receivables

- Consolidated group master folios with traceable charge transfers
- Corporate invoice issuance using property payment terms and tenant currency
- Accounts-receivable aging, company balances, and downloadable account statements
- Vendor-neutral payment allocation, credit notes, overdue tracking, and controlled invoice voids
- Printable invoice detail suitable for browser PDF export
- Corporate credit exposure that includes open invoices and uninvoiced master-folio charges

## 2.26.0 — Administrator Recovery

- Dedicated platform emergency workflow for compromised hotel Super Administrators
- Platform-password confirmation, incident reason, and explicit RECOVER acknowledgement
- Atomic suspension, session/API-token revocation, and replacement promotion or creation
- Critical incident audit trail and recovery-contact notifications

## 2.25.0 — Custom Staff Access

- Tenant-scoped custom roles built from operational workflow templates
- Backend-enforced permission selection and live access preview
- Dedicated role assignment workspace linked from Manage Users
- Required reasons, audit records, and immediate session/token revocation for access changes
- Protection for the final full-access property administrator

## 2.24.1 — Platform Backup Visibility

- Platform-wide backup table covering every hotel, including healthy properties
- Exact creation and verification timestamps, backup age, size, record/file counts, and missing-file warnings
- Next scheduled backup time and direct property-management links

## 2.24.0 — Backup Readiness

- Per-property encrypted database and private-file backup bundles
- Local or S3-compatible backup disk configuration with checksummed manifests
- Daily backup creation, verification, retention cleanup, and restore-readiness checks
- Backup history, size, record/file counts, missing-file warnings, and manual controls in Security
- Non-destructive verification that never writes backup content into the live database

## 2.23.0 — Operations Monitoring

- Per-property lock-health and mobile-delivery monitoring with configurable thresholds
- Administrator, manager, and additional-email alert recipients with cooldown protection
- Test-alert control and live queue age, scheduler heartbeat, lock, battery, and FCM summaries
- Scheduled five-minute health inspection and automatic failed-job and batch pruning
- Platform-wide property operations rollup for SaaS administrators

## 2.22.0 — Production Operations

- Queued tenant-aware FCM delivery with retry, backoff, and final-failure recording
- Vendor-neutral queued lock synchronization and scheduled assigned-lock health checks
- Security-center queue totals with failed-job summaries, retry, retry-all, and deletion controls
- Public readiness endpoint covering database, queue, scheduler heartbeat, storage, and version
- Production worker, scheduler, deployment, backup, retention, and restore guidance

## 2.21.0 — Guest Privacy Controls

- Biometric-protected guest data export from the mobile Account screen
- Password-confirmed account-deletion requests blocked during active stays
- Hotel super-admin review with required notes and safe guest anonymization
- Device/token revocation and private ID-file deletion after approval
- Per-hotel reviewed ID-document retention period and scheduled daily purge
- Privacy request history and review controls on the admin guest profile

## 2.20.0 — Modular Mobile Architecture

- Split the monolithic Flutter entry point into Authentication, Stay, Reservations, Requests, Notifications, and Account feature modules
- Moved shared mobile presentation components into a dedicated widgets module
- Moved biometric/device authorization into an isolated core service
- Reduced `main.dart` to application bootstrap, theme, session lifecycle, and navigation orchestration
- Preserved one Dart library for safe cross-feature navigation without changing runtime behavior

## 2.19.0 — Protected Mobile Actions

- Face ID, Touch ID, Android biometrics, or device-credential protection
- Authentication gates for room access and every door-unlock command
- Protected viewing and revocation of registered guest devices
- Protected password changes and bulk device sign-out operations
- Two-minute authorization window with immediate relocking when the app backgrounds

## 2.18.0 — Guest Device Intelligence

- Redesigned mobile Bookings, Requests, Inbox, and Account screen headers
- Richer reservation and service-request cards with visual status treatment
- Admin guest-device panel with platform, IP, activity, push, and revocation details
- Same-device detection with direct links to other guest accounts in the property
- Strict tenant-scoped device association to prevent cross-hotel account exposure

## 2.17.0 — Mobile Experience Refresh

- Hotel-branded mobile color system with refined inputs and action controls
- Branded app header with property logo, clearer hierarchy, and compact refresh action
- Elevated bottom navigation with stronger selected-state styling
- Redesigned current-stay card emphasizing room, dates, payment, and door access
- Softer bordered surfaces, consistent corner radii, and more deliberate shadows

## 2.16.0 — Guest Account Security

- Guest password changes requiring the current password and confirmation
- Automatic revocation of every other device after a password change
- One-action sign-out for all other phones and tablets from the Account screen
- Current-device session preservation for both security operations
- Push-token removal and sensitive audit records for bulk device revocation

## 2.15.0 — Notification Deep Links

- Shared destination resolver for push notifications and the mobile inbox
- Direct opening of service-request conversations from staff replies and status updates
- Direct pre-arrival review opening for the referenced reservation
- Stay routing for room-ready and checkout notifications
- Verified room-access opening for access-category notifications
- Safe inbox fallback for unknown or incomplete notification payloads

## 2.14.0 — Mobile Session Renewal

- Secure persistence of guest access-token expiration alongside the token
- Automatic token rotation during the five-minute pre-expiration window
- Shared in-flight renewal to prevent concurrent screens from rotating the same token twice
- Immediate local session clearing for expired or server-revoked devices
- Clean return to hotel sign-in instead of repeated unauthorized API errors

## 2.13.0 — Mobile Password Recovery

- Reachable password-recovery flow from the guest mobile sign-in screen
- Privacy-safe reset-code requests that do not reveal whether an account exists
- Six-digit email-code entry, resend support, and password confirmation
- Clear invalid, expired, validation, and successful-reset states
- Tenant-scoped backend coverage for requesting and completing password resets

## 2.12.0 — Mobile Device Security

- Registered-device list in the guest Account screen with current-device identification
- Last-active details and remote access revocation for older phones
- Immediate token and push-token removal when a guest revokes a device
- Current-device protection requiring the normal sign-out flow
- Sensitive security audit records for guest-initiated device revocation

## 2.11.0 — Mobile Room Access

- Guest QR scanning for the secure marker assigned to the checked-in room
- NFC room-marker reading on supported Android and iOS devices
- Device, identity, payment, stay, room, and marker verification before unlocking
- Mobile credential retrieval followed by a tracked remote unlock command
- Manual marker entry for local development and hardware-free testing

## 2.10.0 — Mobile Request Conversations

- Full guest request details with room, priority, current status, and event timeline
- Two-way guest/staff request messaging with authenticated image attachments
- Guest cancellation before work begins and clear closed-request behavior
- Completion photo viewing with guest confirmation or reopen-with-feedback actions
- Automatic request-list refresh after returning from a conversation

## 2.9.1 — Mobile App Identity

- Changed the Android application ID and namespace to `me.romarioburke.hotelcheckin`
- Changed the iOS application and test bundle identifiers to the matching reverse-domain identity
- Updated dynamic Firebase initialization and setup documentation for the new identifiers

## 2.9.0 — Mobile Notifications

- Per-hotel public Firebase mobile options alongside encrypted server credentials
- Dynamic Firebase initialization for the hotel selected in the guest app
- Notification permission, FCM token registration/rotation, tap handling, and logout cleanup
- Mobile notification inbox with unread state and mark-one/mark-all controls
- Guest preferences for booking, access, service, checkout, and marketing notifications
- Inbox fallback when push delivery is not yet configured for a hotel

## 2.8.0 — Mobile Pre-Arrival

- Mobile reservation list with booking-reference claiming and six-digit email verification
- Secure front/back ID image selection and multipart pre-arrival submission
- Arrival time, guest notes, policy consent, review status, rejection notes, and resubmission
- Staff booking terminology distinguishing provisional profiles from mobile guest accounts
- Explicit guidance that hotel staff never create the guest's mobile password

## 2.7.1 — Guest Request Details

- Display the original mobile request description separately from follow-up chat messages
- Show guest name, request type, priority, and submission time in housekeeping and maintenance
- Clarify empty conversations as having no follow-up messages instead of implying the request has no information

## 2.7.0 — Guest Mobile Foundation

- Separate Flutter Android/iOS application under `mobile/`
- Tenant hotel discovery with live property branding and support details
- Secure per-device guest registration, login, session restoration, and logout
- API-backed current-stay, room summary, guest profile, and service-request screens
- Secure token/device storage, structured API errors, development configuration, and mobile tests

## 2.6.1 — Hotel-Isolated Firebase

- Removed the global Firebase credential fallback
- Require every hotel to configure and use its own Firebase project and service account
- Clarified hotel and platform settings screens to reflect strict credential isolation

## 2.6.0 — Tenant Firebase

- Per-hotel Firebase Cloud Messaging service-account settings with encrypted private-key storage
- Hotel super-administrator and platform-admin configuration screens with safe credential summaries
- Live OAuth authentication test, connection health, last error, and test timestamp tracking
- Tenant-aware mobile delivery with hotel-specific credentials
- Audited Firebase configuration changes and preserved private keys during ordinary settings edits

## 2.5.0 — Property Branding

- Shared per-hotel branding and guest-policy controls for platform owners and hotel super administrators
- Secure logo upload/removal plus guest-facing display name, primary color, and accent color
- Support contacts, email sender identity, ID requirements, terms, privacy policy, and notification wording
- Tenant-branded hotel sidebar with configured logo, name, and colors
- Public tenant-resolved mobile property configuration endpoint with private settings excluded

## 2.4.0 — Stripe Billing

- Stripe-hosted subscription Checkout and customer billing portal sessions
- Platform billing dashboard with configuration and subscription health visibility
- Signed webhook verification, replay protection, and idempotent event records
- Automatic plan, customer, subscription, renewal, payment-failure, and cancellation synchronization
- Environment-based Stripe credentials and per-plan price configuration with billing disabled safely by default

## 2.3.0 — Property Onboarding

- Guided per-property onboarding workspace with progress and launch readiness
- Hotel contact profile, address, website, operating hours, timezone, and currency setup
- Plan-aware bulk room creation with per-hotel room-number uniqueness
- Administrator, operations staff, room inventory, and optional smart-lock readiness checks
- Audited onboarding profile, inventory, and launch-completion actions

## 2.2.1 — Platform Layout Fix

- Replaced the stale precompiled stylesheet import with the live Tailwind source build
- Restored the platform sidebar's fully opaque background and generated responsive offset utilities
- Added an isolated content stacking context so pages cannot render underneath the sidebar

## 2.2.0 — Platform Control Center

- Platform overview with client, subscription, room, and staff health metrics
- Recently added properties, plan distribution, and recent platform activity summaries
- Dedicated plans and modules page with tier limits and a complete feature matrix
- Dedicated platform activity timeline for sensitive owner actions across hotels
- Complete responsive sidebar navigation with accurate active states

## 2.1.2 — Platform Sidebar

- Dedicated responsive platform sidebar with client-hotel and dashboard navigation
- Mobile slide-out navigation with a protected platform administrator identity panel
- Consistent rounded treatment for directory controls, property cards, summaries, and hero content

## 2.1.1 — Platform UI Refresh

- Removed legacy starter CSS that constrained and centered the entire application root
- Redesigned the platform owner header, overview metrics, property directory, and hotel cards
- Added property search, responsive usage summaries, clearer module and subscription presentation
- Reworked hotel creation and management dialogs with structured sections and improved spacing

## 2.1.0 — Platform Administration

- Dedicated `/platform` control panel protected by explicit platform-owner authorization
- Client hotel onboarding with plan assignment and first administrator provisioning
- Hotel, subscription lifecycle, usage-limit, and module-override management
- Hotel suspension controls and property-level room, staff, guest, and lock usage visibility
- Audited support impersonation with a persistent, visible exit control
- Platform authorization, tenant provisioning, feature override, and impersonation test coverage

## 2.0.0 — SaaS Foundation

- Hotel-level tenant isolation across staff, guests, rooms, operations, locks, security, and mobile records
- Core, Operations, and Connected plans with centrally enforced optional modules and feature overrides
- Per-property room and staff limits enforced by backend middleware
- Tenant-aware guest mobile authentication and password resets using the `X-Hotel-Slug` header
- Subscription, module availability, and usage shown in Settings, with unavailable modules removed from navigation
- Provider-ready subscription records for future Stripe or Paddle billing synchronization

## 1.10.1 — Staff-Created Guest Requests

- Optional checked-in guest selection when creating Housekeeping or Maintenance work
- Immediate linked conversation, timeline, inbox record, and notification before the mobile app exists
- Server validation prevents attaching a guest to a room they are not actively occupying

## 1.10.0 — Guest Conversations

- Two-way guest and staff request messages with image attachments and unread tracking
- Staff-only internal notes kept out of all guest API responses
- Chronological request timelines, safe pre-work cancellation, completion evidence, confirmation, and reopening
- Shared conversation panels in Housekeeping and Maintenance with FCM reply and completion alerts

## 1.9.0 — Guest Notifications

- FCM HTTP v1 delivery with service-account OAuth, Android priority, and APNs sound support
- Mobile notification inbox, unread state, per-category preferences, and device-token registration
- Delivery tracking, configuration backlog, partial failure reporting, and invalid-token cleanup
- Automatic notifications for arrival review, room check-in, service progress, and checkout
- Settings dashboard showing FCM configuration and delivery health

## 1.8.0 — Pre-Arrival

- Secure reservation claiming with identity matching, expiring codes, attempt limits, and placeholder merge trails
- Mobile pre-arrival check-in with private ID uploads, arrival details, and recorded policy consent
- Staff review queue with protected document viewing, approval, rejection notes, and guest email updates
- Automatic ID verification on approval plus document-access and decision auditing

## 1.7.0 — Mobile Provisioning

- Local QR generation, printing, and downloads for paired room locks
- NFC marker values and setup guidance in Lock Management
- Audited marker rotation that invalidates lost or damaged QR/NFC markers
- Guest request status synchronization from housekeeping and maintenance work

## 1.6.0 — Guest Mobility

- Versioned guest mobile API with registration, login, password reset, and one-hour device-bound tokens
- Current stays, reservations, device management, and automatic checkout revocation
- QR/NFC room-marker verification for mobile credentials and unlock commands
- Guest housekeeping, linen, amenity, and maintenance requests connected to staff workflows
- Encrypted mobile lock credentials, rate limits, access history, and security auditing
- OpenAPI contract and end-to-end feature coverage

## 1.0.0 — Operations Foundation

- Staff roles, permissions, and account management
- Guest accounts, reservations, check-in, and checkout
- Room inventory, history, and controlled operational states
- Smart-lock inventory, pairing, credentials, commands, and simulator
- Housekeeping queue, cleaning completion, and room inspection
- Operational dashboard metrics and audit history

To publish a later application version, update `VERSION`, add a changelog entry,
and optionally set `APP_RELEASE_NAME` for the release label.
## 2.27.0 — Reservation Planning

- Week and four-week room timeline with drag-and-drop reservation placement
- Server-enforced overlap protection for room assignments and inventory holds
- Unassigned reservation queue plus arrival, departure, and stay-over summaries
- Room-type availability matrix with group and operational inventory blocks
- Date-based nightly rates, minimum stays, and closed-to-arrival controls
## 2.28.0 — Controlled Departures

- Distinct normal, early, and manager-authorized forced checkout workflows
- Permanent departure records with reasons, financial handling, refunds, and security involvement
- Emergency room-access suspension and manager restoration without prematurely ending a stay
- Optional do-not-rent restrictions enforced during reservation creation and check-in
- Stay-specific credential revocation for normal departures and full device revocation for evictions
## 2.29.0 — Grouped Navigation

- Expandable Reservations, Rooms & Access, Operations, and Administration sidebar groups
- Room Planner nested beneath Reservations with automatic active-group expansion
- Permission-aware navigation compatible with custom staff role profiles
- Scrollable navigation and compact collapsed-group behavior
## 2.30.0 — Reservation Lifecycle

- Dedicated reservation workspace with editable booking, payment, room, group, and request details
- Permanent reservation timeline for edits, no-shows, stay changes, transfers, and financial reviews
- No-show processing with fee capture and immediate inventory release
- Availability-safe active-stay extensions and shortening with credential refresh
- Room transfers that revoke previous access, issue replacement credentials, and queue turnover cleaning
- Departure financial review queue and manager-controlled do-not-rent restriction release
- Multi-day reservation bars spanning the complete stay in the room planner
## 2.31.0 — Guest Folios

- Vendor-neutral guest folio ledger separate from SaaS subscription billing
- Itemized room, tax, deposit, service, damage, no-show, cancellation, and adjustment charges
- Partial and split payments across cash, cards, bank transfer, terminals, providers, and other methods
- Manager-controlled refunds and charge voids with immutable audit records
- Printable folio invoices and CSV payment reconciliation
- Idempotent scheduled nightly room-charge posting using each property's configured currency
- Automatic no-show fee posting and a provider contract for future payment integrations
## 2.32.0 — Night Audit

- Per-property business dates calculated in each hotel's configured timezone
- Preflight review for pending arrivals, overdue departures, room-state mismatches, unsettled folios, and missing nightly charges
- Idempotent room-charge posting and immutable financial/occupancy snapshots on close
- Manager exception overrides with mandatory reasons and security audit events
- Latest-date-only reopening without deleting charges or historical records
- Thirty-day audit history with downloadable CSV close reports
## 2.33.0 — Property Financial Rules

- Effective-dated hotel taxes, fees, deposits, cancellation, no-show, and early-departure policies
- Fixed or percentage calculations scoped globally or to a room type
- Per-stay and per-night application with inclusive-tax support
- Documented exemptions limited to explicitly exemptible taxes and management-authorized reservations
- Immutable reservation pricing snapshots so later rate changes do not rewrite past bookings
- Automatic configured cancellation/no-show folio charges and taxed nightly postings
- Financial-rule changes retained in the security audit trail
## 2.34.0 — Groups & Corporate Accounts

- Property-scoped corporate profiles with contacts, tax references, credit limits, and payment terms
- Group booking lifecycle with tentative, confirmed, cancelled, and closed states
- Room commitments, release dates, negotiated nightly rates, and billing instructions
- Searchable rooming lists linked to real guest accounts, reservations, rooms, and folios
- Individual, group-master, and corporate billing responsibility on every reservation
- Corporate credit-limit enforcement before accepting billed reservations
- Live group value, outstanding balance, company receivables, and available-credit summaries
- Group confirmation and cancellation synchronized to eligible member reservations
