# UI/UX SPECIFICATION (PHASE 5) — Aleltu Farm EIFMS

This document defines the interface standards, component specifications, and user experience patterns of the **Aleltu Farm EIFMS**.

---

## 1. Design System Tokens

The layout uses custom CSS properties to maintain design consistency across light and dark modes:

```css
/* Theme variables inside app.css */
:root {
  --primary: #1e5631;         /* Forest emerald green */
  --primary-hover: #143e22;
  --secondary: #4a7c59;       /* Light sage green */
  --background: #f4f7f3;      /* Off-white background */
  --card: #ffffff;
  --text: #24291f;            /* High-contrast charcoal text */
  --border: #dfe5dd;
  --shadow: rgba(0, 0, 0, 0.05);
}

[data-theme="dark"] {
  --primary: #2e854b;
  --primary-hover: #3ca35f;
  --secondary: #5f9e70;
  --background: #11140f;      /* Dark olive background */
  --card: #181d16;
  --text: #e2e8e0;
  --border: #2c3529;
  --shadow: rgba(0, 0, 0, 0.2);
}
```

---

## 2. Layout Structure & Responsive Design

The interface uses a 2-column sidebar grid layout:

*   **Left Navigation Sidebar**: Flexbox wrapper containing high-visibility icons and page links. Collapses to an overlays menu on mobile devices (< 768px).
*   **Top Action Bar**: Houses the translation selection dropdown, theme toggle button, notification counter, and active profile identifier.
*   **Main Content Area**: Responsive grid layout with automatic columns scaling (e.g. `grid-template-columns: repeat(auto-fit, minmax(300px, 1fr))`).

---

## 3. UI Component Standards

*   **KPI Cards**: Elevated panels displaying metric titles, large numeric values, and indicators for trend direction.
*   **Data Tables**: Striped rows with hover highlight states. Tables must include clear search fields, date range selectors, and pagination controls.
*   **Action Forms**: Clean input fields, explicit labels, and distinct buttons. Submitting a form triggers standard flash messages (green for success, red for errors) near the top of the viewport.
*   **Status Badges**: Styled indicator pills:
    *   `milking`: Green badge (`background: #e3f2fd; color: #0d47a1`).
    *   `pregnant`: Blue badge (`background: #fff3e0; color: #e65100`).
    *   `sold`/`deceased`: Grey badge (`background: #ffebee; color: #c62828`).
