# SGI Semester Calculation Workflow - Updated

## Changes Made

### ✅ **What Changed:**
A new **"Credit Subjects"** step has been added to the workflow. This step allows students to add non-internal subjects (subjects without CAT marks) before proceeding to verification.

### 📋 **Updated Workflow Flow:**

```
1. Add/Edit Subjects (edit_subjects.php)
   - Add all internal subjects with CAT marks
   ↓
2. Enter CAT 1, 2, 3 Marks (cat1_marks.php, cat2_marks.php, cat3_marks.php)
   - Enter marks for all internal subjects
   ↓
3. Credit Subjects (credit_subjects.php) [NEW STEP]
   - Add non-internal subjects (optional)
   - View list of added non-internal subjects
   - Delete non-internal subjects if needed
   - Click "Confirm & Proceed to Verify" when done
   ↓
4. Verify & Confirm (verify_marks.php)
   - Review CAT marks summary
   - Enter Previous Semester GPA (out of 10)
   - Enter Current Semester Attendance %
   - Declare and confirm
   ↓
5. Final CA Marks + GPA/CGPA (final_ca_marks.php)
   - Enter Current GPA (out of 10)
   - Enter CGPA (out of 10)
   - Enter Final CA scores for each subject
   ↓
6. Calculate SGI (calculate_sgi.php)
   - Student enters Skills, Projects, Activities details
   - System calculates final SGI score
   ↓
7. View Results & Print Report (semester_detail.php, print_report.php)
```

## File Changes Summary

### 1. **credit_subjects.php** [NEW FILE]
- **Purpose:** Dedicated step for adding non-internal subjects
- **Features:**
  - Form to add non-internal subjects (Name, Code, Credits)
  - Table showing all added non-internal subjects
  - Delete button for each non-internal subject
  - "Confirm & Proceed to Verify" button to move to next step
- **Database:** Sets `credits_done = true` when confirmed

### 2. **verify_marks.php** [RESTORED]
- **Purpose:** Verify CAT marks and enter academic details
- **Features:**
  - Shows CAT marks summary for internal subjects
  - Enter Previous Semester GPA
  - Enter Current Semester Attendance
  - Declaration and confirmation
- **Database:** Sets `prev_gpa`, `attendance`, `verified = true`

### 3. **final_ca_marks.php**
- **Purpose:** Enter Final CA scores and current GPA/CGPA
- **Features:**
  - Enter Current GPA (out of 10)
  - Enter CGPA (out of 10)
  - Enter Final CA scores for each subject
- **Database:** Sets `gpa`, `cgpa`, `ca_done = true`

### 4. **semester_detail.php**
- **Updated:** Added `$creditsDone` variable
- **Updated:** Workflow buttons now include:
  - "Credit Subjects" (orange) - appears after all CATs are filled
  - "Verify & Confirm" (green) - appears after credits are done
  - "Final CA Marks" (blue) - appears after verification
  - "Calculate SGI" (purple) - appears after CA is done

## Benefits of This Change

1. **Clear Separation of Concerns:**
   - Internal subjects with CAT marks are managed separately
   - Non-internal subjects (without CAT) have their own dedicated step

2. **Flexibility:**
   - Students can add non-internal subjects even after completing all CAT marks
   - Non-internal subjects can be deleted if added by mistake

3. **Better Workflow:**
   - Each step has a clear, focused purpose
   - Visual progression with different colored buttons for each step

4. **Logical Order:**
   - Step 1-2: Handle internal subjects and CAT marks
   - Step 3: Add any remaining non-internal subjects
   - Step 4: Verify all academic data
   - Step 5: Enter final GPA/CGPA
   - Step 6: Calculate SGI

## Workflow Button Colors

| Step | Button Color | Purpose |
|------|-------------|---------|
| CAT 1/2/3 Marks | Orange (#e94560) | Enter CAT marks |
| Credit Subjects | Orange (#e94560) | Add non-internal subjects |
| Verify & Confirm | Green (#27ae60) | Verify CAT marks & enter Previous GPA/Attendance |
| Final CA Marks | Blue (#2980b9) | Enter GPA, CGPA & Final CA scores |
| Calculate SGI | Purple (#8e44ad) | Calculate final SGI |

## Subject Types

### Internal Subjects (internal = 'yes')
- Have CAT 1, CAT 2, CAT 3 marks
- Included in CAT calculations and charts
- Can only be edited before CAT 1 is submitted
- Edited via `edit_subjects.php`

### Non-Internal Subjects (internal = 'no')
- No CAT marks (display "No Internal")
- NOT included in CAT calculations
- Can be added in Credit Subjects step
- Can be deleted before verification
- Added via `credit_subjects.php`

## Testing Recommendations

1. Test adding a non-internal subject in the Credit Subjects step
2. Verify non-internal subjects appear in the subjects table with "No Internal"
3. Test deleting a non-internal subject
4. Verify the workflow buttons appear in correct order
5. Test the complete workflow from adding subjects to calculating SGI
6. Ensure SGI calculation works correctly with mixed subject types

## Database Fields

### Semester Document
```php
// After Credit Subjects step:
'credits_done' => true

// After Verify step:
'prev_gpa'    => float,
'attendance'  => float,
'verified'    => true

// After Final CA step:
'gpa'         => float,
'cgpa'        => float,
'ca_done'     => true

// After SGI calculation:
'sgi'              => float,
'academic_score'   => float,
'skills_score'     => float,
'projects_score'   => float,
'activities_score' => float,
'discipline_score' => float
```

### Subject Document
```php
// Internal subjects:
'internal' => 'yes',
'cat1'     => float,
'cat2'     => float,
'cat3'     => float,
...

// Non-internal subjects (added in Credit Subjects step):
'internal'   => 'no',
'cat1'       => null,
'cat2'       => null,
'cat3'       => null,
...
```

---

**Status:** ✅ All changes completed successfully
**Date:** 2026-06-19