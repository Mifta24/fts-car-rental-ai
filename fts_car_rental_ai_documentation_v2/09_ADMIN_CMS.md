# FTS CAR RENTAL AI — Admin CMS

## 1. Objective
The CMS manages all data used by FTS CAR RENTAL AI without changing source code.

## 2. Main Admin Modules
- Rental Profile
- Vehicles
- Services
- Policies
- FAQ
- Knowledge Base
- Reservation Requests
- Conversation Logs
- Handover Queue
- Scene / Branding Settings

## 3. Scene / Branding Settings
New V2 requirement.

Admin should be able to manage:
- FTS CAR RENTAL AI logo
- showroom background
- reception background
- AI rental agent image / avatar
- tagline
- Enter button text
- default language
- visual asset status

Optional:
- vehicle scene background
- service scene background
- loading image

## 4. Rental Profile
Editable:
- name
- description
- address
- phone
- WhatsApp
- email
- check-in
- check-out
- supported languages

## 5. Vehicles
Admin can:
- create vehicle
- edit vehicle
- upload images
- set passenger capacity
- set vehicle size
- set transmission and fuel type
- set price range
- set insurance
- set fuel type
- activate / deactivate

## 6. Services
Admin can:
- create
- edit
- upload image
- set opening hours
- set fee
- activate / deactivate

## 7. Policies
Manage:
- check-in
- check-out
- cancellation
- fuel policy
- pets
- passengers
- deposit
- payment

## 8. FAQ
Functions:
- create
- edit
- language
- category
- order
- status

## 9. Knowledge
Functions:
- create / upload
- edit
- categorize
- language
- draft
- approve
- archive
- re-index

Only approved content should reach production AI.

## 10. Reservation Requests
List:
- reference
- customer
- dates
- passengers
- vehicle
- contact
- status

Actions:
- open
- contact customer
- change status
- add note

## 11. Conversation Logs
View:
- messages
- selected scene
- selected vehicle
- reservation state
- handover reason

## 12. Handover Queue
Statuses:
- New
- Assigned
- Contacted
- Closed

## 13. Media Rules
Allowed:
- JPG
- PNG
- WebP
- SVG for logo/icon if sanitized

Recommended:
- optimized image upload
- thumbnail generation
- file size limit
- malware / file validation

## 14. V1 Priority
Must Have:
- rental
- vehicles
- services
- policies
- FAQs
- knowledge
- reservation
- showroom / rental agent branding assets
