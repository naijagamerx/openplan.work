# Alexa Developer Console — Full Configuration Handoff

**Paste this entire document into Claude Chrome Extension.** It instructs the agent to configure the Alexa skill end-to-end: rebuild interaction model (15 intents), enable account linking (multi-user), enable Alexa Timer API permission (pomodoro), and run smoke tests.

User context: Server-side endpoints already live at `https://openplan.work`. Skill ID `amzn1.ask.skill.903cf3c6-5b8b-4205-9b76-8a9781d4491f`. All code deployed.

---

## What's Already Working (don't redo)

| Server-side asset | URL / Status |
|---|---|
| Skill endpoint | `https://openplan.work/api/alexa.php` (returns 405 on GET, correct) |
| Interaction model JSON | `https://openplan.work/alexa-skill/interaction-model.json` (latest version with 15 intents) |
| OAuth authorize endpoint | `https://openplan.work/api/oauth/authorize.php` |
| OAuth token endpoint | `https://openplan.work/api/oauth/token.php` |
| OAuth revoke endpoint | `https://openplan.work/api/oauth/revoke.php` |
| Mobile setup page (informational) | `https://openplan.work/?page=alexa-setup` |
| Settings → Voice Assistants | `https://openplan.work/?page=settings` (scroll to "Voice Assistants" section) |
| Env vars on Hostinger | Set (`ALEXA_OWNER_USER_ID`, `LAZYMAN_MASTER_PASSWORD`, `ALEXA_PLATFORM_KEY`, `ALEXA_OAUTH_CLIENT_ID`, `ALEXA_OAUTH_CLIENT_SECRET`) |

---

## Step 1 — Rebuild the Interaction Model

The model was previously built with 7 intents. Now there are **15 intents** + 1 custom slot type. Rebuild required.

1. Open `https://developer.amazon.com/alexa/console/ask/build/custom/amzn1.ask.skill.903cf3c6-5b8b-4205-9b76-8a9781d4491f/development/en_US/dashboard`
2. Top tab bar → click **Build**
3. Left sidebar → **Interaction Model** → **JSON Editor**
4. Select all current content (`Ctrl+A`) → delete
5. Open new tab → `https://openplan.work/alexa-skill/interaction-model.json`
6. Select all that page content → copy
7. Back in JSON Editor → paste
8. **First sanity check**: the very first lines after the outer `{` should read:
   ```json
   "interactionModel": {
     "languageModel": {
       "invocationName": "my plan",
   ```
9. **Second sanity check**: search the pasted content for "AdjustInventoryIntent" — it should be there. Search for "INVENTORY_OP" — should be in `types` array.
10. Click **Save Model** (top right) → wait for green toast "Model saved"
11. Click **Build Model** (also top right) → wait ~60-90 seconds for "Full Build Successful" green banner

