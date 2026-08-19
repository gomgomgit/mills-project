# Product Requirements Document
# Mill Smart-Log / Mills Operation System

Version: 0.4
Date: 2026-08-14
Sources:
- Figma: `Mills System - Copy` (`S0PuCmq4Q7qYR1n1wlR62C`), page node `0:1`
- SRS: `MMS SRS Design v1.0.pdf`
- Device reference: `Mill Devices Data Sheet.pdf`

## 1. Product Summary

Mill Smart-Log is a mobile operator application and web management system for CPO mill log sheet digitalization. The system replaces manual paper log sheets for the first MVP stations, supports offline field data entry, provides web monitoring/data preview/basic export, and prepares records for later synchronization with local mill servers, central servers, and optional ERP integration.

The current Figma design represents the mobile application experience under the name `Mills Operation System` / `Mill Management System`, using a portrait mobile layout with Eagle High Plantations branding.

## 2. Goals

1. Digitize station log sheets for CPO mill operations from FFB reception through process quality control.
2. Reduce manual entry errors and delayed reporting.
3. Allow operators to input data without internet connectivity.
4. Provide station-level monitoring, data preview, and basic web export, with full PDF templates reserved for a later phase.
5. Prepare synchronization capability via API/local server design without making full sync workflow the main MVP delivery.
6. Prepare a scalable base for optional IoT/device integrations.

## 3. Target Users

1. Station Operator
   - Uses a mobile device or tablet on the mill floor.
   - Inputs and edits own draft/saved station log sheet data according to station access.

2. Supervisor
   - Reviews saved station data, dashboard summaries, and basic exports.
   - Uses the web app for monitoring and data preview.

3. Admin
   - Manages users, roles, master data, station setup, machinery master scope, and optional operational targets.

4. Head Office Management
   - Reviews consolidated multi-location summaries in later phases.
   - Uses central server/web dashboard when multi-location reporting is enabled.

## 4. MVP Scope

### In Scope

1. Mobile app shell for Android and iOS.
2. Login and role-based landing page.
3. Mobile home page with three primary modules:
   - Estimates & Baselines
   - Production Process Activity
   - Dashboard & Reporting
4. Production Process Activity station list.
5. Station detail template with:
   - Station image/reference
   - Load/Edit Data action
   - Monitor action
   - Data Preview action
6. Mobile operator implementation for Weighbridge:
   - Load WB Data form
   - Monitor Active WB Progress
   - Save and data preview flow
7. Mobile operator implementation for Grading:
   - Load Grading Data form
   - Monitor Grading Progress / summary
   - Save and data preview flow
8. Mobile operator implementation for Cages Track / Loading Ramp:
   - Load Cages and Track Data form
   - Cages Header and Cages Tipped Time grid
   - Save and data preview flow
9. Offline input with local device retention for Weighbridge, Grading, and Cages Track.
10. Local record status fields so saved records are API-ready for later sync.
11. Mobile Data Preview for saved Weighbridge, Grading, and Cages Track records.
12. Web app for Admin/Supervisor with:
   - Login and role-based access.
   - Dashboard summary for Weighbridge, Grading, and Cages Track.
   - Saved data browser with date, business unit/mill, and station filters.
   - Detail preview per saved record.
   - CSV/Excel export basic.
   - User/role management minimum.
13. Read-only operational master data on mobile for Station, Machinery Group,
    Machinery/Equipment, Machinery Insurance, and Machinery Tax/Purchase.
14. Admin maintenance of MVP master data:
   - Corporate
   - Company
   - Business Unit/Mill, with mill represented as `business_units.business_type = MILL`
   - Station
   - Machinery Group
   - Machinery
   - Machinery Insurance
   - Machinery Tax/Purchase

### Designed / Future Station Scope

The following stations remain in ERD/data design as prepared future scope, but
are not required as implemented MVP mobile/web screens:

1. Sterilization
2. Threshing
3. Pressing
4. Depricarping
5. Kernel Plant
6. Clarification
7. Storage Tank
8. Effluent Plant
9. Boiler Room
10. Engine Room
11. Oil Dispatch
12. Kernel Dispatch
13. Process Water
14. Solid Waste Disposal
15. Process Quality Control

### Out of Scope for MVP

1. Fully automated station operation control.
2. Direct control of PLC, valves, conveyors, or machinery.
3. AI prompt feature, except as placeholder/extension point.
4. Complete ERP bidirectional integration, except export/staging interfaces.
5. IoT/device ingestion and full automation for every station.
6. Formal approve/reject workflow; Supervisor review is read/review/export only.
7. Audit log module.
8. Full PDF template/report engine.
9. Full sync conflict handling and complete ERP bidirectional integration.

