# BUSINESS ANALYSIS (PHASE 1) — Aleltu Farm EIFMS

This document outlines the core business processes, organizational roles, transactional workflows, and operating rules of the **Aleltu Farm Company**.

---

## 1. Organizational Structure & Actors

Operations are aligned with the company hierarchy:

1.  **Board of Directors / Owner**: Reviews high-level performance charts and financial summaries.
2.  **General Manager**: Overall farm supervision, financial authorizations, system audits, and final workflow approvals.
3.  **Dairy Manager**: Manages breeding attempts, calving registers, cow health files, and milking sessions.
4.  **Poultry Manager**: Manages flock placement, population demographics, egg collections, and mortality tracking.
5.  **Fish Manager**: Stocking fingerlings, pond water measurements, feeding schedules, and harvest totals.
6.  **Feed Production Manager**: Plot allocations, crop planting logs, harvest yields, and internal distribution fulfillments.
7.  **Veterinary Officer**: Diagnostic entries, vaccination calendars, and quarantine controls.
8.  **Warehouse Keeper**: Reorder rules management, receipt inspections, and FEFO stock issues.
9.  **Distribution Officer**: Driver/vehicle registers and delivery dispatches.
10. **Finance Officer**: Account transaction logs, expenses approvals, PO creation, and payroll runs.
11. **HR Officer**: Employee files, manual attendance logging, and leave approvals.
12. **Field Worker / Milker**: Day-to-day milking yields log, collection figures, and task completion.

---

## 2. Integrated Departmental Workflows

### A. Procurement Workflow
Requisitions flow through authorization limits to warehouse intake:
1.  **Requisition**: Employee raises a purchase request for inventory items.
2.  **Approval**: The request is approved by the Department Supervisor (or GM if over threshold).
3.  **Purchase Order**: Finance Officer converts the request into a PO and sends it to the supplier.
4.  **Goods Receipt**: Warehouse Keeper inspects items on arrival, matches against PO, and registers a stock receipt. This updates available batches and triggers auto-checks on minimum stock thresholds.

### B. Internal Feed Production & Distribution
1.  **Harvest**: Feed Plot Manager logs crop harvest yields, automatically transferring feed stock into warehouse inventory.
2.  **Request**: Dairy, Poultry, or Fish managers raise a feed distribution request.
3.  **Fulfillment**: Warehouse Keeper issues stock (deducting inventory). If stock is insufficient, a purchase request is automatically triggered.

### C. POS Supply & Dispatch
1.  **Preparation**: Inventory is prepared for dispatch to retail locations.
2.  **Dispatch**: Distribution Officer assigns a vehicle and driver, issuing items from the warehouse.
3.  **Delivery**: Receiver at the retail outlet verifies intake. Any variance between dispatched and delivered quantities is flagged for audit review.

---

## 3. Unknown Business Rules & Assumptions
The following parameters are marked as **ASSUMPTIONS** pending management confirmation:

*   **Financial Thresholds**: Large purchase orders (> 10,000 ETB) require General Manager approval. Lower values are approved by the Finance Officer.
*   **Biometric Attendance**: A polling task will sync logs, but manual override capability remains for HR.
*   **Ethiopian Calendar**: Gregorian date format is used in database logs, with English/Amharic translation tags used in UI.
