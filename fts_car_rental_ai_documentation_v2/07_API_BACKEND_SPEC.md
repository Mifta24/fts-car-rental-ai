# FTS CAR RENTAL AI — API & Backend Specification

## 1. Main Principle
Frontend is a scene-based single-page experience.

Backend provides:
- session state
- chat
- rental data
- vehicles
- services
- reservation
- handover
- admin data

Base:
`/api/v1`

## 2. Session

### POST `/sessions`
Creates a visitor session.

Suggested response:

```json
{
  "session_id": "uuid",
  "language": "en",
  "current_scene": "showroom"
}
```

### PATCH `/sessions/{id}`
Update:
- language
- current_scene
- selected_vehicle
- reservation_state

## 3. Scene State
Suggested scene values:
- showroom
- reception
- vehicles
- vehicle_detail
- services
- service_detail
- reservation
- handover

The backend may persist current scene so the session can resume.

## 4. Chat

### POST `/chat/messages`

Request:

```json
{
  "session_id": "uuid",
  "message": "Show me a vehicle for two people",
  "language": "en",
  "current_scene": "reception",
  "selected_vehicle_id": null
}
```

Response:

```json
{
  "reply": "I can help you with that.",
  "intent": "vehicle_search",
  "next_scene": "vehicles",
  "suggested_actions": [
    "View Vehicles",
    "Start Reservation"
  ],
  "handover_required": false
}
```

## 5. Rental

### GET `/rental`
Returns:
- name
- logo
- contact
- check-in / check-out
- supported languages

## 6. Vehicles

### GET `/vehicles`

Optional filters:
- passengers
- pickup_date
- return_date
- bed_type
- fuel policy

### GET `/vehicles/{vehicle}`

Return:
- details
- images
- features
- price range
- availability if connected

## 7. Services

### GET `/services`
### GET `/services/{service}`

## 8. Reservation

### POST `/reservation-requests`

Data:
- session_id
- pickup_date
- return_date
- passengers
- passengers
- vehicles
- vehicle_id
- customer_name
- contact
- special_request

### GET `/reservation-requests/{reference}`
Customer-safe status only.

## 9. Handover

### POST `/handovers`

Request:

```json
{
  "session_id": "uuid",
  "channel": "whatsapp",
  "reason": "customer_requested_staff"
}
```

Response:
- handover URL
- summary
- reference

## 10. General Response
Success:

```json
{
  "success": true,
  "data": {},
  "message": null
}
```

Error:

```json
{
  "success": false,
  "data": null,
  "message": "Human readable error",
  "error_code": "VALIDATION_ERROR"
}
```

## 11. AI Flow

```text
Receive Chat Message
    ↓
Validate
    ↓
Load Session + Current Scene
    ↓
Retrieve Relevant Knowledge
    ↓
Build AI Context
    ↓
Call Model
    ↓
Validate Response
    ↓
Save Conversation
    ↓
Return Reply + Suggested UI Action + Optional Next Scene
```

## 12. Admin Endpoints
Suggested:
- CRUD rental
- CRUD vehicles
- CRUD services
- CRUD policies
- CRUD FAQs
- CRUD knowledge
- list conversations
- list reservations
- list handovers

## 13. Security
- admin auth
- role-based authorization
- rate limiting
- server-side validation
- server-side AI keys only
- no secrets in frontend
- secure file upload validation

## 14. Logging
Log:
- request ID
- session ID
- current scene
- selected vehicle
- AI latency
- model
- errors
- reservation reference

Do not log unnecessary sensitive information.