## 5. Design Requirements from Figma

### 5.1 Visual Direction

Use the Figma mobile design as the primary UI reference.

Key style cues:
- Mobile portrait canvas: approximately `834 x 1194` for standard screens, with longer scrolling forms.
- Primary brand color: green `#249360`.
- Header/background tint: light green `#D6F6E5` / `#E3F5EB`.
- Input fields: light gray `#EDEBEB`.
- Station grid buttons: red `#D20000` with white text.
- Main typography in Figma: Belanosima for headings/buttons, Calibri appears in some reference output.
- Header title labels: `Mills Operation System` on home and `Mill Management System` on inner screens.
- Large touch targets for field operators.
- Rounded menu cards and inputs.

Implementation note: Do not blindly paste generated Figma Tailwind code. Rebuild using the target project stack and component system while matching the visual intent.

### 5.2 Figma Screen Inventory

1. `Opener` (`5:25`)
   - Splash/opening screen with EHP logo.

2. `Login` (`5:26`)
   - Logo, `Mills System`, username, password, login button, forgot password.

3. `Home Page` (`26:1110`)
   - Header with hamburger menu and EHP logo.
   - Greeting: `Hello, Operator`.
   - Prompt: `What's the task for today?`
   - Cards:
     - Estimates & Baselines
     - Production Process Activity
     - Dashboard & Reporting

4. `Production Process Activity` (`103:53`)
   - Back/header.
   - Mill image.
   - Grid station selector.

5. `Weighbridge` (`105:47`)
   - Breadcrumb chips: Production Process Activity > Weighbridge.
   - Weighbridge image.
   - Actions: `Load WB Data`, `Monitor`, `Data Preview`.

6. `WB New | WB Load Data` (`106:48`)
   - Long form for Weighbridge data entry.
   - Actions: save/edit-style actions aligned to the final form.

7. `WB Monitor` (`106:49`)
   - Monitor current weighbridge progress.
   - Sections: Arrival and Dispatched.
   - Actions: `Pause`, `Clear`, `Back`.

8. `WB Monitor-Stop` (`112:286`)
   - Saved-data preview/completion state.
   - Actions: `Save`, `Data Preview`, `Back`.

9. Additional station detail screens exist for Cages & Track, Sterilization, Tippler, Threshing, Pressing, Clarification, Engine Room, Boiler Room, Kernel Plant, Depericarping, Process Quality Control, Storage Tank, Effluent Plant, Oil Dispatch, Kernel Dispatch, Process Water, and Solid Waste Disposal.

### 5.3 Station Naming Normalization

Normalize labels in implementation even where Figma contains spelling issues:
- `Oil Dispacth` -> `Oil Dispatch`
- `Thressing` -> `Threshing`
- `Depericapring` / `Depericarping` -> choose one canonical label: `Depericarping`
- `Solid Water Disposal` -> likely `Solid Waste Disposal`; confirm with product owner.
- `License Plat No` -> likely `License Plate No`; confirm or localize as required.
- `Prosess` -> `Process`

## 6. Functional Requirements

### 6.1 Authentication and Device Activation

1. The app shall require login before accessing station modules.
2. Login fields:
   - Company / Business Area, predefined during initialization.
   - Username/NIK, aligned with ERP employee identity.
   - Password, minimum 6 characters, case-sensitive, supports alphabet, numeric, and special characters.
3. The app shall support role-based authorization according to an access matrix.
4. The app shall support password change on mobile.
5. The app installer shall be downloadable from the local server.
6. Device activation shall require a valid activation code generated by System Admin.

### 6.2 Mobile Home

1. The app shall show a role-based landing page after login.
2. The app shall show the current operator greeting.
3. The app shall show the three primary modules:
   - Estimates & Baselines
   - Production Process Activity
   - Dashboard & Reporting
4. The app shall show visual status of ongoing station input:
   - Black label/status: no ongoing data input.
   - Red label/status: data input is ongoing.

### 6.3 Production Process Activity

The station selector shall show the following stations:

1. Weighbridge
2. Grading
3. Cages and Track
4. Sterilization
5. Tippler
6. Threshing
7. Pressing
8. Depericarping
9. Kernel Plant
10. Clarification
11. Storage Tank
12. Effluent Plant
13. Boiler Room
14. Engine Room
15. Oil Dispatch
16. Kernel Dispatch
17. Process Water
18. Solid Waste Disposal
19. Process Quality Control

For MVP implementation, only Weighbridge, Grading, and Cages Track are active
data-entry stations. The remaining stations may be shown as disabled,
placeholder, or later-phase entries according to product decision.

