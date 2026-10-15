# FTS CAR RENTAL AI — Testing Checklist

## 1. Full-Screen Behavior
- [ ] Body does not vertically scroll.
- [ ] App fills 100vw × 100vh.
- [ ] No unexpected scrollbar.
- [ ] Internal panels scroll only when needed.
- [ ] Browser resize does not break scene.

## 2. Opening Showroom
- [ ] Showroom image loads.
- [ ] FTS CAR RENTAL AI logo is correct.
- [ ] Tagline is correct.
- [ ] Enter button is visible.
- [ ] Enter button works.
- [ ] Transition to reception works.
- [ ] No extra traditional navigation appears.

## 3. Reception Scene
- [ ] AI rental agent loads.
- [ ] Greeting appears.
- [ ] Quick actions appear.
- [ ] Chat input works.
- [ ] Microphone works if enabled.
- [ ] Scene fits within viewport.
- [ ] No whole-page scroll.

## 4. Scene Switching
Test:
- [ ] Showroom → Reception
- [ ] Reception → Vehicles
- [ ] Reception → Services
- [ ] Reception → Reservation
- [ ] Reception → Handover
- [ ] Vehicle → Reservation
- [ ] Vehicle → Reception
- [ ] Service → Reception

Verify:
- [ ] smooth transition
- [ ] state preserved
- [ ] no duplicate scene
- [ ] no broken animation

## 5. AI Chat
- [ ] AI answers rental questions.
- [ ] AI knows current scene.
- [ ] AI references selected vehicle correctly.
- [ ] AI does not invent availability.
- [ ] AI does not invent pricing.
- [ ] AI handles unknown data correctly.
- [ ] AI offers staff handover when needed.

## 6. Vehicles
- [ ] Vehicle list loads.
- [ ] Images load.
- [ ] Vehicle data is correct.
- [ ] Previous / Next works.
- [ ] Reservation CTA works.
- [ ] Back to Reception works.

## 7. Services
- [ ] Service list loads.
- [ ] Images load.
- [ ] Opening hours are correct.
- [ ] AI answers service questions.
- [ ] Back works.

## 8. Reservation
- [ ] Check-in validation.
- [ ] Check-out validation.
- [ ] Customer count validation.
- [ ] Passenger capacity validation.
- [ ] Selected vehicle preserved.
- [ ] Name collected.
- [ ] Contact collected.
- [ ] Summary correct.
- [ ] Edit works.
- [ ] Submit works.
- [ ] Reference generated.

## 9. Human Handover
- [ ] Talk to Staff works.
- [ ] WhatsApp link works.
- [ ] Correct number used.
- [ ] Prefilled summary works.
- [ ] Phone link works if enabled.
- [ ] Email link works if enabled.

## 10. Language
- [ ] English UI.
- [ ] Indonesian UI.
- [ ] AI follows selected language.
- [ ] Language switch preserves scene.
- [ ] Language switch preserves session.

## 11. Responsive
Test:
- [ ] 1920×1080
- [ ] 1440×900
- [ ] 1366×768
- [ ] 1024×768
- [ ] 768×1024
- [ ] 390×844
- [ ] 360×800

Check:
- [ ] no body scroll
- [ ] rental agent crop
- [ ] chat panel
- [ ] quick actions
- [ ] vehicle visuals
- [ ] reservation wizard

## 12. Browser
- [ ] Chrome
- [ ] Safari
- [ ] Edge
- [ ] Mobile Safari
- [ ] Mobile Chrome

## 13. Performance
- [ ] showroom image optimized
- [ ] reception image optimized
- [ ] non-current scenes lazy-loaded
- [ ] vehicle images lazy-loaded
- [ ] no huge uncompressed assets
- [ ] AI loading state shown immediately

## 14. Error Handling
Simulate:
- [ ] AI API unavailable
- [ ] slow AI
- [ ] vehicle API failure
- [ ] service API failure
- [ ] network offline
- [ ] invalid reservation
- [ ] WhatsApp unavailable

Expected:
- [ ] user remains in current scene
- [ ] Retry available
- [ ] staff contact available
- [ ] no lost reservation data if recoverable

## 15. Security
- [ ] admin protected
- [ ] authorization tested
- [ ] API keys server-side
- [ ] rate limiting enabled
- [ ] input validation enabled
- [ ] upload validation enabled
- [ ] no secret in frontend source
- [ ] customer data access restricted

## 16. Demo Readiness
- [ ] Opening showroom looks final.
- [ ] FTS CAR RENTAL AI branding final.
- [ ] Enter transition smooth.
- [ ] Reception scene looks final.
- [ ] AI demo questions tested.
- [ ] At least 3 vehicles loaded.
- [ ] Services loaded.
- [ ] Reservation tested end-to-end.
- [ ] WhatsApp handover tested.
- [ ] Desktop demo tested.
- [ ] Mobile demo tested.
- [ ] English tested.
- [ ] Indonesian tested.
