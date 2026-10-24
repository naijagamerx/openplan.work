# OpenPlan Work public website brand spec

## Brand and product

- Use `getPublicAppName()` for the visible brand so hosted installations can use their configured public name.
- The product identity in repository documentation is OpenPlan Work.
- Use the existing stepped geometric mark from `assets/favicons/apple-touch-icon.png`, reproduced with the same outline-free SVG path in the public navigation and footer.
- Keep the existing favicon and Apple touch icon files as the browser identity.

## Visual direction

- Make the site feel like a premium, editorial landing page for people running real work. Lead with a confident headline, original lifestyle photography, calm spacing, and clear calls to action.
- Use Inter throughout. Set headlines in a strong, clean sans-serif style; do not use script, handwriting, or display-serif fonts.
- Use a strict monochrome palette: white `#ffffff`, near-white gray `#f5f5f5`, ink `#111111`, black `#050505`, and neutral gray borders and supporting text. Dark mode uses black and charcoal surfaces with white text. Avoid green, beige, and colored accents.
- Use original image assets in `assets/images/openplan-landing-hero-20261003.png`, `openplan-landing-planning-20261003.png`, and `openplan-landing-mobile-20261003.png`. Preserve their natural color, use descriptive alt text, and avoid inventing visible interface details in the photography.
- Use organic image crops, fine borders, soft neutral shadows, and restrained editorial flourishes. Avoid generic dashboard mockups, fake metrics, customer logos, and technical implementation panels.
- Keep motion limited to short hover and theme transitions; honor reduced-motion preferences.

## Product imagery and content

- Existing dashboard and feature screenshots include names, workspace counts, finance values, or other populated records. Do not reuse them in public marketing until sanitized and approved.
- Describe actual product areas in everyday language: projects, tasks, clients, quotes, invoices, finance, inventory, notes, knowledge, meetings, calendar, habits, and focus tools.
- Describe AI as optional assistance for supported actions. Link to documentation for configuration details instead of putting provider names or integration internals in the marketing story.
- It is accurate to say information is encrypted while stored and that the workspace can be self-hosted. Do not claim third-party certification, absolute privacy, or an unsupported security guarantee.
- Do not show implementation details such as PHP, SQL, JSON, AES, APIs, WebView, or server requirements in the main landing page copy.

## Page system

- Shared tokens and homepage components live in `assets/css/public-homepage.css`.
- Keep the homepage to eight substantial content sections followed by the footer.
- Preserve the PHP front-controller route, configured brand, registration-aware calls to action, theme preference, public documentation route, release artifact discovery, Android download link when an APK exists, and accessible mobile navigation.