Each station shall use a shared station detail pattern:
- Header and breadcrumb/back navigation.
- Station title.
- Station reference image.
- `Load/Edit Data`.
- `Monitor`.
- `Data Preview`.

### 6.4 Station Input Cycle

Each implemented station shall support a manual header/detail input cycle.

#### State 1: Load/Edit Data

Actions:
- `Find Data`: searches/selects an existing station record where available.
- `Add`: creates a draft header or detail row.
- `Save`: validates required fields and persists data to local storage.
- `Delete`: soft-deletes draft or saved records when allowed.
- `Data Preview`: opens saved data preview.
- `Clear`: clears fields and local buffer for the current draft.
- `Back`: returns to previous station screen.

Rules:
- Draft data must remain available offline.
- User should be warned before losing unsaved draft data.
- Saving creates or updates a station-specific header/detail record, not a generic session.

#### State 2: Monitor / Summary

Actions:
- `Clear`: clears monitor filters/input where applicable.
- `Back`: returns to previous station screen.
- `Data Preview`: opens saved record detail where available.

Rules:
- Monitor should show current summary for selected station records.
- Manual entry mode must work even when IoT integration is unavailable.
- IoT values are not required for the current MVP.

#### State 3: Later-Phase Report

Actions:
- `PDF`: generate, show, print, or share report in PDF format when report module is re-enabled.
- `Back`: returns to previous station screen.

Rules:
- Reports must only be generated from saved records.
- PDF output should include station, date/time, operator/PIC, record ID, and all relevant measurements.

### 6.4A Mobile App MVP Requirements

The mobile app shall support:

1. Login and role-based station access.
2. Station selector.
3. Weighbridge input grid using the `weighbridges` table fields.
4. Grading input with `gradings`, `grading_details`, and seeded
   `grading_parameters`.
5. Cages Track input with `cages_tracks`, `cages_track_time_rows`, and
   `cages_track_time_cells`.
6. Offline local save for Weighbridge, Grading, and Cages Track.
7. Data Preview for saved records in the three MVP stations.

The mobile app is not required in MVP to support:

1. Full PDF generation.
2. Formal approval workflow.
3. Full sync conflict handling.
4. IoT/device ingestion.

### 6.4B Web App MVP Requirements

The web app shall support:

1. Login for Admin and Supervisor roles.
2. Dashboard summary for Weighbridge, Grading, and Cages Track.
3. Saved data browser with filters for date, business unit/mill, and station.
4. Detail preview per saved record.
5. CSV/Excel export basic from filtered saved records.
6. Master data management for Corporate, Company, Business Unit/Mill, Station,
   Machinery Group, Machinery, Machinery Insurance, and Machinery Tax/Purchase.
7. User and role management minimum.

The web app is not required in MVP to support:

1. Formal approve/reject workflow.
2. Audit log module.
3. PDF template engine.
4. Full ERP integration.

### 6.5 Weighbridge MVP Requirements

#### Load WB Data Form

Fields from Figma:
- WB ID
- WB Card No
- Running No
- Date
- License Plate No
- Vehicle No
- First Weight
- Second Weight
- Net Weight
- Delivery Note
- Estate/Supplier/Buyer
- Divisi
- Blok
- Quantity
- Driver

Validation:
- WB ID, date, vehicle/license plate, and at least one weight field should be required.
- Weight fields must be numeric and use metric units.
- Net Weight may be calculated from first and second weight when both values are available.
- Date/time should default to device time but respect app restrictions around changing system date/time.
- Vehicle, driver, Estate/Supplier/Buyer, divisi, and blok are plain text inputs for MVP and shall not require separate master data tables.
- Driver and delivery note may be searchable or scannable later only if master data or integration is added as an enhancement.

Actions:
- `Save`: persists current Weighbridge record to local database.
- `Clear`: clears form.
- `Back`: returns to Weighbridge station screen.

#### Weighbridge Monitor

Fields:
- WB ID
- Product to Monitor
- Date
- Time

Arrival summary:
- Sum WB Card
- Sum Net Weight (MT)
- Sum Quantity

Dispatched summary:
- Sum WB Card
- Sum Net Weight (MT)
- Sum Quantity

Actions:
- `Clear`
- `Back`
- `Data Preview`, when enabled from saved records.

#### Weighbridge Completion

Actions:
- `Save`: persists current buffered record to local database.
- `Data Preview`: opens saved Weighbridge data preview.
- `Back`: returns to monitor/station page.

Integration note:
- Existing weighbridge application/device integration may provide measurement data from WE-8000 or equivalent scale interface.
- Automation should not assume every truck carries FFB; manual classification/product selection is required.