**If build fails with error:**
- Error mentions an intent name reserved by Amazon → unlikely, but report back which intent so we can rename
- Error about invocation name → leave as `my plan` (don't change to `open plan` — Amazon rejects "open" as a launch verb)
- Error about slot type → report back full error text

---

## Step 2 — Enable Account Linking

This is the new multi-user setup. Lets any OpenPlan user link their Amazon account via Alexa mobile app.

1. Left sidebar → **Account Linking** (under Build tools)
2. Top toggle: **Do you allow users to create an account or link to an existing account with you?** → flip **ON**
3. Page expands. Now fill in fields:

| Field | Value to paste |
|---|---|
| **Allow users to enable skill without account linking** | **OFF** (skill requires linking) |
| **Allow users to link their account to your skill from within your application or website** | OFF (out of scope v1) |
| **Auth Grant Type** | **Auth Code Grant** (radio button) |
| **Your Web Authorization URI** | `https://openplan.work/api/oauth/authorize.php` |
| **Your Access Token URI** | `https://openplan.work/api/oauth/token.php` |
| **Your Client ID** | `openplan-alexa` |
| **Your Secret** | `25c9951a63628aa0460c13338f29619b98ef274f5c3d4ca34e2415dc7080bf1a` |
| **Your Authentication Scheme** | **HTTP Basic (Recommended)** |
| **Scope** (click "+ Add Scope") | `openplan.read_write` |
| **Domain List** (click "+ Add Domain") | `openplan.work` |
| **Default Access Token Expiration Time** | `3600` |
| **Reciprocal Account Linking** | OFF |

4. **PKCE section** (Security Provider section, optional): you may leave off for v1. Can turn on later.

5. Click **Save** at the top → wait for green confirmation toast.

6. **Make a note of the three "Alexa Redirect URLs"** shown in this page (under "Redirect URLs" subsection). They should be:
   - `https://layla.amazon.com/api/skill/link/<vendorId>`
   - `https://pitangui.amazon.com/api/skill/link/<vendorId>`
   - `https://alexa.amazon.co.jp/api/skill/link/<vendorId>`

   These are already allowlisted in our OAuth provider — no server change needed. But screenshot them for the user just in case.

---

## Step 3 — Enable Alexa Timer Permission (Pomodoro)

Needed for the `StartPomodoroIntent` to create a native Alexa timer that visibly counts down + speaks when done.

1. Left sidebar → **Permissions** (under Build tools)
2. Find row labeled **Timers** (under "Customer Permissions")
3. Toggle **ON**
4. A note appears about user consenting at first use → that's expected; no action needed here
5. Click **Save** (if a save button appears at top)

---

## Step 4 — Re-Build Model (Mandatory After Permission Change)

Any time you toggle a permission or account-linking setting, the model needs a re-build for the change to be reflected in the Test tab.

1. Left sidebar → **Interaction Model** → **Invocation**
2. Click **Save Model** (no changes needed, just re-save to trigger)
3. Click **Build Model** → wait ~60s

---

## Step 5 — Smoke Test in Console (Owner Mode)

Server has env vars for owner mode (admin@demohomexx), so console Test tab works WITHOUT account linking for now. Test the new intents.

1. Top tab bar → **Test**
2. Top-left dropdown labelled **Skill testing is enabled in:** → select **Development**
3. Type each of these into the chat box and verify the response is sensible. Don't worry about the exact phrasing — just confirm the skill responds, doesn't error, and the action seems plausible.

```
open my plan
what is on my list today
read my todos
did I do my habits
how much water did I drink
what did I earn this month
add task buy bread
add task call dentist to project errands
create project home renovation
add client John Smith
take a note remember to follow up with John
schedule meeting design review tomorrow at 3 pm
start a 25 minute pomodoro
add habit meditation
add 5 widgets to inventory
stop
```

4. For each utterance, check the right-side **JSON Output** panel. Successful response should look like:
   ```json
   {
     "version": "1.0",
     "response": {
       "outputSpeech": { "type": "PlainText", "text": "..." },
       "shouldEndSession": false,
       ...
     }
   }
   ```
5. If any return `"Sorry, something went wrong"` → expand the JSON, look for error hints, paste back to user.

**Expected:** writes (add task, create project, etc) appear in the web app within seconds. User can verify by opening:
- `https://openplan.work/?page=tasks` — new task in "Inbox" project (or "errands")
- `https://openplan.work/?page=projects` — new "home renovation" project
- `https://openplan.work/?page=clients` — new "John Smith" client
- `https://openplan.work/?page=notes` — new note with tag "alexa"
- `https://openplan.work/?page=calendar` — new meeting tomorrow at 15:00
- `https://openplan.work/?page=habits` — new "meditation" habit

---

## Step 6 — Test Account Linking End-to-End (Multi-User Mode)

This validates the OAuth flow for users who AREN'T the env-var owner.

**Recommended approach: use Amazon's "Test Account Linking" tool inside the console.**

1. Stay on **Account Linking** page
2. Scroll down to bottom — there's a section labeled **Account Linking Tester** or a **Test** button
3. Click it → it walks through the OAuth flow as if a fresh Amazon user is linking

Alternative: link via the actual Alexa mobile app on a phone:
1. Open Alexa app → bottom nav **More** → **Skills & Games** → top-right user icon → **Your Skills** → **Dev** tab
2. Tap **OpenPlan** (the skill name set in console — if not set, it may show as "Untitled" or the skill ID)
3. Tap **Settings** (gear icon) → **Link Account**
4. Browser opens to `https://openplan.work/api/oauth/authorize.php?client_id=...`
5. Sign in with an OpenPlan account (different from the owner)
6. Approve consent page
7. Browser shows "Account linked successfully" or redirects back to Alexa app

Then on any Echo:
- `Alexa, open my plan` → should greet you using the LINKED account's data
- `Alexa, ask my plan what is on my list today` → returns the linked user's tasks

---

## Step 7 — Save and Distribute (Optional)

If you want the skill to appear in the Alexa Skills Store eventually (Dev mode only is fine for testing):

1. Top tab bar → **Distribution**
2. Fill in basic metadata: name `OpenPlan`, one-sentence description, category `Productivity`, sample utterances, icon (can be placeholder)
3. **DO NOT submit for certification** — leave in Dev mode for now

This is only needed if the skill should appear in skills directory. Dev mode works fine without it.

---

## Troubleshooting Cheatsheet

| Symptom | Cause | Fix |
|---|---|---|
| Build fails on Save Model | Pasted JSON malformed | Re-copy from `https://openplan.work/alexa-skill/interaction-model.json` — paste cleanly |
| "Invocation name not valid" | "open" or "ask" used as first word | Keep as `my plan` — Amazon enforces this |
| Account linking save fails: "Invalid Web Authorization URI" | URL typo, must be exact | `https://openplan.work/api/oauth/authorize.php` (no trailing slash) |
| Account linking save fails: "Domain not allowlisted" | Forgot to add `openplan.work` to Domain List | Click + Add Domain, paste `openplan.work` |
| Test tab returns "There was a problem with the requested skill's response" | Server-side exception | Open `https://openplan.work/?page=settings`, scroll to Voice Assistants section, click "Technical details" → check endpoint URL; user should check `data/php_error.log` |
| Test tab returns "Sorry, something went wrong on the server" | Specific exception caught | The response speech now describes the cause (env vars / decryption / etc) — report exact wording |
| "Account linking is not enabled" message | `ALEXA_PLATFORM_KEY` env var missing | Confirm via `https://openplan.work/?page=alexa-setup` → if Set, problem is elsewhere |
| Linked user gets owner's data instead of own | env var owner mode active because incoming request has no accessToken | Check Alexa app: confirm account is actually linked (Skills → Your Skills → Dev → OpenPlan → "Account is linked" green badge) |
| Pomodoro timer doesn't ring audibly | Timer permission not granted by user yet | In Alexa app, tap OpenPlan skill → Settings → Permissions → enable Timer permission |
| `ask my plan I used 5 widgets` doesn't subtract | Custom slot didn't resolve "used" → remove | Re-build model; the synonym is in INVENTORY_OP type |

---

## What NOT to Do

- **Do not change the Skill ID.** It's wired into all server-side links: `amzn1.ask.skill.903cf3c6-5b8b-4205-9b76-8a9781d4491f`
- **Do not change the endpoint URL.** Must be exactly `https://openplan.work/api/alexa.php`
- **Do not change the invocation name** away from `my plan`. Amazon rejects `open plan` due to reserved word collision.
- **Do not edit the interaction-model.json content in the editor** — paste from URL as-is. Any change there will be overwritten by future server updates.
- **Do not toggle "Send Alexa events to your skill"** (Smart Home or Music events) — wrong skill type.
- **Do not submit for certification.** Dev mode + invite-only is the v1 plan.

---

## Success Criteria (Report Back Once Complete)

Report which of these worked:

1. [ ] Model built with 15 intents (no build error)
2. [ ] Account Linking page saved with all 12 fields filled in
3. [ ] Timer permission toggled ON
4. [ ] Test tab `open my plan` returns greeting
5. [ ] Test tab `add task buy bread` returns "Added task: buy bread..." AND task appears in web app
6. [ ] Test tab `create project home renovation` returns "Created project..." AND project visible in web app
7. [ ] Test tab `start a 25 minute pomodoro` returns success AND a native Alexa timer can be seen in Alexa app for 25 min
8. [ ] Account linking flow completed for at least one Amazon account
9. [ ] Linked user's voice commands return THAT USER's data (not owner's)

If any step fails, paste:
- Exact error text from console
- Or the JSON response from Test tab
- Or screenshot of the failing field

User will debug from there.

---

## Credentials Summary (For Quick Reference)

| Item | Value |
|---|---|
| Skill ID | `amzn1.ask.skill.903cf3c6-5b8b-4205-9b76-8a9781d4491f` |
| Invocation Name | `my plan` |
| Endpoint URL | `https://openplan.work/api/alexa.php` |
| Authorize URL | `https://openplan.work/api/oauth/authorize.php` |
| Token URL | `https://openplan.work/api/oauth/token.php` |
| Client ID | `openplan-alexa` |
| Client Secret | `25c9951a63628aa0460c13338f29619b98ef274f5c3d4ca34e2415dc7080bf1a` |
| Auth Scheme | HTTP Basic |
| Scope | `openplan.read_write` |
| Domain | `openplan.work` |
| Token Expiration | `3600` seconds |
| Grant Type | Authorization Code |
| PKCE | Optional (off for v1) |

---

**End of handoff document.** Begin at Step 1.
