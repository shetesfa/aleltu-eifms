# API SPECIFICATION (PHASE 4) — Aleltu Farm EIFMS

This document defines the REST API communication schemas to support integration with future Flutter mobile applications.

---

## 1. Response Structure

All endpoints under `/api/v1/` must return a standardized JSON format:

```json
{
  "success": true,
  "message": "Milking logged successfully",
  "data": {
    "entry_id": 482,
    "cow_tag": "AF-1002",
    "yield_liters": 12.5
  },
  "errors": [],
  "pagination": null
}
```

*   `success`: Boolean indicating operation status.
*   `message`: Human-readable feedback message.
*   `data`: Response payload object or array.
*   `errors`: Array of validation error objects (including field names and error messages).

---

## 2. API Endpoints Map

### A. Authentication
*   `POST /api/v1/auth/login`: Accepts credentials, returns a session token.
*   `POST /api/v1/auth/logout`: Revokes the token.

### B. Dairy Herd Operations
*   `GET /api/v1/dairy/cows`: Lists active cows.
*   `POST /api/v1/dairy/milking`: Submits a milking yield entry.
*   `POST /api/v1/dairy/breeding`: Registers a breeding attempt.
*   `POST /api/v1/dairy/calving`: Registers a calving outcome.

### C. Warehouse Inventory
*   `GET /api/v1/warehouse/stock`: Returns stock levels per warehouse.
*   `POST /api/v1/warehouse/issue`: Requests inventory issue.

### D. HR & Attendance
*   `POST /api/v1/hr/attendance/checkin`: Submits GPS/biometric check-in.
*   `POST /api/v1/hr/attendance/checkout`: Submits check-out timestamp.

---

## 3. Offline Synchronisation Queue

To support offline operation in the field:
1.  **Local Buffering**: Mobile client writes records to a local database with a `synced` flag set to `0` and generates a temporary client UUID.
2.  **Queue Sync API**: The client posts buffered records to `/api/v1/sync/queue` on network reconnect.
3.  **Conflict Checking**: Server validates incoming operations:
    *   If valid, imports the transaction, registers audit, and returns `status: "synced"`.
    *   If invalid (e.g. cow is marked deceased, stock batch has expired), returns `status: "conflict"` and adds the item to the user's resolution tray.
4.  **Confirmation**: The mobile client updates local records matching the synced UUIDs to `synced: 1`.