### 6.6 Grading MVP Requirements

Grading is part of ST1 FFB Reception and should support manual grading input for one truck/WB card. Grading may be linked to a Weighbridge transaction when the WB card or transaction is already available, but it must also work as a standalone offline input.

#### Load Grading Data Form

The Figma screen confirms a header-detail structure.

Grading Header fields:
- Grading No
- Date
- WB Card No
- License Plate No
- Vehicle Code
- Estate
- Division
- Netto (Kg)
- Quantity (Bunch)
- Note

Grading Detail fields, repeatable for each quality line:
- Quality Parameter
- Quantity
- UoM
- Percentage (%)

Validation:
- Grading No and Date are required.
- WB Card No or License Plate No should be required to identify the load.
- Netto must be numeric, non-negative, and stored in kilograms.
- Header Quantity must be numeric, non-negative, and interpreted as bunch.
- Detail Quantity and Percentage must be numeric and non-negative.
- Percentage must be constrained to 0-100.
- Each detail row must contain a Quality Parameter and UoM.
- Vehicle Code, Estate, and Division remain direct fields for MVP and do not require separate master tables.
- If linked to Weighbridge, WB Card No, license plate, vehicle code, Estate, Division, and Netto may be prefilled but must remain editable before save.

Actions shown in Figma:
- `Find Grading Data`: searches/selects an existing grading record.
- Header: `Add`, `Save`, `Delete`, and `Data Preview`.
- Detail: `Add` and `Edit`.
- Detail grid columns: `Quality`, `Quantity`, `UoM`, and `Percentage`.

#### Grading Monitor

Summary:
- Total Grading Record
- Total Netto (Kg)
- Total Quantity (Bunch)
- Summary detail grouped by Quality Parameter and UoM
- Percentage per quality parameter when the calculation rule is confirmed

Actions:
- `Clear`
- `Back`
- `Data Preview`, when enabled from saved records.

#### Grading Completion

Actions:
- `Save`: persists current buffered grading header and detail records to local database.
- `Data Preview`: opens saved grading data preview.
- `Back`: returns to monitor/station page.

Open confirmation:
- Source/master list for Quality Parameter and UoM must be confirmed.
- The formula and denominator for Percentage (%) must be confirmed; the system must not average percentages with different UoM blindly.

### 6.7 Cages and Track MVP Requirements

Cages and Track is part of ST1 FFB Reception after truck receipt/grading and before sterilization. The station should support manual Cages Header input and a Cages Tipped Time grid. For the current MVP, it stands alone and does not require FK links to Weighbridge or Grading.

#### Load Cages and Track Data Form

Header fields:
- Date
- Tippler Start Time
- Tippler Stop Time
- Cages Out
- Cages Tipped
- Inputted By
- Checked By
- Acknowledged By
- Note

Cages Tipped Time detail row fields:
- Time
- Cages 1..N as dynamic cells
- Total Cages
- Number of Cages Remain
- Remarks, if required

Validation:
- Date should be required.
- Cages Out, Cages Tipped, Total Cages, and Number of Cages Remain must be numeric.
- PIC fields are manual text inputs for MVP.
- Cages 1..N must support a variable number of cage/lori columns without adding database columns.

Actions:
- `Find Cages Data`: searches/selects an existing Cages and Track record.
- Header: `Add`, `Save`, `Delete`, and `Data Preview`.

#### Cages and Track Monitor

Summary:
- Total Log Sheet
- Total Cages Out
- Total Cages Tipped
- Time Slot
- Tipped Cell Count
- Total Cages
- Number of Cages Remain

Actions:
- `Back`
- `Data Preview`, when enabled from saved records.

#### Cages and Track Completion

Actions:
- `Save`: persists current buffered cage/track records to local database.
- `Back`: returns to monitor/station page.

Open confirmation:
- Maximum displayed Cages 1..N columns and whether Cages cells are checkbox/boolean or value text must be confirmed.

### 6.8 Future Station: Sterilization

Sterilization is designed in the ERD/data model for later implementation and is
not part of the first MVP station implementation. The prepared form shape is
manual header and detail grid input.

Header fields:
- Date
- Inputted By
- Checked By
- Acknowledged By
- Note

Detail fields:
- Sterilizer No
- Close Door Time
- Peak 1 Time
- Exhaust 1 Time
- Peak 2 Time
- Exhaust 2 Time
- Peak 3 Time
- Exhaust 3 Time
- Open Door Time
- Duration (Minutes)
- Number of Cages
- Cages Status
- Checked by SPV
- Remarks

### 6.9 Future Station: Threshing

