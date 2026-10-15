# FTS CAR RENTAL AI — UI/UX Specification

## 1. Core Layout Rule
The application must behave as a fixed full-screen experience.

Base layout:

```css
html,
body,
#app {
  width: 100%;
  height: 100%;
  margin: 0;
  overflow: hidden;
}
```

Main scene:

```css
.scene {
  width: 100vw;
  height: 100vh;
  position: relative;
  overflow: hidden;
}
```

There must be no long vertical webpage scrolling.

## 2. Opening Showroom Screen

### Purpose
Create a strong first impression.

### Main Elements
- full-screen rental showroom image
- FTS CAR RENTAL AI logo
- tagline
- one main button
- optional subtle language selector

### CTA
Primary:
**Enter FTS CAR RENTAL AI**

### Visual Priority
1. Showroom
2. Brand
3. Enter button

Avoid:
- many menu buttons
- large text blocks
- traditional navigation bars
- footer

## 3. Reception Scene

### Desktop
Suggested composition:
- AI rental agent: center or center-left
- chat panel: right side
- quick actions: right or bottom
- rental branding: top / wall background
- input bar: lower area

### AI Rental Agent
Should look:
- professional
- rental staff
- human
- premium
- approachable

Avoid:
- robot look
- overly futuristic costume
- sci-fi hologram style

## 4. Quick Action Menu
Recommended:
- Check Vehicles
- Reservation
- Services
- Rental Information
- Talk to Staff

Rules:
- maximum 5 primary actions at one time
- large touch target
- icon + text
- clear visual hierarchy

## 5. Chat Input
Include:
- text input
- send button
- optional microphone
- typing indicator
- suggested prompts

The input should not cover important rental agent visuals.

## 6. Vehicle Scene
The vehicle scene replaces the reception visual area.

Suggested layout:
- large vehicle visual: 60–70%
- information / chat panel: 30–40%

Elements:
- vehicle name
- short description
- passenger capacity
- seats and luggage
- body type
- price range
- vehicle services
- action buttons

## 7. Services Scene
Can use:
- full-screen background change
or
- large overlay panel

Examples:
- driver
- support
- package
- delivery
- meeting vehicle

AI remains available.

## 8. Reservation Scene
Do not use one long form.

Use guided steps:

```text
Step 1 of 5
Dates
```

then:

```text
Step 2 of 5
Passengers
```

etc.

Preferred display:
- centered modal
or
- right-side panel
or
- full-screen scene on mobile

## 9. Scene Transition
Recommended:
- fade
- slight zoom
- horizontal slide
- crossfade background

Timing:
- small panel: 200–350ms
- scene change: 350–700ms

Avoid transitions longer than 1 second for normal actions.

## 10. Navigation Model
Use scene state, not scrolling.

Example:

```text
currentScene = showroom
currentScene = reception
currentScene = vehicles
currentScene = services
currentScene = reservation
currentScene = handover
```

## 11. Desktop Behavior
Target:
- 1280px+
- 1366×768
- 1440×900
- 1920×1080

Important:
- no hidden main buttons
- no forced browser scroll
- maintain rental agent visibility
- maintain readable chat

## 12. Tablet Behavior
At around 768–1024px:
- reduce rental agent size
- make chat panel narrower
- quick actions may become horizontal row
- vehicle details may become overlay panel

## 13. Mobile Behavior
At under ~768px:
- keep full-screen
- no document scroll
- allow internal panel scrolling only where necessary
- chat becomes bottom sheet or full-screen overlay
- quick actions become 2-column buttons
- rental agent crop must remain natural
- reservation uses full-screen step wizard

Important:
The page itself trips fixed.
Only internal panels may scroll.

## 14. Internal Scroll
Allowed only inside:
- chat history
- vehicle detail panel
- service detail panel
- reservation detail panel

Not allowed:
- whole page body scroll

## 15. Loading States

### Initial Load
Display:
- logo
- short loader
- text such as `Preparing your rental experience...`

### AI Loading
Display:
- typing dots
or
- `AI Agent is responding...`

### Scene Asset Loading
Use:
- blurred placeholder
- skeleton
- smooth reveal

## 16. Error States

### AI Error
> I’m having trouble responding right now. Please try again or contact rental staff.

Actions:
- Retry
- Talk to Staff

### Asset Error
Fallback background or neutral panel.

## 17. Accessibility
- keyboard focus
- readable contrast
- clear labels
- alt text
- visible focus states
- no critical information conveyed only by color
- large touch targets

## 18. Visual Style
Keywords:
- luxury
- modern
- clean
- warm
- premium
- welcoming
- intelligent

Avoid:
- dashboard look
- excessive gradients
- overly futuristic UI
- crowded navigation
