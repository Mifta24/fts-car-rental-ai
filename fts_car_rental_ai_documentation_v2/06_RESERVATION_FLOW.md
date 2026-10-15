# FTS CAR RENTAL AI — Reservation Flow

## 1. UX Principle
Reservation must happen inside the fixed full-screen experience.

Do not redirect the customer to a long standalone form.

Use:
- modal
- right-side panel
- scene change
- step-by-step wizard

## 2. Entry Points
Reservation can start from:
- Reception Scene
- Vehicle Scene
- AI conversation
- Service / event conversation
- direct reservation quick action

## 3. Required Fields
- Check-in
- Check-out
- Passengers
- Number of vehicles
- Preferred vehicle
- Customer name
- Contact method

Optional:
- passengers
- special request
- child seat preference
- insurance
- accessibility needs

## 4. Step Flow

### Step 1 — Dates
Ask:
- check-in
- check-out

### Step 2 — Passengers
Ask:
- passengers
- passengers if needed
- number of vehicles

### Step 3 — Vehicle
Use selected vehicle if already chosen.

Otherwise:
- ask preference
or
- recommend from known data

### Step 4 — Customer
Ask:
- name
- contact method

### Step 5 — Summary
Show:
- vehicle
- dates
- passengers
- vehicles
- special request
- contact

Then:
- Confirm Request
- Edit
- Back

## 5. Date Validation
Rules:
- check-in cannot be in the past
- check-out must be after check-in
- use rental timezone
- invalid input should be corrected immediately

## 6. Passenger capacity Validation
Check:
- vehicle capacity
- number of passengers
- number of vehicles

If capacity is exceeded:
- suggest another vehicle type
- suggest multiple vehicles
- offer staff assistance

## 7. Availability

### If real-time system is connected
Show actual availability.

### If not connected
Use:

> This is a reservation request. Final availability will be confirmed by rental staff.

Never fake availability.

## 8. Summary Example

```text
Reservation Request

Vehicle: Toyota Avanza
Check-in: 10 October 2026
Check-out: 13 October 2026
Passengers: 2
Vehicles: 1
Name: John Tan
Contact: WhatsApp
Status: Awaiting Rental Confirmation
```

## 9. WhatsApp Handover
Suggested prefilled message:

```text
Hello, I would like to request a reservation.

Name: {{name}}
Check-in: {{pickup_date}}
Check-out: {{return_date}}
Passengers: {{passengers}}
Vehicles: {{vehicles}}
Vehicle Type: {{vehicle}}
Special Request: {{special_request}}

Reference: {{reference}}
```

## 10. Statuses
Suggested backend statuses:
- draft
- awaiting_customer_confirmation
- submitted
- contacted
- confirmed
- rejected
- cancelled

## 11. Exit / Back
At every reservation step:
- Back
- Close
- Return to Reception

Closing should not automatically delete progress.

## 12. V1 Payment Rule
Do not collect card data in AI chat.

If payment is needed:
- open official rental payment process
or
- hand over to staff
