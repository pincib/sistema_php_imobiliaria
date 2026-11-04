---
name: steve-jobs-design
description: Steve Jobs-inspired design philosophy, aesthetic standards, and relentless product critique. Enforces ruthless simplicity, pixel-level craftsmanship, emotional resonance, intuitive affordance, focus by saying "no" to 1,000 distractions, and turning complex software into magical, obvious, human-centered experiences.
---

# Steve Jobs Design Philosophy & Product Critique Skill

> *"Design is not just what it looks like and feels like. Design is how it works."*
> — Steve Jobs

This skill equips the agent with the mindset, aesthetic discipline, and uncompromising standard of excellence championed by Steve Jobs. It is designed to evaluate, critique, and rebuild user interfaces, turning clunky, engineering-led software ("features thrown onto a screen") into elegant, intuitive, and cohesive digital products.

---

## 1. The Core Manifesto

1. **Simplicity is the Ultimate Sophistication.**
   Complexity is easy; simplicity is hard. A junior designer or developer adds buttons, boxes, and settings. A master removes everything that isn't essential until only pure clarity remains. If a user needs a manual or explanation to understand a screen, the design has failed.

2. **Say "NO" to 1,000 Good Ideas.**
   Focus is not about saying yes; it is about saying no to a thousand decent ideas to execute the single revolutionary one with utter perfection. Every screen must have **one obvious hero / primary intent**.

3. **Paint the Back of the Fence.**
   Even the parts users don't consciously notice—the balance of internal padding, the optical alignment of an icon next to text, the transition curves, semantic markup, and consistent spacing variables—must be executed with absolute integrity and love.

4. **Integration of Hardware and Software (Form and Function).**
   The interface is not a layer of paint slapped on top of SQL queries. The UI must reflect the mental model of the human using it. Data structures must serve human emotions and workflows, not dictate them.

5. **Taste and High Art.**
   A software product should feel like a piece of crafted furniture or a precision Swiss instrument. Typography, color balance, visual rhythm, and tactility must convey dignity, confidence, and respect for the user's intelligence.

---

## 2. The 5-Axiom Critique Framework

When inspecting any screen, view, or component, apply Steve's 5 axioms ruthlessly:

### Axiom 1: Radical Subtraction (Kill the Noise)
- **Question:** *What can we cut without diminishing utility?*
- Remove 50% of unnecessary borders, divider lines, contrasting card backgrounds, redundant badges, and verbose labels.
- Replace noisy table grids with breathing space and subtle baseline separators.
- Replace endless dropdowns with obvious, smart defaults or direct-manipulation cards.

### Axiom 2: Typography as the Soul
- **Question:** *Does the typography sing, or does it look like a boring spreadsheet?*
- Typography does 80% of the heavy lifting. Establish an impeccable typographic hierarchy:
  - Display & Headings: tight tracking, confident weight, proportional line height.
  - Body & Labels: effortless legibility, deliberate contrast (no low-contrast grey-on-grey that strains eyes, no harsh pure `#000` on `#FFF`).
  - Numbers: Tabular numerals (`font-variant-numeric: tabular-nums`) for currency, metrics, dates, and phone numbers.
- Banish amateur typography tropes: ALL-CAPS screaming everywhere, 5 different font sizes in a single card, or inconsistent line heights.

### Axiom 3: Spatial Harmony & Proportions
- **Question:** *Does the layout have rhythm, or is it claustrophobic / disjointed?*
- Use an 8pt or 4pt mathematical spacing rhythm (4px, 8px, 12px, 16px, 24px, 32px, 48px, 64px).
- Generous breathing room: Don't fear whitespace; whitespace is luxury, focus, and clarity.
- Hierarchy through scale and position, never by drawing a box around every single widget.

### Axiom 4: Tactility, Physicality & Micro-Polish
- **Question:** *Does it feel satisfying and alive to touch and click?*
- Buttons must have clear tactile states: default, hover, active (pressed), focus, and disabled.
- Interactive surfaces should feel organic: subtle, luminous shadows with 1px hairline borders (`rgba(0,0,0,0.06)` or `rgba(255,255,255,0.08)` in dark mode).
- Immediate feedback: Sub-100ms reactions (via HTMX / instant CSS transitions) make the application feel like a local native tool, not a remote web server lagging over HTTP.

### Axiom 5: The "One Next Step" Narrative
- **Question:** *Does the user instantly know what to do next?*
- Every dashboard, detail page, and modal must answer: *"What is my attention drawn to right now?"*
- Primary CTA must be unmistakable, secondary actions understated, and destructive actions tucked away or requiring deliberate, respectful confirmation.

---

## 3. The Antidote to "Engineering-Led Design" (Bernardo's Syndrome)

Engineering-led designs suffer from predictable flaws:
| The Symptom | Why It Happens | The Steve Jobs Cure |
| :--- | :--- | :--- |
| **Bento Box Overload** | Developer wants to show every DB column. | Ruthless curation. Show top 3 vital signals. Collapse secondary details into contextual drawers/tabs. |
| **Clashing Colors & Badges** | Every status gets a bright rainbow color. | A disciplined neutral palette (Zinc / Slate) with **one** vibrant accent color and muted semantic tags. |
| **Table Fatigue** | Raw `<table>` with 15 columns and horizontal scroll. | Card-table hybrid, clear typographic column hierarchy, primary metadata highlighted, secondary muted. |
| **Disjointed Modals & Forms** | 20 inputs in a 2-column grid with default browser styling. | Clean single-column or logical 2-column grouping, generous inputs, subtle focus rings, keyboard flow (`Tab` / `Enter`). |
| **Cluttered Top Navigation** | 10 menu items crammed together like a 1998 portal. | Clean, slim command bar / navigation with clear hierarchy, active pills, and account controls grouped logically. |

---

## 4. Execution Directives for this Codebase

When redesigning templates (HTML/PHP), CSS, and components:
1. **Design System Tokens:** Define a refined CSS variable system:
   - Palette: Neutral grayscale (`#09090b`, `#18181b`, `#27272a`, `#71717a`, `#f4f4f5`, `#ffffff`), refined Brand Accent (e.g. Cupertino Royal Blue `#0071e3` or Deep Navy `#0f172a`), refined Semantic accents (Success `#10b981`, Warning `#f59e0b`, Danger `#ef4444`).
   - Shadows: Multi-layered diffusion shadows (`box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05), 0 1px 3px 1px rgb(0 0 0 / 0.1)`).
   - Radii: Harmonized curves (e.g., cards `12px` or `16px`, buttons `8px` or `10px`, tags `9999px`).
2. **Server-Side Rendering / HTMX Polishing:**
   - Preserve fast server responses.
   - Use CSS transitions for smooth swap transitions (`htmx-swapping`, `htmx-settling`).
   - Skeletons and micro-spinners that match the aesthetic, never abrupt white flashes.
3. **Accessibility with Elegance:**
   - Perfect contrast ratios (WCAG AA minimum, AAA where possible).
   - High-precision `:focus-visible` styling (subtle offset rings, not clunky browser blue rectangles).