Threshing is designed in the ERD/data model for later implementation and is not
part of the first MVP station implementation. The prepared form shape is manual
header, time-slot detail grid, and operational target reference.

Header fields:
- Thresher ID
- Date
- Inputted By
- Checked By
- Acknowledged By
- Note

Detail fields:
- Time
- FFB Throughput (MT/hour)
- Thresher Drum Speed (RPM)
- Motor Current (Amps)
- Unstripped Bunch Count (%)
- Empty Bunch Oil Loss (%)
- Downtime Reason

Operational target reference fields:
- Parameter
- Standard Operational Target
- Action Plan on Deviation

### 6.10 Future Station: Pressing

Pressing is designed in the ERD/data model for later implementation and is not
part of the first MVP station implementation. The prepared form shape is manual
header, time-slot detail grid, and operating target reference.

Header fields:
- Presser ID
- Date
- Inputted By
- Checked By
- Acknowledged By
- Note

Detail fields:
- Time
- Digester Temp (C)
- Digester Level (%)
- Press Motor Current (Amps)
- Cone Hydraulic Pressure (Bar)
- Dilution Water Temp (C)
- Downtime Reason

Operating target reference fields:
- Parameter / Metric
- Target Operating Range
- Critical Trigger / Action Limit

### 6.11 Future Station: Depricarping

Depricarping is designed in the ERD/data model for later implementation and is
not part of the first MVP station implementation. The prepared form shape is
manual header, time-slot detail grid, and operating target reference.

Header fields:
- Presser ID, pending confirmation whether label should become Depricarper ID
- Date
- Inputted By
- Checked By
- Acknowledged By
- Note

Detail fields:
- Time
- Fan Static Pressure (mmH2O)
- Polishing Drum Speed (RPM)
- Air Velocity (m/s)
- Fibre Moisture (%)
- Kernel Recovery in Fibre (%)
- Nut Silo 1 Temp (C)
- Nut Silo 2 Temp (C)
- Downtime (Mins)
- Findings

Operating target reference fields:
- Parameter / Metric
- Target Range
- Critical Limit
- Operational Consequence / Justification

### 6.12 Future Station: Kernel Plant

Kernel Plant is designed in the ERD/data model for later implementation and is
not part of the first MVP station implementation. The prepared form shape is
manual header, time-slot detail grid, and operating target reference.

Header fields:
- Kernel Plant ID
- Date
- Inputted By
- Checked By
- Acknowledged By
- Note

Detail fields:
- Time
- Ripple Mill 1 (Amps)
- Ripple Mill 2 (Amps)
- Claybath/Hydro SG
- Kernel Silo 1 Temp (C)
- Kernel Silo 2 Temp (C)
- Kernel Moisture (%)
- Shell Loss (%)
- Downtime (Mins)
- Findings

Operating target reference fields:
- Equipment / Parameter
- Target Benchmark
- Corrective Action Plan

### 6.13 SRS Master Data Requirements

The SRS section 3.1 master data shall be represented explicitly and shall not
be stored only as labels inside operational transactions.

Required master domains:

1. **Station**
   - Mill, station code, station name, description, display order, number of
     machinery groups, and active status.
2. **Machinery Group**
   - Mill, station, group code, description, unit, workshop factor, cost per
     equipment, and number of equipment.
3. **Machinery / Equipment**
   - Mill, station, machinery group, equipment code, description, picture,
     registration number, make, model, equipment type, part number, serial
     number, gearbox, motor, mounting, RPM, chain, capacity, brand, year made,
     fixed asset code, control activity code, and owner item code.
4. **Machinery Insurance**
   - Machinery, ownership, policy number, insurance company, expiry date,
     premium, and amount insured.
5. **Machinery Tax / Purchase**
   - Machinery, purchase date, purchase cost, policy type, contact name,
     contact phone, contact fax, and contact email.

Business rules:

- One mill has many stations; one station has many machinery groups; one
  machinery group has many machineries.
- One machinery may have multiple insurance and tax/purchase history records.
- `no_of_machinery_group` and `no_of_equipment` are informational counts. The
  server should calculate them from child records or validate a synchronized
  cached value to prevent inconsistent totals.
- `machinery.station_id` and `machinery.mill_id`, when retained for offline
  filtering, must match the selected machinery group's station and mill.
- Picture fields store managed file references/URLs, not binary data directly
  in the master table.
- Mobile operators receive these masters as read-only synchronized data.
  Create/update actions are restricted to authorized Admin users on the server
  application.
- Vehicle, driver, Estate/Supplier/Buyer, division, and block remain plain text
  inputs for the operational MVP and are not part of this SRS master scope.

## 7. Monitoring and IoT Data Requirements

