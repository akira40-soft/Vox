# Implementation Plan — VOX Electoral Hub Fixes & Enhancements

## [Overview]
Fix all non-functional features in the electoral hub (`sala_detalhes.php`), improve dark mode typography visibility, add smooth transitions across all phases, and implement the missing "Seguir" toggle UX. The system is a social-media-style election platform built in PHP/MySQL with a Twitter-like interface per election room.

## [Information Gathered]

### Current Architecture
- **Backend**: PHP + MariaDB (`vox_db`), PDO, CSRF protection
- **API Entry Points**: `api/interactions.php` (social), `api/votar.php` (voting), `api/messages.php` (DMs)
- **Frontend**: Vanilla JS (`assets/js/main.js`) + inline `<script>` in PHP files
- **Styling**: CSS custom properties, dark mode via `[data-theme='dark']`, CSS Grid/Flexbox

### Key Issues Identified

| # | Issue | Root Cause | File(s) |
|---|-------|----------|---------|
| 1 | **Seguir button** not toggling state | JS `toggleFollow` in `sala_detalhes.php` works but API returns success without DOM update confirmation; `btn.classList.toggle('following')` called but button text stays "Seguir" | `sala_detalhes.php` inline JS, `main.js` |
| 2 | **Eliminar Sala** not working | SQL syntax error: `DELETE pr FROM post_reacoes pr JOIN...` — MariaDB requires `DELETE pr FROM post_reacoes pr` format, not `DELETE pr FROM post_reacoes pr USING` | `api/interactions.php` |
| 3 | **Denúncias** (Report) not working | Missing `case 'report':` handler in `api/interactions.php`; only `case 'report':` placeholder exists but no logic | `api/interactions.php` |
| 4 | **Post returns JSON screen** instead of updating feed | After `create_post`, `location.reload()` is called — should prepend post to feed | `sala_detalhes.php` inline JS |
| 5 | Phase manager `api/phase_manager.php` not found | File referenced in `pollPhase()` but does not exist | `sala_detalhes.php` |
| 6 | Dark mode letters/elements not visible | Dark theme CSS variables defined but some elements use hardcoded colors or `--white` mapped incorrectly | `style.css`, `sala_detalhes.php` inline styles |
| 7 | Tab transitions not smooth | `switchSocialTab()` uses inline opacity transitions but panels start at `opacity:0` then switch — jarring | `sala_detalhes.php` |
| 8 | Countdown timer logic incomplete | `startVotingCountdown()` exists but `api/phase_manager.php` missing — timer never syncs | `sala_detalhes.php` |
| 9 | **"Quem seguir" sidebar** — following state not visually updating after toggle | `toggleFollow` updates DB but does not persist the "A seguir" visual state in the sidebar button | `sala_detalhes.php` |

### Database Tables Involved
- `seguidores` — `id, seguidor_id, seguido_id, criado_em`
- `denuncias` — `id, user_id, candidato_id, post_id, motivo, detalhes, estado, criado_em`
- `campanhas` — posts feed
- `post_reacoes` — reactions

---

## [Files]

### New files to be created
- `api/phase_manager.php` — Room phase sync endpoint (GET: check phase; POST: advance phase)

### Existing files to be modified
- `api/interactions.php` — Fix DELETE syntax for `delete_room`, add full `case 'report':` handler, add `toggle_follow` response update
- `sala_detalhes.php` — Fix post prepend on success, improve toggleFollow UX, smooth tab transitions, dark mode fixes, phase manager integration
- `assets/js/main.js` — Add `toggleFollow` wrapper, improve toast system
- `style.css` — Dark mode `--white` alias fix, animation improvements, typography polish

---

## [Functions]

### New functions
- **`api/phase_manager.php`** (new file)
  - `GET ?action=check&sala_id=X` → returns current phase, next phase date
  - `POST ?action=advance&sala_id=X` → advance phase manually (organizer only)
  - Uses `computeRoomPhase()` from `config/helpers.php`

### Modified functions
- **`toggleFollow(btn, targetId)`** in `sala_detalhes.php` inline JS
  - Current: calls `apiCall('api/interactions.php', {action:'toggle_follow', target_id:targetId})` → updates DB but UI update inconsistent
  - Change to: fetch → on success, toggle `.following` class AND toggle button text to "A seguir" / "Seguir"; on unfollow, change to "Seguir"
  
- **`reactPost(postId, type, btn)`** in `sala_detalhes.php` inline JS
  - Current: optimistic update + fetch
  - Change: add floating emoji animation on 'adorado', add shake animation on 'hater'

