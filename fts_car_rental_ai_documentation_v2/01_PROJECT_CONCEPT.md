# FTS CAR RENTAL AI — Project Concept

## 1. Project Name
**FTS CAR RENTAL AI**

## 2. Product Vision
FTS CAR RENTAL AI is a full-screen, one-page interactive rental experience.

The website should feel like the customer is entering a real rental showroom and being welcomed by an AI rental agent / agent.

This is **not** a traditional rental website with long scrolling sections.

The entire experience runs inside a fixed viewport:

- `height: 100vh`
- `overflow: hidden`
- no long vertical scrolling
- scene-based navigation
- chat, vehicle information, reservation, services, and handover appear as overlays, panels, or scene transitions

## 3. Core Experience

```text
Open Website
    ↓
Opening Showroom
    ↓
[ Enter FTS CAR RENTAL AI ]
    ↓
AI Rental Agent
    ↓
Choose Need / Ask AI
    ↓
Vehicle / Services / Reservation / Rental Info
    ↓
Human Handover if needed
```

## 4. Main UX Principle
The customer should never feel like they are browsing many pages.

Instead, the website behaves like an interactive application.

Traditional structure to avoid:

```text
Home
↓
About
↓
Vehicles
↓
Services
↓
Contact
↓
Footer
```

Target structure:

```text
Showroom
↕
Reception
↕
Vehicle
↕
Services
↕
Reservation
↕
Staff Handover
```

## 5. Scene 0 — Opening Showroom
This is the first screen.

Purpose:
- establish the rental atmosphere
- introduce the FTS CAR RENTAL AI brand
- provide one clear action to enter the experience

Main elements:
- full-screen luxury rental showroom
- FTS CAR RENTAL AI logo
- short tagline
- one primary CTA

Suggested CTA:

**Enter FTS CAR RENTAL AI**

Alternative:

**Enter Rental**

Suggested tagline:

**AI Agent for a Smarter Trip**

or

**Your Intelligent Rental Experience**

## 6. Scene 1 — AI Rental Agent
After pressing Enter, transition to a closer reception view.

Main elements:
- AI rental agent / agent
- rental branding
- AI greeting
- quick action menu
- chat input
- optional microphone
- no page scroll

Suggested quick actions:
- Check Vehicles
- Reservation
- Services
- Rental Information
- Talk to Staff

Example greeting:

> Welcome to FTS CAR RENTAL AI. How can I assist you today?

## 7. Scene 2 — Vehicle Experience
When the customer chooses vehicles:

- scene changes without leaving the app
- vehicle image / vehicle visual becomes the main focus
- AI remains available
- vehicle information appears in a panel
- customer can continue chatting

Information:
- vehicle name
- vehicle images
- body type
- transmission and fuel type
- passenger capacity
- vehicle services
- price range
- insurance
- fuel policy
- availability status if connected

Actions:
- Ask AI
- Previous Vehicle
- Next Vehicle
- Reservation
- Back to Reception

## 8. Scene 3 — Services
Services appear as a full-screen scene or overlay.

Possible categories:
- Driver Service
- Airport Delivery
- Child Seat
- Insurance & Protection
- Roadside Support
- Tour Packages
- Corporate Rental
- Hotel Delivery

Actions:
- Ask About Service
- View Another Service
- Back to Reception

## 9. Scene 4 — Reservation
Reservation should be guided, not a long form.

Steps:
1. Check-in
2. Check-out
3. Passengers
4. Number of vehicles
5. Preferred vehicle
6. Customer name
7. Contact
8. Summary

Final actions:
- Submit Reservation Request
- Send to WhatsApp
- Contact Rental Staff
- Back

## 10. Scene 5 — Human Handover
If the user needs staff support:

- AI explains that a rental staff member can assist
- conversation summary is prepared
- user chooses a handover channel

Channels:
- WhatsApp
- Phone
- Email

## 11. AI Agent Role
The AI acts like a professional rental rental agent.

Responsibilities:
- welcome visitors
- answer rental questions
- explain vehicles
- explain services
- explain policies
- support reservation requests
- recommend suitable vehicle types
- hand over to staff when necessary

The AI must never invent rental facts.

## 12. Language
V1:
- English
- Indonesian

Future:
- Japanese
- Chinese
- Korean

## 13. V1 Scope

### Must Have
- FTS CAR RENTAL AI branding
- 100vh full-screen layout
- no long vertical scroll
- opening showroom scene
- Enter button
- AI rental agent scene
- vehicle scene
- services scene
- reservation scene
- AI chat
- rental knowledge base
- WhatsApp / staff handover
- desktop + mobile responsive
- English + Indonesian

### Nice to Have
- voice input
- AI voice response
- animated rental agent
- advanced scene transitions
- chat history panel

### Not Required for V1
- direct payment
- fleet management system integration
- OTA integration
- full 3D rental
- real-time avatar lip-sync
- autonomous final booking confirmation

## 14. Success Criteria
The V1 is successful when a visitor can:

1. Open the website and see a rental showroom.
2. Enter FTS CAR RENTAL AI from one clear button.
3. Meet the AI rental agent.
4. Ask questions naturally.
5. View vehicle options.
6. Ask about services.
7. Start a reservation.
8. Contact human rental staff.
9. Complete the whole journey without scrolling through a long webpage.