Monitoring and IoT fields in this section are prepared references from SRS and
device documents. For the first MVP, Weighbridge, Grading, and Cages Track must
work through manual input. IoT/device ingestion is not required.

### 7.1 Cages and Track / Loading Ramp

Monitoring nodes:
- Loading ramp door status: Open/Close.
- Loading ramp conveyor status/speed: Running/Stop and speed if available.
- Cage count.
- Estimated/entered FFB weight per cage.

Device references:
- FFB hopper: two sets of 23 bays, total 46 bays.
- Capacity: 12 tonne FFB per bay.
- Hydraulic door system.
- Horizontal FFB conveyor under ramp: two units, motor power 22 kW.
- S-path FFB distribution conveyor: one unit, motor power 18.5 kW.

### 7.2 Sterilization

Monitoring nodes:
- Active/non-active status of each sterilizer.
- Pressure level of each sterilizer.
- Temperature level of each sterilizer.
- Steam inlet valve: Open/Close.
- Exhaust valve: Open/Close.
- Condensate valve: Open/Close.
- Deaerate valve: Open/Close.
- Entrance door: Open/Close.
- Exit door: Open/Close.
- Processing cycle time.

Device references:
- Sterilizers: 2 units.
- Capacity: 4 cages x 12 tons per cage.
- Working pressure: 3.10 barg.
- Design code: ASME Boiler & Pressure Vessel Code Section VIII Div. 1.
- Safety relief valve set pressure: 3.5 bar.
- Temperature gauge range: 0-200 degrees C.

### 7.3 Tippler

Monitoring nodes:
- Tippler angle/position.
- FFB conveyor speed in RPM or meter/hour.
- Conveyor electricity current in Ampere.

Device references:
- Tippler: one unit.
- Function: tip, turn, and empty sterilized fruit cages.

### 7.4 Threshing

Monitoring nodes:
- Active/non-active status of each thresher.
- Rotation speed in RPM.
- Electricity current in Ampere.

Device references:
- Threshing machine: two units.
- Sterilized fruit bunch elevator and conveyors exist around this station.

### 7.5 Pressing

Monitoring nodes:
- Screw press unit ON/OFF.
- Screw press rotation speed in RPM.
- Screw press motor current in Ampere.
- Vertical conveyor ON/OFF.

Device references:
- Loose fruit bucket elevator: two units, capacity 30,500 kg/hour each.
- Digester feed conveyor and press/digester installation are part of station scope.

### 7.6 Clarification

Monitoring nodes:
- Active/non-active status of electro motors across clarification stages.
- Settling/buffer tank level in cm or percentage.
- Temperature where required by process.

Device references:
- Crude oil tank includes float level switch, temperature gauge, and temperature controller.
- Clarification process should support approximately 15-25 electro motor statuses.

### 7.7 Boiler Room and Engine Room

Later phases may provide log sheet entry and report capability. Exact monitoring fields should be finalized from current manual log sheets and site instrumentation. Prepare generic numeric/status field support for pressure, temperature, level, current, voltage, running hours, and remarks.

### 7.8 Kernel, Dispatch, Water, Waste, and Quality Stations

Later phases may provide station detail and report shell. Full field definitions should be finalized from manual log sheets and site stakeholders. The data model should allow station-specific configurable fields where the station forms are not yet finalized.

## 8. Data and Storage Requirements

### 8.1 Local Device Storage

1. App shall store offline drafts and saved transaction records locally.
2. Local retained transaction data may be auto-deleted after 7 days when obsolete
   and successfully synchronized, once sync workflow is enabled in a later phase.
3. Unsynchronized records must not be deleted.
4. App shall provide local record status per record:
   - Draft
   - Buffered
   - Saved local
   - Pending sync
   - Synced local server, later phase
   - Synced central server, later phase
   - Sync failed, later phase

### 8.2 Core Entities

MVP implementation entities:

- User
- Role
- Corporate
- Company
- BusinessUnit
- Station
- MachineryGroup
- Machinery
- MachineryInsurance
- MachineryTaxPurchase
- Weighbridge
- Grading
- GradingParameter
- GradingDetail
- CagesTrack
- CagesTrackTimeRow
- CagesTrackTimeCell
- Baseline
- Estimate
- SyncJob

Designed future station entities:

- Sterilization
- SterilizationDetail
- Threshing
- ThreshingDetail
- ThreshingOperationalTarget
- Pressing
- PressingDetail
- PressingOperationalTarget
- Depricarping
- DepricarpingDetail
- DepricarpingOperationalTarget
- KernelPlant
- KernelPlantDetail
- KernelPlantOperationalTarget
- Alert

