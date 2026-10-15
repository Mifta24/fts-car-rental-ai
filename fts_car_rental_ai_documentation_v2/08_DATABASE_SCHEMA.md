# FTS CAR RENTAL AI — Database Schema

## 1. Core Tables

### rentals
- id
- name
- slug
- description
- address
- city
- country
- phone
- whatsapp
- email
- open_time
- close_time
- default_language
- active
- created_at
- updated_at

### vehicles
- id
- rental_id
- code
- name
- description
- model_year
- seats
- luggage_capacity
- transmission
- body_type
- fuel_type
- insurance_included
- price_min
- price_max
- currency
- active
- created_at
- updated_at

### vehicle_images
- id
- vehicle_id
- image_url
- alt_text
- sort_order
- active

### vehicle_features
- id
- vehicle_id
- name
- description
- icon
- sort_order

### services
- id
- rental_id
- name
- slug
- description
- location
- opening_time
- closing_time
- fee_text
- image_url
- active

### rental_policies
- id
- rental_id
- policy_type
- title
- content
- language
- active
- effective_from

### faqs
- id
- rental_id
- category
- question
- answer
- language
- sort_order
- active

### knowledge_documents
- id
- rental_id
- title
- category
- language
- content
- source_type
- status
- version
- approved_by
- approved_at

### conversations
- id
- rental_id
- session_id
- language
- current_scene
- selected_vehicle_id
- status
- started_at
- ended_at

### messages
- id
- conversation_id
- role
- content
- intent
- model
- latency_ms
- metadata
- created_at

### reservation_requests
- id
- reference
- rental_id
- conversation_id
- vehicle_id
- pickup_date
- return_date
- passengers
- passengers
- unit_count
- customer_name
- contact_type
- contact_value
- special_request
- status
- submitted_at

### handovers
- id
- conversation_id
- reservation_request_id
- channel
- reason
- summary
- status
- staff_user_id
- created_at

## 2. Scene State
Store scene-related state in `conversations` or `sessions`.

Recommended:
- current_scene
- selected_vehicle_id
- selected_service_id
- reservation_state
- language

## 3. Optional Session Table
If needed:

### visitor_sessions
- id
- rental_id
- session_uuid
- current_scene
- language
- selected_vehicle_id
- reservation_state_json
- expires_at
- created_at
- updated_at

## 4. Admin Users
Use existing users table or:

- id
- rental_id
- name
- email
- password_hash
- role
- active

## 5. Future Tables
- vehicle_inventory
- vehicle_rates
- bookings
- payments
- integrations
- promotions
- analytics_events
- customer_profiles