- **`switchSocialTab(subTabId, btn)`** in `sala_detalhes.php`
  - Current: abrupt display toggle
  - Change: CSS `transition: opacity 0.3s, transform 0.3s` on panels + `will-change: transform`

- **`deleteRoom(e, sid)`** in `sala_detalhes.php`
  - Fix SQL: change cascade deletes from `DELETE pr FROM` to proper multi-table DELETE syntax

- **`submitReport(event)`** — already exists, needs `case 'report':` in API

- **Post creation** (`formPostCampanha` submit handler)
  - Change: instead of `location.reload()`, prepend new post HTML to `#postsFeed`

---

## [Classes / Components]

### Social Hub Tab Panels
- 3-column Twitter-like layout: nav (275px) | main (flex) | sidebar (350px)
- 5 sub-tabs: feed, mensagens, eleicao, estatisticas, (audit/reports for organizers)

### Phase Banner Component
- Top banner showing current election phase with countdown
- States: campanha (blue), votacao (amber), estatisticas (green), aguardando (gray)
- `data-theme='dark'` variants for each state

### Countdown Timer Component
- Dark glass card with neon-style numbers
- Urgency pulse animation when < 5 minutes remain
- States: visible only during `votacao` phase

### Follow Button Component
- States: default ("Seguir"), following ("A seguir"), hover when following ("Deixar de seguir")
- CSS: `.x-btn-follow` base + `.following` modifier
- JS: `toggleFollow()` toggles class and text

### Report Modal
- Triggered by `openReportModal(targetId, targetName, type)`
- Form submits to `submitReport()` → `api/interactions.php` action `report`
- Success: close modal + toast notification

### Live Ticker
- Sticky top bar below header
- Animated pulse indicator + live vote count + post count
- Polls `api/stats.php` every 10 seconds

---

## [Dependencies]
- **Font Awesome 6.x** (already loaded via CDN)
- **Chart.js** (for engagement chart in stats tab)
- No new npm packages needed — vanilla JS

---

## [Testing]
- Manual testing checklist per feature (documented below)
- Verify all 9 issues are resolved

---

## [Implementation Order]

### Phase 1: Critical Backend Fixes (10 min)
1. **[FIX]** `api/interactions.php`: Fix `delete_room` SQL cascade syntax
2. **[FIX]** `api/interactions.php`: Complete `case 'report':` handler
3. **[NEW]** `api/phase_manager.php`: Create phase check endpoint

### Phase 2: Frontend UX Fixes (15 min)
4. **[FIX]** `sala_detalhes.php`: Fix `toggleFollow()` — text + class toggle
5. **[FIX]** `sala_detalhes.php`: Fix post prepend on `create_post` success (no reload)
6. **[FIX]** `sala_detalhes.php`: Smooth tab transitions with CSS transitions
7. **[FIX]** `sala_detalhes.php`: Phase banner and countdown timer integration

### Phase 3: Dark Mode Polish (8 min)
8. **[FIX]** `style.css`: Fix `--white` dark mode alias → should be `#ffffff` not `#1e293b`
9. **[FIX]** `sala_detalhes.php`: Inline styles using hardcoded colors — add dark mode variants

### Phase 4: Polish & Animations (7 min)
10. **[ENHANCE]** Reaction floating emojis on 'adorado'
11. **[ENHANCE]** Follow button hover text change ("Deixar de seguir")
12. **[ENHANCE]** `main.js`: Add `toggleFollow` utility function

---

## [Task Progress]
- [ ] Step 1: Fix `delete_room` SQL cascade in `api/interactions.php`
- [ ] Step 2: Add full `case 'report':` handler in `api/interactions.php`
- [ ] Step 3: Create `api/phase_manager.php` phase check endpoint
- [ ] Step 4: Fix `toggleFollow()` text/class toggle in `sala_detalhes.php`
- [ ] Step 5: Fix post prepend (no reload) in `sala_detalhes.php`
- [ ] Step 6: Smooth tab transitions in `sala_detalhes.php`
- [ ] Step 7: Phase banner + countdown integration (using existing helpers.php logic)
- [ ] Step 8: Fix `--white` dark mode alias in `style.css`
- [ ] Step 9: Fix hardcoded color elements in `sala_detalhes.php` inline styles
- [ ] Step 10: Reaction floating emoji animation
- [ ] Step 11: Follow button hover text enhancement
- [ ] Step 12: `main.js` `toggleFollow` utility + toast polish