Operational MVP tables must remain consistent with the current ERD names:
`weighbridges`, `gradings`, `grading_details`, `grading_parameters`,
`cages_tracks`, `cages_track_time_rows`, and `cages_track_time_cells`.

### 8.3 Operational Header Pattern

Implemented station headers should follow a simple manual-input pattern:

- id
- businessUnitCode
- stationId
- station-specific form number or station ID where shown on the form
- date
- inputtedBy, checkedBy, acknowledgedBy when shown on the form
- note
- createdAt
- updatedAt
- deletedAt

### 8.4 Operational Detail Pattern

Implemented station detail grids should use station-specific detail tables rather
than one generic measurement/session table. Time-slot station details should
include:

- id
- parent header id
- timeSlot
- station-specific numeric/text fields
- downtimeMinutes or downtimeReason where shown on the form
- findings or remarks where shown on the form
- createdAt
- updatedAt
- deletedAt

### 8.5 Operational Target Pattern

Stations with a reference target table in the form should store those references
as lightweight target tables with parameter/metric, target text, and corrective
or action-plan text. These references are seedable and editable by authorized
admin users.

Device registration/activation remains an authentication requirement from the
SRS, but it is handled outside this operational ERD and is not represented as a
core domain entity for the current MVP.

## 9. Synchronization Requirements

Synchronization is a prepared capability for MVP, not the primary delivery.
Saved mobile records should carry local status and stable IDs so they are ready
to be sent to an API later, but full sync orchestration, conflict handling, ERP
integration, and detailed sync support screens are later-phase scope.

### 9.1 Master Data Prepared Capability

Prepared later-phase flow:
1. ERP pushes or saves master data to central server staging database.
2. Central server syncs master data to local server by scheduler.
3. Local server syncs master data to mobile device through USB cable or Wi-Fi.
4. Master payloads shall include stable internal UUID and last-updated
   timestamp for idempotent upsert. Station may additionally include its
   existing active status.
5. Mobile shall treat synchronized Station and Machinery masters as read-only.
6. The sync order shall preserve dependencies: Mill -> Station -> Machinery
   Group -> Machinery -> Insurance/Tax-Purchase.

### 9.2 Transaction Data Prepared Capability

Prepared later-phase flow:
1. Mobile device syncs transactional data manually to local server via USB cable or Wi-Fi.
2. Mobile device may sync directly to central server via cellular network.
3. Local server syncs transaction data to central server by scheduler.
4. ERP pulls transaction data from central server staging database.

### 9.3 Sync Behavior

1. MVP records shall store local status such as Draft, Saved local, Pending sync,
   Synced, and Sync failed where useful.
2. MVP records shall use stable IDs/idempotency keys so later API submission can
   avoid duplicate records.
3. Full conflict resolution, sync logs, and support tooling are later-phase
   requirements.

## 10. Reports and Alerts

### 10.1 Reports

Basic web reporting is part of MVP as dashboard, filtered data table, detail
preview, and CSV/Excel export. Full PDF generation and template management are
reserved for a later phase unless final templates are approved before build.

Later-phase report module may support:
- Station process reports.
- Weighbridge batch report.
- Production process activity report.
- Estimates and baselines reports.
- Mill output summaries.
- PDF export.
- ERP upload templates where required.

### 10.2 WhatsApp/SMS Gateway Alerts

Prepare alert events for:
- 1st half milling output by station, machinery, and total mill.
- Full day milling output by station, machinery, and total mill.
- Restant by station and machinery.

Actual WhatsApp/SMS provider is not defined in the source documents and must be decided later.

## 11. Device and Platform Requirements

1. App must run on Android and iOS mobile devices.
2. App must work without active data connection for station input.
3. App should support GPS, camera photo, barcode scanner, and QR reader integration.
4. App should support tablets used in harsh mill environments.
5. App should restrict or detect risky device changes where possible:
   - Date/time changes.
   - Device setting changes.
   - Telephony/call/SMS interruptions, subject to OS limitations.

## 12. Security and Audit

1. Role-based access control is mandatory.
2. Master password/system admin role is required.
3. Password policy: minimum 6 characters, case-sensitive, supports alphanumeric and special characters.
4. Device activation code is required before app can be used.
5. Audit trail is parked for a later phase and is not included in the current operational ERD.
6. MVP role permissions:
   - Operator: input and edit own draft/saved records for assigned stations.
   - Supervisor: review saved station records, dashboard, data preview, and export.
   - Admin: manage users/roles, organization master data, station setup, and machinery master data.
7. Later-phase audit trail should log:
   - Login/logout.
   - Save/edit/delete of station records.
   - Save and report generation.
   - Edit/delete of saved records.
   - Sync attempts and failures.
   - Admin changes to users, access, master data, and activation codes.
