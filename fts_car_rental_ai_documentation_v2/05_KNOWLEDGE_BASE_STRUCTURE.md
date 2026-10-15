# FTS CAR RENTAL AI — Knowledge Base Structure

## 1. Purpose
The knowledge base is the approved source of rental facts used by the AI agent.

## 2. Main Knowledge Groups

```text
knowledge/
├── rental/
├── vehicles/
├── services/
├── policies/
├── faq/
├── dining/
├── nearby_places/
├── reservation/
└── contact/
```

## 3. Rental Profile
Recommended file:
`rental/overview.md`

Include:
- rental name
- description
- address
- city
- country
- phone
- WhatsApp
- email
- website
- map link
- check-in
- check-out
- languages
- rental introduction text

## 4. Vehicle Data
One file per vehicle type.

Example:
`vehicles/toyota-avanza.md`

Fields:
- vehicle code
- vehicle name
- description
- body type
- transmission and fuel type
- maximum passenger capacity
- view
- fuel rule
- insurance
- price range
- services
- images
- reservation notes

## 5. Services
For each service:
- name
- description
- opening hours
- location
- additional fee
- images
- reservation requirement

## 6. Policies
Include:
- check-in
- check-out
- cancellation
- no-show
- fuel policy
- pet
- child
- deposit
- payment
- visitor
- early check-in
- late check-out

## 7. FAQ
Examples:
- Is insurance included?
- Is a driver available?
- Is Wi-Fi free?
- Is airport pickup available?
- What time is check-in?
- Can I request early check-in?

## 8. Nearby Places
Include:
- place name
- category
- distance
- travel time
- transport method
- notes

Only use verified information.

## 9. Reservation Rules
Include:
- required reservation fields
- passenger capacity rules
- confirmation rules
- deposit rules
- cancellation rules
- escalation rules
- availability disclaimer

## 10. Contact
Include:
- front desk
- reservation team
- WhatsApp
- phone
- email
- contact hours

## 11. Metadata
Recommended:

```yaml
title:
category:
language:
rental_id:
last_updated:
approved_by:
status: draft|approved|archived
```

## 12. Production Rule
Only approved knowledge should be used in production.

## 13. Update Flow

```text
Staff edits knowledge
    ↓
Review
    ↓
Approve
    ↓
Re-index / refresh
    ↓
AI validation test
    ↓
Production
```

## 14. Priority
If information conflicts:

1. current structured rental data
2. approved current document
3. approved FAQ
4. legacy source

Never prefer old data over newer approved data.
