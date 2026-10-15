# FTS CAR RENTAL AI — User Flow

## 1. Global User Flow

```text
Open Website
    ↓
Opening Showroom
    ↓
Click "Enter FTS CAR RENTAL AI"
    ↓
Reception Scene
    ↓
AI Greeting
    ↓
Customer:
    ├── asks a question
    ├── checks vehicles
    ├── checks services
    ├── starts reservation
    └── asks for staff
```

## 2. Opening Flow

### Step 1
Customer opens the website.

### Step 2
System shows:
- full-screen rental showroom
- FTS CAR RENTAL AI logo
- tagline
- Enter button

### Step 3
Customer clicks:

**Enter FTS CAR RENTAL AI**

### Step 4
Transition to reception scene.

No scrolling is required.

## 3. Reception Flow

```text
Reception Scene
    ↓
AI says welcome message
    ↓
Quick Actions appear
    ↓
Customer chooses action or types a question
```

Quick Actions:
- Check Vehicles
- Reservation
- Services
- Rental Information
- Talk to Staff

## 4. Vehicle Flow

```text
Check Vehicles
    ↓
AI asks optional dates / customer count
    ↓
Vehicle options appear
    ↓
Customer selects vehicle
    ↓
Vehicle Scene opens
    ↓
AI explains selected vehicle
    ↓
Customer can:
    ├── Ask AI
    ├── Next Vehicle
    ├── Previous Vehicle
    ├── Start Reservation
    └── Back to Reception
```

## 5. Services Flow

```text
Services
    ↓
Service menu / cards appear
    ↓
Customer selects service
    ↓
Service Scene / Panel opens
    ↓
AI explains details
    ↓
Back to Reception
```

## 6. Reservation Flow

```text
Reservation
    ↓
Check-in
    ↓
Check-out
    ↓
Passengers
    ↓
Number of Vehicles
    ↓
Preferred Vehicle
    ↓
Customer Name
    ↓
Contact
    ↓
Summary
    ↓
Submit Request / WhatsApp
```

## 7. Human Handover Flow

Trigger:
- user asks for staff
- AI lacks confirmed information
- payment issue
- complaint
- group booking
- special price request
- complex booking

Flow:

```text
AI detects handover need
    ↓
AI explains handover
    ↓
Conversation summary prepared
    ↓
Customer chooses:
    ├── WhatsApp
    ├── Phone
    └── Email
```

## 8. Back Navigation
Every scene must provide a clear way back.

Examples:
- Back to Reception
- Back to Showroom
- Close Panel

The app should preserve:
- conversation history
- selected vehicle
- reservation progress
- language

## 9. Unknown Question Flow

```text
Customer asks something unclear
    ↓
AI asks one short clarification
    ↓
If still unclear:
show suggested actions
    ↓
If unresolved:
offer staff handover
```

## 10. API Error Flow
If AI fails:

- keep current scene
- keep user input if possible
- show short error
- show Retry
- keep staff contact available

## 11. No Matching Vehicle
If no vehicle matches:

- AI explains no exact match was found
- suggests alternatives
- offers staff handover

## 12. Returning Session
If the session is still active:
- do not restart the full opening experience unless user chooses to
- restore current scene
- restore selected vehicle
- restore reservation state
- restore conversation