8. Sensitive records must be protected at rest and in transit.

## 13. Non-Functional Requirements

### Usability
- UI must be usable by field operators wearing gloves or working in mill environments.
- Touch targets must be large.
- Screens should avoid dense controls where possible.
- Forms must clearly show required fields, validation errors, and save/sync status.

### Performance
- App launch and navigation should feel instant on mid-range Android devices.
- Offline forms must save locally without blocking on network.
- Station list and saved records should load within 2 seconds for normal datasets.

### Reliability
- No data loss when app is closed, connection drops, or device reboots.
- Later-phase sync should retry failed jobs.
- Later-phase PDF generation must not corrupt saved source data.

### Localization
- Support Indonesian and English labels where required.
- Confirm final terminology with operations team.

## 14. Acceptance Criteria

### MVP Acceptance

1. Operator can log in and access assigned station menus from mobile.
2. Operator can input and save Weighbridge records from mobile.
3. Operator can input and save Grading records from mobile, including Grading
   header, detail rows, and selected grading parameters.
4. Operator can input and save Cages Track records from mobile, including
   header, time rows, and dynamic cage cells.
5. Mobile app remains usable while offline for input and local save of
   Weighbridge, Grading, and Cages Track.
6. Saved mobile records can be viewed in web dashboard and data preview.
7. Supervisor can filter saved records by date, business unit/mill, and station.
8. Supervisor can export filtered saved records to CSV/Excel basic format.
9. Admin can manage user/role data and MVP master data for Corporate, Company,
   Business Unit/Mill, Station, Machinery Group, Machinery, Machinery Insurance,
   and Machinery Tax/Purchase.
10. No generic session/batch table is required for MVP.
11. Operational MVP tables remain consistent with ERD names: `weighbridges`,
    `gradings`, `grading_details`, `grading_parameters`, `cages_tracks`,
    `cages_track_time_rows`, and `cages_track_time_cells`.
12. App visually matches the Figma direction for the implemented MVP screens.

### Design Acceptance

1. Home screen must match Figma structure and visual hierarchy.
2. Station selector must use a 3-column red button grid on mobile/tablet layouts where screen width allows.
3. Inner pages must use light-green top header and breadcrumb/back controls.
4. MVP station forms for Weighbridge, Grading, and Cages Track must support long vertical scrolling and keep actions easy to access.
5. Text must not overlap or clip on typical target devices.

## 15. Implementation Guidance for Agent

1. Inspect the target codebase before choosing components, styling, or state management.
2. Implement shared reusable patterns:
   - AppHeader
   - BreadcrumbPills
   - MenuCard
   - StationGrid
   - StationDetail
   - StationForm
   - StationMonitor
   - ActionButtonRow
   - SyncStatusBadge
3. Build Weighbridge first as the canonical reference station, then build Grading and Cages Track using the same header/detail/data-preview pattern.
4. Keep future station schemas/design notes for Sterilization through Kernel Plant, but do not require their mobile/web screens in MVP implementation.
5. Use stable IDs for offline records and sync jobs.
6. Keep manual entry path fully functional even when IoT integration is absent.
7. Treat Figma asset URLs as temporary. Download/commit exact assets or replace with approved project assets.
8. Do not add Tailwind solely because Figma generated Tailwind code; match the existing project style system.

## 16. Open Questions

1. What is the target implementation stack: Flutter, React Native, native Android/iOS, or web/PWA?
2. Should the product name be `Mill Smart-Log`, `Mills Operation System`, or `Mill Management System` in the final app?
3. What exact CSV/Excel columns and dashboard KPIs are required for Weighbridge,
   Grading, and Cages Track MVP exports?
4. Are manual log sheet images/tables available as structured field definitions?
5. What ERP system and integration protocol will be used in later phases?
6. What WhatsApp/SMS provider should be used in later phases?
7. What exact device restrictions are feasible on target Android/iOS devices?
8. Should AI prompt be implemented now, hidden, or reserved for a later release?
9. Should `Solid Water Disposal` in Figma be corrected to `Solid Waste Disposal`?
10. What are final units and thresholds for each monitoring node?
11. Are Machinery Insurance and Machinery Tax/Purchase intended as historical
    one-to-many records, or exactly one current record per machinery?
12. Are `no_of_machinery_group` and `no_of_equipment` entered by Admin or always
    calculated by the system?
13. Which SRS master fields are authoritative from ERP, and which may be edited
    directly in Mill Smart-Log?
14. What are the unique-code rules: global across company, per mill, or per
    station for Station, Machinery Group, and Machinery?
