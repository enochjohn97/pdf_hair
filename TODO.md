# Staff UI Restrictions - COMPLETE ✅

## Changes Applied

### 1. [x] assets/js/app.js

- Added `isStaff()` helper
- `renderOrderForm()`: Status select restricted to 'pending' only for staff
- `updateRoleUI()`:
  - Hides `#orders-date-to` input
  - Status filter shows only 'pending'
  - Greys date inputs with `staff-date-grey` class
  - Forces pending filter reload

### 2. [x] index.php

- Added `data-staff-hide` attribute to date_to input for JS targeting

### 3. [x] assets/css/style.css

- Added `.staff-date-grey` class: opacity 0.5, cursor not-allowed, grey background
- Added `.staff-mode input[type=date]` fallback styling

## Verification Steps

1. **Login as Staff**: role-select.php → staff@pdfhair.com / admin123
2. **Orders Page**:
   - Status filter: Only "Pending" option
   - Date toolbar: Only "From date" visible (greyed), "To date" hidden
   - Table shows only own pending orders
3. **New/Edit Order**: Status dropdown shows only "Pending"
4. **Switch roles** (admin→staff/manager): UI updates dynamically

## Result

✅ Staff view simplified: Pending orders only, single grey date filter, no other status options.
